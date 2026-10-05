<?php

use App\Models\Company;
use App\Models\User;

function attemptLogin(User $user)
{
    return test()->post('/login', ['email' => $user->email, 'password' => 'password']);
}

test('users of a suspended company cannot log in', function () {
    $company = Company::factory()->suspended()->create();
    $owner = User::factory()->for($company)->create();
    $warehouse = User::factory()->warehouse()->for($company)->create();

    foreach ([$owner, $warehouse] as $user) {
        attemptLogin($user)->assertSessionHasErrors(['login' => trans('auth.suspended')]);
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

test('an open session ends when the company is suspended', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->get('/dashboard')->assertOk();

    $owner->company->update(['status' => 'suspended']);

    $this->get('/dashboard')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['login' => trans('auth.suspended')]);
    $this->assertGuest();
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
    $this->get('/dashboard')->assertOk();
});
