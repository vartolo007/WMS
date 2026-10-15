<?php

use App\Support\Units;

test('liter and kilo accept fractions, box and piece do not', function () {
    expect(Units::allowsFraction('liter'))->toBeTrue()
        ->and(Units::allowsFraction('kilo'))->toBeTrue()
        ->and(Units::allowsFraction('box'))->toBeFalse()
        ->and(Units::allowsFraction('piece'))->toBeFalse();
});

test('an unknown unit is treated as whole numbers only', function () {
    expect(Units::allowsFraction('barrel'))->toBeFalse()
        ->and(Units::allowsFraction(null))->toBeFalse();
});

test('options map each key to its Arabic label', function () {
    expect(Units::options())->toBe(['liter' => 'لتر', 'kilo' => 'كيلو', 'box' => 'علبة', 'piece' => 'قطعة'])
        ->and(Units::keys())->toBe(['liter', 'kilo', 'box', 'piece'])
        ->and(Units::label('kilo'))->toBe('كيلو');
});
