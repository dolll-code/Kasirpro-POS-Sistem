<?php

namespace App\Support;

/**
 * Money and formatting utility helper for IDR currency.
 */
class MoneyCalculator
{
    /**
     * Format a decimal amount to Indonesian Rupiah representation: Rp 1.234.567,00
     */
    public static function formatIdr(string|int|float|Decimal $amount, bool $withPrefix = true): string
    {
        $decimal = $amount instanceof Decimal ? $amount : new Decimal($amount, 2);
        $floatVal = (float) $decimal->toString();

        $formatted = number_format($floatVal, 2, ',', '.');

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }

    /**
     * Format a quantity according to its decimal precision (0 to 3 decimals).
     */
    public static function formatQuantity(string|int|float|Decimal $quantity, int $precision = 0): string
    {
        $decimal = $quantity instanceof Decimal ? $quantity : new Decimal($quantity, $precision);
        $floatVal = (float) $decimal->toString();

        return number_format($floatVal, $precision, ',', '.');
    }

    /**
     * Allocate a total amount proportionally across multiple weights with deterministic residual rounding.
     *
     * @param  Decimal|string  $totalAmount  The header discount or total to distribute.
     * @param  array<int|string, Decimal|string>  $basisArray  Keyed array of basis amounts.
     * @return array<int|string, string> Keyed array of allocated string amounts summing exactly to $totalAmount.
     */
    public static function allocateProportional(Decimal|string $totalAmount, array $basisArray): array
    {
        $total = $totalAmount instanceof Decimal ? $totalAmount : new Decimal($totalAmount, 2);

        if ($total->isZero() || empty($basisArray)) {
            $zeros = [];
            foreach ($basisArray as $k => $v) {
                $zeros[$k] = '0.00';
            }

            return $zeros;
        }

        // Sum basis
        $totalBasis = Decimal::zero(2);
        foreach ($basisArray as $basis) {
            $totalBasis = $totalBasis->add($basis);
        }

        if ($totalBasis->isZero()) {
            $zeros = [];
            foreach ($basisArray as $k => $v) {
                $zeros[$k] = '0.00';
            }

            return $zeros;
        }

        $allocated = [];
        $allocatedSum = Decimal::zero(2);
        $largestKey = null;
        $largestBasis = Decimal::zero(2);

        foreach ($basisArray as $key => $basisVal) {
            $basis = $basisVal instanceof Decimal ? $basisVal : new Decimal($basisVal, 2);

            // allocated_i = floor((basis_i * total) / totalBasis)
            $raw = $basis->mul($total, 6)->div($totalBasis, 2);
            $allocated[$key] = $raw->toString();
            $allocatedSum = $allocatedSum->add($raw);

            if ($largestKey === null || $basis->isGreaterThan($largestBasis)) {
                $largestKey = $key;
                $largestBasis = $basis;
            }
        }

        // Residual difference
        $diff = $total->sub($allocatedSum);
        if (! $diff->isZero() && $largestKey !== null) {
            $allocated[$largestKey] = (new Decimal($allocated[$largestKey], 2))->add($diff)->toString();
        }

        return $allocated;
    }
}
