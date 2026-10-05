<?php

test('login page renders in Arabic right-to-left', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee('تسجيل الدخول')
        ->assertSee('كلمة المرور');
});

test('validation messages are in Arabic', function () {
    $this->post('/login', [])
        ->assertSessionHasErrors([
            'email' => 'حقل البريد الإلكتروني مطلوب.',
            'password' => 'حقل كلمة المرور مطلوب.',
        ]);
});

test('failed login message is in Arabic', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.']);
});
