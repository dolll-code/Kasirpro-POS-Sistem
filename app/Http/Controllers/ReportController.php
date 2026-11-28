<?php

namespace App\Http\Controllers;

use App\Models\ReportExport;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as FileResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected ReportExportService $exportService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $payload = $this->reportService->dashboard($request->user());
        $payload['recent_exports'] = ReportExport::where('user_id', $request->user()->id)->latest()->limit(5)->get();

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

    public function export(Request $request, string $type): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:pdf,xlsx'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', 'max:30'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);
        try {
            $export = $this->exportService->request($request->user(), $type, $data['format'], $data);
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPORT_REQUEST_INVALID', 'message' => $exception->getMessage()]], 422)
                : back()->withErrors(['export' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $export], 201)
            : redirect()->route('reports.export_status', $export)->with('success', $export->status === 'completed' ? 'File ekspor siap diunduh.' : 'Permintaan ekspor sedang diproses.');
    }

    public function exportHistory(Request $request): View|JsonResponse
    {
        $query = ReportExport::with('user:id,name');
        if (! $request->user()->hasRole('admin')) {
            $query->where('user_id', $request->user()->id);
        }
        $exports = $query->latest()->paginate(20);
        $monitoring = $request->user()->hasRole('admin') ? $this->exportService->monitoringSummary() : null;

        return $request->wantsJson()
            ? response()->json(['data' => $exports, 'monitoring' => $monitoring])
            : view('reports.exports', compact('exports', 'monitoring'));
    }

    public function exportStatus(Request $request, ReportExport $export): View|JsonResponse
    {
        $this->assertExportAccess($request, $export);

        return $request->wantsJson() ? response()->json(['data' => $export]) : view('reports.export-status', compact('export'));
    }

    public function download(Request $request, ReportExport $export)
    {
        $file = $this->exportService->download($export, $request->user());

        return FileResponse::download($file['path'], $file['file_name'], ['Content-Type' => $file['mime_type']]);
    }

    private function assertExportAccess(Request $request, ReportExport $export): void
    {
        if (! $this->exportService->canExport($request->user())) {
            abort(403, 'Tidak memiliki izin ekspor.');
        }
        if ((int) $export->user_id !== (int) $request->user()->id && ! $request->user()->hasRole('admin')) {
            abort(403, 'File ekspor ini bukan milik Anda.');
        }
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
            'inventory-valuation' => $this->reportService->inventoryValuation($request->input('as_of', $to), $perPage),
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
            'inventory-valuation' => 'Valuasi Persediaan Historis',
            default => 'Laporan',
        };
    }
}
