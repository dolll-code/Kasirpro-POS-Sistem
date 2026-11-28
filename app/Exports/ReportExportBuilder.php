<?php

namespace App\Exports;

use App\Models\StoreProfile;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Decimal;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExportBuilder
{
    public function __construct(protected ReportService $reportService) {}

    public function build(User $user, string $type, string $format, array $filters): array
    {
        $available = $this->reportService->availableReportRows($user, $type, $filters);
        $document = [
            'store' => StoreProfile::current(),
            'title' => $this->title($type),
            'type' => $type,
            'period' => $available['period'],
            'summary' => $available['summary'] ?? [],
            'summary_labels' => $available['summary_labels'] ?? [],
            'columns' => $available['columns'],
            'rows' => $available['rows'],
            'generated_at' => Carbon::now('Asia/Jakarta'),
            'generated_by' => $user->name,
            'filters' => $filters,
        ];

        return $format === 'pdf' ? $this->pdf($document) : $this->xlsx($document);
    }

    public function pdf(array $document): array
    {
        $html = View::make('exports.report-pdf', $document)->render();
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', count($document['columns']) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 90,
            $canvas->get_height() - 28,
            'Hal. {PAGE_NUM} / {PAGE_COUNT}',
            null,
            9,
            [0.35, 0.35, 0.35]
        );
        $contents = $dompdf->output();
        $file = 'laporan-'.$document['type'].'-'.$document['generated_at']->format('Ymd-His').'.pdf';

        return ['contents' => $contents, 'file_name' => $file, 'mime_type' => 'application/pdf'];
    }

    public function xlsx(array $document): array
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr($document['title'], 0, 31));
        $row = 1;
        $sheet->setCellValueExplicit('A'.$row, $this->safeText($document['store']->name), DataType::TYPE_STRING);
        $sheet->mergeCells('A'.$row.':'.Coordinate::stringFromColumnIndex(count($document['columns'])).$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;
        foreach ([$document['title'], 'Periode '.$document['period']['from_date'].' s/d '.$document['period']['to_date'].' (Asia/Jakarta)', 'Dibuat '.$document['generated_at']->format('d/m/Y H:i').' oleh '.$document['generated_by']] as $line) {
            $sheet->setCellValueExplicit('A'.$row, $this->safeText($line), DataType::TYPE_STRING);
            $row++;
        }
        if ($document['summary'] !== []) {
            $row++;
            $sheet->setCellValueExplicit('A'.$row, 'Ringkasan', DataType::TYPE_STRING);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
            foreach ($document['summary'] as $key => $value) {
                $sheet->setCellValueExplicit('A'.$row, $this->safeText($document['summary_labels'][$key] ?? $key), DataType::TYPE_STRING);
                $this->writeValue($sheet, 'B'.$row, $value, $key);
                $row++;
            }
        }
        $row++;
        foreach ($document['columns'] as $idx => $column) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($idx + 1).$row, $this->safeText($column['label']), DataType::TYPE_STRING);
        }
        $sheet->getStyle('A'.$row.':'.Coordinate::stringFromColumnIndex(count($document['columns'])).$row)->getFont()->setBold(true);
        $row++;
        foreach ($document['rows'] as $dataRow) {
            foreach ($document['columns'] as $idx => $column) {
                $this->writeValue($sheet, Coordinate::stringFromColumnIndex($idx + 1).$row, $dataRow[$column['key']] ?? null, $column['key']);
            }
            $row++;
        }
        for ($col = 1; $col <= count($document['columns']); $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
        $sheet->freezePane('A'.($row - count($document['rows'])));
        $writer = new Xlsx($book);
        ob_start();
        $writer->save('php://output');
        $contents = ob_get_clean();
        $file = 'laporan-'.$document['type'].'-'.$document['generated_at']->format('Ymd-His').'.xlsx';

        return ['contents' => $contents, 'file_name' => $file, 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    }

    private function writeValue($sheet, string $cell, mixed $value, string $key): void
    {
        if ($value !== null && $value !== '' && $this->isDateKey($key)) {
            $sheet->setCellValue($cell, Date::PHPToExcel(Carbon::parse($value)));
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');

            return;
        }
        if (is_int($value) || is_float($value) || is_numeric($value)) {
            $numeric = is_string($value) ? (float) Decimal::of($value, 2)->toString() : (float) $value;
            $sheet->setCellValueExplicit($cell, $numeric, DataType::TYPE_NUMERIC);

            return;
        }
        $sheet->setCellValueExplicit($cell, $this->safeText((string) $value), DataType::TYPE_STRING);
    }

    private function isDateKey(string $key): bool
    {
        return str_contains($key, 'date') || str_ends_with($key, '_at');
    }

    public function safeText(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function title(string $type): string
    {
        return match ($type) {
            'sales' => 'Laporan Penjualan',
            'purchases' => 'Laporan Pembelian',
            'returns' => 'Laporan Retur',
            'expenses' => 'Laporan Biaya Operasional',
            'inventory' => 'Laporan Persediaan',
            'profit-loss' => 'Laba Rugi Sederhana / Ringkasan Hasil Operasional',
            'cash-flow' => 'Laporan Arus Kas',
            'stock-opnames' => 'Laporan Stok Opname',
            'product-performance' => 'Performa Produk',
            'inventory-valuation' => 'Valuasi Persediaan Historis',
            default => 'Laporan',
        };
    }
}
