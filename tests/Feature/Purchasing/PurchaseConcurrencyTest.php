<?php

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\PurchaseService;
use App\Services\ReceivingService;
use App\Services\SupplierPaymentService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::first() ?? User::factory()->create();

    $this->supplier = Supplier::create([
        'supplier_code' => 'SUP-CONC-01',
        'name' => 'PT Distribusi Kilat',
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'conc-purchasing-cat'],
        ['name' => 'Kategori Concurrency PO', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pack'],
        ['name' => 'Pack', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'CONC-PO-ITEM-01',
        'name' => 'Item Concurrency PO',
        'selling_price' => '40000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $this->cashAccount = CashAccount::create([
        'code' => 'KAS-CONC-PO',
        'name' => 'Kas Concurrency Testing',
        'account_type' => 'cash',
        'opening_balance' => '5000000.00',
        'is_active' => true,
    ]);

    app(CashMovementService::class)->recordOpeningBalance($this->cashAccount, $this->user->id);

    $this->purchaseService = app(PurchaseService::class);
    $this->receivingService = app(ReceivingService::class);
    $this->paymentService = app(SupplierPaymentService::class);
});

test('competing receipts on limited PO remainder: first succeeds, second is rejected by row lock without over-receiving', function () {
    // PO of 10 items
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '10', 'unit_cost' => '20000.00'],
        ],
    ], $this->user->id);
    $this->purchaseService->confirm($po, $this->user->id);
    $item = $po->items->first();

    $firstSucceeded = false;
    $secondFailed = false;

    // Transaction 1 accepts 8
    DB::transaction(function () use ($po, $item, &$firstSucceeded) {
        $this->receivingService->postReceipt($po, [
            'items' => [
                [
                    'purchase_item_id' => $item->id,
                    'physically_received_qty' => '8',
                    'accepted_qty' => '8',
                    'rejected_qty' => '0',
                ],
            ],
        ], $this->user->id);
        $firstSucceeded = true;
    });

    // Transaction 2 tries to accept 5 (only 2 remaining)
    try {
        DB::transaction(function () use ($po, $item) {
            $this->receivingService->postReceipt($po, [
                'items' => [
                    [
                        'purchase_item_id' => $item->id,
                        'physically_received_qty' => '5',
                        'accepted_qty' => '5',
                        'rejected_qty' => '0',
                    ],
                ],
            ], $this->user->id);
        });
    } catch (InvalidArgumentException $e) {
        $secondFailed = true;
        expect($e->getMessage())->toContain('melebihi sisa pesanan PO');
    }

    expect($firstSucceeded)->toBeTrue();
    expect($secondFailed)->toBeTrue();

    // Verify stock is strictly 8 and remaining is 2
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('8.000');

    $item->refresh();
    expect($item->accepted_qty_total)->toBe('8.000')
        ->and($item->remainingQty()->toString())->toBe('2.000');
});

test('competing supplier payments on limited payable: first succeeds, second is rejected without overpaying', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '5', 'unit_cost' => '20000.00'], // 100.000
        ],
    ], $this->user->id);
    $this->purchaseService->confirm($po, $this->user->id);
    $item = $po->items->first();

    // Post full receipt -> 100.000 payable
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $item->id,
                'physically_received_qty' => '5',
                'accepted_qty' => '5',
                'rejected_qty' => '0',
            ],
        ],
    ], $this->user->id);

    $firstPaySucceeded = false;
    $secondPayFailed = false;

    // Payment 1: 70.000
    DB::transaction(function () use ($po, &$firstPaySucceeded) {
        $this->paymentService->recordPayment($po, [
            'cash_account_id' => $this->cashAccount->id,
            'method' => 'cash',
            'amount' => '70000.00',
        ], $this->user->id);
        $firstPaySucceeded = true;
    });

    // Payment 2: 50.000 (only 30.000 remains)
    try {
        DB::transaction(function () use ($po) {
            $this->paymentService->recordPayment($po, [
                'cash_account_id' => $this->cashAccount->id,
                'method' => 'cash',
                'amount' => '50000.00',
            ], $this->user->id);
        });
    } catch (InvalidArgumentException $e) {
        $secondPayFailed = true;
        expect($e->getMessage())->toContain('melebihi sisa utang');
    }

    expect($firstPaySucceeded)->toBeTrue();
    expect($secondPayFailed)->toBeTrue();

    $po->refresh();
    expect($po->paid_amount)->toBe('70000.00')
        ->and($po->outstandingPayable()->toString())->toBe('30000.00');
});

test('rapid sequential partial receipts sum up accurately without race condition drift', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '10', 'unit_cost' => '15000.00'],
        ],
    ], $this->user->id);
    $this->purchaseService->confirm($po, $this->user->id);
    $item = $po->items->first();

    // 5 partial receipts of 2 units each
    for ($i = 1; $i <= 5; $i++) {
        $this->receivingService->postReceipt($po, [
            'items' => [
                [
                    'purchase_item_id' => $item->id,
                    'physically_received_qty' => '2',
                    'accepted_qty' => '2',
                    'rejected_qty' => '0',
                ],
            ],
        ], $this->user->id);
    }

    $po->refresh();
    expect($po->status)->toBe('received');

    $item->refresh();
    expect($item->accepted_qty_total)->toBe('10.000')
        ->and($item->remainingQty()->isZero())->toBeTrue();

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('10.000')
        ->and($balance->average_cost)->toBe('15000.00');
});
