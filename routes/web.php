<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterDashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StoreProfileController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
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

    // Dashboard
    Route::get('/', [ReportController::class, 'index'])->name('dashboard');

    // Reports
    Route::prefix('reports')->name('reports.')->middleware('permission:reports.view_all|reports.view_limited')->group(function () {
        Route::get('/{type}', [ReportController::class, 'show'])->name('show');
    });

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

    Route::prefix('expenses')->name('expenses.')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses.create|expenses.post|expenses.pay')->name('index');
        Route::get('/create', [ExpenseController::class, 'create'])->middleware('permission:expenses.create')->name('create');
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses.create')->name('store');
        Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expenses.create|expenses.post|expenses.pay')->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('permission:expenses.create')->name('edit');
        Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.create')->name('update');
        Route::post('/{expense}/post', [ExpenseController::class, 'post'])->middleware('permission:expenses.post')->name('post');
        Route::post('/{expense}/payments', [ExpenseController::class, 'pay'])->middleware('permission:expenses.pay')->name('payments.store');
        Route::post('/{expense}/void', [ExpenseController::class, 'void'])->middleware('permission:expenses.post')->name('void');
    });

    Route::prefix('stock-opnames')->name('stock-opnames.')->group(function () {
        Route::get('/', [StockOpnameController::class, 'index'])->middleware('permission:stock_opname.create|stock_opname.count|stock_opname.approve|stock_opname.post')->name('index');
        Route::get('/create', [StockOpnameController::class, 'create'])->middleware('permission:stock_opname.create')->name('create');
        Route::post('/', [StockOpnameController::class, 'store'])->middleware('permission:stock_opname.create')->name('store');
        Route::get('/{stockOpname}', [StockOpnameController::class, 'show'])->middleware('permission:stock_opname.create|stock_opname.count|stock_opname.approve|stock_opname.post')->name('show');
        Route::put('/{stockOpname}/count', [StockOpnameController::class, 'count'])->middleware('permission:stock_opname.count')->name('count');
        Route::post('/{stockOpname}/submit', [StockOpnameController::class, 'submit'])->middleware('permission:stock_opname.count')->name('submit');
        Route::post('/{stockOpname}/decision', [StockOpnameController::class, 'decide'])->middleware('permission:stock_opname.approve')->name('decide');
        Route::post('/{stockOpname}/post', [StockOpnameController::class, 'post'])->middleware('permission:stock_opname.post')->name('post');
        Route::post('/{stockOpname}/cancel', [StockOpnameController::class, 'cancel'])->middleware('permission:stock_opname.create')->name('cancel');
    });

    // Master: Cash Accounts & Store Settings (FR-MST-06, FR-STORE-01, FR-CASH-01..02)
    Route::middleware('permission:store_settings.manage')->group(function () {
        Route::resource('cash-accounts', CashAccountController::class);
        Route::patch('/cash-accounts/{cash_account}/toggle-status', [CashAccountController::class, 'toggleStatus'])->name('cash-accounts.toggle_status');
        Route::post('/cash-accounts/{cash_account}/reverse/{cash_movement}', [CashAccountController::class, 'reverseMovement'])->name('cash-accounts.reverse_movement');
        Route::get('/cash-accounts/{cash_account}/reconcile', [CashAccountController::class, 'reconcile'])->name('cash-accounts.reconcile');

        // Cash Accounts JSON API
        Route::prefix('api/v1/cash-accounts')->name('api.cash_accounts.')->group(function () {
            Route::get('/', [CashAccountController::class, 'index'])->name('index');
            Route::get('/{cash_account}', [CashAccountController::class, 'show'])->name('show');
            Route::get('/{cash_account}/movements', [CashAccountController::class, 'movements'])->name('movements');
            Route::get('/{cash_account}/reconcile', [CashAccountController::class, 'reconcile'])->name('reconcile');
            Route::post('/{cash_account}/reverse/{cash_movement}', [CashAccountController::class, 'reverseMovement'])->name('reverse');
        });

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

    // Purchasing, Receiving, and Supplier Payments (FR-PUR-01..05)
    Route::middleware('permission:purchases.view|purchases.create')->group(function () {
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
        Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
        Route::delete('/purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
        Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm');
        Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

        // Receiving
        Route::get('/purchases/{purchase}/receive', [PurchaseReceiptController::class, 'create'])->name('purchases.receipts.create');
        Route::post('/purchases/{purchase}/receipts', [PurchaseReceiptController::class, 'store'])->name('purchases.receipts.store');
        Route::get('/purchases/{purchase}/receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->name('purchases.receipts.show');

        // Supplier Payments
        Route::get('/purchases/{purchase}/payments/create', [SupplierPaymentController::class, 'create'])->name('purchases.payments.create');
        Route::post('/purchases/{purchase}/payments', [SupplierPaymentController::class, 'store'])->name('purchases.payments.store');
        Route::post('/purchases/{purchase}/payments/{payment}/reverse', [SupplierPaymentController::class, 'reverse'])->name('purchases.payments.reverse');

        // Purchasing JSON API (PRD §11)
        Route::prefix('api/v1/purchases')->name('api.purchases.')->group(function () {
            Route::get('/', [PurchaseController::class, 'index'])->name('index');
            Route::post('/', [PurchaseController::class, 'store'])->name('store');
            Route::get('/{purchase}', [PurchaseController::class, 'show'])->name('show');
            Route::patch('/{purchase}', [PurchaseController::class, 'update'])->name('update');
            Route::post('/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('confirm');
            Route::post('/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('cancel');
            Route::post('/{purchase}/receipts', [PurchaseReceiptController::class, 'store'])->name('receipts.store');
            Route::get('/{purchase}/receipts/{receipt}', [PurchaseReceiptController::class, 'show'])->name('receipts.show');
            Route::post('/{purchase}/payments', [SupplierPaymentController::class, 'store'])->name('payments.store');
            Route::post('/{purchase}/payments/{payment}/reverse', [SupplierPaymentController::class, 'reverse'])->name('payments.reverse');
        });
    });

    // POS & Cash Sales (PRD §5.5, §6.4, §11.3)
    Route::middleware('permission:sales.create')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/calculate', [PosController::class, 'calculate'])->name('pos.calculate');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
        Route::get('/pos/barcode/{barcode}', [PosController::class, 'barcodeLookup'])->name('pos.barcode');
    });

    // Sales History & Receipts (PRD §5.5, §6.4, §6.5)
    Route::middleware('permission:sales.view_all|sales.view_own')->group(function () {
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/idempotency/{key}', [SaleController::class, 'checkByIdempotencyKey'])->name('sales.idempotency');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    });

    // Manual Bank Transfer Verification (PRD §5.5, §6.4, §7.3)
    Route::middleware('permission:payments.verify_transfer')->group(function () {
        Route::get('/sales-transfers/pending', [SaleController::class, 'pendingTransfers'])->name('sales.transfers.pending');
        Route::post('/sales/{sale}/verify-transfer', [SaleController::class, 'verifyTransfer'])->name('sales.verify_transfer');
    });

    Route::prefix('sales-returns')->name('sales-returns.')->group(function () {
        Route::get('/', [SalesReturnController::class, 'index'])->middleware('permission:returns.request|returns.approve|returns.refund')->name('index');
        Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('permission:returns.request|returns.approve|returns.refund')->name('show');
        Route::post('/{salesReturn}/decision', [SalesReturnController::class, 'decide'])->middleware('permission:returns.approve')->name('decide');
        Route::post('/{salesReturn}/refund', [SalesReturnController::class, 'pay'])->middleware('permission:returns.refund')->name('pay');
    });

    Route::middleware('permission:returns.request')->group(function () {
        Route::get('/sales/{sale}/returns/create', [SalesReturnController::class, 'create'])->name('sales-returns.create');
        Route::post('/sales/{sale}/returns', [SalesReturnController::class, 'store'])->name('sales-returns.store');
    });

    // POS & Sales JSON API (PRD §11.3)
    Route::prefix('api/v1')->group(function () {
        Route::middleware('permission:sales.create')->group(function () {
            Route::post('/sales/calculate', [PosController::class, 'calculate'])->name('api.sales.calculate');
            Route::post('/sales/checkout', [PosController::class, 'checkout'])->name('api.sales.checkout');
        });

        Route::prefix('expenses')->name('api.expenses.')->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:expenses.create|expenses.post|expenses.pay')->name('index');
            Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:expenses.create')->name('store');
            Route::get('/{expense}', [ExpenseController::class, 'show'])->middleware('permission:expenses.create|expenses.post|expenses.pay')->name('show');
            Route::put('/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.create')->name('update');
            Route::post('/{expense}/post', [ExpenseController::class, 'post'])->middleware('permission:expenses.post')->name('post');
            Route::post('/{expense}/payments', [ExpenseController::class, 'pay'])->middleware('permission:expenses.pay')->name('payments.store');
            Route::post('/{expense}/void', [ExpenseController::class, 'void'])->middleware('permission:expenses.post')->name('void');
        });

        Route::prefix('stock-opnames')->name('api.stock-opnames.')->group(function () {
            Route::get('/', [StockOpnameController::class, 'index'])->middleware('permission:stock_opname.create|stock_opname.count|stock_opname.approve|stock_opname.post')->name('index');
            Route::post('/', [StockOpnameController::class, 'store'])->middleware('permission:stock_opname.create')->name('store');
            Route::get('/{stockOpname}', [StockOpnameController::class, 'show'])->middleware('permission:stock_opname.create|stock_opname.count|stock_opname.approve|stock_opname.post')->name('show');
            Route::put('/{stockOpname}/count', [StockOpnameController::class, 'count'])->middleware('permission:stock_opname.count')->name('count');
            Route::post('/{stockOpname}/submit', [StockOpnameController::class, 'submit'])->middleware('permission:stock_opname.count')->name('submit');
            Route::post('/{stockOpname}/decision', [StockOpnameController::class, 'decide'])->middleware('permission:stock_opname.approve')->name('decide');
            Route::post('/{stockOpname}/post', [StockOpnameController::class, 'post'])->middleware('permission:stock_opname.post')->name('post');
            Route::post('/{stockOpname}/cancel', [StockOpnameController::class, 'cancel'])->middleware('permission:stock_opname.create')->name('cancel');
        });

        Route::middleware('permission:sales.view_all|sales.view_own')->group(function () {
            Route::get('/sales', [SaleController::class, 'index'])->name('api.sales.index');
            Route::get('/sales/idempotency/{key}', [SaleController::class, 'checkByIdempotencyKey'])->name('api.sales.idempotency');
            Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('api.sales.show');
            Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('api.sales.cancel');
        });

        Route::middleware('permission:payments.verify_transfer')->group(function () {
            Route::get('/sales/transfers/pending', [SaleController::class, 'pendingTransfers'])->name('api.sales.transfers.pending');
            Route::post('/sales/{sale}/verify-transfer', [SaleController::class, 'verifyTransfer'])->name('api.sales.verify_transfer');
        });

        Route::prefix('sales/returns')->name('api.sales-returns.')->group(function () {
            Route::get('/', [SalesReturnController::class, 'index'])->middleware('permission:returns.request|returns.approve|returns.refund')->name('index');
            Route::get('/{salesReturn}', [SalesReturnController::class, 'show'])->middleware('permission:returns.request|returns.approve|returns.refund')->name('show');
            Route::post('/{salesReturn}/decision', [SalesReturnController::class, 'decide'])->middleware('permission:returns.approve')->name('decide');
            Route::post('/{salesReturn}/refund', [SalesReturnController::class, 'pay'])->middleware('permission:returns.refund')->name('pay');
        });

        Route::middleware('permission:returns.request')->group(function () {
            Route::post('/sales/{sale}/returns', [SalesReturnController::class, 'store'])->name('api.sales-returns.store');
        });

        Route::middleware('permission:products.view')->group(function () {
            Route::get('/products/barcode/{barcode}', [PosController::class, 'barcodeLookup'])->name('api.products.barcode');
        });

        Route::middleware('permission:reports.view_all|reports.view_limited')->group(function () {
            Route::get('/reports/{type}', [ReportController::class, 'report'])->name('api.reports.show');
        });
    });
});
