<?php

namespace App\Http\Controllers;

use App\Models\Correction;
use App\Models\Feriados;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdministracionController extends Controller
{
    // ── CORREGIR ─────────────────────────────────────────────────────────

    public function corregir(Request $request)
    {
        $orden = null;

        if ($request->filled('orden_id')) {
            $o = Order::find($request->orden_id);

            if ($o) {
                $paciente  = DB::table('patients')->where('id', $o->patient_id)->first(['name', 'rut']);
                $clinica   = DB::table('clinics')->join('users', 'users.id', '=', 'clinics.user_id')
                    ->where('clinics.id', $o->clinic_id)->value('users.name');
                $odonto    = DB::table('staffs')->join('users', 'users.id', '=', 'staffs.user_id')
                    ->where('staffs.id', $o->odontologo_id)->value('users.name');

                $examenes = DB::table('examinations')
                    ->join('examination_order', 'examination_order.examination_id', '=', 'examinations.id')
                    ->join('kinds', 'kinds.id', '=', 'examinations.kind_id')
                    ->where('examination_order.order_id', $o->id)
                    ->select(['examinations.id as examination_id', 'kinds.descipcion as descripcion', 'kinds.group as grupo'])
                    ->get()
                    ->map(function ($e) {
                        $archivos = DB::table('files')
                            ->where('examination_id', $e->examination_id)
                            ->get(['id', 'name', 'extension', 'ruta_dcm', 'nombre_dcm', 'type_id'])
                            ->toArray();

                        return [
                            'id'          => $e->examination_id,
                            'descripcion' => $e->descripcion,
                            'grupo'       => (int) $e->grupo,
                            'archivos'    => $archivos,
                        ];
                    });

                $estados = [0 => 'No Informada', 1 => 'Informada', 2 => 'En Corrección'];

                $orden = [
                    'id'             => $o->id,
                    'paciente'       => $paciente->name ?? '—',
                    'rut'            => $paciente->rut  ?? '—',
                    'clinica'        => $clinica         ?? '—',
                    'odontologo'     => $odonto          ?? '—',
                    'estadoradiologo'=> (int) $o->estadoradiologo,
                    'estado_label'   => $estados[(int) $o->estadoradiologo] ?? 'Desconocido',
                    'enviada'        => $o->enviada ? Carbon::parse($o->enviada)->format('d/m/Y') : null,
                    'examenes'       => $examenes,
                ];
            }
        }

        return Inertia::render('Admin/Administracion/Corregir', compact('orden'));
    }

    public function enviarCorreccion(Request $request)
    {
        $request->validate([
            'orden_id' => ['required', 'exists:orders,id'],
            'mensaje'  => ['required', 'string', 'min:5'],
        ]);

        $order = Order::findOrFail($request->orden_id);

        DB::transaction(function () use ($order, $request) {
            $order->update([
                'estadoradiologo' => 2,
            ]);

            Correction::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'staff_id'    => Auth::id(),
                    'description' => $request->mensaje,
                    'status'      => 1,
                ]
            );
        });

        return redirect()
            ->route('admin.administracion.corregir', ['orden_id' => $order->id])
            ->with('success', 'Orden enviada a corrección.');
    }

    public function uploadArchivo(Request $request)
    {
        $request->validate([
            'examination_id' => ['required', 'integer', 'exists:examinations,id'],
            'file'           => ['required', 'file', 'max:102400'],
        ]);

        $examinationId = (int) $request->examination_id;
        $eo = DB::table('examination_order')->where('examination_id', $examinationId)->first(['order_id']);
        abort_if(!$eo, 404);
        $orderId = (int) $eo->order_id;

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $path = $file->store("ordenes/{$orderId}", 's3');

        DB::table('files')->insert([
            'examination_id' => $examinationId,
            'ruta'           => $path,
            'name'           => $file->getClientOriginalName(),
            'extension'      => $ext,
            'file_size'      => (int) $file->getSize(),
            'type_id'        => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return redirect()
            ->route('admin.administracion.corregir', ['orden_id' => $orderId])
            ->with('success', 'Archivo adjuntado correctamente.');
    }

    public function noInformada(Request $request)
    {
        $request->validate([
            'orden_id' => ['required', 'exists:orders,id'],
        ]);

        Order::where('id', $request->orden_id)->update([
            'estadoradiologo'  => 0,
            'estadoodontologo' => 0,
        ]);

        return redirect()
            ->route('admin.administracion.corregir', ['orden_id' => $request->orden_id])
            ->with('success', 'Orden cambiada a "No Informada".');
    }

    // ── GESTIÓN DE ESTADO ────────────────────────────────────────────────

    public function gestionEstado(Request $request): Response
    {
        $query = DB::table('orders')
            ->join('patients', 'patients.id', '=', 'orders.patient_id')
            ->join('clinics', 'clinics.id', '=', 'orders.clinic_id')
            ->join('users as clinica_user', 'clinica_user.id', '=', 'clinics.user_id')
            ->leftJoin('staffs as rad_staff', 'rad_staff.id', '=', 'orders.radiologo_id')
            ->leftJoin('users as rad_user', 'rad_user.id', '=', 'rad_staff.user_id')
            ->leftJoin('staffs as od_staff', 'od_staff.id', '=', 'orders.odontologo_id')
            ->leftJoin('users as od_user', 'od_user.id', '=', 'od_staff.user_id')
            ->whereNotNull('orders.enviada')
            ->select([
                'orders.id',
                'patients.name as paciente',
                'patients.rut',
                'clinica_user.name as clinica',
                'rad_user.name as radiologo',
                'od_user.name as odontologo',
                'orders.estadoradiologo',
                'orders.prioridad',
                'orders.enviada',
                'orders.respondida',
            ]);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('patients.name', 'like', "%{$q}%")
                    ->orWhere('patients.rut', 'like', "%{$q}%")
                    ->orWhere('clinica_user.name', 'like', "%{$q}%")
                    ->orWhere('rad_user.name', 'like', "%{$q}%")
                    ->orWhere('orders.id', '=', $q);
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('orders.estadoradiologo', (int) $request->estado);
        }

        if ($request->filled('radiologo_id')) {
            $query->where('orders.radiologo_id', (int) $request->radiologo_id);
        }

        $paginated = $query->orderByDesc('orders.updated_at')->paginate(25)->withQueryString();

        $radiologos = DB::table('staffs')
            ->join('users', 'users.id', '=', 'staffs.user_id')
            ->where('staffs.type_staff', 3)
            ->select(['staffs.id', 'users.name'])
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Admin/Administracion/GestionEstado', [
            'orders'      => $paginated->items(),
            'total'       => $paginated->total(),
            'currentPage' => $paginated->currentPage(),
            'perPage'     => $paginated->perPage(),
            'filters'     => $request->only(['q', 'estado', 'radiologo_id']),
            'radiologos'  => $radiologos,
            'estados'     => OrderController::ESTADOS,
        ]);
    }

    public function cambiarEstadoMasivo(Request $request)
    {
        $request->validate([
            'orden_ids'    => ['required', 'array', 'min:1'],
            'orden_ids.*'  => ['integer', 'exists:orders,id'],
            'nuevo_estado' => ['required', 'in:0,2'],
            'mensaje'      => ['required_if:nuevo_estado,2', 'nullable', 'string', 'min:5'],
        ]);

        $ids         = $request->orden_ids;
        $nuevoEstado = (int) $request->nuevo_estado;

        DB::transaction(function () use ($ids, $nuevoEstado, $request) {
            foreach ($ids as $orderId) {
                $data = ['estadoradiologo' => $nuevoEstado, 'updated_at' => now()];

                if ($nuevoEstado === 0) {
                    $data['estadoodontologo'] = 0;
                    $data['respondida']       = null;
                }

                DB::table('orders')->where('id', $orderId)->update($data);

                if ($nuevoEstado === 2 && $request->filled('mensaje')) {
                    Correction::updateOrCreate(
                        ['order_id' => $orderId],
                        [
                            'staff_id'    => Auth::id(),
                            'description' => $request->mensaje,
                            'status'      => 1,
                        ]
                    );
                }
            }
        });

        $count = count($ids);
        $label = $nuevoEstado === 0 ? 'No Informada' : 'Corrección';

        return back()->with('success', "{$count} orden(es) cambiada(s) a \"{$label}\" correctamente.");
    }

    // ── REASIGNACIÓN ─────────────────────────────────────────────────────

    public function reasignacion(Request $request): Response
    {
        $query = DB::table('orders')
            ->join('patients', 'patients.id', '=', 'orders.patient_id')
            ->join('clinics', 'clinics.id', '=', 'orders.clinic_id')
            ->join('users as clinica_user', 'clinica_user.id', '=', 'clinics.user_id')
            ->leftJoin('staffs as rad_staff', 'rad_staff.id', '=', 'orders.radiologo_id')
            ->leftJoin('users as rad_user', 'rad_user.id', '=', 'rad_staff.user_id')
            ->leftJoin('staffs as od_staff', 'od_staff.id', '=', 'orders.odontologo_id')
            ->leftJoin('users as od_user', 'od_user.id', '=', 'od_staff.user_id')
            ->select([
                'orders.id',
                'patients.name as paciente',
                'patients.rut',
                'clinica_user.name as clinica',
                'orders.radiologo_id',
                'rad_user.name as radiologo',
                'orders.odontologo_id',
                'od_user.name as odontologo',
                'orders.estadoradiologo',
                'orders.prioridad',
                'orders.enviada',
                'orders.created_at',
            ]);

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('patients.name', 'like', "%{$q}%")
                    ->orWhere('patients.rut', 'like', "%{$q}%")
                    ->orWhere('clinica_user.name', 'like', "%{$q}%")
                    ->orWhere('rad_user.name', 'like', "%{$q}%")
                    ->orWhere('od_user.name', 'like', "%{$q}%")
                    ->orWhere('orders.id', '=', $q);
            });
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('orders.estadoradiologo', (int) $request->estado);
        }

        if ($request->filled('radiologo_id')) {
            $query->where('orders.radiologo_id', (int) $request->radiologo_id);
        }

        if ($request->filled('odontologo_id')) {
            $query->where('orders.odontologo_id', (int) $request->odontologo_id);
        }

        $paginated = $query->orderByDesc('orders.updated_at')->paginate(25)->withQueryString();

        $radiologos = DB::table('staffs')
            ->join('users', 'users.id', '=', 'staffs.user_id')
            ->where('staffs.type_staff', 3)
            ->where('staffs.activo', 1)
            ->select(['staffs.id', 'users.name'])
            ->orderBy('users.name')
            ->get();

        $odontologos = DB::table('staffs')
            ->join('users', 'users.id', '=', 'staffs.user_id')
            ->where('staffs.type_staff', 6)
            ->where('staffs.activo', 1)
            ->select(['staffs.id', 'users.name'])
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Admin/Administracion/Reasignacion', [
            'orders'      => $paginated->items(),
            'total'       => $paginated->total(),
            'currentPage' => $paginated->currentPage(),
            'perPage'     => $paginated->perPage(),
            'filters'     => $request->only(['q', 'estado', 'radiologo_id', 'odontologo_id']),
            'radiologos'  => $radiologos,
            'odontologos' => $odontologos,
            'estados'     => OrderController::ESTADOS,
        ]);
    }

    public function reasignarMasivo(Request $request)
    {
        $request->validate([
            'orden_ids'    => ['required', 'array', 'min:1'],
            'orden_ids.*'  => ['integer', 'exists:orders,id'],
            'radiologo_id' => ['nullable', 'integer', 'exists:staffs,id'],
            'odontologo_id'=> ['nullable', 'integer', 'exists:staffs,id'],
        ]);

        if (!$request->filled('radiologo_id') && !$request->filled('odontologo_id')) {
            return back()->withErrors(['staff' => 'Debe seleccionar al menos un radiólogo u odontólogo.']);
        }

        $ids          = $request->orden_ids;
        $radiologoId  = $request->filled('radiologo_id')  ? (int) $request->radiologo_id  : null;
        $odontologoId = $request->filled('odontologo_id') ? (int) $request->odontologo_id : null;

        DB::transaction(function () use ($ids, $radiologoId, $odontologoId) {
            foreach ($ids as $orderId) {
                $data = ['updated_at' => now()];
                if ($radiologoId)  $data['radiologo_id']  = $radiologoId;
                if ($odontologoId) $data['odontologo_id'] = $odontologoId;
                DB::table('orders')->where('id', $orderId)->update($data);

                if ($radiologoId) {
                    DB::table('order_staff_exam')
                        ->where('order_id', $orderId)
                        ->update(['staff_id' => $radiologoId]);
                }
            }
        });

        $count  = count($ids);
        $partes = [];
        if ($radiologoId)  $partes[] = 'radiólogo';
        if ($odontologoId) $partes[] = 'odontólogo';

        return back()->with('success', "{$count} orden(es) reasignada(s) (" . implode(' y ', $partes) . ") correctamente.");
    }

    // ── FERIADOS ─────────────────────────────────────────────────────────

    public function feriados(Request $request)
    {
        $query = Feriados::query();

        if ($request->filled('search')) {
            $query->where('descripcion', 'like', '%' . $request->search . '%');
        }

        $feriados = $query->orderBy('fecha', 'desc')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Administracion/Feriados/Index', [
            'feriados' => $feriados->items(),
            'total'    => $feriados->total(),
            'currentPage' => $feriados->currentPage(),
            'perPage'  => $feriados->perPage(),
            'filters'  => $request->only('search'),
        ]);
    }

    public function feriadosCreate()
    {
        return Inertia::render('Admin/Administracion/Feriados/Form', ['feriado' => null]);
    }

    public function feriadosStore(Request $request)
    {
        $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        Feriados::create([
            'fecha'       => $request->fecha,
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('admin.feriados')->with('success', 'Feriado creado correctamente.');
    }

    public function feriadosEdit($id)
    {
        $feriado = Feriados::findOrFail($id);
        return Inertia::render('Admin/Administracion/Feriados/Form', compact('feriado'));
    }

    public function feriadosUpdate(Request $request, $id)
    {
        $request->validate([
            'fecha'       => ['required', 'date'],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);

        Feriados::where('id', $id)->update([
            'fecha'       => $request->fecha,
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('admin.feriados')->with('success', 'Feriado actualizado.');
    }

    public function feriadosDestroy($id)
    {
        Feriados::where('id', $id)->delete();
        return redirect()->route('admin.feriados')->with('success', 'Feriado eliminado.');
    }
}
