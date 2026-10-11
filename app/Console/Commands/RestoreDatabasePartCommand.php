<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Worker process for one restore part: streams a gzip SQL part into the mysql
 * client. Bulk-load session settings are sent first and the part commits once.
 */
class RestoreDatabasePartCommand extends Command
{
    protected $signature = 'database:restore-part {spec : JSON spec written by database:restore-daily}';

    protected $description = 'Internal worker: load one part of a chunked daily backup';

    protected $hidden = true;

    private const PRELUDE = "SET SESSION foreign_key_checks=0;\nSET SESSION unique_checks=0;\n"
        ."SET @skip_snapshot_invalidation=1;\nSET autocommit=0;\n";

    public function handle(): int
    {
        set_time_limit(0);
        $spec = json_decode((string) @file_get_contents((string) $this->argument('spec')), true);
        if (! is_array($spec) || ! is_string($spec['result_path'] ?? null)) {
            $this->error('Spec restore part tidak valid.');

            return self::FAILURE;
        }

        $startedAt = microtime(true);
        try {
            $bytes = $this->load($spec);
            $result = ['status' => 'ok', 'uncompressed_bytes' => $bytes, 'seconds' => round(microtime(true) - $startedAt, 1)];
        } catch (Throwable $exception) {
            $result = ['status' => 'failed', 'error' => $exception->getMessage(), 'stderr' => $this->tail((string) $spec['stderr_path'])];
        }

        @unlink((string) $spec['stderr_path']);
        file_put_contents($spec['result_path'], json_encode($result, JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<string, mixed>  $spec */
    private function load(array $spec): int
    {
        $gzip = @gzopen((string) $spec['gzip_path'], 'rb');
        if (! is_resource($gzip)) {
            throw new RuntimeException('Part backup tidak dapat dibuka: '.basename((string) $spec['gzip_path']));
        }

        $process = @proc_open(
            [
                (string) $spec['mysql_binary'],
                '--defaults-extra-file='.$spec['credentials_path'],
                '--default-character-set=utf8mb4',
                '--max-allowed-packet=1073741824',
                '--database='.$spec['database'],
            ],
            [0 => ['pipe', 'r'], 1 => ['file', (string) $spec['stderr_path'], 'ab'], 2 => ['file', (string) $spec['stderr_path'], 'ab']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (! is_resource($process)) {
            gzclose($gzip);
            throw new RuntimeException('Klien mysql tidak dapat dijalankan.');
        }

        $bytes = 0;
        try {
            $this->write($pipes[0], self::PRELUDE);
            while (! gzeof($gzip)) {
                $chunk = gzread($gzip, 4 * 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Part backup rusak saat dibaca.');
                }
                $bytes += strlen($chunk);
                $this->write($pipes[0], $chunk);
            }
            $this->write($pipes[0], "\nCOMMIT;\n");
        } finally {
            gzclose($gzip);
            if (is_resource($pipes[0])) {
                fclose($pipes[0]);
            }
            $exitCode = proc_close($process);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException('Klien mysql gagal memuat part (exit '.$exitCode.').');
        }

        return $bytes;
    }

    /** @param  resource  $pipe */
    private function write($pipe, string $data): void
    {
        $offset = 0;
        $length = strlen($data);
        while ($offset < $length) {
            $written = @fwrite($pipe, substr($data, $offset, 1048576));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Klien mysql berhenti menerima data.');
            }
            $offset += $written;
        }
    }

    private function tail(string $path): string
    {
        if (! is_file($path)) {
            return '';
        }

        $content = (string) file_get_contents($path, false, null, max(0, (int) filesize($path) - 4096));

        return substr(trim(preg_replace('/\s+/', ' ', $content) ?? $content), 0, 1000);
    }
}
