<?php

namespace App\Http\Controllers;

use App\Exceptions\ReservationExpiredException;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

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
                'reservation_expires_at' => $sale->pending_expires_at?->toIso8601String(),
                'receipt_url' => route('sales.receipt', $sale),
                'items_count' => $sale->items->count(),
            ],
        ]);
    }

    /**
     * Display list of pending bank transfer transactions requiring manual verification (PRD §5.5, §6.4, §7.3).
     */
    public function pendingTransfers(Request $request): View|JsonResponse
    {
        $user = $request->user();

        // Expire overdue reservations before rendering list
        $this->saleService->expireOverdueReservations();

        $query = Sale::query()
            ->where('sale_status', 'pending_payment')
            ->where('payment_status', 'pending_verification')
            ->with(['cashier:id,name', 'payment.cashAccount', 'reservation.items.product', 'items']);

        // Cashiers only see their own pending transfers unless they have verify/view_all permissions
        if (! $user->hasPermission('payments.verify_transfer') && ! $user->hasPermission('sales.view_all')) {
            $query->where('cashier_id', $user->id);
        }

        $pendingSales = $query->orderBy('pending_expires_at', 'asc')->paginate(15)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $pendingSales,
                'meta' => [
                    'pending_count' => $pendingSales->total(),
                ],
            ]);
        }

        return view('sales.pending', compact('pendingSales'));
    }

    /**
     * Manual verification decision for bank transfer (PRD §5.5, §6.4, §7.3).
     */
    public function verifyTransfer(Request $request, Sale $sale): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:verified,rejected'],
            'verification_note' => ['nullable', 'string', 'max:500'],
            'admin_override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $updatedSale = $this->saleService->verifyTransfer(
                sale: $sale,
                verifier: $request->user(),
                decision: $validated['decision'],
                note: $validated['verification_note'] ?? null,
                overrideReason: $validated['admin_override_reason'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $validated['decision'] === 'verified'
                        ? 'Pembayaran transfer bank berhasil diverifikasi dan disahkan.'
                        : 'Pembayaran transfer bank ditolak dan reservasi stok telah dirilis.',
                    'data' => [
                        'id' => $updatedSale->id,
                        'transaction_number' => $updatedSale->transaction_number,
                        'sale_status' => $updatedSale->sale_status,
                        'payment_status' => $updatedSale->payment_status,
                        'grand_total' => $updatedSale->grand_total,
                        'completed_at' => $updatedSale->completed_at?->toIso8601String(),
                    ],
                ]);
            }

            $flashMsg = $validated['decision'] === 'verified'
                ? "Transfer transaksi #{$sale->transaction_number} berhasil diverifikasi dan disahkan."
                : "Transfer transaksi #{$sale->transaction_number} telah ditolak dan reservasi stok dirilis.";

            return redirect()->route('sales.show', $sale)->with('success', $flashMsg);
        } catch (AuthorizationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => $e->getMessage(),
                    ],
                ], 403);
            }

            return back()->with('error', $e->getMessage());
        } catch (ReservationExpiredException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'RESERVATION_EXPIRED',
                        'message' => $e->getMessage(),
                    ],
                ], 409);
            }

            return back()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_STATE',
                        'message' => $e->getMessage(),
                    ],
                ], 409);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel a pending transfer sale (PRD §5.5, §6.4).
     */
    public function cancel(Request $request, Sale $sale): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        // Check permission or ownership
        if (! $user->hasPermission('payments.verify_transfer') && ! $user->hasPermission('sales.view_all') && $sale->cashier_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan transaksi transfer ini.');
        }

        $reason = $request->input('reason', 'Dibatalkan oleh kasir/pelanggan');

        try {
            $cancelled = $this->saleService->cancelPendingTransfer($sale, $user, $reason);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Transaksi transfer berhasil dibatalkan dan reservasi stok telah dirilis.',
                    'data' => [
                        'id' => $cancelled->id,
                        'sale_status' => $cancelled->sale_status,
                        'payment_status' => $cancelled->payment_status,
                    ],
                ]);
            }

            return redirect()->route('sales.show', $sale)->with('success', 'Transaksi berhasil dibatalkan.');
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_STATE',
                        'message' => $e->getMessage(),
                    ],
                ], 409);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
