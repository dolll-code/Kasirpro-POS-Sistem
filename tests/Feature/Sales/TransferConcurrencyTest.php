<?php

use App\Exceptions\InsufficientStockException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryBalance;
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
    $this->supervisorRole = Role::where('code', 'supervisor')->first();

    $this->cashier1 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->cashier2 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->supervisor1 = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'is_active' => true,
    ]);

    $this->supervisor2 = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'is_active' => true,
    ]);

    $store = StoreProfile::current();
    $store->update([
        'name' => 'Toko KasirPro Sentosa',
        'address' => 'Jl. Boulevard No. 88, Jakarta Barat',
        'phone' => '081234567890',
        'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
        'receipt_paper_width' => '58',
    ]);

    $this->bankAccount = CashAccount::firstOrCreate(
        ['code' => 'BANK-CONC'],
        [
            'name' => 'Bank BCA Konkurensi',
            'account_type' => 'bank',
            'opening_balance' => '5000000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->bankAccount, $this->supervisor1->id);
    } catch (Throwable $e) {
    }

    $this->cashAccount = CashAccount::firstOrCreate(
        ['code' => 'KAS-CONC'],
        [
            'name' => 'Kas Laci Konkurensi',
            'account_type' => 'cash',
            'opening_balance' => '500000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    try {
        $cashService->recordOpeningBalance($this->cashAccount, $this->cashier1->id);
    } catch (Throwable $e) {
    }

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-tf-conc'],
        ['name' => 'Kategori Transfer Konkurensi', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-tc'],
        ['name' => 'Pieces Transfer Conc', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-TC-01',
        'name' => 'Gula Pasir 1kg',
        'selling_price' => '16000.00',
        'minimum_stock' => '1.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
    $this->saleService = app(SaleService::class);
});

test('two supervisors attempting concurrent verification on the same pending transfer: first succeeds, second is rejected without double cash or stock mutation', function () {
    // Initial on-hand = 10 pcs
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '13000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-TC-01',
        userId: $this->supervisor1->id
    );

    // Cashier initiates pending transfer for 5 pcs
    $pendingSale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '5.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
                'reference_number' => 'REF-CONC-01',
            ],
        ],
        cashier: $this->cashier1,
        idempotencyKey: (string) Str::uuid()
    );

    // Supervisor 1 verifies successfully
    $sale1 = $this->saleService->verifyTransfer($pendingSale, $this->supervisor1, 'verified', 'Sah 1');
    expect($sale1->sale_status)->toBe('completed');

    // Supervisor 2 tries to verify the same sale
    expect(function () use ($pendingSale) {
        $this->saleService->verifyTransfer($pendingSale, $this->supervisor2, 'verified', 'Sah 2');
    })->toThrow(InvalidArgumentException::class);

    // Assert strictly only ONE OUT_SALE movement exists for this sale
    $outMovements = StockMovement::where('source_type', 'sale')
        ->where('source_id', $pendingSale->id)
        ->get();
    expect($outMovements)->toHaveCount(1)
        ->and($outMovements->first()->quantity_out)->toBe('5.000');

    // Assert strictly only ONE cash movement exists for this sale
    $cashMovements = CashMovement::where('source_type', 'sale')
        ->where('source_id', $pendingSale->id)
        ->get();
    expect($cashMovements)->toHaveCount(1)
        ->and($cashMovements->first()->amount)->toBe('80000.00');

    // Remaining physical on-hand must be exactly 5.000 (10 - 5)
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('5.000')
        ->and($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('5.000');
});

test('transfer reservation immediately protects stock from competing cash checkouts', function () {
    // Exactly 3 units available in stock
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '3.000',
        unitCost: '13000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-TC-02',
        userId: $this->supervisor1->id
    );

    // Cashier 1 checks out all 3 units via Transfer (holds reservation)
    $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '3.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->cashier1,
        idempotencyKey: (string) Str::uuid()
    );

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('3.000')
        ->and($balance->quantity_reserved)->toBe('3.000')
        ->and($balance->available_quantity)->toBe('0.000'); // 0 available!

    // Cashier 2 tries to buy 1 unit via Cash -> Must fail due to InsufficientStockException!
    expect(function () {
        $this->saleService->checkoutCash(
            payload: [
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => '1.000'],
                ],
                'payment' => [
                    'method' => 'cash',
                    'cash_account_id' => $this->cashAccount->id,
                    'cash_tendered' => '20000.00',
                ],
            ],
            cashier: $this->cashier2,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InsufficientStockException::class);
});

test('transaction rolls back completely if an exception occurs during verification', function () {
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '13000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-TC-03',
        userId: $this->supervisor1->id
    );

    $pendingSale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '2.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->cashier1,
        idempotencyKey: (string) Str::uuid()
    );

    $stockMovementsCount = StockMovement::count();
    $cashMovementsCount = CashMovement::count();

    // Deactivate bank account to cause an error during lock/movement recording
    $this->bankAccount->update(['is_active' => false]);

    try {
        $this->saleService->verifyTransfer($pendingSale, $this->supervisor1, 'verified');
    } catch (Throwable $e) {
        // expected exception
    }

    // Invariant: Full rollback, no partial stock out, no cash movement
    expect(StockMovement::count())->toBe($stockMovementsCount)
        ->and(CashMovement::count())->toBe($cashMovementsCount);

    $reloadedSale = $pendingSale->fresh();
    expect($reloadedSale->sale_status)->toBe('pending_payment')
        ->and($reloadedSale->payment_status)->toBe('pending_verification');
});
