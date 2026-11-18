<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'order_date',
        'expected_date',
        'subtotal',
        'item_discount_total',
        'document_discount_total',
        'additional_cost_estimate',
        'payable_total',
        'paid_amount',
        'status',
        'payment_status',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'string',
        'item_discount_total' => 'string',
        'document_discount_total' => 'string',
        'additional_cost_estimate' => 'string',
        'payable_total' => 'string',
        'paid_amount' => 'string',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isOrdered(): bool
    {
        return $this->status === 'ordered';
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === 'partially_received';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canEdit(): bool
    {
        return $this->isDraft();
    }

    public function canConfirm(): bool
    {
        return $this->isDraft();
    }

    public function canReceive(): bool
    {
        return in_array($this->status, ['ordered', 'partially_received']);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['draft', 'ordered', 'partially_received']);
    }

    /**
     * Total accepted payable based on posted receipts.
     */
    public function totalAcceptedPayable(): Decimal
    {
        $total = Decimal::zero(2);

        $receipts = $this->receipts()->where('status', 'posted')->with('items')->get();
        foreach ($receipts as $receipt) {
            foreach ($receipt->items as $item) {
                $total = $total->add($item->landed_total_cost);
            }
        }

        return $total;
    }

    /**
     * Outstanding payable = total accepted payable minus active non-reversed supplier payments.
     */
    public function outstandingPayable(): Decimal
    {
        $acceptedPayable = $this->totalAcceptedPayable();
        $paid = Decimal::of($this->paid_amount ?? '0', 2);

        $diff = $acceptedPayable->sub($paid);

        return $diff->isNegative() ? Decimal::zero(2) : $diff;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'ordered' => 'Dipesan (Ordered)',
            'partially_received' => 'Diterima Sebagian',
            'received' => 'Diterima Penuh',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800 border-gray-300',
            'ordered' => 'bg-blue-100 text-blue-800 border-blue-300',
            'partially_received' => 'bg-amber-100 text-amber-800 border-amber-300',
            'received' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300',
            default => 'bg-gray-100 text-gray-800 border-gray-300',
        };
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'unpaid' => 'Belum Dibayar',
            'partially_paid' => 'Dibayar Sebagian',
            'paid' => 'Lunas',
            default => ucfirst($this->payment_status),
        };
    }

    public function getPaymentStatusBadgeClassAttribute(): string
    {
        return match ($this->payment_status) {
            'unpaid' => 'bg-rose-100 text-rose-800 border-rose-300',
            'partially_paid' => 'bg-amber-100 text-amber-800 border-amber-300',
            'paid' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            default => 'bg-gray-100 text-gray-800 border-gray-300',
        };
    }
}
