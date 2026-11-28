<?php

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\ExpenseVoidReversal;
use App\Models\Role;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\ExpenseService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    $this->cashAccount = CashAccount::create([
        'code' => 'EXP-'.strtoupper(Str::random(6)),
        'name' => 'Akun Expense Test',
        'account_type' => 'cash',
        'opening_balance' => '10000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);
    app(CashMovementService::class)->recordOpeningBalance($this->cashAccount, $this->admin->id);
    $this->category = ExpenseCategory::create(['name' => 'Operasional '.Str::random(8), 'is_active' => true]);
    $this->expenseService = app(ExpenseService::class);
});

function createExpenseDraft($test, array $attributes = []): Expense
{
    return $test->expenseService->createDraft(array_merge([
        'expense_category_id' => $test->category->id,
        'expense_date' => now()->subDays(2)->toDateString(),
        'description' => 'Pembelian kebutuhan kantor',
        'amount' => '1000.00',
        'notes' => 'Operasional toko',
    ], $attributes), $test->admin);
}

test('draft expense can be edited and posting recognizes on expense_date, not posted_at', function () {
    $expense = createExpenseDraft($this);
    $expense = $this->expenseService->updateDraft($expense, [
        'expense_category_id' => $this->category->id,
        'expense_date' => now()->subDays(3)->toDateString(),
        'description' => 'Tagihan listrik toko',
        'amount' => '1200.00',
        'notes' => 'Updated draft',
    ], $this->admin);
    $posted = $this->expenseService->post($expense, $this->admin);

    expect($posted->status)->toBe('posted')
        ->and($posted->posted_by)->toBe($this->admin->id)
        ->and($posted->posted_at)->not->toBeNull()
        ->and($posted->expense_date->toDateString())->toBe(now()->subDays(3)->toDateString())
        ->and($this->expenseService->recognizedAmountBetween(now()->subDays(4), now()->subDays(2)))->toBe('1200.00')
        ->and(CashMovement::where('source_type', 'expense_payment')->count())->toBe(0);
});

test('invalid amounts, inactive category, and landed cost are rejected at posting', function () {
    expect(fn () => createExpenseDraft($this, ['amount' => '0.00']))
        ->toThrow(InvalidArgumentException::class, 'lebih besar dari nol');

    $expense = createExpenseDraft($this, ['is_landed_cost' => true]);
    expect(fn () => $this->expenseService->post($expense, $this->admin))
        ->toThrow(InvalidArgumentException::class, 'landed cost');

    $normalExpense = createExpenseDraft($this);
    $this->category->update(['is_active' => false]);
    expect(fn () => $this->expenseService->post($normalExpense, $this->admin))
        ->toThrow(InvalidArgumentException::class, 'tidak aktif');
});

test('expense payments support unpaid partial and full payment, cash date, and idempotent retries', function () {
    $expense = $this->expenseService->post(createExpenseDraft($this), $this->admin);
    expect($expense->payment_status)->toBe('unpaid');
    $paidAt = now()->addDay();
    $first = $this->expenseService->pay($expense, $this->cashAccount, '400.00', $this->admin, 'PAY-KEY-1', 'REF-1', $paidAt);
    expect($expense->fresh()->payment_status)->toBe('partially_paid')
        ->and($this->expenseService->cashOutflowBetween($paidAt->copy()->subMinute(), $paidAt->copy()->addMinute()))->toBe('400.00');

    $replay = $this->expenseService->pay($expense, $this->cashAccount, '400.00', $this->admin, 'PAY-KEY-1', 'REF-1', $paidAt);
    expect($replay->id)->toBe($first->id)
        ->and(ExpensePayment::where('idempotency_key', 'PAY-KEY-1')->count())->toBe(1)
        ->and(CashMovement::where('source_type', 'expense_payment')->count())->toBe(1);
    expect(fn () => $this->expenseService->pay($expense, $this->cashAccount, '401.00', $this->admin, 'PAY-KEY-1', 'REF-1', $paidAt))
        ->toThrow(InvalidArgumentException::class, 'IDEMPOTENCY_CONFLICT');

    $this->expenseService->pay($expense, $this->cashAccount, '600.00', $this->admin, 'PAY-KEY-2');
    expect($expense->fresh()->payment_status)->toBe('paid');
    expect(fn () => $this->expenseService->pay($expense, $this->cashAccount, '1.00', $this->admin, 'PAY-KEY-3'))
        ->toThrow(InvalidArgumentException::class, 'outstanding');
});

test('payment exceeding account balance fails without cash movement', function () {
    $expense = $this->expenseService->post(createExpenseDraft($this, ['amount' => '20000.00']), $this->admin);
    expect(fn () => $this->expenseService->pay($expense, $this->cashAccount, '20000.00', $this->admin, 'PAY-EMPTY'))
        ->toThrow(InsufficientCashBalanceException::class);
    expect(ExpensePayment::count())->toBe(0)
        ->and(CashMovement::where('source_type', 'expense_payment')->count())->toBe(0);
});

test('expense API exposes workflow and requires idempotency key for payment', function () {
    $this->actingAs($this->admin);
    $create = $this->postJson(route('api.expenses.store'), [
        'expense_category_id' => $this->category->id,
        'expense_date' => now()->toDateString(),
        'description' => 'API expense',
        'amount' => '500.00',
    ])->assertCreated();
    $expenseId = $create->json('data.id');
    $this->postJson(route('api.expenses.post', $expenseId))->assertOk()->assertJsonPath('data.status', 'posted');
    $this->postJson(route('api.expenses.payments.store', $expenseId), [
        'cash_account_id' => $this->cashAccount->id,
        'amount' => '100.00',
    ])->assertStatus(422)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    $this->postJson(route('api.expenses.payments.store', $expenseId), [
        'cash_account_id' => $this->cashAccount->id,
        'amount' => '100.00',
    ], ['Idempotency-Key' => 'API-EXPENSE-PAY-1'])->assertCreated();
    $this->getJson(route('api.expenses.show', $expenseId))->assertOk()->assertJsonPath('data.payment_status', 'partially_paid');
});

test('void reverses only actual cash returned and recognizes reversal on void date', function () {
    $expense = $this->expenseService->post(createExpenseDraft($this, ['amount' => '1000.00']), $this->admin);
    $paidAt = now()->subDay();
    $this->expenseService->pay($expense, $this->cashAccount, '1000.00', $this->admin, 'VOID-PAY', null, $paidAt);
    $cashBeforeVoid = (string) $this->cashAccount->fresh()->current_balance;

    $voided = $this->expenseService->void($expense, 'Tagihan dibatalkan pemasok', '250.00', $this->admin);
    expect($voided->status)->toBe('voided')
        ->and($voided->voided_by)->toBe($this->admin->id)
        ->and($voided->void_cash_refund_amount)->toBe('250.00')
        ->and($voided->void_cash_unrecovered_amount)->toBe('750.00')
        ->and($this->cashAccount->fresh()->current_balance)->toBe(bcadd($cashBeforeVoid, '250.00', 2))
        ->and(ExpenseVoidReversal::sum('amount'))->toBe('250.00')
        ->and($this->expenseService->recognizedAmountBetween(now()->subDays(3), now()->subDays(1)))->toBe('1000.00')
        ->and($this->expenseService->recognizedAmountBetween($voided->voided_at->copy()->subMinute(), $voided->voided_at->copy()->addMinute()))->toBe('-1000.00')
        ->and($this->expenseService->cashOutflowBetween($paidAt->copy()->subMinute(), $paidAt->copy()->addMinute()))->toBe('1000.00')
        ->and($this->expenseService->cashOutflowBetween(now()->subMinute(), now()->addMinute()))->toBe('0.00')
        ->and($this->expenseService->cashReversalInflowBetween(now()->subMinute(), now()->addMinute()))->toBe('250.00');
});
