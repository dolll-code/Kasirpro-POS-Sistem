<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SaleItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'quantity' => 'string',
        'unit_price_snapshot' => 'string',
        'gross_line_total' => 'string',
        'line_discount' => 'string',
        'allocated_transaction_discount' => 'string',
        'net_line_total' => 'string',
        'cost_price_snapshot' => 'string',
        'cost_total_snapshot' => 'string',
    ];

    protected static function booted(): void
    {
        // Sale items are append-only/immutable snapshots once the sale is posted (PRD §8.1, §10.1).
        // A manual-transfer sale that is still pending_payment is a draft: only its HPP cost
        // snapshots may be filled in when payment verification posts the sale.
        static::updating(function (SaleItem $item) {
            $isPendingDraft = Sale::whereKey($item->sale_id)->value('sale_status') === 'pending_payment';
            $mutableWhilePending = ['cost_price_snapshot', 'cost_total_snapshot', 'updated_at'];

            if (! $isPendingDraft || array_diff(array_keys($item->getDirty()), $mutableWhilePending) !== []) {
                throw new RuntimeException('Item transaksi penjualan bersifat historis dan tidak dapat diubah.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('Item transaksi penjualan tidak dapat dihapus.');
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function totalDiscount(): string
    {
        return bcadd($this->line_discount ?? '0', $this->allocated_transaction_discount ?? '0', 2);
    }

    public function effectiveUnitSellingPrice(): string
    {
        if (bccomp($this->quantity, '0', 3) <= 0) {
            return '0.00';
        }

        return bcdiv($this->net_line_total, $this->quantity, 2);
    }
}
