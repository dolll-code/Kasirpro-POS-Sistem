<?php

use App\Models\CashAccount;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StoreProfile;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\ExpenseService;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\SalesReturnService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    $this->supervisor = User::factory()->create(['role_id' => Role::where('code', 'supervisor')->value('id'), 'is_active' => true]);
    $this->cashier = User::factory()->create(['role_id' => Role::where('code', 'cashier')->value('id'), 'is_active' => true]);
    StoreProfile::current()->update(['name' => 'Toko Report Test', 'receipt_paper_width' => '58']);
    $this->cash = CashAccount::create(['code' => 'RPT-'.Str::upper(Str::random(5)), 'name' => 'Kas Report', 'account_type' => 'cash', 'opening_balance' => '100000.00', 'opening_balance_at' => now('Asia/Jakarta')->subDays(2), 'is_active' => true]);
    app(CashMovementService::class)->recordOpeningBalance($this->cash, $this->admin->id);
    $category = Category::firstOrCreate(['slug' => 'report-test'], ['name' => 'Report Test', 'is_active' => true]);
    $unit = Unit::firstOrCreate(['symbol' => 'pcs-rpt'], ['name' => 'Pieces Report', 'decimal_precision' => 0, 'is_active' => true]);
    $this->product = Product::create(['category_id' => $category->id, 'unit_id' => $unit->id, 'sku' => 'RPT-'.Str::upper(Str::random(6)), 'name' => 'Produk Report', 'selling_price' => '10000.00', 'minimum_stock' => '2.000', 'is_active' => true]);
    $inventory = app(InventoryService::class);
    $inventory->addStock($this->product, '20.000', '4000.00', 'IN_INITIAL', 'opening_balance', null, null, $this->admin->id);
    $this->saleService = app(SaleService::class);
    $this->returnService = app(SalesReturnService::class);
    $this->reportService = app(ReportService::class);
    $this->expenseCategory = ExpenseCategory::create(['name' => 'Biaya Report '.Str::random(5), 'is_active' => true]);
});

function cashSale($test, string $qty = '2.000', ?string $completedAt = null): Sale
{
    $sale = $test->saleService->checkoutCash([
        'items' => [['product_id' => $test->product->id, 'quantity' => $qty]],
        'payment' => ['method' => 'cash', 'cash_account_id' => $test->cash->id, 'cash_tendered' => '100000.00'],
    ], $test->cashier, (string) Str::uuid());
    if ($completedAt) {
        $sale->completed_at = $completedAt;
        $sale->save();
        DB::table('cash_movements')->where('source_type', 'sale')->where('source_id', $sale->id)->update(['occurred_at' => $completedAt]);
    }

    return $sale;
}

test('sales, return, expense, purchase, and P&L reports reconcile against detail snapshots', function () {
    $sale1 = cashSale($this, '2.000');
    $sale2 = cashSale($this, '1.000');
    $return = $this->returnService->request($sale1, ['sale_item_id' => $sale1->items->first()->id, 'quantity' => '1.000', 'condition' => 'resellable', 'reason' => 'Salah beli'], $this->cashier);
    $this->returnService->decide($return, $this->supervisor, 'approved');
    $this->returnService->payRefund($return, $this->cash, $this->admin);
    $expense = app(ExpenseService::class)->createDraft(['expense_category_id' => $this->expenseCategory->id, 'expense_date' => now('Asia/Jakarta')->toDateString(), 'description' => 'Listrik', 'amount' => '5000.00'], $this->admin);
    app(ExpenseService::class)->post($expense, $this->admin);
    $supplier = Supplier::create(['supplier_code' => 'SUP-'.Str::upper(Str::random(5)), 'name' => 'Supplier Report', 'is_active' => true]);
    Purchase::create(['purchase_number' => 'PO-RPT-'.Str::upper(Str::random(6)), 'supplier_id' => $supplier->id, 'order_date' => now('Asia/Jakarta')->toDateString(), 'subtotal' => '20000.00', 'payable_total' => '20000.00', 'paid_amount' => '5000.00', 'status' => 'ordered', 'payment_status' => 'partially_paid', 'created_by' => $this->admin->id]);

    $period = $this->reportService->period(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString());
    $sales = $this->reportService->salesReport($this->admin, $period['from_date'], $period['to_date']);
    expect($sales['summary']['gross'])->toBe('30000.00')->and($sales['summary']['transactions'])->toBe(2);

    $returns = $this->reportService->returnReport($period['from_date'], $period['to_date']);
    expect($returns['summary']['total'])->toBe('10000.00')
        ->and($returns['summary']['cogs_reversal'])->toBe('4000.00')
        ->and($returns['summary']['damaged_loss_informational'])->toBe('0.00');

    $expenses = $this->reportService->expenseReport($period['from_date'], $period['to_date']);
    expect($expenses['summary']['net'])->toBe('5000.00');

    $purchases = $this->reportService->purchaseReport($period['from_date'], $period['to_date']);
    expect($purchases['summary']['payable'])->toBe('20000.00')->and($purchases['summary']['outstanding'])->toBe('15000.00');

    $pl = $this->reportService->profitLoss($period['from_date'], $period['to_date'])['current']['summary'];
    expect($pl['gross_sales'])->toBe('30000.00')
        ->and($pl['sales_returns'])->toBe('10000.00')
        ->and($pl['net_sales'])->toBe('20000.00')
        ->and($pl['gross_cogs'])->toBe('12000.00')
        ->and($pl['cogs_reversal'])->toBe('4000.00')
        ->and($pl['net_cogs'])->toBe('8000.00')
        ->and($pl['gross_profit'])->toBe('12000.00')
        ->and($pl['operating_expenses'])->toBe('5000.00')
        ->and($pl['operating_result'])->toBe('7000.00');
    expect(bcsub($pl['net_sales'], $pl['net_cogs'], 2))->toBe($pl['gross_profit']);
});

test('cash flow uses occurred_at, carries prior balance, and reconciles closing', function () {
    $period = $this->reportService->period(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString());
    $before = $this->reportService->cashFlow($period['from_date'], $period['to_date']);
    cashSale($this, '2.000');
    $cash = $this->reportService->cashFlow($period['from_date'], $period['to_date']);
    expect((string) bcsub($cash['summary']['inflow'], $before['summary']['inflow'], 2))->toBe('20000.00')
        ->and(bcadd(bcadd($cash['summary']['opening'], $cash['summary']['inflow'], 2), (string) (-1 * (float) $cash['summary']['outflow']), 2))->toBe($cash['summary']['closing']);
});

test('date filters are inclusive in Asia/Jakarta and use recognition dates', function () {
    $sale = cashSale($this, '1.000', '2026-11-10 00:05:00');
    $included = $this->reportService->salesReport($this->admin, '2026-11-10', '2026-11-10');
    expect($included['summary']['transactions'])->toBe(1)->and($included['summary']['gross'])->toBe('10000.00');
    $excluded = $this->reportService->salesReport($this->admin, '2026-11-09', '2026-11-09');
    expect($excluded['summary']['transactions'])->toBe(0);
    expect($sale->fresh()->completed_at->timezone('Asia/Jakarta')->toDateString())->toBe('2026-11-10');
});

test('zero denominator margin and comparison use dash-safe null', function () {
    $pl = $this->reportService->profitLoss('2030-01-01', '2030-01-02', '2030-02-01', '2030-02-02');
    expect($pl['current']['summary']['operating_margin'])->toBeNull()
        ->and($pl['comparison']['summary']['net_sales_change_pct'])->toBeNull();
});

test('report routes enforce permissions and filter pagination', function () {
    cashSale($this, '1.000');
    $this->actingAs($this->cashier);
    $this->get(route('reports.show', 'sales'))->assertForbidden();
    $this->getJson(route('api.reports.show', 'sales'))->assertForbidden();

    $this->actingAs($this->supervisor);
    $this->get(route('dashboard'))->assertOk()->assertSee('Dashboard Operasional');
    $this->getJson(route('api.reports.show', ['type' => 'sales', 'per_page' => 1, 'from' => now('Asia/Jakarta')->toDateString(), 'to' => now('Asia/Jakarta')->toDateString()]))
        ->assertOk()->assertJsonPath('data.data.per_page', 1);
    $this->getJson(route('api.reports.show', 'profit-loss'))->assertOk()->assertJsonPath('data.label', 'Laba Rugi Sederhana / Ringkasan Hasil Operasional');
});

test('damaged return is not COGS reversal and stock opname shrinkage deducts result once', function () {
    $sale = cashSale($this, '2.000');
    $damaged = $this->returnService->request($sale, ['sale_item_id' => $sale->items->first()->id, 'quantity' => '1.000', 'condition' => 'damaged', 'reason' => 'Pecah'], $this->cashier);
    $this->returnService->decide($damaged, $this->supervisor, 'approved');
    $opname = StockOpname::create(['opname_number' => 'SO-RPT-'.Str::upper(Str::random(6)), 'status' => 'posted', 'created_by' => $this->admin->id, 'posted_at' => now('Asia/Jakarta')]);
    StockOpnameItem::create(['stock_opname_id' => $opname->id, 'product_id' => $this->product->id, 'expected_quantity' => '20.000', 'counted_quantity' => '19.000', 'variance_quantity' => '-1.000', 'average_cost_snapshot' => '4000.00', 'variance_value' => '-4000.00']);

    $pl = $this->reportService->profitLoss(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString())['current']['summary'];
    expect($pl['cogs_reversal'])->toBe('0.00')
        ->and($pl['shrinkage_loss'])->toBe('4000.00')
        ->and($pl['operating_result'])->toBe(bcsub(bcsub($pl['gross_profit'], $pl['operating_expenses'], 2), '4000.00', 2));
});

test('product performance uses historical sale item snapshots', function () {
    $sale = cashSale($this, '2.000');
    $this->product->update(['selling_price' => '99999.00']);
    $this->product->name = 'Nama Baru Setelah Sale';
    $this->product->save();
    $report = $this->reportService->productPerformance(now('Asia/Jakarta')->toDateString(), now('Asia/Jakarta')->toDateString());
    expect($report['data']->first()['product_name_snapshot'])->toBe('Produk Report')
        ->and($report['data']->first()['net_sales'])->toBe('20000.00')
        ->and($report['data']->first()['cogs'])->toBe('8000.00');
});
