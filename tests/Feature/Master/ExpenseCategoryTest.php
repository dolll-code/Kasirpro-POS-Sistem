<?php

use App\Models\ExpenseCategory;
use App\Models\Role;
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

test('admin can view expense categories but unauthorized user receives 403', function () {
    $this->actingAs($this->admin)->get(route('expense-categories.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('expense-categories.index'))->assertForbidden();
});

test('admin can create expense category with unique name', function () {
    $response = $this->actingAs($this->admin)->post(route('expense-categories.store'), [
        'name' => 'Biaya Promosi Brosur',
        'description' => 'Cetak brosur promo bulanan',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('expense-categories.index'));
    $this->assertDatabaseHas('expense_categories', [
        'name' => 'Biaya Promosi Brosur',
        'is_active' => true,
    ]);

    // Duplicate name rejected
    $duplicateResponse = $this->actingAs($this->admin)->post(route('expense-categories.store'), [
        'name' => 'Biaya Promosi Brosur',
    ]);
    $duplicateResponse->assertSessionHasErrors(['name']);
});

test('admin can update and toggle expense category status', function () {
    $category = ExpenseCategory::create([
        'name' => 'Biaya Servis AC',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)->put(route('expense-categories.update', $category), [
        'name' => 'Biaya Servis AC & Listrik',
        'is_active' => false,
    ])->assertRedirect(route('expense-categories.index'));

    $category->refresh();
    expect($category->name)->toBe('Biaya Servis AC & Listrik')
        ->and($category->is_active)->toBeFalse();

    $this->actingAs($this->admin)->patch(route('expense-categories.toggle_status', $category))->assertRedirect();
    $category->refresh();
    expect($category->is_active)->toBeTrue();
});
