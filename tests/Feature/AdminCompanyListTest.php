<?php

use App\Enums\CompanyFilter;
use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

/**
 * A company with an owner, so the list and the search have something to show.
 */
function companyWithOwner(Company $company, array $owner = []): Company
{
    User::factory()->for($company)->create($owner);

    return $company;
}

// --- Cards and filters ---------------------------------------------------------------------------

test('the cards count each group of companies', function () {
    Company::factory()->count(2)->create(['trial_ends_at' => today()->addDays(5)]);
    Company::factory()->create(['trial_ends_at' => today()]);
    Company::factory()->trialExpired()->count(3)->create();
    Company::factory()->active()->count(4)->create();
    Company::factory()->suspended()->count(5)->create();

    $counts = app(App\Services\CompanySubscriptionService::class)->overview();

    expect($counts)->toBe([
        'all' => 15,
        'trial' => 3,       // a trial that ends today still runs
        'expired' => 3,
        'active' => 4,
        'suspended' => 5,
    ]);
});

test('the counts are zero, not empty, when there are no companies', function () {
    expect(app(App\Services\CompanySubscriptionService::class)->overview())
        ->toBe(['all' => 0, 'trial' => 0, 'expired' => 0, 'active' => 0, 'suspended' => 0]);
});

test('each card filters the list', function (string $filter, array $shown, array $hidden) {
    companyWithOwner(Company::factory()->create(['name' => 'شركة تجريبية', 'trial_ends_at' => today()->addDays(5)]));
    companyWithOwner(Company::factory()->trialExpired()->create(['name' => 'شركة منتهية']));
    companyWithOwner(Company::factory()->active()->create(['name' => 'شركة فعالة']));
    companyWithOwner(Company::factory()->suspended()->create(['name' => 'شركة معلقة']));

    $response = $this->actingAs($this->admin)->get("/admin/companies?status={$filter}")->assertOk();

    foreach ($shown as $name) {
        $response->assertSee($name);
    }
    foreach ($hidden as $name) {
        $response->assertDontSee($name);
    }
})->with([
    'all' => ['all', ['شركة تجريبية', 'شركة منتهية', 'شركة فعالة', 'شركة معلقة'], []],
    'trial' => ['trial', ['شركة تجريبية'], ['شركة منتهية', 'شركة فعالة', 'شركة معلقة']],
    'expired' => ['expired', ['شركة منتهية'], ['شركة تجريبية', 'شركة فعالة', 'شركة معلقة']],
    'active' => ['active', ['شركة فعالة'], ['شركة تجريبية', 'شركة منتهية', 'شركة معلقة']],
    'suspended' => ['suspended', ['شركة معلقة'], ['شركة تجريبية', 'شركة منتهية', 'شركة فعالة']],
]);

test('an unknown filter shows everything', function () {
    companyWithOwner(Company::factory()->active()->create(['name' => 'شركة فعالة']));
    companyWithOwner(Company::factory()->suspended()->create(['name' => 'شركة معلقة']));

    $this->actingAs($this->admin)->get('/admin/companies?status=nonsense')
        ->assertSee('شركة فعالة')
        ->assertSee('شركة معلقة');
});

test('the page has a card for every filter with its count', function () {
    Company::factory()->active()->count(2)->create();

    $response = $this->actingAs($this->admin)->get('/admin/companies')->assertOk();

    foreach (CompanyFilter::cases() as $card) {
        $response->assertSee($card->label());
    }
});

// --- Search --------------------------------------------------------------------------------------

test('search matches the company name, the owner name and the owner email', function () {
    companyWithOwner(Company::factory()->create(['name' => 'مخازن الريحان']), ['name' => 'سليم دياب', 'email' => 'salim@alrayhan.test']);
    companyWithOwner(Company::factory()->create(['name' => 'محلات القلعة']), ['name' => 'ليلى حداد', 'email' => 'layla@qalaa.test']);
    $this->actingAs($this->admin);

    $this->get('/admin/companies?q=الريحان')->assertSee('مخازن الريحان')->assertDontSee('محلات القلعة');
    $this->get('/admin/companies?q=ليلى')->assertSee('محلات القلعة')->assertDontSee('مخازن الريحان');
    $this->get('/admin/companies?q=salim@')->assertSee('مخازن الريحان')->assertDontSee('محلات القلعة');
});

test('wildcards typed in the search box are plain text', function () {
    companyWithOwner(Company::factory()->create(['name' => 'مخازن الريحان']));
    $this->actingAs($this->admin);

    $this->get('/admin/companies?q=%25')->assertDontSee('مخازن الريحان');
    $this->get('/admin/companies?q=_')->assertDontSee('مخازن الريحان');
});

test('search and filter work together and an empty result says so', function () {
    companyWithOwner(Company::factory()->active()->create(['name' => 'مخازن الريحان']));
    $this->actingAs($this->admin);

    $this->get('/admin/companies?status=active&q=الريحان')->assertSee('مخازن الريحان');
    $this->get('/admin/companies?status=suspended&q=الريحان')
        ->assertDontSee('مخازن الريحان')
        ->assertSee('ما في شركات بهالفلتر');
});

// --- Order and row details -----------------------------------------------------------------------

test('companies whose trial ended come first', function () {
    companyWithOwner(Company::factory()->active()->create(['name' => 'شركة فعالة جديدة']));
    companyWithOwner(Company::factory()->trialExpired()->create(['name' => 'شركة منتهية']));
    companyWithOwner(Company::factory()->create(['name' => 'شركة تجريبية', 'trial_ends_at' => today()->addDays(9)]));

    $names = $this->actingAs($this->admin)->get('/admin/companies')->viewData('companies')->pluck('name')->all();

    expect($names[0])->toBe('شركة منتهية');
});

test('each company shows its owner, registration date and trial note', function () {
    companyWithOwner(
        Company::factory()->create(['name' => 'شركة قريبة', 'trial_ends_at' => today()->addDays(3)]),
        ['name' => 'سامر الأحمد', 'email' => 'samer@near.test'],
    );
    companyWithOwner(Company::factory()->create(['name' => 'شركة بعيدة', 'trial_ends_at' => today()->addDays(20)]));
    companyWithOwner(Company::factory()->create(['name' => 'شركة تنتهي اليوم', 'trial_ends_at' => today()]));
    companyWithOwner(Company::factory()->create(['name' => 'شركة متأخرة', 'trial_ends_at' => today()->subDays(12)]));
    companyWithOwner(Company::factory()->active()->create(['name' => 'شركة مشتركة']));

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee('سامر الأحمد')
        ->assertSee('samer@near.test')
        ->assertSee('باقي 3 أيام')
        ->assertSee('باقي 20 يوم')
        ->assertSee('تنتهي اليوم')
        ->assertSee('انتهت من 12 يوم')
        ->assertSee(App\Support\Format::date(today()));
});

test('a suspended company whose trial is over says the trial ended', function () {
    companyWithOwner(Company::factory()->suspended()->create(['trial_ends_at' => today()->subDays(40)]));

    $this->actingAs($this->admin)->get('/admin/companies')->assertSee('انتهت التجربة');
});

test('the extend dialog shows the new end dates from the later of today and the current end date', function () {
    $running = companyWithOwner(Company::factory()->create(['name' => 'شركة جارية', 'trial_ends_at' => today()->addDays(5)]));
    $over = companyWithOwner(Company::factory()->trialExpired()->create(['name' => 'شركة منتهية']));

    expect($running->trialEndAfterExtending(7)->toDateString())->toBe(today()->addDays(12)->toDateString())
        ->and($over->trialEndAfterExtending(7)->toDateString())->toBe(today()->addDays(7)->toDateString());

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee(App\Support\Format::date(today()->addDays(12)))   // running: 5 + 7
        ->assertSee(App\Support\Format::date(today()->addDays(7)))    // over: today + 7
        ->assertSee('الانتهاء الجديد');
});

test('an active company offers no activate or extend action', function () {
    companyWithOwner(Company::factory()->active()->create());

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertDontSee('تمديد التجربة')
        ->assertDontSee('تفعيل')
        ->assertSee('إيقاف');
});

// --- It is the platform dashboard ----------------------------------------------------------------

test('the platform dashboard is the companies page', function () {
    $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('admin.companies.index'));
});

test('the sidebar of the platform owner has one entry for it', function () {
    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee('الشركات')
        ->assertDontSee('لوحة التحكم');
});

test('company users cannot open the companies page', function () {
    $this->actingAs(User::factory()->create())->get('/admin/companies')->assertForbidden();
    $this->actingAs(User::factory()->warehouse()->create())->get('/admin/companies')->assertForbidden();
});
