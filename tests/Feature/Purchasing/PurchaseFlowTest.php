<?php

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
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
        'supplier_code' => 'SUP-TEST-01',
        'name' => 'PT Sumber Makmur Sejahtera',
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'sembako-purchasing'],
        ['name' => 'Sembako Test', 'is_active' => true]
    );

    $this->unitPcs = Unit::firstOrCreate(
        ['symbol' => 'pcs'],
        ['name' => 'Pcs', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->productA = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'PROD-PURCH-A',
        'name' => 'Minyak Goreng 2L',
        'selling_price' => '35000.00',
        'minimum_stock' => '10.000',
        'is_active' => true,
    ]);

    $this->productB = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unitPcs->id,
        'sku' => 'PROD-PURCH-B',
        'name' => 'Gula Pasir 1Kg',
        'selling_price' => '18000.00',
        'minimum_stock' => '10.000',
        'is_active' => true,
    ]);

    $this->cashAccount = CashAccount::create([
        'code' => 'KAS-PURCH-01',
        'name' => 'Kas Operasional Pembelian',
        'account_type' => 'cash',
        'opening_balance' => '5000000.00',
        'is_active' => true,
    ]);

    $this->cashMovementService = app(CashMovementService::class);
    $this->cashMovementService->recordOpeningBalance($this->cashAccount, $this->user->id);

    $this->purchaseService = app(PurchaseService::class);
    $this->receivingService = app(ReceivingService::class);
    $this->paymentService = app(SupplierPaymentService::class);
});

test('create PO draft calculates line totals and allocated document discount deterministically', function () {
    // Product A: 10 @ 25.000 = 250.000, discount 10.000 => basis 240.000
    // Product B: 20 @ 12.000 = 240.000, discount 0 => basis 240.000
    // Net items subtotal: 480.000
    // Header document discount: 40.000 (divided equally 20.000 each)
    // Additional cost estimate: 10.000
    // Estimated payable: 480.000 - 40.000 + 10.000 = 450.000

    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'document_discount_total' => '40000.00',
        'additional_cost_estimate' => '10000.00',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '25000.00',
                'line_discount' => '10000.00',
            ],
            [
                'product_id' => $this->productB->id,
                'ordered_qty' => '20.000',
                'unit_cost' => '12000.00',
                'line_discount' => '0.00',
            ],
        ],
    ], $this->user->id);

    expect($po)->toBeInstanceOf(Purchase::class)
        ->and($po->status)->toBe('draft')
        ->and($po->subtotal)->toBe('490000.00')
        ->and($po->item_discount_total)->toBe('10000.00')
        ->and($po->document_discount_total)->toBe('40000.00')
        ->and($po->additional_cost_estimate)->toBe('10000.00')
        ->and($po->payable_total)->toBe('450000.00');

    expect($po->items)->toHaveCount(2);

    $itemA = $po->items->firstWhere('product_id', $this->productA->id);
    expect($itemA->allocated_document_discount)->toBe('20000.00')
        ->and($itemA->line_total)->toBe('220000.00'); // 240.000 - 20.000 = 220.000

    $itemB = $po->items->firstWhere('product_id', $this->productB->id);
    expect($itemB->allocated_document_discount)->toBe('20000.00')
        ->and($itemB->line_total)->toBe('220000.00'); // 240.000 - 20.000 = 220.000
});

test('PO cannot be created with inactive supplier', function () {
    $this->supplier->update(['is_active' => false]);

    expect(function () {
        $this->purchaseService->createDraft([
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-11-18',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'ordered_qty' => '5.000',
                    'unit_cost' => '20000.00',
                ],
            ],
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, 'Pemasok [PT Sumber Makmur Sejahtera] berstatus nonaktif');
});

test('confirm transitions PO draft to ordered and locks direct editing', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '25000.00',
            ],
        ],
    ], $this->user->id);

    $confirmedPo = $this->purchaseService->confirm($po, $this->user->id);

    expect($confirmedPo->status)->toBe('ordered')
        ->and($confirmedPo->confirmed_by)->toBe($this->user->id)
        ->and($confirmedPo->confirmed_at)->not->toBeNull();

    // Updating confirmed PO must fail
    expect(function () use ($confirmedPo) {
        $this->purchaseService->updateDraft($confirmedPo, [
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-11-18',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'ordered_qty' => '15.000',
                    'unit_cost' => '25000.00',
                ],
            ],
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, 'tidak dapat diedit langsung');
});

test('partial receipt updates stock for accepted items, ignores rejected, and updates moving average cost', function () {
    // Initial stock = 0, average_cost = 0
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Partial receiving: physically 6, accepted 4, rejected 2
    $receipt = $this->receivingService->postReceipt($po, [
        'received_at' => now()->toIso8601String(),
        'capitalizable_additional_cost' => '4000.00', // 4.000 distributed to 4 accepted units = 1.000/unit extra
        'notes' => 'Penerimaan parsial pertama',
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '6.000',
                'accepted_qty' => '4.000',
                'rejected_qty' => '2.000',
                'rejection_reason' => '2 botol bocor kemasan penyok',
            ],
        ],
    ], $this->user->id);

    expect($receipt)->toBeInstanceOf(PurchaseReceipt::class)
        ->and($receipt->status)->toBe('posted');

    // Landed cost per accepted unit = 20.000 (base) + (4.000 / 4) = 21.000
    $rcvItem = $receipt->items->first();
    expect($rcvItem->physically_received_qty)->toBe('6.000')
        ->and($rcvItem->accepted_qty)->toBe('4.000')
        ->and($rcvItem->rejected_qty)->toBe('2.000')
        ->and($rcvItem->landed_unit_cost)->toBe('21000.00')
        ->and($rcvItem->landed_total_cost)->toBe('84000.00');

    // Inventory balance updated: on_hand = 4.000 (only accepted!), average_cost = 21000.00
    $balance = InventoryBalance::where('product_id', $this->productA->id)->first();
    expect($balance->quantity_on_hand)->toBe('4.000')
        ->and($balance->average_cost)->toBe('21000.00');

    // Stock movement exists
    $stockMovement = StockMovement::where('source_type', 'purchase_receipts')
        ->where('source_id', $receipt->id)
        ->first();
    expect($stockMovement)->not->toBeNull()
        ->and($stockMovement->movement_type)->toBe('IN_PURCHASE')
        ->and($stockMovement->quantity_in)->toBe('4.000')
        ->and($stockMovement->unit_cost)->toBe('21000.00');

    // PO status is now partially_received
    $po->refresh();
    expect($po->status)->toBe('partially_received');

    // PO item remaining qty is 10 - 4 = 6
    $itemA->refresh();
    expect($itemA->accepted_qty_total)->toBe('4.000')
        ->and($itemA->rejected_qty_total)->toBe('2.000')
        ->and($itemA->remainingQty()->toString())->toBe('6.000');
});

test('receiving rejects quantity mismatch when accepted plus rejected does not equal physical', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // 5 accepted + 2 rejected != 6 physical
    expect(function () use ($po, $itemA) {
        $this->receivingService->postReceipt($po, [
            'items' => [
                [
                    'purchase_item_id' => $itemA->id,
                    'physically_received_qty' => '6.000',
                    'accepted_qty' => '5.000',
                    'rejected_qty' => '2.000',
                ],
            ],
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, 'harus tepat sama');
});

test('receiving rejects accepted quantity exceeding remaining ordered quantity', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '5.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Remaining is 5, attempting to accept 6
    expect(function () use ($po, $itemA) {
        $this->receivingService->postReceipt($po, [
            'items' => [
                [
                    'purchase_item_id' => $itemA->id,
                    'physically_received_qty' => '6.000',
                    'accepted_qty' => '6.000',
                    'rejected_qty' => '0.000',
                ],
            ],
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, 'melebihi sisa pesanan PO');
});

test('full receipt updates PO status to received', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '8.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Full receipt of 8
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '8.000',
                'accepted_qty' => '8.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    $po->refresh();
    expect($po->status)->toBe('received');
});

test('cancel PO after partial receipt closes remaining unreceived quantity with reason', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Receive 4
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '4.000',
                'accepted_qty' => '4.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    // Cancel remaining order of 6
    $cancelledPo = $this->purchaseService->cancel($po, 'Supplier kehabisan stok sisa barang', $this->user->id);

    expect($cancelledPo->status)->toBe('cancelled')
        ->and($cancelledPo->cancel_reason)->toBe('Supplier kehabisan stok sisa barang');

    $itemA->refresh();
    expect($itemA->accepted_qty_total)->toBe('4.000')
        ->and($itemA->cancelled_qty)->toBe('6.000')
        ->and($itemA->remainingQty()->isZero())->toBeTrue();

    // Accepted stock in inventory remains intact (4.000)
    $balance = InventoryBalance::where('product_id', $this->productA->id)->first();
    expect($balance->quantity_on_hand)->toBe('4.000');
});

test('supplier payment creates cash outflow and updates payment status', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00', // 200.000
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Receive 5 items @ 20.000 = 100.000 accepted payable
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '5.000',
                'accepted_qty' => '5.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    $po->refresh();
    expect($po->totalAcceptedPayable()->toString())->toBe('100000.00')
        ->and($po->outstandingPayable()->toString())->toBe('100000.00');

    // Partial payment: 60.000
    $payment1 = $this->paymentService->recordPayment($po, [
        'cash_account_id' => $this->cashAccount->id,
        'method' => 'cash',
        'amount' => '60000.00',
        'reference_number' => 'KWT-001',
    ], $this->user->id);

    expect($payment1)->toBeInstanceOf(SupplierPayment::class)
        ->and($payment1->amount)->toBe('60000.00')
        ->and($payment1->cash_movement_id)->not->toBeNull();

    // Verify CashMovement
    $movement = CashMovement::find($payment1->cash_movement_id);
    expect($movement->direction)->toBe('out')
        ->and($movement->amount)->toBe('60000.00')
        ->and($movement->movement_type)->toBe('SUPPLIER_PAYMENT');

    $po->refresh();
    expect($po->paid_amount)->toBe('60000.00')
        ->and($po->payment_status)->toBe('partially_paid')
        ->and($po->outstandingPayable()->toString())->toBe('40000.00');

    // Second payment: 40.000 to fully settle accepted payable
    $payment2 = $this->paymentService->recordPayment($po, [
        'cash_account_id' => $this->cashAccount->id,
        'method' => 'cash',
        'amount' => '40000.00',
    ], $this->user->id);

    $po->refresh();
    expect($po->paid_amount)->toBe('100000.00')
        ->and($po->payment_status)->toBe('paid')
        ->and($po->outstandingPayable()->toString())->toBe('0.00');
});

test('supplier payment exceeding outstanding payable is rejected', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Receive 2 items @ 20.000 = 40.000
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '2.000',
                'accepted_qty' => '2.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    // Attempt to pay 50.000 (exceeds 40.000 outstanding)
    expect(function () use ($po) {
        $this->paymentService->recordPayment($po, [
            'cash_account_id' => $this->cashAccount->id,
            'method' => 'cash',
            'amount' => '50000.00',
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, 'melebihi sisa utang');
});

test('supplier payment reversal restores cash account balance and updates PO payment status', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '5.000',
                'unit_cost' => '20000.00', // 100.000
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '5.000',
                'accepted_qty' => '5.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    // Pay 100.000 -> status paid
    $payment = $this->paymentService->recordPayment($po, [
        'cash_account_id' => $this->cashAccount->id,
        'method' => 'cash',
        'amount' => '100000.00',
    ], $this->user->id);

    $po->refresh();
    expect($po->payment_status)->toBe('paid');

    // Reverse payment
    $reversed = $this->paymentService->reversePayment($payment, 'Koreksi salah bayar via transfer', $this->user->id);

    expect($reversed->isReversed())->toBeTrue()
        ->and($reversed->reversal_reason)->toBe('Koreksi salah bayar via transfer');

    $po->refresh();
    expect($po->paid_amount)->toBe('0.00')
        ->and($po->payment_status)->toBe('unpaid')
        ->and($po->outstandingPayable()->toString())->toBe('100000.00');

    // Cash balance restored
    $this->cashAccount->refresh();
    expect($this->cashAccount->current_balance)->toBe('5000000.00');
});

test('transaction rolls back completely when an error occurs during receipt posting', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            [
                'product_id' => $this->productA->id,
                'ordered_qty' => '10.000',
                'unit_cost' => '20000.00',
            ],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    expect(PurchaseReceipt::count())->toBe(0)
        ->and(StockMovement::where('product_id', $this->productA->id)->count())->toBe(0);

    // Force failure inside transaction
    try {
        DB::transaction(function () use ($po, $itemA) {
            $this->receivingService->postReceipt($po, [
                'items' => [
                    [
                        'purchase_item_id' => $itemA->id,
                        'physically_received_qty' => '5.000',
                        'accepted_qty' => '5.000',
                        'rejected_qty' => '0.000',
                    ],
                ],
            ], $this->user->id);

            throw new RuntimeException('Simulated unexpected failure right after receipt post');
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('Simulated unexpected failure right after receipt post');
    }

    // Assert complete rollback
    expect(PurchaseReceipt::count())->toBe(0)
        ->and(StockMovement::where('product_id', $this->productA->id)->count())->toBe(0);

    $po->refresh();
    expect($po->status)->toBe('ordered');

    $balance = InventoryBalance::where('product_id', $this->productA->id)->first();
    expect($balance)->toBeNull();
});

test('posting receipt to already received or cancelled PO is strictly rejected', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->productA->id, 'ordered_qty' => '5.000', 'unit_cost' => '20000.00'],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    // Full receipt
    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '5.000',
                'accepted_qty' => '5.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    $po->refresh();
    expect($po->status)->toBe('received');

    // Attempting duplicate post on received PO
    expect(function () use ($po, $itemA) {
        $this->receivingService->postReceipt($po, [
            'items' => [
                [
                    'purchase_item_id' => $itemA->id,
                    'physically_received_qty' => '2.000',
                    'accepted_qty' => '2.000',
                    'rejected_qty' => '0.000',
                ],
            ],
        ], $this->user->id);
    })->toThrow(InvalidArgumentException::class, "hanya dapat diposting untuk PO berstatus 'ordered' atau 'partially_received'");
});

test('purchase receipts are append-only and cannot be updated or deleted', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->productA->id, 'ordered_qty' => '3.000', 'unit_cost' => '20000.00'],
        ],
    ], $this->user->id);

    $this->purchaseService->confirm($po, $this->user->id);
    $itemA = $po->items->first();

    $receipt = $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $itemA->id,
                'physically_received_qty' => '3.000',
                'accepted_qty' => '3.000',
                'rejected_qty' => '0.000',
            ],
        ],
    ], $this->user->id);

    expect(function () use ($receipt) {
        $receipt->update(['notes' => 'Tampering attempt']);
    })->toThrow(RuntimeException::class, 'Purchase receipts are immutable/append-only');

    expect(function () use ($receipt) {
        $receipt->delete();
    })->toThrow(RuntimeException::class, 'Purchase receipts are immutable/append-only');
});
