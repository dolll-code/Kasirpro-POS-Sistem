<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreCashAccountRequest;
use App\Http\Requests\Master\UpdateCashAccountRequest;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Services\AuditLogger;
use App\Services\CashMovementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected CashMovementService $cashMovementService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $search = $request->input('search');
        $type = $request->input('account_type');
        $status = $request->input('status');

        $query = CashAccount::query();

        if (! empty($search)) {
            $query->search($search);
        }

        if (! empty($type)) {
            $query->where('account_type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $cashAccounts = $query->orderBy('name')->paginate(15)->withQueryString();

        // Calculate KPI summaries
        $totalCashBalance = CashAccount::where('account_type', 'cash')->where('is_active', true)->sum('current_balance');
        $totalBankBalance = CashAccount::where('account_type', 'bank')->where('is_active', true)->sum('current_balance');
        $totalLiquidity = $totalCashBalance + $totalBankBalance;

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $cashAccounts->items(),
                'meta' => [
                    'page' => $cashAccounts->currentPage(),
                    'per_page' => $cashAccounts->perPage(),
                    'total' => $cashAccounts->total(),
                    'total_cash' => (string) $totalCashBalance,
                    'total_bank' => (string) $totalBankBalance,
                    'total_liquidity' => (string) $totalLiquidity,
                    'request_id' => $request->header('X-Request-Id'),
                ],
            ]);
        }

        return view('cash-accounts.index', compact(
            'cashAccounts',
            'search',
            'type',
            'status',
            'totalCashBalance',
            'totalBankBalance',
            'totalLiquidity'
        ));
    }

    public function create(): View
    {
        return view('cash-accounts.create');
    }

    public function store(StoreCashAccountRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);
        if (empty($validated['opening_balance_at'])) {
            $validated['opening_balance_at'] = now();
        }

        $cashAccount = CashAccount::create($validated);

        // Record opening balance movement if opening_balance > 0
        if ((float) $cashAccount->opening_balance > 0) {
            $this->cashMovementService->recordOpeningBalance($cashAccount, auth()->id());
        }

        AuditLogger::log(
            action: 'cash_account.created',
            module: 'store_settings',
            reference: $cashAccount,
            newValues: $cashAccount->only(['code', 'name', 'account_type', 'opening_balance', 'opening_balance_at', 'current_balance', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Akun kas/bank berhasil ditambahkan.',
                'data' => $cashAccount->fresh(),
            ], 201);
        }

        return redirect()->route('cash-accounts.index')
            ->with('success', 'Akun kas/bank baru berhasil didaftarkan.');
    }

    public function show(Request $request, CashAccount $cashAccount): View|JsonResponse
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $direction = $request->input('direction');
        $movementType = $request->input('movement_type');

        $query = CashMovement::where('cash_account_id', $cashAccount->id)
            ->with(['creator:id,name', 'reversalOf:id,movement_type,source_number', 'reversedBy:id']);

        if (! empty($fromDate)) {
            $start = Carbon::parse($fromDate, 'Asia/Jakarta')->startOfDay();
            $query->where('occurred_at', '>=', $start);
        }

        if (! empty($toDate)) {
            $end = Carbon::parse($toDate, 'Asia/Jakarta')->endOfDay();
            $query->where('occurred_at', '<=', $end);
        }

        if (! empty($direction)) {
            $query->where('direction', $direction);
        }

        if (! empty($movementType)) {
            $query->where('movement_type', $movementType);
        }

        $movements = $query->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        $reconciliation = $this->cashMovementService->reconcile($cashAccount);

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'account' => [
                    'id' => $cashAccount->id,
                    'code' => $cashAccount->code,
                    'name' => $cashAccount->name,
                    'account_type' => $cashAccount->account_type,
                    'opening_balance' => (string) $cashAccount->opening_balance,
                    'opening_balance_at' => $cashAccount->opening_balance_at?->toIso8601String(),
                    'current_balance' => $cashAccount->current_balance,
                    'is_active' => $cashAccount->is_active,
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

        return view('cash-accounts.show', compact('cashAccount', 'movements', 'reconciliation'));
    }

    public function edit(CashAccount $cashAccount): View
    {
        return view('cash-accounts.edit', compact('cashAccount'));
    }

    public function update(UpdateCashAccountRequest $request, CashAccount $cashAccount): RedirectResponse|JsonResponse
    {
        $oldValues = $cashAccount->only(['code', 'name', 'account_type', 'is_active']);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $cashAccount->update($validated);

        AuditLogger::log(
            action: 'cash_account.updated',
            module: 'store_settings',
            reference: $cashAccount,
            oldValues: $oldValues,
            newValues: $cashAccount->only(['code', 'name', 'account_type', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data akun kas/bank berhasil diperbarui.',
                'data' => $cashAccount,
            ]);
        }

        return redirect()->route('cash-accounts.index')
            ->with('success', 'Data akun kas/bank berhasil diperbarui.');
    }

    public function destroy(Request $request, CashAccount $cashAccount): RedirectResponse|JsonResponse
    {
        // Deletion guard: Cannot delete if account has movements
        if ($cashAccount->hasMovements()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Akun kas/bank telah memiliki mutasi pergerakan kas dan tidak dapat dihapus permanen. Gunakan opsi nonaktifkan akun.',
                ], 409);
            }

            return back()->with('error', 'Akun kas/bank telah memiliki mutasi pergerakan kas dan tidak dapat dihapus permanen. Gunakan opsi nonaktifkan akun.')->setStatusCode(409);
        }

        $oldValues = $cashAccount->only(['code', 'name', 'account_type']);
        $cashAccount->delete();

        AuditLogger::log(
            action: 'cash_account.deleted',
            module: 'store_settings',
            reference: $cashAccount,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Akun kas/bank berhasil dihapus.']);
        }

        return redirect()->route('cash-accounts.index')
            ->with('success', 'Akun kas/bank berhasil dihapus.');
    }

    public function toggleStatus(Request $request, CashAccount $cashAccount): RedirectResponse|JsonResponse
    {
        $oldStatus = $cashAccount->is_active;
        $cashAccount->is_active = ! $cashAccount->is_active;
        $cashAccount->save();

        AuditLogger::log(
            action: 'cash_account.status_toggled',
            module: 'store_settings',
            reference: $cashAccount,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $cashAccount->is_active]
        );

        $message = $cashAccount->is_active
            ? 'Akun kas/bank berhasil diaktifkan kembali.'
            : 'Akun kas/bank dinonaktifkan.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $cashAccount->is_active]);
        }

        return back()->with('success', $message);
    }

    public function reverseMovement(Request $request, CashAccount $cashAccount, CashMovement $cashMovement): RedirectResponse|JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $reason = $request->input('reason');

        try {
            $reversal = $this->cashMovementService->reverseMovement($cashMovement, $reason, auth()->id());
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_REVERSAL',
                        'message' => $e->getMessage(),
                    ],
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log(
            action: 'cash_movement.reversed',
            module: 'store_settings',
            reference: $cashAccount,
            oldValues: [
                'movement_id' => $cashMovement->id,
                'amount' => $cashMovement->amount,
                'direction' => $cashMovement->direction,
            ],
            newValues: [
                'reversal_id' => $reversal->id,
                'reason' => $reason,
                'current_balance' => $cashAccount->fresh()->current_balance,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pembalikan (reversal) pergerakan kas berhasil diposting.',
                'data' => $reversal,
            ]);
        }

        return redirect()->route('cash-accounts.show', $cashAccount)
            ->with('success', 'Pembalikan transaksi berhasil dicatat di buku kas.');
    }

    public function reconcile(Request $request, CashAccount $cashAccount): JsonResponse
    {
        $reconciliation = $this->cashMovementService->reconcile($cashAccount);

        return response()->json([
            'cash_account_id' => $cashAccount->id,
            'cash_account_name' => $cashAccount->name,
            'data' => $reconciliation,
            'meta' => [
                'request_id' => $request->header('X-Request-Id'),
            ],
        ]);
    }

    public function movements(Request $request, CashAccount $cashAccount): JsonResponse
    {
        $movements = CashMovement::where('cash_account_id', $cashAccount->id)
            ->with(['creator:id,name'])
            ->orderBy('occurred_at', 'desc')
            ->paginate(25);

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
