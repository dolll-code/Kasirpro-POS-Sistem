<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\DocumentSequenceService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->category = Category::firstOrCreate(
        ['slug' => 'concurrency-category'],
        ['name' => 'Concurrency Category', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'box'],
        ['name' => 'Box', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'CONCURRENCY-ITEM-1',
        'name' => 'Item Concurrency Test',
        'selling_price' => '50000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
    $this->sequenceService = app(DocumentSequenceService::class);
});

test('concurrent document sequence generation produces strict sequential numbers without race condition duplicates', function () {
    $period = '20261117';
    $results = [];

    // Simulate 20 rapid generation calls in separate sub-transactions
    for ($i = 0; $i < 20; $i++) {
        $results[] = $this->sequenceService->generate('CONC', $period);
    }

    expect(count($results))->toBe(20)
        ->and(count(array_unique($results)))->toBe(20)
        ->and($results[0])->toBe('CONC-20261117-00001')
        ->and($results[19])->toBe('CONC-20261117-00020');
});

test('competing deductions on limited stock: first succeeds, second is rejected by row lock without overselling', function () {
    // Initial stock: 10 units
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '30000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    $firstSucceeded = false;
    $secondFailed = false;

    // First transaction tries to deduct 8 units
    DB::transaction(function () use (&$firstSucceeded) {
        $this->inventoryService->deductStock(
            product: $this->product,
            quantity: '8.000',
            movementType: 'OUT_SALE',
            sourceType: 'sales',
            sourceId: 101,
            userId: $this->user->id
        );
        $firstSucceeded = true;
    });

    // Second transaction tries to deduct 5 units (only 2 remain)
    try {
        DB::transaction(function () {
            $this->inventoryService->deductStock(
                product: $this->product,
                quantity: '5.000',
                movementType: 'OUT_SALE',
                sourceType: 'sales',
                sourceId: 102,
                userId: $this->user->id
            );
        });
    } catch (InsufficientStockException $e) {
        $secondFailed = true;
        expect($e->getRequested())->toBe('5.000')
            ->and($e->getAvailable())->toBe('2.000');
    }

    expect($firstSucceeded)->toBeTrue();
    expect($secondFailed)->toBeTrue();

    // Verify on-hand remains exactly 2.000 and never negative
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('2.000');
});

test('pessimistic row locking increments version accurately across multiple mutations', function () {
    // Add 10 -> version 1
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '20000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->user->id
    );

    // Deduct 2 -> version 2
    $this->inventoryService->deductStock(
        product: $this->product,
        quantity: '2.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        userId: $this->user->id
    );

    // Reserve 3 -> version 3
    $this->inventoryService->reserveStock($this->product, '3.000');

    // Release 1 -> version 4
    $this->inventoryService->releaseReservation($this->product, '1.000');

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->version)->toBeGreaterThanOrEqual(4)
        ->and($balance->quantity_on_hand)->toBe('8.000')
        ->and($balance->quantity_reserved)->toBe('2.000')
        ->and($balance->available_quantity)->toBe('6.000');
});
