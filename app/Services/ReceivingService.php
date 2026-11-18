<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Support\Decimal;
use App\Support\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReceivingService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Post a new goods receipt against a Purchase Order atomically.
     *
     * @param  array{
     *     received_at?: string|null,
     *     capitalizable_additional_cost?: string|float|int|null,
     *     notes?: string|null,
     *     items: array<int, array{
     *         purchase_item_id: int,
     *         physically_received_qty: string|float|int,
     *         accepted_qty: string|float|int,
     *         rejected_qty?: string|float|int|null,
     *         rejection_reason?: string|null,
     *         notes?: string|null
     *     }>
     * }  $data
     */
    public function postReceipt(Purchase|int $purchase, array $data, ?int $userId = null): PurchaseReceipt
    {
        return DB::transaction(function () use ($purchase, $data, $userId) {
            $po = $purchase instanceof Purchase ? $purchase : Purchase::findOrFail($purchase);

            // Row lock the purchase order
            $po = Purchase::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if (! in_array($po->status, ['ordered', 'partially_received'])) {
                throw new InvalidArgumentException("Penerimaan barang hanya dapat diposting untuk PO berstatus 'ordered' atau 'partially_received'. Status saat ini: [{$po->status}].");
            }

            if (empty($data['items']) || ! is_array($data['items'])) {
                throw new InvalidArgumentException('Penerimaan barang harus memuat minimal satu rincian item.');
            }

            $capCostInput = Decimal::of($data['capitalizable_additional_cost'] ?? '0', 2);
            if ($capCostInput->isNegative()) {
                throw new InvalidArgumentException('Biaya tambahan perolehan persediaan (capitalizable cost) tidak boleh negatif.');
            }

            $receivedAt = ! empty($data['received_at'])
                ? Carbon::parse($data['received_at'], 'Asia/Jakarta')
                : Carbon::now('Asia/Jakarta');

            // Lock all purchase items belonging to this PO
            $poItems = PurchaseItem::where('purchase_id', $po->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $preparedLines = [];
            $acceptedBases = [];
            $hasPhysicalMovement = false;

            foreach ($data['items'] as $idx => $lineInput) {
                $itemId = $lineInput['purchase_item_id'];
                if (! $poItems->has($itemId)) {
                    throw new InvalidArgumentException("Item PO ID [{$itemId}] tidak ditemukan pada Purchase Order #{$po->purchase_number}.");
                }

                /** @var PurchaseItem $poItem */
                $poItem = $poItems->get($itemId);

                $physQty = Decimal::of($lineInput['physically_received_qty'] ?? '0', 3);
                $acceptedQty = Decimal::of($lineInput['accepted_qty'] ?? '0', 3);
                $rejectedQty = Decimal::of($lineInput['rejected_qty'] ?? '0', 3);

                if ($physQty->isNegative() || $acceptedQty->isNegative() || $rejectedQty->isNegative()) {
                    throw new InvalidArgumentException('Kuantitas fisik, diterima (accepted), dan ditolak (rejected) tidak boleh negatif.');
                }

                // Invariant: physically_received_qty = accepted_qty + rejected_qty
                $sumQty = $acceptedQty->add($rejectedQty);
                if (! $physQty->equals($sumQty)) {
                    throw new InvalidArgumentException(
                        "Kuantitas barang [{$poItem->product_name_snapshot}] tidak valid: Kuantitas fisik ({$physQty}) harus tepat sama dengan jumlah diterima ({$acceptedQty}) + ditolak ({$rejectedQty})."
                    );
                }

                if ($physQty->isGreaterThan(Decimal::zero(3))) {
                    $hasPhysicalMovement = true;
                }

                // Invariant: accepted_qty <= remaining_qty
                $remainingQty = $poItem->remainingQty();
                if ($acceptedQty->isGreaterThan($remainingQty)) {
                    throw new InvalidArgumentException(
                        "Kuantitas diterima [accepted: {$acceptedQty}] untuk barang [{$poItem->product_name_snapshot}] melebihi sisa pesanan PO [sisa: {$remainingQty}]."
                    );
                }

                // Net line cost basis for accepted portion: acceptedQty * (line_total / ordered_qty)
                $effectiveUnitCost = $poItem->effectiveUnitCost();
                $acceptedBaseValue = $acceptedQty->mul($effectiveUnitCost, 2);

                if ($acceptedQty->isGreaterThan(Decimal::zero(3))) {
                    $acceptedBases[$idx] = $acceptedBaseValue->toString();
                }

                $preparedLines[$idx] = [
                    'po_item' => $poItem,
                    'phys_qty' => $physQty,
                    'accepted_qty' => $acceptedQty,
                    'rejected_qty' => $rejectedQty,
                    'rejection_reason' => $lineInput['rejection_reason'] ?? null,
                    'notes' => $lineInput['notes'] ?? null,
                    'accepted_base_value' => $acceptedBaseValue,
                ];
            }

            if (! $hasPhysicalMovement) {
                throw new InvalidArgumentException('Minimal harus ada satu item barang dengan kuantitas fisik diterima > 0.');
            }

            // Allocate capitalizable costs proportionally across lines with accepted goods
            $allocatedCapCosts = [];
            if ($capCostInput->isGreaterThan(Decimal::zero(2)) && ! empty($acceptedBases)) {
                $allocatedCapCosts = MoneyCalculator::allocateProportional($capCostInput, $acceptedBases);
            }

            $receiptNumber = $this->sequenceService->generate('RCV');

            $receipt = PurchaseReceipt::create([
                'receipt_number' => $receiptNumber,
                'purchase_id' => $po->id,
                'received_at' => $receivedAt,
                'capitalizable_additional_cost' => $capCostInput->toString(),
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
                'received_by' => $userId,
                'posted_by' => $userId,
                'posted_at' => Carbon::now('Asia/Jakarta'),
            ]);

            // Process each receipt item line and stock addition
            foreach ($preparedLines as $idx => $line) {
                /** @var PurchaseItem $poItem */
                $poItem = $line['po_item'];
                $acceptedQty = $line['accepted_qty'];
                $rejectedQty = $line['rejected_qty'];

                if ($acceptedQty->isGreaterThan(Decimal::zero(3))) {
                    $allocatedCost = Decimal::of($allocatedCapCosts[$idx] ?? '0.00', 2);
                    $landedTotalCost = $line['accepted_base_value']->add($allocatedCost);
                    $landedUnitCost = $landedTotalCost->div($acceptedQty, 2);
                } else {
                    $landedUnitCost = Decimal::zero(2);
                    $landedTotalCost = Decimal::zero(2);
                }

                PurchaseReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'purchase_item_id' => $poItem->id,
                    'product_id' => $poItem->product_id,
                    'physically_received_qty' => $line['phys_qty']->toString(),
                    'accepted_qty' => $acceptedQty->toString(),
                    'rejected_qty' => $rejectedQty->toString(),
                    'rejection_reason' => $line['rejection_reason'],
                    'landed_unit_cost' => $landedUnitCost->toString(),
                    'landed_total_cost' => $landedTotalCost->toString(),
                    'notes' => $line['notes'],
                ]);

                // Update PO item totals
                $newAcceptedTotal = (Decimal::of($poItem->accepted_qty_total ?? '0', 3))->add($acceptedQty);
                $newRejectedTotal = (Decimal::of($poItem->rejected_qty_total ?? '0', 3))->add($rejectedQty);

                $poItem->accepted_qty_total = $newAcceptedTotal->toString();
                $poItem->rejected_qty_total = $newRejectedTotal->toString();
                $poItem->save();

                // If accepted > 0, post stock mutation and update moving weighted average cost!
                if ($acceptedQty->isGreaterThan(Decimal::zero(3))) {
                    $this->inventoryService->addStock(
                        product: $poItem->product_id,
                        quantity: $acceptedQty,
                        unitCost: $landedUnitCost,
                        movementType: 'IN_PURCHASE',
                        sourceType: 'purchase_receipts',
                        sourceId: $receipt->id,
                        sourceNumber: $receipt->receipt_number,
                        userId: $userId,
                        notes: "Penerimaan barang PO #{$po->purchase_number}",
                        occurredAt: $receivedAt
                    );
                }
            }

            // Re-evaluate PO status
            $allFulfilled = true;
            foreach ($po->items()->get() as $item) {
                if (! $item->isFullyReceived()) {
                    $allFulfilled = false;
                    break;
                }
            }

            $newStatus = $allFulfilled ? 'received' : 'partially_received';
            $po->status = $newStatus;
            $po->save();

            AuditLogger::log(
                action: 'purchase_receipt.posted',
                module: 'purchases',
                reference: $receipt,
                newValues: [
                    'receipt_number' => $receipt->receipt_number,
                    'purchase_number' => $po->purchase_number,
                    'capitalizable_cost' => $receipt->capitalizable_additional_cost,
                    'new_po_status' => $po->status,
                ]
            );

            return $receipt->fresh(['items', 'purchase']);
        });
    }
}
