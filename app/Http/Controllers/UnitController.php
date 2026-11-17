<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreUnitRequest;
use App\Http\Requests\Master\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Unit::query()->withCount('products');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $units = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('units.index', compact('units', 'search', 'status'));
    }

    public function create(): View
    {
        return view('units.create');
    }

    public function store(StoreUnitRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $unit = Unit::create($validated);

        AuditLogger::log(
            action: 'unit.created',
            module: 'catalog',
            reference: $unit,
            newValues: $unit->only(['name', 'symbol', 'decimal_precision', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Satuan berhasil ditambahkan.',
                'data' => $unit,
            ], 201);
        }

        return redirect()->route('units.index')
            ->with('success', 'Satuan baru berhasil ditambahkan.');
    }

    public function edit(Unit $unit): View
    {
        return view('units.edit', compact('unit'));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse|JsonResponse
    {
        $oldValues = $unit->only(['name', 'symbol', 'decimal_precision', 'is_active']);

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $unit->update($validated);

        AuditLogger::log(
            action: 'unit.updated',
            module: 'catalog',
            reference: $unit,
            oldValues: $oldValues,
            newValues: $unit->only(['name', 'symbol', 'decimal_precision', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Satuan berhasil diperbarui.',
                'data' => $unit,
            ]);
        }

        return redirect()->route('units.index')
            ->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        if ($unit->isReferenced()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Satuan tidak dapat dihapus karena masih digunakan oleh produk terdaftar.',
                ], 409);
            }

            return back()->with('error', 'Satuan tidak dapat dihapus karena masih digunakan oleh produk terdaftar.')->setStatusCode(409);
        }

        $oldValues = $unit->only(['name', 'symbol', 'decimal_precision', 'is_active']);
        $unit->delete();

        AuditLogger::log(
            action: 'unit.deleted',
            module: 'catalog',
            reference: $unit,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Satuan berhasil dihapus.']);
        }

        return redirect()->route('units.index')
            ->with('success', 'Satuan berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Unit $unit): RedirectResponse|JsonResponse
    {
        $oldStatus = $unit->is_active;
        $unit->is_active = ! $unit->is_active;
        $unit->save();

        AuditLogger::log(
            action: 'unit.status_toggled',
            module: 'catalog',
            reference: $unit,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $unit->is_active]
        );

        $message = $unit->is_active
            ? 'Satuan telah diaktifkan kembali.'
            : 'Satuan telah dinonaktifkan.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $unit->is_active]);
        }

        return back()->with('success', $message);
    }
}
