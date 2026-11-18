<?php

use App\Exceptions\DuplicateCashMovementException;
use App\Exceptions\InactiveCashAccountException;
use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\CashMovementService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->account = CashAccount::create([
        'code' => 'KAS-TEST-01',
        'name' => 'Kas Toko Uji Coba',
        'account_type' => 'cash',
        'opening_balance' => '500000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);

    $this->cashMovementService = app(CashMovementService::class);
});

test('opening balance movement is recorded and sets initial current_balance', function () {
    $movement = $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    expect($movement)->toBeInstanceOf(CashMovement::class)
        ->and($movement->movement_type)->toBe('OPENING_BALANCE')
        ->and($movement->direction)->toBe('in')
        ->and($movement->amount)->toBe('500000.00')
        ->and($movement->balance_before)->toBe('0.00')
        ->and($movement->balance_after)->toBe('500000.00');

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('500000.00');
});

test('recording duplicate opening balance movement is strictly rejected', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    // Second call must fail
    expect(function () {
        $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);
    })->toThrow(DuplicateCashMovementException::class);

    expect(CashMovement::where('cash_account_id', $this->account->id)->where('movement_type', 'OPENING_BALANCE')->count())->toBe(1);
});

test('cash inflow increases balance and creates append-only movement', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    $movement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '150000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 101,
        sourceNumber: 'TRX-20261118-00001',
        description: 'Penjualan tunai',
        userId: $this->user->id
    );

    expect($movement->direction)->toBe('in')
        ->and($movement->amount)->toBe('150000.00')
        ->and($movement->balance_before)->toBe('500000.00')
        ->and($movement->balance_after)->toBe('650000.00');

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('650000.00');
});

test('cash outflow deducts balance and creates append-only movement', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    $movement = $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '75000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 201,
        sourceNumber: 'EXP-20261118-00001',
        description: 'Beli ATK nota',
        userId: $this->user->id
    );

    expect($movement->direction)->toBe('out')
        ->and($movement->amount)->toBe('75000.00')
        ->and($movement->balance_before)->toBe('500000.00')
        ->and($movement->balance_after)->toBe('425000.00');

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('425000.00');
});

test('cash outflow exceeding available balance is rejected with InsufficientCashBalanceException and leaves balance intact', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    try {
        $this->cashMovementService->recordOutflow(
            account: $this->account,
            amount: '600000.00', // Greater than 500.000
            movementType: 'EXPENSE_PAYMENT',
            sourceType: 'expense_payments',
            sourceId: 202,
            userId: $this->user->id
        );
        $this->fail('Expected InsufficientCashBalanceException was not thrown.');
    } catch (InsufficientCashBalanceException $e) {
        expect($e->getCashAccountId())->toBe($this->account->id)
            ->and($e->getRequested())->toBe('600000.00')
            ->and($e->getAvailable())->toBe('500000.00');
    }

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('500000.00');
});

test('duplicate source transaction is rejected to guarantee idempotency', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    // First payment movement succeeds
    $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '100000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 555,
        userId: $this->user->id
    );

    // Second call with same source_type and source_id must be rejected
    expect(function () {
        $this->cashMovementService->recordInflow(
            account: $this->account,
            amount: '100000.00',
            movementType: 'SALE_PAYMENT',
            sourceType: 'sale_payments',
            sourceId: 555,
            userId: $this->user->id
        );
    })->toThrow(DuplicateCashMovementException::class);
});

test('inactive cash account is rejected for new transactions', function () {
    $this->account->is_active = false;
    $this->account->save();

    expect(function () {
        $this->cashMovementService->recordInflow(
            account: $this->account,
            amount: '50000.00',
            movementType: 'SALE_PAYMENT',
            sourceType: 'sale_payments',
            sourceId: 102,
            userId: $this->user->id
        );
    })->toThrow(InactiveCashAccountException::class);
});

test('cash movements are append-only and cannot be updated or deleted', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    $movement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '10000.00',
        movementType: 'MANUAL_INFLOW',
        sourceType: 'manual',
        userId: $this->user->id
    );

    // Updating throws RuntimeException
    expect(function () use ($movement) {
        $movement->amount = '20000.00';
        $movement->save();
    })->toThrow(RuntimeException::class, 'Cash movements are immutable/append-only and cannot be updated.');

    // Deleting throws RuntimeException
    expect(function () use ($movement) {
        $movement->delete();
    })->toThrow(RuntimeException::class, 'Cash movements are immutable/append-only and cannot be deleted.');
});

test('reconcile verifies account balance matches ledger calculation: balance = opening + inflow - outflow', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id); // +500.000

    $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '200000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 1,
        userId: $this->user->id
    ); // +200.000 => 700.000

    $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '50000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 1,
        userId: $this->user->id
    ); // -50.000 => 650.000

    $reconciliation = $this->cashMovementService->reconcile($this->account);

    expect($reconciliation['is_consistent'])->toBeTrue()
        ->and($reconciliation['current_balance'])->toBe('650000.00')
        ->and($reconciliation['ledger_balance'])->toBe('650000.00')
        ->and($reconciliation['total_in'])->toBe('700000.00')
        ->and($reconciliation['total_out'])->toBe('50000.00')
        ->and($reconciliation['difference'])->toBe('0.00');
});

test('transaction rolls back completely when an error occurs during cash mutation', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    expect(CashMovement::where('cash_account_id', $this->account->id)->count())->toBe(1);

    try {
        DB::transaction(function () {
            // Outflow succeeds
            $this->cashMovementService->recordOutflow(
                account: $this->account,
                amount: '50000.00',
                movementType: 'EXPENSE_PAYMENT',
                sourceType: 'expense_payments',
                sourceId: 99,
                userId: $this->user->id
            );

            // Simulated subsequent error
            throw new RuntimeException('Simulated crash during multi-table post');
        });
    } catch (RuntimeException $e) {
        // Handled
    }

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('500000.00')
        ->and(CashMovement::where('cash_account_id', $this->account->id)->count())->toBe(1);
});
