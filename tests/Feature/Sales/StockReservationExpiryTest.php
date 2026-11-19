<?php

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->bankAccount = CashAccount::firstOrCreate(
        ['code' => 'BANK-EXPIRY'],
        [
            'name' => 'Bank Expiry Test',
            'account_type' => 'bank',
            'opening_balance' => '500000.00',
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
        ['slug' => 'kategori-expiry-test'],
        ['name' => 'Kategori Expiry Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-exp'],
        ['name' => 'Pieces Expiry', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-EXP-01',
        'name' => 'Susu Kotak UHT 1L',
        'selling_price' => '20000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '15.000',
        unitCost: '14000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-EXP-01',
        userId: $this->cashier->id
    );

    $this->saleService = app(SaleService::class);
});

test('artisan command sales:expire-reservations expires overdue active reservations and releases stock', function () {
    // 1. Create pending transfer (holds 4 pcs reserved)
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '4.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    $balanceBefore = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balanceBefore->quantity_reserved)->toBe('4.000')
        ->and($balanceBefore->available_quantity)->toBe('11.000');

    // 2. Manipulate expires_at to be in the past (overdue TTL)
    $reservation = StockReservation::where('sale_id', $sale->id)->first();
    $reservation->update(['expires_at' => Carbon::now('Asia/Jakarta')->subMinutes(5)]);

    // 3. Execute Scheduler command
    Artisan::call('sales:expire-reservations');

    // 4. Assert reservation status is expired
    $reservation->refresh();
    expect($reservation->status)->toBe('expired')
        ->and($reservation->released_at)->not->toBeNull();

    // 5. Assert Sale is cancelled with expiration reason
    $sale->refresh();
    expect($sale->sale_status)->toBe('cancelled')
        ->and($sale->payment_status)->toBe('expired')
        ->and($sale->cancellation_reason)->toContain('kedaluwarsa');

    // 6. Assert reserved stock is released back to available
    $balanceAfter = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balanceAfter->quantity_reserved)->toBe('0.000')
        ->and($balanceAfter->quantity_on_hand)->toBe('15.000')
        ->and($balanceAfter->available_quantity)->toBe('15.000');
});

test('active reservation not yet overdue is untouched by scheduler', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '2.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    // Reservation expires in future (+15 mins)
    Artisan::call('sales:expire-reservations');

    $reservation = StockReservation::where('sale_id', $sale->id)->first();
    expect($reservation->status)->toBe('active');

    $sale->refresh();
    expect($sale->sale_status)->toBe('pending_payment')
        ->and($sale->payment_status)->toBe('pending_verification');

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_reserved)->toBe('2.000');
});
