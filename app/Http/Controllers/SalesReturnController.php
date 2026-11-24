<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class SalesReturnController extends Controller
{
    public function __construct(protected SalesReturnService $returnService) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = SalesReturn::with(['sale:id,transaction_number,cashier_id,sale_status', 'requester:id,name', 'approver:id,name', 'items']);
        if ($request->user()->hasPermission('returns.approve')) {
            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }
        } else {
            $query->whereHas('sale', fn ($sale) => $sale->where('cashier_id', $request->user()->id));
        }
        $returns = $query->latest('requested_at')->paginate(20)->withQueryString();

        return $request->wantsJson()
            ? response()->json(['data' => $returns])
            : view('sales-returns.index', compact('returns'));
    }

    public function create(Request $request, Sale $sale): View
    {
        $this->authorizeSaleAccess($request, $sale);
        $sale->load('items.product.unit');

        return view('sales-returns.create', compact('sale'));
    }

    public function store(Request $request, Sale $sale): JsonResponse|RedirectResponse
    {
        $this->authorizeSaleAccess($request, $sale);
        $validated = $request->validate([
            'sale_item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'condition' => ['required', 'in:resellable,damaged'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $salesReturn = $this->returnService->request($sale, $validated, $request->user());
        } catch (InvalidArgumentException $exception) {
            if ($request->wantsJson()) {
                return response()->json(['error' => ['code' => str_contains($exception->getMessage(), 'RETURN_QUANTITY_EXCEEDED') ? 'RETURN_QUANTITY_EXCEEDED' : 'RETURN_INVALID', 'message' => $exception->getMessage()]], 409);
            }

            return back()->withErrors(['return' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $salesReturn, 'message' => 'Pengajuan retur dibuat.'], 201)
            : redirect()->route('sales-returns.show', $salesReturn)->with('success', 'Pengajuan retur berhasil dibuat.');
    }

    public function show(Request $request, SalesReturn $salesReturn): View|JsonResponse
    {
        $this->authorizeSaleAccess($request, $salesReturn->sale);
        $salesReturn->load(['sale.cashier', 'requester', 'approver', 'items.saleItem.product.unit', 'payment.cashAccount', 'payment.creator']);
        $cashAccounts = $request->user()->hasPermission('returns.refund') ? CashAccount::active()->orderBy('name')->get() : collect();

        return $request->wantsJson()
            ? response()->json(['data' => $salesReturn])
            : view('sales-returns.show', compact('salesReturn', 'cashAccounts'));
    }

    public function decide(Request $request, SalesReturn $salesReturn): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'approval_note' => ['nullable', 'string', 'max:2000'],
            'admin_override_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $salesReturn = $this->returnService->decide(
                $salesReturn,
                $request->user(),
                $validated['decision'],
                $validated['approval_note'] ?? null,
                $validated['admin_override_reason'] ?? null
            );
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            if ($request->wantsJson()) {
                return response()->json(['error' => ['code' => 'RETURN_INVALID_STATE', 'message' => $exception->getMessage()]], 409);
            }

            return back()->withErrors(['return' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $salesReturn])
            : redirect()->route('sales-returns.show', $salesReturn)->with('success', $validated['decision'] === 'approved' ? 'Retur disetujui.' : 'Retur ditolak.');
    }

    public function pay(Request $request, SalesReturn $salesReturn): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);

        try {
            $salesReturn = $this->returnService->payRefund(
                $salesReturn,
                (int) $validated['cash_account_id'],
                $request->user(),
                $validated['reference_number'] ?? null,
                isset($validated['paid_at']) ? Carbon::parse($validated['paid_at'], 'Asia/Jakarta') : null
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->wantsJson()) {
                return response()->json(['error' => ['code' => 'REFUND_ALREADY_PAID_OR_INVALID', 'message' => $exception->getMessage()]], 409);
            }

            return back()->withErrors(['refund' => $exception->getMessage()])->withInput();
        } catch (InsufficientCashBalanceException $exception) {
            if ($request->wantsJson()) {
                return response()->json(['error' => ['code' => 'INSUFFICIENT_CASH_BALANCE', 'message' => $exception->getMessage()]], 409);
            }

            return back()->withErrors(['refund' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $salesReturn, 'message' => 'Refund berhasil dibayarkan.'])
            : redirect()->route('sales-returns.show', $salesReturn)->with('success', 'Refund berhasil dibayarkan.');
    }

    private function authorizeSaleAccess(Request $request, Sale $sale): void
    {
        $user = $request->user();
        if ($user->hasPermission('returns.approve') || $user->hasPermission('returns.refund') || $user->hasPermission('sales.view_all')) {
            return;
        }
        if ($user->hasPermission('sales.view_own') && (int) $sale->cashier_id === (int) $user->id) {
            return;
        }
        abort(403, 'Tidak memiliki akses ke penjualan ini.');
    }
}
