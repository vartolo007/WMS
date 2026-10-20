<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

test('passwords are stored hashed', function () {
    $user = User::factory()->for(Company::factory()->create())->create(['password' => 'a-plain-password']);

    expect($user->getRawOriginal('password'))->not->toBe('a-plain-password')
        ->and(Hash::check('a-plain-password', $user->getRawOriginal('password')))->toBeTrue();
});

test('login stops after five wrong attempts, even with the right password', function () {
    $user = User::factory()->for(Company::factory()->create())->create(['password' => 'right-password']);
    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'right-password'])->assertSessionHasErrors('login');
    $this->assertGuest();

    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
});

test('the signed-in pages are closed to guests', function () {
    foreach (['/dashboard', '/products', '/parties', '/movements', '/movements/create', '/reports', '/reports/stock', '/warehouses', '/settings', '/profile', '/admin/companies'] as $url) {
        $this->get($url)->assertRedirect('/login');
    }
});
