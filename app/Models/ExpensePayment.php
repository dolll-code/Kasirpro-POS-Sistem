<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class ExpensePayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'string', 'paid_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (ExpensePayment $payment) {
            $dirty = array_keys($payment->getDirty());
            if ($payment->getOriginal('cash_movement_id') !== null || array_diff($dirty, ['cash_movement_id', 'updated_at']) !== []) {
                throw new RuntimeException('Expense payment bersifat immutable.');
            }
        });
        static::deleting(function () {
            throw new RuntimeException('Expense payment tidak dapat dihapus.');
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
