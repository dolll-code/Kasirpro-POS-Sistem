<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'purchase_id',
        'received_at',
        'capitalizable_additional_cost',
        'status',
        'notes',
        'received_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'posted_at' => 'datetime',
        'capitalizable_additional_cost' => 'string',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Purchase receipts are immutable/append-only and cannot be updated after posting.');
        });

        static::deleting(function () {
            throw new RuntimeException('Purchase receipts are immutable/append-only and cannot be deleted.');
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'receipt_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
