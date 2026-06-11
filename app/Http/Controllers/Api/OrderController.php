<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // GET /api/v3/order/examinations/types
    public function examTypes()
    {
        $kinds = DB::table('kinds')
            ->select(
                'id',
                'descipcion as descripcion',
                'group as grupo',
                'plazo_dias',
                'con_diente',
                'es_3d',
                'con_trazados',
                'con_estudio_implantes'
            )
            ->orderBy('group')
            ->orderBy('descipcion')
            ->get();

        return response()->json($kinds);
    }

    // GET /api/v3/order/examinations/groups
    public function examGroups()
    {
        $groups = DB::table('kinds')
            ->select('group as grupo')
            ->distinct()
            ->orderBy('group')
            ->pluck('grupo');

        return response()->json($groups);
    }

    // GET /api/v3/order/by-patient/{rut}
    public function listByPatient(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $patient = DB::table('patients as p')
            ->leftJoin('clinic_patient as cp', 'p.id', '=', 'cp.patient_id')
            ->leftJoin('clinics as c', 'cp.clinic_id', '=', 'c.id')
            ->where('c.holding_id', $holdingId)
            ->where('p.rut', $rut)
            ->first(['p.id']);

        if (! $patient) {
            return response()->json(['error' => "Paciente de rut $rut no existe"], 404);
        }

        $page = max(1, (int) $request->get('page', 1));
        $perPage = (int) $request->get('per_page', 10);

        $base = DB::table('orders as o')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as odont', 'odont.id', '=', 'o.odontologo_id')
            ->leftJoin('users as uo', 'uo.id', '=', 'odont.user_id')
            ->where('p.rut', $rut)
            ->where('c.holding_id', $holdingId);

        $total = (clone $base)->count();

        $orders = $base
            ->select(
                'o.id',
                'o.diagnostico',
                'o.prioridad',
                'o.created_at',
                'o.created_at as fecha_creacion',
                'o.enviada as fecha_envio',
                'o.respondida as fecha_respuesta',
                'o.estadoradiologo',
                'o.estadoodontologo',
                'o.estadoradiologo as estado_radiologo',
                'o.estadoodontologo as estado_odontologo',
                'o.odontologo_id',
                'p.name as paciente',
                'p.rut as rut_paciente',
                'uc.name as clinica',
                'uo.name as odontologo',
                'odont.rut as rut_odontologo',
                'uo.email as mail_odontologo'
            )
            ->selectRaw("case when o.estadoradiologo = 0 then 'No Informada' when o.estadoradiologo = 1 then 'Informada' when o.estadoradiologo = 2 and o.estadoodontologo = 3 then 'Corrección' else 'Guardada' end as estado_texto")
            ->selectRaw("case when o.estadoradiologo = 4 then 1 else 0 end as editable")
            ->selectRaw("case when o.estadoradiologo in (1,4) then 1 else 0 end as visitable")
            ->orderByDesc('o.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'current_page' => $page,
            'data' => $orders,
            'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : null,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'to' => min($page * $perPage, $total),
            'total' => $total,

            // compatibilidad anterior Dimage2
            'page' => $page,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // GET /api/v3/order/by-id/{id}
    public function byId(Request $request, string $id)
    {
        $id = is_numeric($id) ? (int) $id : 0;

        if ($id === 0) {
            $lastId = cache()->get('dentalsoft_last_created_order_id');

            if ($lastId) {
                $id = (int) $lastId;
            }
        }
        $order = DB::table('orders as o')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as so', 'so.id', '=', 'o.odontologo_id')
            ->leftJoin('users as uo', 'uo.id', '=', 'so.user_id')
            ->where('o.id', $id)
            ->select(
                'o.*',
                'p.name as paciente',
                'p.rut as rut_paciente',
                'uc.name as clinica',
                'so.id as profesional_id',
                'so.rut as profesional_rut',
                'so.id_externo as profesional_id_externo',
                'uo.name as profesional',
                'uo.name as odontologo',
                'so.rut as rut_odontologo',
                'uo.email as mail_odontologo'
            )
            ->first();

        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        $formatRut = function ($rut) {
            $clean = strtoupper(preg_replace('/[^0-9K]/', '', (string) $rut));

            if (strlen($clean) < 2) {
                return $rut;
            }

            return substr($clean, 0, -1) . '-' . substr($clean, -1);
        };

        if (!empty($order->rut_odontologo)) {
            $order->rut_odontologo = $formatRut($order->rut_odontologo);
        }

        if (!empty($order->profesional_rut)) {
            $order->profesional_rut = $formatRut($order->profesional_rut);
        }

        // Campos calculados que DentalSoft usa para mostrar/ocultar el botón de editar
        $order->editable  = (int) $order->estadoradiologo !== 1 ? 1 : 0;
        $order->visitable = in_array((int) $order->estadoradiologo, [1, 4]) ? 1 : 0;

        \Log::info('BY_ID_PROFESIONAL', [
            'order_id'              => $order->id,
            'profesional_rut'       => $order->profesional_rut,
            'profesional_id_externo' => $order->profesional_id_externo,
            'odontologo_id'         => $order->odontologo_id,
        ]);

        $examenes = DB::table('examinations as e')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->leftJoin('order_staff_exam as ose', function ($join) {
                $join->on('ose.order_id', '=', 'eo.order_id')
                    ->on('ose.group_exam', '=', 'k.group');
            })
            ->leftJoin('staffs as sr', 'sr.id', '=', 'ose.staff_id')
            ->leftJoin('users as ur', 'ur.id', '=', 'sr.user_id')
            ->where('eo.order_id', $id)
            ->select(
                'e.id',
                'e.kind_id as id_tipo_examen',
                'e.url_texto',
                'k.descipcion as tipo_examen',
                'k.descipcion as descripcion',
                'k.group as grupo',
                'ur.name as radiologo',
                'sr.rut as rut_radiologo',
                'ose.respondida'
            )
            ->selectRaw('case when ose.respondida = 0 then 1 else 0 end as respondible')
            ->get()
            ->map(function ($e) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->id)
                    ->get(['id', 'name', 'extension', 'file_size', 'desde_informar', 'ruta', 'ruta_dcm'])
                    ->map(fn ($f) => [
                        'id'             => $f->id,
                        'name'           => $f->name,
                        'extension'      => $f->extension,
                        'file_size'      => $f->file_size,
                        'desde_informar' => (bool) $f->desde_informar,
                        'ruta'           => rtrim(env('RUTA_IMG', ''), '/') . '/' . ltrim($f->ruta ?? '', '/'),
                        'ruta_dcm'       => $f->ruta_dcm ? rtrim(env('RUTA_IMG', ''), '/') . '/' . ltrim($f->ruta_dcm, '/') : null,
                    ]);

                $respuesta = DB::table('answers')
                    ->where('examination_id', $e->id)
                    ->first();

                return [
                    'id'             => $e->id,
                    'id_tipo_examen' => $e->id_tipo_examen,
                    'tipo_examen'    => $e->tipo_examen,
                    'descripcion'    => $e->descripcion,
                    'grupo'          => $e->grupo,
                    'url_texto'      => $e->url_texto,
                    'radiologo'      => $e->radiologo,
                    'rut_radiologo'  => $e->rut_radiologo,
                    'respondida'     => $e->respondida,
                    'respondible'    => (int) $e->respondible,
                    'archivos'       => $archivos,
                    'respuesta'      => $respuesta,
                ];
            });

        $order->examenes = $examenes;

        return response()->json($order);
    }

    // POST /api/v3/order
    public function create(Request $request)
    {
        \Log::info('CREATE_ORDER_PAYLOAD', [
            'all' => $request->all(),
            'content' => $request->getContent(),
        ]);

        \Log::info('DENTALSOFT_ORDER_CREATE', [
            'all' => $request->all(),
        ]);

        $rutPaciente = $request->input('paciente');
        $rutOdontologo = strtoupper(preg_replace('/[^0-9K]/', '', $request->input('odontologo', '')));

        $patient = DB::table('patients')
            ->where('rut', $rutPaciente)
            ->orderByDesc('id')
            ->first();

        if (!$patient) {
            return response()->json(['error' => "Paciente no encontrado: {$rutPaciente}"], 404);
        }

        $odontologo = DB::table('staffs')
            ->where('type_staff', 6)
            ->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$rutOdontologo])
            ->orderByRaw('CASE WHEN id_externo IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();

        if (!$odontologo) {
            return response()->json(['error' => "Odontólogo no encontrado: {$rutOdontologo}"], 404);
        }

        $data = [
            'patient_id'     => $patient->id,
            'clinic_id'      => $request->input('clinica'),
            'odontologo_id'  => $odontologo->id,
            'diagnostico'    => $request->input('diagnostico') ?? $request->input('diagnostico_clinico') ?? '',
            'observaciones'  => $request->input('observaciones') ?? '',
            'prioridad'      => (string) $request->input('prioridad', 'Normal'),
            'examenes'       => $request->input('examenes', []),
        ];

        $orderId = DB::table('orders')->insertGetId([
            'patient_id'       => $data['patient_id'],
            'clinic_id'        => $data['clinic_id'],
            'odontologo_id'    => $data['odontologo_id'],
            'radiologo_id'     => 142,
            'diagnostico'      => $data['diagnostico'],
            'observaciones'    => $data['observaciones'],
            'prioridad'        => $data['prioridad'],
            'estadoradiologo'  => 4,
            'estadoodontologo' => 4,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        foreach ($data['examenes'] as $ex) {
            $kindId = $ex['kind_id'] ?? $ex['tipo'] ?? null;

            if (!$kindId) {
                continue;
            }

            $piezas = null;
            if (isset($ex['dientes']) && is_array($ex['dientes'])) {
                $piezas = implode(',', $ex['dientes']);
            }

            $exId = DB::table('examinations')->insertGetId([
                'kind_id'    => $kindId,
                'piezas'     => $piezas,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('examination_order')->insert([
                'order_id'       => $orderId,
                'examination_id' => $exId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        \Log::info('CREATE_ORDER_RESPONSE', [
            'order_id' => $orderId
        ]);

        cache()->put('dentalsoft_last_created_order_id', $orderId, now()->addMinutes(10));

        return response()->json([
            'orden' => [
                'id' => $orderId
            ],
            'id' => $orderId,
            'order_id' => $orderId,
            'message' => 'Orden creada.'
        ], 201);
    }

    // POST /api/v3/order/{id}/files/{examination_id}
    public function uploadFiles(Request $request, int $id, int $examinationId)
    {
        $exists = DB::table('examination_order')
            ->where('order_id', $id)
            ->where('examination_id', $examinationId)
            ->exists();

        if (! $exists) return response()->json(['error' => 'Examen no pertenece a esta orden.'], 404);

        $request->validate(['archivos' => ['required', 'array'], 'archivos.*' => ['file']]);

        $uploaded = [];
        foreach ($request->file('archivos', []) as $file) {
            $path = $file->store("orders/{$id}/examinations/{$examinationId}", 's3');
            $fileId = DB::table('files')->insertGetId([
                'examination_id' => $examinationId,
                'name'           => $file->getClientOriginalName(),
                'ruta'           => $path,
                'extension'      => strtolower($file->getClientOriginalExtension()),
                'file_size'      => $file->getSize(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $uploaded[] = ['id' => $fileId, 'name' => $file->getClientOriginalName()];
        }

        return response()->json(['message' => 'Archivos subidos.', 'archivos' => $uploaded], 201);
    }

    // DELETE /api/v3/order/file/{fileId}
    public function deleteFile(Request $request, int $fileId)
    {
        $file = DB::table('files')->where('id', $fileId)->first(['id', 'ruta', 'examination_id']);
        if (! $file) return response()->json(['error' => 'Archivo no encontrado.'], 404);

        if ($file->ruta) {
            try { Storage::disk('s3')->delete($file->ruta); } catch (\Throwable) {}
        }

        DB::table('files')->where('id', $fileId)->delete();

        return response()->json(['message' => 'Archivo eliminado.']);
    }

    // PATCH /api/v3/order/{id}/send/radiologo
    public function sendToRadiologo(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first(['id', 'estadoradiologo']);
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        if (! in_array((int) $order->estadoradiologo, [0, 4])) {
            return response()->json(['error' => 'La orden ya fue enviada o respondida.'], 422);
        }

        $data = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['exists:staffs,id'],
        ]);

        DB::table('orders')->where('id', $id)->update([
            'estadoradiologo' => 0,
            'enviada'         => now(),
            'updated_at'      => now(),
        ]);

        DB::table('order_staff_exam')->where('order_id', $id)->delete();

        $examIds = DB::table('examination_order')
            ->where('order_id', $id)
            ->pluck('examination_id');

        foreach ($data['staff_ids'] as $staffId) {
            foreach ($examIds as $exId) {
                DB::table('order_staff_exam')->insert([
                    'order_id'       => $id,
                    'staff_id'       => $staffId,
                    'examination_id' => $exId,
                ]);
            }
        }

        return response()->json(['message' => 'Orden enviada al radiólogo.']);
    }

    // POST /api/v3/order/{id}/answers
    public function saveAnswers(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first(['id', 'estadoradiologo']);
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        $data = $request->validate([
            'respuestas'                  => ['required', 'array'],
            'respuestas.*.examination_id' => ['required', 'integer'],
            'respuestas.*.texto'          => ['nullable', 'string'],
            'respuestas.*.solo_adjunto'   => ['nullable', 'boolean'],
        ]);

        foreach ($data['respuestas'] as $r) {
            $exId = $r['examination_id'];
            $existing = DB::table('answers')->where('examination_id', $exId)->first('id');

            if ($existing) {
                DB::table('answers')->where('id', $existing->id)->update([
                    'campo_1'      => $r['texto'] ?? null,
                    'solo_adjunto' => $r['solo_adjunto'] ?? false,
                    'updated_at'   => now(),
                ]);
            } else {
                DB::table('answers')->insert([
                    'examination_id' => $exId,
                    'campo_1'        => $r['texto'] ?? null,
                    'solo_adjunto'   => $r['solo_adjunto'] ?? false,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        DB::table('orders')->where('id', $id)->update([
            'estadoradiologo' => 1,
            'respondida'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'Respuestas guardadas.']);
    }

    // GET /api/v3/order/by-radiologo/{rut}
    public function listByRadiologo(Request $request, string $rut)
    {
        $holdingId = $request->_holding_id;

        $radiologo = DB::table('staffs as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->join('clinic_staff as cs', 'cs.staff_id', '=', 's.id')
            ->join('clinics as c', 'c.id', '=', 'cs.clinic_id')
            ->where('u.rut', $rut)
            ->where('s.type_staff', 3) // radiólogo
            ->where('c.holding_id', $holdingId)
            ->select('s.id as staff_id', 'u.rut')
            ->first();

        if (! $radiologo) {
            return response()->json(['error' => "Radiólogo de rut $rut no existe en la red."], 404);
        }

        $soloPendientes = $request->boolean('solo_pendientes', false);
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(1, (int) $request->get('per_page', 15)));

        $query = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->where('c.holding_id', $holdingId)
            ->whereExists(function ($sub) use ($radiologo, $soloPendientes) {
                $sub->from('order_staff_exam as ose')
                    ->whereColumn('ose.order_id', 'o.id')
                    ->where('ose.staff_id', $radiologo->staff_id);
                if ($soloPendientes) {
                    $sub->where('ose.respondida', 0);
                }
            })
            ->select(
                'o.id', 'o.estadoradiologo', 'o.estadoodontologo',
                'o.prioridad', 'o.created_at', 'o.enviada', 'o.respondida',
                'p.name as paciente', 'p.rut as rut_paciente',
                'uc.name as clinica'
            );

        if ($soloPendientes) {
            $query->where('o.estadoradiologo', 0);
        }

        $total  = $query->count();
        $orders = $query->orderByDesc('o.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return response()->json([
            'data'        => $orders,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // PUT /api/v3/order/{id}
    public function update(Request $request, int $id)
    {
        \Log::info('UPDATE_ORDER_PAYLOAD', [
            'all' => $request->all(),
        ]);
        $holdingId = $request->_holding_id;

        $order = DB::table('orders as o')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->where('o.id', $id)
            ->where('c.holding_id', $holdingId)
            ->select('o.id', 'o.estadoradiologo', 'o.estadoodontologo', 'o.enviada')
            ->first();

        if (! $order) {
            return response()->json(['error' => "Orden $id no existe para la red seleccionada."], 404);
        }

        // Integración DentalSoft:
        // permitir editar toda orden que NO esté respondida.
        // 1 = respondida/informada, no editable.
        $editable = (int) $order->estadoradiologo !== 1;

        if (! $editable) {
            return response()->json(['error' => 'La orden no puede editarse porque ya está respondida.'], 422);
        }

        \Log::info('UPDATE_RAW_REQUEST', [
            'method' => $request->method(),
            'all' => $request->all(),
            'content' => $request->getContent(),
        ]);

        // Solo actualizar campos que vienen en la solicitud para no sobreescribir con null
        $data = [];

        if ($request->hasAny(['diagnostico', 'diagnostico_clinico'])) {
            $data['diagnostico'] = $request->input('diagnostico') ?? $request->input('diagnostico_clinico');
        }
        if ($request->has('observaciones')) {
            $data['observaciones'] = $request->input('observaciones');
        }
        if ($request->has('prioridad')) {
            $data['prioridad'] = $request->input('prioridad');
        }

        // Cambio de odontólogo (enviado como RUT)
        if ($request->filled('odontologo')) {
            $rutOdont = strtoupper(preg_replace('/[^0-9K]/', '', $request->input('odontologo')));
            $odont = DB::table('staffs')
                ->where('type_staff', 6)
                ->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$rutOdont])
                ->orderByRaw('CASE WHEN id_externo IS NULL THEN 1 ELSE 0 END')
                ->orderBy('id')
                ->first();
            if ($odont) {
                $data['odontologo_id'] = $odont->id;
            }
        }

        // Cambio de clínica
        if ($request->filled('clinica')) {
            $data['clinic_id'] = $request->input('clinica');
        }

        \Log::info('UPDATE_MAPPED_DATA', [
            'mapped' => $data,
        ]);

        \Log::info('UPDATE_BEFORE_DB', [
            'order_id' => $id,
            'data' => $data,
        ]);

        if (! empty($data)) {
            DB::table('orders')->where('id', $id)->update(
                array_merge($data, ['updated_at' => now()])
            );
        }

        if ($request->has('examenes')) {

            $oldExamIds = DB::table('examination_order')
                ->where('order_id', $id)
                ->pluck('examination_id');

            DB::table('examination_order')
                ->where('order_id', $id)
                ->delete();

            if ($oldExamIds->count()) {
                DB::table('examinations')
                    ->whereIn('id', $oldExamIds)
                    ->delete();
            }

            foreach ($request->input('examenes', []) as $ex) {

                $kindId = $ex['kind_id'] ?? $ex['tipo'] ?? null;

                if (!$kindId) {
                    continue;
                }

                $piezas = null;

                if (isset($ex['dientes']) && is_array($ex['dientes'])) {
                    $piezas = implode(',', $ex['dientes']);
                }

                $exId = DB::table('examinations')->insertGetId([
                    'kind_id'    => $kindId,
                    'piezas'     => $piezas,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('examination_order')->insert([
                    'order_id'       => $id,
                    'examination_id' => $exId,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Orden actualizada.']);
    }

    // GET /api/v3/order/pdf/{id}
    public function generatePdf(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first('id');
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        return response()->json([
            'url' => route('ordenes.pdf', $id),
        ]);
    }

    // GET /api/v3/order/zip/{id}
    public function generateZip(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first('id');
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        return response()->json([
            'url' => route('ordenes.zip', $id),
        ]);
    }
}
