<?php

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->superAdmin()->create();
});

test('the admin sees all companies with their owner and status', function () {
    $owner = User::factory()->create();
    Company::factory()->suspended()->create(['name' => 'شركة موقوفة']);

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertOk()
        ->assertSee($owner->company->name)
        ->assertSee($owner->email)
        ->assertSee('شركة موقوفة')
        ->assertSee('موقوفة');
});

test('expired trials are highlighted', function () {
    Company::factory()->trialExpired()->create();

    $this->actingAs($this->admin)->get('/admin/companies')
        ->assertSee('انتهت من 3 أيام')
        ->assertSee('bg-danger-soft', false);
});

test('the admin activates and suspends a company', function () {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)->patch("/admin/companies/{$company->id}/activate")->assertRedirect();
    expect($company->fresh()->status)->toBe(CompanyStatus::Active);

    $this->patch("/admin/companies/{$company->id}/suspend")->assertRedirect();
    expect($company->fresh()->status)->toBe(CompanyStatus::Suspended);
});

test('extending a running trial adds days to its end date', function () {
    $company = Company::factory()->create(['trial_ends_at' => today()->addDays(5)]);

    $this->actingAs($this->admin)->patch("/admin/companies/{$company->id}/extend-trial", ['days' => 10]);

    expect($company->fresh()->trial_ends_at->toDateString())->toBe(today()->addDays(15)->toDateString());
});

test('extending an expired or suspended trial starts from today and returns to trial', function () {
    $company = Company::factory()->suspended()->create(['trial_ends_at' => today()->subDays(20)]);

    $this->actingAs($this->admin)->patch("/admin/companies/{$company->id}/extend-trial", ['days' => 7]);

    $company->refresh();
    expect($company->status)->toBe(CompanyStatus::Trial)
        ->and($company->trial_ends_at->toDateString())->toBe(today()->addDays(7)->toDateString());
});

test('an active company trial cannot be extended', function () {
    $company = Company::factory()->active()->create();
    $end = $company->trial_ends_at->toDateString();

    $this->actingAs($this->admin)
        ->patch("/admin/companies/{$company->id}/extend-trial", ['days' => 7])
        ->assertSessionHasErrors('days');

    expect($company->fresh()->trial_ends_at->toDateString())->toBe($end)
        ->and($company->fresh()->status)->toBe(CompanyStatus::Active);
});

test('extension days must be between 1 and 365', function (mixed $days) {
    $company = Company::factory()->create();

    $this->actingAs($this->admin)
        ->patch("/admin/companies/{$company->id}/extend-trial", ['days' => $days])
        ->assertSessionHasErrors('days');
})->with([0, 366, 'abc']);

test('a company owner cannot change subscriptions', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->patch("/admin/companies/{$owner->company_id}/activate")
        ->assertForbidden();

    expect($owner->company->fresh()->status)->toBe(CompanyStatus::Trial);
});
