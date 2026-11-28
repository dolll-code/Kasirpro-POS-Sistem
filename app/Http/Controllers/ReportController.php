<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = $this->reportService->dashboard($request->user());

        return $request->wantsJson() ? response()->json(['data' => $payload]) : view('reports.dashboard', $payload);
    }

    public function show(Request $request, string $type): View|JsonResponse
    {
        $payload = $this->payload($request, $type);

        return $request->wantsJson() ? response()->json(['data' => $payload]) : view('reports.show', ['type' => $type, 'payload' => $payload, 'title' => $this->title($type)]);
    }

    public function report(Request $request, string $type): JsonResponse
    {
        return response()->json(['data' => $this->payload($request, $type)]);
    }

    private function payload(Request $request, string $type): array
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        return match ($type) {
            'sales' => $this->reportService->salesReport($request->user(), $from, $to, $perPage, (string) $request->input('sort', 'completed_at'), (string) $request->input('dir', 'desc')),
            'purchases' => $this->reportService->purchaseReport($from, $to, $perPage),
            'returns' => $this->reportService->returnReport($from, $to, $perPage),
            'expenses' => $this->reportService->expenseReport($from, $to, $perPage),
            'inventory' => $this->reportService->inventoryReport($from, $to, $perPage),
            'profit-loss' => $this->reportService->profitLoss($from, $to, $request->input('compare_from'), $request->input('compare_to')),
            'cash-flow' => $this->reportService->cashFlow($from, $to, $perPage),
            'stock-opnames' => $this->reportService->stockOpnameReport($from, $to, $perPage),
            'product-performance' => $this->reportService->productPerformance($from, $to, $perPage),
            default => abort(404, 'Jenis laporan tidak dikenal.'),
        };
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
            default => 'Laporan',
        };
    }
}
