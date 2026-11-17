<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Decimal;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        protected CostingService $costingService
    ) {}

    /**
     * Get or create the inventory balance record for a product.
     */
    public function getOrCreateBalance(Product|int $product): InventoryBalance
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return InventoryBalance::firstOrCreate(
            ['product_id' => $productId],
            [
                'quantity_on_hand' => '0.000',
                'quantity_reserved' => '0.000',
                'average_cost' => '0.00',
                'version' => 1,
            ]
        );
    }

    /**
     * Lock the inventory balance row with pessimistic write lock (FOR UPDATE).
     */
    public function lockBalance(Product|int $product): InventoryBalance
    {
        $productId = $product instanceof Product ? $product->id : $product;

        // Ensure row exists before locking
        $this->getOrCreateBalance($productId);

        return InventoryBalance::where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Validate whether product has sufficient available stock (on_hand - reserved >= quantity).
     */
    public function validateAvailableStock(Product|int $product, string|Decimal $quantity): bool
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $balance = InventoryBalance::where('product_id', $productId)->first();

        if (! $balance) {
            return false;
        }

        $available = Decimal::of($balance->available_quantity, 3);
        $needed = Decimal::of($quantity, 3);

        return $available->isGreaterThanOrEqualTo($needed);
    }

    /**
     * Add stock to inventory atomically within a database transaction.
     *
     * @param  string  $movementType  (e.g. IN_PURCHASE, IN_INITIAL, IN_RETURN_RESELLABLE, IN_ADJUSTMENT)
     */
    public function addStock(
        Product|int $product,
        string|Decimal $quantity,
        string|Decimal $unitCost,
        string $movementType,
        string $sourceType,
        ?int $sourceId = null,
        ?string $sourceNumber = null,
        ?int $userId = null,
        ?string $notes = null,
        ?DateTimeInterface $occurredAt = null
    ): StockMovement {
        return DB::transaction(function () use (
            $product,
            $quantity,
            $unitCost,
            $movementType,
            $sourceType,
            $sourceId,
            $sourceNumber,
            $userId,
            $notes,
            $occurredAt
        ) {
            $prod = $product instanceof Product ? $product : Product::with('unit')->findOrFail($product);
            $qtyRaw = $quantity instanceof Decimal ? $quantity->toString() : (string) $quantity;

            // Validate decimal precision against unit definition
            if ($prod->unit && ! $prod->unit->isValidQuantity($qtyRaw)) {
                throw new InvalidArgumentException(
                    "Kuantitas [{$qtyRaw}] melebihi batas presisi desimal satuan ({$prod->unit->decimal_precision} desimal)."
                );
            }

            $qty = Decimal::of($qtyRaw, 3);
            $cost = Decimal::of($unitCost, 2);

            if ($qty->isNegative() || $qty->isZero()) {
                throw new InvalidArgumentException('Kuantitas penambahan stok harus bernilai positif (> 0).');
            }

            if ($cost->isNegative()) {
                throw new InvalidArgumentException('Biaya satuan stok tidak boleh negatif.');
            }

            // Lock balance for update
            $balance = $this->lockBalance($prod);

            $before = Decimal::of($balance->quantity_on_hand, 3);
            $after = $before->add($qty);

            // Update average cost using CostingService if applicable
            if ($movementType === 'IN_PURCHASE' || $movementType === 'IN_INITIAL') {
                $this->costingService->recordReceiptCost(
                    $prod,
                    $qty,
                    $cost,
                    $sourceType,
                    $sourceId,
                    $sourceNumber,
                    $userId,
                    $notes
                );
                // Reload balance to sync average_cost
                $balance->refresh();
            } elseif ($movementType === 'IN_RETURN_RESELLABLE') {
                $this->costingService->recordResellableReturnCost(
                    $prod,
                    $qty,
                    $cost,
                    $sourceType,
                    $sourceId,
                    $sourceNumber,
                    $userId,
                    $notes
                );
                $balance->refresh();
            }

            // Update balance on_hand
            $balance->quantity_on_hand = $after->toString();
            $balance->version += 1;
            $balance->save();

            // Total cost = qty * unitCost
            $totalCost = $qty->mul($cost, 2);

            // Record append-only stock movement
            return StockMovement::create([
                'product_id' => $prod->id,
                'movement_type' => $movementType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_number' => $sourceNumber,
                'quantity_in' => $qty->toString(),
                'quantity_out' => '0.000',
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'unit_cost' => $cost->toString(),
                'total_cost' => $totalCost->toString(),
                'notes' => $notes,
                'created_by' => $userId,
                'occurred_at' => $occurredAt ?? Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        });
    }

    /**
     * Deduct stock from inventory atomically within a database transaction.
     *
     * @param  string  $movementType  (e.g. OUT_SALE, OUT_ADJUSTMENT, OUT_RETURN_SUPPLIER, OUT_DAMAGED)
     *
     * @throws InsufficientStockException
     */
    public function deductStock(
        Product|int $product,
        string|Decimal $quantity,
        string $movementType,
        string $sourceType,
        ?int $sourceId = null,
        ?string $sourceNumber = null,
        ?int $userId = null,
        ?string $notes = null,
        ?DateTimeInterface $occurredAt = null
    ): StockMovement {
        return DB::transaction(function () use (
            $product,
            $quantity,
            $movementType,
            $sourceType,
            $sourceId,
            $sourceNumber,
            $userId,
            $notes,
            $occurredAt
        ) {
            $prod = $product instanceof Product ? $product : Product::with('unit')->findOrFail($product);
            $qtyRaw = $quantity instanceof Decimal ? $quantity->toString() : (string) $quantity;

            // Validate decimal precision against unit definition
            if ($prod->unit && ! $prod->unit->isValidQuantity($qtyRaw)) {
                throw new InvalidArgumentException(
                    "Kuantitas [{$qtyRaw}] melebihi batas presisi desimal satuan ({$prod->unit->decimal_precision} desimal)."
                );
            }

            $qty = Decimal::of($qtyRaw, 3);

            if ($qty->isNegative() || $qty->isZero()) {
                throw new InvalidArgumentException('Kuantitas pengurangan stok harus bernilai positif (> 0).');
            }

            // Lock balance for update
            $balance = $this->lockBalance($prod);

            $onHand = Decimal::of($balance->quantity_on_hand, 3);
            $reserved = Decimal::of($balance->quantity_reserved, 3);
            $available = $onHand->sub($reserved);

            // Check available stock
            if ($available->isLessThan($qty)) {
                throw new InsufficientStockException(
                    message: "Stok tersedia tidak mencukupi untuk produk [{$prod->name}]. Diminta: {$qty}, Tersedia: {$available}.",
                    productId: $prod->id,
                    requested: $qty->toString(),
                    available: $available->toString()
                );
            }

            $before = $onHand;
            $after = $before->sub($qty);

            if ($after->isNegative()) {
                throw new InsufficientStockException(
                    message: "Pengurangan stok menyebabkan saldo minus untuk produk [{$prod->name}].",
                    productId: $prod->id,
                    requested: $qty->toString(),
                    available: $available->toString()
                );
            }

            // Capture unit cost snapshot from average cost
            $unitCost = Decimal::of($balance->average_cost, 2);
            $totalCost = $qty->mul($unitCost, 2);

            // If sale, record cost snapshot and handle reset if stock becomes 0
            if ($movementType === 'OUT_SALE') {
                $this->costingService->recordSaleCostSnapshot(
                    $prod,
                    $qty,
                    $sourceType,
                    $sourceId,
                    $sourceNumber,
                    $userId
                );
                $balance->refresh();
            }

            // Update balance on_hand
            $balance->quantity_on_hand = $after->toString();
            $balance->version += 1;
            $balance->save();

            // Record append-only stock movement
            return StockMovement::create([
                'product_id' => $prod->id,
                'movement_type' => $movementType,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_number' => $sourceNumber,
                'quantity_in' => '0.000',
                'quantity_out' => $qty->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'unit_cost' => $unitCost->toString(),
                'total_cost' => $totalCost->toString(),
                'notes' => $notes,
                'created_by' => $userId,
                'occurred_at' => $occurredAt ?? Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        });
    }

    /**
     * Reserve stock for pending transactions (e.g. manual bank transfer checkout).
     */
    public function reserveStock(Product|int $product, string|Decimal $quantity): void
    {
        DB::transaction(function () use ($product, $quantity) {
            $balance = $this->lockBalance($product);
            $qty = Decimal::of($quantity, 3);

            if ($qty->isNegative() || $qty->isZero()) {
                throw new InvalidArgumentException('Kuantitas reservasi harus bernilai positif.');
            }

            $onHand = Decimal::of($balance->quantity_on_hand, 3);
            $reserved = Decimal::of($balance->quantity_reserved, 3);
            $available = $onHand->sub($reserved);

            if ($available->isLessThan($qty)) {
                $prodId = $product instanceof Product ? $product->id : $product;
                throw new InsufficientStockException(
                    message: "Stok tersedia tidak mencukupi untuk direservasi. Diminta: {$qty}, Tersedia: {$available}.",
                    productId: $prodId,
                    requested: $qty->toString(),
                    available: $available->toString()
                );
            }

            $newReserved = $reserved->add($qty);
            $balance->quantity_reserved = $newReserved->toString();
            $balance->version += 1;
            $balance->save();
        });
    }

    /**
     * Release previously reserved stock (e.g. transfer rejected/expired/cancelled).
     */
    public function releaseReservation(Product|int $product, string|Decimal $quantity): void
    {
        DB::transaction(function () use ($product, $quantity) {
            $balance = $this->lockBalance($product);
            $qty = Decimal::of($quantity, 3);

            $reserved = Decimal::of($balance->quantity_reserved, 3);
            $newReserved = $reserved->sub($qty);

            if ($newReserved->isNegative()) {
                $newReserved = Decimal::zero(3);
            }

            $balance->quantity_reserved = $newReserved->toString();
            $balance->version += 1;
            $balance->save();
        });
    }

    /**
     * Reconcile inventory balance projection against audit ledger (stock_movements).
     *
     * @return array{is_consistent: bool, balance_on_hand: string, ledger_on_hand: string, difference: string, total_in: string, total_out: string}
     */
    public function reconcile(Product|int $product): array
    {
        $productId = $product instanceof Product ? $product->id : $product;
        $balance = InventoryBalance::where('product_id', $productId)->first();
        $balanceOnHand = $balance ? Decimal::of($balance->quantity_on_hand, 3) : Decimal::zero(3);

        $totals = StockMovement::where('product_id', $productId)
            ->selectRaw('COALESCE(SUM(quantity_in), 0) as total_in, COALESCE(SUM(quantity_out), 0) as total_out')
            ->first();

        $totalIn = Decimal::of($totals->total_in ?? '0', 3);
        $totalOut = Decimal::of($totals->total_out ?? '0', 3);
        $ledgerOnHand = $totalIn->sub($totalOut);

        $diff = $balanceOnHand->sub($ledgerOnHand);
        $isConsistent = $diff->isZero();

        return [
            'is_consistent' => $isConsistent,
            'balance_on_hand' => $balanceOnHand->toString(),
            'ledger_on_hand' => $ledgerOnHand->toString(),
            'difference' => $diff->toString(),
            'total_in' => $totalIn->toString(),
            'total_out' => $totalOut->toString(),
        ];
    }
}
