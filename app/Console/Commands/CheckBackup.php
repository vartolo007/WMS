<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Restore test of the newest backup (SRS 4.8: "اختبار الاسترجاع دوريًا").
 */
class CheckBackup extends Command
{
    protected $signature = 'wms:backup:check';

    protected $description = 'Restore the newest backup into a scratch database and compare it with the live one';

    public function handle(BackupService $backups): int
    {
        $archive = $backups->latest();

        if ($archive === null) {
            Log::error('Backup restore check failed: no archive found.');
            $this->error('No backup archive found. Run wms:backup first.');

            return self::FAILURE;
        }

        try {
            $result = $backups->restoreCheck($archive);
        } catch (Throwable $e) {
            Log::error('Backup restore check failed: '.$e->getMessage());
            $this->error('Restore check failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(($result['mode'] === 'restore' ? 'Restored into a scratch database' : 'Archive structure verified (no CREATE DATABASE rights, nothing restored)').': '.basename($archive));
        $this->table(['table', 'restored rows', 'live rows'], collect($result['tables'])->map(fn ($counts, $table) => [$table, $counts['restored'] ?? '-', $counts['live']])->values()->all());

        Log::info('Backup restore check passed for '.basename($archive).' ('.$result['mode'].').');

        return self::SUCCESS;
    }
}
