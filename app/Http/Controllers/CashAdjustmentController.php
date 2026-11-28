<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashAdjustment;
use App\Services\CashAdjustmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CashAdjustmentController extends Controller
{
    public function __construct(protected CashAdjustmentService $service) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = CashAdjustment::with(['cashAccount:id,name', 'requester:id,name', 'approver:id,name']);
        if (! $request->user()->hasRole('admin') && ! $request->user()->hasRole('supervisor')) {
            $query->where('requested_by', $request->user()->id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        $adjustments = $query->latest('requested_at')->paginate(20)->withQueryString();

        return $request->wantsJson() ? response()->json(['data' => $adjustments]) : view('cash-adjustments.index', compact('adjustments'));
    }

    public function create(): View
    {
        return view('cash-adjustments.create', ['accounts' => CashAccount::active()->orderBy('name')->get(), 'categories' => CashAdjustmentService::CATEGORIES]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'direction' => ['required', 'in:in,out'],
            'category' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        try {
            $adjustment = $this->service->request($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'CASH_ADJUSTMENT_INVALID', 'message' => $exception->getMessage()]], 422)
                : back()->withErrors(['adjustment' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $adjustment], 201)
            : redirect()->route('cash-adjustments.show', $adjustment)->with('success', 'Pengajuan adjustment dibuat dan menunggu approval.');
    }

    public function show(Request $request, CashAdjustment $cashAdjustment): View|JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('admin') && ! $user->hasRole('supervisor') && (int) $cashAdjustment->requested_by !== (int) $user->id) {
            abort(403, 'Adjustment ini bukan milik Anda.');
        }
        $cashAdjustment->load(['cashAccount', 'requester', 'approver', 'cashMovement']);

        return $request->wantsJson() ? response()->json(['data' => $cashAdjustment]) : view('cash-adjustments.show', compact('cashAdjustment'));
    }

    public function decide(Request $request, CashAdjustment $cashAdjustment): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'approval_note' => ['nullable', 'string', 'max:2000'],
            'admin_override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $cashAdjustment = $this->service->decide($cashAdjustment, $request->user(), $data['decision'], $data['approval_note'] ?? null, $data['admin_override_reason'] ?? null);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (InsufficientCashBalanceException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'INSUFFICIENT_CASH_BALANCE', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['adjustment' => $exception->getMessage()]);
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'CASH_ADJUSTMENT_INVALID_STATE', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['adjustment' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $cashAdjustment])
            : redirect()->route('cash-adjustments.show', $cashAdjustment)->with('success', 'Keputusan adjustment tersimpan.');
    }

    public function cancel(Request $request, CashAdjustment $cashAdjustment): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        try {
            $cashAdjustment = $this->service->cancel($cashAdjustment, $request->user(), $data['reason']);
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'CASH_ADJUSTMENT_CANCEL_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['adjustment' => $exception->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $cashAdjustment])
            : redirect()->route('cash-adjustments.show', $cashAdjustment)->with('success', 'Adjustment dibatalkan.');
    }
}
