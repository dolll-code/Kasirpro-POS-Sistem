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

test('admin can view units list', function () {
    $response = $this->actingAs($this->admin)->get(route('units.index'));

    $response->assertOk();
    $response->assertViewIs('units.index');
});

test('cashier without catalog.manage receives 403 on units', function () {
    $response = $this->actingAs($this->cashier)->get(route('units.index'));

    $response->assertForbidden();
});

test('admin can create unit with decimal precision 0 to 3', function () {
    $response = $this->actingAs($this->admin)->post(route('units.store'), [
        'name' => 'Mililiter',
        'symbol' => 'ml',
        'decimal_precision' => 2,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('units.index'));
    $this->assertDatabaseHas('units', [
        'name' => 'Mililiter',
        'symbol' => 'ml',
        'decimal_precision' => 2,
        'is_active' => true,
    ]);
});

test('decimal precision must be between 0 and 3', function () {
    $response = $this->actingAs($this->admin)->post(route('units.store'), [
        'name' => 'Invalid Precision',
        'symbol' => 'inv',
        'decimal_precision' => 5, // Invalid > 3
    ]);

    $response->assertSessionHasErrors(['decimal_precision']);
});

test('unit name and symbol must be unique', function () {
    Unit::create([
        'name' => 'Meter',
        'symbol' => 'm',
        'decimal_precision' => 2,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('units.store'), [
        'name' => 'Meter',
        'symbol' => 'm',
        'decimal_precision' => 1,
    ]);

    $response->assertSessionHasErrors(['name', 'symbol']);
});

test('unit quantity precision validation helper works accurately', function () {
    $pcs = Unit::create(['name' => 'Pieces Test', 'symbol' => 'pcst2', 'decimal_precision' => 0]);
    $liter = Unit::create(['name' => 'Liter Test', 'symbol' => 'ltrt', 'decimal_precision' => 2]);
    $kg = Unit::create(['name' => 'Kg Test', 'symbol' => 'kgt', 'decimal_precision' => 3]);

    // Precision 0: only whole numbers
    expect($pcs->isValidQuantity('5'))->toBeTrue()
        ->and($pcs->isValidQuantity('5.0'))->toBeTrue()
        ->and($pcs->isValidQuantity('5.5'))->toBeFalse();

    // Precision 2: max 2 decimals
    expect($liter->isValidQuantity('1.25'))->toBeTrue()
        ->and($liter->isValidQuantity('1.250'))->toBeTrue()
        ->and($liter->isValidQuantity('1.255'))->toBeFalse();

    // Precision 3: max 3 decimals
    expect($kg->isValidQuantity('0.125'))->toBeTrue()
        ->and($kg->isValidQuantity('0.1234'))->toBeFalse();

    // Negative numbers: always false
    expect($kg->isValidQuantity('-1.5'))->toBeFalse();
});

test('unit referenced by products cannot be deleted and returns 409', function () {
    $category = Category::first() ?? Category::create([
        'name' => 'Kat Test Unit',
        'slug' => 'kat-test-unit',
        'is_active' => true,
    ]);

    $unit = Unit::create([
        'name' => 'Satuan Terikat',
        'symbol' => 'stn-t',
        'decimal_precision' => 0,
        'is_active' => true,
    ]);

    Product::create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'sku' => 'SKU-UNIT-01',
        'name' => 'Produk Terikat Satuan',
        'selling_price' => 15000,
        'minimum_stock' => 1,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('units.destroy', $unit));

    $response->assertStatus(409);
    $this->assertDatabaseHas('units', ['id' => $unit->id]);
});
