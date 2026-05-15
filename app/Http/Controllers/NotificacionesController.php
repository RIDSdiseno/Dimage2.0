<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificacionesController extends Controller
{
    /**
     * Devuelve las órdenes respondidas aún no vistas por el usuario actual.
     * Solo aplica a roles no-radiólogo (clínica, odontólogo, técnico, secretaría, admin).
     */
    public function index(): JsonResponse
    {
        $user  = Auth::user();
        $query = DB::table('orders as o')
            ->join('patients as p', 'p.id', '=', 'o.patient_id')
            ->leftJoin('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->leftJoin('users as uc', 'uc.id', '=', 'c.user_id')
            ->whereNotNull('o.respondida')
            ->where('o.vista', 0)
            ->select(
                'o.id',
                'o.respondida',
                'p.name as paciente',
                'p.rut',
                'uc.name as clinica'
            )
            ->orderByDesc('o.respondida');

        // Filtrar por scope del usuario
        if ($user->hasRole('clinica') && $user->clinic) {
            $query->where('o.clinic_id', $user->clinic->id);
        } elseif ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $clinicIds = DB::table('clinic_staff')
                ->where('staff_id', $user->staff->id)
                ->pluck('clinic_id');
            $query->whereIn('o.clinic_id', $clinicIds);
        } elseif ($user->hasRole('holding') && $user->holding) {
            $clinicIds = DB::table('clinics')
                ->where('holding_id', $user->holding->id)
                ->pluck('id');
            $query->whereIn('o.clinic_id', $clinicIds);
        }
        // admin y secretaria ven todas las no vistas

        $orders = $query->limit(50)->get()->map(fn ($o) => [
            'id'         => $o->id,
            'paciente'   => $o->paciente ?? '-',
            'rut'        => $o->rut ?? '-',
            'clinica'    => $o->clinica ?? '-',
            'respondida' => \Carbon\Carbon::parse($o->respondida)->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'total'   => $orders->count(),
            'ordenes' => $orders,
        ]);
    }

    /**
     * Marca una orden como vista.
     */
    public function marcarVista(int $id): JsonResponse
    {
        DB::table('orders')->where('id', $id)->update(['vista' => 1]);
        return response()->json(['ok' => true]);
    }

    /**
     * Marca todas las órdenes del scope actual como vistas.
     */
    public function marcarTodasVistas(): JsonResponse
    {
        $user  = Auth::user();
        $query = DB::table('orders')->where('vista', 0)->whereNotNull('respondida');

        if ($user->hasRole('clinica') && $user->clinic) {
            $query->where('clinic_id', $user->clinic->id);
        } elseif ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $clinicIds = DB::table('clinic_staff')
                ->where('staff_id', $user->staff->id)
                ->pluck('clinic_id');
            $query->whereIn('clinic_id', $clinicIds);
        } elseif ($user->hasRole('holding') && $user->holding) {
            $clinicIds = DB::table('clinics')
                ->where('holding_id', $user->holding->id)
                ->pluck('id');
            $query->whereIn('clinic_id', $clinicIds);
        }

        $query->update(['vista' => 1]);
        return response()->json(['ok' => true]);
    }
}
