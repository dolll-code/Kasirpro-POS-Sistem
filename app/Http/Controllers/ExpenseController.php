<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenseService) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = Expense::with(['category', 'creator', 'poster']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->input('to'));
        }
        $expenses = $query->latest('expense_date')->paginate(20)->withQueryString();

        return $request->wantsJson()
            ? response()->json(['data' => $expenses])
            : view('expenses.index', compact('expenses'));
    }

    public function create(): View
    {
        $expense = new Expense(['expense_date' => now('Asia/Jakarta')->toDateString(), 'amount' => '0.00']);
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return view('expenses.form', compact('expense', 'categories'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $expense = $this->expenseService->createDraft($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPENSE_INVALID', 'message' => $exception->getMessage()]], 422)
                : back()->withErrors(['expense' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $expense], 201)
            : redirect()->route('expenses.show', $expense)->with('success', 'Draft expense dibuat.');
    }

    public function edit(Expense $expense): View
    {
        abort_unless($expense->status === 'draft', 409, 'Hanya draft yang dapat diedit.');
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return view('expenses.form', compact('expense', 'categories'));
    }

    public function update(Request $request, Expense $expense): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $expense = $this->expenseService->updateDraft($expense, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPENSE_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['expense' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $expense])
            : redirect()->route('expenses.show', $expense)->with('success', 'Draft expense diperbarui.');
    }

    public function show(Request $request, Expense $expense): View|JsonResponse
    {
        $expense->load(['category', 'creator', 'poster', 'voider', 'payments.cashAccount', 'payments.cashMovement', 'payments.creator', 'voidReversals.expensePayment', 'voidReversals.reversalCashMovement']);
        $cashAccounts = CashAccount::active()->orderBy('name')->get();

        return $request->wantsJson()
            ? response()->json(['data' => $expense])
            : view('expenses.show', compact('expense', 'cashAccounts'));
    }

    public function post(Request $request, Expense $expense): JsonResponse|RedirectResponse
    {
        try {
            $expense = $this->expenseService->post($expense, $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPENSE_CANNOT_POST', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['expense' => $exception->getMessage()]);
        }

        return $request->wantsJson()
            ? response()->json(['data' => $expense])
            : redirect()->route('expenses.show', $expense)->with('success', 'Expense diposting.');
    }

    public function pay(Request $request, Expense $expense): JsonResponse|RedirectResponse
    {
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key')));
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Idempotency-Key wajib diisi, maksimal 64 karakter.']], 422)
                : back()->withErrors(['payment' => 'Idempotency-Key tidak tersedia. Muat ulang halaman lalu coba kembali.'])->withInput();
        }
        try {
            $payment = $this->expenseService->pay($expense, (int) $data['cash_account_id'], $data['amount'], $request->user(), $idempotencyKey, $data['reference_number'] ?? null, isset($data['paid_at']) ? Carbon::parse($data['paid_at'], 'Asia/Jakarta') : null);
        } catch (InsufficientCashBalanceException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'INSUFFICIENT_CASH_BALANCE', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPENSE_PAYMENT_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['payment' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $payment], 201)
            : redirect()->route('expenses.show', $expense)->with('success', 'Pembayaran expense dicatat.');
    }

    public function void(Request $request, Expense $expense): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'cash_refund_amount' => ['required', 'numeric', 'gte:0'],
        ]);
        try {
            $expense = $this->expenseService->void($expense, $data['reason'], $data['cash_refund_amount'], $request->user());
        } catch (InvalidArgumentException $exception) {
            return $request->wantsJson()
                ? response()->json(['error' => ['code' => 'EXPENSE_VOID_INVALID', 'message' => $exception->getMessage()]], 409)
                : back()->withErrors(['void' => $exception->getMessage()])->withInput();
        }

        return $request->wantsJson()
            ? response()->json(['data' => $expense])
            : redirect()->route('expenses.show', $expense)->with('success', 'Expense di-void; cash reversal sesuai pengembalian aktual tercatat.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'expense_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_landed_cost' => ['nullable', 'boolean'],
            'landed_cost_reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $data;
    }
}
