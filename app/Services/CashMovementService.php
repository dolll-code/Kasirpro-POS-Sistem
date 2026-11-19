<?php

namespace App\Services;

use App\Exceptions\DuplicateCashMovementException;
use App\Exceptions\InactiveCashAccountException;
use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Support\Decimal;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashMovementService
{
    /**
     * Validate cash account and check if it is active.
     */
    public function validateAccount(CashAccount|int $account, bool $requireActive = true): CashAccount
    {
        $acc = $account instanceof CashAccount ? $account : CashAccount::findOrFail($account);

        if ($requireActive && ! $acc->is_active) {
            throw new InactiveCashAccountException(
                message: "Akun kas/bank [{$acc->name}] sedang nonaktif dan tidak dapat digunakan untuk transaksi baru.",
                cashAccountId: $acc->id
            );
        }

        return $acc;
    }

    /**
     * Lock the cash account row with pessimistic write lock (SELECT ... FOR UPDATE).
     */
    public function lockAccount(CashAccount|int $account, bool $requireActive = true): CashAccount
    {
        $acc = $this->validateAccount($account, $requireActive);

        return CashAccount::where('id', $acc->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Record opening balance movement during authorized setup.
     * Prevents duplicate opening balance movements.
     */
    public function recordOpeningBalance(CashAccount|int $account, ?int $userId = null): ?CashMovement
    {
        return DB::transaction(function () use ($account, $userId) {
            $acc = $this->lockAccount($account, requireActive: false);

            // Guard: check if opening balance movement already exists
            $alreadyExists = CashMovement::where('cash_account_id', $acc->id)
                ->where('movement_type', 'OPENING_BALANCE')
                ->exists();

            if ($alreadyExists) {
                throw new DuplicateCashMovementException(
                    message: "Saldo awal untuk akun [{$acc->name}] sudah pernah dibukukan.",
                    sourceType: 'opening_balance',
                    sourceId: $acc->id
                );
            }

            $openingAmount = Decimal::of($acc->opening_balance ?? '0', 2);

            if ($openingAmount->isZero() || $openingAmount->isNegative()) {
                // If opening balance is 0, initialize current_balance to 0.00
                $acc->current_balance = '0.00';
                $acc->save();

                return;
            }

            $before = Decimal::zero(2);
            $after = $openingAmount;

            $acc->current_balance = $after->toString();
            $acc->save();

            return CashMovement::create([
                'cash_account_id' => $acc->id,
                'movement_type' => 'OPENING_BALANCE',
                'direction' => 'in',
                'amount' => $openingAmount->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'description' => 'Saldo awal pembukuan akun kas/bank',
                'source_type' => 'opening_balance',
                'source_id' => $acc->id,
                'source_number' => 'OPENING-'.$acc->code,
                'reversal_of_id' => null,
                'created_by' => $userId,
                'occurred_at' => $acc->opening_balance_at ?? Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        });
    }

    /**
     * Record cash inflow atomically within a database transaction.
     */
    public function recordInflow(
        CashAccount|int $account,
        string|Decimal $amount,
        string $movementType,
        string $sourceType,
        ?int $sourceId = null,
        ?string $sourceNumber = null,
        ?string $description = null,
        ?int $userId = null,
        ?DateTimeInterface $occurredAt = null,
        bool $transactional = true
    ): CashMovement {
        $record = function () use (
            $account,
            $amount,
            $movementType,
            $sourceType,
            $sourceId,
            $sourceNumber,
            $description,
            $userId,
            $occurredAt
        ) {
            $acc = $this->lockAccount($account, requireActive: true);
            $amt = Decimal::of($amount, 2);

            if ($amt->isNegative() || $amt->isZero()) {
                throw new InvalidArgumentException('Nominal pemasukan kas harus bernilai positif (> 0).');
            }

            // Idempotency check: each source document creates at most one movement
            if ($sourceId !== null) {
                $duplicate = CashMovement::where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->exists();

                if ($duplicate) {
                    throw new DuplicateCashMovementException(
                        message: "Transaksi [{$sourceType} #{$sourceId}] telah memiliki catatan pergerakan kas dan tidak dapat diposting ganda.",
                        sourceType: $sourceType,
                        sourceId: $sourceId
                    );
                }
            }

            $before = Decimal::of($acc->current_balance ?? '0', 2);
            $after = $before->add($amt);

            $acc->current_balance = $after->toString();
            $acc->save();

            return CashMovement::create([
                'cash_account_id' => $acc->id,
                'movement_type' => $movementType,
                'direction' => 'in',
                'amount' => $amt->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'description' => $description,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_number' => $sourceNumber,
                'reversal_of_id' => null,
                'created_by' => $userId,
                'occurred_at' => $occurredAt ?? Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        };

        return $transactional ? DB::transaction($record) : $record();
    }

    /**
     * Record cash outflow atomically within a database transaction.
     * Rejects transactions that would cause a negative balance.
     */
    public function recordOutflow(
        CashAccount|int $account,
        string|Decimal $amount,
        string $movementType,
        string $sourceType,
        ?int $sourceId = null,
        ?string $sourceNumber = null,
        ?string $description = null,
        ?int $userId = null,
        ?DateTimeInterface $occurredAt = null,
        bool $transactional = true
    ): CashMovement {
        $record = function () use (
            $account,
            $amount,
            $movementType,
            $sourceType,
            $sourceId,
            $sourceNumber,
            $description,
            $userId,
            $occurredAt
        ) {
            $acc = $this->lockAccount($account, requireActive: true);
            $amt = Decimal::of($amount, 2);

            if ($amt->isNegative() || $amt->isZero()) {
                throw new InvalidArgumentException('Nominal pengeluaran kas harus bernilai positif (> 0).');
            }

            // Idempotency check: each source document creates at most one movement
            if ($sourceId !== null) {
                $duplicate = CashMovement::where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->exists();

                if ($duplicate) {
                    throw new DuplicateCashMovementException(
                        message: "Transaksi [{$sourceType} #{$sourceId}] telah memiliki catatan pergerakan kas dan tidak dapat diposting ganda.",
                        sourceType: $sourceType,
                        sourceId: $sourceId
                    );
                }
            }

            $before = Decimal::of($acc->current_balance ?? '0', 2);

            if ($before->isLessThan($amt)) {
                throw new InsufficientCashBalanceException(
                    message: "Saldo kas/bank tidak mencukupi untuk akun [{$acc->name}]. Diminta: {$amt}, Tersedia: {$before}.",
                    cashAccountId: $acc->id,
                    requested: $amt->toString(),
                    available: $before->toString()
                );
            }

            $after = $before->sub($amt);

            if ($after->isNegative()) {
                throw new InsufficientCashBalanceException(
                    message: "Pengeluaran kas menyebabkan saldo minus pada akun [{$acc->name}].",
                    cashAccountId: $acc->id,
                    requested: $amt->toString(),
                    available: $before->toString()
                );
            }

            $acc->current_balance = $after->toString();
            $acc->save();

            return CashMovement::create([
                'cash_account_id' => $acc->id,
                'movement_type' => $movementType,
                'direction' => 'out',
                'amount' => $amt->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'description' => $description,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_number' => $sourceNumber,
                'reversal_of_id' => null,
                'created_by' => $userId,
                'occurred_at' => $occurredAt ?? Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        };

        return $transactional ? DB::transaction($record) : $record();
    }

    /**
     * Reverse an existing cash movement by creating a compensating reversal entry.
     */
    public function reverseMovement(CashMovement|int $movement, string $reason, ?int $userId = null): CashMovement
    {
        return DB::transaction(function () use ($movement, $reason, $userId) {
            $mov = $movement instanceof CashMovement ? $movement : CashMovement::findOrFail($movement);

            if ($mov->movement_type === 'REVERSAL') {
                throw new InvalidArgumentException('Transaksi pembalikan (reversal) tidak dapat dibalikkan kembali.');
            }

            $alreadyReversed = CashMovement::where('reversal_of_id', $mov->id)->exists();
            if ($alreadyReversed) {
                throw new InvalidArgumentException("Pergerakan kas ID [{$mov->id}] sudah pernah dibalikkan (reversal).");
            }

            $acc = $this->lockAccount($mov->cash_account_id, requireActive: true);
            $amt = Decimal::of($mov->amount, 2);
            $before = Decimal::of($acc->current_balance ?? '0', 2);

            // If original was IN, reversal is OUT (deducts cash)
            if ($mov->direction === 'in') {
                if ($before->isLessThan($amt)) {
                    throw new InsufficientCashBalanceException(
                        message: "Saldo akun [{$acc->name}] tidak mencukupi untuk pembalikan kas masuk. Diminta: {$amt}, Tersedia: {$before}.",
                        cashAccountId: $acc->id,
                        requested: $amt->toString(),
                        available: $before->toString()
                    );
                }
                $after = $before->sub($amt);
                $newDirection = 'out';
            } else {
                // If original was OUT, reversal is IN (restores cash)
                $after = $before->add($amt);
                $newDirection = 'in';
            }

            $acc->current_balance = $after->toString();
            $acc->save();

            return CashMovement::create([
                'cash_account_id' => $acc->id,
                'movement_type' => 'REVERSAL',
                'direction' => $newDirection,
                'amount' => $amt->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'description' => "Pembalikan transaksi #{$mov->id} ({$mov->movement_type}): {$reason}",
                'source_type' => 'reversal',
                'source_id' => $mov->id,
                'source_number' => 'REV-'.($mov->source_number ?? $mov->id),
                'reversal_of_id' => $mov->id,
                'created_by' => $userId,
                'occurred_at' => Carbon::now('Asia/Jakarta'),
                'created_at' => Carbon::now('Asia/Jakarta'),
            ]);
        });
    }

    /**
     * Get current projected balance for an account.
     */
    public function calculateBalance(CashAccount|int $account): string
    {
        $acc = $account instanceof CashAccount ? ($account->fresh() ?? $account) : CashAccount::findOrFail($account);

        return $acc->current_balance ?? '0.00';
    }

    /**
     * Reconcile current account balance against the audit ledger (sum of all cash movements).
     *
     * @return array{is_consistent: bool, current_balance: string, ledger_balance: string, total_in: string, total_out: string, difference: string}
     */
    public function reconcile(CashAccount|int $account): array
    {
        $acc = $account instanceof CashAccount ? ($account->fresh() ?? $account) : CashAccount::findOrFail($account);
        $currentBalance = Decimal::of($acc->current_balance ?? '0', 2);

        $totals = CashMovement::where('cash_account_id', $acc->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->first();

        $totalIn = Decimal::of($totals->total_in ?? '0', 2);
        $totalOut = Decimal::of($totals->total_out ?? '0', 2);
        $ledgerBalance = $totalIn->sub($totalOut);

        $diff = $currentBalance->sub($ledgerBalance);
        $isConsistent = $diff->isZero();

        return [
            'is_consistent' => $isConsistent,
            'current_balance' => $currentBalance->toString(),
            'ledger_balance' => $ledgerBalance->toString(),
            'total_in' => $totalIn->toString(),
            'total_out' => $totalOut->toString(),
            'difference' => $diff->toString(),
        ];
    }
}
