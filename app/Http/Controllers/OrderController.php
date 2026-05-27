<?php

namespace App\Http\Controllers;

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

            if ($file->ruta_dcm) {
                // CBCT series: pre-generate signed S3 URLs for every slice so
                // the JS XHR interceptor can bypass the PHP proxy entirely.
                $baseProxy = "{$base}/archivos/{$id}/dcm";
                $fileUrl   = $baseProxy . '/' . rawurlencode(basename($file->ruta));

                $paths = Cache::remember("serie_paths_{$id}", 3600, function () use ($file) {
                    $all = Storage::disk('s3')->allFiles($file->ruta_dcm);
                    $dcm = array_filter($all, fn($p) => in_array(
                        strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['dcm', 'dicom'], true
                    ));
                    sort($dcm);
                    return array_values($dcm);
                });

                foreach ($paths as $path) {
                    $proxyUrl = $baseProxy . '/' . rawurlencode(basename($path));
                    try {
                        $urlMap[$proxyUrl] = Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
                    } catch (\Throwable) {}
                }
            } else {
                $fileUrl = "{$base}/archivos/{$id}/" . rawurlencode(basename($file->ruta));
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

    public function create(): Response
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

        return Inertia::render('Orders/Create', [
            'examTypes'          => $this->buildExamTabs(),
            'clinics'            => $clinics,
            'canSelectRadiologo' => $this->canSelectRadiologo($user),
        ]);
    }

    private function canSelectRadiologo($user): bool
    {
        if ($user->hasAnyRole(['admin', 'secretaria', 'holding', 'clinica', 'radiologo', 'contralor'])) {
            return true;
        }
        if ($user->hasAnyRole(['odontologo', 'tecnico'])) {
            $staff = DB::table('staffs')->where('user_id', $user->id)->first(['puede_seleccionar_radiologo']);
            return (bool) ($staff->puede_seleccionar_radiologo ?? false);
        }
        return false;
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
        $this->guardRadiologoCrear();
        $request->validate([
            'patient_id'   => ['required', 'exists:patients,id'],
            'clinic_id'    => ['required', 'exists:clinics,id'],
            'odontologo_id'=> ['nullable', 'exists:staffs,id'],
            'radiologo_id' => ['nullable', 'exists:staffs,id'],
            'diagnostico'  => ['required', 'min:3'],
            'prioridad'    => ['required', 'in:1 día,2 días,3 días,Normal,Urgente'],
            'examenes'     => ['required', 'array', 'min:1'],
            'action'       => ['required', 'in:guardar,enviar'],
        ]);

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

        DB::transaction(function () use ($request, $enviar, $odontologoId, $examenes): void {
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

            if (!empty($fileRows)) {
                DB::table('files')->insert($fileRows);
            }

            if ($enviar && $request->filled('radiologo_id')) {
                DB::table('order_staff_exam')->insert([
                    'order_id' => $order->id,
                    'staff_id' => $request->radiologo_id,
                    'group_exam' => 1,
                    'respondida' => 0,
                ]);
            }

            $staffIds = array_values(array_filter([
                $odontologoId,
                $request->radiologo_id,
            ]));
            if (!empty($staffIds)) {
                $orderStaffRows = array_map(fn ($sid) => [
                    'order_id' => $order->id,
                    'staff_id' => (int) $sid,
                ], $staffIds);
                DB::table('order_staff')->insertOrIgnore($orderStaffRows);
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
                'examinations.piezas',
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
                    'grupo'       => (int) $e->grupo,
                    'url_texto'   => $e->url_texto,
                    'piezas'      => $e->piezas,
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

        $puedeResponder = $user->hasAnyRole(['radiologo', 'admin', 'secretaria'])
            && (int) $order->estadoradiologo === 0
            && ($user->hasAnyRole(['admin', 'secretaria']) || $radiologos->contains('id', $user->staff?->id));

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

        if (!$user->hasAnyRole(['radiologo', 'admin', 'secretaria'])) {
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
                $ans = DB::table('answers')
                    ->where('examination_id', $e->examination_id)
                    ->first();

                $archivos = DB::table('files')
                    ->where('examination_id', $e->examination_id)
                    ->get(['id', 'name', 'ruta', 'extension', 'file_size'])
                    ->map(function ($f) {
                        return array_merge((array) $f, ['url' => $this->signedUrl($f->ruta)]);
                    });

                return [
                    'id'          => $e->examination_id,
                    'kind_id'     => $e->kind_id,
                    'descripcion' => $e->descripcion,
                    'grupo'       => $e->grupo,
                    'archivos'    => $archivos,
                    'respuesta'   => $ans ? (array) $ans : null,
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

    private const PANORAMICA_KIND_ID = 44;

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
                $kindId = DB::table('examinations')->where('id', $examinationId)->value('kind_id');
                $isPanoramica = ($kindId == self::PANORAMICA_KIND_ID);

                if ($isPanoramica) {
                    $answerData = ['solo_adjunto' => $soloAdjunto];
                    for ($i = 1; $i <= 8; $i++) {
                        $answerData["campo_{$i}"] = $r["campo_{$i}"] ?? null;
                    }
                    foreach (self::PANORAMICA_DIENTES as $d) {
                        $answerData["diente_{$d}"] = $r["diente_{$d}"] ?? null;
                    }
                } else {
                    $answerData = [
                        'campo_1'      => $r['texto'] ?? '',
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
                $order->update(['estadoradiologo' => 4]);
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
                $order->update([
                    'estadoradiologo'  => 1,
                    'estadoodontologo' => 1,
                    'respondida'       => now(),
                    'vista'            => 0,
                ]);
                if ($user->staff) {
                    DB::table('order_staff_exam')
                        ->where('order_id', $order->id)
                        ->where('staff_id', $user->staff->id)
                        ->update(['respondida' => 1]);
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
        $examenes = DB::table('examinations')
            ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
            ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
            ->where('examination_order.order_id', $order->id)
            ->select(['examinations.id as examination_id', 'kinds.descipcion as descripcion'])
            ->get()
            ->map(function ($e) {
                $respuesta = DB::table('answers')->where('examination_id', $e->examination_id)->first();
                return ['descripcion' => $e->descripcion, 'respuesta' => $respuesta?->campo_1 ?? ''];
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
            ->select(['examinations.id as id', 'kinds.id as kind_id', 'kinds.descipcion as descripcion', 'kinds.group as grupo'])
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
                return ['id' => $e->id, 'kind_id' => $e->kind_id, 'descripcion' => $e->descripcion, 'grupo' => (int) $e->grupo, 'archivos' => $archivos];
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
                $kindGroup = $this->kindGroupForExam((int) $examinationId);
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $stored = $this->storeUploadedFile($file, $order->id, $kindGroup);
                    DB::table('files')->insert([
                        'ruta' => $stored['ruta'], 'examination_id' => $examinationId,
                        'name' => $stored['name'], 'type_id' => 0,
                        'extension' => $stored['extension'],
                        'ruta_dcm' => $stored['ruta_dcm'], 'nombre_dcm' => null,
                        'file_size' => $stored['file_size'], 'file_size_procesed' => 1,
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
                $kindGroup = $this->kindGroupFor((int) $kindId);
                foreach ((array) $request->file($fileKey) as $file) {
                    if (!$file) continue;
                    $stored = $this->storeUploadedFile($file, $order->id, $kindGroup);
                    DB::table('files')->insert([
                        'ruta' => $stored['ruta'], 'examination_id' => $examination->id,
                        'name' => $stored['name'], 'type_id' => 0,
                        'extension' => $stored['extension'],
                        'ruta_dcm' => $stored['ruta_dcm'], 'nombre_dcm' => null,
                        'file_size' => $stored['file_size'], 'file_size_procesed' => 1,
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
        if ((int) (Auth::user()->type_id ?? 0) !== 1) {
            abort(403);
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
        if (!$ruta || $ruta === '0') {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Storage::disk('s3')
                ->temporaryUrl($ruta, now()->addMinutes(60));
        } catch (\Throwable) {
            return null;
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
     * Extract DICOM files from a ZIP and upload each to S3 maintaining
     * the ZIP's internal folder structure under ordenes/{orderId}/.
     *
     * @return array{ruta:string|null,ruta_dcm:string|null,extension:string,name:string,file_size:int}
     */
    private function extractCbctZip(
        \Illuminate\Http\UploadedFile $zipFile,
        int $orderId
    ): array {
        $fallback = function () use ($zipFile, $orderId) {
            $path = $zipFile->store("ordenes/{$orderId}", 's3');
            return [
                'ruta'      => $path,
                'ruta_dcm'  => null,
                'extension' => 'zip',
                'name'      => $zipFile->getClientOriginalName(),
                'file_size' => (int) $zipFile->getSize(),
            ];
        };

        $za = new \ZipArchive();
        if ($za->open($zipFile->getRealPath()) !== true) {
            return $fallback();
        }

        $firstDcmS3  = null;
        $seriePrefix = null;

        for ($i = 0; $i < $za->numFiles; $i++) {
            $entry = $za->getNameIndex($i);
            if (!$entry || str_ends_with($entry, '/')) {
                continue;
            }

            $entryExt = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($entryExt, ['dcm', 'dicom'], true)) {
                continue;
            }

            $content = $za->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            $s3Path = "ordenes/{$orderId}/{$entry}";
            Storage::disk('s3')->put($s3Path, $content);

            if ($firstDcmS3 === null) {
                $firstDcmS3  = $s3Path;
                $dir         = dirname($entry);
                $seriePrefix = "ordenes/{$orderId}/" . ($dir === '.' ? '' : rtrim($dir, '/') . '/');
            }
        }

        $za->close();

        if ($firstDcmS3 === null) {
            return $fallback();
        }

        return [
            'ruta'      => $firstDcmS3,
            'ruta_dcm'  => $seriePrefix,
            'extension' => 'dcm',
            'name'      => $zipFile->getClientOriginalName(),
            'file_size' => (int) $zipFile->getSize(),
        ];
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
}