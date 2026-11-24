<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\ReturnPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesReturnService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected InventoryService $inventoryService,
        protected CashMovementService $cashMovementService,
    ) {}

    public function request(Sale|int $sale, array $data, User $requester): SalesReturn
    {
        $saleId = $sale instanceof Sale ? $sale->id : $sale;

        return DB::transaction(function () use ($saleId, $data, $requester) {
            $sale = Sale::whereKey($saleId)->lockForUpdate()->firstOrFail();
            if (! in_array($sale->sale_status, ['completed', 'partially_returned'], true)) {
                throw new InvalidArgumentException('Retur hanya dapat diajukan untuk penjualan completed atau partially_returned.');
            }

            $saleItem = SaleItem::whereKey($data['sale_item_id'])
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            $saleItem->load('product.unit');

            $quantity = Decimal::of($data['quantity'], 3);
            if (! $quantity->isPositive()) {
                throw new InvalidArgumentException('Kuantitas retur harus lebih besar dari nol.');
            }
            if ($saleItem->product?->unit && ! $saleItem->product->unit->isValidQuantity($quantity->toString())) {
                throw new InvalidArgumentException('Presisi kuantitas retur melebihi presisi satuan produk.');
            }
            if (! in_array($data['condition'], ['resellable', 'damaged'], true)) {
                throw new InvalidArgumentException('Kondisi retur harus resellable atau damaged.');
            }
            if (trim((string) ($data['reason'] ?? '')) === '') {
                throw new InvalidArgumentException('Alasan retur wajib diisi.');
            }

            $alreadyReserved = SalesReturnItem::query()
                ->where('sale_item_id', $saleItem->id)
                ->whereHas('salesReturn', fn ($query) => $query->whereIn('status', ['pending_approval', 'approved', 'completed']))
                ->sum('quantity');
            $returnable = Decimal::of($saleItem->quantity, 3)->sub((string) $alreadyReserved);
            if ($quantity->isGreaterThan($returnable)) {
                throw new InvalidArgumentException('RETURN_QUANTITY_EXCEEDED: kuantitas melebihi sisa yang dapat diretur.');
            }

            $returnNumber = $this->sequenceService->generate('RET');
            $beforeQuantity = Decimal::of((string) $alreadyReserved, 3);
            $totalValue = $this->incrementalPortion($saleItem->net_line_total, $beforeQuantity, $quantity, $saleItem->quantity);
            $costValue = $this->incrementalPortion($saleItem->cost_total_snapshot, $beforeQuantity, $quantity, $saleItem->quantity);
            $itemDiscount = $this->incrementalPortion($saleItem->line_discount, $beforeQuantity, $quantity, $saleItem->quantity);
            $transactionDiscount = $this->incrementalPortion($saleItem->allocated_transaction_discount, $beforeQuantity, $quantity, $saleItem->quantity);
            $netUnitPrice = $this->proportionalValue($saleItem->net_line_total, 1, $saleItem->quantity);
            $now = Carbon::now('Asia/Jakarta');

            $salesReturn = SalesReturn::create([
                'return_number' => $returnNumber,
                'sale_id' => $sale->id,
                'requested_by' => $requester->id,
                'status' => 'pending_approval',
                'refund_status' => 'due',
                'reason' => trim($data['reason']),
                'notes' => $data['notes'] ?? null,
                'return_total' => $totalValue->toString(),
                'requested_at' => $now,
            ]);

            SalesReturnItem::create([
                'sales_return_id' => $salesReturn->id,
                'sale_item_id' => $saleItem->id,
                'condition' => $data['condition'],
                'quantity' => $quantity->toString(),
                'selling_price_snapshot' => $saleItem->unit_price_snapshot,
                'item_discount_snapshot' => $itemDiscount->toString(),
                'transaction_discount_snapshot' => $transactionDiscount->toString(),
                'net_unit_price_snapshot' => $netUnitPrice->toString(),
                'cost_price_snapshot' => $saleItem->cost_price_snapshot,
                'return_value' => $totalValue->toString(),
                'cost_value' => $costValue->toString(),
                'damaged_loss' => $data['condition'] === 'damaged' ? $costValue->toString() : '0.00',
            ]);

            AuditLogger::log(
                action: 'sale.return_requested',
                module: 'returns',
                reference: $salesReturn,
                newValues: [
                    'return_number' => $returnNumber,
                    'sale_id' => $sale->id,
                    'sale_item_id' => $saleItem->id,
                    'quantity' => $quantity->toString(),
                    'condition' => $data['condition'],
                    'return_value' => $totalValue->toString(),
                    'reason' => trim($data['reason']),
                ]
            );

            return $salesReturn->load(['sale', 'requester', 'items.saleItem']);
        });
    }

    public function decide(
        SalesReturn|int $return,
        User $approver,
        string $decision,
        ?string $note = null,
        ?string $overrideReason = null
    ): SalesReturn {
        $returnId = $return instanceof SalesReturn ? $return->id : $return;
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Keputusan retur harus approved atau rejected.');
        }

        return DB::transaction(function () use ($returnId, $approver, $decision, $note, $overrideReason) {
            $saleId = SalesReturn::whereKey($returnId)->value('sale_id');
            $sale = Sale::whereKey($saleId)->lockForUpdate()->firstOrFail();
            $salesReturn = SalesReturn::whereKey($returnId)->lockForUpdate()->firstOrFail();
            if ($salesReturn->status !== 'pending_approval') {
                throw new InvalidArgumentException('Retur tidak berada dalam status pending_approval.');
            }

            $isOwnRequest = (int) $salesReturn->requested_by === (int) $approver->id;
            $hasOverrideReason = trim((string) $overrideReason) !== '';
            if ($isOwnRequest && ! ($approver->hasRole('admin') && $hasOverrideReason)) {
                throw new AuthorizationException('Maker-checker retur: requester dilarang memutuskan retur sendiri; Admin override wajib menyertakan alasan.');
            }
            if ($decision === 'rejected' && trim((string) $note) === '') {
                throw new InvalidArgumentException('Catatan/alasan penolakan retur wajib diisi.');
            }

            $now = Carbon::now('Asia/Jakarta');
            $oldStatus = $salesReturn->status;
            $salesReturn->approved_by = $approver->id;
            $salesReturn->approval_note = trim((string) ($note ?? '')) ?: null;
            $salesReturn->admin_override_reason = $hasOverrideReason ? trim($overrideReason) : null;

            if ($decision === 'rejected') {
                $salesReturn->status = 'rejected';
                $salesReturn->rejected_at = $now;
                $salesReturn->save();
                AuditLogger::log(
                    action: 'sale.return_rejected',
                    module: 'returns',
                    reference: $salesReturn,
                    oldValues: ['status' => $oldStatus],
                    newValues: ['status' => 'rejected', 'approver_id' => $approver->id, 'note' => $note, 'admin_override_reason' => $salesReturn->admin_override_reason]
                );

                return $salesReturn->load(['sale', 'requester', 'approver', 'items.saleItem', 'payment']);
            }

            $salesReturn->load('items');
            $returnTotal = Decimal::zero(2);
            foreach ($salesReturn->items as $returnItem) {
                $saleItem = SaleItem::whereKey($returnItem->sale_item_id)->lockForUpdate()->firstOrFail();
                $approvedQuantity = (string) SalesReturnItem::query()
                    ->where('sale_item_id', $saleItem->id)
                    ->whereHas('salesReturn', fn ($query) => $query->whereIn('status', ['approved', 'completed']))
                    ->sum('quantity');
                $beforeQuantity = Decimal::of($approvedQuantity, 3);
                $returnQuantity = Decimal::of($returnItem->quantity, 3);

                // Finalize monetary rounding at approval, against approved prior quantities only.
                $returnItem->selling_price_snapshot = $saleItem->unit_price_snapshot;
                $returnItem->item_discount_snapshot = $this->incrementalPortion($saleItem->line_discount, $beforeQuantity, $returnQuantity, $saleItem->quantity)->toString();
                $returnItem->transaction_discount_snapshot = $this->incrementalPortion($saleItem->allocated_transaction_discount, $beforeQuantity, $returnQuantity, $saleItem->quantity)->toString();
                $returnItem->net_unit_price_snapshot = $this->proportionalValue($saleItem->net_line_total, 1, $saleItem->quantity)->toString();
                $returnItem->cost_price_snapshot = $saleItem->cost_price_snapshot;
                $returnItem->return_value = $this->incrementalPortion($saleItem->net_line_total, $beforeQuantity, $returnQuantity, $saleItem->quantity)->toString();
                $returnItem->cost_value = $this->incrementalPortion($saleItem->cost_total_snapshot, $beforeQuantity, $returnQuantity, $saleItem->quantity)->toString();
                $returnItem->damaged_loss = $returnItem->condition === 'damaged' ? $returnItem->cost_value : '0.00';
                $returnItem->save();
                $returnTotal = $returnTotal->add($returnItem->return_value);
            }

            $salesReturn->return_total = $returnTotal->toString();
            $salesReturn->status = 'approved';
            $salesReturn->approved_at = $now;
            $salesReturn->save();

            foreach ($salesReturn->items as $returnItem) {
                $saleItem = SaleItem::whereKey($returnItem->sale_item_id)->lockForUpdate()->firstOrFail();
                $product = $saleItem->product_id;
                $this->inventoryService->lockBalance($product);

                if ($returnItem->condition === 'resellable') {
                    $this->inventoryService->addStock(
                        product: $product,
                        quantity: $returnItem->quantity,
                        unitCost: $returnItem->cost_price_snapshot,
                        movementType: 'IN_RETURN_RESELLABLE',
                        sourceType: 'sales_return',
                        sourceId: $salesReturn->id,
                        sourceNumber: $salesReturn->return_number,
                        userId: $approver->id,
                        notes: "Retur resellable {$salesReturn->return_number}; HPP historis",
                        occurredAt: $now
                    );
                }
            }

            $this->updateSaleReturnState($sale);
            AuditLogger::log(
                action: 'sale.return_approved',
                module: 'returns',
                reference: $salesReturn,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'approved',
                    'approver_id' => $approver->id,
                    'approved_at' => $now->toIso8601String(),
                    'return_total' => $salesReturn->return_total,
                    'resellable_cost_reversal' => $salesReturn->items->where('condition', 'resellable')->sum('cost_value'),
                    'damaged_loss_informational' => $salesReturn->items->where('condition', 'damaged')->sum('damaged_loss'),
                    'admin_override_reason' => $salesReturn->admin_override_reason,
                ]
            );

            return $salesReturn->load(['sale', 'requester', 'approver', 'items.saleItem', 'payment']);
        });
    }

    public function payRefund(
        SalesReturn|int $return,
        CashAccount|int $account,
        User $user,
        ?string $referenceNumber = null,
        ?Carbon $paidAt = null
    ): SalesReturn {
        $returnId = $return instanceof SalesReturn ? $return->id : $return;
        $accountId = $account instanceof CashAccount ? $account->id : $account;

        return DB::transaction(function () use ($returnId, $accountId, $user, $referenceNumber, $paidAt) {
            $saleId = SalesReturn::whereKey($returnId)->value('sale_id');
            $sale = Sale::whereKey($saleId)->lockForUpdate()->firstOrFail();
            $salesReturn = SalesReturn::whereKey($returnId)->lockForUpdate()->firstOrFail();
            if ($salesReturn->status !== 'approved' || $salesReturn->refund_status !== 'due') {
                throw new InvalidArgumentException('Refund hanya dapat dibayar satu kali untuk retur berstatus approved dan due.');
            }
            if (ReturnPayment::where('sales_return_id', $salesReturn->id)->exists()) {
                throw new InvalidArgumentException('Refund untuk retur ini sudah dibayarkan.');
            }

            $cashMovement = $this->cashMovementService->recordOutflow(
                account: $accountId,
                amount: $salesReturn->return_total,
                movementType: 'OUT_RETURN_REFUND',
                sourceType: 'return_payment',
                sourceId: $salesReturn->id,
                sourceNumber: $salesReturn->return_number,
                description: "Refund retur penjualan {$salesReturn->return_number}",
                userId: $user->id,
                occurredAt: $paidAt ?? Carbon::now('Asia/Jakarta'),
                transactional: false
            );

            $payment = ReturnPayment::create([
                'sales_return_id' => $salesReturn->id,
                'cash_account_id' => $accountId,
                'cash_movement_id' => $cashMovement->id,
                'amount' => $salesReturn->return_total,
                'reference_number' => $referenceNumber,
                'paid_at' => $paidAt ?? Carbon::now('Asia/Jakarta'),
                'created_by' => $user->id,
            ]);
            $salesReturn->refund_status = 'paid';
            $salesReturn->status = 'completed';
            $salesReturn->completed_at = $paidAt ?? Carbon::now('Asia/Jakarta');
            $salesReturn->save();

            $this->updateSaleReturnState($sale);
            AuditLogger::log(
                action: 'sale.return_refund_paid',
                module: 'returns',
                reference: $salesReturn,
                newValues: [
                    'return_number' => $salesReturn->return_number,
                    'amount' => $payment->amount,
                    'cash_account_id' => $accountId,
                    'cash_movement_id' => $cashMovement->id,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ]
            );

            return $salesReturn->load(['sale', 'requester', 'approver', 'items.saleItem', 'payment.cashAccount', 'payment.cashMovement']);
        });
    }

    public function updateSaleReturnState(Sale $sale): void
    {
        $sale->loadMissing('items');
        $returnedByItem = SalesReturnItem::query()
            ->whereHas('salesReturn', fn ($query) => $query->whereIn('status', ['approved', 'completed']))
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->selectRaw('sale_item_id, SUM(quantity) AS returned_quantity')
            ->groupBy('sale_item_id')
            ->pluck('returned_quantity', 'sale_item_id');

        $returnedQuantity = Decimal::zero(3);
        $totalQuantity = Decimal::zero(3);
        foreach ($sale->items as $item) {
            $totalQuantity = $totalQuantity->add($item->quantity);
            $returnedQuantity = $returnedQuantity->add((string) ($returnedByItem[$item->id] ?? '0'));
        }

        $sale->sale_status = $returnedQuantity->isZero()
            ? 'completed'
            : ($returnedQuantity->isGreaterThanOrEqualTo($totalQuantity) ? 'fully_returned' : 'partially_returned');

        $approvedValue = SalesReturn::where('sale_id', $sale->id)->whereIn('status', ['approved', 'completed'])->sum('return_total');
        $paidValue = ReturnPayment::whereHas('salesReturn', fn ($query) => $query->where('sale_id', $sale->id))->sum('amount');
        if (Decimal::of((string) $approvedValue, 2)->isPositive()) {
            if (Decimal::of((string) $paidValue, 2)->isGreaterThanOrEqualTo((string) $approvedValue)) {
                $sale->payment_status = $sale->sale_status === 'fully_returned' ? 'refunded' : 'paid';
            } else {
                $sale->payment_status = 'refund_due';
            }
        }
        $sale->save();
    }

    public function historyForSale(Sale $sale): Collection
    {
        return SalesReturn::with(['requester', 'approver', 'items.saleItem', 'payment.cashAccount'])
            ->where('sale_id', $sale->id)
            ->latest('requested_at')
            ->get();
    }

    public function recognizedReturnsBetween(Carbon $from, Carbon $to): string
    {
        return Decimal::of((string) SalesReturn::query()
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('approved_at', [$from, $to])
            ->sum('return_total'), 2)->toString();
    }

    public function refundsPaidBetween(Carbon $from, Carbon $to): string
    {
        return Decimal::of((string) ReturnPayment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount'), 2)->toString();
    }

    private function proportionalValue(string $total, string|int|Decimal $quantity, string|int|Decimal $soldQuantity): Decimal
    {
        $qty = Decimal::of($quantity, 3);
        $sold = Decimal::of($soldQuantity, 3);
        if (! $sold->isPositive()) {
            throw new InvalidArgumentException('Kuantitas penjualan historis tidak valid.');
        }

        return Decimal::of($total, 2)->mul($qty, 8)->div($sold, 2);
    }

    private function incrementalPortion(string $total, Decimal $alreadyReturned, Decimal $returningNow, string $soldQuantity): Decimal
    {
        return $this->proportionalValue($total, $alreadyReturned->add($returningNow), $soldQuantity)
            ->sub($this->proportionalValue($total, $alreadyReturned, $soldQuantity));
    }
}
