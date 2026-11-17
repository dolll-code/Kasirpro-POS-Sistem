<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'movement_type',
        'source_type',
        'source_id',
        'source_number',
        'quantity_in',
        'quantity_out',
        'balance_before',
        'balance_after',
        'unit_cost',
        'total_cost',
        'notes',
        'created_by',
        'occurred_at',
        'created_at',
    ];

    protected $casts = [
        'quantity_in' => 'string',
        'quantity_out' => 'string',
        'balance_before' => 'string',
        'balance_after' => 'string',
        'unit_cost' => 'string',
        'total_cost' => 'string',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Enforce strict append-only ledger invariant.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Stock movements are immutable/append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Stock movements are immutable/append-only and cannot be deleted.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Return formatted human-readable movement type label.
     */
    public function getMovementTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            'IN_INITIAL' => 'Saldo Awal',
            'IN_PURCHASE' => 'Penerimaan Pembelian',
            'IN_RETURN_RESELLABLE' => 'Retur Penjualan (Layak Jual)',
            'IN_ADJUSTMENT' => 'Penyesuaian Masuk',
            'OUT_SALE' => 'Penjualan POS',
            'OUT_RETURN_SUPPLIER' => 'Retur ke Pemasok',
            'OUT_ADJUSTMENT' => 'Penyesuaian Keluar',
            'OUT_DAMAGED' => 'Barang Rusak/Loss',
            default => str_replace('_', ' ', $this->movement_type),
        };
    }

    /**
     * Return signed net quantity (+qty_in or -qty_out).
     */
    public function getNetQuantityAttribute(): string
    {
        $in = Decimal::of($this->quantity_in ?? '0', 3);
        $out = Decimal::of($this->quantity_out ?? '0', 3);

        return $in->sub($out)->toString();
    }
}
