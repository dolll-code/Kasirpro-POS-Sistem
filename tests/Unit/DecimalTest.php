<?php

use App\Support\Decimal;

test('decimal adds and subtracts with exact scale', function () {
    $a = new Decimal('100.50', 2);
    $b = new Decimal('25.25', 2);

    expect($a->add($b)->toString())->toBe('125.75');
    expect($a->sub($b)->toString())->toBe('75.25');
});

test('decimal multiplies and divides with scale control', function () {
    $qty = new Decimal('3.500', 3);
    $price = new Decimal('15000.00', 2);

    $total = $qty->mul($price, 2);
    expect($total->toString())->toBe('52500.00');

    $divided = (new Decimal('100.00', 2))->div(3, 2);
    expect($divided->toString())->toBe('33.33');
});

test('decimal throws exception on division by zero', function () {
    (new Decimal('100.00', 2))->div(0);
})->throws(InvalidArgumentException::class, 'Division by zero');

test('decimal compares values correctly', function () {
    $a = new Decimal('50.00', 2);
    $b = new Decimal('75.00', 2);
    $c = new Decimal('50.00', 2);

    expect($a->isLessThan($b))->toBeTrue();
    expect($b->isGreaterThan($a))->toBeTrue();
    expect($a->equals($c))->toBeTrue();
    expect($a->compareTo($b))->toBe(-1);
    expect($b->compareTo($a))->toBe(1);
    expect($a->compareTo($c))->toBe(0);
});
