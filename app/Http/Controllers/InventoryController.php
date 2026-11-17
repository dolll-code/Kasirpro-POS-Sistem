<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display inventory balances dashboard & list.
     */
    public function index(Request $request): View|JsonResponse
    {
        $status = $request->input('status');
        $categoryId = $request->input('category_id');
        $search = $request->input('search');

        $query = Product::query()
            ->with(['category:id,name', 'unit:id,name,symbol,decimal_precision', 'inventoryBalance']);

        if ($search) {
            $query->search($search);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($status === 'out_of_stock') {
            $query->outOfStock();
        } elseif ($status === 'low_stock') {
            $query->lowStock();
        } elseif ($status === 'available') {
            $query->availableStock();
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        // Summary counts
        $totalProducts = Product::count();
        $outOfStockCount = Product::outOfStock()->count();
        $lowStockCount = Product::lowStock()->count();
        $availableCount = Product::availableStock()->count();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $products->map(function (Product $p) {
                    return [
                        'id' => $p->id,
                        'sku' => $p->sku,
                        'barcode' => $p->barcode,
                        'name' => $p->name,
                        'category' => $p->category?->name,
                        'unit' => $p->unit?->symbol,
                        'quantity_on_hand' => $p->quantity_on_hand,
                        'quantity_reserved' => $p->quantity_reserved,
                        'available_quantity' => $p->available_quantity,
                        'average_cost' => $p->average_cost,
                        'stock_status' => $p->stock_status,
                        'stock_status_label' => $p->stock_status_label,
                    ];
                }),
                'meta' => [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ]);
        }

        $categories = Category::orderBy('name')->get();

        return view('inventory.index', compact(
            'products',
            'categories',
            'totalProducts',
            'outOfStockCount',
            'lowStockCount',
            'availableCount'
        ));
    }

    /**
     * Display stock card (kartu stok) for a specific product.
     */
    public function stockCard(Request $request, Product $product): View|JsonResponse
    {
        $product->load(['category', 'unit', 'inventoryBalance']);

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $movementType = $request->input('movement_type');

        $query = StockMovement::where('product_id', $product->id)
            ->with(['creator:id,name']);

        if ($fromDate) {
            $start = Carbon::parse($fromDate, 'Asia/Jakarta')->startOfDay();
            $query->where('occurred_at', '>=', $start);
        }

        if ($toDate) {
            $end = Carbon::parse($toDate, 'Asia/Jakarta')->endOfDay();
            $query->where('occurred_at', '<=', $end);
        }

        if ($movementType) {
            $query->where('movement_type', $movementType);
        }

        $movements = $query->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(30)
            ->withQueryString();

        $reconciliation = $this->inventoryService->reconcile($product);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'product' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'unit' => $product->unit?->symbol,
                    'quantity_on_hand' => $product->quantity_on_hand,
                    'available_quantity' => $product->available_quantity,
                    'average_cost' => $product->average_cost,
                ],
                'reconciliation' => $reconciliation,
                'data' => $movements->items(),
                'meta' => [
                    'page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ]);
        }

        return view('inventory.card', compact('product', 'movements', 'reconciliation'));
    }

    /**
     * API / Web reconcile endpoint for a product.
     */
    public function reconcile(Request $request, Product $product): JsonResponse
    {
        $reconciliation = $this->inventoryService->reconcile($product);

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'data' => $reconciliation,
            'meta' => [
                'request_id' => $request->header('X-Request-Id'),
            ],
        ]);
    }

    /**
     * API / View endpoint for low stock & out of stock products.
     */
    public function lowStock(Request $request): View|JsonResponse
    {
        $query = Product::query()
            ->where(function ($q) {
                $q->outOfStock()->orWhere->lowStock();
            })
            ->with(['category:id,name', 'unit:id,name,symbol,decimal_precision', 'inventoryBalance'])
            ->orderBy('name');

        $products = $query->paginate(20)->withQueryString();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $products->map(function (Product $p) {
                    return [
                        'id' => $p->id,
                        'sku' => $p->sku,
                        'name' => $p->name,
                        'category' => $p->category?->name,
                        'unit' => $p->unit?->symbol,
                        'quantity_on_hand' => $p->quantity_on_hand,
                        'available_quantity' => $p->available_quantity,
                        'minimum_stock' => (string) $p->minimum_stock,
                        'stock_status' => $p->stock_status,
                        'stock_status_label' => $p->stock_status_label,
                    ];
                }),
                'meta' => [
                    'page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ]);
        }

        return view('inventory.low-stock', compact('products'));
    }

    /**
     * API endpoint for all stock movements across products.
     */
    public function movements(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product:id,sku,name', 'creator:id,name']);

        if ($request->input('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->input('movement_type')) {
            $query->where('movement_type', $request->input('movement_type'));
        }

        if ($request->input('from_date')) {
            $start = Carbon::parse($request->input('from_date'), 'Asia/Jakarta')->startOfDay();
            $query->where('occurred_at', '>=', $start);
        }

        if ($request->input('to_date')) {
            $end = Carbon::parse($request->input('to_date'), 'Asia/Jakarta')->endOfDay();
            $query->where('occurred_at', '<=', $end);
        }

        $movements = $query->orderBy('occurred_at', 'desc')->paginate(20);

        return response()->json([
            'data' => $movements->items(),
            'meta' => [
                'page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'request_id' => $request->header('X-Request-Id'),
            ],
        ]);
    }
}
