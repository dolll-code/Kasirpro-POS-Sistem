<?php

use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StoreProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashAdjustmentService;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\SalesReturnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    $this->supervisor = User::factory()->create(['role_id' => Role::where('code', 'supervisor')->value('id'), 'is_active' => true]);
    $this->cashier = User::factory()->create(['role_id' => Role::where('code', 'cashier')->value('id'), 'is_active' => true]);
    StoreProfile::current()->update(['name' => 'Toko P1', 'receipt_paper_width' => '58']);
    $this->cash = CashAccount::create(['code' => 'P1-'.Str::upper(Str::random(5)), 'name' => 'Kas P1', 'account_type' => 'cash', 'opening_balance' => '500000.00', 'opening_balance_at' => now('Asia/Jakarta')->subDays(2), 'is_active' => true]);
    app(CashMovementService::class)->recordOpeningBalance($this->cash, $this->admin->id);
    $category = Category::firstOrCreate(['slug' => 'p1-test'], ['name' => 'P1 Test', 'is_active' => true]);
    $unit = Unit::firstOrCreate(['symbol' => 'pcs-p1'], ['name' => 'Pieces P1', 'decimal_precision' => 0, 'is_active' => true]);
    $this->product = Product::create(['category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'P1-'.Str::upper(Str::random(6)), 'name' => 'Produk P1', 'selling_price' => '10000.00', 'minimum_stock' => '0.000', 'is_active' => true]);
    $this->inventory = app(InventoryService::class);
    $this->inventory->addStock($this->product, '10.000', '4000.00', 'IN_INITIAL', 'opening_balance', null, null, $this->admin->id);
    $this->saleService = app(SaleService::class);
    $this->reportService = app(ReportService::class);
    $this->adjustmentService = app(CashAdjustmentService::class);
});

test('product performance reconciles returns, discounts, cogs, and gross profit from snapshots', function () {
    $sale = $this->saleService->checkoutCash([
        'items' => [['product_id' => $this->product->id, 'quantity' => '4.000', 'line_discount_type' => 'amount', 'line_discount_value' => '2000.00']],
        'payment' => ['method' => 'cash', 'cash_account_id' => $this->cash->id, 'cash_tendered' => '50000.00'],
    ], $this->cashier, (string) Str::uuid());
    $return = app(SalesReturnService::class)->request($sale, ['sale_item_id' => $sale->items->first()->id, 'quantity' => '1.000', 'condition' => 'resellable', 'reason' => 'Retur'], $this->cashier);
    app(SalesReturnService::class)->decide($return, $this->supervisor, 'approved');

    $row = $this->reportService->productPerformance(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString())['data']->first();
    expect($row['gross_sales'])->toBe('40000.00')
        ->and($row['discounts'])->toBe('2000.00')
        ->and($row['returns'])->toBe('9500.00')
        ->and($row['net_sales'])->toBe('28500.00')
        ->and($row['cogs'])->toBe('12000.00')
        ->and($row['gross_profit'])->toBe('16500.00');
});

test('historical valuation uses ledger and cost history, not current average cost', function () {
    $oldDate = now('Asia/Jakarta')->subDays(3);
    $this->product->update(['name' => 'Nama Sekarang']);
    DB::table('stock_movements')->where('product_id', $this->product->id)->update(['occurred_at' => $oldDate]);
    $this->inventory->addStock($this->product, '10.000', '9000.00', 'IN_PURCHASE', 'receipt', null, 'P1-NEW-COST', $this->admin->id);
    $this->product->refresh();

    $valuation = $this->reportService->inventoryValuation($oldDate->toDateString());
    $row = $valuation['data']->first();
    expect($row['historical_quantity'])->toBe('10.000')
        ->and($row['historical_cost'])->toBe('4000.00')
        ->and($row['historical_value'])->toBe('40000.00')
        ->and($row['current_cost'])->toBe('6500.00')
        ->and($row['historical_cost'])->not->toBe($row['current_cost']);
});

test('period comparison uses equal duration and zero denominator is null', function () {
    $period = $this->reportService->period('2026-03-11', '2026-03-20');
    expect($period['days'])->toBe(10)
        ->and($period['comparison_from_date'])->toBe('2026-03-01')
        ->and($period['comparison_to_date'])->toBe('2026-03-10');

    $pl = $this->reportService->profitLoss('2030-01-01', '2030-01-10', '2030-02-01', '2030-02-10');
    expect($pl['comparison']['summary']['net_sales_change_pct'])->toBeNull();
});

test('cash adjustment requires maker-checker, posts cash movement once, and is not revenue', function () {
    $adjustment = $this->adjustmentService->request([
        'cash_account_id' => $this->cash->id, 'direction' => 'in', 'category' => 'setoran_modal', 'amount' => '25000.00', 'reason' => 'Setoran pemilik',
    ], $this->admin);
    expect($adjustment->status)->toBe('pending_approval')
        ->and(CashMovement::where('source_type', 'cash_adjustment')->count())->toBe(0);

    expect(fn () => $this->adjustmentService->decide($adjustment, $this->admin, 'approved'))
        ->toThrow(AuthorizationException::class);

    $approved = $this->adjustmentService->decide($adjustment, $this->supervisor, 'approved', 'Terverifikasi');
    expect($approved->status)->toBe('posted')
        ->and($approved->cash_movement_id)->not->toBeNull()
        ->and(CashMovement::where('source_type', 'cash_adjustment')->count())->toBe(1);

    $pl = $this->reportService->profitLoss(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString())['current']['summary'];
    expect($pl['gross_sales'])->toBe('0.00')->and($pl['operating_expenses'])->toBe('0.00');
});

test('cash adjustment rejection and invalid category do not mutate cash', function () {
    expect(fn () => $this->adjustmentService->request(['cash_account_id' => $this->cash->id, 'direction' => 'out', 'category' => 'omzet', 'amount' => '1000.00', 'reason' => 'x'], $this->admin))
        ->toThrow(InvalidArgumentException::class, 'Kategori');
    $adjustment = $this->adjustmentService->request(['cash_account_id' => $this->cash->id, 'direction' => 'out', 'category' => 'koreksi_kas', 'amount' => '1000.00', 'reason' => 'Koreksi'], $this->admin);
    $this->adjustmentService->decide($adjustment, $this->supervisor, 'rejected', 'Bukti belum lengkap');
    expect($adjustment->fresh()->status)->toBe('rejected')
        ->and(CashMovement::where('source_type', 'cash_adjustment')->count())->toBe(0)
        ->and(StoreProfile::current()->name)->toBe('Toko P1');
});

test('receipt renders 58 and 80 mm with store snapshot footer', function () {
    $sale = $this->saleService->checkoutCash([
        'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        'payment' => ['method' => 'cash', 'cash_account_id' => $this->cash->id, 'cash_tendered' => '10000.00'],
    ], $this->cashier, (string) Str::uuid());
    $this->actingAs($this->cashier);
    $this->get(route('sales.receipt', ['sale' => $sale, 'width' => '58']))->assertOk()->assertSee('58mm')->assertSee('Toko P1');
    $this->get(route('sales.receipt', ['sale' => $sale, 'width' => '80']))->assertOk()->assertSee('80mm');
});

test('dashboard chart and permission-aware summaries are available', function () {
    $this->actingAs($this->supervisor);
    $this->get(route('dashboard'))->assertOk()->assertSee('Tren Penjualan 7 Hari')->assertSee('Gross Profit Hari Ini');
    $this->actingAs($this->cashier);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('Aktivitas Kas Terbaru');
});

test('inventory valuation report is exportable and has historical columns', function () {
    $this->actingAs($this->admin);
    $this->getJson(route('api.reports.show', 'inventory-valuation'))->assertOk()->assertJsonStructure(['data' => ['summary' => ['current_value', 'historical_value'], 'data']]);
    $this->postJson(route('api.reports.export', 'inventory-valuation'), ['format' => 'xlsx'])->assertCreated();
});
