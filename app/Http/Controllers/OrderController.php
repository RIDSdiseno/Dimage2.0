<?php

namespace App\Http\Controllers;

use App\Mail\OrdenAsignada;
use App\Models\Clinic;
use App\Models\Examination;
use App\Models\Kind;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public const ESTADOS = [
        0 => ['label' => 'No Informada', 'color' => 'warn'],
        1 => ['label' => 'Informada', 'color' => 'success'],
        2 => ['label' => 'Corrección', 'color' => 'danger'],
        4 => ['label' => 'Guardada', 'color' => 'secondary'],
    ];

    public function index(): Response
    {
        return Inertia::render('Orders/Index');
    }

    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();

        $term    = trim((string) $request->get('q', ''));
        $estado  = $request->get('estado', '');
        $soloMis = filter_var($request->get('solo_mis', false), FILTER_VALIDATE_BOOLEAN);
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;

        $query = Order::query()
            ->select([
                'orders.id',
                'orders.created_at',
                'orders.enviada',
                'orders.respondida',
                'orders.estadoradiologo',
                'orders.estadoodontologo',
                'orders.prioridad',
                'patients.name as paciente',
                'patients.rut as rut',
                'uc.name as clinica',
                'uo.name as odontologo',
            ])
            ->addSelect(DB::raw("
                (
                    SELECT GROUP_CONCAT(DISTINCT k.descipcion ORDER BY k.descipcion SEPARATOR ', ')
                    FROM examination_order eo
                    INNER JOIN examinations ex ON ex.id = eo.examination_id
                    INNER JOIN kinds k ON k.id = ex.kind_id
                    WHERE eo.order_id = orders.id
                ) as tipo_examen
            "))
            ->addSelect(DB::raw("
                (
                    SELECT GROUP_CONCAT(DISTINCT us.name ORDER BY us.name SEPARATOR ', ')
                    FROM order_staff_exam ose
                    INNER JOIN staffs s ON s.id = ose.staff_id
                    INNER JOIN users us ON us.id = s.user_id
                    WHERE ose.order_id = orders.id
                ) as radiologos
            "))
            ->join('patients', 'orders.patient_id', '=', 'patients.id')
            ->join('clinics as c', 'orders.clinic_id', '=', 'c.id')
            ->join('users as uc', 'c.user_id', '=', 'uc.id')
            ->leftJoin('staffs as od', 'orders.odontologo_id', '=', 'od.id')
            ->leftJoin('users as uo', 'od.user_id', '=', 'uo.id');

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('patients.name', 'like', "%{$term}%")
                    ->orWhere('patients.rut', 'like', "%{$term}%")
                    ->orWhere('orders.id', 'like', "%{$term}%")
                    ->orWhere('uc.name', 'like', "%{$term}%")
                    ->orWhere('uo.name', 'like', "%{$term}%");
            });
        }

        if ($estado !== '' && is_numeric($estado)) {
            $query->where('orders.estadoradiologo', (int) $estado);
        }

        $this->applyRoleFilter($query, $user);

        if ($soloMis && $user) {
            $this->applyMisOrdenesFilter($query, $user);
        }

        $orders = $query
            ->orderByDesc('orders.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($orders->items())->map(function ($o) {
            return [
                'id' => $o->id,
                'paciente' => $o->paciente,
                'rut' => $o->rut,
                'clinica' => $o->clinica,
                'odontologo' => $o->odontologo ?: '-',
                'radiologos' => $o->radiologos ?: '-',
                'tipo_examen' => $o->tipo_examen ?: '-',
                'created_at' => $o->created_at ? Carbon::parse($o->created_at)->format('d/m/Y') : '-',
                'enviada' => $o->enviada ? Carbon::parse($o->enviada)->format('d/m/Y') : '-',
                'respondida' => $o->respondida ? Carbon::parse($o->respondida)->format('d/m/Y') : '-',
                'estado' => self::ESTADOS[(int) $o->estadoradiologo] ?? ['label' => 'Desconocido', 'color' => 'secondary'],
                'prioridad' => $o->prioridad,
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $orders->total(),
            'pages' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ]);
    }

    public function create(): Response
    {
        $user = Auth::user();

        $kinds = Kind::query()
            ->orderBy('group')
            ->orderBy('id')
            ->get(['id', 'descipcion', 'group']);

        $groupNames = [
            '1' => 'Adulto',
            '2' => 'Niño',
            '3' => 'General',
            '4' => '3D',
        ];

        $examTypes = $kinds
            ->groupBy('group')
            ->map(function (Collection $items, $group) use ($groupNames) {
                return [
                    'label' => $groupNames[(string) $group] ?? "Grupo {$group}",
                    'items' => $items->map(fn ($k) => [
                        'id' => $k->id,
                        'label' => $k->descipcion,
                    ])->values(),
                ];
            })
            ->values();

        $clinics = $this->clinicsForUser($user)
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->user->name ?? "Clínica #{$c->id}",
                ];
            })
            ->values();

        return Inertia::render('Orders/Create', [
            'examTypes' => $examTypes,
            'clinics' => $clinics,
        ]);
    }

    public function getPatients(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        $clinicId = $request->get('clinic_id');

        $query = Patient::query()->select('id', 'name', 'rut');

        if ($clinicId) {
            $query->whereHas('clinics', function (Builder $q) use ($clinicId): void {
                $q->where('clinics.id', $clinicId);
            });
        }

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('rut', 'like', "%{$term}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(30)->get()
        );
    }

    public function getOdontologos(Request $request): JsonResponse
    {
        $clinicId = (int) $request->get('clinic_id');

        if (!$clinicId) {
            return response()->json([]);
        }

        $odontologos = DB::table('staffs')
            ->select('staffs.id', 'users.name')
            ->join('users', 'staffs.user_id', '=', 'users.id')
            ->join('clinic_staff', 'clinic_staff.staff_id', '=', 'staffs.id')
            ->where('staffs.type_staff', 6)
            ->where('clinic_staff.clinic_id', $clinicId)
            ->groupBy('staffs.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json($odontologos);
    }

    public function getRadiologos(Request $request): JsonResponse
    {
        $clinicId = (int) $request->get('clinic_id');

        if (!$clinicId) {
            return response()->json([]);
        }

        $radiologos = DB::table('staffs')
            ->select('staffs.id', 'users.name')
            ->join('users', 'staffs.user_id', '=', 'users.id')
            ->join('clinic_staff', 'clinic_staff.staff_id', '=', 'staffs.id')
            ->where('staffs.type_staff', 3)
            ->where('clinic_staff.clinic_id', $clinicId)
            ->groupBy('staffs.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json($radiologos);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'clinic_id' => ['required', 'exists:clinics,id'],
            'odontologo_id' => ['nullable', 'exists:staffs,id'],
            'radiologo_id' => ['nullable', 'exists:staffs,id'],
            'diagnostico' => ['required', 'min:3'],
            'prioridad' => ['required', 'in:Normal,Urgente'],
            'examenes' => ['required', 'array', 'min:1'],
            'examenes.*' => ['required', 'exists:kinds,id'],
            'action' => ['required', 'in:guardar,enviar'],
        ]);

        $user = Auth::user();
        $enviar = $request->action === 'enviar';
        $odontologoId = $request->odontologo_id;

        if ($user->hasRole('odontologo') && $user->staff) {
            $odontologoId = $user->staff->id;
        }

        DB::transaction(function () use ($request, $enviar, $odontologoId): void {
            $order = Order::create([
                'patient_id' => $request->patient_id,
                'clinic_id' => $request->clinic_id,
                'odontologo_id' => $odontologoId ?: 0,
                'radiologo_id' => $request->radiologo_id ?: 0,
                'diagnostico' => $request->diagnostico,
                'observaciones' => $request->observaciones ?? '',
                'observaciones_2' => $request->observaciones_2 ?? '',
                'prioridad' => $request->prioridad,
                'estadoradiologo' => $enviar ? 0 : 4,
                'estadoodontologo' => $enviar ? 0 : 1,
                'enviada' => $enviar ? now() : null,
                'sin_diagnostico' => 0,
            ]);

            foreach ($request->examenes as $kindId) {
                $examination = Examination::create([
                    'kind_id' => $kindId,
                    'order_id' => $order->id,
                ]);

                if (method_exists($order, 'examinations')) {
                    $order->examinations()->syncWithoutDetaching([$examination->id]);
                } else {
                    DB::table('examination_order')->insert([
                        'order_id' => $order->id,
                        'examination_id' => $examination->id,
                    ]);
                }

                $fileKey = "files_{$kindId}";
                if (!$request->hasFile($fileKey)) {
                    continue;
                }

                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) {
                        continue;
                    }

                    $path = $file->store("ordenes/{$order->id}", 's3');

                    DB::table('files')->insert([
                        'ruta' => $path,
                        'examination_id' => $examination->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'name' => $file->getClientOriginalName(),
                        'type_id' => 0,
                        'extension' => strtolower((string) $file->getClientOriginalExtension()),
                        'ruta_dcm' => null,
                        'nombre_dcm' => null,
                        'file_size' => (int) $file->getSize(),
                        'file_size_procesed' => 1,
                        'file_size_error' => null,
                        'desde_informar' => 0,
                    ]);
                }
            }

            if ($enviar && $request->filled('radiologo_id')) {
                DB::table('order_staff_exam')->insert([
                    'order_id' => $order->id,
                    'staff_id' => $request->radiologo_id,
                    'group_exam' => 1,
                    'respondida' => 0,
                ]);
            }

            if (method_exists($order, 'staff')) {
                $staffIds = array_values(array_filter([
                    $odontologoId,
                    $request->radiologo_id,
                ]));

                if (!empty($staffIds)) {
                    $order->staff()->sync($staffIds);
                }
            }
        });

        if ($enviar && $request->filled('radiologo_id')) {
            $this->notificarRadiologo((int) $request->radiologo_id);
        }

        return redirect()
            ->route('ordenes.index')
            ->with('success', $enviar ? '¡Orden enviada al radiólogo!' : '¡Orden guardada!');
    }

    public function show(Order $order): Response
    {
        $user = Auth::user();

        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select([
                'examinations.id as examination_id',
                'kinds.id as kind_id',
                'kinds.descipcion as descripcion',
                'kinds.group as grupo',
                'examinations.url_texto',
            ])
            ->get()
            ->map(function ($e) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->where('desde_informar', '!=', 1)
                    ->get(['id', 'name', 'ruta', 'ruta_dcm', 'nombre_dcm', 'extension', 'file_size'])
                    ->map(fn ($f) => array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]));

                $respuesta = DB::table('answers')
                    ->where('examination_id', $e->examination_id)
                    ->first(['id', 'campo_1', 'solo_adjunto']);

                $archivosInforme = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->where('desde_informar', 1)
                    ->get(['id', 'name', 'ruta', 'extension', 'file_size'])
                    ->map(fn ($f) => array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]));

                return [
                    'id'          => $e->examination_id,
                    'kind_id'     => $e->kind_id,
                    'descripcion' => $e->descripcion,
                    'grupo'       => $e->grupo,
                    'url_texto'   => $e->url_texto,
                    'archivos'    => $archivos,
                    'archivos_informe' => $archivosInforme,
                    'respuesta'   => $respuesta ? [
                        'texto'       => $respuesta->campo_1,
                        'solo_adjunto'=> (bool) $respuesta->solo_adjunto,
                    ] : null,
                ];
            });

        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $order->id)
            ->select('s.id', 'u.name', 'ose.respondida')
            ->get();

        $odontologoRow = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $order->odontologo_id)
            ->first(['u.name as nombre', 's.rut']);

        $clinica = DB::table('clinics as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.id', $order->clinic_id)
            ->value('u.name');

        $pacienteRow = DB::table('patients')
            ->where('id', $order->patient_id)
            ->first(['name', 'rut', 'dateofbirth', 'email', 'housephone', 'celphone']);

        $edad = null;
        if ($pacienteRow && $pacienteRow->dateofbirth) {
            $edad = Carbon::parse($pacienteRow->dateofbirth)->age;
        }

        $paciente = $pacienteRow ? [
            'name'        => $pacienteRow->name,
            'rut'         => $pacienteRow->rut,
            'email'       => $pacienteRow->email,
            'telefono'    => $pacienteRow->celphone ?: $pacienteRow->housephone,
            'dateofbirth' => $pacienteRow->dateofbirth
                ? Carbon::parse($pacienteRow->dateofbirth)->format('d/m/Y') : null,
            'edad'        => $edad,
        ] : null;

        $correcciones = DB::table('corrections')
            ->where('order_id', $order->id)
            ->orderBy('created_at')
            ->get(['id', 'detalle', 'enviada', 'respondida']);

        $estado = self::ESTADOS[(int) $order->estadoradiologo] ?? [
            'label' => 'Desconocido', 'color' => 'secondary',
        ];

        $puedeResponder = $user->hasAnyRole(['radiologo', 'tecnico'])
            && (int) $order->estadoradiologo === 0
            && $radiologos->contains('id', $user->staff?->id);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id'              => $order->id,
                'diagnostico'     => $order->diagnostico,
                'observaciones'   => $order->observaciones,
                'observaciones_2' => $order->observaciones_2,
                'prioridad'       => $order->prioridad,
                'estadoradiologo' => $order->estadoradiologo,
                'estadoodontologo'=> $order->estadoodontologo,
                'sin_diagnostico' => $order->sin_diagnostico,
                'created_at'      => $order->created_at ? Carbon::parse($order->created_at)->format('d/m/Y H:i') : null,
                'enviada'         => $order->enviada    ? Carbon::parse($order->enviada)->format('d/m/Y H:i') : null,
                'respondida'      => $order->respondida ? Carbon::parse($order->respondida)->format('d/m/Y H:i') : null,
                'tiempo_respuesta'=> (function () use ($order) {
                    if (!$order->respondida || !$order->created_at) return null;
                    $diff = Carbon::parse($order->created_at)->diff(Carbon::parse($order->respondida));
                    $parts = [];
                    if ($diff->d) $parts[] = $diff->d . ' día' . ($diff->d > 1 ? 's' : '');
                    if ($diff->h) $parts[] = $diff->h . 'h';
                    if ($diff->i && !$diff->d) $parts[] = $diff->i . 'min';
                    return implode(' ', $parts) ?: '< 1 min';
                })(),
                'estado'          => $estado,
            ],
            'paciente'       => $paciente,
            'clinica'        => $clinica,
            'odontologo'     => $odontologoRow,
            'radiologos'     => $radiologos,
            'correcciones'   => $correcciones,
            'examenes'       => $examenes,
            'puedeResponder' => $puedeResponder,
            'esAdmin'        => $user->type_id === 1 || $user->hasRole('admin'),
            'esRadiologo'    => $user->type_id === 5 || $user->hasRole('radiologo'),
        ]);
    }

    // ── Responder orden ───────────────────────────────────────────────────

    public function responder(Order $order): Response|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['radiologo', 'tecnico'])) {
            return redirect()->route('ordenes.show', $order)->with('error', 'Sin permiso para responder órdenes.');
        }

        if ((int) $order->estadoradiologo === 1) {
            return redirect()->route('ordenes.show', $order)->with('error', 'La orden ya fue respondida.');
        }

        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select([
                'examinations.id as examination_id',
                'kinds.id as kind_id',
                'kinds.descipcion as descripcion',
                'kinds.group as grupo',
            ])
            ->get()
            ->map(function ($e) {
                $respuesta = DB::table('answers')
                    ->where('examination_id', $e->examination_id)
                    ->first(['id', 'texto', 'url_texto']);

                $archivos = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->get(['id', 'name', 'ruta', 'extension', 'file_size']);

                return [
                    'id' => $e->examination_id,
                    'kind_id' => $e->kind_id,
                    'descripcion' => $e->descripcion,
                    'grupo' => $e->grupo,
                    'archivos' => $archivos,
                    'respuesta' => $respuesta,
                ];
            });

        $paciente = DB::table('patients')->where('id', $order->patient_id)->first(['name', 'rut', 'dateofbirth']);
        $clinica  = DB::table('clinics as c')->join('users as u', 'u.id', '=', 'c.user_id')
                      ->where('c.id', $order->clinic_id)->value('u.name');

        return Inertia::render('Orders/Respond', [
            'order' => [
                'id'           => $order->id,
                'diagnostico'  => $order->diagnostico,
                'observaciones'=> $order->observaciones,
                'prioridad'    => $order->prioridad,
                'created_at'   => $order->created_at ? Carbon::parse($order->created_at)->format('d/m/Y H:i') : null,
                'enviada'      => $order->enviada    ? Carbon::parse($order->enviada)->format('d/m/Y H:i') : null,
            ],
            'paciente' => $paciente,
            'clinica'  => $clinica,
            'examenes' => $examenes,
        ]);
    }

    public function doResponder(Request $request, Order $order): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['radiologo', 'tecnico'])) {
            return redirect()->route('ordenes.show', $order)->with('error', 'Sin permiso.');
        }

        $request->validate([
            'respuestas'         => ['required', 'array', 'min:1'],
            'respuestas.*.id'    => ['required', 'exists:examinations,id'],
            'respuestas.*.texto' => ['required', 'string', 'min:5'],
        ]);

        DB::transaction(function () use ($request, $order, $user): void {
            foreach ($request->respuestas as $r) {
                $examinationId = $r['id'];
                $texto = $r['texto'];

                // Upsert answer
                $existing = DB::table('answers')->where('examination_id', $examinationId)->first();
                if ($existing) {
                    DB::table('answers')->where('id', $existing->id)->update([
                        'campo_1'    => $texto,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('answers')->insert([
                        'examination_id' => $examinationId,
                        'campo_1'        => $texto,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }

                // Upload informe files if provided
                $fileKey = "archivos_{$examinationId}";
                if ($request->hasFile($fileKey)) {
                    foreach ((array) $request->file($fileKey) as $file) {
                        if (!$file) continue;
                        $path = $file->store("informes/{$order->id}", 's3');
                        DB::table('files')->insert([
                            'ruta'              => $path,
                            'examination_id'    => $examinationId,
                            'name'              => $file->getClientOriginalName(),
                            'type_id'           => 1,
                            'extension'         => strtolower($file->getClientOriginalExtension()),
                            'ruta_dcm'          => null,
                            'nombre_dcm'        => null,
                            'file_size'         => (int) $file->getSize(),
                            'file_size_procesed'=> 1,
                            'file_size_error'   => null,
                            'desde_informar'    => 1,
                            'created_at'        => now(),
                            'updated_at'        => now(),
                        ]);
                    }
                }
            }

            // Mark order as responded
            $order->update([
                'estadoradiologo'  => 1,
                'estadoodontologo' => 1,
                'respondida'       => now(),
            ]);

            // Mark order_staff_exam as respondida
            if ($user->staff) {
                DB::table('order_staff_exam')
                    ->where('order_id', $order->id)
                    ->where('staff_id', $user->staff->id)
                    ->update(['respondida' => 1]);
            }
        });

        return redirect()->route('ordenes.show', $order)->with('success', '¡Orden respondida correctamente!');
    }

    public function pdf(Order $order): \Illuminate\Http\Response
    {
        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as examination_id', 'kinds.descipcion as descripcion'])
            ->get()
            ->map(function ($e) {
                $respuesta = DB::table('answers')->where('examination_id', $e->examination_id)->first();
                return ['descripcion' => $e->descripcion, 'respuesta' => $respuesta?->texto ?? ''];
            });

        $paciente  = DB::table('patients')->where('id', $order->patient_id)->first();
        $clinica   = DB::table('clinics as c')->join('users as u', 'u.id', '=', 'c.user_id')
                       ->where('c.id', $order->clinic_id)->value('u.name');
        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $order->id)
            ->pluck('u.name');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden', [
            'order'      => $order,
            'paciente'   => $paciente,
            'clinica'    => $clinica,
            'radiologos' => $radiologos,
            'examenes'   => $examenes,
        ]);

        return $pdf->download("orden-{$order->id}.pdf");
    }

    private function clinicsForUser($user): Collection
    {
        if (!$user) {
            return collect();
        }

        if ($user->hasAnyRole(['admin', 'secretaria', 'contralor'])) {
            return Clinic::with('user')
                ->orderBy('id')
                ->get();
        }

        if ($user->hasRole('holding') && $user->holding) {
            return Clinic::with('user')
                ->where('holding_id', $user->holding->id)
                ->orderBy('id')
                ->get();
        }

        if ($user->hasRole('clinica') && $user->clinic) {
            return Clinic::with('user')
                ->where('holding_id', $user->clinic->holding_id)
                ->orderBy('id')
                ->get();
        }

        if ($user->hasAnyRole(['odontologo', 'tecnico', 'radiologo']) && $user->staff) {
            $clinicIds = $this->clinicIdsForStaff((int) $user->staff->id);

            if ($clinicIds->isEmpty()) {
                return collect();
            }

            return Clinic::with('user')
                ->whereIn('id', $clinicIds->all())
                ->orderBy('id')
                ->get();
        }

        return collect();
    }

    private function applyMisOrdenesFilter(Builder $query, $user): void
    {
        // Radiólogo → órdenes donde está asignado (ya cubierto por applyRoleFilter, sin cambio)
        if ($user->hasRole('radiologo')) {
            return;
        }

        // Odontólogo / Técnico → solo órdenes donde es el odontólogo asignado directamente
        if ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $query->where('orders.odontologo_id', $user->staff->id);
            return;
        }

        // Clínica → solo órdenes de su clínica específica (no todo el holding)
        if ($user->hasRole('clinica') && $user->clinic) {
            $query->where('orders.clinic_id', $user->clinic->id);
            return;
        }

        // Admin / Secretaria / Holding → sin filtro adicional (ven todo)
    }

    private function applyRoleFilter(Builder $query, $user): void
    {
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        if ($user->hasAnyRole(['admin', 'secretaria', 'contralor'])) {
            return;
        }

        if ($user->hasRole('clinica') && $user->clinic) {
            $query->where('c.holding_id', $user->clinic->holding_id);
            return;
        }

        if ($user->hasRole('holding') && $user->holding) {
            $query->where('c.holding_id', $user->holding->id);
            return;
        }

        if ($user->hasRole('radiologo') && $user->staff) {
            $staffId = (int) $user->staff->id;

            $query->whereExists(function ($sub) use ($staffId) {
                $sub->select(DB::raw(1))
                    ->from('order_staff_exam as ose')
                    ->whereColumn('ose.order_id', 'orders.id')
                    ->where('ose.staff_id', $staffId);
            })->where('orders.estadoradiologo', '!=', 4);

            return;
        }

        if ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $clinicIds = $this->clinicIdsForStaff((int) $user->staff->id);

            if ($clinicIds->isEmpty()) {
                $query->whereRaw('1 = 0');
                return;
            }

            $holdingIds = Clinic::query()
                ->whereIn('id', $clinicIds->all())
                ->pluck('holding_id')
                ->filter()
                ->unique()
                ->values();

            if ($holdingIds->isEmpty()) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereIn('c.holding_id', $holdingIds->all());
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function clinicIdsForStaff(int $staffId): Collection
    {
        return DB::table('clinic_staff')
            ->where('staff_id', $staffId)
            ->pluck('clinic_id')
            ->filter()
            ->unique()
            ->values();
    }

    // ── Editar orden ──────────────────────────────────────────────────────

    public function edit(Order $order): Response|RedirectResponse
    {
        if ((int) $order->estadoradiologo === 1) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No se puede editar una orden ya respondida.');
        }

        $user = Auth::user();

        $kinds = Kind::query()->orderBy('group')->orderBy('id')->get(['id', 'descipcion', 'group']);
        $groupNames = ['1' => 'Adulto', '2' => 'Niño', '3' => 'General', '4' => '3D'];
        $examTypes = $kinds->groupBy('group')->map(function (Collection $items, $group) use ($groupNames) {
            return [
                'label' => $groupNames[(string) $group] ?? "Grupo {$group}",
                'items' => $items->map(fn($k) => ['id' => $k->id, 'label' => $k->descipcion])->values(),
            ];
        })->values();

        $clinics = $this->clinicsForUser($user)->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->user->name ?? "Clínica #{$c->id}",
        ])->values();

        // Examenes existentes con archivos
        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as id', 'kinds.id as kind_id', 'kinds.descipcion as descripcion'])
            ->get()
            ->map(function ($e) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->id)
                    ->where('desde_informar', '!=', 1)
                    ->get(['id', 'name', 'extension', 'ruta'])
                    ->map(fn($f) => [
                        'id'        => $f->id,
                        'name'      => $f->name,
                        'extension' => $f->extension,
                        'url'       => $this->signedUrl($f->ruta),
                    ]);
                return ['id' => $e->id, 'kind_id' => $e->kind_id, 'descripcion' => $e->descripcion, 'archivos' => $archivos];
            });

        $radiologoId = DB::table('order_staff_exam')->where('order_id', $order->id)->value('staff_id');

        return Inertia::render('Orders/Edit', [
            'order' => [
                'id'              => $order->id,
                'clinic_id'       => $order->clinic_id,
                'odontologo_id'   => $order->odontologo_id ?: null,
                'radiologo_id'    => $radiologoId,
                'diagnostico'     => $order->diagnostico,
                'observaciones'   => $order->observaciones,
                'prioridad'       => $order->prioridad,
                'estadoradiologo' => $order->estadoradiologo,
                'sin_diagnostico' => (bool) $order->sin_diagnostico,
            ],
            'examenes'  => $examenes,
            'examTypes' => $examTypes,
            'clinics'   => $clinics,
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        if ((int) $order->estadoradiologo === 1) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No se puede editar una orden ya respondida.');
        }

        $request->validate([
            'prioridad' => ['required', 'in:Normal,Urgente'],
            'action'    => ['required', 'in:guardar,enviar'],
        ]);

        $enviar          = $request->input('action') === 'enviar';
        $yaEstabaEnviada = ! is_null($order->enviada); // capturar ANTES del update

        DB::transaction(function () use ($request, $order, $enviar): void {
            $order->update([
                'diagnostico'      => $request->input('diagnostico') ?? $order->diagnostico,
                'observaciones'    => $request->input('observaciones') ?? '',
                'prioridad'        => $request->input('prioridad'),
                'sin_diagnostico'  => $request->boolean('sin_diagnostico') ? 1 : 0,
                'estadoradiologo'  => $enviar ? 0 : 4,
                'estadoodontologo' => $enviar ? 0 : 1,
                'enviada'          => $enviar && !$order->enviada ? now() : $order->enviada,
            ]);

            // Subir nuevos archivos a exámenes existentes
            $existingIds = DB::table('examination_order')
                ->where('order_id', $order->id)
                ->pluck('examination_id');

            foreach ($existingIds as $examinationId) {
                $fileKey = "archivos_{$examinationId}";
                if (!$request->hasFile($fileKey)) continue;
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $path = $file->store("ordenes/{$order->id}", 's3');
                    DB::table('files')->insert([
                        'ruta' => $path, 'examination_id' => $examinationId,
                        'name' => $file->getClientOriginalName(), 'type_id' => 0,
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'ruta_dcm' => null, 'nombre_dcm' => null,
                        'file_size' => (int) $file->getSize(), 'file_size_procesed' => 1,
                        'file_size_error' => null, 'desde_informar' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Agregar nuevos exámenes
            foreach ((array) $request->input('nuevos_examenes', []) as $kindId) {
                if (!$kindId) continue;
                $examination = Examination::create(['kind_id' => $kindId, 'order_id' => $order->id]);
                DB::table('examination_order')->insert(['order_id' => $order->id, 'examination_id' => $examination->id]);
                $fileKey = "archivos_nuevo_{$kindId}";
                if (!$request->hasFile($fileKey)) continue;
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $path = $file->store("ordenes/{$order->id}", 's3');
                    DB::table('files')->insert([
                        'ruta' => $path, 'examination_id' => $examination->id,
                        'name' => $file->getClientOriginalName(), 'type_id' => 0,
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'ruta_dcm' => null, 'nombre_dcm' => null,
                        'file_size' => (int) $file->getSize(), 'file_size_procesed' => 1,
                        'file_size_error' => null, 'desde_informar' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Actualizar radiólogo (solo admin)
            if ($request->filled('radiologo_id') && Auth::user()->type_id === 1) {
                $rid = (int) $request->input('radiologo_id');
                $existing = DB::table('order_staff_exam')->where('order_id', $order->id)->exists();
                if ($existing) {
                    DB::table('order_staff_exam')->where('order_id', $order->id)->update(['staff_id' => $rid]);
                } else {
                    DB::table('order_staff_exam')->insert(['order_id' => $order->id, 'staff_id' => $rid, 'group_exam' => 1, 'respondida' => 0]);
                }
            }
        });

        if ($enviar && ! $yaEstabaEnviada) {
            $staffId = DB::table('order_staff_exam')->where('order_id', $order->id)->value('staff_id');
            if ($staffId) {
                $this->notificarRadiologo((int) $staffId);
            }
        }

        return redirect()->route('ordenes.show', $order)
            ->with('success', $enviar ? '¡Orden actualizada y enviada!' : '¡Orden guardada correctamente!');
    }

    // ── Eliminar orden (solo admin) ───────────────────────────────────────

    public function destroy(Order $order): \Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
        if ($user->type_id !== 1 && ! $user->hasRole('admin')) {
            abort(403);
        }

        DB::transaction(function () use ($order) {
            // Borrar archivos de S3
            $files = DB::table('files as f')
                ->join('examinations as e', 'e.id', '=', 'f.examination_id')
                ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
                ->where('eo.order_id', $order->id)
                ->whereNotNull('f.ruta')
                ->pluck('f.ruta');

            foreach ($files as $ruta) {
                try { \Illuminate\Support\Facades\Storage::disk('s3')->delete($ruta); } catch (\Throwable) {}
            }

            // Borrar registros relacionados
            $examinationIds = DB::table('examination_order')
                ->where('order_id', $order->id)
                ->pluck('examination_id');

            DB::table('files')->whereIn('examination_id', $examinationIds)->delete();
            DB::table('answers')->whereIn('examination_id', $examinationIds)->delete();
            DB::table('examination_order')->where('order_id', $order->id)->delete();
            DB::table('examinations')->whereIn('id', $examinationIds)->delete();
            DB::table('corrections')->where('order_id', $order->id)->delete();
            DB::table('accounts')->where('order_id', $order->id)->delete();
            DB::table('order_staff')->where('order_id', $order->id)->delete();
            DB::table('order_staff_exam')->where('order_id', $order->id)->delete();

            $order->delete();
        });

        return redirect()->route('ordenes.index')->with('success', "Orden #{$order->id} eliminada correctamente.");
    }

    // ── Eliminar examen ───────────────────────────────────────────────────

    public function deleteExamen(Order $order, int $examinationId): \Illuminate\Http\RedirectResponse
    {
        if ((int) $order->estadoradiologo === 1) {
            return back()->with('error', 'No se puede eliminar un examen de una orden ya respondida.');
        }

        DB::transaction(function () use ($order, $examinationId) {
            // Delete files from S3 and DB
            $files = DB::table('files')->where('examination_id', $examinationId)->get(['id', 'ruta']);
            foreach ($files as $f) {
                if ($f->ruta) {
                    try { \Illuminate\Support\Facades\Storage::disk('s3')->delete($f->ruta); } catch (\Throwable) {}
                }
            }
            DB::table('files')->where('examination_id', $examinationId)->delete();
            DB::table('answers')->where('examination_id', $examinationId)->delete();
            DB::table('examination_order')->where('examination_id', $examinationId)->where('order_id', $order->id)->delete();
            DB::table('examinations')->where('id', $examinationId)->delete();
        });

        return back()->with('success', 'Examen eliminado correctamente.');
    }

    // ── Descargar ZIP ─────────────────────────────────────────────────────

    public function zip(Order $order): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $files = DB::table('files as f')
            ->join('examinations as e', 'e.id', '=', 'f.examination_id')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->where('eo.order_id', $order->id)
            ->whereNotNull('f.ruta')
            ->select('f.id', 'f.name', 'f.ruta', 'f.extension', 'k.descipcion as examen')
            ->get();

        if ($files->isEmpty()) {
            return back()->with('error', 'Esta orden no tiene archivos adjuntos.');
        }

        $zipName = "orden-{$order->id}.zip";
        $tmpPath = sys_get_temp_dir() . '/' . $zipName;

        $zip = new \ZipArchive();
        $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $usedNames = [];
        foreach ($files as $f) {
            try {
                $content = \Illuminate\Support\Facades\Storage::disk('s3')->get($f->ruta);
                if (!$content) continue;
            } catch (\Throwable) {
                continue;
            }

            $ext      = $f->extension ?: pathinfo($f->ruta, PATHINFO_EXTENSION);
            $baseName = $f->name ?: ('archivo_' . $f->id . ($ext ? ".{$ext}" : ''));
            $folder   = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $f->examen);

            $zipEntry = "{$folder}/{$baseName}";
            // Avoid duplicates
            if (isset($usedNames[$zipEntry])) {
                $usedNames[$zipEntry]++;
                $zipEntry = "{$folder}/{$usedNames[$zipEntry]}_{$baseName}";
            } else {
                $usedNames[$zipEntry] = 0;
            }

            $zip->addFromString($zipEntry, $content);
        }
        $zip->close();

        return response()->streamDownload(function () use ($tmpPath) {
            readfile($tmpPath);
            @unlink($tmpPath);
        }, $zipName, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$zipName}\"",
        ]);
    }

    /** Envía correo inmediato al radiólogo cuando se le asigna una orden. */
    private function notificarRadiologo(int $staffId): void
    {
        $radiologo = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.id', $staffId)
            ->first(['u.name', 'u.mail']);

        if (! $radiologo || empty($radiologo->mail)) {
            return;
        }

        // Obtener la orden recién creada/actualizada
        $orden = DB::table('orders as o')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->join('order_staff_exam as ose', 'ose.order_id', '=', 'o.id')
            ->where('ose.staff_id', $staffId)
            ->orderByDesc('o.created_at')
            ->first(['o.id', 'o.prioridad', 'p.name as paciente', 'uc.name as clinica']);

        if (! $orden) {
            return;
        }

        $examen = DB::table('kinds as k')
            ->join('examinations as ex', 'ex.kind_id', '=', 'k.id')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'ex.id')
            ->where('eo.order_id', $orden->id)
            ->pluck('k.descipcion')
            ->implode(', ');

        try {
            Mail::to($radiologo->mail)->send(new OrdenAsignada(
                radiologoNombre: $radiologo->name,
                ordenId:         $orden->id,
                paciente:        $orden->paciente,
                clinica:         $orden->clinica,
                examen:          $examen ?: 'Sin especificar',
                prioridad:       $orden->prioridad ?? 'Normal',
            ));
        } catch (\Throwable) {
            // No interrumpir el flujo si el correo falla
        }
    }

    /** Generate a 60-minute pre-signed S3 URL for a given path. */
    private function signedUrl(?string $ruta): ?string
    {
        if (!$ruta) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Storage::disk('s3')
                ->temporaryUrl($ruta, now()->addMinutes(60));
        } catch (\Throwable) {
            return null;
        }
    }
}