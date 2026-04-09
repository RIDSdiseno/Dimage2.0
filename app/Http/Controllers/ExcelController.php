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
        return Inertia::render('Admin/Excel/Index');
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

        $rows = DB::table('orders')
            ->join('patients as p', 'p.id', '=', 'orders.patient_id')
            ->join('clinics as c', 'c.id', '=', 'orders.clinic_id')
            ->join('users as uc', 'uc.id', '=', 'c.user_id')
            ->leftJoin('staffs as od', 'od.id', '=', 'orders.odontologo_id')
            ->leftJoin('users as uod', 'uod.id', '=', 'od.user_id')
            ->leftJoin('staffs as rad', 'rad.id', '=', 'orders.radiologo_id')
            ->leftJoin('users as urad', 'urad.id', '=', 'rad.user_id')
            ->whereBetween($dateColumn, [$desde, $hasta])
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
            ->orderByDesc('orders.created_at')
            ->get();

        $orderIds = $rows->pluck('id');

        // Exam types per order
        $examTypes = DB::table('examination_order as eo')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->whereIn('eo.order_id', $orderIds)
            ->select('eo.order_id', 'k.descipcion')
            ->get()
            ->groupBy('order_id');

        // Count of examinations per order (Cant. de Informes)
        $examCounts = DB::table('examination_order as eo')
            ->whereIn('eo.order_id', $orderIds)
            ->select('eo.order_id', DB::raw('count(*) as cnt'))
            ->groupBy('eo.order_id')
            ->pluck('cnt', 'order_id');

        // Count of Rx files per order (Cant. de Rx)
        $fileCounts = DB::table('files as f')
            ->join('examinations as e', 'e.id', '=', 'f.examination_id')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->whereIn('eo.order_id', $orderIds)
            ->where('f.desde_informar', '!=', 1)
            ->select('eo.order_id', DB::raw('count(*) as cnt'))
            ->groupBy('eo.order_id')
            ->pluck('cnt', 'order_id');

        // Piezas: total files (image + report) per order
        $piezas = DB::table('files as f')
            ->join('examinations as e', 'e.id', '=', 'f.examination_id')
            ->join('examination_order as eo', 'eo.examination_id', '=', 'e.id')
            ->whereIn('eo.order_id', $orderIds)
            ->select('eo.order_id', DB::raw('count(*) as cnt'))
            ->groupBy('eo.order_id')
            ->pluck('cnt', 'order_id');

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

        // Write headers in row 1
        foreach ($headers as $col => $heading) {
            $sheet->setCellValue([$col + 1, 1], $heading);
        }

        // Write data rows
        $rowNum = 2;
        foreach ($rows as $r) {
            $tipos = ($examTypes[$r->id] ?? collect())->pluck('descipcion')->unique()->implode(', ');

            $sheet->setCellValue([1,  $rowNum], $r->id);
            $sheet->setCellValue([2,  $rowNum], $r->clinica);
            $sheet->setCellValue([3,  $rowNum], $r->radiologo ?? '');
            $sheet->setCellValue([4,  $rowNum], $r->rut);
            $sheet->setCellValue([5,  $rowNum], $r->paciente);
            $sheet->setCellValue([6,  $rowNum], $r->odontologo ?? '');
            $sheet->setCellValue([7,  $rowNum], $this->estados[(int) $r->estadoradiologo] ?? 'Desconocido');
            $sheet->setCellValue([8,  $rowNum], $tipos ?: '');
            $sheet->setCellValue([9,  $rowNum], (int) ($examCounts[$r->id] ?? 0));
            $sheet->setCellValue([10, $rowNum], (int) ($fileCounts[$r->id] ?? 0));
            $sheet->setCellValue([11, $rowNum], (int) ($piezas[$r->id] ?? 0));
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

        $rows = DB::table('examination_order as eo')
            ->join('orders', 'orders.id', '=', 'eo.order_id')
            ->join('examinations as e', 'e.id', '=', 'eo.examination_id')
            ->join('kinds as k', 'k.id', '=', 'e.kind_id')
            ->whereBetween($dateCol, [$desde, $hasta])
            ->select(
                'k.descipcion as tipo',
                DB::raw('COUNT(DISTINCT eo.order_id) as total_ordenes'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo = 1 THEN 1 ELSE 0 END) as informadas'),
                DB::raw('SUM(CASE WHEN orders.estadoradiologo != 1 THEN 1 ELSE 0 END) as no_informadas'),
                DB::raw('COUNT(eo.id) as total_examenes')
            )
            ->groupBy('k.id', 'k.descipcion')
            ->orderBy('k.descipcion')
            ->get();

        $headers = ['Tipo de Examen', 'Total Órdenes', 'Informadas', 'No Informadas', 'Total Exámenes'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Por Tipo de Examen');

        foreach ($headers as $col => $h) {
            $sheet->setCellValue([$col + 1, 1], $h);
        }

        $rowNum = 2;
        foreach ($rows as $r) {
            $sheet->setCellValue([1, $rowNum], $r->tipo);
            $sheet->setCellValue([2, $rowNum], (int) $r->total_ordenes);
            $sheet->setCellValue([3, $rowNum], (int) $r->informadas);
            $sheet->setCellValue([4, $rowNum], (int) $r->no_informadas);
            $sheet->setCellValue([5, $rowNum], (int) $r->total_examenes);
            $rowNum++;
        }

        $lastRow = max($rowNum - 1, 1);
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $range = "A1:{$lastColLetter}{$lastRow}";

        $this->applyTable($sheet, $range, 'TablaTipoExamen');
        $this->styleHeaderRow($sheet, "A1:{$lastColLetter}1");

        foreach (['A' => 30, 'B' => 15, 'C' => 13, 'D' => 15, 'E' => 15] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        $sheet->freezePane('A2');

        return $this->streamXlsx($spreadsheet, 'por_tipo_examen_' . $request->input('desde') . '_' . $request->input('hasta') . '.xlsx');
    }

    // ── Por radiólogo ─────────────────────────────────────────────────────────

    public function downloadByRadiologo(Request $request): Response
    {
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

        $rows = DB::table('orders')
            ->leftJoin('staffs as rad', 'rad.id', '=', 'orders.radiologo_id')
            ->leftJoin('users as u', 'u.id', '=', 'rad.user_id')
            ->whereBetween($dateCol, [$desde, $hasta])
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
