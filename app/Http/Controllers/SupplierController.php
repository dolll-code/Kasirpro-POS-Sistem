<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Supplier::query();

        if (! empty($search)) {
            $query->search($search);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search', 'status'));
    }

    public function create(): View
    {
        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $supplier = Supplier::create($validated);

        AuditLogger::log(
            action: 'supplier.created',
            module: 'suppliers',
            reference: $supplier,
            newValues: $supplier->only(['supplier_code', 'name', 'contact_person', 'phone', 'email', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pemasok baru berhasil ditambahkan.',
                'data' => $supplier,
            ], 201);
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Pemasok baru berhasil ditambahkan.');
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $oldValues = $supplier->only(['supplier_code', 'name', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active']);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $supplier->update($validated);

        AuditLogger::log(
            action: 'supplier.updated',
            module: 'suppliers',
            reference: $supplier,
            oldValues: $oldValues,
            newValues: $supplier->only(['supplier_code', 'name', 'contact_person', 'phone', 'email', 'address', 'notes', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Data pemasok berhasil diperbarui.',
                'data' => $supplier,
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Data pemasok berhasil diperbarui.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        // Deletion guard: Check if supplier has any purchases
        $hasPurchases = Schema::hasTable('purchases') && DB::table('purchases')->where('supplier_id', $supplier->id)->exists();

        if ($hasPurchases) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Pemasok tidak dapat dihapus karena memiliki riwayat transaksi pembelian (PO). Gunakan opsi nonaktifkan pemasok.',
                ], 409);
            }

            return back()->with('error', 'Pemasok tidak dapat dihapus karena memiliki riwayat transaksi pembelian (PO).')->setStatusCode(409);
        }

        $oldValues = $supplier->only(['supplier_code', 'name', 'phone']);
        $supplier->delete();

        AuditLogger::log(
            action: 'supplier.deleted',
            module: 'suppliers',
            reference: $supplier,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Pemasok berhasil dihapus.']);
        }

        return redirect()->route('suppliers.index')
            ->with('success', 'Pemasok berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $oldStatus = $supplier->is_active;
        $supplier->is_active = ! $supplier->is_active;
        $supplier->save();

        AuditLogger::log(
            action: 'supplier.status_toggled',
            module: 'suppliers',
            reference: $supplier,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $supplier->is_active]
        );

        $message = $supplier->is_active
            ? 'Pemasok telah diaktifkan kembali dan dapat menerima PO.'
            : 'Pemasok telah dinonaktifkan (tidak dapat menerima PO baru).';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $supplier->is_active]);
        }

        return back()->with('success', $message);
    }
}
