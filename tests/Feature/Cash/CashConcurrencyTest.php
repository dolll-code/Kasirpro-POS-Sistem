<?php

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\CashMovementService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->account = CashAccount::create([
        'code' => 'KAS-CONCURRENCY-01',
        'name' => 'Kas Concurrency Testing',
        'account_type' => 'cash',
        'opening_balance' => '500000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);

    $this->cashMovementService = app(CashMovementService::class);
});

test('competing outflows exceeding available balance: first succeeds, second is rejected without overdraft', function () {
    // Initial opening balance = 500.000
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    $firstSucceeded = false;
    $secondFailed = false;

    // Transaction 1: attempts to deduct 400.000
    DB::transaction(function () use (&$firstSucceeded) {
        $this->cashMovementService->recordOutflow(
            account: $this->account,
            amount: '400000.00',
            movementType: 'EXPENSE_PAYMENT',
            sourceType: 'expense_payments',
            sourceId: 501,
            userId: $this->user->id
        );
        $firstSucceeded = true;
    });

    // Transaction 2: attempts to deduct 200.000 (only 100.000 remains)
    try {
        DB::transaction(function () {
            $this->cashMovementService->recordOutflow(
                account: $this->account,
                amount: '200000.00',
                movementType: 'EXPENSE_PAYMENT',
                sourceType: 'expense_payments',
                sourceId: 502,
                userId: $this->user->id
            );
        });
    } catch (InsufficientCashBalanceException $e) {
        $secondFailed = true;
        expect($e->getRequested())->toBe('200000.00')
            ->and($e->getAvailable())->toBe('100000.00');
    }

    expect($firstSucceeded)->toBeTrue();
    expect($secondFailed)->toBeTrue();

    // Verify balance remains exactly 100.000 and never negative
    $account = CashAccount::find($this->account->id);
    expect($account->current_balance)->toBe('100000.00');

    // Verify reconciliation is consistent
    $reconciliation = $this->cashMovementService->reconcile($account);
    expect($reconciliation['is_consistent'])->toBeTrue()
        ->and($reconciliation['current_balance'])->toBe('100000.00')
        ->and($reconciliation['ledger_balance'])->toBe('100000.00');
});

test('rapid interleaved inflows and outflows preserve exact ledger consistency', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id); // 500.000

    // Run 10 inflows of 25.000 (+250.000)
    for ($i = 1; $i <= 10; $i++) {
        $this->cashMovementService->recordInflow(
            account: $this->account,
            amount: '25000.00',
            movementType: 'SALE_PAYMENT',
            sourceType: 'sale_payments',
            sourceId: $i + 1000,
            userId: $this->user->id
        );
    }

    // Run 5 outflows of 50.000 (-250.000)
    for ($j = 1; $j <= 5; $j++) {
        $this->cashMovementService->recordOutflow(
            account: $this->account,
            amount: '50000.00',
            movementType: 'EXPENSE_PAYMENT',
            sourceType: 'expense_payments',
            sourceId: $j + 2000,
            userId: $this->user->id
        );
    }

    $account = CashAccount::find($this->account->id);
    // 500.000 + 250.000 - 250.000 = 500.000
    expect($account->current_balance)->toBe('500000.00');

    $reconciliation = $this->cashMovementService->reconcile($account);
    expect($reconciliation['is_consistent'])->toBeTrue()
        ->and($reconciliation['current_balance'])->toBe('500000.00')
        ->and($reconciliation['ledger_balance'])->toBe('500000.00')
        ->and($reconciliation['total_in'])->toBe('750000.00')
        ->and($reconciliation['total_out'])->toBe('250000.00')
        ->and($reconciliation['difference'])->toBe('0.00');
});

test('concurrent reversal attempts on the same movement prevent double reversal', function () {
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);

    $originalMovement = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '150000.00',
        movementType: 'SALE_PAYMENT',
        sourceType: 'sale_payments',
        sourceId: 9999,
        userId: $this->user->id
    );

    // First reversal succeeds
    $reversal = $this->cashMovementService->reverseMovement(
        movement: $originalMovement,
        reason: 'Kesalahan transaksi pertama',
        userId: $this->user->id
    );

    expect($reversal)->toBeInstanceOf(CashMovement::class)
        ->and($reversal->direction)->toBe('out')
        ->and($reversal->reversal_of_id)->toBe($originalMovement->id);

    // Second reversal attempt must fail
    expect(function () use ($originalMovement) {
        $this->cashMovementService->reverseMovement(
            movement: $originalMovement,
            reason: 'Percobaan pembalikan kedua',
            userId: $this->user->id
        );
    })->toThrow(InvalidArgumentException::class);

    // Ensure only 1 reversal movement exists
    expect(CashMovement::where('reversal_of_id', $originalMovement->id)->count())->toBe(1);

    // Balance should be back to 500.000
    $account = CashAccount::find($this->account->id);
    expect($account->current_balance)->toBe('500000.00');
});
