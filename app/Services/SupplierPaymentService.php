<?php

namespace App\Services;

use App\Exceptions\InactiveCashAccountException;
use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\SupplierPayment;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierPaymentService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected CashMovementService $cashMovementService
    ) {}

    /**
     * Record a supplier payment against outstanding payable from accepted receipts.
     *
     * @param  array{
     *     cash_account_id: int,
     *     method: string,
     *     amount: string|float|int,
     *     reference_number?: string|null,
     *     paid_at?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function recordPayment(Purchase|int $purchase, array $data, ?int $userId = null): SupplierPayment
    {
        return DB::transaction(function () use ($purchase, $data, $userId) {
            $po = $purchase instanceof Purchase ? $purchase : Purchase::findOrFail($purchase);

            // Lock PO
            $po = Purchase::where('id', $po->id)->lockForUpdate()->firstOrFail();

            $cashAccountId = (int) $data['cash_account_id'];
            $account = CashAccount::findOrFail($cashAccountId);
            if (! $account->is_active) {
                throw new InactiveCashAccountException(
                    message: "Akun kas/bank [{$account->name}] berstatus nonaktif dan tidak dapat digunakan untuk pembayaran pemasok.",
                    cashAccountId: $account->id
                );
            }

            $amount = Decimal::of($data['amount'] ?? '0', 2);
            if ($amount->isNegative() || $amount->isZero()) {
                throw new InvalidArgumentException('Jumlah pembayaran harus bernilai positif (> 0).');
            }

            $outstanding = $po->outstandingPayable();
            if ($outstanding->isZero()) {
                throw new InvalidArgumentException("Purchase Order #{$po->purchase_number} tidak memiliki sisa utang barang diterima.");
            }

            if ($amount->isGreaterThan($outstanding)) {
                throw new InvalidArgumentException(
                    "Jumlah pembayaran [{$amount}] melebihi sisa utang yang terbentuk dari penerimaan barang [{$outstanding}]."
                );
            }

            $paidAt = ! empty($data['paid_at'])
                ? Carbon::parse($data['paid_at'], 'Asia/Jakarta')
                : Carbon::now('Asia/Jakarta');

            $method = $data['method'] ?? 'cash';
            if (! in_array($method, ['cash', 'bank_transfer'])) {
                throw new InvalidArgumentException("Metode pembayaran [{$method}] tidak valid. Pilih 'cash' atau 'bank_transfer'.");
            }

            $paymentNumber = $this->sequenceService->generate('PAY');

            $payment = SupplierPayment::create([
                'payment_number' => $paymentNumber,
                'purchase_id' => $po->id,
                'cash_account_id' => $account->id,
                'method' => $method,
                'amount' => $amount->toString(),
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Create cash movement out atomically
            $cashMovement = $this->cashMovementService->recordOutflow(
                account: $account,
                amount: $amount,
                movementType: 'SUPPLIER_PAYMENT',
                sourceType: 'supplier_payments',
                sourceId: $payment->id,
                sourceNumber: $payment->payment_number,
                description: "Pembayaran pemasok PO #{$po->purchase_number}",
                userId: $userId,
                occurredAt: $paidAt
            );

            $payment->cash_movement_id = $cashMovement->id;
            $payment->save();

            // Update PO paid_amount and payment_status
            $currentPaid = Decimal::of($po->paid_amount ?? '0', 2);
            $newPaid = $currentPaid->add($amount);
            $po->paid_amount = $newPaid->toString();

            $totalPayable = $po->totalAcceptedPayable();
            if ($newPaid->isGreaterThanOrEqualTo($totalPayable)) {
                $po->payment_status = 'paid';
            } else {
                $po->payment_status = 'partially_paid';
            }
            $po->save();

            AuditLogger::log(
                action: 'supplier_payment.created',
                module: 'purchases',
                reference: $payment,
                newValues: [
                    'payment_number' => $payment->payment_number,
                    'purchase_number' => $po->purchase_number,
                    'amount' => $payment->amount,
                    'cash_account_id' => $payment->cash_account_id,
                    'new_payment_status' => $po->payment_status,
                ]
            );

            return $payment->fresh(['cashAccount', 'cashMovement', 'purchase']);
        });
    }

    /**
     * Reverse an existing supplier payment.
     */
    public function reversePayment(SupplierPayment|int $payment, string $reason, ?int $userId = null): SupplierPayment
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            if (empty(trim($reason))) {
                throw new InvalidArgumentException('Alasan pembalikan pembayaran supplier wajib diisi.');
            }

            $pay = $payment instanceof SupplierPayment ? $payment : SupplierPayment::findOrFail($payment);
            $pay = SupplierPayment::where('id', $pay->id)->lockForUpdate()->firstOrFail();

            if ($pay->isReversed()) {
                throw new InvalidArgumentException("Pembayaran supplier #{$pay->payment_number} sudah pernah dibalikkan (reversal).");
            }

            // Lock PO
            $po = Purchase::where('id', $pay->purchase_id)->lockForUpdate()->firstOrFail();

            // Reverse the cash movement (out -> in)
            $this->cashMovementService->reverseMovement(
                movement: $pay->cash_movement_id,
                reason: "Pembalikan pembayaran supplier #{$pay->payment_number}: {$reason}",
                userId: $userId
            );

            $pay->reversed_by = $userId;
            $pay->reversal_reason = trim($reason);
            $pay->save();

            // Deduct paid amount from PO
            $currentPaid = Decimal::of($po->paid_amount ?? '0', 2);
            $payAmt = Decimal::of($pay->amount, 2);
            $newPaid = $currentPaid->sub($payAmt);

            $po->paid_amount = $newPaid->isNegative() ? '0.00' : $newPaid->toString();

            $totalPayable = $po->totalAcceptedPayable();
            if ($newPaid->isZero()) {
                $po->payment_status = 'unpaid';
            } elseif ($newPaid->isGreaterThanOrEqualTo($totalPayable)) {
                $po->payment_status = 'paid';
            } else {
                $po->payment_status = 'partially_paid';
            }
            $po->save();

            AuditLogger::log(
                action: 'supplier_payment.reversed',
                module: 'purchases',
                reference: $pay,
                newValues: [
                    'payment_number' => $pay->payment_number,
                    'reversed_by' => $userId,
                    'reason' => $reason,
                    'new_po_payment_status' => $po->payment_status,
                ]
            );

            return $pay->fresh(['cashAccount', 'purchase']);
        });
    }
}
