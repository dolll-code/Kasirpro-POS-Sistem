<?php

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Role;
use App\Models\User;
use App\Services\CashMovementService;

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

    $this->account = CashAccount::create([
        'code' => 'KAS-CTRL-01',
        'name' => 'Kas Controller Test',
        'account_type' => 'cash',
        'opening_balance' => '1000000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);

    $this->cashMovementService = app(CashMovementService::class);
    $this->cashMovementService->recordOpeningBalance($this->account, $this->admin->id);
});

test('authorized admin can view cash account details and movement list in buku kas', function () {
    $movement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '250000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 701,
        sourceNumber: 'INV-20261118-0001',
        description: 'Pembayaran penjualan kasir #01',
        userId: $this->admin->id
    );

    $response = $this->actingAs($this->admin)->get(route('cash-accounts.show', $this->account));

    $response->assertOk()
        ->assertSee('KAS-CTRL-01')
        ->assertSee('Kas Controller Test')
        ->assertSee('INV-20261118-0001')
        ->assertSee('Pembayaran penjualan kasir #01');
});

test('cashier without store_settings.manage permission is forbidden from viewing or modifying cash accounts', function () {
    $this->actingAs($this->cashier)->get(route('cash-accounts.show', $this->account))
        ->assertForbidden();

    $movement = CashMovement::where('cash_account_id', $this->account->id)->first();

    $this->actingAs($this->cashier)->post(route('cash-accounts.reverse_movement', [
        $this->account,
        $movement,
    ]), ['reason' => 'Unauthorized attempt'])->assertForbidden();
});

test('admin can filter movements by direction in buku kas', function () {
    $inflow = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '120000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 702,
        sourceNumber: 'INV-FILTER-IN',
        description: 'Kas Masuk Penjualan',
        userId: $this->admin->id
    );

    $outflow = $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '40000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 703,
        sourceNumber: 'EXP-FILTER-OUT',
        description: 'Kas Keluar Operasional',
        userId: $this->admin->id
    );

    $responseIn = $this->actingAs($this->admin)->get(route('cash-accounts.show', [
        'cash_account' => $this->account,
        'direction' => 'in',
    ]));

    $responseIn->assertOk()
        ->assertSee('INV-FILTER-IN')
        ->assertDontSee('EXP-FILTER-OUT');

    $responseOut = $this->actingAs($this->admin)->get(route('cash-accounts.show', [
        'cash_account' => $this->account,
        'direction' => 'out',
    ]));

    $responseOut->assertOk()
        ->assertSee('EXP-FILTER-OUT')
        ->assertDontSee('INV-FILTER-IN');
});

test('admin can reverse a movement with a valid reason and generate an audit log', function () {
    $movement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '75000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 704,
        sourceNumber: 'INV-REV-01',
        description: 'Transaksi salah input',
        userId: $this->admin->id
    );

    $response = $this->actingAs($this->admin)->post(route('cash-accounts.reverse_movement', [
        $this->account,
        $movement,
    ]), [
        'reason' => 'Koreksi kasir salah pilih metode pembayaran',
    ]);

    $response->assertRedirect(route('cash-accounts.show', $this->account));
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('cash_movements', [
        'cash_account_id' => $this->account->id,
        'movement_type' => 'REVERSAL',
        'direction' => 'out',
        'amount' => '75000.00',
        'reversal_of_id' => $movement->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'cash_movement.reversed',
        'module' => 'store_settings',
        'user_id' => $this->admin->id,
    ]);
});

test('reversal strictly requires a reason', function () {
    $movement = CashMovement::where('cash_account_id', $this->account->id)->first();

    $response = $this->actingAs($this->admin)->post(route('cash-accounts.reverse_movement', [
        $this->account,
        $movement,
    ]), [
        'reason' => '',
    ]);

    $response->assertSessionHasErrors(['reason']);
});

test('attempting duplicate reversal via controller returns error flash message', function () {
    $movement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '50000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 705,
        userId: $this->admin->id
    );

    // First reversal
    $this->actingAs($this->admin)->post(route('cash-accounts.reverse_movement', [
        $this->account,
        $movement,
    ]), ['reason' => 'First reversal'])->assertRedirect();

    // Second reversal attempt
    $response = $this->actingAs($this->admin)->post(route('cash-accounts.reverse_movement', [
        $this->account,
        $movement,
    ]), ['reason' => 'Second reversal']);

    $response->assertSessionHas('error');
});

test('reconcile endpoint returns structured json with consistency state', function () {
    $response = $this->actingAs($this->admin)->getJson(route('cash-accounts.reconcile', $this->account));

    $response->assertOk()
        ->assertJsonPath('cash_account_id', $this->account->id)
        ->assertJsonPath('data.is_consistent', true)
        ->assertJsonPath('data.current_balance', '1000000.00')
        ->assertJsonPath('data.ledger_balance', '1000000.00')
        ->assertJsonPath('data.difference', '0.00');
});

test('api movements endpoint returns paginated json list', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/cash-accounts/{$this->account->id}/movements");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'cash_account_id', 'movement_type', 'direction', 'amount', 'balance_before', 'balance_after'],
            ],
            'meta' => ['page', 'per_page', 'total'],
        ]);
});

test('account deletion is strictly rejected when movements exist in ledger', function () {
    // Opening balance movement already exists
    $response = $this->actingAs($this->admin)->delete(route('cash-accounts.destroy', $this->account));

    $response->assertStatus(409);
    $this->assertDatabaseHas('cash_accounts', ['id' => $this->account->id]);
});
