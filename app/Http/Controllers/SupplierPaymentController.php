<?php

namespace App\Http\Controllers;

use App\Exceptions\InactiveCashAccountException;
use App\Exceptions\InsufficientCashBalanceException;
use App\Http\Requests\Purchasing\StoreSupplierPaymentRequest;
use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentService $paymentService
    ) {}

    public function create(Purchase $purchase): View|RedirectResponse
    {
        $outstanding = $purchase->outstandingPayable();
        if ($outstanding->isZero()) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', "Purchase Order #{$purchase->purchase_number} tidak memiliki sisa utang barang diterima untuk dibayar.");
        }

        $cashAccounts = CashAccount::where('is_active', true)
            ->orderBy('account_type')
            ->orderBy('name')
            ->get();

        return view('purchases.payments.create', compact('purchase', 'outstanding', 'cashAccounts'));
    }

    public function store(StoreSupplierPaymentRequest $request, Purchase $purchase): RedirectResponse|JsonResponse
    {
        try {
            $payment = $this->paymentService->recordPayment($purchase, $request->validated(), auth()->id());
        } catch (InactiveCashAccountException|InsufficientCashBalanceException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Pembayaran supplier #{$payment->payment_number} berhasil dicatat.",
                'data' => $payment->load(['cashAccount', 'cashMovement']),
            ], 201);
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Pembayaran pemasok #{$payment->payment_number} sebesar Rp ".number_format((float) $payment->amount, 2, ',', '.').' berhasil dicatat dan memotong saldo kas.');
    }

    public function reverse(Request $request, Purchase $purchase, SupplierPayment $payment): RedirectResponse|JsonResponse
    {
        if (! auth()->user()->hasPermission('supplier_payments.reverse')) {
            abort(403, 'Anda tidak memiliki wewenang untuk membalikkan (reversal) pembayaran supplier.');
        }

        $request->validate([
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'Alasan pembatalan/pembalikan pembayaran wajib diisi.',
        ]);

        try {
            $reversed = $this->paymentService->reversePayment($payment, $request->input('reason'), auth()->id());
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->is('api/*')) {
                return response()->json(['error' => ['message' => $e->getMessage()]], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => "Pembalikan pembayaran #{$reversed->payment_number} berhasil diposting.",
                'data' => $reversed,
            ]);
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Pembalikan pembayaran supplier #{$reversed->payment_number} berhasil. Saldo kas telah dikembalikan.");
    }
}
