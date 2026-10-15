<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

test('the money component prints the amount with the company currency symbol', function () {
    $user = User::factory()->for(Company::factory()->create(['currency_symbol' => 'ل.س']))->create();
    $this->actingAs($user);

    $html = Blade::render('<x-ui.money amount="24999.375" />');

    expect(strip_tags($html))->toContain('24,999.38')->toContain('ل.س')
        ->and($html)->toContain('dir="ltr"');
});

test('without a currency symbol only the number is printed', function () {
    $user = User::factory()->for(Company::factory()->create(['currency_symbol' => null]))->create();
    $this->actingAs($user);

    expect(trim(strip_tags(Blade::render('<x-ui.money amount="1000" />'))))->toBe('1,000.00');
});

test('a symbol can be passed explicitly', function () {
    expect(trim(strip_tags(Blade::render('<x-ui.money amount="5" symbol="$" />'))))->toBe('5.00 $');
});

test('amounts are never rounded through a float', function () {
    expect(strip_tags(Blade::render('<x-ui.money amount="0.005" />')))->toContain('0.01')
        ->and(strip_tags(Blade::render('<x-ui.money amount="1234567890.125" />')))->toContain('1,234,567,890.13');
});
