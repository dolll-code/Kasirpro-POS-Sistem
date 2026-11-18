<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'purchase_item_id',
        'product_id',
        'physically_received_qty',
        'accepted_qty',
        'rejected_qty',
        'rejection_reason',
        'landed_unit_cost',
        'landed_total_cost',
        'notes',
    ];

    protected $casts = [
        'physically_received_qty' => 'string',
        'accepted_qty' => 'string',
        'rejected_qty' => 'string',
        'landed_unit_cost' => 'string',
        'landed_total_cost' => 'string',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Purchase receipt items are immutable and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('Purchase receipt items are immutable and cannot be deleted.');
        });
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
