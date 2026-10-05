<?php

test('palette has eight colours and includes the default', function () {
    $palette = config('wms.palette');

    expect($palette)->toHaveCount(8)
        ->and($palette)->toContain(config('wms.default_color'))
        ->each->toMatch('/^#[0-9A-F]{6}$/');
});

test('layout prints the primary colour as a CSS variable', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('--color-primary: '.config('wms.default_color'), false);
});
