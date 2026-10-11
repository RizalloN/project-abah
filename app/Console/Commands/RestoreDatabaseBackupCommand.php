<?php

namespace App\Console\Commands;

use App\Services\DailyDatabaseBackupService;
use App\Services\DatabaseBackup\ParallelPartRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Restores a chunked daily backup: verify checksums, load schema, load data
 * parts in parallel, apply triggers last, then reconcile period row counts.
 */
class RestoreDatabaseBackupCommand extends Command
{
    protected $signature = 'database:restore-daily
        {--backup= : Nama folder backup (default: backup terkelola terbaru)}
        {--database= : Database tujuan restore (wajib)}
        {--tables= : Batasi data ke tabel tertentu, dipisah koma (struktur tetap lengkap)}
        {--workers=3 : Jumlah proses pemuat paralel}
        {--skip-verify : Lewati verifikasi checksum part (tidak disarankan)}
        {--force-live : Izinkan menimpa database aplikasi yang sedang dipakai}';

    protected $description = 'Restore a verified chunked daily database backup into a target database';

    public function handle(DailyDatabaseBackupService $backups, ParallelPartRunner $runner): int
    {
        set_time_limit(0);
        $target = trim((string) $this->option('database'));
        if ($target === '' || preg_match('/^[A-Za-z0-9_]+$/', $target) !== 1) {
            $this->error('Opsi --database wajib diisi dengan nama database yang valid (huruf, angka, garis bawah).');

            return self::FAILURE;
        }

        $connectionName = (string) Config::get('database.default');
        $connection = (array) Config::get("database.connections.{$connectionName}");
        if ($target === (string) ($connection['database'] ?? '') && ! $this->option('force-live')) {
            $this->error("Database tujuan sama dengan database aplikasi ({$target}). Gunakan --force-live bila memang disengaja.");

            return self::FAILURE;
        }

        $credentialsPath = null;
        $workDirectory = null;
        try {
            $backup = $backups->resolveChunkedBackup($this->option('backup') !== null ? (string) $this->option('backup') : null);
            $manifest = $backup['manifest'];
            $this->info('Backup: '.basename($backup['directory']).' ('.$manifest['backup_date'].', '.count($manifest['parts']).' part)');

            $tableFilter = $this->tableFilter();
            if (! $this->option('skip-verify')) {
                $this->line('Memverifikasi checksum part...');
                $backups->verifyChunkedParts($backup, $tableFilter);
            }

            $workDirectory = storage_path('framework/restore-'.date('YmdHis').'-'.bin2hex(random_bytes(4)));
            if (! @mkdir($workDirectory, 0775, true)) {
                throw new RuntimeException('Folder kerja restore tidak dapat dibuat.');
            }
            $credentialsPath = $workDirectory.DIRECTORY_SEPARATOR.'.mysql-client.cnf';
            $this->writeCredentials($credentialsPath, $connection);

            DB::statement('CREATE DATABASE IF NOT EXISTS `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $phases = [
                'struktur' => array_filter($manifest['parts'], static fn (array $part): bool => $part['kind'] === 'schema'),
                'data' => array_filter($manifest['parts'], static fn (array $part): bool => $part['kind'] === 'data'
                    && ($tableFilter === null || in_array($part['table'], $tableFilter, true))),
                'trigger' => array_filter($manifest['parts'], static fn (array $part): bool => $part['kind'] === 'post'),
            ];

            $startedAt = microtime(true);
            foreach ($phases as $label => $parts) {
                $this->line(sprintf('Memuat %s: %d part...', $label, count($parts)));
                $specs = [];
                foreach (array_values($parts) as $index => $part) {
                    $specPath = $workDirectory.DIRECTORY_SEPARATOR.$label.'-'.sprintf('%05d', $index).'.json';
                    file_put_contents($specPath, json_encode([
                        'mysql_binary' => $this->mysqlBinary(),
                        'credentials_path' => $credentialsPath,
                        'database' => $target,
                        'gzip_path' => $backup['directory'].DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $part['file']),
                        'stderr_path' => $specPath.'.stderr',
                        'result_path' => $specPath.'.result.json',
                    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                    $specs[$part['key']] = $specPath;
                }

                $done = 0;
                $runner->run(
                    'database:restore-part',
                    $specs,
                    $label === 'data' ? max(1, (int) $this->option('workers')) : 1,
                    function (string $key) use (&$done, $specs): void {
                        $done++;
                        if ($done === count($specs) || $done % 10 === 0) {
                            $this->line("  {$done}/".count($specs).' selesai (terakhir: '.$key.')');
                        }
                    }
                );
            }

            $mismatches = $this->reconcileRows($target, $manifest['parts'], $tableFilter);
            $this->info(sprintf('Restore selesai dalam %.1f menit ke database `%s`.', (microtime(true) - $startedAt) / 60, $target));
            foreach ($mismatches as $mismatch) {
                $this->warn($mismatch);
            }

            return $mismatches === [] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Restore gagal: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($credentialsPath !== null && is_file($credentialsPath)) {
                @unlink($credentialsPath);
            }
            if ($workDirectory !== null && is_dir($workDirectory)) {
                foreach (glob($workDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($workDirectory);
            }
        }
    }

    /**
     * Row counts of every restored period must equal the counts recorded
     * when the backup was planned.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @param  array<int, string>|null  $tableFilter
     * @return array<int, string>
     */
    private function reconcileRows(string $target, array $parts, ?array $tableFilter): array
    {
        $expected = [];
        foreach ($parts as $part) {
            if (($part['kind'] ?? '') !== 'data' || ! isset($part['period_column'], $part['rows'])
                || ($tableFilter !== null && ! in_array($part['table'], $tableFilter, true))) {
                continue;
            }
            $expected[$part['table']]['column'] = $part['period_column'];
            $expected[$part['table']]['periods'][(string) ($part['period'] ?? "\0null")] = (int) $part['rows'];
        }

        $mismatches = [];
        foreach ($expected as $table => $definition) {
            $column = '`'.str_replace('`', '``', $definition['column']).'`';
            $actual = [];
            foreach (DB::select("SELECT {$column} AS period, COUNT(*) AS row_count FROM `{$target}`.`{$table}` GROUP BY {$column}") as $row) {
                $actual[$row->period === null ? "\0null" : (string) $row->period] = (int) $row->row_count;
            }
            foreach ($definition['periods'] as $period => $rows) {
                if (($actual[$period] ?? 0) !== $rows) {
                    $mismatches[] = sprintf('Jumlah baris %s periode %s: backup %d, hasil restore %d.',
                        $table, $period === "\0null" ? 'NULL' : $period, $rows, $actual[$period] ?? 0);
                }
            }
            $this->line(sprintf('  Cocok: %s (%d periode, %s baris)', $table, count($definition['periods']),
                number_format(array_sum($definition['periods']), 0, ',', '.')));
        }

        return $mismatches;
    }

    /** @return array<int, string>|null */
    private function tableFilter(): ?array
    {
        $option = trim((string) $this->option('tables'));
        if ($option === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $option))));
    }

    private function mysqlBinary(): string
    {
        foreach ([(string) config('database_backup.mysql_binary', ''), 'D:\\XAMPP\\mysql\\bin\\mysql.exe', 'C:\\xampp\\mysql\\bin\\mysql.exe', '/usr/bin/mysql'] as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Binary mysql client tidak ditemukan.');
    }

    /** @param  array<string, mixed>  $connection */
    private function writeCredentials(string $path, array $connection): void
    {
        $quote = static fn (string $value): string => '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n']).'"';
        file_put_contents($path, implode(PHP_EOL, [
            '[client]',
            'user='.$quote((string) ($connection['username'] ?? '')),
            'password='.$quote((string) ($connection['password'] ?? '')),
            'protocol=TCP',
            'host='.$quote((string) ($connection['host'] ?? '127.0.0.1')),
            'port='.(int) ($connection['port'] ?? 3306),
        ]).PHP_EOL);
        @chmod($path, 0600);
    }
}
