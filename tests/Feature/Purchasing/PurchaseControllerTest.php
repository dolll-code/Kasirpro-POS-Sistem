<?php

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\PurchaseService;
use App\Services\ReceivingService;

beforeEach(function () {
    $this->adminRole = Role::where('code', 'admin')->first();
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->supervisorRole = Role::where('code', 'supervisor')->first();

    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->supervisor = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'is_active' => true,
    ]);

    $this->supplier = Supplier::create([
        'supplier_code' => 'SUP-CTRL-01',
        'name' => 'PT Mitra Sejahtera',
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'ctrl-purchasing-cat'],
        ['name' => 'Kategori Controller PO', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'box'],
        ['name' => 'Box', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'CTRL-PROD-01',
        'name' => 'Produk Pembelian Uji',
        'selling_price' => '50000.00',
        'minimum_stock' => '5.000',
        'is_active' => true,
    ]);

    $this->cashAccount = CashAccount::create([
        'code' => 'KAS-CTRL-PO',
        'name' => 'Kas Toko Pembelian',
        'account_type' => 'cash',
        'opening_balance' => '2000000.00',
        'is_active' => true,
    ]);

    app(CashMovementService::class)->recordOpeningBalance($this->cashAccount, $this->admin->id);

    $this->purchaseService = app(PurchaseService::class);
    $this->receivingService = app(ReceivingService::class);
});

test('admin and supervisor can access purchasing index while cashier is forbidden', function () {
    $this->actingAs($this->admin)->get(route('purchases.index'))->assertOk();
    $this->actingAs($this->supervisor)->get(route('purchases.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('purchases.index'))->assertForbidden();
});

test('admin can create a draft purchase order via web form', function () {
    $response = $this->actingAs($this->admin)->post(route('purchases.store'), [
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'expected_date' => '2026-11-20',
        'document_discount_total' => '10000.00',
        'additional_cost_estimate' => '5000.00',
        'notes' => 'PO via Web Controller',
        'items' => [
            [
                'product_id' => $this->product->id,
                'ordered_qty' => '10',
                'unit_cost' => '30000.00',
                'line_discount' => '0',
            ],
        ],
    ]);

    $purchase = Purchase::where('supplier_id', $this->supplier->id)->latest('id')->first();

    $response->assertRedirect(route('purchases.show', $purchase));
    expect($purchase)->not->toBeNull()
        ->and($purchase->status)->toBe('draft')
        ->and($purchase->subtotal)->toBe('300000.00')
        ->and($purchase->payable_total)->toBe('295000.00'); // 300.000 - 10.000 + 5.000 = 295.000
});

test('confirming purchase order updates status to ordered and prohibits further direct edit', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '5', 'unit_cost' => '20000.00'],
        ],
    ], $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('purchases.confirm', $po));
    $response->assertRedirect(route('purchases.show', $po));

    $po->refresh();
    expect($po->status)->toBe('ordered')
        ->and($po->confirmed_by)->toBe($this->admin->id);

    // Edit attempt must redirect with error
    $editResponse = $this->actingAs($this->admin)->get(route('purchases.edit', $po));
    $editResponse->assertRedirect(route('purchases.show', $po));
    $editResponse->assertSessionHas('error');
});

test('admin can cancel purchase order with a mandatory reason', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '5', 'unit_cost' => '20000.00'],
        ],
    ], $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('purchases.cancel', $po), [
        'reason' => 'Perubahan anggaran belanja toko',
    ]);

    $response->assertRedirect(route('purchases.show', $po));
    $po->refresh();

    expect($po->status)->toBe('cancelled')
        ->and($po->cancel_reason)->toBe('Perubahan anggaran belanja toko');
});

test('cancellation requires reason input', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '5', 'unit_cost' => '20000.00'],
        ],
    ], $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('purchases.cancel', $po), [
        'reason' => '',
    ]);

    $response->assertSessionHasErrors(['reason']);
});

test('draft PO can be deleted, but non-draft PO deletion returns 409 error', function () {
    $draftPo = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '2', 'unit_cost' => '10000.00'],
        ],
    ], $this->admin->id);

    $this->actingAs($this->admin)->delete(route('purchases.destroy', $draftPo))
        ->assertRedirect(route('purchases.index'));
    $this->assertDatabaseMissing('purchases', ['id' => $draftPo->id]);

    $orderedPo = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '2', 'unit_cost' => '10000.00'],
        ],
    ], $this->admin->id);
    $this->purchaseService->confirm($orderedPo, $this->admin->id);

    $response = $this->actingAs($this->admin)->delete(route('purchases.destroy', $orderedPo));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('purchases', ['id' => $orderedPo->id]);
});

test('posting goods receipt via controller updates stock and redirects to receipt detail', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '10', 'unit_cost' => '25000.00'],
        ],
    ], $this->admin->id);
    $this->purchaseService->confirm($po, $this->admin->id);
    $item = $po->items->first();

    $response = $this->actingAs($this->admin)->post(route('purchases.receipts.store', $po), [
        'received_at' => now()->format('Y-m-d H:i:s'),
        'capitalizable_additional_cost' => '5000.00',
        'items' => [
            [
                'purchase_item_id' => $item->id,
                'physically_received_qty' => '10',
                'accepted_qty' => '10',
                'rejected_qty' => '0',
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $po->refresh();
    expect($po->status)->toBe('received');
});

test('recording supplier payment via controller deducts cash and reduces outstanding', function () {
    $po = $this->purchaseService->createDraft([
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '4', 'unit_cost' => '25000.00'], // 100.000
        ],
    ], $this->admin->id);
    $this->purchaseService->confirm($po, $this->admin->id);
    $item = $po->items->first();

    $this->receivingService->postReceipt($po, [
        'items' => [
            [
                'purchase_item_id' => $item->id,
                'physically_received_qty' => '4',
                'accepted_qty' => '4',
                'rejected_qty' => '0',
            ],
        ],
    ], $this->admin->id);

    $response = $this->actingAs($this->admin)->post(route('purchases.payments.store', $po), [
        'cash_account_id' => $this->cashAccount->id,
        'method' => 'cash',
        'amount' => '100000.00',
        'reference_number' => 'REF-BAYAR-01',
    ]);

    $response->assertRedirect(route('purchases.show', $po));
    $response->assertSessionHas('success');

    $po->refresh();
    expect($po->payment_status)->toBe('paid')
        ->and($po->paid_amount)->toBe('100000.00')
        ->and($po->outstandingPayable()->toString())->toBe('0.00');
});

test('api endpoints return structured json for purchases, receipts, and payments', function () {
    // 1. Create via JSON API
    $createRes = $this->actingAs($this->admin)->postJson('/api/v1/purchases', [
        'supplier_id' => $this->supplier->id,
        'order_date' => '2026-11-18',
        'items' => [
            ['product_id' => $this->product->id, 'ordered_qty' => '6', 'unit_cost' => '15000.00'],
        ],
    ]);

    $createRes->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'purchase_number', 'status', 'payable_total']]);

    $poId = $createRes->json('data.id');

    // 2. Confirm via JSON API
    $confirmRes = $this->actingAs($this->admin)->postJson("/api/v1/purchases/{$poId}/confirm");
    $confirmRes->assertOk()
        ->assertJsonPath('data.status', 'ordered');

    $item = Purchase::find($poId)->items->first();

    // 3. Post Receipt via JSON API
    $rcvRes = $this->actingAs($this->admin)->postJson("/api/v1/purchases/{$poId}/receipts", [
        'items' => [
            [
                'purchase_item_id' => $item->id,
                'physically_received_qty' => '6',
                'accepted_qty' => '6',
                'rejected_qty' => '0',
            ],
        ],
    ]);

    $rcvRes->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'receipt_number', 'status']]);

    // 4. Pay Supplier via JSON API
    $payRes = $this->actingAs($this->admin)->postJson("/api/v1/purchases/{$poId}/payments", [
        'cash_account_id' => $this->cashAccount->id,
        'method' => 'cash',
        'amount' => '90000.00',
    ]);

    $payRes->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'payment_number', 'amount']]);
});
