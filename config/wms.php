<?php

return [

    /*
    | Trial period length in days for newly registered companies (SRS FR-01, Q4).
    */
    'trial_days' => (int) env('WMS_TRIAL_DAYS', 14),

    /*
    | A product with stock and no sale for this many days counts as stagnant on the dashboards (SRS 3.7).
    | The stagnant report takes its own period from its filter.
    */
    'stagnant_days' => (int) env('WMS_STAGNANT_DAYS', 60),

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
    | How users of a suspended company reach the platform owner (SRS FR-03, shown to company owners only).
    | The defaults are placeholders from the design: set the real values in .env.
    */
    'support' => [
        'phone' => env('WMS_SUPPORT_PHONE', '+963 11 000 0000'),
        'email' => env('WMS_SUPPORT_EMAIL', 'support@raff.app'),
        'hours' => env('WMS_SUPPORT_HOURS', '9 الصبح – 5 المسا، الأحد للخميس'),
    ],

    /*
    | Platform owner (super admin) created by SuperAdminSeeder.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
