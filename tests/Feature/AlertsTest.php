<?php

use App\Mail\LowStockDigest;
use App\Mail\OperationalAlert;
use App\Models\Company;
use App\Models\Party;
use App\Models\Product;
use App\Models\User;
use App\Services\MovementService;
use App\Support\AdminAlert;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * A company with one warehouse holding 5 litres of oil (minimum 20): the owner should be told.
 */
function companyWithLowStock(array $company = [], string $ownerEmail = 'owner@example.test'): Company
{
    $company = Company::factory()->create($company);
    $owner = User::factory()->for($company)->create(['email' => $ownerEmail]);
    $warehouse = User::factory()->warehouse()->for($company)->create(['name' => 'مستودع المدينة']);
    $supplier = Party::factory()->supplier()->for($company)->create();
    $oil = Product::factory()->for($company)->create(['name' => 'زيت زيتون', 'unit' => 'liter', 'min_stock' => '20']);

    Auth::setUser($owner);
    app(MovementService::class)->purchase($owner, [
        'warehouse_id' => $warehouse->id,
        'party_id' => $supplier->id,
        'date' => today()->toDateString(),
        'items' => [['product_id' => $oil->id, 'quantity' => '5', 'unit_price' => '1000']],
    ]);
    Auth::forgetUser();

    return $company;
}

beforeEach(function () {
    Mail::fake();
});

test('the owner gets one mail listing the products below their minimum', function () {
    companyWithLowStock();

    $this->artisan('wms:alerts:low-stock')->assertSuccessful()->expectsOutputToContain('sent: 1');

    Mail::assertSent(LowStockDigest::class, 1);
    Mail::assertSent(LowStockDigest::class, function (LowStockDigest $mail) {
        $mail->assertSeeInHtml('زيت زيتون');
        $mail->assertSeeInHtml('مستودع المدينة');

        return $mail->hasTo('owner@example.test') && $mail->rows->count() === 1;
    });
});

test('nothing is sent when no product is below its minimum', function () {
    $company = companyWithLowStock();
    Product::withoutGlobalScopes()->where('company_id', $company->id)->update(['min_stock' => '2']);

    $this->artisan('wms:alerts:low-stock')->assertSuccessful()->expectsOutputToContain('sent: 0');

    Mail::assertNothingSent();
});

test('a suspended company, a company with alerts off and an inactive owner get nothing', function () {
    companyWithLowStock(['low_stock_alerts' => false], 'off@example.test');
    companyWithLowStock([], 'inactive@example.test')->owner->update(['is_active' => false]);
    companyWithLowStock([], 'suspended@example.test')->update(['status' => 'suspended']);

    $this->artisan('wms:alerts:low-stock')->assertSuccessful();

    Mail::assertNothingSent();
});

test('every company is told only about its own products', function () {
    companyWithLowStock([], 'a@example.test');
    $other = companyWithLowStock([], 'b@example.test');
    Product::withoutGlobalScopes()->where('company_id', $other->id)->update(['name' => 'سكر شركة ب']);

    $this->artisan('wms:alerts:low-stock')->assertSuccessful();

    Mail::assertSent(LowStockDigest::class, 2);
    Mail::assertSent(LowStockDigest::class, fn (LowStockDigest $mail) => $mail->hasTo('a@example.test')
        && $mail->rows->every(fn ($row) => $row['product']->company_id === $mail->company->id)
        && ! str_contains($mail->render(), 'سكر شركة ب'));
    Mail::assertSent(LowStockDigest::class, fn (LowStockDigest $mail) => $mail->hasTo('b@example.test') && str_contains($mail->render(), 'سكر شركة ب'));
    expect(Auth::user())->toBeNull();
});

test('the command leaves nobody signed in', function () {
    companyWithLowStock();

    Artisan::call('wms:alerts:low-stock');

    expect(Auth::check())->toBeFalse();
});

test('an admin alert goes to the platform e-mail and never throws', function () {
    config(['wms.admin.email' => 'admin@example.test']);

    AdminAlert::send('فشل النسخ', 'التفاصيل');

    Mail::assertSent(OperationalAlert::class, fn (OperationalAlert $mail) => $mail->hasTo('admin@example.test') && str_contains($mail->render(), 'التفاصيل'));
});

test('an admin alert without a platform e-mail sends nothing, and a broken mailer does not throw', function () {
    config(['wms.admin.email' => null]);
    AdminAlert::send('x', 'y');
    Mail::assertNothingSent();

    config(['wms.admin.email' => 'admin@example.test']);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

    expect(fn () => AdminAlert::send('x', 'y'))->not->toThrow(RuntimeException::class);
});

test('the scheduler runs the low-stock alert every morning', function () {
    Artisan::call('schedule:list');

    $expressions = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [preg_match('/(wms:[a-z:\-]+)\s*$/', (string) $event->command, $m) ? $m[1] : $event->command => $event->expression]);

    expect($expressions->get('wms:alerts:low-stock'))->toBe('0 8 * * *');
});

test('the owner switches the low-stock mail off and on from the settings page', function () {
    $company = Company::factory()->create(['low_stock_alerts' => true]);
    $owner = User::factory()->for($company)->create();
    $data = ['name' => $company->name, 'primary_color' => '#1F4E79', 'currency_symbol' => 'ل.س', 'markup_percent' => '200'];

    $this->actingAs($owner)->get('/settings')->assertOk()->assertSee('low_stock_alerts', false);

    $this->put('/settings', $data)->assertSessionHasNoErrors();
    expect($company->fresh()->low_stock_alerts)->toBeFalse();

    $this->put('/settings', $data + ['low_stock_alerts' => '1'])->assertSessionHasNoErrors();
    expect($company->fresh()->low_stock_alerts)->toBeTrue();
});
