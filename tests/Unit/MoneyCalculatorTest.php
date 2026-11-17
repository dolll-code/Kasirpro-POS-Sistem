<?php

use App\Support\Decimal;
use App\Support\MoneyCalculator;

test('formatIdr formats numbers to standard IDR format', function () {
    expect(MoneyCalculator::formatIdr('1234567.50'))->toBe('Rp 1.234.567,50');
    expect(MoneyCalculator::formatIdr(new Decimal('50000.00')))->toBe('Rp 50.000,00');
    expect(MoneyCalculator::formatIdr('1000', false))->toBe('1.000,00');
});

test('formatQuantity formats quantity according to precision', function () {
    expect(MoneyCalculator::formatQuantity('12.500', 3))->toBe('12,500');
    expect(MoneyCalculator::formatQuantity('10', 0))->toBe('10');
});

test('allocateProportional distributes discount header exactly without residual loss', function () {
    // Total discount Rp 500,00 to be allocated between 3 items with values 1000, 2000, 3000
    $totalDiscount = '500.00';
    $basis = [
        'item_1' => '1000.00',
        'item_2' => '2000.00',
        'item_3' => '3000.00',
    ];

    $allocated = MoneyCalculator::allocateProportional($totalDiscount, $basis);

    // Sum of allocated amounts must equal exactly 500.00
    $sum = (new Decimal($allocated['item_1'], 2))
        ->add($allocated['item_2'])
        ->add($allocated['item_3']);

    expect($sum->toString())->toBe('500.00');
    expect(count($allocated))->toBe(3);
});
