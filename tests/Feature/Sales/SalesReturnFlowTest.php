<?php

use App\Exceptions\InsufficientCashBalanceException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\StoreProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use App\Services\SalesReturnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->cashier = User::factory()->create(['role_id' => Role::where('code', 'cashier')->value('id'), 'is_active' => true]);
    $this->supervisor = User::factory()->create(['role_id' => Role::where('code', 'supervisor')->value('id'), 'is_active' => true]);
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    StoreProfile::current()->update(['name' => 'Toko Return Test', 'receipt_paper_width' => '58']);

    $this->cashAccount = CashAccount::create([
        'code' => 'RET-'.strtoupper(Str::random(6)),
        'name' => 'Kas Refund Test',
        'account_type' => 'cash',
        'opening_balance' => '100000.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);
    app(CashMovementService::class)->recordOpeningBalance($this->cashAccount, $this->admin->id);

    $category = Category::firstOrCreate(['slug' => 'return-test'], ['name' => 'Return Test', 'is_active' => true]);
    $unit = Unit::firstOrCreate(['symbol' => 'pcs-ret'], ['name' => 'Pieces Return', 'decimal_precision' => 0, 'is_active' => true]);
    $this->product = Product::create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'sku' => 'RET-'.strtoupper(Str::random(8)),
        'name' => 'Produk Retur',
        'selling_price' => '5000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);
    $this->inventory = app(InventoryService::class);
    $this->inventory->addStock($this->product, '20.000', '3000.00', 'IN_PURCHASE', 'receipt', null, 'RET-STOCK-'.Str::random(6), $this->admin->id);
    $sale = app(SaleService::class)->checkoutCash([
        'items' => [['product_id' => $this->product->id, 'quantity' => '5.000']],
        'payment' => ['method' => 'cash', 'cash_account_id' => $this->cashAccount->id, 'cash_tendered' => '25000.00'],
    ], $this->cashier, (string) Str::uuid());
    $this->sale = $sale->load('items');
    $this->returnService = app(SalesReturnService::class);
});

function requestReturnFor($test, string $quantity = '2.000', string $condition = 'resellable'): SalesReturn
{
    return $test->returnService->request($test->sale, [
        'sale_item_id' => $test->sale->items->first()->id,
        'quantity' => $quantity,
        'condition' => $condition,
        'reason' => 'Produk dikembalikan pelanggan',
        'notes' => 'Bukti penerimaan diperiksa',
    ], $test->cashier);
}

test('partial return approval restocks historical cost and recognizes revenue before refund cash flow', function () {
    $this->product->update(['selling_price' => '9000.00']);
    $this->inventory->addStock($this->product, '10.000', '9000.00', 'IN_PURCHASE', 'receipt', null, 'RET-LATER-'.Str::random(6), $this->admin->id);
    $return = requestReturnFor($this, '2.000');
    expect($return->status)->toBe('pending_approval')
        ->and($return->return_total)->toBe('10000.00')
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe('25.000')
        ->and(CashMovement::where('source_type', 'return_payment')->count())->toBe(0);

    $approved = $this->returnService->decide($return, $this->supervisor, 'approved', 'Barang sesuai');
    expect($approved->status)->toBe('approved')
        ->and($approved->approved_at)->not->toBeNull()
        ->and($approved->refund_status)->toBe('due')
        ->and($approved->items->first()->selling_price_snapshot)->toBe('5000.00')
        ->and($approved->items->first()->cost_price_snapshot)->toBe('3000.00')
        ->and($approved->items->first()->cost_value)->toBe('6000.00')
        ->and($approved->payment)->toBeNull()
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe('27.000')
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('average_cost'))->toBe('5222.22')
        ->and(StockMovement::where('source_type', 'sales_return')->where('source_id', $return->id)->first()->unit_cost)->toBe('3000.00')
        ->and($approved->sale->sale_status)->toBe('partially_returned')
        ->and($approved->sale->payment_status)->toBe('refund_due');

    $paidAt = $approved->approved_at->copy()->addDay();
    $paid = $this->returnService->payRefund($return, $this->cashAccount, $this->admin, 'REFUND-001', $paidAt);
    expect($paid->status)->toBe('completed')
        ->and($paid->refund_status)->toBe('paid')
        ->and($paid->payment->amount)->toBe('10000.00')
        ->and($paid->payment->paid_at->toDateString())->toBe($paidAt->toDateString())
        ->and($paid->sale->payment_status)->toBe('paid')
        ->and(CashMovement::where('source_type', 'return_payment')->where('source_id', $return->id)->count())->toBe(1);

    expect($paid->approved_at->toDateString())->not->toBe($paid->payment->paid_at->toDateString());
    expect($this->returnService->recognizedReturnsBetween($approved->approved_at->copy()->subMinute(), $approved->approved_at->copy()->addMinute()))->toBe('10000.00')
        ->and($this->returnService->refundsPaidBetween($approved->approved_at->copy()->subMinute(), $approved->approved_at->copy()->addMinute()))->toBe('0.00')
        ->and($this->returnService->refundsPaidBetween($paidAt->copy()->subMinute(), $paidAt->copy()->addMinute()))->toBe('10000.00');
});

test('full return updates sale status independently from refund payment status', function () {
    $return = requestReturnFor($this, '5.000');
    $approved = $this->returnService->decide($return, $this->supervisor, 'approved');
    expect($approved->sale->sale_status)->toBe('fully_returned')
        ->and($approved->sale->payment_status)->toBe('refund_due')
        ->and($approved->return_total)->toBe('25000.00');

    $paid = $this->returnService->payRefund($return, $this->cashAccount, $this->admin);
    expect($paid->sale->sale_status)->toBe('fully_returned')
        ->and($paid->sale->payment_status)->toBe('refunded');
});

test('return requests reserve returnable quantity and reject aggregate over-return', function () {
    requestReturnFor($this, '3.000');
    expect(fn () => requestReturnFor($this, '3.000'))
        ->toThrow(InvalidArgumentException::class, 'RETURN_QUANTITY_EXCEEDED');
});

test('return cannot be requested for an item belonging to another sale', function () {
    $otherProduct = Product::create([
        'category_id' => $this->product->category_id,
        'unit_id' => $this->product->unit_id,
        'sku' => 'OTHER-'.strtoupper(Str::random(8)),
        'name' => 'Produk lain',
        'selling_price' => '1000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);
    $this->inventory->addStock($otherProduct, '2.000', '500.00', 'IN_PURCHASE', 'receipt', null, 'OTHER-'.Str::random(6), $this->admin->id);
    $otherSale = app(SaleService::class)->checkoutCash([
        'items' => [['product_id' => $otherProduct->id, 'quantity' => '1.000']],
        'payment' => ['method' => 'cash', 'cash_account_id' => $this->cashAccount->id, 'cash_tendered' => '1000.00'],
    ], $this->cashier, (string) Str::uuid());

    expect(fn () => $this->returnService->request($this->sale, [
        'sale_item_id' => $otherSale->items->first()->id,
        'quantity' => '1.000',
        'condition' => 'resellable',
        'reason' => 'Salah barang',
    ], $this->cashier))->toThrow(ModelNotFoundException::class);
});

test('return API enforces permissions and returns validation conflict for over-return', function () {
    $this->actingAs($this->cashier);
    $this->postJson(route('api.sales-returns.store', $this->sale), [
        'sale_item_id' => $this->sale->items->first()->id,
        'quantity' => '6.000',
        'condition' => 'resellable',
        'reason' => 'Quantity berlebih',
    ])->assertStatus(409)->assertJsonPath('error.code', 'RETURN_QUANTITY_EXCEEDED');

    $this->actingAs(User::factory()->create(['role_id' => Role::where('code', 'cashier')->value('id'), 'is_active' => true]))
        ->postJson(route('api.sales-returns.decide', requestReturnFor($this)), ['decision' => 'approved'])
        ->assertForbidden();
});

test('requester cannot approve own return except admin override with reason', function () {
    $return = requestReturnFor($this);
    expect(fn () => $this->returnService->decide($return, $this->cashier, 'approved'))
        ->toThrow(AuthorizationException::class);

    $override = $this->returnService->decide($return, $this->admin, 'approved', 'Diperiksa', 'Override karena tidak ada approver lain');
    expect($override->admin_override_reason)->toBe('Override karena tidak ada approver lain');
});

test('rejection releases return quantity and leaves inventory and revenue unchanged', function () {
    $return = requestReturnFor($this);
    $this->returnService->decide($return, $this->supervisor, 'rejected', 'Tidak memenuhi syarat retur');
    expect($return->fresh()->status)->toBe('rejected')
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe('15.000')
        ->and($this->sale->fresh()->sale_status)->toBe('completed');

    expect(requestReturnFor($this, '5.000')->status)->toBe('pending_approval');
});

test('damaged return records historical loss but does not increase inventory', function () {
    $return = requestReturnFor($this, '1.000', 'damaged');
    $approved = $this->returnService->decide($return, $this->supervisor, 'approved');
    expect($approved->items->first()->damaged_loss)->toBe('3000.00')
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe('15.000')
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('average_cost'))->toBe('3000.00')
        ->and(StockMovement::where('source_type', 'sales_return')->count())->toBe(0);
});

test('duplicate refund is rejected and insufficient cash rolls back payment and state', function () {
    $return = requestReturnFor($this, '2.000');
    $this->returnService->decide($return, $this->supervisor, 'approved');
    $emptyAccount = CashAccount::create([
        'code' => 'EMPTY-'.strtoupper(Str::random(5)),
        'name' => 'Saldo kosong',
        'account_type' => 'cash',
        'opening_balance' => '0.00',
        'opening_balance_at' => now(),
        'is_active' => true,
    ]);
    app(CashMovementService::class)->recordOpeningBalance($emptyAccount, $this->admin->id);

    expect(fn () => $this->returnService->payRefund($return, $emptyAccount, $this->admin))
        ->toThrow(InsufficientCashBalanceException::class);
    expect($return->fresh()->status)->toBe('approved')
        ->and($return->fresh()->payment)->toBeNull();

    $this->returnService->payRefund($return, $this->cashAccount, $this->admin);
    expect(fn () => $this->returnService->payRefund($return, $this->cashAccount, $this->admin))
        ->toThrow(InvalidArgumentException::class, 'satu kali');
    expect(CashMovement::where('source_type', 'return_payment')->where('source_id', $return->id)->count())->toBe(1);
});
