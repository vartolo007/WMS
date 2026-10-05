<?php

use App\Models\User;

test('each role lands on its own dashboard', function (string $state, string $view) {
    $user = $state === 'owner' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertViewIs($view);
})->with([
    'owner' => ['owner', 'dashboard.owner'],
    'warehouse' => ['warehouse', 'dashboard.warehouse'],
    'super admin' => ['superAdmin', 'dashboard.admin'],
]);

test('a warehouse cannot manage warehouses', function () {
    $this->actingAs(User::factory()->warehouse()->create())
        ->get('/warehouses')
        ->assertForbidden();
});

test('only the super admin reaches the admin area', function (string $state) {
    $user = $state === 'owner' ? User::factory()->create() : User::factory()->{$state}()->create();

    $this->actingAs($user)->get('/admin/companies')->assertForbidden();
})->with(['owner', 'warehouse']);

test('the super admin cannot open company pages', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get('/warehouses')
        ->assertForbidden();
});

test('guests are redirected to login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/dashboard', '/warehouses', '/admin/companies']);
