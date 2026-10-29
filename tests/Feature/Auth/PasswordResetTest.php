<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the reset e-mail is Arabic, right to left, in the company colour, with a working link', function () {
    Notification::fake();

    $company = \App\Models\Company::factory()->create(['primary_color' => '#0F766E']);
    $user = User::factory()->for($company)->create(['name' => 'سامر']);

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);
        $html = (string) $mail->render();

        expect($mail->subject)->toContain('استعادة كلمة المرور')
            ->and($html)->toContain('dir="rtl"')
            ->and($html)->toContain('أهلاً سامر')
            ->and($html)->toContain('#0F766E')
            ->and($html)->toContain(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->and($html)->not->toContain('You are receiving this email');

        return true;
    });
});
