<?php

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameLock;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\StockOpnameService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role_id' => Role::where('code', 'admin')->value('id'), 'is_active' => true]);
    $this->supervisor = User::factory()->create(['role_id' => Role::where('code', 'supervisor')->value('id'), 'is_active' => true]);
    $category = Category::firstOrCreate(['slug' => 'opname-test'], ['name' => 'Opname Test', 'is_active' => true]);
    $pieceUnit = Unit::firstOrCreate(['symbol' => 'pcs-opn'], ['name' => 'Pieces Opname', 'decimal_precision' => 0, 'is_active' => true]);
    $liquidUnit = Unit::firstOrCreate(['symbol' => 'ltr-opn'], ['name' => 'Liter Opname', 'decimal_precision' => 3, 'is_active' => true]);
    $this->product = Product::create(['category_id' => $category->id, 'unit_id' => $pieceUnit->id, 'sku' => 'OPN-'.Str::upper(Str::random(6)), 'name' => 'Produk Opname', 'selling_price' => '10000.00', 'minimum_stock' => '0.000', 'is_active' => true]);
    $this->liquid = Product::create(['category_id' => $category->id, 'unit_id' => $liquidUnit->id, 'sku' => 'OPNL-'.Str::upper(Str::random(6)), 'name' => 'Produk Opname Liter', 'selling_price' => '15000.00', 'minimum_stock' => '0.000', 'is_active' => true]);
    $this->inventory = app(InventoryService::class);
    $this->inventory->addStock($this->product, '10.000', '4000.00', 'IN_INITIAL', 'opening_balance', null, null, $this->admin->id);
    $this->inventory->addStock($this->liquid, '5.500', '7000.00', 'IN_INITIAL', 'opening_balance', null, null, $this->admin->id);
    $this->opnameService = app(StockOpnameService::class);
});

function startedOpname($test, array $productIds = []): StockOpname
{
    return $test->opnameService->start($productIds ?: [$test->product->id], 'Audit fisik bulanan', $test->admin);
}

test('start session snapshots expected quantity and cost, and locks products', function () {
    $opname = startedOpname($this);
    expect($opname->status)->toBe('counting')
        ->and($opname->items)->toHaveCount(1)
        ->and($opname->items->first()->expected_quantity)->toBe('10.000')
        ->and($opname->items->first()->average_cost_snapshot)->toBe('4000.00')
        ->and(StockOpnameLock::where('product_id', $this->product->id)->whereNotNull('active_product_id')->count())->toBe(1);

    expect(fn () => $this->inventory->addStock($this->product, '1.000', '4000.00', 'IN_PURCHASE', 'receipt', null, 'BLOCKED', $this->admin->id))
        ->toThrow(InvalidArgumentException::class, 'dikunci');
});

test('concurrent opname on same product is rejected by unique active lock', function () {
    startedOpname($this);
    expect(fn () => startedOpname($this))
        ->toThrow(InvalidArgumentException::class, 'dikunci');
});

test('submit requires every item counted and enforces count precision', function () {
    $opname = $this->opnameService->start([$this->product->id, $this->liquid->id], 'Audit fisik bulanan', $this->admin);
    $liquidItem = $opname->items->firstWhere('product_id', $this->liquid->id);
    $productItem = $opname->items->firstWhere('product_id', $this->product->id);
    expect(fn () => $this->opnameService->submit($opname, $this->admin))
        ->toThrow(InvalidArgumentException::class, 'Semua produk');

    $this->opnameService->count($opname, ['items' => [$liquidItem->id => ['counted_quantity' => '2.555'], $productItem->id => ['counted_quantity' => '10.000']]], $this->admin);
    expect(fn () => $this->opnameService->count($opname, ['items' => [$productItem->id => ['counted_quantity' => '2.5']]], $this->admin))
        ->toThrow(InvalidArgumentException::class, 'Presisi');
});

test('negative variance uses snapshot cost while positive variance uses active cost and posts once', function () {
    $opname = $this->opnameService->start([$this->product->id], 'Audit fisik bulanan', $this->admin);
    $item = $opname->items->first();
    $this->inventory->addStock($this->liquid, '1.000', '8000.00', 'IN_PURCHASE', 'receipt', null, 'UNRELATED', $this->admin->id);
    $this->opnameService->count($opname, ['items' => [$item->id => ['counted_quantity' => '7.000']]], $this->admin);
    $submitted = $this->opnameService->submit($opname, $this->admin);
    expect($submitted->status)->toBe('pending_approval')
        ->and($submitted->items->first()->variance_quantity)->toBe('-3.000')
        ->and($submitted->items->first()->variance_value)->toBe('-12000.00')
        ->and($submitted->items->first()->active_cost_snapshot)->toBe('4000.00');

    $approved = $this->opnameService->decide($submitted, $this->supervisor, 'approved');
    $posted = $this->opnameService->post($approved, $this->admin);
    expect($posted->status)->toBe('posted')
        ->and($posted->locks->every(fn ($lock) => $lock->active_product_id === null))->toBeTrue()
        ->and(InventoryBalance::where('product_id', $this->product->id)->value('quantity_on_hand'))->toBe('7.000')
        ->and(StockMovement::where('source_type', 'stock_opname')->where('source_id', $opname->id)->count())->toBe(1)
        ->and(StockMovement::where('source_type', 'stock_opname')->where('source_id', $opname->id)->first()->unit_cost)->toBe('4000.00');

    expect(fn () => $this->opnameService->post($posted, $this->admin))
        ->toThrow(InvalidArgumentException::class, 'approved');
});

test('positive variance without cost basis requires supervisor cost and reason', function () {
    $product = Product::create(['category_id' => $this->product->category_id, 'unit_id' => $this->product->unit_id, 'sku' => 'OPNNEW-'.Str::upper(Str::random(6)), 'name' => 'Produk Tanpa Saldo', 'selling_price' => '9000.00', 'minimum_stock' => '0.000', 'is_active' => true]);
    $opname = $this->opnameService->start([$product->id], 'Audit fisik bulanan', $this->admin);
    $item = $opname->items->first();
    $this->opnameService->count($opname, ['items' => [$item->id => ['counted_quantity' => '2.000']]], $this->admin);
    $submitted = $this->opnameService->submit($opname, $this->admin);
    $item = $submitted->items->first();
    expect($item->variance_quantity)->toBe('2.000')
        ->and($item->active_cost_snapshot)->toBe('0.00');

    expect(fn () => $this->opnameService->decide($submitted, $this->supervisor, 'approved'))
        ->toThrow(InvalidArgumentException::class, 'positive unit cost');

    $this->opnameService->setPositiveCost($submitted, $item->id, '6000.00', 'Harga perolehan dari nota supplier', $this->supervisor);
    $approved = $this->opnameService->decide($submitted->fresh(), $this->supervisor, 'approved');
    $posted = $this->opnameService->post($approved, $this->admin);
    expect($posted->status)->toBe('posted')
        ->and(InventoryBalance::where('product_id', $product->id)->value('quantity_on_hand'))->toBe('2.000')
        ->and(StockMovement::where('source_type', 'stock_opname')->where('source_id', $opname->id)->first()->unit_cost)->toBe('6000.00');
});

test('maker-checker rejection and cancellation release locks without movements', function () {
    $opname = startedOpname($this);
    $item = $opname->items->first();
    $this->opnameService->count($opname, ['items' => [$item->id => ['counted_quantity' => '10.000']]], $this->supervisor);
    $submitted = $this->opnameService->submit($opname, $this->supervisor);

    expect(fn () => $this->opnameService->decide($submitted, $this->supervisor, 'approved'))
        ->toThrow(AuthorizationException::class);

    $rejected = $this->opnameService->decide($submitted, $this->admin, 'rejected', 'Hitung ulang lokasi rak', 'Admin override untuk uji');
    expect($rejected->status)->toBe('rejected')
        ->and(StockOpnameLock::where('stock_opname_id', $opname->id)->whereNotNull('active_product_id')->count())->toBe(0)
        ->and(StockMovement::where('source_type', 'stock_opname')->count())->toBe(0);

    $reopened = $this->opnameService->count($rejected, ['reopen_reason' => 'Dibuka untuk hitung ulang', 'items' => [$item->id => ['counted_quantity' => '9.000']]], $this->admin);
    expect($reopened->status)->toBe('counting')
        ->and(StockOpnameLock::where('stock_opname_id', $opname->id)->whereNotNull('active_product_id')->count())->toBe(1);

    $cancelled = $this->opnameService->cancel($reopened, $this->admin, 'Batal karena toko tutup');
    expect($cancelled->status)->toBe('cancelled')
        ->and(StockOpnameLock::where('stock_opname_id', $opname->id)->whereNotNull('active_product_id')->count())->toBe(0)
        ->and(StockMovement::where('source_type', 'stock_opname')->count())->toBe(0);
});

test('opname HTTP lifecycle enforces permissions and approval', function () {
    $this->actingAs($this->admin);
    $created = $this->postJson(route('api.stock-opnames.store'), [
        'product_ids' => [$this->product->id],
        'notes' => 'API opname',
    ])->assertCreated();
    $id = $created->json('data.id');
    $itemId = $created->json('data.items.0.id');
    $this->putJson(route('api.stock-opnames.count', $id), ['items' => [$itemId => ['counted_quantity' => '8.000']]])->assertOk();
    $this->postJson(route('api.stock-opnames.submit', $id))->assertOk()->assertJsonPath('data.status', 'pending_approval');

    $this->actingAs($this->supervisor);
    $this->postJson(route('api.stock-opnames.post', $id))->assertForbidden();
    $this->postJson(route('api.stock-opnames.decide', $id), ['decision' => 'approved'])->assertOk();
    expect(StockOpname::find($id)->status)->toBe('approved');
    $this->actingAs($this->admin);
    $this->postJson(route('api.stock-opnames.post', $id))->assertOk()->assertJsonPath('data.status', 'posted');
});

test('inventory ledger reconciles after opname posting', function () {
    $opname = $this->opnameService->start([$this->product->id], 'Audit fisik bulanan', $this->admin);
    $item = $opname->items->first();
    $this->opnameService->count($opname, ['items' => [$item->id => ['counted_quantity' => '12.000']]], $this->admin);
    $submitted = $this->opnameService->submit($opname, $this->admin);
    $approved = $this->opnameService->decide($submitted, $this->supervisor, 'approved');
    $this->opnameService->post($approved, $this->admin);

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    $ledgerIn = StockMovement::where('product_id', $this->product->id)->sum('quantity_in');
    $ledgerOut = StockMovement::where('product_id', $this->product->id)->sum('quantity_out');
    expect(bcsub((string) $ledgerIn, (string) $ledgerOut, 3))->toBe($balance->quantity_on_hand);
    expect($this->inventory->reconcile($this->product)['is_consistent'])->toBeTrue();
});
