<?php

namespace App\Http\Controllers;

use App\Http\Requests\Purchasing\PostReceiptRequest;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Services\ReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseReceiptController extends Controller
{
    public function __construct(
        protected ReceivingService $receivingService
    ) {}

    public function create(Purchase $purchase): View|RedirectResponse
    {
        if (! $purchase->canReceive()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', "Penerimaan barang hanya dapat diproses untuk PO dengan status 'ordered' atau 'partially_received'. Status saat ini: [{$purchase->status_label}].");
        }

        $purchase->load(['items.product.unit', 'supplier']);

        return view('purchases.receipts.create', compact('purchase'));
    }

    public function store(PostReceiptRequest $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            $receipt = $this->receivingService->postReceipt($purchase, $request->validated(), auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Penerimaan barang #{$receipt->receipt_number} berhasil diposting.",
                'data' => $receipt->load(['items.product', 'purchase']),
            ], 201);
        }

        return redirect()->route('purchases.receipts.show', [$purchase, $receipt])
            ->with('success', "Penerimaan barang #{$receipt->receipt_number} berhasil diposting. Stok fisik dan HPP rata-rata telah diperbarui.");
    }

    public function show(Request $request, Purchase $purchase, PurchaseReceipt $receipt): View|JsonResponse
    {
        $receipt->load([
            'purchase.supplier',
            'items.product.unit',
            'items.purchaseItem',
            'receiver:id,name',
            'poster:id,name',
        ]);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $receipt,
            ]);
        }

        return view('purchases.receipts.show', compact('purchase', 'receipt'));
    }
}
