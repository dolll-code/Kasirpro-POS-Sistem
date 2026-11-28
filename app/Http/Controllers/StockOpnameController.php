<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class StockOpnameController extends Controller
{
    public function __construct(protected StockOpnameService $stockOpnameService) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = StockOpname::with(['creator:id,name', 'starter:id,name', 'approver:id,name', 'poster:id,name'])
            ->withCount('items');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $opnames = $query->latest()->paginate(20)->withQueryString();

        return $request->wantsJson()
            ? response()->json(['data' => $opnames])
            : view('stock-opnames.index', compact('opnames'));
    }

    public function create(): View
    {
        $products = Product::query()
            ->active()
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereHas('unit', fn ($query) => $query->where('is_active', true))
            ->with(['category:id,name', 'unit:id,symbol', 'inventoryBalance'])
            ->orderBy('name')
            ->get();

        return view('stock-opnames.create', compact('products'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $opname = $this->stockOpnameService->start($data['product_ids'], $data['notes'] ?? null, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_START_FAILED', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['opname' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname], 201)
            : redirect()->route('stock-opnames.show', $opname)->with('success', 'Sesi opname dimulai; mutasi produk terpilih dikunci.');
    }

    public function show(Request $request, StockOpname $stockOpname): View|JsonResponse
    {
        $stockOpname->load([
            'creator:id,name', 'starter:id,name', 'submitter:id,name', 'approver:id,name', 'poster:id,name',
            'items.product.category', 'items.product.unit', 'items.counter:id,name', 'locks.product',
        ]);

        return $request->wantsJson()
            ? response()->json(['data' => $stockOpname])
            : view('stock-opnames.show', compact('stockOpname'));
    }

    public function count(Request $request, StockOpname $stockOpname): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.counted_quantity' => ['nullable', 'numeric', 'gte:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reopen_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $opname = $this->stockOpnameService->count($stockOpname, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_COUNT_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['count' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname])
            : redirect()->route('stock-opnames.show', $opname)->with('success', 'Hasil hitung disimpan.');
    }

    public function submit(Request $request, StockOpname $stockOpname): JsonResponse|RedirectResponse
    {
        try {
            $opname = $this->stockOpnameService->submit($stockOpname, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_SUBMIT_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['submit' => $exception->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname])
            : redirect()->route('stock-opnames.show', $opname)->with('success', 'Sesi opname dikirim untuk approval.');
    }

    public function decide(Request $request, StockOpname $stockOpname): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:2000'],
            'admin_override_reason' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array'],
            'items.*.positive_unit_cost' => ['nullable', 'numeric', 'gt:0'],
            'items.*.positive_cost_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            foreach ($data['items'] ?? [] as $itemId => $itemData) {
                if (filled($itemData['positive_unit_cost'] ?? null) || filled($itemData['positive_cost_reason'] ?? null)) {
                    $this->stockOpnameService->setPositiveCost($stockOpname, (int) $itemId, (string) ($itemData['positive_unit_cost'] ?? ''), (string) ($itemData['positive_cost_reason'] ?? ''), $request->user());
                }
            }
            $opname = $this->stockOpnameService->decide($stockOpname, $request->user(), $data['decision'], $data['note'] ?? null, $data['admin_override_reason'] ?? null);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_DECISION_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['decision' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname])
            : redirect()->route('stock-opnames.show', $opname)->with('success', $data['decision'] === 'approved' ? 'Sesi opname disetujui.' : 'Sesi opname ditolak dan lock dilepas.');
    }

    public function post(Request $request, StockOpname $stockOpname): JsonResponse|RedirectResponse
    {
        try {
            $opname = $this->stockOpnameService->post($stockOpname, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_POST_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['post' => $exception->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname])
            : redirect()->route('stock-opnames.show', $opname)->with('success', 'Adjustment opname diposting dan seluruh lock dilepas.');
    }

    public function cancel(Request $request, StockOpname $stockOpname): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        try {
            $opname = $this->stockOpnameService->cancel($stockOpname, $request->user(), $data['reason']);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'STOCK_OPNAME_CANCEL_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['cancel' => $exception->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $opname])
            : redirect()->route('stock-opnames.show', $opname)->with('success', 'Sesi opname dibatalkan dan lock dilepas.');
    }
}
