<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol',
        'decimal_precision',
        'is_active',
    ];

    protected $casts = [
        'decimal_precision' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope a query to only include active units.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Unit has many products.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if unit is referenced by any products.
     */
    public function isReferenced(): bool
    {
        return $this->products()->exists();
    }

    /**
     * Check if a given quantity complies with the unit's decimal precision.
     * Precision ranges from 0 to 3 decimal places.
     */
    public function isValidQuantity(int|float|string $quantity): bool
    {
        if (! is_numeric($quantity) || (float) $quantity < 0) {
            return false;
        }

        $str = (string) $quantity;
        $parts = explode('.', $str);

        if (count($parts) === 1) {
            return true;
        }

        $fractional = rtrim($parts[1], '0');

        return strlen($fractional) <= $this->decimal_precision;
    }
}
