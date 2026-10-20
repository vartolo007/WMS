<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SRS 4.8: a daily database backup, and a weekly restore test of the newest archive. Needs the scheduler
// running (cron / Task Scheduler calling `php artisan schedule:run` every minute, see the README).
Schedule::command('wms:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::critical('Scheduled database backup failed.'));

Schedule::command('wms:backup:check')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->onFailure(fn () => Log::critical('Scheduled backup restore check failed.'));
