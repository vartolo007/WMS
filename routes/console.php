<?php

use App\Support\AdminAlert;
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
    ->onFailure(function () {
        Log::critical('Scheduled database backup failed.');
        AdminAlert::send('فشل النسخ الاحتياطي اليومي', 'النسخة الاحتياطية المجدولة ما انكتبت. شوف storage/logs وشغّل php artisan wms:backup يدويًا.');
    });

Schedule::command('wms:backup:check')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('Scheduled backup restore check failed.');
        AdminAlert::send('فشل اختبار استرجاع النسخة الاحتياطية', 'أحدث نسخة احتياطية ما انسترجعت بشكل سليم. شوف storage/logs.');
    });

// Comfort pack: the owner's daily e-mail when products are below their minimum (only when there are some).
Schedule::command('wms:alerts:low-stock')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        Log::critical('Scheduled low-stock alerts failed.');
        AdminAlert::send('فشل إرسال تنبيهات المواد المنخفضة', 'مهمة التنبيه اليومي ما اكتملت. شوف storage/logs.');
    });
