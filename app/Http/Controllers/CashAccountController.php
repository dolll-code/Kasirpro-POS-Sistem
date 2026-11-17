<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreCashAccountRequest;
use App\Http\Requests\Master\UpdateCashAccountRequest;
use App\Models\CashAccount;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
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

        return view('cash-accounts.index', compact('cashAccounts', 'search', 'type', 'status'));
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

        AuditLogger::log(
            action: 'cash_account.created',
            module: 'store_settings',
            reference: $cashAccount,
            newValues: $cashAccount->only(['code', 'name', 'account_type', 'opening_balance', 'opening_balance_at', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Akun kas/bank berhasil ditambahkan.',
                'data' => $cashAccount,
            ], 201);
        }

        return redirect()->route('cash-accounts.index')
            ->with('success', 'Akun kas/bank baru berhasil didaftarkan.');
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
            ? 'Akun kas/bank telah diaktifkan kembali.'
            : 'Akun kas/bank telah dinonaktifkan.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $cashAccount->is_active]);
        }

        return back()->with('success', $message);
    }
}
