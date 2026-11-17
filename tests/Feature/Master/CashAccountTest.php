<?php

use App\Models\CashAccount;
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

test('admin can manage cash accounts, while cashier is forbidden', function () {
    $this->actingAs($this->admin)->get(route('cash-accounts.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('cash-accounts.index'))->assertForbidden();
});

test('admin can create cash account with uppercase code and non-negative opening balance', function () {
    $response = $this->actingAs($this->admin)->post(route('cash-accounts.store'), [
        'code' => '  bnk-mandiri  ',
        'name' => 'Bank Mandiri Toko',
        'account_type' => 'bank',
        'opening_balance' => '1500000.00',
        'opening_balance_at' => '2026-11-01',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('cash-accounts.index'));
    $account = CashAccount::where('code', 'BNK-MANDIRI')->first();

    expect($account)->not->toBeNull()
        ->and($account->code)->toBe('BNK-MANDIRI')
        ->and($account->account_type)->toBe('bank')
        ->and((float) $account->opening_balance)->toEqual(1500000.00);
});

test('negative opening balance is rejected', function () {
    $response = $this->actingAs($this->admin)->post(route('cash-accounts.store'), [
        'code' => 'KAS-MINUS',
        'name' => 'Kas Minus',
        'account_type' => 'cash',
        'opening_balance' => -50000,
    ]);

    $response->assertSessionHasErrors(['opening_balance']);
});

test('cash account code must be unique', function () {
    CashAccount::create([
        'code' => 'KAS-DUP-01',
        'name' => 'Kas Asli',
        'account_type' => 'cash',
        'opening_balance' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin)->post(route('cash-accounts.store'), [
        'code' => 'kas-dup-01',
        'name' => 'Kas Duplikat',
        'account_type' => 'cash',
        'opening_balance' => 1000,
    ]);

    $response->assertSessionHasErrors(['code']);
});

test('admin can delete cash account when no movements exist', function () {
    $account = CashAccount::create([
        'code' => 'KAS-TMP-01',
        'name' => 'Kas Sementara',
        'account_type' => 'cash',
        'opening_balance' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)->delete(route('cash-accounts.destroy', $account))
        ->assertRedirect(route('cash-accounts.index'));

    $this->assertDatabaseMissing('cash_accounts', ['id' => $account->id]);
});
