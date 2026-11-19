<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SalePayment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'string',
        'cash_tendered' => 'string',
        'change_amount' => 'string',
        'verified_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Sale payment records are immutable once verified/paid
        static::updating(function (SalePayment $payment) {
            if ($payment->getOriginal('verification_status') === 'verified') {
                throw new RuntimeException('Catatan pembayaran kasir yang telah terverifikasi tidak dapat diubah langsung.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('Catatan pembayaran kasir tidak dapat dihapus.');
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
