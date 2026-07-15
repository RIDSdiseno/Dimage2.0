<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCbctZip;
use App\Mail\OrdenAsignada;
use App\Models\Clinic;
use App\Models\Examination;
use App\Models\Kind;
use App\Models\KindGroup;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function visorDicom(Request $request)
    {
        if ($request->has('id')) {
            $id   = (int) $request->get('id');
            $name = $request->get('name', 'DICOM');
            $file = DB::table('files')->where('id', $id)->first(['ruta', 'ruta_dcm']);

            abort_if(!$file || !$file->ruta, 404);

            $base    = request()->getSchemeAndHttpHost();
            $urlMap  = [];

            if ($file->ruta_dcm && $file->ruta_dcm !== 'processing') {
                if (in_array(strtolower(pathinfo($file->ruta_dcm, PATHINFO_EXTENSION)), ['dcm', 'dicom'], true)) {
                    $fileUrl = Storage::disk('s3')->temporaryUrl($file->ruta_dcm, now()->addHours(2));
                    return view('visor3d', compact('fileUrl', 'name', 'urlMap'));
                }

                // CBCT series: pre-generate signed S3 URLs for every slice so
                // the JS XHR interceptor can bypass the PHP proxy entirely.
                $baseProxy = "{$base}/archivos/{$id}/dcm";

                $paths = Cache::remember("serie_paths_{$id}", 3600, function () use ($file) {
                    $all = Storage::disk('s3')->allFiles($file->ruta_dcm);
                    $dcm = array_filter($all, fn($p) => in_array(
                        strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['dcm', 'dicom'], true
                    ));
                    sort($dcm);
                    return array_values($dcm);
                });

                if (!empty($paths)) {
                    // Use actual first DCM slice — ruta may still point to legacy ZIP path
                    $fileUrl = $baseProxy . '/' . rawurlencode(basename($paths[0]));

                    foreach ($paths as $path) {
                        $proxyUrl = $baseProxy . '/' . rawurlencode(basename($path));
                        try {
                            $urlMap[$proxyUrl] = Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
                        } catch (\Throwable) {}
                    }
                } else {
                    // No DCM slices in prefix — serve file directly (ZIP fallback)
                    try {
                        $fileUrl = Storage::disk('s3')->temporaryUrl($file->ruta, now()->addHours(2));
                    } catch (\Throwable) {
                        $fileUrl = "{$base}/archivos/{$id}/" . rawurlencode(basename($file->ruta));
                    }
                }
            } else {
                try {
                        $fileUrl = Storage::disk('s3')->temporaryUrl($file->ruta, now()->addHours(2));
                    } catch (\Throwable) {
                        $fileUrl = "{$base}/archivos/{$id}/" . rawurlencode(basename($file->ruta));
                    }
            }

            return view('visor3d', compact('fileUrl', 'name', 'urlMap'));
        }

        return view('visor3d');
    }

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

        $currentStaff       = DB::table('staffs')->where('user_id', $user->id)->first(['id', 'puede_editar_ordenes_asignadas']);
        $currentStaffId     = $currentStaff?->id;

        $operatorClinicIds  = $currentStaffId ? $this->clinicIdsForStaff((int) $currentStaffId) : collect();
        $isOdontologo       = $user->hasRole('odontologo') || (int) ($user->type_id ?? 0) === 6;
        $isRadiologo        = $user->hasRole('radiologo')  || (int) ($user->type_id ?? 0) === 5;
        $puedeEditarAsignadas = $isOdontologo && (bool) ($currentStaff?->puede_editar_ordenes_asignadas ?? false);

        $query = Order::query()
            ->select([
                'orders.id',
                'orders.clinic_id',
                'orders.created_at',
                'orders.enviada',
                'orders.respondida',
                'orders.estadoradiologo',
                'orders.estadoodontologo',
                'orders.prioridad',
                'orders.operator_id',
                'orders.odontologo_id',
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
            ->addSelect(DB::raw("
                (
                    CASE
                        WHEN orders.operator_id IS NOT NULL THEN
                            (SELECT u.name FROM staffs s INNER JOIN users u ON u.id = s.user_id WHERE s.id = orders.operator_id LIMIT 1)
                        ELSE uc.name
                    END
                ) as creado_por
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

        // Radiólogo: agregar su estado personal por orden desde order_staff_exam
        // Usamos $user->staff->id (misma fuente que applyRoleFilter y show()) para garantizar
        // que el staff_id coincide con el registrado en order_staff_exam
        if ($isRadiologo && $user->staff) {
            $sid = (int) $user->staff->id;
            $query->addSelect(DB::raw(
                "(SELECT ose.respondida FROM order_staff_exam ose
                  WHERE ose.order_id = orders.id AND ose.staff_id = {$sid}
                  ORDER BY ose.id LIMIT 1) as mi_respondida"
            ));
            $query->addSelect(DB::raw(
                "(SELECT COALESCE(ose.borrador, 0) FROM order_staff_exam ose
                  WHERE ose.order_id = orders.id AND ose.staff_id = {$sid}
                  ORDER BY ose.id LIMIT 1) as mi_borrador"
            ));
        }

        $this->applyRoleFilter($query, $user, $puedeEditarAsignadas);

        if ($soloMis && $user) {
            $this->applyMisOrdenesFilter($query, $user, $puedeEditarAsignadas);
        }

        $orders = $query
            ->orderByDesc('orders.created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $estadosRadiologoPersonal = [
            0 => self::ESTADOS[0],  // No Informada — aún no respondió su parte
            1 => ['label' => 'Informada',     'color' => 'success'],
            2 => ['label' => 'En corrección', 'color' => 'danger'],
        ];

        $items = collect($orders->items())->map(function ($o) use ($currentStaffId, $operatorClinicIds, $isOdontologo, $isRadiologo, $estadosRadiologoPersonal, $puedeEditarAsignadas) {
            // mi_borrador=1 (nuevo) O estadoradiologo=4 con enviada (borrador guardado con código antiguo)
            $miBorrador = $isRadiologo && (
                (int) ($o->mi_borrador ?? 0) === 1
                || (int) $o->estadoradiologo === 4
            );
            $estado = ($isRadiologo && isset($o->mi_respondida))
                ? (
                    $miBorrador
                        ? self::ESTADOS[4]  // solo este radiólogo ve "Guardada"
                        : (
                            (int) $o->estadoradiologo === 2
                                ? ['label' => 'En corrección', 'color' => 'danger']
                                : ($estadosRadiologoPersonal[(int) $o->mi_respondida] ?? self::ESTADOS[(int) $o->estadoradiologo])
                        )
                )
                : (self::ESTADOS[(int) $o->estadoradiologo] ?? ['label' => 'Desconocido', 'color' => 'secondary']);

            return [
                'id'         => $o->id,
                'paciente'   => $o->paciente,
                'rut'        => $o->rut,
                'clinica'    => $o->clinica,
                'odontologo' => $o->odontologo ?: '-',
                'radiologos' => $o->radiologos ?: '-',
                'tipo_examen'=> $o->tipo_examen ?: '-',
                'created_at' => $o->created_at  ? Carbon::parse($o->created_at)->format('d/m/Y')  : '-',
                'enviada'    => $o->enviada      ? Carbon::parse($o->enviada)->format('d/m/Y')      : '-',
                'respondida' => $o->respondida   ? Carbon::parse($o->respondida)->format('d/m/Y')  : '-',
                'estado'     => $estado,
                'prioridad'  => $o->prioridad,
                'creado_por' => $o->creado_por ?: '-',
                'es_mia'     => $currentStaffId && (
                    (!is_null($o->operator_id ?? null) && (int) $o->operator_id === (int) $currentStaffId) ||
                    (!$isOdontologo && $operatorClinicIds->contains($o->clinic_id)) ||
                    ($puedeEditarAsignadas && !is_null($o->odontologo_id ?? null) && (int) $o->odontologo_id === (int) $currentStaffId)
                ),
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $orders->total(),
            'pages' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ]);
    }

    private function guardRadiologoCrear(): void
    {
        $user = Auth::user();
        if ((int) $user->type_id === 5) {
            $puede = DB::table('staffs')->where('user_id', $user->id)->value('puede_crear_ordenes');
            if (! $puede) {
                abort(403, 'Sin permiso para crear órdenes.');
            }
        }
    }

    private function buildExamTabs(): array
    {
        $groups = KindGroup::orderBy('tab')->orderBy('orden')->orderBy('id')->get();
        $kinds  = Kind::whereIn('group', $groups->pluck('id'))->orderBy('id')->get(['id', 'descipcion', 'group']);

        $tabs = [];
        foreach ($groups as $g) {
            $tab = $g->tab; // 'intraorales' | 'extraorales'
            if (!isset($tabs[$tab])) $tabs[$tab] = [];
            $tabs[$tab][] = [
                'group_id' => $g->id,
                'nombre'   => $g->nombre,
                'items'    => $kinds->where('group', (string) $g->id)
                    ->map(fn ($k) => ['id' => $k->id, 'label' => $k->descipcion])
                    ->values(),
            ];
        }
        return $tabs;
    }

    public function create(Request $request): Response
    {
        $this->guardRadiologoCrear();
        $user = Auth::user();

        $clinics = $this->clinicsForUser($user)
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->user->name ?? "Clínica #{$c->id}",
                ];
            })
            ->values();

        $pacientePreseleccionado = null;
        if ($request->filled('patient_id')) {
            $p = DB::table('patients')->where('id', (int) $request->patient_id)->first(['id', 'name', 'rut']);
            if ($p) {
                $pacientePreseleccionado = ['id' => $p->id, 'name' => $p->name, 'rut' => $p->rut];
            }
        }

        return Inertia::render('Orders/Create', [
            'examTypes'               => $this->buildExamTabs(),
            'clinics'                 => $clinics,
            'canSelectRadiologo'      => $this->canSelectRadiologo($user),
            'pacientePreseleccionado' => $pacientePreseleccionado,
        ]);
    }

    private function canSelectRadiologo($user): bool
    {
        // Admin, secretaria, holding, contralor siempre pueden seleccionar
        if ($user->hasAnyRole(['admin', 'secretaria', 'holding', 'contralor'])) {
            return true;
        }
        // Clínica: SIEMPRE auto-asignación aleatoria (sin permiso manual)
        if ($user->hasRole('clinica') || (int) ($user->type_id ?? 0) === 4) {
            return false;
        }
        // Técnico y odontólogo: depende del permiso puede_seleccionar_radiologo
        if ($user->hasAnyRole(['tecnico', 'odontologo']) || in_array((int) ($user->type_id ?? 0), [6, 11])) {
            $staff = DB::table('staffs')->where('user_id', $user->id)->first(['puede_seleccionar_radiologo']);
            return (bool) ($staff->puede_seleccionar_radiologo ?? false);
        }
        return false;
    }

    public function getPatients(Request $request): JsonResponse
    {
        $term     = trim((string) $request->get('q', ''));
        $clinicId = $request->get('clinic_id') ? (int) $request->get('clinic_id') : null;
        $user     = Auth::user();

        $query = Patient::query()->select('id', 'name', 'rut');

        // Operadores solo pueden ver pacientes de sus propias clínicas
        if ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $allowedClinicIds = DB::table('clinic_staff')
                ->where('staff_id', $user->staff->id)
                ->pluck('clinic_id');

            if ($allowedClinicIds->isEmpty()) {
                return response()->json([]);
            }

            // Si pasan un clinic_id, validar que pertenece a sus clínicas
            if ($clinicId && !$allowedClinicIds->contains($clinicId)) {
                return response()->json([]);
            }

            $filterIds = $clinicId ? [$clinicId] : $allowedClinicIds->all();
            $query->whereHas('clinics', function (Builder $q) use ($filterIds): void {
                $q->whereIn('clinics.id', $filterIds);
            });
        } elseif ($clinicId) {
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
            ->where(function ($q) { $q->where('staffs.type_staff', 3)->orWhere('staffs.type_staff', 5); })
            ->where('clinic_staff.clinic_id', $clinicId)
            ->groupBy('staffs.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json($radiologos);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guardRadiologoCrear();
        $rules = [
            'patient_id'   => ['required', 'exists:patients,id'],
            'clinic_id'    => ['required', 'exists:clinics,id'],
            'odontologo_id'=> ['nullable', 'exists:staffs,id'],
            'radiologo_id' => ['nullable', 'exists:staffs,id'],
            'prioridad'    => ['required', 'in:1 día,2 días,3 días,Normal,Urgente'],
            'examenes'     => ['required', 'array', 'min:1'],
            'action'       => ['required', 'in:guardar,enviar'],
        ];
        if (!$request->boolean('sin_diagnostico')) {
            $rules['diagnostico'] = ['required', 'min:3'];
        }
        $request->validate($rules);

        // Validate all exam IDs in one query instead of N queries
        $examenes = array_unique(array_map('intval', (array) $request->examenes));
        $validCount = DB::table('kinds')->whereIn('id', $examenes)->count();
        if ($validCount < count($examenes)) {
            abort(422, 'Tipo de examen inválido.');
        }

        $user = Auth::user();
        $enviar = $request->action === 'enviar';
        $odontologoId = $request->odontologo_id;

        if ($user->hasRole('odontologo') && $user->staff) {
            $odontologoId = $user->staff->id;
        }

        // Guardar el staff_id del operador/técnico que crea la orden
        $operatorId = null;
        if ($user->hasAnyRole(['tecnico', 'odontologo']) && $user->staff) {
            $operatorId = $user->staff->id;
        }

        // Determinar asignaciones de radiólogo por examen
        $assignments = $this->buildAssignments($request, $examenes, (int) $request->clinic_id, $enviar, $user);
        $radiologoId = !empty($assignments) ? $assignments[0]['radiologo_id'] : null; // for orders.radiologo_id

        $cbctJobs = [];
        DB::transaction(function () use ($request, $enviar, $odontologoId, $examenes, $radiologoId, $assignments, $operatorId, &$cbctJobs): void {
            $order = Order::create([
                'patient_id' => $request->patient_id,
                'clinic_id' => $request->clinic_id,
                'odontologo_id' => $odontologoId ?: 0,
                'radiologo_id' => $radiologoId ?: 0,
                'operator_id' => $operatorId,
                'diagnostico' => $request->boolean('sin_diagnostico') ? 'Sin diagnóstico' : ($request->diagnostico ?? ''),
                'observaciones' => $request->observaciones ?? '',
                'observaciones_2' => $request->observaciones_2 ?? '',
                'prioridad' => $request->prioridad,
                'estadoradiologo' => $enviar ? 0 : 4,
                'estadoodontologo' => $enviar ? 0 : 1,
                'enviada' => $enviar ? now() : null,
                'sin_diagnostico' => $request->boolean('sin_diagnostico') ? 1 : 0,
            ]);

            $examOrderRows = [];
            $fileRows      = [];

            foreach ($examenes as $kindId) {
                $piezasRaw = $request->input("piezas_{$kindId}");
                $piezasStr = null;
                if (!empty($piezasRaw)) {
                    $piezasStr = implode(',', array_map('intval', (array) $piezasRaw));
                }

                $urlTexto = $request->input("url_{$kindId}") ?: null;

                $examinationId = DB::table('examinations')->insertGetId([
                    'kind_id'    => $kindId,
                    'piezas'     => $piezasStr,
                    'url_texto'  => $urlTexto,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $examOrderRows[] = [
                    'order_id'       => $order->id,
                    'examination_id' => $examinationId,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                // ZIP CBCT pre-subido en segundo plano (eager upload)
                $cbctTempPath = $request->input("cbct_s3_path_{$kindId}");
                if ($cbctTempPath) {
                    $finalPath = "ordenes/{$order->id}/" . basename($cbctTempPath);
                    Storage::disk('s3')->move($cbctTempPath, $finalPath);

                    $fileRows[] = [
                        'ruta'               => $finalPath,
                        'examination_id'     => $examinationId,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                        'name'               => $request->input("cbct_s3_name_{$kindId}", basename($finalPath)),
                        'type_id'            => 0,
                        'extension'          => 'zip',
                        'ruta_dcm'           => 'processing',
                        'nombre_dcm'         => $finalPath,
                        'file_size'          => (int) $request->input("cbct_s3_size_{$kindId}", 0),
                        'file_size_procesed' => 1,
                        'file_size_error'    => null,
                        'desde_informar'     => 0,
                    ];
                    continue;
                }

                $fileKey = "files_{$kindId}";
                if (!$request->hasFile($fileKey)) {
                    continue;
                }

                $kindGroup = $this->kindGroupFor((int) $kindId);

                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) {
                        continue;
                    }

                    $stored = $this->storeUploadedFile($file, $order->id, $kindGroup);

                    $fileRows[] = [
                        'ruta'               => $stored['ruta'],
                        'examination_id'     => $examinationId,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                        'name'               => $stored['name'],
                        'type_id'            => 0,
                        'extension'          => $stored['extension'],
                        'ruta_dcm'           => $stored['ruta_dcm'],
                        'nombre_dcm'         => null,
                        'file_size'          => $stored['file_size'],
                        'file_size_procesed' => 1,
                        'file_size_error'    => null,
                        'desde_informar'     => 0,
                    ];
                }
            }

            DB::table('examination_order')->insert($examOrderRows);

            foreach ($fileRows as $row) {
                $fid = DB::table('files')->insertGetId($row);
                if (($row['ruta_dcm'] ?? null) === 'processing') {
                    $cbctJobs[] = [$fid, $row['ruta']];
                }
            }

            if (!empty($assignments)) {
                $this->insertRadiologoAssignments($order->id, $assignments);
            }

            $uniqueStaffIds = array_values(array_unique(array_filter(
                array_merge([$odontologoId], array_column($assignments, 'radiologo_id'))
            )));
            if (!empty($uniqueStaffIds)) {
                DB::table('order_staff')->insertOrIgnore(
                    array_map(fn($sid) => ['order_id' => $order->id, 'staff_id' => (int) $sid], $uniqueStaffIds)
                );
            }
        });

        if ($enviar) {
            foreach (array_unique(array_column($assignments, 'radiologo_id')) as $rid) {
                $this->notificarRadiologo((int) $rid);
            }
        }

        // Procesar ZIPs CBCT en segundo plano (1+ GB no puede bloquear el request)
        foreach ($cbctJobs as [$fid, $zipPath]) {
            ProcessCbctZip::dispatch($fid, $this->extractOrderIdFromPath($zipPath), $zipPath)
                ->onConnection('database')->onQueue('default');
        }

        return redirect()
            ->route('ordenes.index')
            ->with('success', $enviar ? '¡Orden enviada al radiólogo!' : '¡Orden guardada!');
    }

    public function show(Order $order): Response
    {
        $user = Auth::user();

        $canSeeInforme = (int) $order->estadoradiologo === 1
            || $user->hasAnyRole(['admin', 'secretaria', 'radiologo'])
            || (int) ($user->type_id ?? 0) === 1;

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
                'examinations.piezas',
            ])
            ->get()
            ->map(function ($e) use ($canSeeInforme) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->where('desde_informar', '!=', 1)
                    ->get(['id', 'name', 'ruta', 'ruta_dcm', 'nombre_dcm', 'extension', 'file_size'])
                    ->map(fn ($f) => array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]));

                $respuesta       = null;
                $archivosInforme = collect();

                if ($canSeeInforme) {
                    $ans = DB::table('answers')
                        ->where('examination_id', $e->examination_id)
                        ->first();

                    if ($ans) {
                        $respuesta = (array) $ans;
                        $respuesta['solo_adjunto'] = (bool) ($ans->solo_adjunto ?? false);
                        if ($e->kind_id == self::PANORAMICA_KIND_ID && !empty($ans->content)) {
                            $c = json_decode($ans->content, true) ?? [];
                            $respuesta['informe_examen']    = $c['examen']    ?? '';
                            $respuesta['informe_libre']     = $c['informe']   ?? '';
                            $respuesta['informe_impresion'] = $c['impresion'] ?? '';
                        }
                    }

                    $archivosInforme = DB::table('files')
                        ->where('examination_id', $e->examination_id)
                        ->where('desde_informar', 1)
                        ->get(['id', 'name', 'ruta', 'extension', 'file_size'])
                        ->map(fn ($f) => array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]));
                }

                return [
                    'id'               => $e->examination_id,
                    'kind_id'          => $e->kind_id,
                    'descripcion'      => $e->descripcion,
                    'grupo'            => (int) $e->grupo,
                    'url_texto'        => $e->url_texto,
                    'piezas'           => $e->piezas,
                    'archivos'         => $archivos,
                    'archivos_informe' => $archivosInforme,
                    'respuesta'        => $respuesta,
                ];
            });

        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $order->id)
            ->select('s.id', 'u.name', DB::raw('MAX(ose.respondida) as respondida'))
            ->groupBy('s.id', 'u.name')
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
            ->get(['id', 'detalle', 'enviada', 'respondida', 'description', 'status', 'created_at']);

        $esRadiologoAsignado = $user->hasRole('radiologo') && $radiologos->contains('id', $user->staff?->id);

        // Si este radiólogo tiene borrador personal, él ve "Guardada"; los demás ven el estado global
        $miBorrador = $esRadiologoAsignado && $user->staff && DB::table('order_staff_exam')
            ->where('order_id', $order->id)
            ->where('staff_id', $user->staff->id)
            ->where('borrador', 1)
            ->exists();

        $estado = $miBorrador
            ? self::ESTADOS[4]
            : (self::ESTADOS[(int) $order->estadoradiologo] ?? ['label' => 'Desconocido', 'color' => 'secondary']);
        // Si ningún radiólogo está asignado y la orden está pendiente, cualquier radiólogo puede responder
        $sinAsignar = $radiologos->isEmpty() && (int) $order->estadoradiologo === 0;

        // Radiólogo con asignación pendiente (respondida=0) en order_staff_exam — puede responder
        // aunque estadoradiologo=1 (otro ya respondió en orden compartida)
        $miPendiente = $user->hasRole('radiologo') && $user->staff && DB::table('order_staff_exam')
            ->where('order_id', $order->id)
            ->where('staff_id', $user->staff->id)
            ->where('respondida', 0)
            ->exists();

        $puedeResponder = $user->hasAnyRole(['radiologo', 'admin', 'secretaria'])
            && (
                in_array((int) $order->estadoradiologo, [0, 4]) ||
                $miPendiente ||
                ($user->hasAnyRole(['admin', 'secretaria']) && in_array((int) $order->estadoradiologo, [1, 2])) ||
                ($esRadiologoAsignado && (int) $order->estadoradiologo === 2)
            )
            && ($user->hasAnyRole(['admin', 'secretaria']) || $esRadiologoAsignado || $miPendiente || ($user->hasRole('radiologo') && $sinAsignar));

        // Radiólogo asignado solo ve sus exámenes, no los de los demás radiólogos
        if ($esRadiologoAsignado && $user->staff) {
            $oseRows = DB::table('order_staff_exam')
                ->where('order_id', $order->id)
                ->where('staff_id', $user->staff->id)
                ->get(['kind_id']);
            $hasNullAssignment = $oseRows->contains('kind_id', null);
            if (!$hasNullAssignment) {
                $assignedKindIds = $oseRows->pluck('kind_id')->filter()->toArray();
                if (!empty($assignedKindIds)) {
                    $examenes = $examenes->filter(fn($e) => in_array($e['kind_id'], $assignedKindIds))->values();
                }
            }
        }

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
            'canEdit'        => (int) $order->estadoradiologo !== 1,
            'esAdmin'        => $user->type_id === 1 || $user->hasRole('admin'),
            'esRadiologo'    => $user->type_id === 5 || $user->hasRole('radiologo'),
        ]);
    }

    // ── Responder orden ───────────────────────────────────────────────────

    public function responder(Order $order): Response|RedirectResponse
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['radiologo', 'admin', 'secretaria'])) {
            return redirect()->route('ordenes.show', $order)->with('error', 'Sin permiso para responder órdenes.');
        }

        if ((int) $order->estadoradiologo === 1 && !$user->hasRole('radiologo')) {
            return redirect()->route('ordenes.show', $order)->with('error', 'La orden ya fue respondida.');
        }

        // Filtrar exámenes según asignación del radiólogo
        $examenesQuery = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select([
                'examinations.id as examination_id',
                'kinds.id as kind_id',
                'kinds.descipcion as descripcion',
                'kinds.group as grupo',
                'examinations.piezas',
                'examinations.url_texto',
            ]);

        if ($user->hasRole('radiologo') && $user->staff) {
            $staffId = (int) $user->staff->id;
            $oseRows = DB::table('order_staff_exam')
                ->where('order_id', $order->id)
                ->where('staff_id', $staffId)
                ->get(['kind_id']);
            $hasNullAssignment = $oseRows->contains('kind_id', null);
            if (!$hasNullAssignment && $oseRows->isNotEmpty()) {
                $assignedKindIds = $oseRows->pluck('kind_id')->toArray();
                $examenesQuery->whereIn('examinations.kind_id', $assignedKindIds);
            }
        }

        $examenes = $examenesQuery->get()
            ->map(function ($e) {
                $ans = DB::table('answers')
                    ->where('examination_id', $e->examination_id)
                    ->first();

                $archivos = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->get(['id', 'name', 'ruta', 'ruta_dcm', 'extension', 'file_size'])
                    ->map(function ($f) {
                        return array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]);
                    });

                $respuestaArr = $ans ? (array) $ans : null;
                if ($respuestaArr && $e->kind_id == self::PANORAMICA_KIND_ID && !empty($ans->content)) {
                    $contentData = json_decode($ans->content, true) ?? [];
                    $respuestaArr['informe_examen']    = $contentData['examen']    ?? '';
                    $respuestaArr['informe_libre']     = $contentData['informe']   ?? '';
                    $respuestaArr['informe_impresion'] = $contentData['impresion'] ?? '';
                }

                return [
                    'id'          => $e->examination_id,
                    'kind_id'     => $e->kind_id,
                    'descripcion' => $e->descripcion,
                    'grupo'       => $e->grupo,
                    'piezas'      => $e->piezas,
                    'url_texto'   => $e->url_texto,
                    'archivos'    => $archivos,
                    'respuesta'   => $respuestaArr,
                ];
            });

        $paciente = DB::table('patients')->where('id', $order->patient_id)->first(['name', 'rut', 'dateofbirth']);
        $clinica  = DB::table('clinics as c')->join('users as u', 'u.id', '=', 'c.user_id')
                      ->where('c.id', $order->clinic_id)->value('u.name');

        $staff = DB::table('staffs')->where('user_id', $user->id)->first(['solo_adjuntar_informe']);
        $conPermisoSoloAdjuntar = (bool) ($staff->solo_adjuntar_informe ?? false);

        return Inertia::render('Orders/Respond', [
            'order' => [
                'id'             => $order->id,
                'diagnostico'    => $order->diagnostico,
                'observaciones'  => $order->observaciones,
                'observaciones_2'=> $order->observaciones_2,
                'prioridad'      => $order->prioridad,
                'created_at'     => $order->created_at ? Carbon::parse($order->created_at)->format('d/m/Y H:i') : null,
                'enviada'        => $order->enviada    ? Carbon::parse($order->enviada)->format('d/m/Y H:i') : null,
            ],
            'paciente'               => $paciente,
            'clinica'                => $clinica,
            'examenes'               => $examenes,
            'conPermisoSoloAdjuntar' => $conPermisoSoloAdjuntar,
        ]);
    }

    private const PANORAMICA_KIND_ID = 15;

    private const PANORAMICA_DIENTES = [
        11,12,13,14,15,16,17,18,
        21,22,23,24,25,26,27,28,
        31,32,33,34,35,36,37,38,
        41,42,43,44,45,46,47,48,
        51,52,53,54,55,
        61,62,63,64,65,
        71,72,73,74,75,
        81,82,83,84,85,
    ];

    public function doResponder(Request $request, Order $order): RedirectResponse
    {
        $user = Auth::user();

        if (!$user->hasAnyRole(['radiologo', 'admin', 'secretaria'])) {
            return redirect()->route('ordenes.show', $order)->with('error', 'Sin permiso.');
        }

        $soloAdjunto = $request->boolean('solo_adjunto');
        $action      = $request->input('action', 'responder'); // responder | borrador | correccion

        $request->validate([
            'respuestas'      => ['required', 'array', 'min:1'],
            'respuestas.*.id' => ['required', 'exists:examinations,id'],
        ]);

        DB::transaction(function () use ($request, $order, $user, $soloAdjunto, $action): void {
            foreach ($request->respuestas as $r) {
                $examinationId = (int) $r['id'];
                $examRow = DB::table('examinations')
                    ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
                    ->where('examinations.id', $examinationId)
                    ->select('examinations.kind_id', 'examinations.piezas', 'kinds.descipcion as descripcion')
                    ->first();
                $kindId        = $examRow ? (int) $examRow->kind_id : null;
                $piezasStr     = $examRow->piezas ?? '';
                $descripcion   = strtolower($examRow->descripcion ?? '');
                $isPanoramica  = ($kindId === self::PANORAMICA_KIND_ID);
                $isRetroTotal  = str_contains($descripcion, 'retroalveolar') && str_contains($descripcion, 'total');
                $isNinoExam    = preg_match('/ni[ñn]/u', $descripcion) > 0;

                if ($isPanoramica) {
                    $answerData = ['solo_adjunto' => $soloAdjunto];
                    for ($i = 1; $i <= 9; $i++) {
                        $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    }
                    foreach (self::PANORAMICA_DIENTES as $d) {
                        $answerData["diente_{$d}"] = $r["diente_{$d}"] ?? null;
                    }
                } elseif (!empty($piezasStr) || $isRetroTotal) {
                    // Unitaria con piezas o Retroalveolar Total: guardar campo_1 + diente_N
                    $isRetroUnitaria = str_contains($descripcion, 'retroalveolar') && !$isRetroTotal;
                    if (!empty($piezasStr)) {
                        $teeth = array_filter(array_map('intval', explode(',', $piezasStr)));
                    } else {
                        $perm = [11,12,13,14,15,16,17,18,21,22,23,24,25,26,27,28,
                                 31,32,33,34,35,36,37,38,41,42,43,44,45,46,47,48];
                        $temp = [51,52,53,54,55,61,62,63,64,65,71,72,73,74,75,81,82,83,84,85];
                        $teeth = $isNinoExam ? array_merge($perm, $temp) : $perm;
                    }
                    $answerData = ['campo_1' => $r['campo_1'] ?? '', 'solo_adjunto' => $soloAdjunto];
                    // Retro unitaria y total: guardar campo_2-7 (Maxilar/Mandíbula secciones)
                    for ($i = 2; $i <= 7; $i++) {
                        $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    }
                    foreach ($teeth as $p) {
                        $answerData["diente_{$p}"] = $r["diente_{$p}"] ?? null;
                    }
                } elseif (str_contains($descripcion, 'bite wing bilateral')) {
                    $answerData = ['campo_1' => $r['campo_1'] ?? '', 'solo_adjunto' => $soloAdjunto];
                    for ($i = 2; $i <= 7; $i++) {
                        $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    }
                    $bwTeeth = [13,14,15,16,17,18,43,44,45,46,47,48,53,54,55,83,84,85,
                                23,24,25,26,27,28,33,34,35,36,37,38,63,64,65,73,74,75];
                    foreach ($bwTeeth as $p) {
                        $answerData["diente_{$p}"] = $r["diente_{$p}"] ?? null;
                    }
                } elseif (str_contains($descripcion, 'bite wing unilateral derecha')) {
                    $answerData = ['campo_1' => $r['campo_1'] ?? '', 'solo_adjunto' => $soloAdjunto];
                    for ($i = 2; $i <= 4; $i++) $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    foreach ([13,14,15,16,17,18,43,44,45,46,47,48,53,54,55,83,84,85] as $p) {
                        $answerData["diente_{$p}"] = $r["diente_{$p}"] ?? null;
                    }
                } elseif (str_contains($descripcion, 'bite wing unilateral izquierda')) {
                    $answerData = ['campo_1' => $r['campo_1'] ?? '', 'solo_adjunto' => $soloAdjunto];
                    for ($i = 2; $i <= 4; $i++) $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    foreach ([23,24,25,26,27,28,33,34,35,36,37,38,63,64,65,73,74,75] as $p) {
                        $answerData["diente_{$p}"] = $r["diente_{$p}"] ?? null;
                    }
                } elseif (preg_match('/cefalom/i', $descripcion)) {
                    $answerData = ['solo_adjunto' => $soloAdjunto];
                    for ($i = 1; $i <= 9; $i++) {
                        $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    }
                } else {
                    $answerData = [
                        'campo_1'      => $r['campo_1'] ?? '',
                        'campo_2'      => $r['campo_2'] ?? '',
                        'campo_3'      => $r['campo_3'] ?? '',
                        'solo_adjunto' => $soloAdjunto,
                    ];
                }

                $existing = DB::table('answers')->where('examination_id', $examinationId)->first();
                if ($existing) {
                    DB::table('answers')->where('id', $existing->id)->update(
                        array_merge($answerData, ['updated_at' => now()])
                    );
                } else {
                    DB::table('answers')->insert(
                        array_merge($answerData, [
                            'examination_id' => $examinationId,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ])
                    );
                }

                $fileKey = "archivos_{$examinationId}";
                if ($request->hasFile($fileKey)) {
                    foreach ((array) $request->file($fileKey) as $file) {
                        if (!$file) continue;
                        $path = $file->store("informes/{$order->id}", 's3');
                        DB::table('files')->insert([
                            'ruta'               => $path,
                            'examination_id'     => $examinationId,
                            'name'               => $file->getClientOriginalName(),
                            'type_id'            => 1,
                            'extension'          => strtolower($file->getClientOriginalExtension()),
                            'ruta_dcm'           => null,
                            'nombre_dcm'         => null,
                            'file_size'          => (int) $file->getSize(),
                            'file_size_procesed' => 1,
                            'file_size_error'    => null,
                            'desde_informar'     => 1,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }
                }
            }

            // Save observaciones_2
            DB::table('orders')->where('id', $order->id)->update([
                'observaciones_2' => $request->input('observaciones_2', ''),
            ]);

            if ($action === 'borrador') {
                // No cambia el estado global — solo marca el borrador personal del radiólogo
                // Los demás siguen viendo la orden como "No Informada"
                $staffId = $user->staff?->id
                    ?? DB::table('staffs')->where('user_id', $user->id)->value('id');
                if ($staffId) {
                    DB::table('order_staff_exam')
                        ->where('order_id', $order->id)
                        ->where('staff_id', (int) $staffId)
                        ->update(['borrador' => 1]);
                }
            } elseif ($action === 'correccion') {
                $order->update(['estadoradiologo' => 2, 'estadoodontologo' => 3]);
                DB::table('corrections')->insert([
                    'order_id'    => $order->id,
                    'staff_id'    => $user->staff?->id,
                    'description' => $request->input('mensaje_correccion', ''),
                    'status'      => 'pendiente',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                // Marcar las asignaciones de este radiólogo como respondidas y limpiar borrador
                if ($user->staff) {
                    DB::table('order_staff_exam')
                        ->where('order_id', $order->id)
                        ->where('staff_id', $user->staff->id)
                        ->update(['respondida' => 1, 'borrador' => 0]);
                }

                // Solo marcar orden como completamente respondida si:
                // a) Hay asignaciones en order_staff_exam Y todas están respondidas
                // b) No hay asignaciones (orden sin radiólogo específico → un solo radiólogo)
                $tieneAsignaciones = DB::table('order_staff_exam')
                    ->where('order_id', $order->id)
                    ->exists();

                $allDone = $tieneAsignaciones
                    ? !DB::table('order_staff_exam')->where('order_id', $order->id)->where('respondida', 0)->exists()
                    : true;

                if ($allDone) {
                    $order->update([
                        'estadoradiologo'  => 1,
                        'estadoodontologo' => 1,
                        'respondida'       => now(),
                        'vista'            => 0,
                    ]);
                }
            }
        });

        $messages = [
            'borrador'   => '¡Borrador guardado correctamente!',
            'correccion' => 'Solicitud de corrección enviada.',
        ];
        return redirect()->route('ordenes.show', $order)
            ->with('success', $messages[$action] ?? '¡Orden respondida correctamente!');
    }

    public function pdf(Order $order): \Illuminate\Http\Response
    {
        $user = Auth::user();
        $isInformada = (int) $order->estadoradiologo === 1;
        $canSeeInforme = $isInformada
            || $user->hasAnyRole(['admin', 'secretaria', 'radiologo'])
            || (int) ($user->type_id ?? 0) === 1;

        return $this->buildOrderPdf($order, $canSeeInforme)->stream("orden-{$order->id}.pdf");
    }

    private function buildOrderPdf(Order $order, bool $canSeeInforme): \Barryvdh\DomPDF\PDF
    {
        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as examination_id', 'kinds.id as kind_id', 'kinds.descipcion as descripcion', 'examinations.piezas'])
            ->get()
            ->map(function ($e) use ($canSeeInforme) {
                $ans = DB::table('answers')->where('examination_id', $e->examination_id)->first();
                $respuesta = ($ans && $canSeeInforme) ? (array) $ans : null;
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

        $paciente    = DB::table('patients')->where('id', $order->patient_id)->first();
        $clinicaRow  = DB::table('clinics as c')
                         ->join('users as u', 'u.id', '=', 'c.user_id')
                         ->join('holdings as h', 'h.id', '=', 'c.holding_id')
                         ->where('c.id', $order->clinic_id)
                         ->select('u.name', 'c.logo as clinic_logo', 'h.logo as holding_logo')
                         ->first();
        $clinica     = $clinicaRow->name ?? '';
        $logoPath    = $clinicaRow->clinic_logo ?? $clinicaRow->holding_logo ?? null;
        $clinicaLogoB64 = null;
        if ($logoPath) {
            try {
                $content = Storage::disk('public')->get($logoPath);
                $ext     = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime    = match($ext) {
                    'png'        => 'image/png',
                    'gif'        => 'image/gif',
                    'jpg','jpeg' => 'image/jpeg',
                    default      => 'image/jpeg',
                };
                $clinicaLogoB64 = 'data:' . $mime . ';base64,' . base64_encode($content);
            } catch (\Throwable) {}
        }
        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $order->id)
            ->select('s.id', 'u.name', 's.firma')
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($rad) {
                // DomPDF can't use URLs directly; convert firma to base64 data URI.
                // Firmas are stored on the 'public' disk (storage/app/public/firmas/).
                $rad->firma_b64 = null;
                if (!empty($rad->firma)) {
                    try {
                        $content = Storage::disk('public')->get($rad->firma);
                        $ext     = strtolower(pathinfo($rad->firma, PATHINFO_EXTENSION));
                        $mime    = match($ext) {
                            'png'         => 'image/png',
                            'gif'         => 'image/gif',
                            'jpg','jpeg'  => 'image/jpeg',
                            default       => 'image/jpeg',
                        };
                        $rad->firma_b64 = 'data:' . $mime . ';base64,' . base64_encode($content);
                    } catch (\Throwable) {}
                }
                return $rad;
            });

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden', [
            'order'          => $order,
            'paciente'       => $paciente,
            'clinica'        => $clinica,
            'clinicaLogoB64' => $clinicaLogoB64,
            'radiologos'     => $radiologos,
            'examenes'       => $examenes,
        ]);
    }

    // GET /ordenes/{id}/pdf-signed — acceso sin sesión, URL firmada para DentalSoft
    public function pdfSigned(Request $request, int $id): \Illuminate\Http\Response
    {
        $order = Order::findOrFail($id);
        $isInformada = (int) $order->estadoradiologo === 1;

        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as examination_id', 'kinds.id as kind_id', 'kinds.descipcion as descripcion', 'examinations.piezas'])
            ->get()
            ->map(function ($e) use ($isInformada) {
                $ans = DB::table('answers')->where('examination_id', $e->examination_id)->first();
                $respuesta = ($ans && $isInformada) ? (array) $ans : null;
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

        $paciente    = DB::table('patients')->where('id', $order->patient_id)->first();
        $clinicaRow  = DB::table('clinics as c')
                         ->join('users as u', 'u.id', '=', 'c.user_id')
                         ->join('holdings as h', 'h.id', '=', 'c.holding_id')
                         ->where('c.id', $order->clinic_id)
                         ->select('u.name', 'c.logo as clinic_logo', 'h.logo as holding_logo')
                         ->first();
        $clinica     = $clinicaRow->name ?? '';
        $logoPath    = $clinicaRow->clinic_logo ?? $clinicaRow->holding_logo ?? null;
        $clinicaLogoB64 = null;
        if ($logoPath) {
            try {
                $content = Storage::disk('public')->get($logoPath);
                $ext     = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                $mime    = match($ext) {
                    'png'        => 'image/png',
                    'gif'        => 'image/gif',
                    'jpg','jpeg' => 'image/jpeg',
                    default      => 'image/jpeg',
                };
                $clinicaLogoB64 = 'data:' . $mime . ';base64,' . base64_encode($content);
            } catch (\Throwable) {}
        }
        $radiologos = DB::table('order_staff_exam as ose')
            ->join('staffs as s', 's.id', '=', 'ose.staff_id')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('ose.order_id', $order->id)
            ->select('s.id', 'u.name', 's.firma')
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($rad) {
                $rad->firma_b64 = null;
                if (!empty($rad->firma)) {
                    try {
                        $content = Storage::disk('public')->get($rad->firma);
                        $ext     = strtolower(pathinfo($rad->firma, PATHINFO_EXTENSION));
                        $mime    = match($ext) {
                            'png'         => 'image/png',
                            'gif'         => 'image/gif',
                            'jpg','jpeg'  => 'image/jpeg',
                            default       => 'image/jpeg',
                        };
                        $rad->firma_b64 = 'data:' . $mime . ';base64,' . base64_encode($content);
                    } catch (\Throwable) {}
                }
                return $rad;
            });

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.orden', [
            'order'          => $order,
            'paciente'       => $paciente,
            'clinica'        => $clinica,
            'clinicaLogoB64' => $clinicaLogoB64,
            'radiologos'     => $radiologos,
            'examenes'       => $examenes,
        ]);

        return $pdf->stream("orden-{$order->id}.pdf");
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
                ->where('id', $user->clinic->id)
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

    private function applyMisOrdenesFilter(Builder $query, $user, bool $puedeEditarAsignadas = false): void
    {
        // Radiólogo → órdenes donde está asignado (ya cubierto por applyRoleFilter, sin cambio)
        if ($user->hasRole('radiologo')) {
            return;
        }

        // Técnico → órdenes que creó + borradores de sus clínicas (creados por clínica u odontólogo)
        if ($user->hasRole('tecnico') && $user->staff) {
            $staffId   = (int) $user->staff->id;
            $clinicIds = $this->clinicIdsForStaff($staffId);
            $query->where(function ($q) use ($staffId, $clinicIds) {
                $q->where('orders.operator_id', $staffId)
                  ->orWhere('orders.odontologo_id', $staffId);
                if (!$clinicIds->isEmpty()) {
                    // Órdenes legacy sin operator_id de sus clínicas
                    $q->orWhere(function ($inner) use ($clinicIds) {
                        $inner->whereNull('orders.operator_id')
                              ->whereIn('orders.clinic_id', $clinicIds->all());
                    });
                    // Borradores de clínica/odontólogo de sus clínicas
                    $q->orWhere(function ($inner) use ($clinicIds) {
                        $inner->where('orders.estadoradiologo', 4)
                              ->whereIn('orders.clinic_id', $clinicIds->all());
                    });
                }
            });
            return;
        }

        // Odontólogo → órdenes que creó + órdenes donde está asignado (si tiene permiso)
        if ($user->hasRole('odontologo')) {
            $staffId = $user->staff?->id
                ?? DB::table('staffs')->where('user_id', $user->id)->value('id');
            if ($staffId) {
                if ($puedeEditarAsignadas) {
                    $query->where(function ($q) use ($staffId) {
                        $q->where('orders.operator_id', (int) $staffId)
                          ->orWhere('orders.odontologo_id', (int) $staffId);
                    });
                } else {
                    $query->where('orders.operator_id', (int) $staffId);
                }
            } else {
                $query->whereRaw('1 = 0');
            }
            return;
        }

        // Clínica → solo órdenes de su clínica específica (no todo el holding)
        if ($user->hasRole('clinica') && $user->clinic) {
            $query->where('orders.clinic_id', $user->clinic->id);
            return;
        }

        // Admin / Secretaria / Holding → sin filtro adicional (ven todo)
    }

    private function applyRoleFilter(Builder $query, $user, bool $puedeEditarAsignadas = false): void
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
            })->where(function ($q) {
                // Estados activos siempre visibles
                $q->whereIn('orders.estadoradiologo', [0, 1, 2])
                  // Estado 4 solo si la orden fue enviada (borrador del radiólogo,
                  // no draft del operador que aún no la envió)
                  ->orWhere(function ($q2) {
                      $q2->where('orders.estadoradiologo', 4)
                         ->whereNotNull('orders.enviada');
                  });
            });

            return;
        }


        if ($user->hasAnyRole(['odontologo', 'tecnico'])) {
            // Fallback: buscar staff por DB si la relación no carga
            $staffId = $user->staff?->id
                ?? DB::table('staffs')->where('user_id', $user->id)->value('id');

            if (!$staffId) {
                $query->whereRaw('1 = 0');
                return;
            }

            $staffId   = (int) $staffId;
            $clinicIds = $this->clinicIdsForStaff($staffId);

            if ($clinicIds->isNotEmpty()) {
                $holdingIds = DB::table('clinics')
                    ->whereIn('id', $clinicIds->all())
                    ->pluck('holding_id')
                    ->filter()->unique();

                if ($holdingIds->isNotEmpty()) {
                    if ($puedeEditarAsignadas) {
                        $query->where(function ($q) use ($holdingIds, $staffId) {
                            $q->whereIn('c.holding_id', $holdingIds->all())
                              ->orWhere('orders.odontologo_id', $staffId);
                        });
                    } else {
                        $query->whereIn('c.holding_id', $holdingIds->all());
                    }
                    return;
                }
            }

            // Sin clínica asociada o sin holding: ver solo sus propias órdenes
            $query->where(function ($q) use ($staffId) {
                $q->where('orders.operator_id', $staffId)
                  ->orWhere('orders.odontologo_id', $staffId);
            });
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

    private function operadorPuedeEditar(Order $order, $user): bool
    {
        if ($user->hasRole('radiologo')) return false;

        // Admin/secretaria/holding/contralor: sin restricción de estado
        if ($user->hasAnyRole(['admin', 'secretaria', 'holding', 'contralor'])) return true;

        $estado   = (int) $order->estadoradiologo;
        $typeId   = (int) ($user->type_id ?? 0);

        // Perfil clínica: puede editar cualquier orden propia mientras no esté respondida
        if ($user->hasRole('clinica') || $typeId === 4) {
            if ($estado === 1) return false;
            return (int) $order->clinic_id === (int) ($user->clinic?->id ?? 0);
        }

        // Operadores (tecnico/odontologo): pueden editar borradores o correcciones de sus clínicas asociadas
        // No se aplica restricción de enviada porque el rol del operador es gestionar órdenes de su clínica
        $isOperador = $user->hasAnyRole(['tecnico', 'odontologo']) || in_array($typeId, [6, 11]);
        if (!$isOperador) return false;

        if (!($estado === 2 || $estado === 4)) return false;

        // Buscar staff_id tanto por relación Eloquent como por consulta directa (fallback)
        $staffId = $user->staff?->id ?? DB::table('staffs')->where('user_id', $user->id)->value('id');
        if (!$staffId) return false;

        // El creador siempre puede editar su propia orden
        if (!is_null($order->operator_id) && (int) $order->operator_id === (int) $staffId) return true;

        // Odontólogo: solo puede editar órdenes que él mismo creó (ya cubierto arriba)
        if ($user->hasRole('odontologo') || (int) ($user->type_id ?? 0) === 6) return false;

        // Técnico: puede editar borradores de cualquier clínica asociada
        $clinicIds = $this->clinicIdsForStaff((int) $staffId);
        return $clinicIds->contains($order->clinic_id);
    }

    public function edit(Order $order): Response|RedirectResponse
    {
        if ((int) $order->estadoradiologo === 1) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No se puede editar una orden ya respondida.');
        }

        $user = Auth::user();

        // Integración DentalSoft:
        // permitir entrar a edición mientras la orden no esté respondida.
        // La validación de respondida ya está arriba.
        /*
        if (!$this->operadorPuedeEditar($order, $user)) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No puedes editar una orden ya enviada.');
        }
        */

        $examTypes = $this->buildExamTabs();

        $clinics = $this->clinicsForUser($user)->map(fn($c) => [
            'id'   => $c->id,
            'name' => $c->user->name ?? "Clínica #{$c->id}",
        ])->values();

        // Examenes existentes con archivos
        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as id', 'kinds.id as kind_id', 'kinds.descipcion as descripcion', 'kinds.group as grupo', 'examinations.piezas', 'examinations.url_texto'])
            ->get()
            ->map(function ($e) {
                $archivos = DB::table('files')
                    ->where('examination_id', $e->id)
                    ->where('desde_informar', '!=', 1)
                    ->get(['id', 'name', 'extension', 'ruta', 'ruta_dcm', 'nombre_dcm'])
                    ->map(fn($f) => [
                        'id'        => $f->id,
                        'name'      => $f->name,
                        'extension' => $f->extension,
                        'ruta_dcm'  => $f->ruta_dcm,
                        'nombre_dcm'=> $f->nombre_dcm,
                        'url'       => $this->signedUrl($f->ruta),
                    ]);
                return ['id' => $e->id, 'kind_id' => $e->kind_id, 'descripcion' => $e->descripcion, 'grupo' => (int) $e->grupo, 'archivos' => $archivos, 'piezas' => $e->piezas, 'url_texto' => $e->url_texto];
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
            'examenes'           => $examenes,
            'examTypes'          => $examTypes,
            'clinics'            => $clinics,
            'canSelectRadiologo' => $this->canSelectRadiologo($user),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        if ((int) $order->estadoradiologo === 1) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No se puede editar una orden ya respondida.');
        }

        $user = Auth::user();

        // Integración DentalSoft:
        // permitir guardar edición mientras la orden no esté respondida.
        // La validación de respondida ya está arriba.
        /*
        if (!$this->operadorPuedeEditar($order, $user)) {
            return redirect()->route('ordenes.show', $order)
                ->with('error', 'No puedes editar una orden ya enviada.');
        }
        */

        $request->validate([
            'prioridad' => ['required', 'in:1 día,2 días,3 días,Normal,Urgente'],
            'action'    => ['required', 'in:guardar,enviar'],
        ]);

        $enviar            = $request->input('action') === 'enviar';
        $yaEstabaEnviada   = ! is_null($order->enviada); // capturar ANTES del update
        $estabaEnCorreccion = (int) $order->estadoradiologo === 2;

        // Determinar asignaciones de radiólogo al enviar desde update()
        $radiologoIdUpdate   = null; // legacy single-rad fallback
        $updateAssignments   = [];
        if ($enviar) {
            $existingStaffId = DB::table('order_staff_exam')->where('order_id', $order->id)->value('staff_id');
            if (! $existingStaffId) {
                $kindIds = DB::table('examinations')
                    ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
                    ->where('examination_order.order_id', $order->id)
                    ->pluck('examinations.kind_id')
                    ->map('intval')
                    ->toArray();

                $rawAss = $request->input('radiologo_assignments', []);
                if (!empty($rawAss) && is_array($rawAss) && $this->canSelectRadiologo($user)) {
                    foreach ($rawAss as $a) {
                        if (empty($a['radiologo_id'])) continue;
                        $updateAssignments[] = [
                            'radiologo_id' => (int) $a['radiologo_id'],
                            'kind_ids'     => !empty($a['kind_ids']) ? array_map('intval', (array) $a['kind_ids']) : null,
                        ];
                    }
                } else {
                    // Auto-asignar primero: respeta especialistas en kind_staff.
                    // radiologo_id manual se ignora al enviar para no bypassear al especialista.
                    $updateAssignments = $this->autoAsignarRadiologoPorExamen((int) $order->clinic_id, $kindIds);
                }
                $radiologoIdUpdate = !empty($updateAssignments) ? $updateAssignments[0]['radiologo_id'] : null;
            }
        }

        $updateCbctJobs = [];
        DB::transaction(function () use ($request, $order, $enviar, $yaEstabaEnviada, $estabaEnCorreccion, $radiologoIdUpdate, $updateAssignments, $user, &$updateCbctJobs): void {
            $orderUpdateData = [
                'diagnostico'      => $request->boolean('sin_diagnostico') ? 'Sin diagnóstico' : ($request->input('diagnostico') ?? $order->diagnostico),
                'observaciones'    => $request->input('observaciones') ?? '',
                'prioridad'        => $request->input('prioridad'),
                'sin_diagnostico'  => $request->boolean('sin_diagnostico') ? 1 : 0,
                'estadoradiologo'  => $enviar ? 0 : ($yaEstabaEnviada ? $order->estadoradiologo : 4),
                'estadoodontologo' => $enviar ? 0 : ($yaEstabaEnviada ? $order->estadoodontologo : 1),
                'enviada'          => $enviar && (!$order->enviada || $estabaEnCorreccion) ? now() : $order->enviada,
            ];
            if ($radiologoIdUpdate) {
                $orderUpdateData['radiologo_id'] = $radiologoIdUpdate;
            }
            $order->update($orderUpdateData);

            // Al re-enviar desde corrección, resetear asignaciones del radiólogo a pendiente
            if ($enviar && $estabaEnCorreccion) {
                DB::table('order_staff_exam')->where('order_id', $order->id)->update(['respondida' => 0]);
            }

            // Actualizar url_texto de exámenes existentes (ej: análisis cefalométrico)
            foreach ((array) $request->input('url_texto_existente', []) as $examinationId => $urlTexto) {
                DB::table('examinations')->where('id', (int) $examinationId)->update([
                    'url_texto'  => $urlTexto ?: null,
                    'updated_at' => now(),
                ]);
            }

            // Subir nuevos archivos a exámenes existentes
            $existingIds = DB::table('examination_order')
                ->where('order_id', $order->id)
                ->pluck('examination_id');

            foreach ($existingIds as $examinationId) {
                $fileKey = "archivos_{$examinationId}";
                if (!$request->hasFile($fileKey)) continue;
                $kindGroup = $this->kindGroupForExam((int) $examinationId);
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $stored = $this->storeUploadedFile($file, $order->id, $kindGroup);
                    $isCbct = ($stored['ruta_dcm'] ?? null) === 'processing';
                    $fid = DB::table('files')->insertGetId([
                        'ruta' => $stored['ruta'], 'examination_id' => $examinationId,
                        'name' => $stored['name'], 'type_id' => 0,
                        'extension' => $stored['extension'],
                        'ruta_dcm' => $stored['ruta_dcm'],
                        'nombre_dcm' => $isCbct ? $stored['ruta'] : null,
                        'file_size' => $stored['file_size'], 'file_size_procesed' => 1,
                        'file_size_error' => null, 'desde_informar' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($isCbct) {
                        $updateCbctJobs[] = [$fid, $stored['ruta']];
                    }
                }
            }

            // Actualizar piezas de exámenes Unitaria existentes
            foreach ($existingIds as $examinationId) {
                if ($request->input("piezas_existente_{$examinationId}_update") !== '1') continue;
                $piezasRaw = (array) $request->input("piezas_existente_{$examinationId}", []);
                $piezasStr = !empty($piezasRaw) ? implode(',', array_map('intval', $piezasRaw)) : null;
                DB::table('examinations')->where('id', $examinationId)->update(['piezas' => $piezasStr, 'updated_at' => now()]);
            }

            // Agregar nuevos exámenes
            foreach ((array) $request->input('nuevos_examenes', []) as $kindId) {
                if (!$kindId) continue;
                $piezasNuevoRaw = (array) $request->input("piezas_nuevo_{$kindId}", []);
                $piezasNuevoStr = !empty($piezasNuevoRaw) ? implode(',', array_map('intval', $piezasNuevoRaw)) : null;
                $urlNuevo       = $request->input("url_nuevo_{$kindId}");
                $examinationId = DB::table('examinations')->insertGetId([
                    'kind_id'    => (int) $kindId,
                    'piezas'     => $piezasNuevoStr,
                    'url_texto'  => $urlNuevo ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('examination_order')->insert(['order_id' => $order->id, 'examination_id' => $examinationId]);
                $fileKey = "archivos_nuevo_{$kindId}";
                if (!$request->hasFile($fileKey)) continue;
                $kindGroup = $this->kindGroupFor((int) $kindId);
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $stored = $this->storeUploadedFile($file, $order->id, $kindGroup);
                    $isCbct = ($stored['ruta_dcm'] ?? null) === 'processing';
                    $newFid = DB::table('files')->insertGetId([
                        'ruta' => $stored['ruta'], 'examination_id' => $examinationId,
                        'name' => $stored['name'], 'type_id' => 0,
                        'extension' => $stored['extension'],
                        'ruta_dcm' => $stored['ruta_dcm'],
                        'nombre_dcm' => $isCbct ? $stored['ruta'] : null,
                        'file_size' => $stored['file_size'], 'file_size_procesed' => 1,
                        'file_size_error' => null, 'desde_informar' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if ($isCbct) {
                        $updateCbctJobs[] = [$newFid, $stored['ruta']];
                    }
                }
            }

            // Manejar asignaciones de radiólogo
            $rawAssignments = $request->input('radiologo_assignments', []);
            if (!empty($rawAssignments) && is_array($rawAssignments) && $this->canSelectRadiologo($user)) {
                // Per-exam assignments: clear pending (respondida=0) and re-insert
                DB::table('order_staff_exam')
                    ->where('order_id', $order->id)
                    ->where('respondida', 0)
                    ->delete();
                $newAssignments = [];
                foreach ($rawAssignments as $a) {
                    if (empty($a['radiologo_id'])) continue;
                    $newAssignments[] = [
                        'radiologo_id' => (int) $a['radiologo_id'],
                        'kind_ids'     => !empty($a['kind_ids']) ? array_map('intval', (array) $a['kind_ids']) : null,
                    ];
                }
                $this->insertRadiologoAssignments($order->id, $newAssignments);
                foreach (array_unique(array_column($newAssignments, 'radiologo_id')) as $sid) {
                    DB::table('order_staff')->insertOrIgnore(['order_id' => $order->id, 'staff_id' => $sid]);
                }
            } elseif (!empty($updateAssignments)) {
                $this->insertRadiologoAssignments($order->id, $updateAssignments);
                foreach (array_unique(array_column($updateAssignments, 'radiologo_id')) as $sid) {
                    DB::table('order_staff')->insertOrIgnore(['order_id' => $order->id, 'staff_id' => $sid]);
                }
            } elseif ($request->filled('radiologo_id') && ($this->canSelectRadiologo($user) || Auth::user()->type_id === 1)) {
                $rid = (int) $request->input('radiologo_id');
                $existing = DB::table('order_staff_exam')->where('order_id', $order->id)->exists();
                if ($existing) {
                    DB::table('order_staff_exam')->where('order_id', $order->id)->where('respondida', 0)->update(['staff_id' => $rid]);
                } else {
                    DB::table('order_staff_exam')->insert(['order_id' => $order->id, 'staff_id' => $rid, 'group_exam' => 1, 'kind_id' => null, 'respondida' => 0]);
                }
            }
        });

        // Procesar ZIPs CBCT en segundo plano (1+ GB no puede bloquear el request)
        foreach ($updateCbctJobs as [$fid, $zipPath]) {
            ProcessCbctZip::dispatch($fid, $this->extractOrderIdFromPath($zipPath), $zipPath)
                ->onConnection('database')->onQueue('default');
        }

        if ($enviar && ! $yaEstabaEnviada) {
            $staffIds = DB::table('order_staff_exam')
                ->where('order_id', $order->id)
                ->pluck('staff_id')
                ->unique();
            foreach ($staffIds as $sid) {
                $this->notificarRadiologo((int) $sid);
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
        $user    = Auth::user();
        $isAdmin = (int) ($user->type_id ?? 0) === 1;

        if (!$isAdmin && !$this->operadorPuedeEditar($order, $user)) {
            abort(403, 'Sin permiso para eliminar exámenes de esta orden.');
        }

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
            ->select('f.id', 'f.name', 'f.ruta', 'f.extension', 'f.nombre_dcm', 'k.descipcion as examen')
            ->get();

        if ($files->isEmpty()) {
            return back()->with('error', 'Esta orden no tiene archivos adjuntos.');
        }

        set_time_limit(600);

        $zipName  = "orden-{$order->id}.zip";
        $tmpPath  = sys_get_temp_dir() . '/' . $zipName;
        $tmpFiles = [];

        try {
            $zip = new \ZipArchive();
            $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            // Incluir el PDF de la orden (igual que el servidor antiguo)
            try {
                $user = Auth::user();
                $canSeeInforme = (int) $order->estadoradiologo === 1
                    || ($user && (
                        $user->hasAnyRole(['admin', 'secretaria', 'radiologo'])
                        || (int) ($user->type_id ?? 0) === 1
                    ));
                $zip->addFromString("orden-{$order->id}.pdf", $this->buildOrderPdf($order, $canSeeInforme)->output());
            } catch (\Throwable) {}

            $usedNames = [];
            foreach ($files as $f) {
                // Para CBCT procesados: usar el ZIP original (nombre_dcm) en vez del
                // primer slice DCM que queda en ruta. Así se incluye el CBCT completo.
                $isCbct  = ($f->extension === 'dcm' && !empty($f->nombre_dcm));
                $rutaS3  = $isCbct ? $f->nombre_dcm : $f->ruta;

                try {
                    $stream = \Illuminate\Support\Facades\Storage::disk('s3')->readStream($rutaS3);
                    if (!is_resource($stream)) continue;

                    $entryTmp = tempnam(sys_get_temp_dir(), 'zip_e_');
                    $dst = fopen($entryTmp, 'wb');
                    stream_copy_to_stream($stream, $dst);
                    fclose($dst);
                    if (is_resource($stream)) fclose($stream);
                } catch (\Throwable) {
                    continue;
                }

                $ext      = $isCbct ? 'zip' : ($f->extension ?: pathinfo($f->ruta, PATHINFO_EXTENSION));
                $baseName = $f->name ?: ('archivo_' . $f->id . ($ext ? ".{$ext}" : ''));
                // Asegurar extensión .zip para CBCT
                if ($isCbct && !preg_match('/\.zip$/i', $baseName)) {
                    $baseName = pathinfo($baseName, PATHINFO_FILENAME) . '.zip';
                }
                $folder   = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $f->examen);

                $zipEntry = "{$folder}/{$baseName}";
                // Avoid duplicates
                if (isset($usedNames[$zipEntry])) {
                    $usedNames[$zipEntry]++;
                    $zipEntry = "{$folder}/{$usedNames[$zipEntry]}_{$baseName}";
                } else {
                    $usedNames[$zipEntry] = 0;
                }

                $zip->addFile($entryTmp, $zipEntry);
                $tmpFiles[] = $entryTmp;
            }
            $zip->close();
        } finally {
            foreach ($tmpFiles as $t) { @unlink($t); }
        }

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
            \Illuminate\Support\Facades\Log::info("Email enviado al radiólogo {$radiologo->name} ({$radiologo->mail}) para orden #{$orden->id}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error enviando email al radiólogo #{$staffId}: " . $e->getMessage());
        }
    }

    /** Generate a 60-minute pre-signed S3 URL for a given path (cached 55 min). */
    private function signedUrl(?string $ruta): ?string
    {
        if (!$ruta || $ruta === '0') {
            return null;
        }
        $key = 's3_url_' . md5($ruta);
        try {
            $url = \Illuminate\Support\Facades\Cache::remember($key, 3300, function () use ($ruta) {
                return \Illuminate\Support\Facades\Storage::disk('s3')
                    ->temporaryUrl($ruta, now()->addMinutes(60));
            });
            \Log::info('SIGNED_URL_OK', ['ruta' => $ruta, 'url_prefix' => substr($url ?? '', 0, 80)]);
            return $url;
        } catch (\Throwable $e) {
            \Log::warning('SIGNED_URL_FAIL', ['ruta' => $ruta, 'error' => $e->getMessage()]);
            try {
                return \Illuminate\Support\Facades\Storage::disk('s3')
                    ->temporaryUrl($ruta, now()->addMinutes(60));
            } catch (\Throwable $e2) {
                \Log::error('SIGNED_URL_FAIL2', ['ruta' => $ruta, 'error' => $e2->getMessage()]);
                return null;
            }
        }
    }

    /**
     * Store an uploaded file. If it's a ZIP for a CBCT exam (group=4),
     * extract the DCM files and upload them individually to S3,
     * preserving the internal folder structure of the ZIP.
     *
     * @return array{ruta:string,ruta_dcm:string|null,extension:string,name:string,file_size:int}
     */
    private function storeUploadedFile(
        \Illuminate\Http\UploadedFile $file,
        int $orderId,
        int $kindGroup
    ): array {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'zip' && $kindGroup === 4) {
            return $this->extractCbctZip($file, $orderId);
        }

        $path = $file->store("ordenes/{$orderId}", 's3');

        return [
            'ruta'      => $path,
            'ruta_dcm'  => null,
            'extension' => $ext,
            'name'      => $file->getClientOriginalName(),
            'file_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Upload a CBCT ZIP directly to S3 and dispatch a background job
     * to extract the DCM slices. Returns immediately so the HTTP request
     * doesn't time out on large series.
     *
     * The file row is created with ruta_dcm='processing'; the job updates
     * it to the real series prefix once extraction completes.
     *
     * @return array{ruta:string,ruta_dcm:string,extension:string,name:string,file_size:int}
     */
    private function extractCbctZip(
        \Illuminate\Http\UploadedFile $zipFile,
        int $orderId
    ): array {
        $zipPath = $zipFile->store("ordenes/{$orderId}", 's3');

        return [
            'ruta'      => $zipPath,
            'ruta_dcm'  => 'processing', // job will update this
            'extension' => 'zip',
            'name'      => $zipFile->getClientOriginalName(),
            'file_size' => (int) $zipFile->getSize(),
        ];
    }

    /**
     * Dispatch the CBCT extraction job once the file row has been inserted.
     * Called by store() and update() after DB::table('files')->insert().
     */
    public function dispatchCbctJobIfNeeded(array $fileRow, int $fileId): void
    {
        if (($fileRow['ruta_dcm'] ?? null) === 'processing') {
            ProcessCbctZip::dispatch($fileId, $this->extractOrderIdFromPath($fileRow['ruta']), $fileRow['ruta'])
                ->onConnection('database')
                ->onQueue('default');
        }
    }

    private function extractOrderIdFromPath(string $ruta): int
    {
        // path format: ordenes/{orderId}/filename.zip
        $parts = explode('/', $ruta);
        return (int) ($parts[1] ?? 0);
    }

    /**
     * Auto-asigna radiólogos por examen usando kind_staff.
     * Para cada kindId elige el especialista/generalista menos ocupado.
     * Devuelve un array de assignments [{radiologo_id, kind_ids}].
     */
    private function autoAsignarRadiologoPorExamen(int $clinicId, array $kindIds): array
    {
        $radiologos = DB::table('staffs')
            ->join('clinic_staff', 'clinic_staff.staff_id', '=', 'staffs.id')
            ->join('users', 'users.id', '=', 'staffs.user_id')
            ->where('clinic_staff.clinic_id', $clinicId)
            ->where(function ($q) { $q->where('staffs.type_staff', 3)->orWhere('users.type_id', 5); })
            ->where('staffs.activo', 1)
            ->pluck('staffs.id')->unique()->toArray();

        if (empty($radiologos)) return [];

        // Mapa staff_id => [kind_ids que maneja]
        $kindStaffMap = DB::table('kind_staff')
            ->whereIn('staff_id', $radiologos)
            ->get(['staff_id', 'kind_id'])
            ->groupBy('staff_id')
            ->map(fn($rows) => $rows->pluck('kind_id')->toArray());

        $generalists = array_values(array_filter($radiologos, fn($id) => !isset($kindStaffMap[$id])));

        // Carga de trabajo: órdenes pendientes por radiólogo
        $workload = DB::table('order_staff_exam as ose')
            ->join('orders as o', 'o.id', '=', 'ose.order_id')
            ->whereIn('ose.staff_id', $radiologos)
            ->whereIn('o.estadoradiologo', [0, 2])
            ->groupBy('ose.staff_id')
            ->select('ose.staff_id', DB::raw('COUNT(DISTINCT ose.order_id) as cnt'))
            ->pluck('cnt', 'staff_id');

        $leastBusy = function (array $ids) use ($workload): ?int {
            return collect($ids)->sortBy(fn($id) => (int) ($workload[$id] ?? 0))->first();
        };

        // Asignar el mejor radiólogo para cada kindId
        $kindToRad = [];
        foreach ($kindIds as $kindId) {
            $specialists = array_values(array_filter(
                $radiologos,
                fn($id) => isset($kindStaffMap[$id]) && in_array($kindId, $kindStaffMap[$id])
            ));
            if (!empty($specialists)) {
                $kindToRad[$kindId] = $leastBusy($specialists);
            } elseif (!empty($generalists)) {
                $kindToRad[$kindId] = $leastBusy($generalists);
            } else {
                $kindToRad[$kindId] = $leastBusy($radiologos);
            }
        }

        // Agrupar kind_ids por radiólogo asignado
        $grouped = [];
        foreach ($kindToRad as $kindId => $radId) {
            if ($radId) $grouped[$radId][] = $kindId;
        }

        return array_values(array_map(
            fn($radId, $kIds) => ['radiologo_id' => (int) $radId, 'kind_ids' => $kIds],
            array_keys($grouped), array_values($grouped)
        ));
    }

    /**
     * Elige aleatoriamente un radiólogo de la clínica que pueda informar los exámenes.
     * Mantenido para compatibilidad — usar autoAsignarRadiologoPorExamen cuando sea posible.
     */
    private function autoAsignarRadiologo(int $clinicId, array $kindIds): ?int
    {
        $radiologos = DB::table('staffs')
            ->join('clinic_staff', 'clinic_staff.staff_id', '=', 'staffs.id')
            ->join('users', 'users.id', '=', 'staffs.user_id')
            ->where('clinic_staff.clinic_id', $clinicId)
            ->where(function ($q) {
                $q->where('staffs.type_staff', 3)->orWhere('users.type_id', 5);
            })
            ->where('staffs.activo', 1)
            ->pluck('staffs.id')
            ->unique()
            ->toArray();

        if (empty($radiologos)) {
            return null;
        }

        $candidates = [];
        foreach ($radiologos as $staffId) {
            $assignedKinds = DB::table('kind_staff')
                ->where('staff_id', $staffId)
                ->pluck('kind_id')
                ->toArray();

            if (empty($assignedKinds)) {
                // Generalista: acepta cualquier orden
                $candidates[] = $staffId;
            } elseif (!empty(array_intersect($kindIds, $assignedKinds))) {
                // Especialista con al menos un tipo coincidente
                $candidates[] = $staffId;
            }
        }

        if (empty($candidates)) {
            // Fallback: cualquier radiólogo activo de la clínica
            $candidates = $radiologos;
        }

        return $candidates[array_rand($candidates)];
    }

    /** Resolve the kind.group for a given kind_id (cached per request). */
    private array $kindGroupCache = [];

    private function kindGroupFor(int $kindId): int
    {
        if (!isset($this->kindGroupCache[$kindId])) {
            $this->kindGroupCache[$kindId] = (int) DB::table('kinds')
                ->where('id', $kindId)
                ->value('group');
        }
        return $this->kindGroupCache[$kindId];
    }

    private function kindGroupForExam(int $examinationId): int
    {
        return (int) DB::table('examinations')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examinations.id', $examinationId)
            ->value('kinds.group');
    }

    /**
     * Inserts order_staff_exam rows for per-exam radiologist assignments.
     * Each assignment: ['radiologo_id' => int, 'kind_ids' => int[]|null]
     * kind_ids=null means the radiologist handles all exams (backward compat).
     */
    private function insertRadiologoAssignments(int $orderId, array $assignments): void
    {
        foreach ($assignments as $a) {
            $staffId = (int) $a['radiologo_id'];
            $kindIds = $a['kind_ids'] ?? null;
            if (empty($kindIds)) {
                DB::table('order_staff_exam')->insertOrIgnore([
                    'order_id'   => $orderId,
                    'staff_id'   => $staffId,
                    'group_exam' => 1,
                    'kind_id'    => null,
                    'respondida' => 0,
                ]);
            } else {
                foreach ((array) $kindIds as $kindId) {
                    DB::table('order_staff_exam')->insertOrIgnore([
                        'order_id'   => $orderId,
                        'staff_id'   => $staffId,
                        'group_exam' => 1,
                        'kind_id'    => (int) $kindId,
                        'respondida' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * Builds radiologist assignments from the request.
     * Supports both old single radiologo_id and new per-exam radiologo_assignments.
     */
    private function buildAssignments(
        \Illuminate\Http\Request $request,
        array $kindIds,
        int $clinicId,
        bool $enviar,
        $user
    ): array {
        // New per-exam format
        $raw = $request->input('radiologo_assignments', []);
        if (!empty($raw) && is_array($raw)) {
            $result = [];
            foreach ($raw as $a) {
                if (empty($a['radiologo_id'])) continue;
                $result[] = [
                    'radiologo_id' => (int) $a['radiologo_id'],
                    'kind_ids'     => !empty($a['kind_ids'])
                        ? array_map('intval', (array) $a['kind_ids'])
                        : null,
                ];
            }
            if (!empty($result)) return $result;
        }

        // Auto-asignar siempre por kind_staff, tanto al guardar como al enviar,
        // para que el radiólogo quede asignado desde la creación de la orden.
        $autoAssignments = $this->autoAsignarRadiologoPorExamen($clinicId, $kindIds);
        if (!empty($autoAssignments)) {
            return $autoAssignments;
        }

        // Fallback: radiologo_id manual (solo para borradores si no hay auto-asignación)
        if ($request->filled('radiologo_id') && $this->canSelectRadiologo($user)) {
            return [['radiologo_id' => (int) $request->radiologo_id, 'kind_ids' => null]];
        }

        return [];
    }
}