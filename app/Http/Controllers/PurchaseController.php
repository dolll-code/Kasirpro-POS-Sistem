<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\StorePurchaseRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\PurchaseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected AuditLogger $auditLogger
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $supplierId = $request->input('supplier_id');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $query = Purchase::with(['supplier:id,name,supplier_code', 'creator:id,name'])
            ->withCount('items');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('purchase_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('supplier_code', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($paymentStatus)) {
            $query->where('payment_status', $paymentStatus);
        }

        if (! empty($supplierId)) {
            $query->where('supplier_id', $supplierId);
        }

        if (! empty($fromDate)) {
            $query->whereDate('order_date', '>=', Carbon::parse($fromDate, 'Asia/Jakarta'));
        }

        if (! empty($toDate)) {
            $query->whereDate('order_date', '<=', Carbon::parse($toDate, 'Asia/Jakarta'));
        }

        $purchases = $query->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Summary KPIs
        $totalPurchases = Purchase::count();
        $totalDraft = Purchase::where('status', 'draft')->count();
        $totalOrdered = Purchase::where('status', 'ordered')->count();
        $totalPartiallyReceived = Purchase::where('status', 'partially_received')->count();
        $totalReceived = Purchase::where('status', 'received')->count();

        $activeSuppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'supplier_code']);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $purchases->items(),
                'meta' => [
                    'page' => $purchases->currentPage(),
                    'per_page' => $purchases->perPage(),
                    'total' => $purchases->total(),
                    'summary' => [
                        'total_purchases' => $totalPurchases,
                        'total_draft' => $totalDraft,
                        'total_ordered' => $totalOrdered,
                        'total_partially_received' => $totalPartiallyReceived,
                        'total_received' => $totalReceived,
                    ],
                ],
            ]);
        }

        return view('purchases.index', compact(
            'purchases',
            'search',
            'status',
            'paymentStatus',
            'supplierId',
            'fromDate',
            'toDate',
            'activeSuppliers',
            'totalPurchases',
            'totalDraft',
            'totalOrdered',
            'totalPartiallyReceived',
            'totalReceived'
        ));
    }

    public function create(): View
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::with('unit')->where('is_active', true)->orderBy('name')->get();

        return view('purchases.create', compact('suppliers', 'products'));
    }

    public function store(StorePurchaseRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $purchase = $this->purchaseService->createDraft($request->validated(), auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Purchase Order #{$purchase->purchase_number} berhasil dibuat dalam status draft.",
                'data' => $purchase,
            ], 201);
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Purchase Order #{$purchase->purchase_number} berhasil dibuat sebagai draft.");
    }

    public function show(Request $request, Purchase $purchase): View|JsonResponse
    {
        $purchase->load([
            'supplier',
            'items.product.unit',
            'receipts.receiver:id,name',
            'receipts.poster:id,name',
            'receipts.items.product',
            'payments.cashAccount:id,name,code',
            'payments.cashMovement:id,direction,amount',
            'payments.creator:id,name',
            'creator:id,name',
            'confirmer:id,name',
            'canceller:id,name',
        ]);

        $totalAcceptedPayable = $purchase->totalAcceptedPayable();
        $outstandingPayable = $purchase->outstandingPayable();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $purchase,
                'meta' => [
                    'total_accepted_payable' => $totalAcceptedPayable->toString(),
                    'outstanding_payable' => $outstandingPayable->toString(),
                ],
            ]);
        }

        return view('purchases.show', compact('purchase', 'totalAcceptedPayable', 'outstandingPayable'));
    }

    public function edit(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->canEdit()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', "Purchase Order #{$purchase->purchase_number} dengan status [{$purchase->status_label}] tidak dapat diedit langsung.");
        }

        $purchase->load(['items.product.unit', 'supplier']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $products = Product::with('unit')->where('is_active', true)->orderBy('name')->get();

        return view('purchases.edit', compact('purchase', 'suppliers', 'products'));
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->purchaseService->updateDraft($purchase, $request->validated(), auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Purchase Order #{$updated->purchase_number} berhasil diperbarui.",
                'data' => $updated,
            ]);
        }

        return redirect()->route('purchases.show', $updated)
            ->with('success', "Draft Purchase Order #{$updated->purchase_number} berhasil diperbarui.");
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        if (! $purchase->isDraft()) {
            $msg = 'Hanya Purchase Order berstatus draft yang dapat dihapus.';
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $msg]], 409);
            }

            return back()->with('error', $msg);
        }

        $poNumber = $purchase->purchase_number;
        $purchase->delete();

        AuditLogger::log(
            action: 'purchase.deleted',
            module: 'purchases',
            reference: $purchase,
            oldValues: ['purchase_number' => $poNumber]
        );

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json(['message' => "Draft Purchase Order #{$poNumber} berhasil dihapus."]);
        }

        return redirect()->route('purchases.index')
            ->with('success', "Draft Purchase Order #{$poNumber} berhasil dihapus.");
    }

    public function confirm(Request $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        if (! auth()->user()->hasPermission('purchases.confirm')) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengonfirmasi Purchase Order.');
        }

        try {
            $confirmed = $this->purchaseService->confirm($purchase, auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Purchase Order #{$confirmed->purchase_number} berhasil dikonfirmasi menjadi 'ordered'.",
                'data' => $confirmed,
            ]);
        }

        return redirect()->route('purchases.show', $confirmed)
            ->with('success', "Purchase Order #{$confirmed->purchase_number} telah disahkan dan berstatus 'Dipesan'.");
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        if (! auth()->user()->hasPermission('purchases.cancel')) {
            abort(403, 'Anda tidak memiliki hak akses untuk membatalkan Purchase Order.');
        }

        $request->validate([
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'Alasan pembatalan/penutupan pesanan wajib diisi.',
        ]);

        try {
            $cancelled = $this->purchaseService->cancel($purchase, $request->input('reason'), auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Purchase Order #{$cancelled->purchase_number} berhasil dibatalkan.",
                'data' => $cancelled,
            ]);
        }

        return redirect()->route('purchases.show', $cancelled)
            ->with('success', "Purchase Order #{$cancelled->purchase_number} berhasil ditutup/dibatalkan.");
    }
}
