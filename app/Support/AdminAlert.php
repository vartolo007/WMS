<?php

namespace App\Support;

use App\Mail\OperationalAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A short e-mail to the platform owner (`ADMIN_EMAIL`) when a scheduled job fails (backup, restore test, alerts).
 * It never throws: a broken mail setup must not hide the original failure, which is already in the log.
 */
class AdminAlert
{
    public static function send(string $subject, string $body): void
    {
        $to = config('wms.admin.email');

        if (blank($to)) {
            return;
        }

        try {
            Mail::to($to)->send(new OperationalAlert($subject, $body));
        } catch (Throwable $e) {
            Log::error('The admin alert "'.$subject.'" could not be sent: '.$e->getMessage());
        }
    }
}
