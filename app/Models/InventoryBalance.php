<?php

namespace App\Models;

use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity_on_hand',
        'quantity_reserved',
        'average_cost',
        'version',
    ];

    protected $casts = [
        'quantity_on_hand' => 'string',
        'quantity_reserved' => 'string',
        'average_cost' => 'string',
        'version' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get available quantity (on_hand - reserved).
     */
    public function getAvailableQuantityAttribute(): string
    {
        $onHand = Decimal::of($this->quantity_on_hand ?? '0', 3);
        $reserved = Decimal::of($this->quantity_reserved ?? '0', 3);

        return $onHand->sub($reserved)->toString();
    }

    /**
     * Get stock status code: 'out_of_stock', 'low_stock', or 'available'.
     */
    public function getStockStatusAttribute(): string
    {
        $onHand = Decimal::of($this->quantity_on_hand ?? '0', 3);

        if ($onHand->isZero() || $onHand->isNegative()) {
            return 'out_of_stock';
        }

        $minStock = Decimal::of($this->product?->minimum_stock ?? '0', 3);

        if ($onHand->isLessThanOrEqualTo($minStock)) {
            return 'low_stock';
        }

        return 'available';
    }

    /**
     * Get human readable stock status label.
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
     * Calculate current inventory valuation (on_hand * average_cost).
     */
    public function getValuationAttribute(): string
    {
        $onHand = Decimal::of($this->quantity_on_hand ?? '0', 3);
        $avgCost = Decimal::of($this->average_cost ?? '0', 2);

        return $onHand->mul($avgCost, 2)->toString();
    }

    /**
     * Scope for products that are out of stock (on_hand = 0).
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', '<=', 0);
    }

    /**
     * Scope for products with low stock (0 < on_hand <= minimum_stock).
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('quantity_on_hand', '>', 0)
            ->whereHas('product', function (Builder $productQuery) {
                $productQuery->whereColumn('inventory_balances.quantity_on_hand', '<=', 'products.minimum_stock');
            });
    }

    /**
     * Scope for products with available stock (on_hand > minimum_stock).
     */
    public function scopeAvailableStock(Builder $query): Builder
    {
        return $query->whereHas('product', function (Builder $productQuery) {
            $productQuery->whereColumn('inventory_balances.quantity_on_hand', '>', 'products.minimum_stock');
        });
    }
}
