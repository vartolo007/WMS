<?php

return [

    /*
    | Trial period length in days for newly registered companies (SRS FR-01, Q4).
    */
    'trial_days' => (int) env('WMS_TRIAL_DAYS', 14),

    /*
    | Approved primary colours (SRS FR-25, T15). All are readable with white text.
    | A company's primary_color must be one of these values.
    */
    'palette' => [
        '#1F4E79',
        '#2E75B6',
        '#0F766E',
        '#15803D',
        '#B45309',
        '#B91C1C',
        '#7E22CE',
        '#374151',
    ],

    'default_color' => '#1F4E79',

    /*
    | Approved product units (SRS FR-07, 13.2.14). The key is what products.unit stores;
    | allows_fraction decides whether quantities may have decimals (up to 3).
    */
    'units' => [
        'liter' => ['label' => 'لتر', 'allows_fraction' => true],
        'kilo' => ['label' => 'كيلو', 'allows_fraction' => true],
        'box' => ['label' => 'علبة', 'allows_fraction' => false],
        'piece' => ['label' => 'قطعة', 'allows_fraction' => false],
    ],

    /*
    | Platform owner (super admin) created by SuperAdminSeeder.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
