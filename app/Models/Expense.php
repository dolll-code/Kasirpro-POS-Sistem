<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'string',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
        'void_cash_refund_amount' => 'string',
        'void_cash_unrecovered_amount' => 'string',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Expense $expense) {
            if ($expense->status !== 'draft') {
                throw new RuntimeException('Expense posted/voided tidak dapat dihapus.');
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->orderBy('paid_at');
    }

    public function voidReversals(): HasMany
    {
        return $this->hasMany(ExpenseVoidReversal::class);
    }
}
