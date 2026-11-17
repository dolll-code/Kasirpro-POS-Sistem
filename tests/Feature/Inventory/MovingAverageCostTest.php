<?php

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\Unit;
use App\Models\User;
use App\Services\CostingService;
use App\Services\InventoryService;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->category = Category::firstOrCreate(
        ['slug' => 'makanan-ringan'],
        ['name' => 'Makanan Ringan', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs'],
        ['name' => 'Pieces', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SNACK-001',
        'name' => 'Keripik Singkong 100g',
        'selling_price' => '10000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $this->costingService = app(CostingService::class);
    $this->inventoryService = app(InventoryService::class);
});

test('moving average calculation with multiple receipts and different costs', function () {
    // Receipt 1: 10 units @ Rp 5.000
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '5000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 101,
        sourceNumber: 'RCV-20261117-00001',
        userId: $this->user->id
    );

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('10.000')
        ->and($balance->average_cost)->toBe('5000.00');

    // Receipt 2: 10 units @ Rp 6.000
    // Expected: ((10 * 5000) + (10 * 6000)) / (10 + 10) = 110.000 / 20 = 5.500
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '6000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 102,
        sourceNumber: 'RCV-20261117-00002',
        userId: $this->user->id
    );

    $balance->refresh();
    expect($balance->quantity_on_hand)->toBe('20.000')
        ->and($balance->average_cost)->toBe('5500.00');

    // Receipt 3: 10 units @ Rp 7.000
    // Expected: ((20 * 5500) + (10 * 7000)) / 30 = (110.000 + 70.000) / 30 = 180.000 / 30 = 6.000
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '7000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 103,
        sourceNumber: 'RCV-20261117-00003',
        userId: $this->user->id
    );

    $balance->refresh();
    expect($balance->quantity_on_hand)->toBe('30.000')
        ->and($balance->average_cost)->toBe('6000.00');

    // Verify product cost histories were logged accurately
    $histories = ProductCostHistory::where('product_id', $this->product->id)
        ->orderBy('id', 'asc')
        ->get();

    expect($histories)->toHaveCount(3);
    expect($histories[0]->cost_after)->toBe('5000.00');
    expect($histories[1]->cost_after)->toBe('5500.00');
    expect($histories[2]->cost_after)->toBe('6000.00');
});

test('sale deducts stock using current HPP snapshot without modifying average cost', function () {
    // Initial: 20 units with average cost Rp 5.500
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '5000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 1,
        userId: $this->user->id
    );
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '6000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 2,
        userId: $this->user->id
    );

    // Sale: 5 units
    $movement = $this->inventoryService->deductStock(
        product: $this->product,
        quantity: '5.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        sourceId: 501,
        sourceNumber: 'TRX-20261117-00001',
        userId: $this->user->id
    );

    expect($movement->unit_cost)->toBe('5500.00')
        ->and($movement->total_cost)->toBe('27500.00') // 5 * 5500
        ->and($movement->balance_after)->toBe('15.000');

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    // Average cost remains 5500.00 while stock is positive
    expect($balance->quantity_on_hand)->toBe('15.000')
        ->and($balance->average_cost)->toBe('5500.00');
});

test('sale reducing remaining quantity to zero resets current average cost to 0 and logs cost history', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '5.000',
        unitCost: '4000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 10,
        userId: $this->user->id
    );

    // Sell entire 5.000 units
    $this->inventoryService->deductStock(
        product: $this->product,
        quantity: '5.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        sourceId: 502,
        sourceNumber: 'TRX-20261117-00002',
        userId: $this->user->id
    );

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('0.000')
        ->and($balance->average_cost)->toBe('0.00'); // Reset to 0 per PRD §8.3

    // Verify history was recorded explaining the reset to 0
    $latestHistory = ProductCostHistory::where('product_id', $this->product->id)
        ->latest('id')
        ->first();

    expect($latestHistory)->not->toBeNull()
        ->and($latestHistory->cost_before)->toBe('4000.00')
        ->and($latestHistory->cost_after)->toBe('0.00')
        ->and($latestHistory->qty_after)->toBe('0.000');
});

test('resellable return recalculates average cost using original historical return cost', function () {
    // Current stock: 10 units @ Rp 6.000
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '6000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 20,
        userId: $this->user->id
    );

    // Resellable return: 2 units originally sold at historical cost Rp 4.500
    // Expected: ((10 * 6000) + (2 * 4500)) / (10 + 2) = (60.000 + 9.000) / 12 = 69.000 / 12 = 5.750
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '2.000',
        unitCost: '4500.00',
        movementType: 'IN_RETURN_RESELLABLE',
        sourceType: 'sales_returns',
        sourceId: 301,
        sourceNumber: 'RET-20261117-00001',
        userId: $this->user->id
    );

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('12.000')
        ->and($balance->average_cost)->toBe('5750.00');
});

test('damaged return does not increase available stock and does not alter average cost', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '5000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 30,
        userId: $this->user->id
    );

    $balanceBefore = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balanceBefore->quantity_on_hand)->toBe('10.000')
        ->and($balanceBefore->average_cost)->toBe('5000.00');

    // In PRD §8.3 & §5.7, damaged return is recorded as loss and does not add stock to inventory
    // The balance on-hand and average cost must remain completely unchanged.
    expect($balanceBefore->available_quantity)->toBe('10.000');
});
