<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PatientPortalController extends Controller
{
    private const PANORAMICA_KIND_ID = 15;

    public function showLogin()
    {
        return Inertia::render('PatientPortal/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'rut'      => ['required', 'string'],
            'orden_id' => ['required', 'integer', 'min:1'],
        ], [
            'rut.required'      => 'El RUT es requerido.',
            'orden_id.required' => 'El número de orden es requerido.',
            'orden_id.integer'  => 'El número de orden debe ser un número.',
            'orden_id.min'      => 'El número de orden no es válido.',
        ]);

        $ordenId = (int) $request->input('orden_id');
        $rut     = strtoupper(preg_replace('/[^0-9K]/', '', (string) $request->input('rut')));

        if ($rut === '') {
            return back()->withErrors(['rut' => 'Ingrese un RUT válido.']);
        }

        $rutDb   = "REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '')";
        $patient = DB::table('patients')
            ->where(function ($q) use ($rut, $rutDb) {
                $q->whereRaw("{$rutDb} = ?", [$rut])
                  ->orWhereRaw("LEFT({$rutDb}, CHAR_LENGTH({$rutDb}) - 1) = ?", [$rut]);
            })
            ->first(['id', 'name', 'rut']);

        if (!$patient) {
            return back()->withErrors(['rut' => 'El RUT ingresado no está registrado.']);
        }

        $order = DB::table('orders')
            ->where('id', $ordenId)
            ->where('patient_id', $patient->id)
            ->first(['id', 'estadoradiologo']);

        if (!$order) {
            return back()->withErrors([
                'orden_id' => "No se encontró la orden N° {$ordenId} para el RUT ingresado.",
            ]);
        }

        session([
            'paciente_portal' => [
                'order_id'   => $order->id,
                'patient_id' => $patient->id,
                'rut'        => $rut,
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ]);

        return redirect()->route('paciente.show', $order->id);
    }

    public function show(Request $request, int $id)
    {
        $portal = session('paciente_portal');

        if (!$portal
            || (int) $portal['order_id'] !== $id
            || Carbon::parse($portal['expires_at'])->isPast()
        ) {
            return redirect()->route('paciente.login')
                ->withErrors(['session' => 'Sesión expirada. Ingresa nuevamente.']);
        }

        $order = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->where('o.id', $id)
            ->select('o.*', 'uc.name as clinica_nombre')
            ->first();

        abort_if(!$order, 404);

        $pacienteRow = DB::table('patients')
            ->where('id', $order->patient_id)
            ->first(['name', 'rut', 'dateofbirth', 'email', 'celphone', 'housephone']);

        $odontologoRow = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $order->odontologo_id)
            ->first(['u.name as nombre']);

        $isInformada = (int) $order->estadoradiologo === 1;

        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $id)
            ->select([
                'examinations.id as examination_id',
                'kinds.id as kind_id',
                'kinds.descipcion as descripcion',
                'kinds.group as grupo',
                'examinations.piezas',
            ])
            ->get()
            ->map(function ($e) use ($isInformada) {
                $archivosQ = DB::table('files')
                    ->where('examination_id', $e->examination_id);
                // Si la orden ya fue informada, el paciente ve todos los archivos
                // (incluidos trazados y cortes 3D subidos durante el informar).
                // Antes de ser informada, solo ve los archivos originales.
                if (!$isInformada) {
                    $archivosQ->where('desde_informar', '!=', 1);
                }
                $archivos = $archivosQ->get(['id', 'name', 'ruta', 'extension', 'file_size'])
                    ->map(fn ($f) => [
                        'id'           => $f->id,
                        'name'         => $f->name,
                        'extension'    => strtolower((string) $f->extension),
                        'url'          => $this->signedUrl($f->ruta),
                        'download_url' => $this->downloadUrl($f->ruta, $f->name),
                    ]);

                $respuesta = null;
                if ($isInformada) {
                    $ans = DB::table('answers')
                        ->where('examination_id', $e->examination_id)
                        ->first();

                    if ($ans) {
                        $ansArr = (array) $ans;
                        $respuesta = [
                            'campo_1'      => $ans->campo_1,
                            'campo_2'      => $ans->campo_2,
                            'campo_3'      => $ans->campo_3,
                            'campo_4'      => $ans->campo_4,
                            'campo_5'      => $ans->campo_5,
                            'campo_6'      => $ans->campo_6,
                            'campo_7'      => $ans->campo_7,
                            'campo_8'      => $ans->campo_8,
                            'campo_9'      => $ans->campo_9,
                            'solo_adjunto' => (bool) ($ans->solo_adjunto ?? false),
                        ];
                        // Dientes individuales
                        foreach ($ansArr as $key => $val) {
                            if (str_starts_with($key, 'diente_')) {
                                $respuesta[$key] = $val;
                            }
                        }
                        // Panorámica legacy JSON
                        if ($e->kind_id == self::PANORAMICA_KIND_ID && !empty($ans->content)) {
                            $c = json_decode($ans->content, true) ?? [];
                            $respuesta['informe_examen']    = $c['examen']    ?? '';
                            $respuesta['informe_libre']     = $c['informe']   ?? '';
                            $respuesta['informe_impresion'] = $c['impresion'] ?? '';
                        }
                    }
                }

                return [
                    'id'          => $e->examination_id,
                    'kind_id'     => $e->kind_id,
                    'descripcion' => $e->descripcion,
                    'grupo'       => (int) $e->grupo,
                    'piezas'      => $e->piezas,
                    'archivos'    => $archivos,
                    'respuesta'   => $respuesta,
                ];
            });

        $estado = match((int) $order->estadoradiologo) {
            1       => ['label' => 'Informada',     'color' => 'success'],
            2       => ['label' => 'En corrección', 'color' => 'warning'],
            4       => ['label' => 'Guardada',      'color' => 'secondary'],
            default => ['label' => 'En proceso',    'color' => 'warning'],
        };

        return Inertia::render('PatientPortal/Show', [
            'order' => [
                'id'              => $order->id,
                'diagnostico'     => $order->diagnostico,
                'observaciones'   => $order->observaciones,
                'prioridad'       => $order->prioridad,
                'estadoradiologo' => (int) $order->estadoradiologo,
                'estado'          => $estado,
                'created_at'      => $order->created_at
                    ? Carbon::parse($order->created_at)->format('d/m/Y') : null,
                'respondida'      => $order->respondida
                    ? Carbon::parse($order->respondida)->format('d/m/Y') : null,
                'clinica'         => $order->clinica_nombre,
            ],
            'paciente'   => $pacienteRow ? [
                'name'        => $pacienteRow->name,
                'rut'         => $pacienteRow->rut,
                'dateofbirth' => $pacienteRow->dateofbirth
                    ? Carbon::parse($pacienteRow->dateofbirth)->format('d/m/Y') : null,
            ] : null,
            'odontologo' => $odontologoRow?->nombre,
            'examenes'   => $examenes,
            'pdfUrl'     => $isInformada ? route('paciente.pdf', $id) : null,
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $portal = session('paciente_portal');

        if (!$portal
            || (int) $portal['order_id'] !== $id
            || Carbon::parse($portal['expires_at'])->isPast()
        ) {
            return redirect()->route('paciente.login');
        }

        $order = DB::table('orders')->where('id', $id)->first();
        abort_if(!$order || (int) $order->estadoradiologo !== 1, 404);

        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $id)
            ->select([
                'examinations.id as examination_id',
                'kinds.id as kind_id',
                'kinds.descipcion as descripcion',
                'examinations.piezas',
            ])
            ->get()
            ->map(function ($e) {
                $ans       = DB::table('answers')->where('examination_id', $e->examination_id)->first();
                $respuesta = $ans ? (array) $ans : null;
                if ($respuesta && $e->kind_id == self::PANORAMICA_KIND_ID && !empty($ans->content)) {
                    $c = json_decode($ans->content, true) ?? [];
                    $respuesta['informe_examen']    = $c['examen']    ?? '';
                    $respuesta['informe_libre']     = $c['informe']   ?? '';
                    $respuesta['informe_impresion'] = $c['impresion'] ?? '';
                }
                return [
                    'descripcion' => $e->descripcion,
                    'kind_id'     => $e->kind_id,
                    'piezas'      => $e->piezas,
                    'respuesta'   => $respuesta,
                ];
            });

        $paciente   = DB::table('patients')->where('id', $order->patient_id)->first();
        $clinica    = DB::table('clinics as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $order->clinic_id)
            ->value('u.name');
        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $id)
            ->select('s.id', 'u.name', 's.firma')
            ->get()->unique('id')->values()
            ->map(function ($rad) {
                $rad->firma_b64 = null;
                if (!empty($rad->firma)) {
                    try {
                        $content        = Storage::disk('public')->get($rad->firma);
                        $ext            = strtolower(pathinfo($rad->firma, PATHINFO_EXTENSION));
                        $mime           = match($ext) {
                            'png'  => 'image/png',
                            'gif'  => 'image/gif',
                            default => 'image/jpeg',
                        };
                        $rad->firma_b64 = 'data:' . $mime . ';base64,' . base64_encode($content);
                    } catch (\Throwable) {}
                }
                return $rad;
            });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden', compact(
            'order', 'paciente', 'clinica', 'radiologos', 'examenes'
        ));

        return $pdf->stream("informe-{$id}.pdf");
    }

    public function logout(Request $request)
    {
        $request->session()->forget('paciente_portal');
        return redirect()->route('paciente.login');
    }

    private function signedUrl(?string $ruta): ?string
    {
        if (!$ruta || $ruta === '0') return null;
        try {
            return Storage::disk('s3')->temporaryUrl($ruta, now()->addHours(2));
        } catch (\Throwable) {
            return null;
        }
    }

    private function downloadUrl(?string $ruta, ?string $name = null): ?string
    {
        if (!$ruta || $ruta === '0') return null;
        try {
            return Storage::disk('s3')->temporaryUrl($ruta, now()->addHours(2), [
                'ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($name ?: 'archivo') . '"',
            ]);
        } catch (\Throwable) {
            return $this->signedUrl($ruta);
        }
    }
}
