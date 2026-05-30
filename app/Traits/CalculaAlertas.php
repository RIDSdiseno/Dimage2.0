<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait CalculaAlertas
{
    private function calcularAlertas($user, ?int $holdingId): array
    {
        $holidays = DB::table('feriados')
            ->pluck('fecha')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $today       = Carbon::today();
        $nextWorkDay = $this->addBusinessDays($today->copy(), 1, $holidays);

        try {
            $query = DB::table('orders as o')
                ->join('examination_order as eo', 'eo.order_id', '=', 'o.id')
                ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
                ->join('kinds as k', 'k.id', '=', 'e.kind_id')
                ->leftJoin('patients as p', 'p.id', '=', 'o.patient_id')
                ->leftJoin('clinics as c', 'c.id', '=', 'o.clinic_id')
                ->leftJoin('users as uc', 'uc.id', '=', 'c.user_id')
                ->whereNotNull('o.enviada')
                ->whereIn('o.estadoradiologo', [0, 2])
                ->select(
                    'o.id',
                    'o.enviada',
                    'o.prioridad',
                    'p.name as paciente',
                    'p.rut',
                    'uc.name as clinica',
                    DB::raw('COALESCE(MAX(k.plazo_dias), 3) as plazo_dias')
                )
                ->groupBy('o.id', 'o.enviada', 'o.prioridad', 'p.name', 'p.rut', 'uc.name');

            if ($user->hasRole('radiologo') && $user->staff) {
                $staffId = (int) $user->staff->id;
                $query->whereExists(function ($sub) use ($staffId) {
                    $sub->select(DB::raw(1))
                        ->from('order_staff_exam as ose')
                        ->whereColumn('ose.order_id', 'o.id')
                        ->where('ose.staff_id', $staffId);
                });
            } elseif ($user->hasRole('holding') && $holdingId) {
                $query->whereIn('o.clinic_id',
                    DB::table('clinics')->where('holding_id', $holdingId)->pluck('id')
                );
            } elseif ($user->hasRole('clinica') && $user->clinic) {
                $query->where('c.holding_id', $user->clinic->holding_id);
            } elseif ($user->hasAnyRole(['odontologo', 'tecnico']) && $user->staff) {
                $staffId = (int) $user->staff->id;
                $query->where(function ($q) use ($staffId) {
                    $q->where('o.operator_id', $staffId)
                      ->orWhere('o.odontologo_id', $staffId);
                });
            }

            $orders = $query->get();
        } catch (\Throwable) {
            return ['pendientes' => 0, 'vencidas' => [], 'porVencer' => []];
        }

        $pendientes = $orders->count();
        $vencidas   = [];
        $porVencer  = [];

        foreach ($orders as $order) {
            $plazo    = max(1, (int) ($order->plazo_dias ?? 3));
            $deadline = $this->addBusinessDays(
                Carbon::parse($order->enviada)->startOfDay(),
                $plazo,
                $holidays
            );

            $row = [
                'id'        => $order->id,
                'paciente'  => $order->paciente ?? '-',
                'rut'       => $order->rut ?? '-',
                'clinica'   => $order->clinica ?? '-',
                'enviada'   => Carbon::parse($order->enviada)->format('d/m/Y'),
                'vence'     => $deadline->format('d/m/Y'),
                'prioridad' => $order->prioridad ?? 'Normal',
            ];

            if ($deadline->lt($today)) {
                $row['dias_vencida'] = (int) $today->diffInDays($deadline);
                $vencidas[] = $row;
            } elseif ($deadline->lte($nextWorkDay)) {
                $porVencer[] = $row;
            }
        }

        usort($vencidas, fn($a, $b) => $b['dias_vencida'] - $a['dias_vencida']);

        return compact('pendientes', 'vencidas', 'porVencer');
    }

    private function addBusinessDays(Carbon $date, int $days, array $holidays): Carbon
    {
        $d     = $date->copy()->startOfDay();
        $added = 0;
        while ($added < $days) {
            $d->addDay();
            if (!$d->isWeekend() && !in_array($d->format('Y-m-d'), $holidays)) {
                $added++;
            }
        }
        return $d;
    }
}
