<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        $holdingId = null;
        if ($user->hasRole('holding') && $user->holding) {
            $holdingId = $user->holding->id;
        }

        // Date range (default: current month)
        $inicioDate = Carbon::now()->startOfMonth()->startOfDay();
        $finDate    = Carbon::now()->endOfMonth()->endOfDay();

        if ($request->filled('fecha_desde')) {
            try { $inicioDate = Carbon::createFromFormat('Y-m-d', $request->fecha_desde)->startOfDay(); } catch (\Throwable) {}
        }
        if ($request->filled('fecha_hasta')) {
            try { $finDate = Carbon::createFromFormat('Y-m-d', $request->fecha_hasta)->endOfDay(); } catch (\Throwable) {}
        }
        if ($finDate->lt($inicioDate)) {
            $finDate = $inicioDate->copy()->endOfDay();
        }

        $totalesOrdenes    = $this->totalesOrdenes($inicioDate, $finDate, $holdingId);
        $totalesExamenes   = $this->totalesExamenes($inicioDate, $finDate, $holdingId);
        $examenesRadiologo = $this->examenes($inicioDate, $finDate, 'radiologo', $holdingId);
        $examenesClinica   = $this->examenes($inicioDate, $finDate, 'clinica', $holdingId);
        $examenesHolding   = $this->examenes($inicioDate, $finDate, 'holding', $holdingId);
        $examenesRespondidos = $this->examenesRespondidos($inicioDate, $finDate, $holdingId);

        $gruposExamenes = [
            0 => 'Retroalveolares',
            1 => 'Bitewing',
            2 => 'Panorámicas',
            3 => 'Telerradiografía',
            4 => 'Cefalometrías',
            5 => 'Cone Beam Unimaxilar',
            6 => 'Cone Beam Bimaxilar',
            7 => 'Cone Beam ATM',
            8 => 'Otros',
        ];

        $examenesAgrupados = [
            1 => 0, 2 => 0, 3 => 1, 4 => 1, 5 => 1, 6 => 2, 7 => 2,
            8 => 0, 9 => 0, 10 => 1, 11 => 1, 12 => 1, 13 => 2, 14 => 2,
            15 => 2, 16 => 3, 17 => 3, 18 => 3, 19 => 5, 20 => 5, 21 => 6,
            22 => 7, 23 => 7, 24 => 8, 25 => 8, 26 => 7, 27 => 8, 28 => 4, 29 => 8,
        ];

        // ── Radiólogo chart data ──────────────────────────────────────────
        $radiologoChartData = [
            '-1' => ['dataset' => [], 'title' => 'Todos los radiólogos', 'total' => 0],
        ];
        $radiologos = [['id' => '-1', 'name' => 'Todos']];

        foreach ($examenesRadiologo as $e) {
            $kindId  = $e->id_tipo_examen;
            $radioId = (string) $e->id;
            $group   = $examenesAgrupados[$kindId] ?? 8;

            if (!isset($radiologoChartData[$radioId])) {
                $radiologos[] = ['id' => $radioId, 'name' => $e->name];
                $radiologoChartData[$radioId] = ['dataset' => [], 'title' => 'Radiólogo ' . $e->name, 'total' => 0];
                $radiologoChartData['-1']['dataset'][$radioId] = ['id' => $radioId, 'label' => $e->name, 'total' => 0, 'details' => []];
            }
            if (!isset($radiologoChartData[$radioId]['dataset'][$group])) {
                $radiologoChartData[$radioId]['dataset'][$group] = ['total' => 0, 'label' => $gruposExamenes[$group], 'details' => []];
            }
            $radiologoChartData[$radioId]['dataset'][$group]['total'] += $e->total;
            $radiologoChartData[$radioId]['total'] += $e->total;
            $radiologoChartData[$radioId]['dataset'][$group]['details'][] = $e->tipo_examen . ': ' . $e->total;
            $radiologoChartData['-1']['dataset'][$radioId]['total'] += $e->total;
            $radiologoChartData['-1']['total'] += $e->total;
            $radiologoChartData['-1']['dataset'][$radioId]['details'][] = $e->tipo_examen . ': ' . $e->total;
        }

        // ── Clínica chart data ────────────────────────────────────────────
        $clinicaChartData = [
            '-1' => ['dataset' => [], 'title' => 'Todas las clínicas', 'total' => 0],
        ];
        $clinicas = [['id' => '-1', 'name' => 'Todos']];

        foreach ($examenesClinica as $e) {
            $kindId   = $e->id_tipo_examen;
            $clinicId = (string) $e->clinic_id;
            $group    = $examenesAgrupados[$kindId] ?? 8;

            if (!isset($clinicaChartData[$clinicId])) {
                $clinicas[] = ['id' => $clinicId, 'name' => $e->name];
                $clinicaChartData[$clinicId] = ['dataset' => [], 'title' => 'Clínica ' . $e->name, 'total' => 0];
                $clinicaChartData['-1']['dataset'][$clinicId] = ['id' => $clinicId, 'label' => $e->name, 'total' => 0, 'details' => []];
            }
            if (!isset($clinicaChartData[$clinicId]['dataset'][$group])) {
                $clinicaChartData[$clinicId]['dataset'][$group] = ['total' => 0, 'label' => $gruposExamenes[$group], 'details' => []];
            }
            $clinicaChartData[$clinicId]['dataset'][$group]['total'] += $e->total;
            $clinicaChartData[$clinicId]['total'] += $e->total;
            $clinicaChartData[$clinicId]['dataset'][$group]['details'][] = $e->tipo_examen . ': ' . $e->total;
            $clinicaChartData['-1']['dataset'][$clinicId]['total'] += $e->total;
            $clinicaChartData['-1']['total'] += $e->total;
            $clinicaChartData['-1']['dataset'][$clinicId]['details'][] = $e->tipo_examen . ': ' . $e->total;
        }

        // ── Red / Holding chart data ──────────────────────────────────────
        $redChartData = [
            '-1' => ['dataset' => [], 'title' => 'Todas las redes', 'total' => 0],
        ];
        $redes = [['id' => '-1', 'name' => 'Todas']];

        foreach ($examenesHolding as $e) {
            $kindId  = $e->id_tipo_examen;
            $holdId  = (string) $e->holding_id;
            $group   = $examenesAgrupados[$kindId] ?? 8;

            if (!isset($redChartData[$holdId])) {
                $redes[] = ['id' => $holdId, 'name' => $e->name];
                $redChartData[$holdId] = ['dataset' => [], 'title' => 'Red ' . $e->name, 'total' => 0];
                $redChartData['-1']['dataset'][$holdId] = ['id' => $holdId, 'label' => $e->name, 'total' => 0, 'details' => []];
            }
            if (!isset($redChartData[$holdId]['dataset'][$group])) {
                $redChartData[$holdId]['dataset'][$group] = ['total' => 0, 'label' => $gruposExamenes[$group], 'details' => []];
            }
            $redChartData[$holdId]['dataset'][$group]['total'] += $e->total;
            $redChartData[$holdId]['total'] += $e->total;
            $redChartData[$holdId]['dataset'][$group]['details'][] = $e->tipo_examen . ': ' . $e->total;
            $redChartData['-1']['dataset'][$holdId]['total'] += $e->total;
            $redChartData['-1']['total'] += $e->total;
            $redChartData['-1']['dataset'][$holdId]['details'][] = $e->tipo_examen . ': ' . $e->total;
        }

        // ── Tiempo respuesta + Respondidas chart data ─────────────────────
        $radiologoTiempoChartData = [
            '-1' => ['dataset' => [], 'title' => 'Todos los radiólogos', 'total' => 0, 'tiempo_respuesta' => 0],
        ];
        $radiologoRespuestasChartData = [
            '-1' => ['dataset' => [], 'title' => 'Todos los radiólogos', 'total' => 0],
        ];
        $radiologosRespuestas = [['id' => '-1', 'name' => 'Todos']];
        $segundosEnDia = 86400;

        foreach ($examenesRespondidos as $e) {
            $kindId  = $e->id_tipo_examen;
            $radioId = (string) $e->id;
            $group   = $examenesAgrupados[$kindId] ?? 8;

            if (!isset($radiologoTiempoChartData[$radioId])) {
                $radiologosRespuestas[] = ['id' => $radioId, 'name' => $e->name];

                $radiologoTiempoChartData[$radioId] = ['dataset' => [], 'title' => 'Radiólogo ' . $e->name, 'total' => 0, 'tiempo_respuesta' => 0];
                $radiologoTiempoChartData['-1']['dataset'][$radioId] = ['id' => $radioId, 'label' => $e->name, 'total' => 0, 'tiempo_respuesta' => 0, 'details' => []];

                $radiologoRespuestasChartData[$radioId] = ['dataset' => [], 'title' => 'Radiólogo ' . $e->name, 'total' => 0];
                $radiologoRespuestasChartData['-1']['dataset'][$radioId] = ['id' => $radioId, 'label' => $e->name, 'total' => 0, 'details' => []];
            }

            // Respondidas
            if (!isset($radiologoRespuestasChartData[$radioId]['dataset'][$group])) {
                $radiologoRespuestasChartData[$radioId]['dataset'][$group] = ['total' => 0, 'label' => $gruposExamenes[$group], 'details' => []];
            }
            $radiologoRespuestasChartData[$radioId]['dataset'][$group]['total'] += $e->total;
            $radiologoRespuestasChartData[$radioId]['total'] += $e->total;
            $radiologoRespuestasChartData[$radioId]['dataset'][$group]['details'][] = $e->tipo_examen . ': ' . $e->total;
            $radiologoRespuestasChartData['-1']['dataset'][$radioId]['total'] += $e->total;
            $radiologoRespuestasChartData['-1']['total'] += $e->total;
            $radiologoRespuestasChartData['-1']['dataset'][$radioId]['details'][] = $e->tipo_examen . ': ' . $e->total;

            // Tiempo de respuesta
            if (!isset($radiologoTiempoChartData[$radioId]['dataset'][$kindId])) {
                $radiologoTiempoChartData[$radioId]['dataset'][$kindId] = ['total' => 0, 'tiempo_respuesta' => 0, 'label' => $e->tipo_examen];
            }
            $diasPromedio = round($e->tiempo_respuesta / $segundosEnDia, 3);
            $radiologoTiempoChartData[$radioId]['dataset'][$kindId]['tiempo_respuesta'] = $diasPromedio;
            $radiologoTiempoChartData[$radioId]['total'] += $e->total;
            $radiologoTiempoChartData[$radioId]['tiempo_respuesta'] += $e->total * $diasPromedio;
            $radiologoTiempoChartData['-1']['dataset'][$radioId]['tiempo_respuesta'] += round($e->total * $e->tiempo_respuesta / $segundosEnDia, 3);
            $radiologoTiempoChartData['-1']['dataset'][$radioId]['total'] += $e->total;
            $radiologoTiempoChartData['-1']['total'] += $e->total;
            $radiologoTiempoChartData['-1']['tiempo_respuesta'] += round($e->total * $e->tiempo_respuesta / $segundosEnDia, 3);
        }

        return Inertia::render('Dashboard/Index', [
            'fechaDesde'                   => $inicioDate->format('Y-m-d'),
            'fechaHasta'                   => $finDate->format('Y-m-d'),
            'totalesOrdenes'               => $totalesOrdenes,
            'totalesExamenes'              => $totalesExamenes,
            'radiologos'                   => $radiologos,
            'radiologoChartData'           => $radiologoChartData,
            'clinicas'                     => $clinicas,
            'clinicaChartData'             => $clinicaChartData,
            'redes'                        => $redes,
            'redChartData'                 => $redChartData,
            'radiologosRespuestas'         => $radiologosRespuestas,
            'radiologoTiempoChartData'     => $radiologoTiempoChartData,
            'radiologoRespuestasChartData' => $radiologoRespuestasChartData,
        ]);
    }

    // ── Private query helpers ─────────────────────────────────────────────

    private function totalesOrdenes(Carbon $desde, Carbon $hasta, $holdingId): array
    {
        $params = ['desde' => $desde->toDateTimeString(), 'hasta' => $hasta->toDateTimeString()];
        $w = '';
        if ($holdingId !== null) {
            $w = ' AND o.clinic_id IN (SELECT c.id FROM holdings h INNER JOIN clinics c ON h.id = c.holding_id WHERE h.id = :holding_id)';
            $params['holding_id'] = $holdingId;
        }
        $sql = "SELECT COUNT(1) AS total_creadas,
                       SUM(IF(o.enviada IS NOT NULL, 1, 0))    AS total_enviadas,
                       SUM(IF(o.respondida IS NOT NULL, 1, 0)) AS total_respondidas
                FROM orders o
                WHERE o.created_at BETWEEN :desde AND :hasta $w";
        $r = DB::select($sql, $params);
        return isset($r[0]) ? (array) $r[0] : ['total_creadas' => 0, 'total_enviadas' => 0, 'total_respondidas' => 0];
    }

    private function totalesExamenes(Carbon $desde, Carbon $hasta, $holdingId): array
    {
        $params = ['desde' => $desde->toDateTimeString(), 'hasta' => $hasta->toDateTimeString()];
        $w = '';
        if ($holdingId !== null) {
            $w = ' AND o.clinic_id IN (SELECT c.id FROM holdings h INNER JOIN clinics c ON h.id = c.holding_id WHERE h.id = :holding_id)';
            $params['holding_id'] = $holdingId;
        }
        $sql = "SELECT COUNT(1)                                                        AS total_examenes,
                       SUM(IF(e.kind_id NOT IN (19,20,21,22,23,27), 1, 0))             AS total_2d,
                       SUM(IF(e.kind_id IN (19,20,21,22,23,27), 1, 0))                AS total_3d,
                       SUM(IF(ose.respondida = 1, 1, 0))                              AS total_respondidos
                FROM orders o
                INNER JOIN examination_order eo  ON o.id = eo.order_id
                INNER JOIN examinations e        ON eo.examination_id = e.id
                INNER JOIN kinds k               ON e.kind_id = k.id
                LEFT  JOIN order_staff_exam ose  ON o.id = ose.order_id AND ose.group_exam = k.`group`
                WHERE o.created_at BETWEEN :desde AND :hasta $w";
        $r = DB::select($sql, $params);
        return isset($r[0]) ? (array) $r[0] : ['total_examenes' => 0, 'total_2d' => 0, 'total_3d' => 0, 'total_respondidos' => 0];
    }

    private function examenes(Carbon $desde, Carbon $hasta, string $agrupador, $holdingId): array
    {
        $params = ['desde' => $desde->toDateTimeString(), 'hasta' => $hasta->toDateTimeString()];
        $w = '';
        if ($holdingId !== null) {
            $w = ' AND o.clinic_id IN (SELECT c.id FROM holdings h INNER JOIN clinics c ON h.id = c.holding_id WHERE h.id = :holding_id)';
            $params['holding_id'] = $holdingId;
        }
        [$extraSelect, $groupBy] = match ($agrupador) {
            'holding'  => [', c.holding_id, uh.name', ', c.holding_id, uh.name'],
            'clinica'  => [', o.clinic_id,  uc.name', ', o.clinic_id,  uc.name'],
            default    => [', s.id, ur.name',          ', s.id, ur.name'],
        };
        $sql = "SELECT e.kind_id AS id_tipo_examen, k.descipcion AS tipo_examen, COUNT(1) AS total $extraSelect
                FROM orders o
                INNER JOIN examination_order eo  ON o.id = eo.order_id
                INNER JOIN examinations e        ON eo.examination_id = e.id
                INNER JOIN kinds k               ON e.kind_id = k.id
                INNER JOIN clinics c             ON o.clinic_id = c.id
                INNER JOIN users uc              ON c.user_id = uc.id
                INNER JOIN holdings h            ON c.holding_id = h.id
                INNER JOIN users uh              ON h.user_id = uh.id
                INNER JOIN order_staff_exam ose  ON o.id = ose.order_id AND k.`group` = ose.group_exam
                INNER JOIN staffs s              ON ose.staff_id = s.id
                INNER JOIN users ur              ON s.user_id = ur.id
                WHERE o.created_at BETWEEN :desde AND :hasta $w
                GROUP BY e.kind_id, k.descipcion $groupBy";
        return DB::select($sql, $params);
    }

    private function examenesRespondidos(Carbon $desde, Carbon $hasta, $holdingId): array
    {
        $params = ['desde' => $desde->toDateTimeString(), 'hasta' => $hasta->toDateTimeString()];
        $w = '';
        if ($holdingId !== null) {
            $w = ' AND o.clinic_id IN (SELECT c.id FROM holdings h INNER JOIN clinics c ON h.id = c.holding_id WHERE h.id = :holding_id)';
            $params['holding_id'] = $holdingId;
        }
        $sql = "SELECT e.kind_id AS id_tipo_examen,
                       k.descipcion AS tipo_examen,
                       AVG(TIMESTAMPDIFF(SECOND, o.enviada, o.respondida)) AS tiempo_respuesta,
                       COUNT(1) AS total,
                       s.id,
                       ur.name
                FROM orders o
                INNER JOIN examination_order eo  ON o.id = eo.order_id
                INNER JOIN examinations e        ON eo.examination_id = e.id
                INNER JOIN kinds k               ON e.kind_id = k.id
                INNER JOIN order_staff_exam ose  ON o.id = ose.order_id AND k.`group` = ose.group_exam
                INNER JOIN staffs s              ON ose.staff_id = s.id
                INNER JOIN users ur              ON s.user_id = ur.id
                WHERE o.created_at BETWEEN :desde AND :hasta
                  AND ose.respondida = 1
                $w
                GROUP BY e.kind_id, s.id, ur.name, k.descipcion";
        return DB::select($sql, $params);
    }
}
