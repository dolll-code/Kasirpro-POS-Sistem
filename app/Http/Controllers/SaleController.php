<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    /**
     * Display sales history (PRD §5.5, §6.4).
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $canViewAll = $user->hasPermission('sales.view_all');

        $query = Sale::query()->with(['cashier:id,name', 'payment.cashAccount', 'items']);

        // Scope to own sales if cashier doesn't have sales.view_all (PRD §4.2)
        if (! $canViewAll) {
            $query->where('cashier_id', $user->id);
        } elseif ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->input('cashier_id'));
        }

        // Filters
        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where('transaction_number', 'like', "%{$term}%");
        }

        if ($request->filled('start_date')) {
            $query->whereDate('completed_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('completed_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        // KPI statistics calculation (for today / filtered)
        $today = Carbon::today('Asia/Jakarta');
        $todayStatsQuery = Sale::query()->where('sale_status', 'completed');
        if (! $canViewAll) {
            $todayStatsQuery->where('cashier_id', $user->id);
        }
        $todayStats = (clone $todayStatsQuery)->whereDate('completed_at', $today)->selectRaw('
            COUNT(*) as trx_count,
            COALESCE(SUM(grand_total), 0) as total_revenue,
            COALESCE(SUM(item_discount_total + transaction_discount_total), 0) as total_discount
        ')->first();

        $sales = $query->orderByDesc('completed_at')->paginate(15)->withQueryString();
        $cashiers = $canViewAll ? User::orderBy('name')->get(['id', 'name']) : collect();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $sales,
                'meta' => [
                    'today_revenue' => $todayStats->total_revenue ?? '0.00',
                    'today_transactions' => $todayStats->trx_count ?? 0,
                ],
            ]);
        }

        return view('sales.index', compact('sales', 'cashiers', 'todayStats', 'canViewAll'));
    }

    /**
     * Display sale details with items and payment breakdown.
     */
    public function show(Request $request, Sale $sale): View|JsonResponse
    {
        $user = $request->user();

        // IDOR resource ownership enforcement (PRD §4.2)
        if (! $user->hasPermission('sales.view_all') && $sale->cashier_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk melihat transaksi penjualan kasir lain.');
        }

        $sale->load(['items.product.unit', 'payment.cashAccount', 'cashier', 'discountApprover']);

        if ($request->wantsJson()) {
            return response()->json(['data' => $sale]);
        }

        return view('sales.show', compact('sale'));
    }

    /**
     * Display thermal receipt view and printable receipt (PRD §5.5, §6.5).
     */
    public function receipt(Request $request, Sale $sale): View
    {
        $user = $request->user();

        // Ownership enforcement
        if (! $user->hasPermission('sales.view_all') && $sale->cashier_id !== $user->id) {
            abort(403, 'Anda tidak memiliki izin mencetak struk transaksi kasir lain.');
        }

        $sale->load(['items', 'payment.cashAccount', 'cashier']);

        $width = $request->input('width', $sale->store_snapshot['receipt_width'] ?? '58');
        if (! in_array($width, ['58', '80'])) {
            $width = '58';
        }

        $isReprint = $request->boolean('reprint', false);

        return view('sales.receipt', compact('sale', 'width', 'isReprint'));
    }

    /**
     * Look up sale by idempotency key (PRD §6.4, §15.4, §17.2).
     * Used when browser encounters timeout or disconnection after submit.
     */
    public function checkByIdempotencyKey(Request $request, string $key): JsonResponse
    {
        $user = $request->user();
        $sale = Sale::where('idempotency_key', $key)->with(['items', 'payment.cashAccount', 'cashier'])->first();

        if (! $sale) {
            return response()->json([
                'error' => [
                    'code' => 'TRANSACTION_NOT_FOUND',
                    'message' => "Transaksi dengan Idempotency-Key [{$key}] tidak ditemukan.",
                ],
            ], 404);
        }

        if (! $user->hasPermission('sales.view_all') && $sale->cashier_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk memeriksa transaksi kasir lain.');
        }

        return response()->json([
            'data' => [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'sale_status' => $sale->sale_status,
                'payment_status' => $sale->payment_status,
                'gross_total' => $sale->gross_total,
                'item_discount_total' => $sale->item_discount_total,
                'transaction_discount_total' => $sale->transaction_discount_total,
                'grand_total' => $sale->grand_total,
                'cash_tendered' => $sale->payment?->cash_tendered,
                'change_amount' => $sale->payment?->change_amount,
                'completed_at' => $sale->completed_at?->toIso8601String(),
                'receipt_url' => route('sales.receipt', $sale),
                'items_count' => $sale->items->count(),
            ],
        ]);
    }
}
