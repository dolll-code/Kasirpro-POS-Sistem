<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockOpnameLock;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockOpnameService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected InventoryService $inventoryService,
    ) {}

    public function start(array $productIds, ?string $notes, User $user): StockOpname
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            throw new InvalidArgumentException('Pilih minimal satu produk untuk sesi opname.');
        }
        sort($productIds);

        return DB::transaction(function () use ($productIds, $notes, $user) {
            $opname = StockOpname::create([
                'opname_number' => $this->sequenceService->generate('SO'),
                'status' => 'draft',
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
            $now = Carbon::now('Asia/Jakarta');
            foreach ($productIds as $productId) {
                $product = Product::with('unit')->whereKey($productId)->lockForUpdate()->firstOrFail();
                if (! $product->is_active || ! $product->category()->where('is_active', true)->exists() || ! $product->unit()->where('is_active', true)->exists()) {
                    throw new InvalidArgumentException("Produk {$product->sku} tidak aktif atau tidak sellable.");
                }
                $balance = $this->inventoryService->lockBalance($product);
                $this->inventoryService->assertNoActiveStockReservation($product);
                $this->inventoryService->assertNoActiveStockOpnameLock($product);

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'expected_quantity' => $balance->quantity_on_hand,
                    'average_cost_snapshot' => $balance->average_cost,
                ]);
                StockOpnameLock::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $product->id,
                    'active_product_id' => $product->id,
                    'locked_by' => $user->id,
                    'locked_at' => $now,
                ]);
            }
            $opname->status = 'counting';
            $opname->started_by = $user->id;
            $opname->started_at = $now;
            $opname->save();

            AuditLogger::log(
                action: 'stock_opname.started',
                module: 'inventory',
                reference: $opname,
                newValues: ['opname_number' => $opname->opname_number, 'product_ids' => $productIds, 'started_at' => $now->toIso8601String()]
            );

            return $opname->load(['items.product.unit', 'creator', 'starter', 'locks']);
        });
    }

    public function count(StockOpname|int $opname, array $counts, User $user): StockOpname
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;

        return DB::transaction(function () use ($opnameId, $counts, $user) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if (! in_array($opname->status, ['counting', 'rejected'], true)) {
                throw new InvalidArgumentException('Hasil hitung hanya dapat diubah pada sesi counting/rejected.');
            }
            if ($opname->status === 'rejected') {
                $this->assertCanReopen($opname, $user);
                $now = Carbon::now('Asia/Jakarta');
                foreach ($opname->items as $item) {
                    $product = Product::with('unit')->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $balance = $this->inventoryService->lockBalance($product);
                    $this->inventoryService->assertNoActiveStockReservation($product);
                    $this->inventoryService->assertNoActiveStockOpnameLock($product);
                    $item->expected_quantity = $balance->quantity_on_hand;
                    $item->average_cost_snapshot = $balance->average_cost;
                    $item->counted_quantity = null;
                    $item->variance_quantity = null;
                    $item->variance_value = null;
                    $item->active_cost_snapshot = null;
                    $item->counted_by = null;
                    $item->counted_at = null;
                    $item->save();
                    StockOpnameLock::create([
                        'stock_opname_id' => $opname->id,
                        'product_id' => $product->id,
                        'active_product_id' => $product->id,
                        'locked_by' => $user->id,
                        'locked_at' => $now,
                    ]);
                }
                $opname->status = 'counting';
                $opname->reopen_reason = trim((string) ($counts['reopen_reason'] ?? ''));
                $opname->rejection_reason = null;
                $opname->submitted_at = null;
                $opname->approved_by = null;
                $opname->approved_at = null;
                $opname->save();
            }

            $itemCounts = $counts['items'] ?? [];
            foreach ($itemCounts as $itemId => $payload) {
                $item = StockOpnameItem::whereKey($itemId)
                    ->where('stock_opname_id', $opname->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (! array_key_exists('counted_quantity', $payload) || $payload['counted_quantity'] === null || $payload['counted_quantity'] === '') {
                    continue;
                }
                $product = Product::with('unit')->findOrFail($item->product_id);
                $quantity = Decimal::of((string) $payload['counted_quantity'], 3);
                if ($quantity->isNegative()) {
                    throw new InvalidArgumentException('Counted quantity tidak boleh negatif.');
                }
                if ($product->unit && ! $product->unit->isValidQuantity($quantity->toString())) {
                    throw new InvalidArgumentException("Presisi counted quantity produk {$product->sku} melebihi presisi unit.");
                }

                $item->counted_quantity = $quantity->toString();
                $item->counted_by = $user->id;
                $item->counted_at = Carbon::now('Asia/Jakarta');
                $item->notes = $payload['notes'] ?? null;
                $item->save();
            }

            if (filled($counts['notes'] ?? null)) {
                $opname->notes = $counts['notes'];
                $opname->save();
            }

            AuditLogger::log(
                action: 'stock_opname.counts_saved',
                module: 'inventory',
                reference: $opname,
                newValues: ['item_count' => count($itemCounts), 'counted_by' => $user->id]
            );

            return $opname->load(['items.product.unit', 'creator', 'starter', 'locks']);
        });
    }

    public function submit(StockOpname|int $opname, User $user): StockOpname
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;

        return DB::transaction(function () use ($opnameId, $user) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if ($opname->status !== 'counting') {
                throw new InvalidArgumentException('Hanya sesi counting yang dapat diajukan untuk approval.');
            }
            $items = $opname->items()->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->counted_quantity === null)) {
                throw new InvalidArgumentException('Semua produk wajib memiliki hasil hitung sebelum submit.');
            }
            foreach ($items as $item) {
                $variance = Decimal::of($item->counted_quantity, 3)->sub($item->expected_quantity);
                $item->variance_quantity = $variance->toString();
                $this->inventoryService->lockBalance($item->product_id);
                if ($variance->isNegative()) {
                    $item->active_cost_snapshot = $item->average_cost_snapshot;
                    $item->positive_unit_cost = null;
                    $item->positive_cost_reason = null;
                    $item->variance_value = $variance->mul($item->average_cost_snapshot, 2)->toString();
                } elseif ($variance->isPositive()) {
                    $balance = $this->inventoryService->lockBalance($item->product_id);
                    $item->active_cost_snapshot = $balance->average_cost;
                    $item->variance_value = null;
                } else {
                    $item->active_cost_snapshot = $item->average_cost_snapshot;
                    $item->variance_value = '0.00';
                }
                $item->save();
            }
            $opname->status = 'pending_approval';
            $opname->submitted_by = $user->id;
            $opname->submitted_at = Carbon::now('Asia/Jakarta');
            $opname->save();
            AuditLogger::log(action: 'stock_opname.submitted', module: 'inventory', reference: $opname, newValues: ['submitted_by' => $user->id, 'submitted_at' => $opname->submitted_at->toIso8601String()]);

            return $opname->load(['items.product.unit', 'creator', 'starter', 'submitter']);
        });
    }

    public function decide(StockOpname|int $opname, User $approver, string $decision, ?string $note = null, ?string $overrideReason = null): StockOpname
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Keputusan harus approved atau rejected.');
        }

        return DB::transaction(function () use ($opnameId, $approver, $decision, $note, $overrideReason) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if ($opname->status !== 'pending_approval') {
                throw new InvalidArgumentException('Sesi opname tidak menunggu approval.');
            }
            $itemCounters = $opname->items()->whereNotNull('counted_by')->distinct()->pluck('counted_by')->map(fn ($id) => (int) $id)->all();
            $isMaker = in_array((int) $approver->id, $itemCounters, true) || (int) $approver->id === (int) $opname->created_by;
            $override = trim((string) $overrideReason);
            if ($isMaker && ! ($approver->hasRole('admin') && $override !== '')) {
                throw new AuthorizationException('Maker-checker: penghitung tidak boleh menyetujui sesi ini; Admin override memerlukan alasan.');
            }
            if ($decision === 'rejected' && trim((string) $note) === '') {
                throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
            }
            $old = $opname->status;
            $opname->approved_by = $approver->id;
            $opname->admin_override_reason = $override !== '' ? $override : null;
            if ($decision === 'rejected') {
                $opname->status = 'rejected';
                $opname->rejection_reason = trim((string) $note);
                $this->releaseLocks($opname, 'rejected');
                $opname->save();
                AuditLogger::log(action: 'stock_opname.rejected', module: 'inventory', reference: $opname, oldValues: ['status' => $old], newValues: ['status' => 'rejected', 'approver_id' => $approver->id, 'reason' => $note, 'admin_override_reason' => $opname->admin_override_reason]);
            } else {
                foreach ($opname->items as $item) {
                    if (Decimal::of($item->variance_quantity, 3)->isPositive()) {
                        $cost = $item->active_cost_snapshot;
                        if (Decimal::of($cost ?? '0', 2)->isZero()) {
                            $positiveCost = Decimal::of($item->positive_unit_cost ?? '0', 2);
                            if (! $positiveCost->isPositive() || trim((string) $item->positive_cost_reason) === '') {
                                throw new InvalidArgumentException("Produk {$item->product->sku} membutuhkan positive unit cost dan reason karena tidak memiliki cost basis.");
                            }
                        }
                    }
                }
                $opname->status = 'approved';
                $opname->approved_at = Carbon::now('Asia/Jakarta');
                $opname->save();
                AuditLogger::log(action: 'stock_opname.approved', module: 'inventory', reference: $opname, oldValues: ['status' => $old], newValues: ['status' => 'approved', 'approver_id' => $approver->id, 'approved_at' => $opname->approved_at->toIso8601String(), 'admin_override_reason' => $opname->admin_override_reason]);
            }

            return $opname->load(['items.product.unit', 'creator', 'starter', 'submitter', 'approver', 'locks']);
        });
    }

    public function setPositiveCost(StockOpname|int $opname, int $itemId, string $unitCost, string $reason, User $user): StockOpnameItem
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;

        return DB::transaction(function () use ($opnameId, $itemId, $unitCost, $reason, $user) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if (! in_array($opname->status, ['counting', 'pending_approval', 'rejected'], true)) {
                throw new InvalidArgumentException('Positive cost hanya dapat diisi sebelum posting.');
            }
            if (! $user->hasRole('admin') && ! $user->hasRole('supervisor')) {
                throw new AuthorizationException('Hanya Admin/Supervisor dapat menetapkan positive unit cost.');
            }
            $item = StockOpnameItem::whereKey($itemId)->where('stock_opname_id', $opnameId)->lockForUpdate()->firstOrFail();
            if (! Decimal::of($item->variance_quantity ?? '0', 3)->isPositive()) {
                throw new InvalidArgumentException('Positive unit cost hanya relevan untuk variance positif.');
            }
            $cost = Decimal::of($unitCost, 2);
            if (! $cost->isPositive() || trim($reason) === '') {
                throw new InvalidArgumentException('Unit cost harus > 0 dan reason wajib.');
            }
            $item->positive_unit_cost = $cost->toString();
            $item->positive_cost_reason = trim($reason);
            if (Decimal::of($item->active_cost_snapshot ?? '0', 2)->isZero()) {
                $item->variance_value = Decimal::of($item->variance_quantity, 3)->mul($cost, 2)->toString();
            }
            $item->save();
            AuditLogger::log(action: 'stock_opname.positive_cost_set', module: 'inventory', reference: $opname, newValues: ['item_id' => $item->id, 'unit_cost' => $cost->toString(), 'reason' => trim($reason)], userId: $user->id);

            return $item;
        });
    }

    public function post(StockOpname|int $opname, User $poster): StockOpname
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;

        return DB::transaction(function () use ($opnameId, $poster) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if ($opname->status !== 'approved') {
                throw new InvalidArgumentException('Hanya sesi approved yang dapat diposting.');
            }
            $items = $opname->items()->with('product.unit')->orderBy('product_id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $this->inventoryService->lockBalance($item->product_id);
                $variance = Decimal::of($item->variance_quantity, 3);
                if ($variance->isZero()) {
                    continue;
                }
                if ($variance->isPositive()) {
                    $unitCost = Decimal::of($item->active_cost_snapshot ?? '0', 2);
                    if ($unitCost->isZero()) {
                        $unitCost = Decimal::of($item->positive_unit_cost ?? '0', 2);
                    }
                    if (! $unitCost->isPositive()) {
                        throw new InvalidArgumentException("Produk {$item->product->sku} tidak memiliki cost basis untuk adjustment positif.");
                    }
                    $this->inventoryService->addStock(
                        product: $item->product_id,
                        quantity: $variance,
                        unitCost: $unitCost,
                        movementType: 'IN_STOCKTAKE',
                        sourceType: 'stock_opname',
                        sourceId: $opname->id,
                        sourceNumber: $opname->opname_number,
                        userId: $poster->id,
                        notes: 'Penambahan variance positif opname; '.$item->positive_cost_reason,
                        allowStockOpnameId: $opname->id
                    );
                } else {
                    $cost = Decimal::of($item->average_cost_snapshot, 2);
                    $this->inventoryService->deductStock(
                        product: $item->product_id,
                        quantity: $variance->isNegative() ? Decimal::of('0', 3)->sub($variance) : $variance,
                        movementType: 'OUT_STOCKTAKE',
                        sourceType: 'stock_opname',
                        sourceId: $opname->id,
                        sourceNumber: $opname->opname_number,
                        userId: $poster->id,
                        notes: 'Pengurangan variance negatif opname',
                        allowStockOpnameId: $opname->id,
                        costOverride: $cost
                    );
                }
            }
            $opname->status = 'posted';
            $opname->posted_by = $poster->id;
            $opname->posted_at = Carbon::now('Asia/Jakarta');
            $this->releaseLocks($opname, 'posted');
            $opname->save();
            AuditLogger::log(action: 'stock_opname.posted', module: 'inventory', reference: $opname, newValues: ['opname_number' => $opname->opname_number, 'posted_by' => $poster->id, 'posted_at' => $opname->posted_at->toIso8601String(), 'movement_count' => $items->filter(fn ($item) => ! Decimal::of($item->variance_quantity, 3)->isZero())->count()]);

            return $opname->load(['items.product.unit', 'creator', 'starter', 'submitter', 'approver', 'poster', 'locks']);
        });
    }

    public function cancel(StockOpname|int $opname, User $user, string $reason): StockOpname
    {
        $opnameId = $opname instanceof StockOpname ? $opname->id : $opname;
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($opnameId, $user, $reason) {
            $opname = StockOpname::whereKey($opnameId)->lockForUpdate()->firstOrFail();
            if (! in_array($opname->status, ['draft', 'counting', 'rejected', 'pending_approval'], true)) {
                throw new InvalidArgumentException('Sesi ini tidak dapat dibatalkan pada status sekarang.');
            }
            if ((int) $opname->created_by !== (int) $user->id && ! $user->hasRole('admin') && ! $user->hasRole('supervisor')) {
                throw new AuthorizationException('Hanya pembuat sesi atau supervisor/admin dapat membatalkan sesi.');
            }
            $old = $opname->status;
            $opname->status = 'cancelled';
            $opname->cancelled_by = $user->id;
            $opname->cancelled_at = Carbon::now('Asia/Jakarta');
            $opname->rejection_reason = trim($reason);
            $this->releaseLocks($opname, 'cancelled');
            $opname->save();
            AuditLogger::log(action: 'stock_opname.cancelled', module: 'inventory', reference: $opname, oldValues: ['status' => $old], newValues: ['status' => 'cancelled', 'reason' => trim($reason)], userId: $user->id);

            return $opname->load(['items.product.unit', 'locks']);
        });
    }

    public function releaseLocks(StockOpname $opname, string $reason): void
    {
        StockOpnameLock::where('stock_opname_id', $opname->id)
            ->whereNotNull('active_product_id')
            ->update(['active_product_id' => null, 'released_at' => Carbon::now('Asia/Jakarta'), 'release_reason' => $reason, 'updated_at' => Carbon::now('Asia/Jakarta')]);
    }

    private function assertCanReopen(StockOpname $opname, User $user): void
    {
        if ((int) $opname->created_by !== (int) $user->id && ! $user->hasRole('admin') && ! $user->hasRole('supervisor')) {
            throw new AuthorizationException('Sesi rejected hanya dapat dibuka kembali oleh pembuat atau supervisor/admin.');
        }
    }
}
