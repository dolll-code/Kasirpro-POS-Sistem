<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->category = Category::firstOrCreate(
        ['slug' => 'bahan-pokok'],
        ['name' => 'Bahan Pokok', 'is_active' => true]
    );

    // Unit with 0 decimal precision (e.g. integer pieces)
    $this->unitPcs = Unit::firstOrCreate(
        ['symbol' => 'bks'],
        ['name' => 'Bungkus', 'decimal_precision' => 0, 'is_active' => true]
    );

    // Unit with 3 decimal precision (e.g. weight in kilograms)
    $this->unitKg = Unit::firstOrCreate(
        ['symbol' => 'kg'],
        ['name' => 'Kilogram', 'decimal_precision' => 3, 'is_active' => true]
    );

    $this->productPcs = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'INDOMIE-GORENG',
        'name' => 'Indomie Goreng Spesial',
        'selling_price' => '3500.00',
        'minimum_stock' => '10.000',
        'is_active' => true,
    ]);

    $this->productKg = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitKg->id,
        'sku' => 'BERAS-PANDAN-WANGI',
        'name' => 'Beras Pandan Wangi Curah',
        'selling_price' => '15000.00',
        'minimum_stock' => '20.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
});

test('adding stock updates inventory balance and writes append-only movement', function () {
    $movement = $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '50.000',
        unitCost: '2800.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 1,
        sourceNumber: 'RCV-20261117-00001',
        userId: $this->user->id,
        notes: 'Penerimaan stok pertama'
    );

    expect($movement)->toBeInstanceOf(StockMovement::class)
        ->and($movement->quantity_in)->toBe('50.000')
        ->and($movement->quantity_out)->toBe('0.000')
        ->and($movement->balance_before)->toBe('0.000')
        ->and($movement->balance_after)->toBe('50.000');

    $balance = InventoryBalance::where('product_id', $this->productPcs->id)->first();
    expect($balance->quantity_on_hand)->toBe('50.000')
        ->and($balance->available_quantity)->toBe('50.000')
        ->and($balance->average_cost)->toBe('2800.00');
});

test('fractional quantity is strictly validated according to unit decimal precision', function () {
    // Product with 0 decimal precision rejects '1.500'
    expect(function () {
        $this->inventoryService->addStock(
            product: $this->productPcs,
            quantity: '1.500',
            unitCost: '2500.00',
            movementType: 'IN_PURCHASE',
            sourceType: 'purchase_receipts',
            userId: $this->user->id
        );
    })->toThrow(InvalidArgumentException::class);

    // Product with 3 decimal precision accepts '2.750'
    $movement = $this->inventoryService->addStock(
        product: $this->productKg,
        quantity: '2.750',
        unitCost: '14000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    expect($movement->quantity_in)->toBe('2.750');

    // Product with 3 decimal precision rejects 4 decimals '2.7555'
    expect(function () {
        $this->inventoryService->addStock(
            product: $this->productKg,
            quantity: '2.7555',
            unitCost: '14000.00',
            movementType: 'IN_PURCHASE',
            sourceType: 'purchase_receipts',
            userId: $this->user->id
        );
    })->toThrow(InvalidArgumentException::class);
});

test('deducting stock beyond available quantity is rejected with InsufficientStockException and does not change balance', function () {
    // Add 10 units
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '10.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    // Try to deduct 15 units
    try {
        $this->inventoryService->deductStock(
            product: $this->productPcs,
            quantity: '15.000',
            movementType: 'OUT_SALE',
            sourceType: 'sales',
            userId: $this->user->id
        );
        $this->fail('Expected InsufficientStockException was not thrown.');
    } catch (InsufficientStockException $e) {
        expect($e->getProductId())->toBe($this->productPcs->id)
            ->and($e->getRequested())->toBe('15.000')
            ->and($e->getAvailable())->toBe('10.000');
    }

    // Verify balance is unchanged
    $balance = InventoryBalance::where('product_id', $this->productPcs->id)->first();
    expect($balance->quantity_on_hand)->toBe('10.000');
});

test('stock reservation holds available quantity and releasing restores it', function () {
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '20.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    // Reserve 5 units (e.g. pending bank transfer checkout)
    $this->inventoryService->reserveStock($this->productPcs, '5.000');

    $balance = InventoryBalance::where('product_id', $this->productPcs->id)->first();
    expect($balance->quantity_on_hand)->toBe('20.000')
        ->and($balance->quantity_reserved)->toBe('5.000')
        ->and($balance->available_quantity)->toBe('15.000');

    // Trying to deduct 18 units fails because only 15 are available
    expect(function () {
        $this->inventoryService->deductStock(
            product: $this->productPcs,
            quantity: '18.000',
            movementType: 'OUT_SALE',
            sourceType: 'sales',
            userId: $this->user->id
        );
    })->toThrow(InsufficientStockException::class);

    // Release reservation
    $this->inventoryService->releaseReservation($this->productPcs, '5.000');

    $balance->refresh();
    expect($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('20.000');
});

test('reconcile verifies balance projection matches the sum of audit ledger movements', function () {
    // Add 100
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '100.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    // Deduct 25
    $this->inventoryService->deductStock(
        product: $this->productPcs,
        quantity: '25.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        userId: $this->user->id
    );

    // Deduct 15
    $this->inventoryService->deductStock(
        product: $this->productPcs,
        quantity: '15.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        userId: $this->user->id
    );

    $reconciliation = $this->inventoryService->reconcile($this->productPcs);

    expect($reconciliation['is_consistent'])->toBeTrue()
        ->and($reconciliation['balance_on_hand'])->toBe('60.000')
        ->and($reconciliation['ledger_on_hand'])->toBe('60.000')
        ->and($reconciliation['difference'])->toBe('0.000')
        ->and($reconciliation['total_in'])->toBe('100.000')
        ->and($reconciliation['total_out'])->toBe('40.000');
});

test('transaction rolls back completely when an error occurs during stock mutation', function () {
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '10.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    expect(StockMovement::where('product_id', $this->productPcs->id)->count())->toBe(1);

    try {
        DB::transaction(function () {
            // First deduct valid 3
            $this->inventoryService->deductStock(
                product: $this->productPcs,
                quantity: '3.000',
                movementType: 'OUT_SALE',
                sourceType: 'sales',
                userId: $this->user->id
            );

            // Then throw an artificial exception
            throw new RuntimeException('Simulated unexpected failure during checkout');
        });
    } catch (RuntimeException $e) {
        // Handled
    }

    // Verify rollback: balance remains 10 and movement count remains 1
    $balance = InventoryBalance::where('product_id', $this->productPcs->id)->first();
    expect($balance->quantity_on_hand)->toBe('10.000')
        ->and(StockMovement::where('product_id', $this->productPcs->id)->count())->toBe(1);
});

test('stock movements are append-only and cannot be updated or deleted', function () {
    $movement = $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '5.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    // Attempting to update throws RuntimeException
    expect(function () use ($movement) {
        $movement->quantity_in = '10.000';
        $movement->save();
    })->toThrow(RuntimeException::class, 'Stock movements are immutable/append-only and cannot be updated.');

    // Attempting to delete throws RuntimeException
    expect(function () use ($movement) {
        $movement->delete();
    })->toThrow(RuntimeException::class, 'Stock movements are immutable/append-only and cannot be deleted.');
});

test('stock status accurately reports out_of_stock, low_stock, and available', function () {
    // Minimum stock for productPcs is 10.000
    // Currently on_hand = 0 -> out_of_stock
    expect($this->productPcs->stock_status)->toBe('out_of_stock')
        ->and($this->productPcs->stock_status_label)->toBe('Habis');

    // Add 8 -> 0 < on_hand <= 10 -> low_stock
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '8.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    $this->productPcs->refresh();
    expect($this->productPcs->stock_status)->toBe('low_stock')
        ->and($this->productPcs->stock_status_label)->toBe('Menipis');

    // Add 10 more -> on_hand = 18 > 10 -> available
    $this->inventoryService->addStock(
        product: $this->productPcs,
        quantity: '10.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    $this->productPcs->refresh();
    expect($this->productPcs->stock_status)->toBe('available')
        ->and($this->productPcs->stock_status_label)->toBe('Tersedia');
});
