<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class CashMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'cash_account_id',
        'movement_type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'source_type',
        'source_id',
        'source_number',
        'reversal_of_id',
        'created_by',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'string',
        'balance_before' => 'string',
        'balance_after' => 'string',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Enforce strict append-only ledger invariant.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Cash movements are immutable/append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Cash movements are immutable/append-only and cannot be deleted.');
        });
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'reversal_of_id');
    }

    public function reversedBy(): HasOne
    {
        return $this->hasOne(CashMovement::class, 'reversal_of_id');
    }

    /**
     * Human-readable movement type label.
     */
    public function getMovementTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            'OPENING_BALANCE' => 'Saldo Awal',
            'SALE_PAYMENT' => 'Penerimaan Penjualan',
            'EXPENSE_PAYMENT' => 'Pembayaran Biaya Operasional',
            'SUPPLIER_PAYMENT' => 'Pembayaran Pemasok',
            'REFUND_PAYMENT' => 'Pengembalian Dana (Refund)',
            'MANUAL_INFLOW' => 'Pemasukan Kas Manual',
            'MANUAL_OUTFLOW' => 'Pengeluaran Kas Manual',
            'REVERSAL' => 'Pembalikan (Reversal)',
            default => str_replace('_', ' ', $this->movement_type),
        };
    }

    /**
     * Signed decimal amount (+amount if in, -amount if out).
     */
    public function getSignedAmountAttribute(): string
    {
        $amt = Decimal::of($this->amount, 2);

        return $this->direction === 'in' ? '+'.$amt->toString() : '-'.$amt->toString();
    }

    /**
     * Check if this movement has been reversed.
     */
    public function isReversed(): bool
    {
        return $this->reversedBy()->exists();
    }
}
