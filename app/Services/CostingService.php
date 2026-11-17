<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Support\Decimal;
use Carbon\Carbon;
use InvalidArgumentException;

class CostingService
{
    /**
     * Calculate new Moving Weighted Average Cost using decimal-safe arithmetic.
     * Formula: new_average_cost = ((Q × C) + (q_in × c_in)) / (Q + q_in)
     *
     * @param  string|Decimal  $currentQty  On-hand quantity before movement (Q)
     * @param  string|Decimal  $currentCost  Current average cost (C)
     * @param  string|Decimal  $incomingQty  Incoming quantity (q_in)
     * @param  string|Decimal  $incomingCost  Landed unit cost of incoming goods (c_in)
     * @return Decimal New average unit cost rounded to 2 decimal places
     */
    public function calculateMovingAverage(
        string|Decimal $currentQty,
        string|Decimal $currentCost,
        string|Decimal $incomingQty,
        string|Decimal $incomingCost
    ): Decimal {
        $q = Decimal::of($currentQty, 3);
        $c = Decimal::of($currentCost, 2);
        $qIn = Decimal::of($incomingQty, 3);
        $cIn = Decimal::of($incomingCost, 2);

        if ($qIn->isNegative()) {
            throw new InvalidArgumentException('Incoming quantity cannot be negative.');
        }

        if ($cIn->isNegative()) {
            throw new InvalidArgumentException('Incoming landed unit cost cannot be negative.');
        }

        // If no incoming stock, return current cost
        if ($qIn->isZero()) {
            return $c;
        }

        // If current stock is zero or negative, new cost is simply incoming landed cost
        if ($q->isZero() || $q->isNegative()) {
            return $cIn;
        }

        // Total existing value: Q * C (scale 4 to retain intermediate precision)
        $existingValue = $q->mul($c, 4);

        // Total incoming value: q_in * c_in
        $incomingValue = $qIn->mul($cIn, 4);

        // Combined value
        $totalValue = $existingValue->add($incomingValue);

        // Combined quantity: Q + q_in
        $totalQty = $q->add($qIn);

        if ($totalQty->isZero()) {
            return Decimal::zero(2);
        }

        // Divide total value by total quantity, rounding to scale 2
        return $totalValue->div($totalQty, 2);
    }

    /**
     * Record receipt cost update and log cost history.
     *
     * @return Decimal The updated average cost
     */
    public function recordReceiptCost(
        Product|int $product,
        string|Decimal $qtyIn,
        string|Decimal $landedUnitCost,
        string $refType,
        ?int $refId = null,
        ?string $refNumber = null,
        ?int $userId = null,
        ?string $reason = null
    ): Decimal {
        $productId = $product instanceof Product ? $product->id : $product;
        $balance = InventoryBalance::firstOrCreate(
            ['product_id' => $productId],
            [
                'quantity_on_hand' => '0.000',
                'quantity_reserved' => '0.000',
                'average_cost' => '0.00',
            ]
        );

        $qtyBefore = Decimal::of($balance->quantity_on_hand, 3);
        $costBefore = Decimal::of($balance->average_cost, 2);
        $qtyIncoming = Decimal::of($qtyIn, 3);
        $unitCostIncoming = Decimal::of($landedUnitCost, 2);

        $newCost = $this->calculateMovingAverage($qtyBefore, $costBefore, $qtyIncoming, $unitCostIncoming);
        $qtyAfter = $qtyBefore->add($qtyIncoming);

        // Update balance
        $balance->average_cost = $newCost->toString();
        $balance->save();

        // Record history
        ProductCostHistory::create([
            'product_id' => $productId,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'reference_number' => $refNumber,
            'qty_before' => $qtyBefore->toString(),
            'cost_before' => $costBefore->toString(),
            'qty_after' => $qtyAfter->toString(),
            'cost_after' => $newCost->toString(),
            'reason' => $reason ?? 'Penerimaan barang (receipt)',
            'effective_at' => Carbon::now('Asia/Jakarta'),
            'created_by' => $userId,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return $newCost;
    }

    /**
     * Record cost snapshot for a sale.
     * When remaining quantity becomes 0, current average cost is reset to 0
     * while cost history is retained per PRD §8.3.
     *
     * @return Decimal The cost snapshot used for COGS
     */
    public function recordSaleCostSnapshot(
        Product|int $product,
        string|Decimal $qtySold,
        string $refType,
        ?int $refId = null,
        ?string $refNumber = null,
        ?int $userId = null
    ): Decimal {
        $productId = $product instanceof Product ? $product->id : $product;
        $balance = InventoryBalance::where('product_id', $productId)->first();

        $costSnapshot = $balance ? Decimal::of($balance->average_cost, 2) : Decimal::zero(2);
        $qtyBefore = $balance ? Decimal::of($balance->quantity_on_hand, 3) : Decimal::zero(3);
        $qtyOut = Decimal::of($qtySold, 3);
        $qtyAfter = $qtyBefore->sub($qtyOut);

        // If stock reaches zero, reset average cost in balance to 0 per PRD §8.3
        if ($balance && $qtyAfter->isZero()) {
            $balance->average_cost = '0.00';
            $balance->save();

            ProductCostHistory::create([
                'product_id' => $productId,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_number' => $refNumber,
                'qty_before' => $qtyBefore->toString(),
                'cost_before' => $costSnapshot->toString(),
                'qty_after' => '0.000',
                'cost_after' => '0.00',
                'reason' => 'Saldo menjadi nol setelah penjualan',
                'effective_at' => Carbon::now('Asia/Jakarta'),
                'created_by' => $userId,
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }

        return $costSnapshot;
    }

    /**
     * Record resellable return recalculation with historical return cost.
     * Formula:
     * Q_new = Q_current + r
     * C_new = ((Q_current × C_current) + (r × historical_return_cost)) / Q_new
     *
     * @return Decimal The new average cost
     */
    public function recordResellableReturnCost(
        Product|int $product,
        string|Decimal $qtyReturn,
        string|Decimal $historicalCost,
        string $refType,
        ?int $refId = null,
        ?string $refNumber = null,
        ?int $userId = null,
        ?string $reason = null
    ): Decimal {
        $productId = $product instanceof Product ? $product->id : $product;
        $balance = InventoryBalance::firstOrCreate(
            ['product_id' => $productId],
            [
                'quantity_on_hand' => '0.000',
                'quantity_reserved' => '0.000',
                'average_cost' => '0.00',
            ]
        );

        $qtyBefore = Decimal::of($balance->quantity_on_hand, 3);
        $costBefore = Decimal::of($balance->average_cost, 2);
        $r = Decimal::of($qtyReturn, 3);
        $histCost = Decimal::of($historicalCost, 2);

        $newCost = $this->calculateMovingAverage($qtyBefore, $costBefore, $r, $histCost);
        $qtyAfter = $qtyBefore->add($r);

        $balance->average_cost = $newCost->toString();
        $balance->save();

        ProductCostHistory::create([
            'product_id' => $productId,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'reference_number' => $refNumber,
            'qty_before' => $qtyBefore->toString(),
            'cost_before' => $costBefore->toString(),
            'qty_after' => $qtyAfter->toString(),
            'cost_after' => $newCost->toString(),
            'reason' => $reason ?? 'Retur penjualan barang layak jual (resellable return)',
            'effective_at' => Carbon::now('Asia/Jakarta'),
            'created_by' => $userId,
            'created_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return $newCost;
    }
}
