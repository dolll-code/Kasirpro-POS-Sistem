<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    /**
     * Search active sellable products by query (name, SKU, barcode).
     *
     * @return Collection<int, Product>
     */
    public function searchSellableProducts(?string $query, ?int $categoryId = null, int $limit = 24): Collection
    {
        $q = Product::query()
            ->active()
            ->whereHas('category', fn ($c) => $c->where('is_active', true))
            ->whereHas('unit', fn ($u) => $u->where('is_active', true))
            ->with(['category:id,name,is_active', 'unit:id,name,symbol,decimal_precision,is_active', 'inventoryBalance']);

        if (! empty($categoryId)) {
            $q->where('category_id', $categoryId);
        }

        if (! empty($query)) {
            $term = trim($query);
            // Search query across name, sku, barcode
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });

            // Order exact matches first (exact barcode or SKU)
            $q->orderByRaw('CASE WHEN barcode = ? THEN 0 WHEN sku = ? THEN 1 WHEN name = ? THEN 2 ELSE 3 END', [$term, $term, $term]);
        }

        return $q->orderBy('name')->limit($limit)->get();
    }

    /**
     * Find an active product by exact barcode (USB scanner keyboard wedge support).
     */
    public function findByBarcode(string $barcode): ?Product
    {
        $trimmed = trim($barcode);
        if (empty($trimmed)) {
            return null;
        }

        return Product::query()
            ->active()
            ->where('barcode', $trimmed)
            ->whereHas('category', fn ($c) => $c->where('is_active', true))
            ->whereHas('unit', fn ($u) => $u->where('is_active', true))
            ->with(['category:id,name,is_active', 'unit:id,name,symbol,decimal_precision,is_active', 'inventoryBalance'])
            ->first();
    }

    /**
     * Format product data for JSON API / POS Alpine.js frontend.
     */
    public function formatProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'selling_price' => (string) $product->selling_price,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'unit_symbol' => $product->unit?->symbol,
            'decimal_precision' => $product->unit?->decimal_precision ?? 0,
            'quantity_on_hand' => $product->quantity_on_hand,
            'quantity_reserved' => $product->quantity_reserved,
            'available_quantity' => $product->available_quantity,
            'stock_status' => $product->stock_status,
            'stock_status_label' => $product->stock_status_label,
            'is_sellable' => $product->isSellable(),
        ];
    }
}
