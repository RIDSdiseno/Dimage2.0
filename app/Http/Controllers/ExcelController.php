<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelController extends Controller
{
    private array $estados = [
        0 => 'No Informada',
        1 => 'Informada',
        2 => 'En Corrección',
        4 => 'Guardada',
    ];

    public function index()
    {
        return Inertia::render('Admin/Excel/Index', [
            'esRadiologoRestringido' => $this->radiologoRestringidoId() !== null,
            'esClinica'              => $this->clinicaId() !== null,
        ]);
    }

    public function download(Request $request): Response
    {
        $request->validate([
            'desde'      => ['required', 'date'],
            'hasta'      => ['required', 'date', 'after_or_equal:desde'],
            'tipo_fecha' => ['required', 'in:creacion,envio,respuesta'],
        ]);

        $desde      = Carbon::parse($request->input('desde'))->startOfDay();
        $hasta      = Carbon::parse($request->input('hasta'))->endOfDay();
        $tipoFecha  = $request->input('tipo_fecha');

        $dateColumn = match ($tipoFecha) {
            'envio'     => 'orders.enviada',
            'respuesta' => 'orders.respondida',
            default     => 'orders.created_at',
        };

        $restrictedId    = $this->radiologoRestringidoId();
        $clinicStaffIds  = $this->clinicaStaffIds();

        // One row per (order, radiologist) — multiple radiologists = multiple rows
        $rows = DB::table('orders')
            ->join('patients as p', 'p.id', '=', 'orders.patient_id')
            ->join('clinics as c', 'c.id', '=', 'orders.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as od', 'od.id', '=', 'orders.odontologo_id')
            ->leftJoin('users as uod', 'uod.id', '=', 'od.user_id')
            ->leftJoin('order_staff_exam as ose', 'ose.order_id', '=', 'orders.id')
            ->leftJoin('staffs as rad', 'rad.id', '=', 'ose.staff_id')
            ->leftJoin('users as urad', 'urad.id', '=', 'rad.user_id')
            ->whereBetween($dateColumn, [$desde, $hasta])
            ->when($restrictedId, fn ($q) => $q->where('ose.staff_id', $restrictedId))
            ->when($clinicStaffIds, fn ($q) => $q->whereIn('ose.staff_id', $clinicStaffIds))
            ->select(
                'orders.id',
                'uc.name as clinica',
                'urad.name as radiologo',
                'p.rut',
                'p.name as paciente',
                'uod.name as odontologo',
                'orders.estadoradiologo',
                'orders.created_at',
                'orders.enviada',
                'orders.respondida'
            )
            ->distinct()
            ->orderByDesc('orders.created_at')
            ->orderBy('urad.name')
            ->get();

        $orderIds = $rows->pluck('id')->unique();

        // One row per examination with piezas and url_texto
        $examinationsData = DB::table('examination_order as eo')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->whereIn('eo.order_id', $orderIds)
            ->select('eo.order_id', 'e.id as exam_id', 'k.descipcion', 'e.piezas', 'e.url_texto')
            ->get()
            ->groupBy('order_id');

        // Rx file count per examination (Cant. de Rx = files uploaded by clinic, not informe)
        $fileCountsPerExam = DB::table('files as f')
            ->whereIn('f.examination_id', function ($q) use ($orderIds) {
                $q->select('eo.examination_id')
                  ->from('examination_order as eo')
                  ->whereIn('eo.order_id', $orderIds);
            })
            ->where('f.desde_informar', '!=', 1)
            ->select('f.examination_id', DB::raw('count(f.id) as cnt'))
            ->groupBy('f.examination_id')
            ->pluck('cnt', 'examination_id');

        // ── Build spreadsheet ────────────────────────────────────────────────

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Órdenes');

        $headers = [
            'N° de Orden', 'Sucursal', 'Radiólogo', 'Rut', 'Paciente',
            'Odontólogo', 'Estado del informe', 'Tipo de radiografía',
            'Cant. de Informes', 'Cant. de Rx', 'Piezas',
            'Fecha de creación', 'Hora de creación',
            'Fecha de envío', 'Hora de envío',
            'Fecha de respuesta', 'Hora de respuesta',
        ];

        foreach ($headers as $col => $heading) {
            $sheet->setCellValue([$col + 1, 1], $heading);
        }

        // One row per exam per order:
        // - Cefalométrico with url_texto → one row per analysis type
        // - Retroalveolar → cantInformes = pieza count, cantPiezas = pieza count
        // - Everything else → cantInformes = 1, cantPiezas = blank
        $expandedRows = [];
        foreach ($rows as $r) {
            $exams = $examinationsData[$r->id] ?? collect();

            if ($exams->isEmpty()) {
                $expandedRows[] = ['row' => $r, 'tipo' => '', 'cantInformes' => 0, 'cantRx' => 0, 'cantPiezas' => null];
                continue;
            }

            foreach ($exams as $exam) {
                $desc      = $exam->descipcion ?? '';
                $piezasStr = $exam->piezas ?? '';
                $urlTexto  = $exam->url_texto ?? '';
                $fileCount = (int) ($fileCountsPerExam[$exam->exam_id] ?? 0);
                $descLower       = strtolower($desc);
                $isCefalo        = str_contains($descLower, 'cefalom');
                $isRetroUnitaria = str_contains($descLower, 'retroalveolar') && str_contains($descLower, 'unitaria');

                $piezaCount = 0;
                if ($isRetroUnitaria && !empty($piezasStr)) {
                    $piezaCount = count(array_filter(array_map('trim', explode(',', $piezasStr))));
                }

                if ($isCefalo && !empty($urlTexto)) {
                    foreach (explode(',', $urlTexto) as $analysis) {
                        $analysis = trim($analysis);
                        if ($analysis) {
                            $expandedRows[] = [
                                'row'          => $r,
                                'tipo'         => $desc . ' - ' . $analysis,
                                'cantInformes' => 1,
                                'cantRx'       => $fileCount,
                                'cantPiezas'   => null,
                            ];
                        }
                    }
                } else {
                    $expandedRows[] = [
                        'row'          => $r,
                        'tipo'         => $desc,
                        // Retroalveolar Unitaria: cantInformes = pieza count; all others = 1
                        'cantInformes' => $isRetroUnitaria ? max($piezaCount, 1) : 1,
                        'cantRx'       => $fileCount,
                        // Piezas column: only for Retroalveolar Unitaria
                        'cantPiezas'   => $isRetroUnitaria ? $piezaCount : null,
                    ];
                }
            }
        }

        // Write data rows
        $rowNum = 2;
        foreach ($expandedRows as $item) {
            $r = $item['row'];

            $sheet->setCellValue([1,  $rowNum], $r->id);
            $sheet->setCellValue([2,  $rowNum], $r->clinica);
            $sheet->setCellValue([3,  $rowNum], $r->radiologo ?? '');
            $sheet->setCellValue([4,  $rowNum], $r->rut);
            $sheet->setCellValue([5,  $rowNum], $r->paciente);
            $sheet->setCellValue([6,  $rowNum], $r->odontologo ?? '');
            $sheet->setCellValue([7,  $rowNum], $this->estados[(int) $r->estadoradiologo] ?? 'Desconocido');
            $sheet->setCellValue([8,  $rowNum], $item['tipo']);
            $sheet->setCellValue([9,  $rowNum], (int) $item['cantInformes']);
            $sheet->setCellValue([10, $rowNum], (int) $item['cantRx']);
            $sheet->setCellValue([11, $rowNum], $item['cantPiezas'] !== null ? (int) $item['cantPiezas'] : '');
            $sheet->setCellValue([12, $rowNum], $r->created_at ? Carbon::parse($r->created_at)->format('d/m/Y') : '');
            $sheet->setCellValue([13, $rowNum], $r->created_at ? Carbon::parse($r->created_at)->format('H:i')   : '');
            $sheet->setCellValue([14, $rowNum], $r->enviada    ? Carbon::parse($r->enviada)->format('d/m/Y')    : '');
            $sheet->setCellValue([15, $rowNum], $r->enviada    ? Carbon::parse($r->enviada)->format('H:i')      : '');
            $sheet->setCellValue([16, $rowNum], $r->respondida ? Carbon::parse($r->respondida)->format('d/m/Y') : '');
            $sheet->setCellValue([17, $rowNum], $r->respondida ? Carbon::parse($r->respondida)->format('H:i')   : '');

            $rowNum++;
        }

        $lastRow = max($rowNum - 1, 1);
        $lastCol = \count($headers); // 17
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $tableRange    = "A1:{$lastColLetter}{$lastRow}";

        // ── Excel Table (ListObject) ─────────────────────────────────────────
        $table = new Table($tableRange);
        $table->setName('TablaOrdenes');
        $table->setShowTotalsRow(false);

        $tableStyle = new TableStyle();
        $tableStyle->setTheme(TableStyle::TABLE_STYLE_MEDIUM2); // blue banded rows
        $table->setStyle($tableStyle);

        $sheet->addTable($table);

        // ── Header row styling ───────────────────────────────────────────────
        $headerRange = "A1:{$lastColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => Color::COLOR_WHITE],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0B2A4A'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // ── Data rows: alternate banding & borders ───────────────────────────
        for ($i = 2; $i <= $lastRow; $i++) {
            $fillColor = ($i % 2 === 0) ? 'FFE8EEF7' : 'FFFFFFFF';
            $sheet->getStyle("A{$i}:{$lastColLetter}{$i}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => $fillColor],
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFD1D9E6'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // Center-align numeric & time columns
        $centerCols = ['A', 'I', 'J', 'K', 'M', 'O', 'Q'];
        foreach ($centerCols as $col) {
            $sheet->getStyle("{$col}2:{$col}{$lastRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // ── Column widths ────────────────────────────────────────────────────
        $widths = [
            'A' => 10,  // N° de Orden
            'B' => 22,  // Sucursal
            'C' => 22,  // Radiólogo
            'D' => 14,  // Rut
            'E' => 28,  // Paciente
            'F' => 22,  // Odontólogo
            'G' => 18,  // Estado del informe
            'H' => 22,  // Tipo de radiografía
            'I' => 16,  // Cant. de Informes
            'J' => 13,  // Cant. de Rx
            'K' => 10,  // Piezas
            'L' => 14,  // Fecha de creación
            'M' => 13,  // Hora de creación
            'N' => 14,  // Fecha de envío
            'O' => 13,  // Hora de envío
            'P' => 16,  // Fecha de respuesta
            'Q' => 15,  // Hora de respuesta
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Freeze the header row
        $sheet->freezePane('A2');

        // ── Stream XLSX ──────────────────────────────────────────────────────
        $filename = 'ordenes_' . $request->input('desde') . '_' . $request->input('hasta') . '.xlsx';

        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($temp);
        $content = file_get_contents($temp);
        @unlink($temp);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Returns the clinic_id if the current user is a clinic profile (type_id=4).
     * Returns null for all other user types.
     */
    private function clinicaId(): ?int
    {
        $user = auth()->user();
        if ((int) $user->type_id !== 4 && ! $user->hasRole('clinica')) {
            return null;
        }
        $clinic = DB::table('clinics')->where('user_id', $user->id)->first(['id']);
        return $clinic ? (int) $clinic->id : null;
    }

    /**
     * Returns the staff_ids of all radiologists associated with the clinic user's clinic.
     * Works like radiologoRestringidoId() but for all radiologists in clinic_staff.
     * Returns null if the current user is not a clinic.
     */
    private function clinicaStaffIds(): ?array
    {
        $clinicId = $this->clinicaId();
        if ($clinicId === null) {
            return null;
        }
        $ids = DB::table('clinic_staff')
            ->where('clinic_id', $clinicId)
            ->pluck('staff_id')
            ->all();
        return $ids ?: [-1]; // -1 ensures no match if clinic has no staff
    }

    /**
     * Returns the staff_id if the current user is a radiologist WITHOUT admin rights,
     * meaning their queries must be scoped to their own orders only.
     * Returns null for admins and non-radiologist users (no restriction).
     */
    private function radiologoRestringidoId(): ?int
    {
        $user = auth()->user();
        if ((int) $user->type_id !== 5) {
            return null;
        }
        $staff = DB::table('staffs')
            ->where('user_id', $user->id)
            ->first(['id', 'puede_crear_ordenes']);

        if (! $staff || $staff->puede_crear_ordenes) {
            return null; // admin radiologist — unrestricted
        }
        return (int) $staff->id;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function streamXlsx(Spreadsheet $spreadsheet, string $filename): Response
    {
        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($temp);
        $content = file_get_contents($temp);
        @unlink($temp);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function styleHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B2A4A']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
    }

    private function applyTable(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range, string $name): void
    {
        $table = new Table($range);
        $table->setName($name);
        $table->setShowTotalsRow(false);
        $tableStyle = new TableStyle();
        $tableStyle->setTheme(TableStyle::TABLE_STYLE_MEDIUM2);
        $table->setStyle($tableStyle);
        $sheet->addTable($table);
    }

    // ── Por tipo de examen ────────────────────────────────────────────────────

    public function downloadByExamType(Request $request): Response
    {
        $request->validate([
            'desde'      => ['required', 'date'],
            'hasta'      => ['required', 'date', 'after_or_equal:desde'],
            'tipo_fecha' => ['required', 'in:creacion,envio,respuesta'],
        ]);

        $desde     = Carbon::parse($request->input('desde'))->startOfDay();
        $hasta     = Carbon::parse($request->input('hasta'))->endOfDay();
        $dateCol   = match ($request->input('tipo_fecha')) {
            'envio'     => 'orders.enviada',
            'respuesta' => 'orders.respondida',
            default     => 'orders.created_at',
        };

        $restrictedId   = $this->radiologoRestringidoId();
        $clinicStaffIds = $this->clinicaStaffIds();

        // Filtered order IDs (respects radiologist / clinic restriction)
        $filteredOrderIds = DB::table('orders')
            ->leftJoin('order_staff_exam as ose', 'ose.order_id', '=', 'orders.id')
            ->whereBetween($dateCol, [$desde, $hasta])
            ->when($restrictedId, fn ($q) => $q->where('ose.staff_id', $restrictedId))
            ->when($clinicStaffIds, fn ($q) => $q->whereIn('ose.staff_id', $clinicStaffIds))
            ->select('orders.id')
            ->distinct()
            ->pluck('id');

        // ── Hoja 1: Resumen por tipo (sin join ose — usa filteredOrderIds) ───
        $summary = DB::table('examination_order as eo')
            ->join('orders', 'orders.id', '=', 'eo.order_id')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->whereIn('eo.order_id', $filteredOrderIds)
            ->select(
                'k.id as kind_id',
                'k.descipcion as tipo',
                DB::raw('COUNT(DISTINCT eo.order_id) as total_ordenes'),
                DB::raw('COUNT(DISTINCT CASE WHEN orders.estadoradiologo = 1 THEN eo.order_id END) as informadas'),
                DB::raw('COUNT(DISTINCT CASE WHEN orders.estadoradiologo != 1 THEN eo.order_id END) as no_informadas'),
                // total_examenes: cefalométrico counts each analysis type, unitaria counts each pieza
                DB::raw("
                    SUM(CASE
                        WHEN LOWER(k.descipcion) LIKE '%cefalom%' AND e.url_texto IS NOT NULL AND e.url_texto != ''
                        THEN (CHAR_LENGTH(e.url_texto) - CHAR_LENGTH(REPLACE(e.url_texto,',','')) + 1)
                        WHEN LOWER(k.descipcion) LIKE '%retroalveolar%' AND LOWER(k.descipcion) LIKE '%unitaria%'
                             AND e.piezas IS NOT NULL AND e.piezas != ''
                        THEN (CHAR_LENGTH(e.piezas) - CHAR_LENGTH(REPLACE(e.piezas,',','')) + 1)
                        ELSE 1
                    END) as total_examenes
                "),
                // cant_piezas: only retroalveolar unitaria
                DB::raw("
                    SUM(CASE
                        WHEN LOWER(k.descipcion) LIKE '%retroalveolar%' AND LOWER(k.descipcion) LIKE '%unitaria%'
                             AND e.piezas IS NOT NULL AND e.piezas != ''
                        THEN (CHAR_LENGTH(e.piezas) - CHAR_LENGTH(REPLACE(e.piezas,',','')) + 1)
                        ELSE 0
                    END) as cant_piezas
                ")
            )
            ->groupBy('k.id', 'k.descipcion')
            ->orderBy('k.descipcion')
            ->get();

        // Rx file counts per kind_id (separate query avoids file-join row explosion)
        $rxByKind = DB::table('files as f')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'f.examination_id')
            ->join('examinations as e', 'e.id', '=', 'f.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->whereIn('eo.order_id', $filteredOrderIds)
            ->where('f.desde_informar', '!=', 1)
            ->select('k.id as kind_id', DB::raw('COUNT(DISTINCT f.id) as cnt'))
            ->groupBy('k.id')
            ->pluck('cnt', 'kind_id');

        // ── Hoja 2: Detalle — one row per (exam/analysis, order, radiologist) ─
        // Raw data: one row per (examination, order, radiologist)
        $detailRaw = DB::table('examination_order as eo')
            ->join('orders', 'orders.id', '=', 'eo.order_id')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->join('patients as p', 'p.id', '=', 'orders.patient_id')
            ->join('clinics as c', 'c.id', '=', 'orders.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as od', 'od.id', '=', 'orders.odontologo_id')
            ->leftJoin('users as uod', 'uod.id', '=', 'od.user_id')
            ->leftJoin('order_staff_exam as ose', 'ose.order_id', '=', 'orders.id')
            ->leftJoin('staffs as rad', 'rad.id', '=', 'ose.staff_id')
            ->leftJoin('users as urad', 'urad.id', '=', 'rad.user_id')
            ->whereIn('eo.order_id', $filteredOrderIds)
            ->select(
                'k.descipcion as tipo',
                'e.url_texto',
                'e.piezas',
                'e.id as exam_id',
                'orders.id as orden_id',
                'uc.name as clinica',
                'urad.name as radiologo',
                'p.rut',
                'p.name as paciente',
                'uod.name as odontologo',
                'orders.estadoradiologo',
                'orders.created_at',
                'orders.enviada',
                'orders.respondida'
            )
            ->distinct()
            ->orderBy('k.descipcion')
            ->orderBy('orders.id')
            ->orderBy('urad.name')
            ->get();

        // File counts per examination for detail
        $detailExamIds    = $detailRaw->pluck('exam_id')->unique();
        $detailFileCounts = DB::table('files as f')
            ->whereIn('f.examination_id', $detailExamIds)
            ->where('f.desde_informar', '!=', 1)
            ->select('f.examination_id', DB::raw('count(f.id) as cnt'))
            ->groupBy('f.examination_id')
            ->pluck('cnt', 'examination_id');

        // Expand cefalométrico by analysis types in detail rows
        $detailRows = [];
        foreach ($detailRaw as $r) {
            $descLower       = strtolower($r->tipo);
            $isCefalo        = str_contains($descLower, 'cefalom');
            $isRetroUnitaria = str_contains($descLower, 'retroalveolar') && str_contains($descLower, 'unitaria');
            $fileCount       = (int) ($detailFileCounts[$r->exam_id] ?? 0);
            $piezaCount      = 0;
            if ($isRetroUnitaria && !empty($r->piezas)) {
                $piezaCount = count(array_filter(array_map('trim', explode(',', $r->piezas))));
            }

            if ($isCefalo && !empty($r->url_texto)) {
                foreach (explode(',', $r->url_texto) as $analysis) {
                    $analysis = trim($analysis);
                    if ($analysis) {
                        $detailRows[] = array_merge((array) $r, [
                            'tipo_display' => $r->tipo . ' - ' . $analysis,
                            'cantInformes' => 1,
                            'cantRx'       => $fileCount,
                            'cantPiezas'   => null,
                        ]);
                    }
                }
            } else {
                $detailRows[] = array_merge((array) $r, [
                    'tipo_display' => $r->tipo,
                    'cantInformes' => $isRetroUnitaria ? max($piezaCount, 1) : 1,
                    'cantRx'       => $fileCount,
                    'cantPiezas'   => $isRetroUnitaria ? $piezaCount : null,
                ]);
            }
        }

        // ── Spreadsheet ───────────────────────────────────────────────────────
        $spreadsheet = new Spreadsheet();

        // Hoja 1 — Resumen
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Resumen');

        $hResumen = [
            'Tipo de Examen', 'Total Órdenes', 'Informadas', 'No Informadas',
            'Total Exámenes', 'Cant. de Rx', 'Cant. de Piezas',
        ];
        foreach ($hResumen as $col => $h) {
            $sheet1->setCellValue([$col + 1, 1], $h);
        }
        $rowNum = 2;
        foreach ($summary as $r) {
            $cantPiezas = (int) $r->cant_piezas;
            $sheet1->setCellValue([1, $rowNum], $r->tipo);
            $sheet1->setCellValue([2, $rowNum], (int) $r->total_ordenes);
            $sheet1->setCellValue([3, $rowNum], (int) $r->informadas);
            $sheet1->setCellValue([4, $rowNum], (int) $r->no_informadas);
            $sheet1->setCellValue([5, $rowNum], (int) $r->total_examenes);
            $sheet1->setCellValue([6, $rowNum], (int) ($rxByKind[$r->kind_id] ?? 0));
            $sheet1->setCellValue([7, $rowNum], $cantPiezas > 0 ? $cantPiezas : '');
            $rowNum++;
        }
        $lastRow1  = max($rowNum - 1, 1);
        $lastCol1L = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($hResumen));
        $this->applyTable($sheet1, "A1:{$lastCol1L}{$lastRow1}", 'TablaResumen');
        $this->styleHeaderRow($sheet1, "A1:{$lastCol1L}1");
        foreach (['A' => 32, 'B' => 15, 'C' => 13, 'D' => 15, 'E' => 16, 'F' => 13, 'G' => 15] as $col => $w) {
            $sheet1->getColumnDimension($col)->setWidth($w);
        }
        $sheet1->freezePane('A2');

        // Hoja 2 — Detalle
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle por Orden');

        $hDetalle = [
            'Tipo de Examen', 'N° de Orden', 'Sucursal', 'Radiólogo',
            'Rut', 'Paciente', 'Odontólogo', 'Estado del informe',
            'Cant. Informes', 'Cant. Rx', 'Piezas',
            'Fecha creación', 'Hora creación',
            'Fecha envío', 'Hora envío',
            'Fecha respuesta', 'Hora respuesta',
        ];
        foreach ($hDetalle as $col => $h) {
            $sheet2->setCellValue([$col + 1, 1], $h);
        }
        $rowNum = 2;
        foreach ($detailRows as $r) {
            $sheet2->setCellValue([1,  $rowNum], $r['tipo_display']);
            $sheet2->setCellValue([2,  $rowNum], $r['orden_id']);
            $sheet2->setCellValue([3,  $rowNum], $r['clinica']);
            $sheet2->setCellValue([4,  $rowNum], $r['radiologo'] ?? '');
            $sheet2->setCellValue([5,  $rowNum], $r['rut']);
            $sheet2->setCellValue([6,  $rowNum], $r['paciente']);
            $sheet2->setCellValue([7,  $rowNum], $r['odontologo'] ?? '');
            $sheet2->setCellValue([8,  $rowNum], $this->estados[(int) $r['estadoradiologo']] ?? '');
            $sheet2->setCellValue([9,  $rowNum], (int) $r['cantInformes']);
            $sheet2->setCellValue([10, $rowNum], (int) $r['cantRx']);
            $sheet2->setCellValue([11, $rowNum], $r['cantPiezas'] !== null ? (int) $r['cantPiezas'] : '');
            $sheet2->setCellValue([12, $rowNum], $r['created_at'] ? Carbon::parse($r['created_at'])->format('d/m/Y') : '');
            $sheet2->setCellValue([13, $rowNum], $r['created_at'] ? Carbon::parse($r['created_at'])->format('H:i')   : '');
            $sheet2->setCellValue([14, $rowNum], $r['enviada']    ? Carbon::parse($r['enviada'])->format('d/m/Y')    : '');
            $sheet2->setCellValue([15, $rowNum], $r['enviada']    ? Carbon::parse($r['enviada'])->format('H:i')      : '');
            $sheet2->setCellValue([16, $rowNum], $r['respondida'] ? Carbon::parse($r['respondida'])->format('d/m/Y') : '');
            $sheet2->setCellValue([17, $rowNum], $r['respondida'] ? Carbon::parse($r['respondida'])->format('H:i')   : '');
            $rowNum++;
        }
        $lastRow2  = max($rowNum - 1, 1);
        $lastCol2L = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($hDetalle));
        $this->applyTable($sheet2, "A1:{$lastCol2L}{$lastRow2}", 'TablaDetalle');
        $this->styleHeaderRow($sheet2, "A1:{$lastCol2L}1");

        $widths2 = ['A' => 30, 'B' => 11, 'C' => 22, 'D' => 22, 'E' => 14,
                    'F' => 28, 'G' => 22, 'H' => 18, 'I' => 13, 'J' => 11,
                    'K' => 11, 'L' => 14, 'M' => 12, 'N' => 12, 'O' => 12,
                    'P' => 15, 'Q' => 13];
        foreach ($widths2 as $col => $w) {
            $sheet2->getColumnDimension($col)->setWidth($w);
        }
        $sheet2->freezePane('A2');

        $spreadsheet->setActiveSheetIndex(0);

        return $this->streamXlsx($spreadsheet, 'por_tipo_examen_' . $request->input('desde') . '_' . $request->input('hasta') . '.xlsx');
    }

    // ── Por radiólogo ─────────────────────────────────────────────────────────

    public function downloadByRadiologo(Request $request): Response
    {
        if ($this->radiologoRestringidoId() !== null || $this->clinicaId() !== null) {
            abort(403, 'Sin permiso para este reporte.');
        }

        $request->validate([
            'desde'      => ['required', 'date'],
            'hasta'      => ['required', 'date', 'after_or_equal:desde'],
            'tipo_fecha' => ['required', 'in:creacion,envio,respuesta'],
        ]);

        $desde   = Carbon::parse($request->input('desde'))->startOfDay();
        $hasta   = Carbon::parse($request->input('hasta'))->endOfDay();
        $dateCol = match ($request->input('tipo_fecha')) {
            'envio'     => 'orders.enviada',
            'respuesta' => 'orders.respondida',
            default     => 'orders.created_at',
        };

        $restrictedId = $this->radiologoRestringidoId();

        $rows = DB::table('orders')
            ->leftJoin('staffs as rad', 'rad.id', '=', 'orders.radiologo_id')
            ->leftJoin('users as u', 'u.id', '=', 'rad.user_id')
            ->whereBetween($dateCol, [$desde, $hasta])
            ->when($restrictedId, fn ($q) => $q->where('orders.radiologo_id', $restrictedId))
            ->select(
                DB::raw("COALESCE(u.name, 'Sin asignar') as radiologo"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo = 1 THEN 1 ELSE 0 END) as informadas'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo = 0 THEN 1 ELSE 0 END) as pendientes'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo = 2 THEN 1 ELSE 0 END) as en_correccion'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo = 4 THEN 1 ELSE 0 END) as borradores')
            )
            ->groupBy('orders.radiologo_id', 'u.name')
            ->orderBy('u.name')
            ->get();

        $headers = ['Radiólogo', 'Total Órdenes', 'Informadas', 'Pendientes', 'En Corrección', 'Borradores'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Por Radiólogo');

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue([1, $rowNum], $r->radiologo);
            $sheet->setCellValue([2, $rowNum], (int) $r->total);
            $sheet->setCellValue([3, $rowNum], (int) $r->informadas);
            $sheet->setCellValue([4, $rowNum], (int) $r->pendientes);
            $sheet->setCellValue([5, $rowNum], (int) $r->en_correccion);
            $sheet->setCellValue([6, $rowNum], (int) $r->borradores);
            $rowNum++;
        }

        $lastRow = max($rowNum - 1, 1);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $range = "A1:{$lastColLetter}{$lastRow}";

        $this->applyTable($sheet, $range, 'TablaRadiologo');
        $this->styleHeaderRow($sheet, "A1:{$lastColLetter}1");

        foreach (['A' => 28, 'B' => 14, 'C' => 13, 'D' => 13, 'E' => 15, 'F' => 13] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        return $this->streamXlsx($spreadsheet, 'por_radiologo_' . $request->input('desde') . '_' . $request->input('hasta') . '.xlsx');
    }

    // ── Uso de espacio ────────────────────────────────────────────────────────

    public function downloadEspacioUso(): Response
    {
        // Space report — restricted radiologists and clinic users cannot access it
        if ($this->radiologoRestringidoId() !== null || $this->clinicaId() !== null) {
            abort(403, 'Sin permiso para este reporte.');
        }

        $rows = DB::table('files as f')
            ->join('examinations as e', 'e.id', '=', 'f.examination_id')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->join('orders as o', 'o.id', '=', 'eo.order_id')
            ->join('clinics as c', 'c.id', '=', 'o.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->select(
                'uc.name as clinica',
                DB::raw('COUNT(f.id) as total_archivos'),
                DB::raw('SUM(f.size) as bytes_total')
            )
            ->groupBy('c.id', 'uc.name')
            ->orderBy('bytes_total', 'desc')
            ->get();

        $headers = ['Clínica', 'Total Archivos', 'Tamaño Total (MB)', 'Tamaño Total (GB)'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Uso de Espacio');

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }

        $rowNum = 2;
        $grandTotal = 0;
        foreach ($rows as $r) {
            $mb = round(($r->bytes_total ?? 0) / 1048576, 2);
            $gb = round(($r->bytes_total ?? 0) / 1073741824, 4);
            $grandTotal += ($r->bytes_total ?? 0);

            $sheet->setCellValue([1, $rowNum], $r->clinica);
            $sheet->setCellValue([2, $rowNum], (int) $r->total_archivos);
            $sheet->setCellValue([3, $rowNum], $mb);
            $sheet->setCellValue([4, $rowNum], $gb);
            $rowNum++;
        }

        // Totals row
        $sheet->setCellValue([1, $rowNum], 'TOTAL');
        $sheet->setCellValue([3, $rowNum], round($grandTotal / 1048576, 2));
        $sheet->setCellValue([4, $rowNum], round($grandTotal / 1073741824, 4));
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
        ]);

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $tableRange = "A1:{$lastColLetter}" . ($rowNum - 1);

        $this->applyTable($sheet, $tableRange, 'TablaEspacio');
        $this->styleHeaderRow($sheet, "A1:{$lastColLetter}1");

        foreach (['A' => 28, 'B' => 15, 'C' => 18, 'D' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        return $this->streamXlsx($spreadsheet, 'uso_espacio_' . now()->format('Y-m-d') . '.xlsx');
    }
}
