<?php

namespace App\Http\Controllers;

use App\Traits\CalculaAlertas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use CalculaAlertas;

    public function index(): Response
    {
        $user = Auth::user();

        $holdingId = null;
        if ($user->hasRole('holding') && $user->holding) {
            $holdingId = $user->holding->id;
        }

        $alertas = $this->calcularAlertas($user, $holdingId);

        // Conteos rápidos del día
        $hoy = Carbon::today();

        $baseQuery = DB::table('orders as o');
        $this->applyOrdenScopeToQuery($baseQuery, $user, $holdingId);

        $creadosHoy     = (clone $baseQuery)->whereDate('o.created_at', $hoy)->count();
        $respondidosHoy = (clone $baseQuery)->whereDate('o.respondida', $hoy)->count();

        return Inertia::render('Home/Index', [
            'alertas'        => $alertas,
            'creadosHoy'     => $creadosHoy,
            'respondidosHoy' => $respondidosHoy,
            'userName'       => $user->name,
        ]);
    }

    private function applyOrdenScopeToQuery($query, $user, ?int $holdingId): void
    {
        if ($user->hasAnyRole(['admin', 'secretaria', 'contralor'])) {
            return;
        }

        if ($user->hasRole('radiologo') && $user->staff) {
            $staffId = (int) $user->staff->id;
            $query->whereExists(fn($sub) =>
                $sub->select(DB::raw(1))
                    ->from('order_staff_exam as ose')
                    ->whereColumn('ose.order_id', 'o.id')
                    ->where('ose.staff_id', $staffId)
            );
            return;
        }

        if ($user->hasRole('holding') && $holdingId) {
            $query->whereIn('o.clinic_id',
                DB::table('clinics')->where('holding_id', $holdingId)->pluck('id')
            );
            return;
        }

        if ($user->hasRole('clinica') && $user->clinic) {
            $query->join('clinics as _c', '_c.id', '=', 'o.clinic_id')
                  ->where('_c.holding_id', $user->clinic->holding_id);
            return;
        }

        if ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
            $staffId = (int) $user->staff->id;
            $query->where(function ($q) use ($staffId) {
                $q->where('o.operator_id', $staffId)
                  ->orWhere('o.odontologo_id', $staffId);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }
}
