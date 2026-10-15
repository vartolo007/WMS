<?php

use App\Models\Company;
use App\Models\User;

function attemptLogin(User $user)
{
    return test()->post('/login', ['email' => $user->email, 'password' => 'password']);
}

test('users of a suspended company can log in and land on the suspended page', function () {
    $company = Company::factory()->suspended()->create();
    $owner = User::factory()->for($company)->create();
    $warehouse = User::factory()->warehouse()->for($company)->create();

    foreach ([$owner, $warehouse] as $user) {
        attemptLogin($user)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect(route('subscription.suspended'));
        $this->get(route('subscription.suspended'))->assertOk();

        $this->post('/logout');
        $this->assertGuest();
    }
});

test('a deactivated warehouse cannot log in', function () {
    $warehouse = User::factory()->warehouse()->inactive()->create();

    attemptLogin($warehouse)->assertSessionHasErrors(['login' => trans('auth.inactive')]);
    $this->assertGuest();
});

test('an expired trial does not block login', function () {
    $owner = User::factory()->for(Company::factory()->trialExpired())->create();

    attemptLogin($owner)->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($owner);
});

test('an open session is sent to the suspended page when the company is suspended', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/dashboard')->assertOk();

    $owner->company->update(['status' => 'suspended']);

    $this->get('/dashboard')->assertRedirect(route('subscription.suspended'));
    $this->assertAuthenticatedAs($owner);
});

test('an open session ends when the warehouse is deactivated', function () {
    $warehouse = User::factory()->warehouse()->create();
    $this->actingAs($warehouse)->get('/dashboard')->assertOk();

    $warehouse->update(['is_active' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the super admin is not affected by company status', function () {
    $admin = User::factory()->superAdmin()->create();

    attemptLogin($admin)->assertRedirect(route('dashboard', absolute: false));
    $this->get('/admin/companies')->assertOk();
});
