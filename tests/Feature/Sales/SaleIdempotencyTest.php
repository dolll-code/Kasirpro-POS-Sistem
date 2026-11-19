<?php

use App\Exceptions\IdempotencyConflictException;
use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
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

    $this->cashAccount = CashAccount::firstOrCreate(
        ['code' => 'KAS-TOKO'],
        [
            'name' => 'Kas Toko',
            'account_type' => 'cash',
            'opening_balance' => '500000.00',
            'opening_balance_at' => now(),
            'is_active' => true,
        ]
    );

    $cashService = app(CashMovementService::class);
    try {
        $cashService->recordOpeningBalance($this->cashAccount, $this->cashier->id);
    } catch (\Throwable $e) {}

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-idempotency-test'],
        ['name' => 'Kategori Idempotency Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-idem'],
        ['name' => 'Pieces Idem', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-IDEM-01',
        'name' => 'Produk Uji Idempotensi',
        'selling_price' => '20000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);

    $inventoryService = app(InventoryService::class);
    $inventoryService->addStock(
        product: $this->product,
        quantity: '50.000',
        unitCost: '12000.00',
        movementType: 'IN_INITIAL',
        sourceType: 'opening_balance',
        userId: $this->cashier->id
    );

    $this->saleService = app(SaleService::class);
});

test('repeated checkout with same idempotency key and same payload returns original sale without duplicate movements', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '50000.00',
        ],
    ];

    // First checkout
    $sale1 = $this->saleService->checkoutCash($payload, $this->cashier, $idempotencyKey);
    $stockMovementCount1 = StockMovement::where('source_type', 'sale')->count();

    // Second checkout with identical key and payload
    $sale2 = $this->saleService->checkoutCash($payload, $this->cashier, $idempotencyKey);
    $stockMovementCount2 = StockMovement::where('source_type', 'sale')->count();

    expect($sale2->id)->toBe($sale1->id)
        ->and($sale2->transaction_number)->toBe($sale1->transaction_number)
        ->and($stockMovementCount2)->toBe($stockMovementCount1);
});

test('checkout with same idempotency key but different payload throws 409 conflict exception', function () {
    $idempotencyKey = (string) Str::uuid();

    $payload1 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '50000.00',
        ],
    ];

    $payload2 = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '2.000'], // Different quantity!
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '50000.00',
        ],
    ];

    $this->saleService->checkoutCash($payload1, $this->cashier, $idempotencyKey);

    expect(function () use ($payload2, $idempotencyKey) {
        $this->saleService->checkoutCash($payload2, $this->cashier, $idempotencyKey);
    })->toThrow(IdempotencyConflictException::class);
});

test('http api checkout replay returns identical json response with 201 status', function () {
    $this->actingAs($this->cashier);
    $key = (string) Str::uuid();

    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '20000.00',
        ],
        'idempotency_key' => $key,
    ];

    $res1 = $this->postJson('/api/v1/sales/checkout', $payload, ['Idempotency-Key' => $key]);
    $res1->assertStatus(201);
    $trx1 = $res1->json('data.transaction_number');

    $res2 = $this->postJson('/api/v1/sales/checkout', $payload, ['Idempotency-Key' => $key]);
    $res2->assertStatus(201);
    $trx2 = $res2->json('data.transaction_number');

    expect($trx2)->toBe($trx1);
});

test('transaction status can be queried by idempotency key after browser timeout', function () {
    $this->actingAs($this->cashier);
    $key = (string) Str::uuid();

    $payload = [
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => '1.000'],
        ],
        'payment' => [
            'method' => 'cash',
            'cash_account_id' => $this->cashAccount->id,
            'cash_tendered' => '20000.00',
        ],
        'idempotency_key' => $key,
    ];

    // Simulate successful server commit
    $sale = $this->saleService->checkoutCash($payload, $this->cashier, $key);

    // Client checks status using idempotency key
    $queryRes = $this->getJson("/api/v1/sales/idempotency/{$key}");
    $queryRes->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'sale_status' => 'completed',
                'payment_status' => 'paid',
            ],
        ]);

    // Query with unknown key returns 404
    $unknownKey = (string) Str::uuid();
    $unknownRes = $this->getJson("/api/v1/sales/idempotency/{$unknownKey}");
    $unknownRes->assertStatus(404)
        ->assertJson([
            'error' => [
                'code' => 'TRANSACTION_NOT_FOUND',
            ],
        ]);
});
