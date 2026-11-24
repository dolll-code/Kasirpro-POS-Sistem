<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseVoidReversal extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'string', 'created_at' => 'datetime'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function expensePayment(): BelongsTo
    {
        return $this->belongsTo(ExpensePayment::class);
    }

    public function originalCashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'original_cash_movement_id');
    }

    public function reversalCashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class, 'reversal_cash_movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
