<?php

use App\Exceptions\InsufficientStockException;
use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();

    $this->cashier1 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->cashier2 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->cashAccount = CashAccount::firstOrCreate(
        ['code' => 'KAS-TOKO'],
        [
            'name' => 'Kas Toko',
            'account_type' => 'cash',
            'opening_balance' => '1000000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->cashAccount, $this->cashier1->id);
    } catch (\Throwable $e) {}

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-concurrency-test'],
        ['name' => 'Kategori Concurrency Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-conc'],
        ['name' => 'Pieces Conc', 'decimal_precision' => 0, 'is_active' => true]
    );

    // Product with ONLY 1 unit of available stock
    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-LIMITED-01',
        'name' => 'Barang Terakhir 1 Pcs',
        'selling_price' => '100000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '1.000',
        unitCost: '70000.00',
        movementType: 'IN_INITIAL',
        sourceType: 'opening_balance',
        userId: $this->cashier1->id
    );

    $this->saleService = app(SaleService::class);
});

test('two cashiers attempting to buy the last remaining stock: first succeeds, second is rejected by row lock without negative stock', function () {
    $payload1 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '100000.00',
        ],
    ];

    $payload2 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '100000.00',
        ],
    ];

    // Cashier 1 checks out the only item
    $sale1 = $this->saleService->checkoutCash($payload1, $this->cashier1, (string) Str::uuid());
    expect($sale1->sale_status)->toBe('completed');

    // Cashier 2 attempts to checkout the same item immediately
    expect(function () use ($payload2) {
        $this->saleService->checkoutCash($payload2, $this->cashier2, (string) Str::uuid());
    })->toThrow(InsufficientStockException::class);

    // Verify stock balance is exactly 0 and never went negative
    $balance = $this->inventoryService->getOrCreateBalance($this->product);
    expect($balance->quantity_on_hand)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('0.000');
});
