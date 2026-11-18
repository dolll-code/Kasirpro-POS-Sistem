<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_snapshot',
        'ordered_qty',
        'unit_cost',
        'line_discount',
        'allocated_document_discount',
        'accepted_qty_total',
        'rejected_qty_total',
        'cancelled_qty',
        'line_total',
    ];

    protected $casts = [
        'ordered_qty' => 'string',
        'unit_cost' => 'string',
        'line_discount' => 'string',
        'allocated_document_discount' => 'string',
        'accepted_qty_total' => 'string',
        'rejected_qty_total' => 'string',
        'cancelled_qty' => 'string',
        'line_total' => 'string',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    /**
     * Remaining unreceived quantity for this line.
     */
    public function remainingQty(): Decimal
    {
        $ordered = Decimal::of($this->ordered_qty, 3);
        $accepted = Decimal::of($this->accepted_qty_total ?? '0', 3);
        $cancelled = Decimal::of($this->cancelled_qty ?? '0', 3);

        $fulfilled = $accepted->add($cancelled);
        $remaining = $ordered->sub($fulfilled);

        return $remaining->isNegative() ? Decimal::zero(3) : $remaining;
    }

    public function isFullyReceived(): bool
    {
        return $this->remainingQty()->isZero();
    }

    /**
     * Effective PO line unit price after discounts.
     */
    public function effectiveUnitCost(): Decimal
    {
        $ordered = Decimal::of($this->ordered_qty, 3);
        if ($ordered->isZero()) {
            return Decimal::zero(2);
        }

        $lineTotal = Decimal::of($this->line_total, 2);

        return $lineTotal->div($ordered, 2);
    }
}
