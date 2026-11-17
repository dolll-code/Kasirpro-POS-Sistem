<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreExpenseCategoryRequest;
use App\Http\Requests\Master\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = ExpenseCategory::query();

        if (! empty($search)) {
            $query->search($search);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $expenseCategories = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('expense-categories.index', compact('expenseCategories', 'search', 'status'));
    }

    public function create(): View
    {
        return view('expense-categories.create');
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $expenseCategory = ExpenseCategory::create($validated);

        AuditLogger::log(
            action: 'expense_category.created',
            module: 'expenses',
            reference: $expenseCategory,
            newValues: $expenseCategory->only(['name', 'description', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Kategori biaya berhasil ditambahkan.',
                'data' => $expenseCategory,
            ], 201);
        }

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori pengeluaran baru berhasil ditambahkan.');
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('expense-categories.edit', compact('expenseCategory'));
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse|JsonResponse
    {
        $oldValues = $expenseCategory->only(['name', 'description', 'is_active']);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $expenseCategory->update($validated);

        AuditLogger::log(
            action: 'expense_category.updated',
            module: 'expenses',
            reference: $expenseCategory,
            oldValues: $oldValues,
            newValues: $expenseCategory->only(['name', 'description', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Kategori biaya berhasil diperbarui.',
                'data' => $expenseCategory,
            ]);
        }

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori pengeluaran berhasil diperbarui.');
    }

    public function destroy(Request $request, ExpenseCategory $expenseCategory): RedirectResponse|JsonResponse
    {
        // Deletion guard: Check if referenced in expenses
        $hasExpenses = Schema::hasTable('expenses') && DB::table('expenses')->where('category_id', $expenseCategory->id)->exists();

        if ($hasExpenses) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Kategori biaya tidak dapat dihapus karena telah digunakan pada transaksi pengeluaran.',
                ], 409);
            }

            return back()->with('error', 'Kategori biaya tidak dapat dihapus karena telah digunakan pada transaksi pengeluaran.')->setStatusCode(409);
        }

        $oldValues = $expenseCategory->only(['name', 'description']);
        $expenseCategory->delete();

        AuditLogger::log(
            action: 'expense_category.deleted',
            module: 'expenses',
            reference: $expenseCategory,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Kategori pengeluaran berhasil dihapus.']);
        }

        return redirect()->route('expense-categories.index')
            ->with('success', 'Kategori pengeluaran berhasil dihapus.');
    }

    public function toggleStatus(Request $request, ExpenseCategory $expenseCategory): RedirectResponse|JsonResponse
    {
        $oldStatus = $expenseCategory->is_active;
        $expenseCategory->is_active = ! $expenseCategory->is_active;
        $expenseCategory->save();

        AuditLogger::log(
            action: 'expense_category.status_toggled',
            module: 'expenses',
            reference: $expenseCategory,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $expenseCategory->is_active]
        );

        $message = $expenseCategory->is_active
            ? 'Kategori biaya telah diaktifkan kembali.'
            : 'Kategori biaya telah dinonaktifkan.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $expenseCategory->is_active]);
        }

        return back()->with('success', $message);
    }
}
