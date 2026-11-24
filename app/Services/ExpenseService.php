<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\ExpenseVoidReversal;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected CashMovementService $cashMovementService,
    ) {}

    public function createDraft(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user) {
            $category = ExpenseCategory::whereKey($data['expense_category_id'])->firstOrFail();
            if (! $category->is_active) {
                throw new InvalidArgumentException('Kategori biaya tidak aktif.');
            }
            $amount = Decimal::of($data['amount'], 2);
            if (! $amount->isPositive()) {
                throw new InvalidArgumentException('Nominal expense harus lebih besar dari nol.');
            }

            $expense = Expense::create([
                'expense_number' => $this->sequenceService->generate('EXP'),
                'expense_category_id' => $category->id,
                'expense_date' => $data['expense_date'],
                'description' => trim($data['description']),
                'amount' => $amount->toString(),
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'payment_status' => 'unpaid',
                'is_landed_cost' => (bool) ($data['is_landed_cost'] ?? false),
                'landed_cost_reference' => $data['landed_cost_reference'] ?? null,
                'created_by' => $user->id,
            ]);

            AuditLogger::log(action: 'expense.created', module: 'expenses', reference: $expense, newValues: $expense->only(['expense_number', 'expense_category_id', 'expense_date', 'amount', 'description']));

            return $expense->load(['category', 'creator']);
        });
    }

    public function updateDraft(Expense|int $expense, array $data, User $user): Expense
    {
        $expenseId = $expense instanceof Expense ? $expense->id : $expense;

        return DB::transaction(function () use ($expenseId, $data, $user) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'draft') {
                throw new InvalidArgumentException('Hanya expense berstatus draft yang dapat diubah.');
            }
            $category = ExpenseCategory::whereKey($data['expense_category_id'])->firstOrFail();
            if (! $category->is_active) {
                throw new InvalidArgumentException('Kategori biaya tidak aktif.');
            }
            $amount = Decimal::of($data['amount'], 2);
            if (! $amount->isPositive()) {
                throw new InvalidArgumentException('Nominal expense harus lebih besar dari nol.');
            }
            $old = $expense->only(['expense_category_id', 'expense_date', 'description', 'amount', 'notes', 'is_landed_cost', 'landed_cost_reference']);
            $expense->fill([
                'expense_category_id' => $category->id,
                'expense_date' => $data['expense_date'],
                'description' => trim($data['description']),
                'amount' => $amount->toString(),
                'notes' => $data['notes'] ?? null,
                'is_landed_cost' => (bool) ($data['is_landed_cost'] ?? false),
                'landed_cost_reference' => $data['landed_cost_reference'] ?? null,
            ])->save();
            AuditLogger::log(action: 'expense.draft_updated', module: 'expenses', reference: $expense, oldValues: $old, newValues: $expense->only(array_keys($old)), userId: $user->id);

            return $expense->load(['category', 'creator']);
        });
    }

    public function post(Expense|int $expense, User $user): Expense
    {
        $expenseId = $expense instanceof Expense ? $expense->id : $expense;

        return DB::transaction(function () use ($expenseId, $user) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'draft') {
                throw new InvalidArgumentException('Hanya expense draft yang dapat diposting.');
            }
            $category = ExpenseCategory::whereKey($expense->expense_category_id)->lockForUpdate()->firstOrFail();
            if (! $category->is_active) {
                throw new InvalidArgumentException('Kategori biaya tidak aktif.');
            }
            if ($expense->is_landed_cost || filled($expense->landed_cost_reference)) {
                throw new InvalidArgumentException('Biaya landed cost harus diakui melalui receipt inventori, bukan operating expense.');
            }
            $expense->status = 'posted';
            $expense->posted_by = $user->id;
            $expense->posted_at = Carbon::now('Asia/Jakarta');
            $expense->save();
            AuditLogger::log(action: 'expense.posted', module: 'expenses', reference: $expense, oldValues: ['status' => 'draft'], newValues: ['status' => 'posted', 'amount' => $expense->amount, 'expense_date' => $expense->expense_date->toDateString(), 'posted_at' => $expense->posted_at->toIso8601String()], userId: $user->id);

            return $expense->load(['category', 'creator', 'poster']);
        });
    }

    public function pay(Expense|int $expense, CashAccount|int $account, string|Decimal $amount, User $user, string $idempotencyKey, ?string $reference = null, ?Carbon $paidAt = null): ExpensePayment
    {
        $expenseId = $expense instanceof Expense ? $expense->id : $expense;
        $accountId = $account instanceof CashAccount ? $account->id : $account;

        return DB::transaction(function () use ($expenseId, $accountId, $amount, $user, $idempotencyKey, $reference, $paidAt) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'posted') {
                throw new InvalidArgumentException('Pembayaran hanya dapat dicatat untuk expense posted.');
            }
            $paymentAmount = Decimal::of($amount, 2);
            if (! $paymentAmount->isPositive()) {
                throw new InvalidArgumentException('Nominal pembayaran harus lebih besar dari nol.');
            }
            $normalizedPaidAt = $paidAt ?? Carbon::now('Asia/Jakarta');
            $hash = hash('sha256', json_encode([
                'expense_id' => $expense->id,
                'cash_account_id' => $accountId,
                'amount' => $paymentAmount->toString(),
                'reference_number' => $reference,
                'paid_at' => $paidAt?->toIso8601String(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $existingPayment = ExpensePayment::where('idempotency_key', $idempotencyKey)->first();
            if ($existingPayment) {
                if ($existingPayment->payload_hash !== $hash) {
                    throw new InvalidArgumentException('EXPENSE_PAYMENT_IDEMPOTENCY_CONFLICT: key sudah digunakan dengan payload berbeda.');
                }

                return $existingPayment->load(['expense', 'cashAccount', 'cashMovement', 'creator']);
            }
            $paidTotal = Decimal::of((string) $expense->payments()->sum('amount'), 2);
            $outstanding = Decimal::of($expense->amount, 2)->sub($paidTotal);
            if ($paymentAmount->isGreaterThan($outstanding)) {
                throw new InvalidArgumentException('Nominal pembayaran melebihi outstanding expense.');
            }
            $payment = ExpensePayment::create([
                'payment_number' => $this->sequenceService->generate('EPM'),
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $hash,
                'expense_id' => $expense->id,
                'cash_account_id' => $accountId,
                'cash_movement_id' => null,
                'amount' => $paymentAmount->toString(),
                'reference_number' => $reference,
                'paid_at' => $normalizedPaidAt,
                'created_by' => $user->id,
            ]);
            $cashMovement = $this->cashMovementService->recordOutflow(
                account: $accountId,
                amount: $paymentAmount,
                movementType: 'EXPENSE_PAYMENT',
                sourceType: 'expense_payment',
                sourceId: $payment->id,
                sourceNumber: $expense->expense_number,
                description: "Pembayaran expense {$expense->expense_number}",
                userId: $user->id,
                occurredAt: $normalizedPaidAt,
                transactional: false
            );
            $payment->cash_movement_id = $cashMovement->id;
            $payment->save();
            $newPaid = $paidTotal->add($paymentAmount);
            $expense->payment_status = $newPaid->isGreaterThanOrEqualTo($expense->amount) ? 'paid' : 'partially_paid';
            $expense->save();
            AuditLogger::log(action: 'expense.payment_posted', module: 'expenses', reference: $expense, newValues: ['payment_number' => $payment->payment_number, 'amount' => $payment->amount, 'paid_at' => $payment->paid_at->toIso8601String(), 'cash_movement_id' => $cashMovement->id], userId: $user->id);

            return $payment->load(['expense', 'cashAccount', 'cashMovement', 'creator']);
        });
    }

    public function void(Expense|int $expense, string $reason, string|Decimal $cashRefundAmount, User $user): Expense
    {
        $expenseId = $expense instanceof Expense ? $expense->id : $expense;
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan void wajib diisi.');
        }

        return DB::transaction(function () use ($expenseId, $reason, $cashRefundAmount, $user) {
            $expense = Expense::whereKey($expenseId)->lockForUpdate()->firstOrFail();
            if ($expense->status !== 'posted') {
                throw new InvalidArgumentException('Hanya expense posted yang dapat di-void.');
            }
            $payments = $expense->payments()->with('cashMovement')->orderBy('id')->lockForUpdate()->get();
            $paidTotal = Decimal::zero(2);
            foreach ($payments as $payment) {
                $paidTotal = $paidTotal->add($payment->amount);
            }
            $refund = Decimal::of($cashRefundAmount, 2);
            if ($refund->isNegative() || $refund->isGreaterThan($paidTotal)) {
                throw new InvalidArgumentException('Nominal kas yang benar-benar dikembalikan harus antara nol dan total pembayaran aktual.');
            }

            $remaining = $refund;
            foreach ($payments as $payment) {
                if (! $remaining->isPositive()) {
                    break;
                }
                $reversalAmount = $remaining->isGreaterThan($payment->amount) ? Decimal::of($payment->amount, 2) : $remaining;
                $reversal = $this->cashMovementService->reverseMovement(
                    movement: $payment->cashMovement,
                    reason: "Void expense {$expense->expense_number}: {$reason}",
                    userId: $user->id,
                    amount: $reversalAmount
                );
                ExpenseVoidReversal::create([
                    'expense_id' => $expense->id,
                    'expense_payment_id' => $payment->id,
                    'original_cash_movement_id' => $payment->cash_movement_id,
                    'reversal_cash_movement_id' => $reversal->id,
                    'amount' => $reversalAmount->toString(),
                    'reason' => $reason,
                    'created_by' => $user->id,
                ]);
                $remaining = $remaining->sub($reversalAmount);
            }

            $expense->status = 'voided';
            $expense->void_reason = $reason;
            $expense->voided_by = $user->id;
            $expense->voided_at = Carbon::now('Asia/Jakarta');
            $expense->void_cash_refund_amount = $refund->toString();
            $expense->void_cash_unrecovered_amount = $paidTotal->sub($refund)->toString();
            $expense->save();
            AuditLogger::log(action: 'expense.voided', module: 'expenses', reference: $expense, oldValues: ['status' => 'posted'], newValues: ['status' => 'voided', 'reason' => $reason, 'cash_refund_actual' => $refund->toString(), 'cash_unrecovered' => $expense->void_cash_unrecovered_amount, 'voided_at' => $expense->voided_at->toIso8601String()], userId: $user->id);

            return $expense->load(['category', 'creator', 'poster', 'voider', 'payments.cashAccount', 'payments.cashMovement', 'voidReversals']);
        });
    }

    public function recognizedAmountBetween(Carbon $from, Carbon $to): string
    {
        $posted = (string) Expense::whereIn('status', ['posted', 'voided'])->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
        $voidedInPeriod = (string) Expense::where('status', 'voided')->whereBetween('voided_at', [$from, $to])->sum('amount');

        return Decimal::of($posted, 2)->sub($voidedInPeriod)->toString();
    }

    public function cashOutflowBetween(Carbon $from, Carbon $to): string
    {
        $paid = (string) ExpensePayment::whereBetween('paid_at', [$from, $to])->sum('amount');

        return Decimal::of($paid, 2)->toString();
    }

    public function cashReversalInflowBetween(Carbon $from, Carbon $to): string
    {
        $reversed = (string) ExpenseVoidReversal::whereBetween('created_at', [$from, $to])->sum('amount');

        return Decimal::of($reversed, 2)->toString();
    }

    public function list(): Collection
    {
        return Expense::with(['category', 'creator', 'poster'])->latest('created_at')->get();
    }
}
