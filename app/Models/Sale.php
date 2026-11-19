<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class Sale extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'store_snapshot' => 'array',
        'gross_total' => 'string',
        'item_discount_total' => 'string',
        'transaction_discount_total' => 'string',
        'grand_total' => 'string',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Prevent deleting completed sales (PRD §1.5, §10.2)
        static::deleting(function (Sale $sale) {
            if ($sale->sale_status === 'completed') {
                throw new RuntimeException("Transaksi penjualan #{$sale->transaction_number} yang telah selesai tidak dapat dihapus.");
            }
        });
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(SalePayment::class);
    }

    public function isCompleted(): bool
    {
        return $this->sale_status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->sale_status === 'cancelled';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function totalQuantity(): string
    {
        return (string) $this->items->sum(fn ($i) => (float) $i->quantity);
    }

    public function totalDiscount(): string
    {
        return bcadd($this->item_discount_total ?? '0', $this->transaction_discount_total ?? '0', 2);
    }

    public function getSaleStatusBadgeAttribute(): string
    {
        return match ($this->sale_status) {
            'completed' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">Selesai</span>',
            'cancelled' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">Dibatalkan</span>',
            default => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">'.e($this->sale_status).'</span>',
        };
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Lunas</span>',
            'unpaid' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Belum Lunas</span>',
            'partially_refunded' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">Retur Parsial</span>',
            'refunded' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">Direfund Penuh</span>',
            default => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-800">'.e($this->payment_status).'</span>',
        };
    }
}
