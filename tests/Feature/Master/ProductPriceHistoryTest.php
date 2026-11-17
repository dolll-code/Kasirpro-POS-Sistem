<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->adminRole = Role::where('code', 'admin')->first();
    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $this->category = Category::create([
        'name' => 'Kategori Harga',
        'slug' => 'kategori-harga',
        'is_active' => true,
    ]);

    $this->unit = Unit::create([
        'name' => 'Pcs Harga',
        'symbol' => 'pcsh',
        'decimal_precision' => 0,
        'is_active' => true,
    ]);
});

test('creating product logs initial price history', function () {
    $this->actingAs($this->admin)->post(route('products.store'), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-PRC-01',
        'name' => 'Barang Harga Awal',
        'selling_price' => '12000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $product = Product::where('sku', 'SKU-PRC-01')->first();
    expect($product->priceHistories)->toHaveCount(1);

    $initialHistory = $product->priceHistories->first();
    expect((float) $initialHistory->old_price)->toEqual(0.00)
        ->and((float) $initialHistory->new_price)->toEqual(12000.00)
        ->and($initialHistory->changed_by)->toBe($this->admin->id);
});

test('updating product price records price history with old price, new price, actor and reason', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-PRC-02',
        'name' => 'Barang Uji Ubah Harga',
        'selling_price' => '10000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    // Initial record
    ProductPriceHistory::create([
        'product_id' => $product->id,
        'old_price' => '0.00',
        'new_price' => '10000.00',
        'changed_by' => $this->admin->id,
        'effective_at' => now()->subDay(),
        'reason' => 'Harga awal',
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->admin)->put(route('products.update', $product), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-PRC-02',
        'name' => 'Barang Uji Ubah Harga',
        'selling_price' => '12500.00', // Changed!
        'minimum_stock' => '2.000',
        'price_change_reason' => 'Penyesuaian biaya distributor',
    ]);

    $response->assertRedirect();
    $product->refresh();

    expect((float) $product->selling_price)->toEqual(12500.00)
        ->and($product->priceHistories)->toHaveCount(2);

    $latestHistory = $product->priceHistories->first(); // Ordered by effective_at desc
    expect((float) $latestHistory->old_price)->toEqual(10000.00)
        ->and((float) $latestHistory->new_price)->toEqual(12500.00)
        ->and($latestHistory->changed_by)->toBe($this->admin->id)
        ->and($latestHistory->reason)->toBe('Penyesuaian biaya distributor');
});

test('updating product without price change does not create new price history', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-PRC-03',
        'name' => 'Barang Nama Berubah',
        'selling_price' => '15000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    ProductPriceHistory::create([
        'product_id' => $product->id,
        'old_price' => '0.00',
        'new_price' => '15000.00',
        'changed_by' => $this->admin->id,
        'effective_at' => now(),
        'reason' => 'Harga awal',
        'created_at' => now(),
    ]);

    // Update only description
    $this->actingAs($this->admin)->put(route('products.update', $product), [
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-PRC-03',
        'name' => 'Barang Nama Berubah Baru',
        'description' => 'Deskripsi baru',
        'selling_price' => '15000.00', // Same price
        'minimum_stock' => '2.000',
    ]);

    $product->refresh();
    expect($product->priceHistories)->toHaveCount(1);
});
