<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     * Scope query to only include products that are out of stock.
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->doesntHave('inventoryBalance')
                ->orWhereHas('inventoryBalance', function (Builder $b) {
                    $b->where('quantity_on_hand', '<=', 0);
                });
        });
    }

    /**
     * Scope query to only include products with low stock.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('inventoryBalance', function (Builder $b) {
            $b->where('quantity_on_hand', '>', 0)
                ->whereColumn('inventory_balances.quantity_on_hand', '<=', 'products.minimum_stock');
        });
    }

    /**
     * Scope query to only include products with available stock.
     */
    public function scopeAvailableStock(Builder $query): Builder
    {
        return $query->whereHas('inventoryBalance', function (Builder $b) {
            $b->whereColumn('inventory_balances.quantity_on_hand', '>', 'products.minimum_stock');
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
     * Product has one inventory balance.
     */
    public function inventoryBalance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }

    /**
     * Product has many stock movements.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderBy('occurred_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Product has many cost histories.
     */
    public function costHistories(): HasMany
    {
        return $this->hasMany(ProductCostHistory::class)->orderBy('effective_at', 'desc')->orderBy('id', 'desc');
    }

    /**
     * Get current on-hand quantity.
     */
    public function getQuantityOnHandAttribute(): string
    {
        return $this->inventoryBalance?->quantity_on_hand ?? '0.000';
    }

    /**
     * Get current reserved quantity.
     */
    public function getQuantityReservedAttribute(): string
    {
        return $this->inventoryBalance?->quantity_reserved ?? '0.000';
    }

    /**
     * Get current available quantity.
     */
    public function getAvailableQuantityAttribute(): string
    {
        return $this->inventoryBalance?->available_quantity ?? '0.000';
    }

    /**
     * Get current average cost.
     */
    public function getAverageCostAttribute(): string
    {
        return $this->inventoryBalance?->average_cost ?? '0.00';
    }

    /**
     * Get current stock status code: 'out_of_stock', 'low_stock', or 'available'.
     */
    public function getStockStatusAttribute(): string
    {
        $onHand = Decimal::of($this->quantity_on_hand, 3);

        if ($onHand->isZero() || $onHand->isNegative()) {
            return 'out_of_stock';
        }

        $minStock = Decimal::of($this->minimum_stock ?? '0', 3);

        if ($onHand->isLessThanOrEqualTo($minStock)) {
            return 'low_stock';
        }

        return 'available';
    }

    /**
     * Get current stock status label.
     */
    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'out_of_stock' => 'Habis',
            'low_stock' => 'Menipis',
            default => 'Tersedia',
        };
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
