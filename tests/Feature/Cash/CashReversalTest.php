<?php

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\User;
use App\Services\CashMovementService;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->account = CashAccount::create([
        'code' => 'BNK-TEST-REV',
        'name' => 'Bank Reversal Test',
        'account_type' => 'bank',
        'opening_balance' => '1000000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);

    $this->cashMovementService = app(CashMovementService::class);
    $this->cashMovementService->recordOpeningBalance($this->account, $this->user->id);
});

test('reversal of cash inflow creates compensating outflow movement and deducts balance', function () {
    // Inflow: 200.000
    $inflow = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '200000.00',
        movementType: 'MANUAL_INFLOW',
        sourceType: 'manual',
        sourceId: 1,
        userId: $this->user->id
    );

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('1200000.00');

    // Reverse inflow
    $reversal = $this->cashMovementService->reverseMovement(
        movement: $inflow,
        reason: 'Koreksi salah input nominal setoran',
        userId: $this->user->id
    );

    expect($reversal)->toBeInstanceOf(CashMovement::class)
        ->and($reversal->movement_type)->toBe('REVERSAL')
        ->and($reversal->direction)->toBe('out') // Reversal of in is out
        ->and($reversal->amount)->toBe('200000.00')
        ->and($reversal->balance_before)->toBe('1200000.00')
        ->and($reversal->balance_after)->toBe('1000000.00')
        ->and($reversal->reversal_of_id)->toBe($inflow->id);

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('1000000.00');
});

test('reversal of cash outflow creates compensating inflow movement and restores balance', function () {
    // Outflow: 300.000
    $outflow = $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '300000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 50,
        userId: $this->user->id
    );

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('700000.00');

    // Reverse outflow
    $reversal = $this->cashMovementService->reverseMovement(
        movement: $outflow,
        reason: 'Pembatalan transaksi beban',
        userId: $this->user->id
    );

    expect($reversal->direction)->toBe('in') // Reversal of out is in
        ->and($reversal->amount)->toBe('300000.00')
        ->and($reversal->balance_before)->toBe('700000.00')
        ->and($reversal->balance_after)->toBe('1000000.00');

    $this->account->refresh();
    expect($this->account->current_balance)->toBe('1000000.00');
});

test('reversing an already reversed movement is strictly rejected', function () {
    $outflow = $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '100000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 51,
        userId: $this->user->id
    );

    // First reversal succeeds
    $this->cashMovementService->reverseMovement(
        movement: $outflow,
        reason: 'Pertama kali dibalikkan',
        userId: $this->user->id
    );

    // Second reversal must fail
    expect(function () use ($outflow) {
        $this->cashMovementService->reverseMovement(
            movement: $outflow,
            reason: 'Coba balikkan kedua kali',
            userId: $this->user->id
        );
    })->toThrow(InvalidArgumentException::class, "Pergerakan kas ID [{$outflow->id}] sudah pernah dibalikkan (reversal).");
});

test('reversing a reversal movement itself is strictly rejected', function () {
    $outflow = $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '100000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 52,
        userId: $this->user->id
    );

    $reversal = $this->cashMovementService->reverseMovement(
        movement: $outflow,
        reason: 'Pembalikan sah',
        userId: $this->user->id
    );

    // Trying to reverse $reversal
    expect(function () use ($reversal) {
        $this->cashMovementService->reverseMovement(
            movement: $reversal,
            reason: 'Balikkan reversal',
            userId: $this->user->id
        );
    })->toThrow(InvalidArgumentException::class, 'Transaksi pembalikan (reversal) tidak dapat dibalikkan kembali.');
});

test('reversal of inflow fails when current balance is insufficient to absorb deduction', function () {
    // Inflow: 500.000 -> balance becomes 1.500.000
    $inflow = $this->cashMovementService->recordInflow(
        account: $this->account,
        amount: '500000.00',
        movementType: 'MANUAL_INFLOW',
        sourceType: 'manual',
        sourceId: 2,
        userId: $this->user->id
    );

    // Later, cash is spent: 1.200.000 -> balance becomes 300.000
    $this->cashMovementService->recordOutflow(
        account: $this->account,
        amount: '1200000.00',
        movementType: 'EXPENSE_PAYMENT',
        sourceType: 'expense_payments',
        sourceId: 53,
        userId: $this->user->id
    );

    // Trying to reverse original 500.000 inflow fails because only 300.000 is available
    expect(function () use ($inflow) {
        $this->cashMovementService->reverseMovement(
            movement: $inflow,
            reason: 'Reversal inflow 500rb',
            userId: $this->user->id
        );
    })->toThrow(InsufficientCashBalanceException::class);
});
