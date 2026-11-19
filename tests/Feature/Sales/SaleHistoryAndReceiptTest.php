<?php

use App\Models\CashAccount;
use App\Models\CashMovement;
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
    $this->adminRole = Role::where('code', 'admin')->first();

    $this->cashier1 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->cashier2 = User::factory()->create([
        'role_id' => $this->cashierRole->id,
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
        'receipt_footer' => 'Terima kasih!',
        'receipt_paper_width' => '58',
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
        $cashService->recordOpeningBalance($this->cashAccount, $this->cashier1->id);
    } catch (\Throwable $e) {}

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-history'],
        ['name' => 'Kategori History', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-hist'],
        ['name' => 'Pieces Hist', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::firstOrCreate(
        ['sku' => 'SKU-HIST-01'],
        [
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Produk Riwayat Uji',
            'selling_price' => '25000.00',
            'minimum_stock' => '0.000',
            'is_active' => true,
        ]
    );

    $inventoryService = app(InventoryService::class);
    $inventoryService->addStock(
        product: $this->product,
        quantity: '50.000',
        unitCost: '15000.00',
        movementType: 'IN_INITIAL',
        sourceType: 'opening_balance',
        userId: $this->cashier1->id
    );

    $this->saleService = app(SaleService::class);

    // Create a sale made by Cashier 1
    $this->sale1 = $this->saleService->checkoutCash(
        payload: [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            'payment' => [
                'method' => 'cash',
                'cash_account_id' => $this->cashAccount->id,
                'cash_tendered' => '50000.00',
            ],
        ],
        cashier: $this->cashier1,
        idempotencyKey: (string) Str::uuid()
    );
});

test('cashier can view own sale but cannot view another cashiers sale (IDOR protection)', function () {
    // Cashier 1 views own sale
    $this->actingAs($this->cashier1);
    $resOwn = $this->get(route('sales.show', $this->sale1));
    $resOwn->assertStatus(200);

    // Cashier 2 tries to view Cashier 1's sale
    $this->actingAs($this->cashier2);
    $resOther = $this->get(route('sales.show', $this->sale1));
    $resOther->assertStatus(403);
});

test('admin with sales.view_all can view any cashiers sale', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('sales.show', $this->sale1));
    $response->assertStatus(200);
});

test('receipt endpoint renders thermal layout and reprint banner without creating new mutations', function () {
    $this->actingAs($this->cashier1);

    $salesCountBefore = Sale::count();
    $stockMovementsBefore = StockMovement::count();
    $cashMovementsBefore = CashMovement::count();

    // Normal receipt render
    $resNormal = $this->get(route('sales.receipt', $this->sale1));
    $resNormal->assertStatus(200)
        ->assertSee('Toko KasirPro Sentosa')
        ->assertSee($this->sale1->transaction_number)
        ->assertDontSee('SALINAN / REPRINT');

    // Reprint render
    $resReprint = $this->get(route('sales.receipt', ['sale' => $this->sale1, 'reprint' => 1, 'width' => 80]));
    $resReprint->assertStatus(200)
        ->assertSee('*** SALINAN / REPRINT ***');

    // Invariant: No additional sales, stock movements, or cash movements were created
    expect(Sale::count())->toBe($salesCountBefore)
        ->and(StockMovement::count())->toBe($stockMovementsBefore)
        ->and(CashMovement::count())->toBe($cashMovementsBefore);
});
