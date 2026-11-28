<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashAdjustment;
use App\Models\User;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashAdjustmentService
{
    // MVP: kategori tetap agar adjustment tidak diperlakukan sebagai omzet/biaya otomatis.
    public const CATEGORIES = ['koreksi_kas', 'koreksi_bank', 'setoran_modal', 'penarikan_prive', 'lain_lain'];

    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected CashMovementService $cashMovementService,
    ) {}

    public function request(array $data, User $user): CashAdjustment
    {
        return DB::transaction(function () use ($data, $user) {
            $account = CashAccount::whereKey($data['cash_account_id'])->firstOrFail();
            if (! $account->is_active) {
                throw new InvalidArgumentException('Akun kas/bank tidak aktif.');
            }
            $amount = Decimal::of($data['amount'], 2);
            if (! $amount->isPositive()) {
                throw new InvalidArgumentException('Nominal adjustment harus > 0.');
            }
            if (! in_array($data['direction'], ['in', 'out'], true)) {
                throw new InvalidArgumentException('Arah adjustment harus in atau out.');
            }
            if (! in_array($data['category'], self::CATEGORIES, true)) {
                throw new InvalidArgumentException('Kategori adjustment tidak valid.');
            }
            if (trim((string) $data['reason']) === '') {
                throw new InvalidArgumentException('Alasan adjustment wajib diisi.');
            }

            $adjustment = CashAdjustment::create([
                'adjustment_number' => $this->sequenceService->generate('ADJ'),
                'cash_account_id' => $account->id,
                'direction' => $data['direction'],
                'category' => $data['category'],
                'amount' => $amount->toString(),
                'reason' => trim($data['reason']),
                'status' => 'pending_approval',
                'requested_by' => $user->id,
                'requested_at' => Carbon::now('Asia/Jakarta'),
            ]);
            AuditLogger::log(action: 'cash_adjustment.requested', module: 'cash', reference: $adjustment, newValues: $adjustment->only(['adjustment_number', 'cash_account_id', 'direction', 'category', 'amount', 'reason']), userId: $user->id);

            return $adjustment->load(['cashAccount', 'requester']);
        });
    }

    public function decide(CashAdjustment|int $adjustment, User $approver, string $decision, ?string $note = null, ?string $overrideReason = null): CashAdjustment
    {
        $id = $adjustment instanceof CashAdjustment ? $adjustment->id : $adjustment;
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Keputusan harus approved atau rejected.');
        }

        return DB::transaction(function () use ($id, $approver, $decision, $note, $overrideReason) {
            $adjustment = CashAdjustment::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== 'pending_approval') {
                throw new InvalidArgumentException('Adjustment tidak menunggu approval.');
            }
            $isOwn = (int) $adjustment->requested_by === (int) $approver->id;
            $override = trim((string) $overrideReason);
            if ($isOwn && ! ($approver->hasRole('admin') && $override !== '')) {
                throw new AuthorizationException('Maker-checker: pembuat adjustment tidak boleh menyetujui sendiri; Admin override wajib alasan.');
            }
            if ($decision === 'rejected' && trim((string) $note) === '') {
                throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
            }
            if ($decision === 'rejected') {
                $adjustment->status = 'rejected';
                $adjustment->approved_by = $approver->id;
                $adjustment->rejection_reason = trim((string) $note);
                $adjustment->rejected_at = Carbon::now('Asia/Jakarta');
                $adjustment->save();
                AuditLogger::log(action: 'cash_adjustment.rejected', module: 'cash', reference: $adjustment, newValues: ['reason' => $adjustment->rejection_reason], userId: $approver->id);

                return $adjustment->load(['cashAccount', 'requester', 'approver']);
            }

            $movement = $adjustment->direction === 'in'
                ? $this->cashMovementService->recordInflow(
                    account: $adjustment->cash_account_id,
                    amount: $adjustment->amount,
                    movementType: 'CASH_ADJUSTMENT_IN',
                    sourceType: 'cash_adjustment',
                    sourceId: $adjustment->id,
                    sourceNumber: $adjustment->adjustment_number,
                    description: "Adjustment {$adjustment->category}: {$adjustment->reason}",
                    userId: $approver->id,
                    transactional: false
                )
                : $this->cashMovementService->recordOutflow(
                    account: $adjustment->cash_account_id,
                    amount: $adjustment->amount,
                    movementType: 'CASH_ADJUSTMENT_OUT',
                    sourceType: 'cash_adjustment',
                    sourceId: $adjustment->id,
                    sourceNumber: $adjustment->adjustment_number,
                    description: "Adjustment {$adjustment->category}: {$adjustment->reason}",
                    userId: $approver->id,
                    transactional: false
                );

            $adjustment->status = 'posted';
            $adjustment->approved_by = $approver->id;
            $adjustment->approval_note = $note;
            $adjustment->admin_override_reason = $override !== '' ? $override : null;
            $adjustment->approved_at = Carbon::now('Asia/Jakarta');
            $adjustment->posted_at = Carbon::now('Asia/Jakarta');
            $adjustment->cash_movement_id = $movement->id;
            $adjustment->save();
            AuditLogger::log(action: 'cash_adjustment.posted', module: 'cash', reference: $adjustment, newValues: ['status' => 'posted', 'cash_movement_id' => $movement->id, 'amount' => $adjustment->amount], userId: $approver->id);

            return $adjustment->load(['cashAccount', 'requester', 'approver', 'cashMovement']);
        });
    }

    public function cancel(CashAdjustment|int $adjustment, User $user, string $reason): CashAdjustment
    {
        $id = $adjustment instanceof CashAdjustment ? $adjustment->id : $adjustment;
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($id, $user, $reason) {
            $adjustment = CashAdjustment::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($adjustment->status !== 'pending_approval') {
                throw new InvalidArgumentException('Hanya adjustment pending yang dapat dibatalkan.');
            }
            if ((int) $adjustment->requested_by !== (int) $user->id && ! $user->hasRole('admin') && ! $user->hasRole('supervisor')) {
                throw new AuthorizationException('Hanya pembuat atau supervisor/admin yang dapat membatalkan.');
            }
            $adjustment->status = 'cancelled';
            $adjustment->rejection_reason = trim($reason);
            $adjustment->rejected_at = Carbon::now('Asia/Jakarta');
            $adjustment->save();
            AuditLogger::log(action: 'cash_adjustment.cancelled', module: 'cash', reference: $adjustment, newValues: ['reason' => trim($reason)], userId: $user->id);

            return $adjustment->load(['cashAccount', 'requester']);
        });
    }
}
