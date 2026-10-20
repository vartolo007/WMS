<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Daily database backup and its restore test (SRS 4.8). No package: `mysqldump` through Symfony Process,
 * gzip through PHP streams. The database password travels in the MYSQL_PWD environment variable of the
 * child process, never on its command line. A half-written archive never keeps its final name.
 */
class BackupService
{
    private const PREFIX = 'wms-';

    private const SUFFIX = '.sql.gz';

    /** mysqldump ends a complete dump with this comment (unless --skip-dump-date / --skip-comments is used). */
    private const COMPLETE_MARKER = '-- Dump completed';

    /**
     * Dump the database into a new gzip archive in the backup folder. Returns its path.
     *
     * @throws RuntimeException when the dump fails or is incomplete (no archive is left behind)
     */
    public function backup(): string
    {
        $directory = $this->directory();
        $final = $directory.DIRECTORY_SEPARATOR.self::PREFIX.now()->format('Ymd-His').self::SUFFIX;
        $partial = $final.'.partial';

        $gz = gzopen($partial, 'wb9');

        if ($gz === false) {
            throw new RuntimeException("Cannot write the backup file {$partial}.");
        }

        $tail = '';
        $errors = '';
        $bytes = 0;

        try {
            $process = new Process($this->dumpCommand(), null, ['MYSQL_PWD' => (string) $this->connection('password')], null, null);
            $process->run(function (string $type, string $buffer) use ($gz, &$tail, &$errors, &$bytes) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                    $bytes += strlen($buffer);
                    $tail = substr($tail.$buffer, -300);
                } else {
                    $errors .= $buffer;
                }
            });
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($partial);

            throw new RuntimeException('mysqldump could not run: '.$e->getMessage(), previous: $e);
        }

        gzclose($gz);

        if (! $process->isSuccessful() || $bytes === 0 || ! str_contains($tail, self::COMPLETE_MARKER)) {
            @unlink($partial);

            throw new RuntimeException('mysqldump failed or produced an incomplete dump: '.trim($errors ?: "exit code {$process->getExitCode()}"));
        }

        rename($partial, $final);

        return $final;
    }

    /**
     * Delete archives older than `keep_days`; the newest archive always stays, so a stopped scheduler
     * cannot end with no backup at all. Returns the number of deleted archives.
     */
    public function prune(): int
    {
        $archives = $this->archives();
        $limit = now()->subDays((int) config('wms.backup.keep_days'))->getTimestamp();
        $deleted = 0;

        foreach (array_slice($archives, 1) as $path) {
            if (filemtime($path) < $limit && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Archive paths, newest first.
     *
     * @return list<string>
     */
    public function archives(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.self::PREFIX.'*'.self::SUFFIX) ?: [];

        usort($files, fn (string $a, string $b) => [filemtime($b), $b] <=> [filemtime($a), $a]);

        return $files;
    }

    public function latest(): ?string
    {
        return $this->archives()[0] ?? null;
    }

    /**
     * Restore test: load the archive into a scratch database, compare it with the live one, drop it.
     * Fails (RuntimeException) when the archive does not restore or its tables differ from the live ones.
     * Without CREATE DATABASE rights it falls back to reading the archive itself.
     *
     * @return array{mode: string, tables: array<string, array{restored: ?int, live: int}>}
     */
    public function restoreCheck(string $archive): array
    {
        // Only this database: `Schema::getTables()` without a schema lists every schema of the server.
        $live = collect(Schema::getTables(DB::getDatabaseName()))->pluck('name')->sort()->values()->all();
        $scratch = (string) config('wms.backup.restore_database');

        $admin = $this->adminConnection();

        try {
            $admin->statement("DROP DATABASE IF EXISTS `{$scratch}`");
            $admin->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (QueryException $e) {
            // Typically "access denied": the structural check still proves the archive is a whole dump.
            return $this->inspect($archive, $live);
        }

        try {
            $this->load($archive, $scratch);

            $restored = collect($admin->select('SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$scratch]))->pluck('name')->sort()->values()->all();

            if ($restored !== $live) {
                throw new RuntimeException('Restored tables differ from the live ones. Missing: '.implode(', ', array_diff($live, $restored)).'; extra: '.implode(', ', array_diff($restored, $live)));
            }

            $counts = [];

            foreach ($live as $table) {
                $counts[$table] = [
                    'restored' => (int) $admin->selectOne("SELECT COUNT(*) AS total FROM `{$scratch}`.`{$table}`")->total,
                    'live' => (int) DB::selectOne("SELECT COUNT(*) AS total FROM `{$table}`")->total,
                ];
            }

            return ['mode' => 'restore', 'tables' => $counts];
        } finally {
            $admin->statement("DROP DATABASE IF EXISTS `{$scratch}`");
        }
    }

    /**
     * Structural check: the archive must be complete and create every live table.
     *
     * @param  list<string>  $liveTables
     * @return array{mode: string, tables: array<string, array{restored: ?int, live: int}>}
     */
    private function inspect(string $archive, array $liveTables): array
    {
        $handle = gzopen($archive, 'rb');
        $found = [];
        $tail = '';

        while ($handle && ! gzeof($handle)) {
            $chunk = gzread($handle, 1 << 20);

            if (preg_match_all('/CREATE TABLE `([^`]+)`/', $tail.$chunk, $matches)) {
                $found = [...$found, ...$matches[1]];
            }

            $tail = substr($tail.$chunk, -300);
        }

        $handle && gzclose($handle);

        if (! str_contains($tail, self::COMPLETE_MARKER)) {
            throw new RuntimeException('The archive is incomplete (no "Dump completed" marker).');
        }

        $missing = array_diff($liveTables, $found);

        if ($missing !== []) {
            throw new RuntimeException('The archive lacks the tables: '.implode(', ', $missing));
        }

        return ['mode' => 'structure', 'tables' => collect($liveTables)->mapWithKeys(fn (string $table) => [$table => ['restored' => null, 'live' => (int) DB::selectOne("SELECT COUNT(*) AS total FROM `{$table}`")->total]])->all()];
    }

    /**
     * Pipe the gunzipped archive into the mysql client, loading it into `$database`.
     */
    private function load(string $archive, string $database): void
    {
        $input = fopen('compress.zlib://'.$archive, 'rb');

        if ($input === false) {
            throw new RuntimeException("Cannot read the archive {$archive}.");
        }

        $process = new Process([
            $this->client(),
            '--host='.$this->connection('host'),
            '--port='.$this->connection('port'),
            '--user='.$this->connection('username'),
            '--default-character-set=utf8mb4',
            $database,
        ], null, ['MYSQL_PWD' => (string) $this->connection('password')], $input, null);

        $process->run();
        fclose($input);

        if (! $process->isSuccessful()) {
            throw new RuntimeException('The archive did not restore: '.trim($process->getErrorOutput()));
        }
    }

    /**
     * @return list<string>
     */
    private function dumpCommand(): array
    {
        return [
            (string) config('wms.backup.mysqldump'),
            '--host='.$this->connection('host'),
            '--port='.$this->connection('port'),
            '--user='.$this->connection('username'),
            '--single-transaction',
            '--routines',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            (string) $this->connection('database'),
        ];
    }

    /**
     * The mysql client sits next to mysqldump unless `wms.backup.mysql` says otherwise.
     */
    private function client(): string
    {
        $configured = config('wms.backup.mysql');

        return $configured ?: preg_replace('/mysqldump(\.exe)?$/i', 'mysql$1', (string) config('wms.backup.mysqldump'));
    }

    private function directory(): string
    {
        $directory = (string) (config('wms.backup.path') ?: storage_path('app/backups'));

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the backup folder {$directory}.");
        }

        return $directory;
    }

    private function connection(string $key): mixed
    {
        return config('database.connections.'.config('database.default').'.'.$key);
    }

    /**
     * A second connection with the same credentials for CREATE / DROP DATABASE, so those statements
     * never touch the transaction of the main connection.
     */
    private function adminConnection(): \Illuminate\Database\Connection
    {
        Config::set('database.connections.wms_backup_admin', [...config('database.connections.'.config('database.default')), 'database' => null]);
        DB::purge('wms_backup_admin');

        return DB::connection('wms_backup_admin');
    }
}
