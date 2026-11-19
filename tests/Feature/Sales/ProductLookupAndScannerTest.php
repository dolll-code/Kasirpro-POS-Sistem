<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'minuman-kemasan-lookup'],
        ['name' => 'Minuman Kemasan Lookup', 'is_active' => true]
    );

    $this->unitPcs = Unit::firstOrCreate(
        ['symbol' => 'pcs-lookup'],
        ['name' => 'Pieces Lookup', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->productActive = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'SKU-KOPI-01',
        'barcode' => '8991234567890',
        'name' => 'Kopi Tubruk Robusta',
        'selling_price' => '15000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $this->productInactive = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'SKU-KOPI-OLD',
        'barcode' => '8999999999999',
        'name' => 'Kopi Kadaluwarsa Nonaktif',
        'selling_price' => '10000.00',
        'minimum_stock' => '0.000',
        'is_active' => false,
    ]);

    // Add initial stock to active product
    $inventoryService = app(InventoryService::class);
    $inventoryService->addStock(
        product: $this->productActive,
        quantity: '20.000',
        unitCost: '8000.00',
        movementType: 'IN_INITIAL',
        sourceType: 'opening_balance',
        sourceNumber: 'INIT-01',
        userId: $this->cashier->id
    );
});

test('product lookup finds active product by name, sku, or barcode', function () {
    $this->actingAs($this->cashier);

    // Search by Name
    $resName = $this->getJson('/api/v1/products/lookup?q=Tubruk');
    $resName->assertStatus(200);
    expect($resName->json('data'))->toHaveCount(1)
        ->and($resName->json('data.0.id'))->toBe($this->productActive->id)
        ->and($resName->json('data.0.name'))->toBe('Kopi Tubruk Robusta')
        ->and($resName->json('data.0.selling_price'))->toBe('15000.00')
        ->and($resName->json('data.0.available_quantity'))->toBe('20.000')
        ->and($resName->json('data.0.stock_status'))->toBe('available')
        ->and($resName->json('data.0.is_sellable'))->toBeTrue();

    // Search by SKU
    $resSku = $this->getJson('/api/v1/products/lookup?q=SKU-KOPI-01');
    $resSku->assertStatus(200);
    expect($resSku->json('data.0.id'))->toBe($this->productActive->id);

    // Search by Barcode
    $resBarcode = $this->getJson('/api/v1/products/lookup?q=8991234567890');
    $resBarcode->assertStatus(200);
    expect($resBarcode->json('data.0.id'))->toBe($this->productActive->id);
});

test('product lookup excludes inactive products', function () {
    $this->actingAs($this->cashier);

    $response = $this->getJson('/api/v1/products/lookup?q=Kadaluwarsa');
    $response->assertStatus(200);
    expect($response->json('data'))->toBeEmpty();
});

test('barcode scanner endpoint finds product by exact barcode and returns formatted payload', function () {
    $this->actingAs($this->cashier);

    $response = $this->getJson('/pos/barcode/8991234567890');
    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->productActive->id,
                'barcode' => '8991234567890',
                'sku' => 'SKU-KOPI-01',
                'name' => 'Kopi Tubruk Robusta',
                'selling_price' => '15000.00',
                'available_quantity' => '20.000',
                'is_sellable' => true,
            ],
        ]);
});

test('barcode scanner returns 404 with error message for unregistered barcode', function () {
    $this->actingAs($this->cashier);

    $response = $this->getJson('/pos/barcode/0000000000000');
    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'PRODUCT_NOT_FOUND',
            ],
        ]);
});

test('barcode scanner returns 404 for barcode of an inactive product', function () {
    $this->actingAs($this->cashier);

    $response = $this->getJson('/pos/barcode/8999999999999');
    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'PRODUCT_NOT_FOUND',
            ],
        ]);
});
