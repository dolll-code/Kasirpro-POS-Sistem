<?php

use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientStockException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockReservation;
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

    $store = StoreProfile::current();
    $store->update([
        'name' => 'Toko KasirPro Sentosa',
        'address' => 'Jl. Boulevard No. 88, Jakarta Barat',
        'phone' => '081234567890',
        'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
        'receipt_paper_width' => '58',
    ]);

    // Setup Bank Account (BCA-TOKO)
    $this->bankAccount = CashAccount::firstOrCreate(
        ['code' => 'BANK-BCA'],
        [
            'name' => 'Bank BCA Toko',
            'account_type' => 'bank',
            'opening_balance' => '1000000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->bankAccount, $this->cashier->id);
    } catch (Throwable $e) {
    }

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-transfer-test'],
        ['name' => 'Kategori Transfer Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-tf'],
        ['name' => 'Pieces Transfer', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-TF-01',
        'name' => 'Minyak Goreng 2L',
        'selling_price' => '35000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    // Add initial on-hand stock = 20 pcs @ Rp 28.000
    $this->inventoryService = app(InventoryService::class);
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '20.000',
        unitCost: '28000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-TF-01',
        userId: $this->cashier->id
    );

    $this->saleService = app(SaleService::class);
});

test('transfer checkout creates pending sale and holds stock reservation without stock out or cash in', function () {
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '3.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
            'reference_number' => 'REF-TRANSFER-101',
        ],
    ];

    $stockMovementsBefore = StockMovement::count();
    $cashMovementsBefore = CashMovement::count();

    $sale = $this->saleService->checkoutTransfer(
        payload: $payload,
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    // 1. Sale status & payment status assertions (PRD §5.5, §7.6)
    expect($sale->sale_status)->toBe('pending_payment')
        ->and($sale->payment_status)->toBe('pending_verification')
        ->and($sale->gross_total)->toBe('105000.00')
        ->and($sale->grand_total)->toBe('105000.00')
        ->and($sale->pending_expires_at)->not->toBeNull()
        ->and($sale->completed_at)->toBeNull();

    // 2. Stock Reservation created
    $reservation = StockReservation::where('sale_id', $sale->id)->first();
    expect($reservation)->not->toBeNull()
        ->and($reservation->status)->toBe('active')
        ->and($reservation->expires_at->isFuture())->toBeTrue()
        ->and($reservation->items)->toHaveCount(1)
        ->and($reservation->items->first()->quantity)->toBe('3.000');

    // 3. Inventory Balance: quantity_reserved increased, on_hand UNCHANGED, available reduced
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('20.000') // Physical stock NOT reduced yet!
        ->and($balance->quantity_reserved)->toBe('3.000')
        ->and($balance->available_quantity)->toBe('17.000'); // 20 - 3 = 17

    // 4. CRITICAL: No stock movements or cash movements created before verification!
    expect(StockMovement::count())->toBe($stockMovementsBefore)
        ->and(CashMovement::count())->toBe($cashMovementsBefore);
});

test('duplicate product cart lines aggregate to one reservation item without losing sale lines', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '2.000'],
                ['product_id' => $this->product->id, 'quantity' => '3.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    expect($sale->items)->toHaveCount(2)
        ->and($sale->reservation->items)->toHaveCount(1)
        ->and($sale->reservation->items->first()->quantity)->toBe('5.000')
        ->and(InventoryBalance::where('product_id', $this->product->id)->first()->quantity_reserved)->toBe('5.000');
});

test('transfer checkout rejects when requested quantity exceeds available stock', function () {
    // Current on-hand: 20 pcs. Requesting 25 pcs
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '25.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
            'reference_number' => 'REF-OVER-01',
        ],
    ];

    expect(function () use ($payload) {
        $this->saleService->checkoutTransfer(
            payload: $payload,
            cashier: $this->cashier,
            idempotencyKey: (string) Str::uuid()
        );
    })->toThrow(InsufficientStockException::class);

    // Balance reserved must remain 0
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_reserved)->toBe('0.000');
});

test('transfer checkout idempotency: repeated request with same key returns original pending sale without duplicate reservation', function () {
    $key = (string) Str::uuid();
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
            'reference_number' => 'REF-DUP-01',
        ],
    ];

    $sale1 = $this->saleService->checkoutTransfer($payload, $this->cashier, $key);
    $sale2 = $this->saleService->checkoutTransfer($payload, $this->cashier, $key);

    expect($sale2->id)->toBe($sale1->id)
        ->and($sale2->transaction_number)->toBe($sale1->transaction_number);

    // Reserved quantity must remain exactly 2.000 (NOT 4.000)
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_reserved)->toBe('2.000');
});

test('transfer checkout idempotency: same key with different payload throws 409 conflict', function () {
    $key = (string) Str::uuid();
    $payload1 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ],
    ];

    $payload2 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '4.000'], // Different quantity!
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ],
    ];

    $this->saleService->checkoutTransfer($payload1, $this->cashier, $key);

    expect(function () use ($payload2, $key) {
        $this->saleService->checkoutTransfer($payload2, $this->cashier, $key);
    })->toThrow(IdempotencyConflictException::class);
});

test('cashier can cancel pending transfer before TTL and release stock reservation', function () {
    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '5.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ],
    ];

    $sale = $this->saleService->checkoutTransfer($payload, $this->cashier, (string) Str::uuid());

    // Reserved is 5.000
    expect(InventoryBalance::where('product_id', $this->product->id)->first()->quantity_reserved)->toBe('5.000');

    // Cancel pending transfer
    $cancelled = $this->saleService->cancelPendingTransfer($sale, $this->cashier, 'Pelanggan membatalkan pesanan');

    expect($cancelled->sale_status)->toBe('cancelled')
        ->and($cancelled->cancellation_reason)->toBe('Pelanggan membatalkan pesanan')
        ->and($cancelled->reservation->status)->toBe('released');

    // Reserved must be restored to 0.000
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('20.000');
});
