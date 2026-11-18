<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Support\Decimal;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService
    ) {}

    /**
     * Create a new Purchase Order in draft status.
     *
     * @param  array{
     *     supplier_id: int,
     *     order_date: string,
     *     expected_date?: string|null,
     *     document_discount_total?: string|null,
     *     additional_cost_estimate?: string|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         product_id: int,
     *         ordered_qty: string|float|int,
     *         unit_cost: string|float|int,
     *         line_discount?: string|float|int|null
     *     }>
     * }  $data
     */
    public function createDraft(array $data, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            if (! $supplier->is_active) {
                throw new InvalidArgumentException("Pemasok [{$supplier->name}] berstatus nonaktif dan tidak dapat digunakan untuk Purchase Order baru.");
            }

            if (empty($data['items']) || ! is_array($data['items'])) {
                throw new InvalidArgumentException('Purchase Order harus memiliki minimal satu item barang.');
            }

            $purchaseNumber = $this->sequenceService->generate('PO');
            $docDiscountInput = Decimal::of($data['document_discount_total'] ?? '0', 2);
            $addCostInput = Decimal::of($data['additional_cost_estimate'] ?? '0', 2);

            if ($docDiscountInput->isNegative()) {
                throw new InvalidArgumentException('Diskon dokumen tidak boleh negatif.');
            }
            if ($addCostInput->isNegative()) {
                throw new InvalidArgumentException('Estimasi biaya tambahan tidak boleh negatif.');
            }

            // Process line items and compute subtotal
            $computedLines = $this->processAndValidateItems($data['items']);

            // Validate document discount does not exceed net items subtotal
            $netItemsSubtotal = $computedLines['net_subtotal'];
            if ($docDiscountInput->isGreaterThan($netItemsSubtotal)) {
                throw new InvalidArgumentException("Diskon dokumen [{$docDiscountInput}] tidak boleh melebihi nilai subtotal setelah diskon item [{$netItemsSubtotal}].");
            }

            // Allocate document discount proportionally
            $allocatedDiscounts = MoneyCalculator::allocateProportional($docDiscountInput, $computedLines['line_bases']);

            // Calculate final payable estimate
            // payable_total = net_subtotal - document_discount_total + additional_cost_estimate
            $payableTotal = $netItemsSubtotal->sub($docDiscountInput)->add($addCostInput);

            $purchase = Purchase::create([
                'purchase_number' => $purchaseNumber,
                'supplier_id' => $supplier->id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $computedLines['gross_subtotal']->toString(),
                'item_discount_total' => $computedLines['item_discount_total']->toString(),
                'document_discount_total' => $docDiscountInput->toString(),
                'additional_cost_estimate' => $addCostInput->toString(),
                'payable_total' => $payableTotal->toString(),
                'paid_amount' => '0.00',
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // Save line items
            foreach ($computedLines['items'] as $index => $line) {
                $allocatedDoc = Decimal::of($allocatedDiscounts[$index] ?? '0.00', 2);
                $finalLineTotal = $line['basis']->sub($allocatedDoc);

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'sku_snapshot' => $line['product']->sku,
                    'unit_snapshot' => $line['product']->unit?->name ?? 'Pcs',
                    'ordered_qty' => $line['ordered_qty']->toString(),
                    'unit_cost' => $line['unit_cost']->toString(),
                    'line_discount' => $line['line_discount']->toString(),
                    'allocated_document_discount' => $allocatedDoc->toString(),
                    'accepted_qty_total' => '0.000',
                    'rejected_qty_total' => '0.000',
                    'cancelled_qty' => '0.000',
                    'line_total' => $finalLineTotal->toString(),
                ]);
            }

            AuditLogger::log(
                action: 'purchase.created',
                module: 'purchases',
                reference: $purchase,
                newValues: [
                    'purchase_number' => $purchase->purchase_number,
                    'supplier_id' => $purchase->supplier_id,
                    'subtotal' => $purchase->subtotal,
                    'payable_total' => $purchase->payable_total,
                    'status' => $purchase->status,
                ]
            );

            return $purchase->fresh(['items', 'supplier']);
        });
    }

    /**
     * Update an existing draft Purchase Order.
     */
    public function updateDraft(Purchase|int $purchase, array $data, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $po = $purchase instanceof Purchase ? $purchase : Purchase::findOrFail($purchase);

            // Row lock
            $po = Purchase::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (! $po->isDraft()) {
                throw new InvalidArgumentException("Purchase Order dengan status [{$po->status}] tidak dapat diedit langsung. Hanya draft yang dapat diubah.");
            }

            $supplier = Supplier::findOrFail($data['supplier_id']);
            if (! $supplier->is_active) {
                throw new InvalidArgumentException("Pemasok [{$supplier->name}] berstatus nonaktif dan tidak dapat digunakan untuk Purchase Order.");
            }

            if (empty($data['items']) || ! is_array($data['items'])) {
                throw new InvalidArgumentException('Purchase Order harus memiliki minimal satu item barang.');
            }

            $docDiscountInput = Decimal::of($data['document_discount_total'] ?? '0', 2);
            $addCostInput = Decimal::of($data['additional_cost_estimate'] ?? '0', 2);

            if ($docDiscountInput->isNegative()) {
                throw new InvalidArgumentException('Diskon dokumen tidak boleh negatif.');
            }
            if ($addCostInput->isNegative()) {
                throw new InvalidArgumentException('Estimasi biaya tambahan tidak boleh negatif.');
            }

            $computedLines = $this->processAndValidateItems($data['items']);

            $netItemsSubtotal = $computedLines['net_subtotal'];
            if ($docDiscountInput->isGreaterThan($netItemsSubtotal)) {
                throw new InvalidArgumentException("Diskon dokumen [{$docDiscountInput}] tidak boleh melebihi nilai subtotal setelah diskon item [{$netItemsSubtotal}].");
            }

            $allocatedDiscounts = MoneyCalculator::allocateProportional($docDiscountInput, $computedLines['line_bases']);
            $payableTotal = $netItemsSubtotal->sub($docDiscountInput)->add($addCostInput);

            $oldValues = $po->only(['supplier_id', 'subtotal', 'payable_total', 'document_discount_total']);

            $po->update([
                'supplier_id' => $supplier->id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $computedLines['gross_subtotal']->toString(),
                'item_discount_total' => $computedLines['item_discount_total']->toString(),
                'document_discount_total' => $docDiscountInput->toString(),
                'additional_cost_estimate' => $addCostInput->toString(),
                'payable_total' => $payableTotal->toString(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Re-create items
            $po->items()->delete();
            foreach ($computedLines['items'] as $index => $line) {
                $allocatedDoc = Decimal::of($allocatedDiscounts[$index] ?? '0.00', 2);
                $finalLineTotal = $line['basis']->sub($allocatedDoc);

                PurchaseItem::create([
                    'purchase_id' => $po->id,
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'sku_snapshot' => $line['product']->sku,
                    'unit_snapshot' => $line['product']->unit?->name ?? 'Pcs',
                    'ordered_qty' => $line['ordered_qty']->toString(),
                    'unit_cost' => $line['unit_cost']->toString(),
                    'line_discount' => $line['line_discount']->toString(),
                    'allocated_document_discount' => $allocatedDoc->toString(),
                    'accepted_qty_total' => '0.000',
                    'rejected_qty_total' => '0.000',
                    'cancelled_qty' => '0.000',
                    'line_total' => $finalLineTotal->toString(),
                ]);
            }

            AuditLogger::log(
                action: 'purchase.updated',
                module: 'purchases',
                reference: $po,
                oldValues: $oldValues,
                newValues: $po->only(['supplier_id', 'subtotal', 'payable_total', 'document_discount_total'])
            );

            return $po->fresh(['items', 'supplier']);
        });
    }

    /**
     * Confirm a draft Purchase Order, moving its status to 'ordered'.
     */
    public function confirm(Purchase|int $purchase, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $userId) {
            $po = $purchase instanceof Purchase ? $purchase : Purchase::findOrFail($purchase);
            $po = Purchase::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (! $po->isDraft()) {
                throw new InvalidArgumentException("Purchase Order #{$po->purchase_number} tidak dapat dikonfirmasi karena status saat ini adalah [{$po->status}].");
            }

            if ($po->items()->count() === 0) {
                throw new InvalidArgumentException("Purchase Order #{$po->purchase_number} tidak memiliki item.");
            }

            $po->status = 'ordered';
            $po->confirmed_by = $userId;
            $po->confirmed_at = Carbon::now('Asia/Jakarta');
            $po->save();

            AuditLogger::log(
                action: 'purchase.confirmed',
                module: 'purchases',
                reference: $po,
                oldValues: ['status' => 'draft'],
                newValues: ['status' => 'ordered', 'confirmed_by' => $userId, 'confirmed_at' => $po->confirmed_at]
            );

            return $po;
        });
    }

    /**
     * Cancel an entire Purchase Order or close remaining unreceived quantity with reason.
     */
    public function cancel(Purchase|int $purchase, string $reason, ?int $userId = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $userId) {
            if (empty(trim($reason))) {
                throw new InvalidArgumentException('Alasan pembatalan/penutupan PO wajib diisi.');
            }

            $po = $purchase instanceof Purchase ? $purchase : Purchase::findOrFail($purchase);
            $po = Purchase::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if ($po->isReceived()) {
                throw new InvalidArgumentException("Purchase Order #{$po->purchase_number} sudah berstatus diterima penuh (received) dan tidak dapat dibatalkan.");
            }

            if ($po->isCancelled()) {
                throw new InvalidArgumentException("Purchase Order #{$po->purchase_number} sudah dalam status dibatalkan (cancelled).");
            }

            $oldStatus = $po->status;

            // Mark remaining unreceived qty on lines as cancelled
            foreach ($po->items()->lockForUpdate()->get() as $item) {
                $rem = $item->remainingQty();
                if ($rem->isGreaterThan(Decimal::zero(3))) {
                    $item->cancelled_qty = (Decimal::of($item->cancelled_qty ?? '0', 3))->add($rem)->toString();
                    $item->save();
                }
            }

            $po->status = 'cancelled';
            $po->cancelled_by = $userId;
            $po->cancelled_at = Carbon::now('Asia/Jakarta');
            $po->cancel_reason = trim($reason);
            $po->save();

            AuditLogger::log(
                action: 'purchase.cancelled',
                module: 'purchases',
                reference: $po,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'cancelled',
                    'cancelled_by' => $userId,
                    'cancelled_at' => $po->cancelled_at,
                    'cancel_reason' => $po->cancel_reason,
                ]
            );

            return $po;
        });
    }

    /**
     * Helper to process, validate, and compute line values for items.
     *
     * @param  array<int, array{product_id: int, ordered_qty: mixed, unit_cost: mixed, line_discount?: mixed}>  $items
     * @return array{
     *     items: array<int, array{product: Product, ordered_qty: Decimal, unit_cost: Decimal, line_discount: Decimal, gross: Decimal, basis: Decimal}>,
     *     gross_subtotal: Decimal,
     *     item_discount_total: Decimal,
     *     net_subtotal: Decimal,
     *     line_bases: array<int, string>
     * }
     */
    protected function processAndValidateItems(array $items): array
    {
        $processed = [];
        $grossSubtotal = Decimal::zero(2);
        $itemDiscountTotal = Decimal::zero(2);
        $lineBases = [];

        foreach ($items as $idx => $line) {
            $product = Product::with('unit')->findOrFail($line['product_id']);

            $qtyRaw = (string) $line['ordered_qty'];
            if ($product->unit && ! $product->unit->isValidQuantity($qtyRaw)) {
                throw new InvalidArgumentException("Kuantitas barang [{$product->name}] melebihi presisi desimal satuan ({$product->unit->decimal_precision} desimal).");
            }

            $orderedQty = Decimal::of($qtyRaw, 3);
            if ($orderedQty->isNegative() || $orderedQty->isZero()) {
                throw new InvalidArgumentException("Kuantitas pemesanan barang [{$product->name}] harus bernilai positif (> 0).");
            }

            $unitCost = Decimal::of($line['unit_cost'], 2);
            if ($unitCost->isNegative()) {
                throw new InvalidArgumentException("Harga satuan barang [{$product->name}] tidak boleh bernilai negatif.");
            }

            $lineDiscount = Decimal::of($line['line_discount'] ?? '0', 2);
            if ($lineDiscount->isNegative()) {
                throw new InvalidArgumentException("Diskon item untuk [{$product->name}] tidak boleh negatif.");
            }

            $grossLine = $orderedQty->mul($unitCost, 2);
            if ($lineDiscount->isGreaterThan($grossLine)) {
                throw new InvalidArgumentException("Diskon item [{$lineDiscount}] untuk barang [{$product->name}] melebihi total kotor baris [{$grossLine}].");
            }

            $lineBasis = $grossLine->sub($lineDiscount);

            $grossSubtotal = $grossSubtotal->add($grossLine);
            $itemDiscountTotal = $itemDiscountTotal->add($lineDiscount);
            $lineBases[$idx] = $lineBasis->toString();

            $processed[$idx] = [
                'product' => $product,
                'ordered_qty' => $orderedQty,
                'unit_cost' => $unitCost,
                'line_discount' => $lineDiscount,
                'gross' => $grossLine,
                'basis' => $lineBasis,
            ];
        }

        $netSubtotal = $grossSubtotal->sub($itemDiscountTotal);

        return [
            'items' => $processed,
            'gross_subtotal' => $grossSubtotal,
            'item_discount_total' => $itemDiscountTotal,
            'net_subtotal' => $netSubtotal,
            'line_bases' => $lineBases,
        ];
    }
}
