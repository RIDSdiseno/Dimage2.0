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
            ->selectRaw("case when o.estadoradiologo = 1 then 1 else 0 end as visitable")
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

        // Devolver RUT sin guión ni puntos — DentalSoft almacena y compara en formato limpio
        $stripRut = fn($rut) => strtoupper(preg_replace('/[^0-9K]/', '', (string) $rut));

        // Guardar RUT original (con guión) antes de limpiar — se usa como profesional_id_externo
        // igual que el Dimage antiguo, que almacenaba el RUT en id_externo (ej: "794350-4").
        $rawProfesionalRut = $order->profesional_rut ?? null;

        if (!empty($order->rut_odontologo)) {
            $order->rut_odontologo = $stripRut($order->rut_odontologo);
        }

        if (!empty($order->profesional_rut)) {
            $order->profesional_rut = $stripRut($order->profesional_rut);
        }

        // El Dimage antiguo guardaba id_externo = RUT del odontólogo y lo enviaba como
        // profesional_id_externo. DentalSoft valida al profesional por RUT, no por ID numérico.
        // Replicamos ese comportamiento para que la validación funcione igual que antes.
        if (!empty($rawProfesionalRut)) {
            $order->profesional_id_externo = $rawProfesionalRut;
        } else {
            unset($order->profesional_id_externo);
        }

        // Igual que el Dimage antiguo: editable solo cuando está en borrador (estado 4),
        // visitable solo cuando el radiólogo ya respondió (estado 1).
        $order->editable  = (int) $order->estadoradiologo === 4 ? 1 : 0;
        $order->visitable = (int) $order->estadoradiologo === 1 ? 1 : 0;

        // Aliases que DentalSoft espera (igual que Dimage antiguo)
        $order->estado_radiologo   = $order->estadoradiologo;
        $order->estado_odontologo  = $order->estadoodontologo;
        $order->fecha_creacion     = $order->created_at;
        $order->fecha_envio        = $order->enviada;
        $order->fecha_respuesta    = $order->respondida;
        $order->observaciones_informe = $order->observaciones_2;

        // Estado en texto
        $er = (int) $order->estadoradiologo;
        $eo = (int) $order->estadoodontologo;
        $order->estado_texto = match(true) {
            $er === 0 => 'No Informada',
            $er === 1 => 'Informada',
            $er === 2 && $eo === 3 => 'Corrección',
            default   => 'Guardada',
        };

        // Radiólogos asignados (igual que Dimage antiguo)
        $order->ruts_radiologos_asignados = DB::table('order_staff_exam as ose')
            ->leftJoin('staffs as s', 's.id', '=', 'ose.staff_id')
            ->where('ose.order_id', $order->id)
            ->groupBy('ose.order_id')
            ->value(DB::raw("GROUP_CONCAT(DISTINCT s.rut SEPARATOR ', ')"));

        $order->ruts_radiologos_pendientes = DB::table('order_staff_exam as ose')
            ->leftJoin('staffs as s', 's.id', '=', 'ose.staff_id')
            ->where('ose.order_id', $order->id)
            ->where('ose.respondida', 0)
            ->groupBy('ose.order_id')
            ->value(DB::raw("GROUP_CONCAT(DISTINCT s.rut SEPARATOR ', ')"));

        $order->correcciones = DB::table('corrections')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        \Log::info('BY_ID_PROFESIONAL', [
            'order_id'               => $order->id,
            'profesional_rut'        => $order->profesional_rut,
            'profesional_id_externo' => $order->profesional_id_externo,
            'odontologo_id'          => $order->odontologo_id,
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
                'e.piezas',
                'e.otrocheck',
                'e.otrocheck1',
                'e.otrocheck2',
                'e.otrocheck3',
                'e.otrocheck4',
                'e.otrocheck5',
                'e.otroinput',
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
                        'extension'      => $this->extToMime($this->normalizeExt($f->extension, $f->name, $f->ruta)),
                        'is_image'       => $this->isImageExt($f->extension, $f->name, $f->ruta),
                        'file_size'      => $f->file_size,
                        'desde_informar' => (bool) $f->desde_informar,
                        'url'            => $this->apiFileUrl($f->ruta),
                        'ruta'           => $this->apiProxyUrl($f->id, $f->ruta, $f->name),
                        'download_url'   => $this->apiDownloadUrl($f->ruta, $f->name, $f->extension),
                        'ruta_dcm'       => $f->ruta_dcm ? $this->apiFileUrl($f->ruta_dcm) : null,
                    ]);

                $respuesta = DB::table('answers')
                    ->where('examination_id', $e->id)
                    ->first();

                // Separar piezas en permanentes y temporales — mismo formato que API v3 antigua.
                // piezas_adultos/piezas_ninos: enteros separados por punto ("26.27").
                $permanentes = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,
                                31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
                $temporales  = [51,52,53,54,55,61,62,63,64,65,71,72,73,74,75,81,82,83,84,85];
                $dList = $e->piezas
                    ? array_map('intval', array_filter(array_map('trim', explode(',', $e->piezas)), 'strlen'))
                    : [];
                $piezasAdultos = implode('.', array_values(array_filter($dList, fn($d) => in_array($d, $permanentes))));
                $piezasNinos   = implode('.', array_values(array_filter($dList, fn($d) => in_array($d, $temporales))));

                return [
                    'id'              => $e->id,
                    'id_tipo_examen'  => $e->id_tipo_examen,
                    'tipo_examen'     => $e->tipo_examen,
                    'descripcion'     => $e->descripcion,
                    'grupo'           => $e->grupo,
                    'piezas_adultos'  => $piezasAdultos,
                    'piezas_ninos'    => $piezasNinos,
                    'dientes'         => $dList,
                    'url_texto'       => implode(',', $this->urlTextoToTrazados($e->url_texto)) ?: null,
                    'trazados'        => $this->urlTextoToTrazados($e->url_texto),
                    // columnas individuales que DentalSoft usa para pre-poblar checkboxes
                    'otrocheck'       => (int) $e->otrocheck,
                    'otrocheck1'      => (int) $e->otrocheck1,
                    'otrocheck2'      => (int) $e->otrocheck2,
                    'otrocheck3'      => (int) $e->otrocheck3,
                    'otrocheck4'      => (int) $e->otrocheck4,
                    'otrocheck5'      => (int) $e->otrocheck5,
                    'otroinput'       => $e->otroinput,
                    'trazados_otros_texto' => $e->otroinput,
                    'radiologo'      => $e->radiologo,
                    'rut_radiologo'  => $e->rut_radiologo,
                    'respondida'     => $e->respondida,
                    'respondible'    => (int) $e->respondible,
                    'archivos'       => $archivos,
                    'respuesta'      => $respuesta,
                ];
            });

        $order->examenes = $examenes;

        \Log::info('BY_ID_EXAMENES_TRAZADOS', [
            'order_id' => $order->id,
            'examenes' => $examenes->map(fn($e) => [
                'id'         => $e['id'],
                'kind_id'    => $e['id_tipo_examen'],
                'url_texto'  => $e['url_texto'],
                'trazados'   => $e['trazados'],
                'archivos'   => collect($e['archivos'])->map(fn($a) => [
                    'id'        => $a['id'],
                    'name'      => $a['name'],
                    'extension' => $a['extension'],
                    'is_image'  => $a['is_image'],
                    'url_prefix' => $a['url'] ? substr($a['url'], 0, 80) : null,
                ])->all(),
            ])->all(),
        ]);

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

            $urlTextoCreate = $this->extractUrlTexto($ex);
            $exId = DB::table('examinations')->insertGetId([
                'kind_id'    => $kindId,
                'piezas'     => $piezas,
                'url_texto'  => $urlTextoCreate,
                'otroinput'  => $ex['trazados_otros_texto'] ?? $ex['otroinput'] ?? null,
                ...$this->trazadosToOtrochecks($urlTextoCreate),
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
    // DentalSoft envía el kind_id (tipo) como examinationId, no el examination.id real.
    // Buscamos primero por examination_id directo y si no existe, por kind_id.
    public function uploadFiles(Request $request, int $id, int $examinationId)
    {
        \Log::info('UPLOAD_FILES', ['order_id' => $id, 'examination_id_param' => $examinationId]);

        $exam = DB::table('examination_order as eo')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->where('eo.order_id', $id)
            ->where(function ($q) use ($examinationId) {
                $q->where('eo.examination_id', $examinationId)
                  ->orWhere('e.kind_id', $examinationId);
            })
            ->select('eo.examination_id')
            ->orderBy('eo.examination_id')
            ->first();

        if (! $exam) return response()->json(['error' => 'Examen no pertenece a esta orden.'], 404);

        $realExamId = $exam->examination_id;

        \Log::info('UPLOAD_FILES_FIELDS', [
            'all_keys'  => array_keys($request->all()),
            'file_keys' => array_keys($request->allFiles()),
        ]);

        $request->validate(['archivos' => ['required', 'array'], 'archivos.*' => ['file']]);

        $uploaded = [];
        foreach ($request->file('archivos', []) as $file) {
            $path = $file->store("orders/{$id}/examinations/{$realExamId}", 's3');
            if ($path === false) {
                \Log::error('UPLOAD_FILES_S3_FAIL', [
                    'order_id'       => $id,
                    'examination_id' => $realExamId,
                    'filename'       => $file->getClientOriginalName(),
                ]);
                return response()->json(['error' => 'Error al subir archivo a S3.'], 500);
            }
            \Log::info('UPLOAD_FILES_S3_OK', ['path' => $path, 'name' => $file->getClientOriginalName()]);
            $ext = strtolower($file->getClientOriginalExtension());
            $fileId = DB::table('files')->insertGetId([
                'examination_id' => $realExamId,
                'name'           => $file->getClientOriginalName(),
                'ruta'           => $path,
                'extension'      => $ext,
                'file_size'      => $file->getSize(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $uploaded[] = [
                'id'             => $fileId,
                'name'           => $file->getClientOriginalName(),
                'extension'      => $this->extToMime($this->normalizeExt($ext, $file->getClientOriginalName(), $path)),
                'is_image'       => $this->isImageExt($ext, $file->getClientOriginalName(), $path),
                'file_size'      => $file->getSize(),
                'desde_informar' => false,
                'url'            => $this->apiFileUrl($path),
                'ruta'           => $this->apiProxyUrl($fileId, $path, $file->getClientOriginalName()),
                'download_url'   => $this->apiDownloadUrl($path, $file->getClientOriginalName(), $ext),
                'ruta_dcm'       => null,
            ];
        }

        return response()->json(['message' => 'Archivos subidos.', 'archivos' => $uploaded], 201);
    }

    // GET /api/v3/file/{sig}/{id}/{filename}  — sin auth.api, proxy que sirve el archivo desde S3
    // sig en el path para que la URL termine en .ext — DentalSoft detecta extensión con split('.')
    public function serveFile(Request $request, string $sig, int $id, string $filename)
    {
        $expected = substr(hash_hmac('sha256', $id . '|' . $filename, config('app.key')), 0, 20);
        if ($sig !== $expected) {
            return response('Forbidden', 403);
        }

        $file = DB::table('files')->where('id', $id)->first(['ruta', 'extension', 'name']);
        if (! $file || ! $file->ruta || $file->ruta === '0') {
            return response('Not Found', 404);
        }

        try {
            $stream = Storage::disk('s3')->readStream($file->ruta);
        } catch (\Throwable) {
            return response('Storage Error', 500);
        }

        $ext = strtolower($file->extension ?: pathinfo($file->ruta, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'pdf'         => 'application/pdf',
            'dcm'         => 'application/dicom',
            default       => 'application/octet-stream',
        };

        return response()->stream(
            function () use ($stream) { fpassthru($stream); },
            200,
            [
                'Content-Type'                => $mime,
                'Cache-Control'               => 'public, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods'=> 'GET, OPTIONS',
                'Access-Control-Allow-Headers'=> 'Content-Type',
            ]
        );
    }

    // OPTIONS preflight para serveFile (CORS)
    public function serveFileOptions(): \Illuminate\Http\Response
    {
        return response('', 204, [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ]);
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
        \Log::info('SEND_TO_RADIOLOGO_PAYLOAD', [
            'order_id' => $id,
            'all'      => $request->all(),
            'content'  => $request->getContent(),
        ]);

        $order = DB::table('orders')->where('id', $id)->first(['id', 'estadoradiologo']);
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        if ((int) $order->estadoradiologo === 1) {
            return response()->json(['error' => 'La orden ya fue respondida y no puede reenviarse.'], 422);
        }

        $staffIds = $request->input('staff_ids', []);

        // Validar staff_ids solo si se enviaron
        if (!empty($staffIds)) {
            $request->validate([
                'staff_ids'   => ['array'],
                'staff_ids.*' => ['exists:staffs,id'],
            ]);
        }

        DB::table('orders')->where('id', $id)->update([
            'estadoradiologo' => 0,
            'enviada'         => now(),
            'updated_at'      => now(),
        ]);

        if (!empty($staffIds)) {
            DB::table('order_staff_exam')->where('order_id', $id)->delete();

            foreach ($staffIds as $staffId) {
                DB::table('order_staff_exam')->insertOrIgnore([
                    'order_id'   => $id,
                    'staff_id'   => (int) $staffId,
                    'group_exam' => 1,
                    'kind_id'    => null,
                    'respondida' => 0,
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
            ->where('s.rut', $rut)
            ->where('s.type_staff', 3) // radiólogo
            ->where('c.holding_id', $holdingId)
            ->select('s.id as staff_id', 's.rut')
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
            $newExams   = collect($request->input('examenes', []));
            $newKindIds = $newExams
                ->map(fn($ex) => (int) ($ex['kind_id'] ?? $ex['tipo'] ?? 0))
                ->filter()
                ->values();

            // Examinations actuales de la orden
            $currentRows = DB::table('examination_order as eo')
                ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
                ->where('eo.order_id', $id)
                ->select('eo.examination_id', 'e.kind_id')
                ->get();

            $currentKindIds = $currentRows->pluck('kind_id');

            \Log::info('UPDATE_EXAMENES_DEBUG', [
                'has_examenes'    => $request->has('examenes'),
                'newKindIds'      => $newKindIds->all(),
                'currentKindIds'  => $currentKindIds->all(),
                'intersect'       => $currentKindIds->intersect($newKindIds)->all(),
                'newExams_raw'    => $newExams->all(),
            ]);

            // Actualizar examinations existentes cuando cambien trazados, piezas u otroinput
            foreach ($currentKindIds->intersect($newKindIds) as $kindId) {
                $srcEx = $newExams->first(fn($e) => (int)($e['kind_id'] ?? $e['tipo'] ?? 0) === (int)$kindId);
                if (!$srcEx) continue;

                $hasTrazados  = array_key_exists('url_texto', $srcEx) || array_key_exists('trazados', $srcEx);
                $hasDientes   = array_key_exists('dientes', $srcEx);
                $hasOtroinput = array_key_exists('trazados_otros_texto', $srcEx) || array_key_exists('otroinput', $srcEx);

                if (!$hasTrazados && !$hasDientes && !$hasOtroinput) continue;

                $row = $currentRows->first(fn($r) => $r->kind_id == $kindId);
                if (!$row) continue;

                $updateData = ['updated_at' => now()];

                if ($hasTrazados) {
                    $urlTexto = $this->extractUrlTexto($srcEx);
                    $updateData['url_texto'] = $urlTexto;
                    $updateData = array_merge($updateData, $this->trazadosToOtrochecks($urlTexto));
                }
                if ($hasDientes) {
                    $updateData['piezas'] = is_array($srcEx['dientes']) && count($srcEx['dientes']) > 0
                        ? implode(',', $srcEx['dientes'])
                        : null;
                }
                if ($hasOtroinput) {
                    $updateData['otroinput'] = $srcEx['trazados_otros_texto'] ?? $srcEx['otroinput'];
                }

                DB::table('examinations')->where('id', $row->examination_id)->update($updateData);
            }

            // Agregar kind_ids nuevos que no existen todavía
            foreach ($newKindIds->diff($currentKindIds) as $kindId) {
                $srcEx = $newExams->first(fn($e) => (int)($e['kind_id'] ?? $e['tipo'] ?? 0) === (int)$kindId);
                $piezas = (isset($srcEx['dientes']) && is_array($srcEx['dientes']))
                    ? implode(',', $srcEx['dientes'])
                    : null;

                $urlTextoNew = $this->extractUrlTexto($srcEx);
                $exId = DB::table('examinations')->insertGetId([
                    'kind_id'    => $kindId,
                    'piezas'     => $piezas,
                    'url_texto'  => $urlTextoNew,
                    'otroinput'  => $srcEx['trazados_otros_texto'] ?? $srcEx['otroinput'] ?? null,
                    ...$this->trazadosToOtrochecks($urlTextoNew),
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

            // Eliminar kind_ids que ya no están en la lista, pero solo si no tienen archivos
            foreach ($currentRows->whereIn('kind_id', $currentKindIds->diff($newKindIds)) as $row) {
                $hasFiles = DB::table('files')->where('examination_id', $row->examination_id)->exists();
                if (! $hasFiles) {
                    DB::table('examination_order')
                        ->where('order_id', $id)
                        ->where('examination_id', $row->examination_id)
                        ->delete();
                    DB::table('examinations')->where('id', $row->examination_id)->delete();
                }
            }
        }

        return response()->json(['message' => 'Orden actualizada.']);
    }

    // GET /api/v3/order/pdf/{id}
    public function generatePdf(Request $request, int $id)
    {
        $order = DB::table('orders')->where('id', $id)->first('id');
        if (! $order) return response()->json(['error' => 'Orden no encontrada.'], 404);

        // URL firmada temporal (4 h) — DentalSoft no tiene sesión web
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'ordenes.pdf.signed',
            now()->addHours(4),
            ['id' => $id]
        );

        return response()->json(['url' => $url]);
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

    /**
     * Presigned S3 URL para visualización inline (sin Content-Disposition).
     */
    private function normalizeExt(?string $ext, ?string $name = null, ?string $ruta = null): string
    {
        $clean = '';
        if ($ext) {
            $clean = strtolower(ltrim(trim((string) $ext), '.'));
        }
        // Try the ruta (S3 file path) — most reliable for legacy records with empty extension
        if ($clean === '' && $ruta && preg_match('/\.([a-z0-9]{1,5})$/i', $ruta, $m)) {
            $clean = strtolower($m[1]);
        }
        // Fall back to name only if it ends with a real dotted extension
        if ($clean === '' && $name && preg_match('/\.([a-z0-9]{1,5})$/i', $name, $m)) {
            $clean = strtolower($m[1]);
        }
        // Normalize common aliases so consumers don't need to handle both variants
        return ['jpeg' => 'jpg', 'tiff' => 'tif'][$clean] ?? $clean;
    }

    private function isImageExt(?string $ext, ?string $name = null, ?string $ruta = null): bool
    {
        return in_array(
            $this->normalizeExt($ext, $name, $ruta),
            ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif']
        );
    }

    // Convierte url_texto a columnas otrocheck* que DentalSoft lee para pre-poblar checkboxes.
    private function trazadosToOtrochecks(?string $urlTexto): array
    {
        $t = $this->urlTextoToTrazados($urlTexto);
        return [
            'otrocheck'  => in_array('rickets',  $t) ? 1 : 0,
            'otrocheck1' => in_array('roth',     $t) ? 1 : 0,
            'otrocheck2' => in_array('jaraback', $t) ? 1 : 0,
            'otrocheck3' => in_array('steiner',  $t) ? 1 : 0,
            'otrocheck4' => in_array('mcnamara', $t) ? 1 : 0,
            'otrocheck5' => in_array('otros',    $t) ? 1 : 0,
        ];
    }

    // El sistema antiguo guardaba MIME type en extension (image/jpeg, application/pdf).
    // DentalSoft chequea extension.startsWith('image/') para mostrar thumbnail.
    private function extToMime(?string $ext): string
    {
        return match(strtolower((string) $ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            'tif', 'tiff' => 'image/tiff',
            'pdf'         => 'application/pdf',
            'dcm'         => 'application/dicom',
            default       => 'application/octet-stream',
        };
    }

    private function apiFileUrl(?string $ruta): ?string
    {
        if (!$ruta || $ruta === '0') {
            return null;
        }
        try {
            return Storage::disk('s3')->temporaryUrl($ruta, now()->addHours(24));
        } catch (\Throwable) {
            return $ruta;
        }
    }

    // URL proxy con extensión limpia — DentalSoft la usa para thumbnail y visor.
    // Redirige internamente al presigned S3 URL para evitar AccessDenied del bucket privado.
    private function apiProxyUrl(int $fileId, ?string $ruta, ?string $name = null): ?string
    {
        if (! $ruta || $ruta === '0') {
            return null;
        }

        $basename = ($name && pathinfo($name, PATHINFO_EXTENSION))
            ? $name
            : basename($ruta);

        $sig = substr(hash_hmac('sha256', $fileId . '|' . $basename, config('app.key')), 0, 20);

        // sig va ANTES del id/filename para que la URL termine limpia en .ext (sin ?querystring)
        return url('/api/v3/file/' . $sig . '/' . $fileId . '/' . rawurlencode($basename));
    }

    /**
     * Presigned S3 URL con Content-Disposition: attachment para forzar descarga.
     * DentalSoft puede usar este campo para mostrar un botón "Descargar".
     */
    private function apiDownloadUrl(?string $ruta, ?string $name = null, ?string $extension = null): ?string
    {
        if (!$ruta || $ruta === '0') {
            return null;
        }
        try {
            $filename = $name ?: ('archivo' . ($extension ? '.' . $extension : ''));
            return Storage::disk('s3')->temporaryUrl($ruta, now()->addHours(24), [
                'ResponseContentDisposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
            ]);
        } catch (\Throwable) {
            return $this->apiFileUrl($ruta);
        }
    }

    /**
     * Convierte url_texto almacenado en Dimage ("Análisis Rickets,Análisis Roth")
     * al array de nombres cortos que usa DentalSoft (["rickets","roth"]).
     * Se incluye en la respuesta del API para que DentalSoft pueda pre-marcar
     * los checkboxes al cargar una orden guardada.
     */
    private function urlTextoToTrazados(?string $urlTexto): array
    {
        if (!$urlTexto) return [];
        $reverseMap = [
            'análisis rickets'  => 'rickets',
            'análisis roth'     => 'roth',
            'análisis jaraback' => 'jaraback',
            'análisis steiner'  => 'steiner',
            'análisis mcnamara' => 'mcnamara',
            'otros'             => 'otros',
        ];
        $result = [];
        foreach (explode(',', $urlTexto) as $part) {
            $lower = strtolower(trim($part));
            if (str_starts_with($lower, 'otros:')) {
                $result[] = 'otros';
            } else {
                $result[] = $reverseMap[$lower] ?? $lower;
            }
        }
        return array_values(array_filter($result));
    }

    /**
     * Extrae url_texto de un item de examen enviado por DentalSoft.
     * Acepta tanto `url_texto` (string) como `trazados` (array o string).
     * Normaliza los valores cortos de DentalSoft ("rickets") al formato
     * completo que usa Dimage ("Análisis Rickets").
     */
    private function extractUrlTexto(array $ex): ?string
    {
        // DentalSoft envía nombres en minúsculas sin prefijo
        $map = [
            'rickets'  => 'Análisis Rickets',
            'roth'     => 'Análisis Roth',
            'jaraback' => 'Análisis Jaraback',
            'steiner'  => 'Análisis Steiner',
            'mcnamara' => 'Análisis Mcnamara',
            'otros'    => 'Otros',
        ];

        if (array_key_exists('url_texto', $ex)) {
            return $ex['url_texto'] ?: null;
        }
        if (array_key_exists('trazados', $ex)) {
            $t = $ex['trazados'];
            if (is_array($t)) {
                $parts = array_values(array_filter(array_map(function ($v) use ($map) {
                    $v = trim((string) $v);
                    if ($v === '') return null;
                    // Normalizar: "rickets" → "Análisis Rickets"; ya formateado queda igual
                    return $map[strtolower($v)] ?? $v;
                }, $t)));
                return $parts ? implode(',', $parts) : null;
            }
            return is_string($t) && $t !== '' ? $t : null;
        }
        return null;
    }
}
