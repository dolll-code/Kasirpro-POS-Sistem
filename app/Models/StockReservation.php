<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReservation extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReservationItem::class, 'reservation_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'active' && $this->expires_at->isPast());
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isConsumed(): bool
    {
        return $this->status === 'consumed';
    }
}
