<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily database backup (SRS 4.8): runs from the scheduler, exits non-zero on any failure.
 */
class BackupDatabase extends Command
{
    protected $signature = 'wms:backup';

    protected $description = 'Back up the database to a gzip archive and prune old archives';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->backup();
            $pruned = $backups->prune();
        } catch (Throwable $e) {
            Log::error('Database backup failed: '.$e->getMessage());
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        Log::info("Database backup written to {$path}; {$pruned} old archive(s) removed.");
        $this->info('Backup written: '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');
        $this->line("Old archives removed: {$pruned}");

        return self::SUCCESS;
    }
}
