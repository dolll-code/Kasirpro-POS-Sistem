<?php

use App\Exceptions\InsufficientCashTenderException;
use App\Exceptions\InsufficientStockException;
use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StoreProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    // Store Profile Setup
    $store = StoreProfile::current();
    $store->update([
        'name' => 'Toko KasirPro Sentosa',
        'address' => 'Jl. Boulevard No. 88, Jakarta Barat',
        'phone' => '081234567890',
        'receipt_footer' => 'Barang yang sudah dibeli tidak dapat ditukar kecuali ada perjanjian.',
        'receipt_paper_width' => '58',
    ]);

    // Cash Account Setup (KAS-TOKO)
    $this->cashAccount = CashAccount::firstOrCreate(
        ['code' => 'KAS-TOKO'],
        [
            'name' => 'Kas Laci Kasir Toko',
            'account_type' => 'cash',
            'opening_balance' => '500000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->cashAccount, $this->cashier->id);
    } catch (\Throwable $e) {
        // already opened in seed/previous test
    }

    $this->category = Category::firstOrCreate(
        ['slug' => 'minuman-retail-cash'],
        ['name' => 'Minuman Retail Cash', 'is_active' => true]
    );

    $this->unitPcs = Unit::firstOrCreate(
        ['symbol' => 'pcs-cash'],
        ['name' => 'Pieces Cash', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'SKU-TEH-01',
        'barcode' => '8992222111100',
        'name' => 'Teh Melati Kotak 250ml',
        'selling_price' => '5000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    // Stock receipt to establish on-hand and average cost HPP = Rp 3.000
    $this->inventoryService = app(InventoryService::class);
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '50.000',
        unitCost: '3000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-TEST-01',
        userId: $this->cashier->id
    );

    $this->saleService = app(SaleService::class);
});

test('cash checkout succeeds and records sale, payment, stock out, and exact cash inflow', function () {
    // 5 units @ 5.000 = 25.000. Tender 50.000. Change = 25.000
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '5.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '50000.00',
        ],
    ];

    $sale = $this->saleService->checkoutCash(
        payload: $payload,
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    expect($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->sale_status)->toBe('completed')
        ->and($sale->payment_status)->toBe('paid')
        ->and($sale->gross_total)->toBe('25000.00')
        ->and($sale->grand_total)->toBe('25000.00')
        ->and($sale->store_snapshot['store_name'])->toBe('Toko KasirPro Sentosa');

    // Payment assertions
    $payment = $sale->payment;
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe('25000.00')
        ->and($payment->cash_tendered)->toBe('50000.00')
        ->and($payment->change_amount)->toBe('25000.00');

    // CRITICAL: Cash Inflow must be equal to grand_total (25.000), NOT tendered (50.000)!
    $cashMovement = $payment->cashMovement;
    expect($cashMovement)->not->toBeNull()
        ->and($cashMovement->direction)->toBe('in')
        ->and($cashMovement->amount)->toBe('25000.00')
        ->and($cashMovement->movement_type)->toBe('IN_SALE');

    // Stock Movement assertions (OUT_SALE)
    $stockMovement = StockMovement::where('source_type', 'sale')
        ->where('source_id', $sale->id)
        ->first();
    expect($stockMovement)->not->toBeNull()
        ->and($stockMovement->movement_type)->toBe('OUT_SALE')
        ->and($stockMovement->quantity_out)->toBe('5.000')
        ->and($stockMovement->balance_before)->toBe('50.000')
        ->and($stockMovement->balance_after)->toBe('45.000')
        ->and($stockMovement->unit_cost)->toBe('3000.00')
        ->and($stockMovement->total_cost)->toBe('15000.00');

    // Sale Item HPP snapshot assertion
    $saleItem = $sale->items->first();
    expect($saleItem->quantity)->toBe('5.000')
        ->and($saleItem->unit_price_snapshot)->toBe('5000.00')
        ->and($saleItem->net_line_total)->toBe('25000.00')
        ->and($saleItem->cost_price_snapshot)->toBe('3000.00')
        ->and($saleItem->cost_total_snapshot)->toBe('15000.00');
});

test('cash checkout rejects insufficient tender with exception', function () {
    // 5 units @ 5.000 = 25.000. Tender 20.000 (< 25.000)
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '5.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '20000.00',
        ],
    ];

    expect(function () use ($payload) {
        $this->saleService->checkoutCash(
            payload: $payload,
            cashier: $this->cashier,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InsufficientCashTenderException::class);
});

test('cash checkout with exact tender produces zero change', function () {
    // 2 units @ 5.000 = 10.000. Tender exactly 10.000
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '10000.00',
        ],
    ];

    $sale = $this->saleService->checkoutCash(
        payload: $payload,
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    expect($sale->payment->change_amount)->toBe('0.00');
});

test('cash checkout rejects when requested quantity exceeds available stock', function () {
    // Current stock is 50. Request 51.
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '51.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '300000.00',
        ],
    ];

    expect(function () use ($payload) {
        $this->saleService->checkoutCash(
            payload: $payload,
            cashier: $this->cashier,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InsufficientStockException::class);
});

test('cash checkout rejects when product is deactivated', function () {
    $this->product->update(['is_active' => false]);

    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '10000.00',
        ],
    ];

    expect(function () use ($payload) {
        $this->saleService->checkoutCash(
            payload: $payload,
            cashier: $this->cashier,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InvalidArgumentException::class, 'nonaktif');
});

test('cash checkout rejects invalid decimal precision for unit', function () {
    // unit is pcs with decimal_precision = 0. Request 1.5
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.500'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '10000.00',
        ],
    ];

    expect(function () use ($payload) {
        $this->saleService->checkoutCash(
            payload: $payload,
            cashier: $this->cashier,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InvalidArgumentException::class, 'presisi desimal');
});
