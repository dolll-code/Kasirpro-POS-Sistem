<?php

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StoreProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->supervisorRole = Role::where('code', 'supervisor')->first();
    $this->adminRole = Role::where('code', 'admin')->first();

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->supervisor = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'is_active' => true,
    ]);

    $this->admin = User::factory()->create([
        'role_id' => $this->adminRole->id,
        'is_active' => true,
    ]);

    $store = StoreProfile::current();
    $store->update([
        'name' => 'Toko KasirPro Sentosa',
        'address' => 'Jl. Boulevard No. 88',
        'phone' => '081234567890',
        'receipt_paper_width' => '58',
    ]);

    $this->bankAccount = CashAccount::firstOrCreate(
        ['code' => 'BANK-HTTP'],
        [
            'name' => 'Bank Mandiri Toko',
            'account_type' => 'bank',
            'opening_balance' => '1000000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->bankAccount, $this->admin->id);
    } catch (Throwable $e) {
    }

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-http-tf'],
        ['name' => 'Kategori HTTP Transfer', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-htf'],
        ['name' => 'Pieces HTTP TF', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-HTF-01',
        'name' => 'Kecap Manis 600ml',
        'selling_price' => '22000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    $inventoryService = app(InventoryService::class);
    $inventoryService->addStock(
        product: $this->product,
        quantity: '20.000',
        unitCost: '18000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-HTF-01',
        userId: $this->admin->id
    );

    $this->saleService = app(SaleService::class);
});

test('http api checkout transfer creates pending sale with 201 response and reservation expiry', function () {
    $this->actingAs($this->cashier);

    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'],
        ],
        'payment' => [
            'method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
            'reference_number' => 'REF-API-999',
        ],
        'idempotency_key' => (string) Str::uuid(),
    ];

    $response = $this->postJson('/api/v1/sales/checkout', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'data' => [
                'sale_status' => 'pending_payment',
                'payment_status' => 'pending_verification',
                'grand_total' => '44000.00',
            ],
        ]);

    expect($response->json('data.reservation_expires_at'))->not->toBeNull();
});

test('supervisor can view pending transfers list via web and json api', function () {
    // Create pending sale
    $this->saleService->checkoutTransfer(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => ['method' => 'bank_transfer', 'cash_account_id' => $this->bankAccount->id],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    $this->actingAs($this->supervisor);

    // Web endpoint
    $resWeb = $this->get(route('sales.transfers.pending'));
    $resWeb->assertStatus(200)
        ->assertSee('Verifikasi Transfer Bank')
        ->assertSee('Bank Mandiri Toko');

    // JSON endpoint
    $resJson = $this->getJson('/api/v1/sales/transfers/pending');
    $resJson->assertStatus(200)
        ->assertJsonStructure(['data' => ['data'], 'meta' => ['pending_count']]);
});

test('supervisor can verify transfer via http api and receives 200 with completed status', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => ['method' => 'bank_transfer', 'cash_account_id' => $this->bankAccount->id],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    $this->actingAs($this->supervisor);

    $res = $this->postJson("/api/v1/sales/{$sale->id}/verify-transfer", [
        'decision' => 'verified',
        'verification_note' => 'Mutasi cocok',
    ]);

    $res->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $sale->id,
                'sale_status' => 'completed',
                'payment_status' => 'paid',
            ],
        ]);
});

test('cashier cannot self-verify transfer via http api and receives 403 forbidden', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => ['method' => 'bank_transfer', 'cash_account_id' => $this->bankAccount->id],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    $this->actingAs($this->cashier);

    $res = $this->postJson("/api/v1/sales/{$sale->id}/verify-transfer", [
        'decision' => 'verified',
    ]);

    $res->assertStatus(403);
});

test('attempting to verify expired transfer via http api returns 409 conflict', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => ['method' => 'bank_transfer', 'cash_account_id' => $this->bankAccount->id],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    // Expire reservation
    $sale->reservation->update(['expires_at' => Carbon::now('Asia/Jakarta')->subMinutes(1)]);

    $this->actingAs($this->supervisor);

    $res = $this->postJson("/api/v1/sales/{$sale->id}/verify-transfer", [
        'decision' => 'verified',
    ]);

    $res->assertStatus(409)
        ->assertJson([
            'error' => [
                'code' => 'RESERVATION_EXPIRED',
            ],
        ]);
});

test('cashier can cancel own pending transfer via http api', function () {
    $sale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => ['method' => 'bank_transfer', 'cash_account_id' => $this->bankAccount->id],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );

    $this->actingAs($this->cashier);

    $res = $this->postJson("/api/v1/sales/{$sale->id}/cancel", [
        'reason' => 'Pelanggan tidak jadi beli',
    ]);

    $res->assertStatus(200)
        ->assertJson([
            'data' => [
                'sale_status' => 'cancelled',
            ],
        ]);
});
