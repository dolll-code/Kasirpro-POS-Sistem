<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;

beforeEach(function () {
    $this->adminRole = Role::where('code', 'admin')->first();
    $this->supervisorRole = Role::where('code', 'supervisor')->first();
    $this->cashierRole = Role::where('code', 'cashier')->first();

    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $this->supervisor = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'is_active' => true,
    ]);

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'minuman-dingin'],
        ['name' => 'Minuman Dingin', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'btl'],
        ['name' => 'Botol', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'TEH-BOTOL-450ML',
        'barcode' => '8991234567890',
        'name' => 'Teh Botol Sosro 450ml',
        'selling_price' => '6000.00',
        'minimum_stock' => '10.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
});

test('admin and supervisor can access inventory index and stock card', function () {
    $this->actingAs($this->admin)->get(route('inventory.index'))->assertOk();
    $this->actingAs($this->supervisor)->get(route('inventory.index'))->assertOk();

    $this->actingAs($this->admin)->get(route('inventory.card', $this->product))->assertOk();
    $this->actingAs($this->supervisor)->get(route('inventory.card', $this->product))->assertOk();
});

test('cashier without inventory.view receives 403 forbidden on inventory index and card', function () {
    $this->actingAs($this->cashier)->get(route('inventory.index'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('inventory.card', $this->product))->assertForbidden();
});

test('stock card displays chronological movements and reconciliation status', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '24.000',
        unitCost: '4500.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        sourceId: 1,
        sourceNumber: 'RCV-20261117-00001',
        userId: $this->admin->id
    );

    $this->inventoryService->deductStock(
        product: $this->product,
        quantity: '4.000',
        movementType: 'OUT_SALE',
        sourceType: 'sales',
        sourceId: 10,
        sourceNumber: 'TRX-20261117-00001',
        userId: $this->admin->id
    );

    $response = $this->actingAs($this->admin)->get(route('inventory.card', $this->product));
    $response->assertOk()
        ->assertSee('Teh Botol Sosro 450ml')
        ->assertSee('20') // Current on-hand
        ->assertSee('RCV-20261117-00001')
        ->assertSee('TRX-20261117-00001')
        ->assertSee('100% Konsisten');
});

test('inventory JSON API endpoints return structured response with metadata', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '15.000',
        unitCost: '4500.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->admin->id
    );

    // 1. Balances JSON
    $balancesRes = $this->actingAs($this->admin)->getJson('/api/v1/inventory/balances');
    $balancesRes->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'sku',
                    'name',
                    'quantity_on_hand',
                    'quantity_reserved',
                    'available_quantity',
                    'average_cost',
                    'stock_status',
                ],
            ],
            'meta' => ['page', 'per_page', 'total'],
        ]);

    // 2. Movements JSON
    $movementsRes = $this->actingAs($this->admin)->getJson('/api/v1/inventory/movements');
    $movementsRes->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['page', 'per_page', 'total'],
        ]);

    // 3. Reconcile JSON
    $reconcileRes = $this->actingAs($this->admin)->getJson("/api/v1/inventory/{$this->product->id}/reconcile");
    $reconcileRes->assertOk()
        ->assertJsonPath('data.is_consistent', true)
        ->assertJsonPath('data.balance_on_hand', '15.000');
});

test('product deletion guard returns 409 conflict when product has stock movements', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '5.000',
        unitCost: '4000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'purchase_receipts',
        userId: $this->admin->id
    );

    // Attempting to delete product through controller
    $response = $this->actingAs($this->admin)->delete(route('products.destroy', $this->product));
    $response->assertStatus(409);

    // Product still exists
    expect(Product::find($this->product->id))->not->toBeNull();
});
