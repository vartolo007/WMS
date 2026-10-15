<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'شركة النور']);
    $this->owner = User::factory()->for($this->company)->create();
});

function settingsData(array $overrides = []): array
{
    return array_merge([
        'name' => 'شركة النور',
        'primary_color' => '#1F4E79',
        'currency_symbol' => 'ل.س',
        'markup_percent' => '200',
    ], $overrides);
}

// --- Access --------------------------------------------------------------------------------------

test('the owner opens the settings page with the saved values', function () {
    $this->company->update(['currency_symbol' => 'ل.س', 'markup_percent' => '250.50']);

    $this->actingAs($this->owner)->get('/settings')
        ->assertOk()
        ->assertSee('شركة النور')
        ->assertSee('250.5')
        ->assertSee('ل.س');
});

test('a warehouse user and the platform admin cannot reach the settings', function () {
    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($warehouse)->get('/settings')->assertForbidden();
    $this->actingAs($warehouse)->put('/settings', settingsData())->assertForbidden();
    $this->actingAs($admin)->get('/settings')->assertForbidden();
});

test('a guest is sent to login', function () {
    $this->get('/settings')->assertRedirect('/login');
});

test('the owner sidebar links to the settings, the warehouse sidebar does not', function () {
    $this->actingAs($this->owner)->get('/dashboard')->assertSee(route('settings.edit'));

    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($warehouse)->get('/dashboard')->assertDontSee(route('settings.edit'));
});

test('only the owner of that company passes the policy', function () {
    $otherOwner = User::factory()->create();

    expect($this->owner->can('updateSettings', $this->company))->toBeTrue()
        ->and($otherOwner->can('updateSettings', $this->company))->toBeFalse()
        ->and(User::factory()->warehouse()->for($this->company)->create()->can('updateSettings', $this->company))->toBeFalse();
});

// --- Saving --------------------------------------------------------------------------------------

test('the owner saves name, colour, currency and pricing ratio', function () {
    $this->actingAs($this->owner)->put('/settings', settingsData([
        'name' => 'النور للتجارة',
        'primary_color' => '#0F766E',
        'currency_symbol' => '$',
        'markup_percent' => '250.55',
    ]))->assertRedirect(route('settings.edit'));

    $company = $this->company->fresh();
    expect($company->name)->toBe('النور للتجارة')
        ->and($company->primary_color)->toBe('#0F766E')
        ->and($company->currency_symbol)->toBe('$')
        ->and($company->markup_percent)->toBe('250.55');
});

test('the name is required and the currency symbol is optional', function () {
    $this->actingAs($this->owner);

    $this->put('/settings', settingsData(['name' => '']))->assertSessionHasErrors('name');
    $this->put('/settings', settingsData(['currency_symbol' => '']))->assertSessionHasNoErrors();
    $this->put('/settings', settingsData(['currency_symbol' => str_repeat('x', 11)]))->assertSessionHasErrors('currency_symbol');

    expect($this->company->fresh()->currency_symbol)->toBeNull();
});

test('the colour must come from the approved palette', function () {
    $this->actingAs($this->owner)
        ->put('/settings', settingsData(['primary_color' => '#FF00FF']))
        ->assertSessionHasErrors('primary_color');

    expect($this->company->fresh()->primary_color)->toBe(config('wms.default_color'));
});

test('every palette colour is accepted', function (string $hex) {
    $this->actingAs($this->owner)
        ->put('/settings', settingsData(['primary_color' => $hex]))
        ->assertSessionHasNoErrors();
})->with(fn () => (require __DIR__.'/../../config/wms.php')['palette']);

test('the pricing ratio is a number between 100 and 1000 with two decimals at most', function (string $value, bool $valid) {
    $this->actingAs($this->owner)->put('/settings', settingsData(['markup_percent' => $value]));

    $valid
        ? expect($this->company->fresh()->markup_percent)->toBe(number_format((float) $value, 2, '.', ''))
        : expect($this->company->fresh()->markup_percent)->toBe('200.00');
})->with([
    'lowest' => ['100', true],
    'highest' => ['1000', true],
    'two decimals' => ['250.55', true],
    'below the minimum' => ['99.99', false],
    'above the maximum' => ['1000.01', false],
    'three decimals' => ['250.555', false],
    'not a number' => ['abc', false],
    'empty' => ['', false],
]);

// --- Isolation -----------------------------------------------------------------------------------

test('saving changes only the own company, whatever the request says', function () {
    $other = Company::factory()->create(['name' => 'شركة أخرى']);

    $this->actingAs($this->owner)->put('/settings', settingsData([
        'name' => 'اسم جديد',
        'company_id' => $other->id,
        'id' => $other->id,
    ]));

    expect($this->company->fresh()->name)->toBe('اسم جديد')
        ->and($other->fresh()->name)->toBe('شركة أخرى');
});

test('a saved colour reaches every user of the company and nobody else', function () {
    $this->actingAs($this->owner)->put('/settings', settingsData(['primary_color' => '#0F766E']));

    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $stranger = User::factory()->create();

    $this->actingAs($warehouse)->get('/dashboard')->assertSee('--color-primary: #0F766E', false);
    $this->actingAs($stranger)->get('/dashboard')->assertSee('--color-primary: '.config('wms.default_color'), false);
});

test('a saved company name shows in the top bar of its users', function () {
    $this->actingAs($this->owner)->put('/settings', settingsData(['name' => 'النور للتجارة']));

    $warehouse = User::factory()->warehouse()->for($this->company)->create();
    $this->actingAs($warehouse)->get('/dashboard')->assertSee('النور للتجارة');
});
