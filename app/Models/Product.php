<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'unit_id',
        'sku',
        'barcode',
        'name',
        'description',
        'selling_price',
        'minimum_stock',
        'is_active',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'minimum_stock' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    /**
     * Normalize SKU: uppercase and trimmed.
     */
    protected function sku(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? strtoupper(trim($value)) : $value
        );
    }

    /**
     * Normalize Barcode: preserve leading zeros, store null if empty.
     */
    protected function barcode(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => ($value !== null && trim((string) $value) !== '') ? trim((string) $value) : null
        );
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to search products by name, SKU, or barcode.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    /**
     * Product belongs to a Category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Product belongs to a Unit.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Product has many price history records.
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderBy('effective_at', 'desc');
    }

    /**
     * Check if product is currently sellable (active and both category & unit active).
     */
    public function isSellable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return (bool) ($this->category?->is_active && $this->unit?->is_active);
    }
}
