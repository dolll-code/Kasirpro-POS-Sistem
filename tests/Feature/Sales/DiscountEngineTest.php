<?php

use App\Exceptions\DiscountApprovalRequiredException;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\SaleService;
use App\Support\Decimal;

beforeEach(function () {
    $this->cashierRole = Role::where('code', 'cashier')->first();
    $this->supervisorRole = Role::where('code', 'supervisor')->first();

    $this->cashier = User::factory()->create([
        'role_id' => $this->cashierRole->id,
        'is_active' => true,
    ]);

    $this->supervisor = User::factory()->create([
        'role_id' => $this->supervisorRole->id,
        'password' => bcrypt('supervisor123'),
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => 'kategori-diskon-test'],
        ['name' => 'Kategori Diskon Test', 'is_active' => true]
    );

    $this->unit = Unit::firstOrCreate(
        ['symbol' => 'pcs-disc'],
        ['name' => 'Pieces Disc', 'decimal_precision' => 0, 'is_active' => true]
    );

    $this->p1 = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'PROD-DISC-01',
        'name' => 'Produk Diskon A',
        'selling_price' => '10000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);

    $this->p2 = Product::create([
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'sku' => 'PROD-DISC-02',
        'name' => 'Produk Diskon B',
        'selling_price' => '20000.00',
        'minimum_stock' => '0.000',
        'is_active' => true,
    ]);

    $this->discountService = app(DiscountService::class);
    $this->saleService = app(SaleService::class);
});

test('calculate line discount supports amount and percentage capped at gross', function () {
    $gross = Decimal::of('50000.00', 2);

    // Percentage: 10% of 50000 = 5000
    $disc1 = $this->discountService->calculateLineDiscount('percent', '10.00', $gross);
    expect($disc1->toString())->toBe('5000.00');

    // Amount: 15000
    $disc2 = $this->discountService->calculateLineDiscount('amount', '15000.00', $gross);
    expect($disc2->toString())->toBe('15000.00');

    // Amount exceeding gross is capped at gross
    $disc3 = $this->discountService->calculateLineDiscount('amount', '60000.00', $gross);
    expect($disc3->toString())->toBe('50000.00');
});

test('proportional transaction discount allocation sums up exactly with deterministic residual rounding', function () {
    // 3 lines with different bases: 10.00, 20.00, 30.00. Total = 60.00
    // Transaction discount: 10.00
    // 10.00 / 60.00 * 10 = 1.6666...
    // 10.00 / 60.00 * 20 = 3.3333...
    // 10.00 / 60.00 * 30 = 5.0000...
    $lineBases = [
        0 => '10.00',
        1 => '20.00',
        2 => '30.00',
    ];

    $txDiscount = Decimal::of('10.00', 2);
    $allocations = $this->discountService->allocateTransactionDiscount($txDiscount, $lineBases);

    $totalAllocated = Decimal::zero(2);
    foreach ($allocations as $val) {
        $totalAllocated = $totalAllocated->add(Decimal::of($val, 2));
    }

    // Must be exactly 10.00
    expect($totalAllocated->toString())->toBe('10.00');

    // Largest line basis (index 2: 30.00) receives the deterministic rounding residue
    expect($allocations[0])->toBe('1.66')
        ->and($allocations[1])->toBe('3.33')
        ->and($allocations[2])->toBe('5.01');
});

test('cashier discount within limit proceeds without approval', function () {
    // 5% discount (limit is 10%)
    $calculation = $this->saleService->calculateCart(
        itemsInput: [
            ['product_id' => $this->p1->id, 'quantity' => '1.000', 'line_discount_type' => 'percent', 'line_discount_value' => '5.00'],
        ],
        txDiscountInput: ['type' => 'amount', 'value' => '0.00'],
        cashier: $this->cashier
    );

    expect($calculation['gross_total']->toString())->toBe('10000.00')
        ->and($calculation['item_discount_total']->toString())->toBe('500.00')
        ->and($calculation['grand_total']->toString())->toBe('9500.00')
        ->and($calculation['approver'])->toBeNull();
});

test('cashier discount exceeding limit is rejected without supervisor approval', function () {
    // 25% discount (limit is 10%)
    expect(function () {
        $this->saleService->calculateCart(
            itemsInput: [
                ['product_id' => $this->p1->id, 'quantity' => '1.000', 'line_discount_type' => 'percent', 'line_discount_value' => '25.00'],
            ],
            txDiscountInput: ['type' => 'amount', 'value' => '0.00'],
            cashier: $this->cashier
        );
    })->toThrow(DiscountApprovalRequiredException::class);
});

test('cashier cannot self-approve high discount due to maker-checker rule', function () {
    expect(function () {
        $this->saleService->calculateCart(
            itemsInput: [
                ['product_id' => $this->p1->id, 'quantity' => '1.000', 'line_discount_type' => 'percent', 'line_discount_value' => '25.00'],
            ],
            txDiscountInput: ['type' => 'amount', 'value' => '0.00'],
            cashier: $this->cashier,
            approvalData: [
                'approver_id' => $this->cashier->id, // Self approval!
                'approver_password' => 'password',
                'reason' => 'Mencoba approve sendiri',
            ]
        );
    })->toThrow(DiscountApprovalRequiredException::class, 'Maker-Checker');
});

test('high discount succeeds when authorized by supervisor with valid credentials', function () {
    $calculation = $this->saleService->calculateCart(
        itemsInput: [
            ['product_id' => $this->p1->id, 'quantity' => '1.000', 'line_discount_type' => 'percent', 'line_discount_value' => '25.00'],
        ],
        txDiscountInput: ['type' => 'amount', 'value' => '0.00'],
        cashier: $this->cashier,
        approvalData: [
            'approver_id' => $this->supervisor->id,
            'approver_password' => 'supervisor123',
            'reason' => 'Persetujuan diskon khusus supervisor',
        ]
    );

    expect($calculation['approver']->id)->toBe($this->supervisor->id)
        ->and($calculation['item_discount_total']->toString())->toBe('2500.00')
        ->and($calculation['grand_total']->toString())->toBe('7500.00');
});
