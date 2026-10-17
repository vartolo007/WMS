<?php

use App\Support\Money;

test('a line is quantity times price rounded half up to two decimals', function () {
    expect(Money::line('1.250', '19999.50'))->toBe('24999.38')
        ->and(Money::line('3.000', '10.00'))->toBe('30.00')
        ->and(Money::line('0.005', '1.00'))->toBe('0.01');
});

test('sums keep their scale and never use floats', function () {
    expect(Money::sum(['0.10', '0.20']))->toBe('0.30')
        ->and(Money::sum([]))->toBe('0.00')
        ->and(Money::sumQuantity(['1.250', '0.750']))->toBe('2.000');
});

test('a share of the net is rounded once at the end', function () {
    // SRS 6.2: 6,000 and 4,000 with a net of 9,000 → 5,400 and 3,600
    expect(Money::ratio('6000.00', '9000.00', '10000.00'))->toBe('5400.00')
        ->and(Money::ratio('4000.00', '9000.00', '10000.00'))->toBe('3600.00')
        // SRS 3.4.6 / 6.2 returned items: 2,000 × (9,000 ÷ 10,000) = 1,800
        ->and(Money::ratio('2000.00', '9000.00', '10000.00'))->toBe('1800.00');
});

test('with a zero subtotal the value stays as it is', function () {
    expect(Money::ratio('0.00', '0.00', '0.00'))->toBe('0.00');
});

test('a percentage scales a price: 1000 at 200 percent is 2000', function () {
    expect(Money::percent('1000.00', '200.00'))->toBe('2000.00')
        ->and(Money::percent('10.00', '150.00'))->toBe('15.00');
});

test('comparison is numeric, not textual', function () {
    expect(Money::compare('9.000', '10.000'))->toBe(-1)
        ->and(Money::compare('10.0', '10.000'))->toBe(0)
        ->and(Money::compare('100', '99.999'))->toBe(1);
});

test('subtraction and normalising keep the right scale', function () {
    expect(Money::subtract('9000', '1000'))->toBe('8000.00')
        ->and(Money::subtractQuantity('5', '1.5'))->toBe('3.500')
        ->and(Money::quantity('2'))->toBe('2.000')
        ->and(Money::money('2.5'))->toBe('2.50');
});
