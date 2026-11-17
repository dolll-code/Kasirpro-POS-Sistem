<?php

use App\Models\Role;
use App\Models\Supplier;
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
});

test('supervisor and admin can view suppliers, but cashier cannot', function () {
    $this->actingAs($this->admin)->get(route('suppliers.index'))->assertOk();
    $this->actingAs($this->supervisor)->get(route('suppliers.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('suppliers.index'))->assertForbidden();
});

test('admin can create supplier with uppercase supplier code', function () {
    $response = $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'supplier_code' => '  sup-tst-01  ',
        'name' => 'PT Pangan Mandiri Sentosa',
        'contact_person' => 'Agus Wijaya',
        'phone' => '081233445566',
        'email' => 'agus@panganmandiri.com',
        'address' => 'Jl. Industri No. 10',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('suppliers.index'));
    $supplier = Supplier::where('supplier_code', 'SUP-TST-01')->first();

    expect($supplier)->not->toBeNull()
        ->and($supplier->supplier_code)->toBe('SUP-TST-01')
        ->and($supplier->name)->toBe('PT Pangan Mandiri Sentosa')
        ->and($supplier->is_active)->toBeTrue();
});

test('supplier code must be unique', function () {
    Supplier::create([
        'supplier_code' => 'SUP-DUP-01',
        'name' => 'Supplier Asli',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('suppliers.store'), [
        'supplier_code' => 'sup-dup-01',
        'name' => 'Supplier Duplikat',
    ]);

    $response->assertSessionHasErrors(['supplier_code']);
});

test('inactive supplier cannot be used for new purchase orders', function () {
    $supplier = Supplier::create([
        'supplier_code' => 'SUP-INACT-01',
        'name' => 'Supplier Tutup',
        'is_active' => false,
    ]);

    expect($supplier->canBeOrderedFrom())->toBeFalse();

    $supplier->is_active = true;
    $supplier->save();
    expect($supplier->canBeOrderedFrom())->toBeTrue();
});

test('admin can toggle supplier status and delete supplier', function () {
    $supplier = Supplier::create([
        'supplier_code' => 'SUP-DEL-01',
        'name' => 'Supplier Uji Hapus',
        'is_active' => true,
    ]);

    // Toggle status
    $this->actingAs($this->admin)->patch(route('suppliers.toggle_status', $supplier))->assertRedirect();
    $supplier->refresh();
    expect($supplier->is_active)->toBeFalse();

    // Delete
    $this->actingAs($this->admin)->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});
