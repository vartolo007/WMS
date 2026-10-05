<?php

use App\Models\Company;
use App\Models\User;

test('the owner sidebar has the company management section', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertSee('إدارة الشركة')
        ->assertSee(route('warehouses.index'))
        ->assertSee('المستودع:')
        ->assertDontSee(route('admin.companies.index'));
});

test('a warehouse sees neither company management nor the warehouse selector', function () {
    $warehouse = User::factory()->warehouse()->create(['name' => 'مستودع المدينة']);

    $this->actingAs($warehouse)->get('/dashboard')
        ->assertOk()
        ->assertSee('مستودع المدينة')
        ->assertDontSee('إدارة الشركة')
        ->assertDontSee(route('warehouses.index'))
        ->assertDontSee('المستودع:');
});

test('the super admin sees the platform shell', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee('لوحة إدارة المنصة')
        ->assertSee(route('admin.companies.index'))
        ->assertDontSee(route('warehouses.index'));
});

test('company users get the company colour, guests and the admin get the default', function () {
    $company = Company::factory()->create(['primary_color' => '#0F766E']);
    $owner = User::factory()->for($company)->create();
    $warehouse = User::factory()->warehouse()->for($company)->create();

    foreach ([$owner, $warehouse] as $user) {
        $this->actingAs($user)->get('/dashboard')->assertSee('--color-primary: #0F766E', false);
    }

    $this->actingAs(User::factory()->superAdmin()->create())->get('/dashboard')
        ->assertSee('--color-primary: '.config('wms.default_color'), false);

    auth()->logout();
    $this->get('/login')->assertSee('--color-primary: '.config('wms.default_color'), false);
});

test('the owner sees the trial days left', function () {
    $company = Company::factory()->create(['trial_ends_at' => today()->addDays(9)]);

    $this->actingAs(User::factory()->for($company)->create())->get('/dashboard')
        ->assertSee('تجريبي · باقي 9 يوم');
});

test('login failures show one general message above the form', function () {
    $this->from('/login')->followingRedirects()
        ->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
        ->assertSee('بيانات الدخول غير صحيحة.')
        ->assertSee('role="alert"', false);
});

test('register shows the configured trial length', function () {
    config(['wms.trial_days' => 21]);

    $this->get('/register')->assertSee('21 يوم');
});
