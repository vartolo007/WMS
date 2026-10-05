<?php

use App\Enums\CompanyStatus;
use App\Enums\Role;
use App\Models\Company;
use App\Models\User;

function registrationData(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'شركة النور',
        'name' => 'سامر',
        'email' => 'samer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('اسم الشركة');
});

test('a visitor registers a trial company with its owner', function () {
    config(['wms.trial_days' => 14]);

    $response = $this->post('/register', registrationData());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $company = Company::sole();
    expect($company->name)->toBe('شركة النور')
        ->and($company->status)->toBe(CompanyStatus::Trial)
        ->and($company->trial_ends_at->toDateString())->toBe(today()->addDays(14)->toDateString());

    $owner = User::sole();
    expect($owner->company_id)->toBe($company->id)
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->is_active)->toBeTrue();
});

test('a duplicate email creates no company', function () {
    User::factory()->create(['email' => 'samer@example.com']);
    $companiesBefore = Company::count();

    $this->post('/register', registrationData())
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(Company::count())->toBe($companiesBefore);
});

test('company name is required', function () {
    $this->post('/register', registrationData(['company_name' => '']))
        ->assertSessionHasErrors(['company_name' => 'حقل اسم الشركة مطلوب.']);
});
