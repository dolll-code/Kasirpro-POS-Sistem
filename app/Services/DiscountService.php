<?php

namespace App\Services;

use App\Exceptions\DiscountApprovalRequiredException;
use App\Models\User;
use App\Support\Decimal;
use App\Support\MoneyCalculator;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class DiscountService
{
    /**
     * Calculate line discount amount from type and value.
     *
     * @param  string  $type  ('percent' or 'amount')
     * @param  string|float|int  $value
     * @param  Decimal  $grossLineTotal
     */
    public function calculateLineDiscount(string $type, string|float|int $value, Decimal $grossLineTotal): Decimal
    {
        $val = Decimal::of($value, 4);

        if ($val->isNegative()) {
            throw new InvalidArgumentException('Nilai diskon tidak boleh bernilai negatif.');
        }

        if ($type === 'percent') {
            if ($val->isGreaterThan(Decimal::of('100', 4))) {
                throw new InvalidArgumentException('Persentase diskon tidak boleh melebihi 100%.');
            }

            // gross * (percent / 100)
            $rate = $val->div(Decimal::of('100', 4), 6);
            $amount = $grossLineTotal->mul($rate, 2);

            return $amount->isGreaterThan($grossLineTotal) ? $grossLineTotal : $amount;
        }

        if ($type === 'amount') {
            $amt = Decimal::of($value, 2);

            return $amt->isGreaterThan($grossLineTotal) ? $grossLineTotal : $amt;
        }

        throw new InvalidArgumentException("Tipe diskon [{$type}] tidak didukung. Gunakan 'percent' atau 'amount'.");
    }

    /**
     * Calculate transaction-level discount amount from type and value.
     *
     * @param  string  $type  ('percent' or 'amount')
     * @param  string|float|int  $value
     * @param  Decimal  $preTransactionNetTotal  Sum of item nets before transaction discount
     */
    public function calculateTransactionDiscount(string $type, string|float|int $value, Decimal $preTransactionNetTotal): Decimal
    {
        if ($preTransactionNetTotal->isZero() || $preTransactionNetTotal->isNegative()) {
            return Decimal::zero(2);
        }

        $val = Decimal::of($value, 4);

        if ($val->isNegative()) {
            throw new InvalidArgumentException('Nilai diskon transaksi tidak boleh bernilai negatif.');
        }

        if ($type === 'percent') {
            if ($val->isGreaterThan(Decimal::of('100', 4))) {
                throw new InvalidArgumentException('Persentase diskon transaksi tidak boleh melebihi 100%.');
            }

            $rate = $val->div(Decimal::of('100', 4), 6);
            $amount = $preTransactionNetTotal->mul($rate, 2);

            return $amount->isGreaterThan($preTransactionNetTotal) ? $preTransactionNetTotal : $amount;
        }

        if ($type === 'amount') {
            $amt = Decimal::of($value, 2);

            return $amt->isGreaterThan($preTransactionNetTotal) ? $preTransactionNetTotal : $amt;
        }

        throw new InvalidArgumentException("Tipe diskon transaksi [{$type}] tidak didukung.");
    }

    /**
     * Allocate transaction discount proportionally across items with deterministic residual rounding (PRD §8.2).
     *
     * @param  Decimal  $transactionDiscount
     * @param  array<int|string, string>  $lineBases  Array of preNet totals keyed by line index
     * @return array<int|string, string> Array of allocated discount amounts
     */
    public function allocateTransactionDiscount(Decimal $transactionDiscount, array $lineBases): array
    {
        if ($transactionDiscount->isZero()) {
            $allocated = [];
            foreach ($lineBases as $key => $basis) {
                $allocated[$key] = '0.00';
            }

            return $allocated;
        }

        return MoneyCalculator::allocateProportional($transactionDiscount->toString(), $lineBases);
    }

    /**
     * Check discount limits and validate approval / maker-checker (PRD §4.2, §5.6).
     *
     * @param  User  $cashier
     * @param  array  $lineDiscounts  Array of ['type' => string, 'value' => string|float, 'amount' => Decimal, 'gross' => Decimal]
     * @param  array  $transactionDiscount  ['type' => string, 'value' => string|float, 'amount' => Decimal]
     * @param  array|null  $approvalData  ['approver_id' => int, 'approver_password' => string, 'reason' => ?string]
     * @return User|null The approved user, if approval was required and verified
     *
     * @throws DiscountApprovalRequiredException
     */
    public function validateDiscountLimitsAndApproval(
        User $cashier,
        array $lineDiscounts,
        array $transactionDiscount,
        ?array $approvalData = null
    ): ?User {
        $limits = config('pos.discount_limits', [
            'max_cashier_item_discount_percent' => 10.0,
            'max_cashier_item_discount_amount' => 50000.00,
            'max_cashier_transaction_discount_percent' => 10.0,
            'max_cashier_transaction_discount_amount' => 100000.00,
        ]);

        $exceeded = false;
        $exceededReason = '';

        // 1. Check line discounts
        foreach ($lineDiscounts as $ld) {
            $type = $ld['type'] ?? 'amount';
            $val = (float) ($ld['value'] ?? 0);
            $amt = $ld['amount'] instanceof Decimal ? (float) $ld['amount']->toString() : (float) $ld['amount'];

            if ($type === 'percent' && $val > $limits['max_cashier_item_discount_percent']) {
                $exceeded = true;
                $exceededReason = "Diskon item {$val}% melebihi batas wewenang kasir ({$limits['max_cashier_item_discount_percent']}%).";
                break;
            }

            if ($type === 'amount' && $amt > $limits['max_cashier_item_discount_amount']) {
                $exceeded = true;
                $exceededReason = 'Diskon item Rp'.number_format($amt, 0, ',', '.')." melebihi batas wewenang kasir (Rp".number_format($limits['max_cashier_item_discount_amount'], 0, ',', '.').').';
                break;
            }
        }

        // 2. Check transaction discount
        if (! $exceeded) {
            $txType = $transactionDiscount['type'] ?? 'amount';
            $txVal = (float) ($transactionDiscount['value'] ?? 0);
            $txAmt = $transactionDiscount['amount'] instanceof Decimal ? (float) $transactionDiscount['amount']->toString() : (float) $transactionDiscount['amount'];

            if ($txType === 'percent' && $txVal > $limits['max_cashier_transaction_discount_percent']) {
                $exceeded = true;
                $exceededReason = "Diskon transaksi {$txVal}% melebihi batas wewenang kasir ({$limits['max_cashier_transaction_discount_percent']}%).";
            } elseif ($txType === 'amount' && $txAmt > $limits['max_cashier_transaction_discount_amount']) {
                $exceeded = true;
                $exceededReason = 'Diskon transaksi Rp'.number_format($txAmt, 0, ',', '.')." melebihi batas wewenang kasir (Rp".number_format($limits['max_cashier_transaction_discount_amount'], 0, ',', '.').').';
            }
        }

        if (! $exceeded) {
            return null; // No special approval needed
        }

        // Cashier user already has override permission (e.g. Admin or Supervisor acting as cashier)
        if ($cashier->hasPermission('sales.discount.override')) {
            return $cashier;
        }

        // Cashier only has within_limit, so approval by different supervisor/admin is required
        if (empty($approvalData) || empty($approvalData['approver_id']) || empty($approvalData['approver_password'])) {
            throw new DiscountApprovalRequiredException(
                message: "{$exceededReason} Diperlukan otorisasi supervisor/admin.",
                discountType: $transactionDiscount['type'] ?? 'amount',
                discountValue: (string) ($transactionDiscount['value'] ?? '0')
            );
        }

        // Maker-checker rule: Approver CANNOT be the cashier themselves (PRD §4.2)
        if ((int) $approvalData['approver_id'] === (int) $cashier->id) {
            throw new DiscountApprovalRequiredException(
                message: 'Prinsip Maker-Checker: Kasir dilarang menyetujui diskon khusus milik sendiri.',
                discountType: $transactionDiscount['type'] ?? 'amount',
                discountValue: (string) ($transactionDiscount['value'] ?? '0')
            );
        }

        $approver = User::find($approvalData['approver_id']);
        if (! $approver || ! $approver->is_active) {
            throw new DiscountApprovalRequiredException('Akun supervisor/admin tidak ditemukan atau sedang nonaktif.');
        }

        if (! $approver->hasPermission('sales.discount.override')) {
            throw new DiscountApprovalRequiredException('Pengguna yang dipilih tidak memiliki izin untuk memberikan override diskon.');
        }

        if (! Hash::check($approvalData['approver_password'], $approver->password)) {
            throw new DiscountApprovalRequiredException('Kata sandi supervisor/admin salah.');
        }

        return $approver;
    }
}
