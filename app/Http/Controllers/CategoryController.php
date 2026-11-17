<?php

namespace App\Http\Controllers;

use App\Http\Requests\Master\StoreCategoryRequest;
use App\Http\Requests\Master\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(protected AuditLogger $auditLogger) {}

    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Category::query()->withCount('products');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('categories.index', compact('categories', 'search', 'status'));
    }

    public function create(): View
    {
        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['is_active'] = $request->boolean('is_active', true);

        $category = Category::create($validated);

        AuditLogger::log(
            action: 'category.created',
            module: 'catalog',
            reference: $category,
            newValues: $category->only(['name', 'slug', 'description', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Kategori berhasil ditambahkan.',
                'data' => $category,
            ], 201);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Kategori baru berhasil ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $oldValues = $category->only(['name', 'slug', 'description', 'is_active']);

        $validated = $request->validated();
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['is_active'] = $request->boolean('is_active', true);

        $category->update($validated);

        AuditLogger::log(
            action: 'category.updated',
            module: 'catalog',
            reference: $category,
            oldValues: $oldValues,
            newValues: $category->only(['name', 'slug', 'description', 'is_active'])
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Kategori berhasil diperbarui.',
                'data' => $category,
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        if ($category->isReferenced()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Kategori tidak dapat dihapus karena masih digunakan oleh produk terdaftar.',
                ], 409);
            }

            return back()->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk terdaftar.')->setStatusCode(409);
        }

        $oldValues = $category->only(['name', 'slug', 'description', 'is_active']);
        $category->delete();

        AuditLogger::log(
            action: 'category.deleted',
            module: 'catalog',
            reference: $category,
            oldValues: $oldValues
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Kategori berhasil dihapus.']);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }

    public function toggleStatus(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $oldStatus = $category->is_active;
        $category->is_active = ! $category->is_active;
        $category->save();

        AuditLogger::log(
            action: 'category.status_toggled',
            module: 'catalog',
            reference: $category,
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $category->is_active]
        );

        $message = $category->is_active
            ? 'Kategori telah diaktifkan kembali.'
            : 'Kategori telah dinonaktifkan.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'is_active' => $category->is_active]);
        }

        return back()->with('success', $message);
    }
}
