<?php

use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * The first mysqldump that runs on this machine: WMS_MYSQLDUMP_PATH, the XAMPP default, or the PATH.
 */
function findMysqldump(): ?string
{
    foreach (array_filter([env('WMS_MYSQLDUMP_PATH'), 'C:\xampp\mysql\bin\mysqldump.exe', 'mysqldump']) as $candidate) {
        try {
            if ((new Process([$candidate, '--version']))->run() === 0) {
                return $candidate;
            }
        } catch (Throwable) {
            // try the next one
        }
    }

    return null;
}

beforeEach(function () {
    $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wms-backup-test-'.uniqid();
    config(['wms.backup.path' => $this->directory, 'wms.backup.keep_days' => 14]);

    $this->binary = findMysqldump();
    config(['wms.backup.mysqldump' => $this->binary ?? 'mysqldump']);
    config(['wms.backup.mysql' => null]);

    $this->needsMysql = function () {
        if ($this->binary === null) {
            $this->markTestSkipped('mysqldump was not found: set WMS_MYSQLDUMP_PATH to run the backup tests.');
        }
    };
});

afterEach(function () {
    foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->directory);
});

/**
 * The text of a gzip archive.
 */
function archiveText(string $path): string
{
    return gzdecode(file_get_contents($path));
}

test('a backup is a complete gzip dump of every table, and no partial file stays', function () {
    ($this->needsMysql)();

    $path = app(BackupService::class)->backup();
    $dump = archiveText($path);

    expect($path)->toStartWith($this->directory)
        ->and($path)->toEndWith('.sql.gz')
        ->and($dump)->toContain('CREATE TABLE `companies`')
        ->and($dump)->toContain('CREATE TABLE `operations`')
        ->and($dump)->toContain('-- Dump completed')
        ->and(glob($this->directory.DIRECTORY_SEPARATOR.'*.partial'))->toBe([]);
});

test('the wms:backup command writes an archive and reports it', function () {
    ($this->needsMysql)();

    $this->artisan('wms:backup')->assertSuccessful()->expectsOutputToContain('Backup written');

    expect(app(BackupService::class)->archives())->toHaveCount(1);
});

test('pruning removes only archives older than the retention and never the newest', function () {
    mkdir($this->directory);
    $make = function (string $name, int $daysOld) {
        $path = $this->directory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, 'x');
        touch($path, now()->subDays($daysOld)->getTimestamp());

        return $path;
    };

    $old = $make('wms-20260101-020000.sql.gz', 40);
    $older = $make('wms-20250101-020000.sql.gz', 400);
    $recent = $make('wms-20261015-020000.sql.gz', 5);
    $other = $this->directory.DIRECTORY_SEPARATOR.'notes.txt';
    file_put_contents($other, 'keep me');
    touch($other, now()->subDays(900)->getTimestamp());

    expect(app(BackupService::class)->prune())->toBe(2)
        ->and(file_exists($old))->toBeFalse()
        ->and(file_exists($older))->toBeFalse()
        ->and(file_exists($recent))->toBeTrue()
        ->and(file_exists($other))->toBeTrue();
});

test('when every archive is old the newest one still stays', function () {
    mkdir($this->directory);
    foreach (['wms-20250101-020000.sql.gz' => 100, 'wms-20250201-020000.sql.gz' => 90] as $name => $days) {
        $path = $this->directory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, 'x');
        touch($path, now()->subDays($days)->getTimestamp());
    }

    expect(app(BackupService::class)->prune())->toBe(1)
        ->and(app(BackupService::class)->archives())->toHaveCount(1)
        ->and(basename(app(BackupService::class)->latest()))->toBe('wms-20250201-020000.sql.gz');
});

test('the database password never appears on the command line', function () {
    config(['database.connections.mysql.password' => 'S3cr3t-Passw0rd!']);

    $command = (new ReflectionMethod(BackupService::class, 'dumpCommand'))->invoke(app(BackupService::class));

    expect(implode(' ', $command))->not->toContain('S3cr3t-Passw0rd!')
        ->and(implode(' ', $command))->not->toContain('--password');
});

test('a failing dump fails the command and leaves nothing behind', function () {
    config(['wms.backup.mysqldump' => $this->directory.DIRECTORY_SEPARATOR.'no-such-mysqldump']);

    $this->artisan('wms:backup')->assertFailed()->expectsOutputToContain('Backup failed');

    expect(glob($this->directory.DIRECTORY_SEPARATOR.'*') ?: [])->toBe([]);
});

test('the restore check restores the newest archive into a scratch database and drops it again', function () {
    ($this->needsMysql)();

    $this->artisan('wms:backup')->assertSuccessful();
    $this->artisan('wms:backup:check')->assertSuccessful()->expectsOutputToContain('Restored into a scratch database');

    $scratch = config('wms.backup.restore_database');
    $exists = DB::select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$scratch]);

    expect($exists)->toBe([]);
});

test('the restore check fails when the archive does not hold the live tables', function () {
    ($this->needsMysql)();

    $path = app(BackupService::class)->backup();
    file_put_contents($path, gzencode(str_replace('CREATE TABLE `companies`', 'CREATE TABLE `companies_gone`', archiveText($path))));

    $this->artisan('wms:backup:check')->assertFailed()->expectsOutputToContain('Restore check failed');
});

test('the restore check needs an archive', function () {
    $this->artisan('wms:backup:check')->assertFailed()->expectsOutputToContain('No backup archive found');
});

test('the scheduler runs the backup daily and the restore check weekly', function () {
    // The schedule is defined in routes/console.php, which loads when the console kernel boots.
    Artisan::call('schedule:list');

    $expressions = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [preg_match('/(wms:[a-z:]+)\s*$/', (string) $event->command, $m) ? $m[1] : $event->command => $event->expression]);

    expect($expressions->get('wms:backup'))->toBe('0 2 * * *')
        ->and($expressions->get('wms:backup:check'))->toBe('0 3 * * 0');
});
