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
        $totalActivos   = (clone $baseQuery)->whereIn('o.estadoradiologo', [0, 2, 4])->count();

        return Inertia::render('Home/Index', [
            'alertas'        => $alertas,
            'creadosHoy'     => $creadosHoy,
            'respondidosHoy' => $respondidosHoy,
            'totalActivos'   => $totalActivos,
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
            $clinicIds = DB::table('clinic_staff')
                ->where('staff_id', $user->staff->id)
                ->pluck('clinic_id');
            if ($clinicIds->isEmpty()) {
                $query->whereRaw('1 = 0');
                return;
            }
            $holdingIds = DB::table('clinics')
                ->whereIn('id', $clinicIds)
                ->pluck('holding_id')
                ->filter()->unique();
            $query->join('clinics as _c', '_c.id', '=', 'o.clinic_id')
                  ->whereIn('_c.holding_id', $holdingIds);
            return;
        }

        $query->whereRaw('1 = 0');
    }
}
