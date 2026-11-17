<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;

beforeEach(function () {
    $this->adminRole = Role::where('code', 'admin')->first();
    $this->cashierRole = Role::where('code', 'cashier')->first();

    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);
});

test('admin can view categories list', function () {
    $response = $this->actingAs($this->admin)->get(route('categories.index'));

    $response->assertOk();
    $response->assertViewIs('categories.index');
});

test('cashier without catalog.manage receives 403 on categories', function () {
    $response = $this->actingAs($this->cashier)->get(route('categories.index'));

    $response->assertForbidden();
});

test('admin can create category with auto-generated slug', function () {
    $response = $this->actingAs($this->admin)->post(route('categories.store'), [
        'name' => 'Minuman Bersoda',
        'description' => 'Kategori minuman berkarbonasi',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('categories.index'));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('categories', [
        'name' => 'Minuman Bersoda',
        'slug' => 'minuman-bersoda',
        'is_active' => true,
    ]);
});

test('category name and slug must be unique', function () {
    Category::create([
        'name' => 'Elektronik Toko',
        'slug' => 'elektronik-toko',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('categories.store'), [
        'name' => 'Elektronik Toko',
        'slug' => 'elektronik-toko',
    ]);

    $response->assertSessionHasErrors(['name', 'slug']);
});

test('admin can update category', function () {
    $category = Category::create([
        'name' => 'Biskuit Lama',
        'slug' => 'biskuit-lama',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->put(route('categories.update', $category), [
        'name' => 'Biskuit Baru',
        'slug' => 'biskuit-baru',
        'description' => 'Deskripsi diperbarui',
        'is_active' => false,
    ]);

    $response->assertRedirect(route('categories.index'));

    $category->refresh();
    expect($category->name)->toBe('Biskuit Baru')
        ->and($category->slug)->toBe('biskuit-baru')
        ->and($category->is_active)->toBeFalse();
});

test('admin can toggle category active status', function () {
    $category = Category::create([
        'name' => 'Roti Kering',
        'slug' => 'roti-kering',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->patch(route('categories.toggle_status', $category));

    $response->assertRedirect();
    $category->refresh();
    expect($category->is_active)->toBeFalse();
});

test('admin can delete category when not referenced', function () {
    $category = Category::create([
        'name' => 'Kategori Kosong',
        'slug' => 'kategori-kosong',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('categories.destroy', $category));

    $response->assertRedirect(route('categories.index'));
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('category referenced by products cannot be deleted and returns 409', function () {
    $category = Category::create([
        'name' => 'Kategori Terikat',
        'slug' => 'kategori-terikat',
        'is_active' => true,
    ]);

    $unit = Unit::first() ?? Unit::create([
        'name' => 'Pcs Test',
        'symbol' => 'pcst',
        'decimal_precision' => 0,
        'is_active' => true,
    ]);

    Product::create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'sku' => 'SKU-KAT-01',
        'name' => 'Produk Terikat Kategori',
        'selling_price' => 10000,
        'minimum_stock' => 5,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('categories.destroy', $category));

    $response->assertStatus(409);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});
