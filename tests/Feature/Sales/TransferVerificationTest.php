<?php

use App\Exceptions\ReservationExpiredException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\StoreProfile;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashMovementService;
use App\Services\InventoryService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
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
        'address' => 'Jl. Boulevard No. 88, Jakarta Barat',
        'phone' => '081234567890',
        'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
        'receipt_paper_width' => '58',
    ]);

    $this->bankAccount = CashAccount::firstOrCreate(
        ['code' => 'BANK-BCA-VERIFY'],
        [
            'name' => 'Bank BCA Operasional',
            'account_type' => 'bank',
            'opening_balance' => '2000000.00',
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
        ['slug' => 'kategori-verify-test'],
        ['name' => 'Kategori Verify Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-vf'],
        ['name' => 'Pieces Verify', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->product = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'SKU-VF-01',
        'name' => 'Beras Pandan Wangi 5kg',
        'selling_price' => '75000.00',
        'minimum_stock' => '2.000',
        'is_active' => true,
    ]);

    $this->inventoryService = app(InventoryService::class);
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '30.000',
        unitCost: '60000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-VF-01',
        userId: $this->admin->id
    );

    $this->saleService = app(SaleService::class);

    // Create a standard pending transfer checkout
    $this->pendingSale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '2.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
                'reference_number' => 'REF-VERIFY-12345',
            ],
        ],
        cashier: $this->cashier,
        idempotencyKey: (string) Str::uuid()
    );
});

test('verification before TTL succeeds and atomically posts sale, payment, stock out, and cash in', function () {
    // Cost basis changes while transfer is pending; posting snapshots the locked cost at verification time.
    $this->inventoryService->addStock(
        product: $this->product,
        quantity: '10.000',
        unitCost: '70000.00',
        movementType: 'IN_PURCHASE',
        sourceType: 'receipt',
        sourceNumber: 'RCV-VF-02',
        userId: $this->admin->id
    );

    $sale = $this->saleService->verifyTransfer(
        sale: $this->pendingSale,
        verifier: $this->supervisor,
        decision: 'verified',
        note: 'Bukti transfer BCA telah dicek dan valid'
    );

    // 1. Sale status & payment status
    expect($sale->sale_status)->toBe('completed')
        ->and($sale->payment_status)->toBe('paid')
        ->and($sale->completed_at)->not->toBeNull();

    // 2. Reservation is consumed
    $reservation = StockReservation::where('sale_id', $sale->id)->first();
    expect($reservation->status)->toBe('consumed')
        ->and($reservation->released_at)->not->toBeNull();

    // 3. Stock deduction assertion
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('38.000') // 30 + 10 - 2 = 38
        ->and($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('38.000');

    // 4. Stock movement OUT_SALE created with verification-time weighted average HPP = Rp 62.500
    $stockMovement = StockMovement::where('source_type', 'sale')
        ->where('source_id', $sale->id)
        ->first();
    expect($stockMovement)->not->toBeNull()
        ->and($stockMovement->movement_type)->toBe('OUT_SALE')
        ->and($stockMovement->quantity_out)->toBe('2.000')
        ->and($stockMovement->unit_cost)->toBe('62500.00')
        ->and($stockMovement->total_cost)->toBe('125000.00');

    $saleItem = $sale->items->first();
    expect($saleItem->cost_price_snapshot)->toBe('62500.00')
        ->and($saleItem->cost_total_snapshot)->toBe('125000.00');

    // 5. Cash Inflow created in bank account = exactly grand_total (150.000)
    $payment = $sale->payment;
    expect($payment->verification_status)->toBe('verified')
        ->and($payment->verified_by)->toBe($this->supervisor->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->cash_movement_id)->not->toBeNull();

    $cashMovement = CashMovement::find($payment->cash_movement_id);
    expect($cashMovement)->not->toBeNull()
        ->and($cashMovement->cash_account_id)->toBe($this->bankAccount->id)
        ->and($cashMovement->direction)->toBe('in')
        ->and($cashMovement->amount)->toBe('150000.00') // 2 * 75.000
        ->and($cashMovement->movement_type)->toBe('IN_SALE');
});

test('verification after TTL expires rejects and cancels transaction without stock out or cash in', function () {
    // Force reservation to be expired in database
    $reservation = StockReservation::where('sale_id', $this->pendingSale->id)->first();
    $reservation->update(['expires_at' => Carbon::now('Asia/Jakarta')->subMinutes(2)]);

    $stockMovementsBefore = StockMovement::count();
    $cashMovementsBefore = CashMovement::count();

    expect(function () {
        $this->saleService->verifyTransfer(
            sale: $this->pendingSale,
            verifier: $this->supervisor,
            decision: 'verified'
        );
    })->toThrow(ReservationExpiredException::class);

    // Sale must be cancelled, payment expired
    $reloadedSale = $this->pendingSale->fresh();
    expect($reloadedSale->sale_status)->toBe('cancelled')
        ->and($reloadedSale->payment_status)->toBe('expired');

    // Reservation must be released and reserved stock restored
    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('30.000')
        ->and($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('30.000');

    // Invariant: No stock movement out, no cash movement in!
    expect(StockMovement::count())->toBe($stockMovementsBefore)
        ->and(CashMovement::count())->toBe($cashMovementsBefore);
});

test('rejection releases reservation and marks sale cancelled without stock out or cash in', function () {
    $stockMovementsBefore = StockMovement::count();
    $cashMovementsBefore = CashMovement::count();

    $sale = $this->saleService->verifyTransfer(
        sale: $this->pendingSale,
        verifier: $this->supervisor,
        decision: 'rejected',
        note: 'Bukti transfer palsu / tidak ditemukan di mutasi BCA'
    );

    expect($sale->sale_status)->toBe('cancelled')
        ->and($sale->payment_status)->toBe('rejected')
        ->and($sale->cancellation_reason)->toBe('Bukti transfer palsu / tidak ditemukan di mutasi BCA')
        ->and($sale->payment->verification_status)->toBe('rejected');

    // Reservation must be released
    $reservation = StockReservation::where('sale_id', $sale->id)->first();
    expect($reservation->status)->toBe('released');

    $balance = InventoryBalance::where('product_id', $this->product->id)->first();
    expect($balance->quantity_on_hand)->toBe('30.000')
        ->and($balance->quantity_reserved)->toBe('0.000')
        ->and($balance->available_quantity)->toBe('30.000');

    // No stock movements or cash movements
    expect(StockMovement::count())->toBe($stockMovementsBefore)
        ->and(CashMovement::count())->toBe($cashMovementsBefore);
});

test('requester cashier cannot self-approve transfer due to maker-checker rule', function () {
    expect(function () {
        $this->saleService->verifyTransfer(
            sale: $this->pendingSale,
            verifier: $this->cashier, // Cashier who initiated the sale!
            decision: 'verified'
        );
    })->toThrow(AuthorizationException::class);
});

test('admin can override maker-checker when providing mandatory reason', function () {
    // Admin creates their own transfer checkout
    $adminSale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '1.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->admin,
        idempotencyKey: (string) Str::uuid()
    );

    // Admin verifies own transfer WITH override reason
    $verified = $this->saleService->verifyTransfer(
        sale: $adminSale,
        verifier: $this->admin,
        decision: 'verified',
        note: 'Disetujui',
        overrideReason: 'Admin override: Kasir tunggal piket malam, mutasi telah dicek manual'
    );

    expect($verified->sale_status)->toBe('completed')
        ->and($verified->payment_status)->toBe('paid');
});

test('admin self-verification fails if override reason is omitted', function () {
    $adminSale = $this->saleService->checkoutTransfer(
        payload: [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => '1.000'],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'cash_account_id' => $this->bankAccount->id,
            ],
        ],
        cashier: $this->admin,
        idempotencyKey: (string) Str::uuid()
    );

    expect(function () use ($adminSale) {
        $this->saleService->verifyTransfer(
            sale: $adminSale,
            verifier: $this->admin,
            decision: 'verified',
            note: 'Tanpa override reason',
            overrideReason: '' // Empty reason!
        );
    })->toThrow(AuthorizationException::class);
});

test('duplicate verification attempt is rejected', function () {
    $this->saleService->verifyTransfer(
        sale: $this->pendingSale,
        verifier: $this->supervisor,
        decision: 'verified'
    );

    $cashMovementsCount = CashMovement::count();

    expect(function () {
        $this->saleService->verifyTransfer(
            sale: $this->pendingSale,
            verifier: $this->supervisor,
            decision: 'verified'
        );
    })->toThrow(InvalidArgumentException::class);

    // Cash movement count must remain unchanged
    expect(CashMovement::count())->toBe($cashMovementsCount);
});

test('non-authorized user without payments.verify_transfer cannot verify transfer', function () {
    $unauthorizedUser = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    expect(function () use ($unauthorizedUser) {
        $this->saleService->verifyTransfer(
            sale: $this->pendingSale,
            verifier: $unauthorizedUser,
            decision: 'verified'
        );
    })->toThrow(AuthorizationException::class);
});
