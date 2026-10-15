<?php

use App\Support\Format;
use Illuminate\Support\Carbon;

test('money rounds half up to two places without floats', function () {
    expect(Format::money('24999.375'))->toBe('24,999.38')
        ->and(Format::money('0.005'))->toBe('0.01')
        ->and(Format::money('1234567.1'))->toBe('1,234,567.10')
        ->and(Format::money('999'))->toBe('999.00')
        ->and(Format::money('-1500.5'))->toBe('-1,500.50')
        ->and(Format::money(null))->toBe('0.00');
});

test('money appends the currency symbol when given', function () {
    expect(Format::money('24999.38', 'ل.س'))->toBe('24,999.38 ل.س')
        ->and(Format::money('10', ''))->toBe('10.00')
        ->and(Format::money('10', null))->toBe('10.00');
});

test('quantity uses three decimal places', function () {
    expect(Format::quantity('1.25'))->toBe('1.250')
        ->and(Format::quantity('12500'))->toBe('12,500.000')
        ->and(Format::quantity('0.0005'))->toBe('0.001');
});

test('dates use Levantine month names', function () {
    $date = Carbon::create(2026, 10, 5);

    expect(Format::date($date))->toBe('5 تشرين الأول 2026')
        ->and(Format::date($date, withWeekday: true))->toBe('الاثنين 5 تشرين الأول 2026')
        ->and(Format::date(Carbon::create(2027, 1, 31)))->toBe('31 كانون الثاني 2027');
});

test('plain drops padding zeros of a stored decimal', function () {
    expect(Format::plain('20.000'))->toBe('20')
        ->and(Format::plain('50.500'))->toBe('50.5')
        ->and(Format::plain('15.00'))->toBe('15')
        ->and(Format::plain('100'))->toBe('100')
        ->and(Format::plain('0.125'))->toBe('0.125')
        ->and(Format::plain(null))->toBe('');
});
