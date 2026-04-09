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
                    ->select(['examinations.id as examination_id', 'kinds.descipcion as descripcion'])
                    ->get()
                    ->map(function ($e) {
                        $archivos = DB::table('files')
                            ->where('examination_id', $e->examination_id)
                            ->get(['id', 'name', 'extension'])
                            ->toArray();

                        return [
                            'id'          => $e->examination_id,
                            'descripcion' => $e->descripcion,
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
