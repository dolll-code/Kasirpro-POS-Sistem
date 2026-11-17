<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreProfileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Authentication (Guest)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Authenticated & Active User Routes
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Dashboard / Foundation Landing
    Route::get('/', function () {
        return view('pages.foundation');
    })->name('dashboard');

    // User Profile & Password Change (FR-AUTH-05)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // User Management (FR-AUTH-03) - Protected by users.manage permission
    Route::prefix('users')->name('users.')->middleware('permission:users.manage')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}', [UserManagementController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggle_status');
    });

    // Master Data Dashboard
    Route::get('/master', MasterDashboardController::class)->name('master.index');

    // Master: Categories (FR-MST-01) & Units (FR-MST-02)
    Route::middleware('permission:catalog.manage')->group(function () {
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::patch('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle_status');

        Route::resource('units', UnitController::class)->except(['show']);
        Route::patch('/units/{unit}/toggle-status', [UnitController::class, 'toggleStatus'])->name('units.toggle_status');
    });

    // Master: Products & Lookup (FR-MST-03, FR-MST-04)
    Route::get('/api/v1/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup')->middleware('permission:products.view');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index')->middleware('permission:products.view');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create')->middleware('permission:products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store')->middleware('permission:products.create');
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show')->middleware('permission:products.view');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit')->middleware('permission:products.update');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('permission:products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('permission:products.deactivate');
    Route::patch('/products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])->name('products.toggle_status')->middleware('permission:products.deactivate');

    // Master: Suppliers (FR-MST-05)
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('permission:suppliers.view');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create')->middleware('permission:suppliers.manage');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('permission:suppliers.manage');
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show')->middleware('permission:suppliers.view');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->middleware('permission:suppliers.manage');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('permission:suppliers.manage');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('permission:suppliers.manage');
    Route::patch('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle_status')->middleware('permission:suppliers.manage');

    // Master: Expense Categories (FR-MST-06)
    Route::middleware('permission:expenses.create')->group(function () {
        Route::resource('expense-categories', ExpenseCategoryController::class)->except(['show']);
        Route::patch('/expense-categories/{expense_category}/toggle-status', [ExpenseCategoryController::class, 'toggleStatus'])->name('expense-categories.toggle_status');
    });

    // Master: Cash Accounts & Store Settings (FR-MST-06, FR-STORE-01)
    Route::middleware('permission:store_settings.manage')->group(function () {
        Route::resource('cash-accounts', CashAccountController::class)->except(['show']);
        Route::patch('/cash-accounts/{cash_account}/toggle-status', [CashAccountController::class, 'toggleStatus'])->name('cash-accounts.toggle_status');

        Route::get('/store-profile', [StoreProfileController::class, 'edit'])->name('store-profile.edit');
        Route::put('/store-profile', [StoreProfileController::class, 'update'])->name('store-profile.update');
    });

    // Inventory & Stock Ledger (FR-INV-02..07)
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low_stock');
        Route::get('/inventory/{product}/card', [InventoryController::class, 'stockCard'])->name('inventory.card');
        Route::get('/inventory/{product}/reconcile', [InventoryController::class, 'reconcile'])->name('inventory.reconcile');

        // Inventory JSON API
        Route::prefix('api/v1/inventory')->name('api.inventory.')->group(function () {
            Route::get('/balances', [InventoryController::class, 'index'])->name('balances');
            Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
            Route::get('/low-stock', [InventoryController::class, 'lowStock'])->name('low_stock');
            Route::get('/{product}/reconcile', [InventoryController::class, 'reconcile'])->name('product_reconcile');
        });
    });
});
