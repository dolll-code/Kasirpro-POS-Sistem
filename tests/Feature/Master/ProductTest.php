<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

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

    $this->category = Category::create([
        'name' => 'Minuman Test',
        'slug' => 'minuman-test',
        'is_active' => true,
    ]);

    $this->unit = Unit::create([
        'name' => 'Botol Test',
        'symbol' => 'btl',
        'decimal_precision' => 0,
        'is_active' => true,
    ]);
});

test('cashier can view products but cannot create products', function () {
    $this->actingAs($this->cashier)->get(route('products.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('products.create'))->assertForbidden();
    $this->actingAs($this->cashier)->post(route('products.store'), [])->assertForbidden();
});

test('admin can create product with normalized uppercase SKU and leading zero barcode preserved', function () {
    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => '  sku-abc-001  ', // Should be normalized to 'SKU-ABC-001'
        'barcode' => '001234567890', // Leading zeros preserved
        'name' => 'Teh Botol Kotak 250ml',
        'description' => 'Minuman teh melati dalam kemasan',
        'selling_price' => '4500.00',
        'minimum_stock' => '10.000',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $product = Product::where('sku', 'SKU-ABC-001')->first();

    expect($product)->not->toBeNull()
        ->and($product->sku)->toBe('SKU-ABC-001')
        ->and($product->barcode)->toBe('001234567890')
        ->and((float) $product->selling_price)->toEqual(4500.00)
        ->and((float) $product->minimum_stock)->toEqual(10.000);
});

test('multiple products with null barcode are permitted', function () {
    $p1 = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-NO-BAR-1',
        'barcode' => null,
        'name' => 'Barang Tanpa Barcode 1',
        'selling_price' => 5000,
        'minimum_stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-NO-BAR-2',
        'barcode' => '', // Empty string should be stored as NULL
        'name' => 'Barang Tanpa Barcode 2',
        'selling_price' => 7000,
        'minimum_stock' => 0,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $p2 = Product::where('sku', 'SKU-NO-BAR-2')->first();
    expect($p2->barcode)->toBeNull();
});

test('duplicate barcode is rejected', function () {
    Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-ORIGINAL',
        'barcode' => '899123456789',
        'name' => 'Barang Asli',
        'selling_price' => 5000,
        'minimum_stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-NEW',
        'barcode' => '899123456789',
        'name' => 'Barang Duplikat Barcode',
        'selling_price' => 6000,
        'minimum_stock' => 0,
    ]);

    $response->assertSessionHasErrors(['barcode']);
});

test('SKU uniqueness is enforced after normalization', function () {
    Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-UNIQUE-1',
        'name' => 'Barang Pertama',
        'selling_price' => 5000,
        'minimum_stock' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'sku-unique-1', // Lowercase of existing SKU
        'name' => 'Barang Duplikat SKU',
        'selling_price' => 6000,
        'minimum_stock' => 0,
    ]);

    $response->assertSessionHasErrors(['sku']);
});

test('negative price or minimum stock is rejected', function () {
    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-NEGATIVE',
        'name' => 'Barang Negatif',
        'selling_price' => -1000,
        'minimum_stock' => -5,
    ]);

    $response->assertSessionHasErrors(['selling_price', 'minimum_stock']);
});

test('cannot select inactive category or inactive unit for new product', function () {
    $inactiveCat = Category::create(['name' => 'Kategori Nonaktif', 'slug' => 'kat-nonaktif', 'is_active' => false]);
    $inactiveUnit = Unit::create(['name' => 'Satuan Nonaktif', 'symbol' => 'stn-nonaktif', 'decimal_precision' => 0, 'is_active' => false]);

    $response = $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $inactiveCat->id,
        'unit_id' => $inactiveUnit->id,
        'sku' => 'SKU-INVALID-REF',
        'name' => 'Barang Ref Nonaktif',
        'selling_price' => 5000,
        'minimum_stock' => 0,
    ]);

    $response->assertSessionHasErrors(['category_id', 'unit_id']);
});

test('product isSellable checks status of product and its parent category and unit', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-SELLABLE',
        'name' => 'Produk Uji Jual',
        'selling_price' => 10000,
        'minimum_stock' => 5,
        'is_active' => true,
    ]);

    expect($product->isSellable())->toBeTrue();

    // Inactive product cannot be sold
    $product->is_active = false;
    $product->save();
    expect($product->isSellable())->toBeFalse();

    // Re-active product
    $product->is_active = true;
    $product->save();

    // Inactive category prevents sale
    $this->category->is_active = false;
    $this->category->save();
    $product->refresh();
    expect($product->isSellable())->toBeFalse();

    // Restore category, deactivate unit
    $this->category->is_active = true;
    $this->category->save();
    $this->unit->is_active = false;
    $this->unit->save();
    $product->refresh();
    expect($product->isSellable())->toBeFalse();
});

test('product lookup endpoint returns only active sellable products matching query', function () {
    Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'LOOKUP-01',
        'barcode' => '89999901',
        'name' => 'Kopi Kapal Api Spesial 65g',
        'selling_price' => 3500,
        'minimum_stock' => 10,
        'is_active' => true,
    ]);

    Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'LOOKUP-02',
        'barcode' => '89999902',
        'name' => 'Kopi Luwak White Koffie',
        'selling_price' => 4000,
        'minimum_stock' => 10,
        'is_active' => false, // Inactive!
    ]);

    $response = $this->actingAs($this->cashier)->getJson('/api/v1/products/lookup?q=Kopi');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['sku'])->toBe('LOOKUP-01')
        ->and($data[0]['name'])->toBe('Kopi Kapal Api Spesial 65g');
});
