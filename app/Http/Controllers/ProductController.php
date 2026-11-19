<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreProductRequest;
use App\Http\Requests\Master\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        $query = Product::query()->with(['category', 'unit']);

        if (! empty($search)) {
            $query->search($search);
        }

        if (! empty($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query->orderBy('name')->paginate(15)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('products.index', compact('products', 'categories', 'search', 'categoryId', 'status'));
    }

    public function create(): View
    {
        $categories = Category::active()->orderBy('name')->get();
        $units = Unit::active()->orderBy('name')->get();

        return view('products.create', compact('categories', 'units'));
    }

    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->safe()->except(['on_hand_stock', 'average_cost']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $product = Product::create($validated);

        // Record initial price history
        ProductPriceHistory::create([
            'product_id' => $product->id,
            'old_price' => 0.00,
            'new_price' => $product->selling_price,
            'changed_by' => $request->user()?->id,
            'effective_at' => now(),
            'reason' => 'Harga awal produk baru',
            'created_at' => now(),
        ]);

        AuditLogger::log(
            action: 'product.created',
            module: 'catalog',
            reference: $product,
            newValues: $product->only(['sku', 'barcode', 'name', 'selling_price', 'minimum_stock', 'category_id', 'unit_id', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Produk baru berhasil ditambahkan.',
                'data' => $product->load(['category', 'unit']),
            ], 201);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Produk baru berhasil ditambahkan.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'unit', 'priceHistories.changer']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        // Load active categories and units, plus current ones even if deactivated
        $categories = Category::where('is_active', true)
            ->orWhere('id', $product->category_id)
            ->orderBy('name')
            ->get();

        $units = Unit::where('is_active', true)
            ->orWhere('id', $product->unit_id)
            ->orderBy('name')
            ->get();

        return view('products.edit', compact('product', 'categories', 'units'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        $oldValues = $product->only(['sku', 'barcode', 'name', 'selling_price', 'minimum_stock', 'category_id', 'unit_id', 'is_active']);
        $validated = $request->safe()->except(['on_hand_stock', 'average_cost', 'price_change_reason']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $oldPrice = (string) $product->selling_price;
        $newPrice = (string) $validated['selling_price'];

        $product->update($validated);

        // Check for price change
        if (bccomp($oldPrice, $newPrice, 2) !== 0) {
            ProductPriceHistory::create([
                'product_id' => $product->id,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'changed_by' => $request->user()?->id,
                'effective_at' => now(),
                'reason' => $request->input('price_change_reason') ?: 'Pembaruan harga master',
                'created_at' => now(),
            ]);

            AuditLogger::log(
                action: 'product.price_changed',
                module: 'catalog',
                reference: $product,
                oldValues: ['selling_price' => $oldPrice],
                newValues: [
                    'selling_price' => $newPrice,
                    'reason' => $request->input('price_change_reason'),
                ]
            );
        }

        AuditLogger::log(
            action: 'product.updated',
            module: 'catalog',
            reference: $product,
            oldValues: $oldValues,
            newValues: $product->only(['sku', 'barcode', 'name', 'selling_price', 'minimum_stock', 'category_id', 'unit_id', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data produk berhasil diperbarui.',
                'data' => $product->load(['category', 'unit']),
            ]);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Data produk berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        // Deletion guard: Check if referenced by transactions, price history, or stock movements
        if ($product->priceHistories()->count() > 1 || $product->stockMovements()->count() > 0) {
            $msg = 'Produk memiliki riwayat harga atau mutasi stok dan tidak dapat dihapus permanen. Gunakan nonaktifkan produk.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 409);
            }

            return back()->with('error', $msg)->setStatusCode(409);
        }

        $oldValues = $product->only(['sku', 'barcode', 'name', 'selling_price']);
        $product->delete();

        AuditLogger::log(
            action: 'product.deleted',
            module: 'catalog',
            reference: $product,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Produk berhasil dihapus.']);
        }

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $oldStatus = $product->is_active;
        $product->is_active = ! $product->is_active;
        $product->save();

        AuditLogger::log(
            action: 'product.status_toggled',
            module: 'catalog',
            reference: $product,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $product->is_active]
        );

        $message = $product->is_active
            ? 'Produk telah diaktifkan kembali dan dapat dijual.'
            : 'Produk telah dinonaktifkan dan tidak dapat dijual.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $product->is_active]);
        }

        return back()->with('success', $message);
    }

    /**
     * API lookup for POS and search suggestions.
     * Only returns active, sellable products.
     */
    public function lookup(Request $request): JsonResponse
    {
        $query = $request->input('q');

        if (empty($query) || strlen(trim($query)) < 1) {
            return response()->json(['data' => []]);
        }

        $term = trim($query);

        $products = Product::query()
            ->active()
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->whereHas('unit', fn ($q) => $q->where('is_active', true))
            ->search($term)
            ->with(['category:id,name,is_active', 'unit:id,name,symbol,decimal_precision,is_active', 'inventoryBalance'])
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 WHEN sku = ? THEN 1 WHEN name = ? THEN 2 ELSE 3 END', [$term, $term, $term])
            ->limit(20)
            ->get();

        $data = $products->map(function (Product $p) {
            return [
                'id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'selling_price' => (string) $p->selling_price,
                'category_name' => $p->category?->name,
                'unit' => [
                    'symbol' => $p->unit?->symbol,
                    'decimal_precision' => $p->unit?->decimal_precision ?? 0,
                ],
                'unit_symbol' => $p->unit?->symbol,
                'decimal_precision' => $p->unit?->decimal_precision ?? 0,
                'quantity_on_hand' => $p->quantity_on_hand,
                'quantity_reserved' => $p->quantity_reserved,
                'available_quantity' => $p->available_quantity,
                'stock_status' => $p->stock_status,
                'stock_status_label' => $p->stock_status_label,
                'is_sellable' => $p->isSellable(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
