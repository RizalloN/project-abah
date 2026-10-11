<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup\ChunkedBackupPlanner;
use App\Services\DatabaseBackup\CompressedDatabaseDumpRunner;
use App\Services\DatabaseBackup\GzipSqlVerifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Worker process for one chunked-backup part. The parent writes a JSON spec,
 * this process dumps, verifies and reports back through a result file.
 */
class BackupDatabasePartCommand extends Command
{
    protected $signature = 'database:backup-part {spec : JSON spec written by database:backup-daily}';

    protected $description = 'Internal worker: dump and verify one part of the chunked daily backup';

    protected $hidden = true;

    public function handle(
        CompressedDatabaseDumpRunner $runner,
        GzipSqlVerifier $verifier,
        ChunkedBackupPlanner $planner
    ): int {
        set_time_limit(0);
        $spec = json_decode((string) @file_get_contents((string) $this->argument('spec')), true);
        if (! is_array($spec) || ! is_string($spec['result_path'] ?? null)) {
            $this->error('Spec part backup tidak valid.');

            return self::FAILURE;
        }

        $startedAt = microtime(true);
        try {
            $dump = $runner->run(
                $spec['command'],
                $spec['gzip_path'],
                $spec['stderr_path'],
                (int) $spec['compression_level']
            );
            $verified = $verifier->verify(
                $spec['gzip_path'],
                (int) $dump['uncompressed_bytes'],
                (string) $dump['uncompressed_sha256']
            );

            // A chunk is reusable only when its period still has the identity
            // that was planned after the dump finished.
            $stable = true;
            $recheck = $spec['recheck'] ?? null;
            if (is_array($recheck)) {
                $stable = hash_equals(
                    (string) $recheck['fingerprint'],
                    (string) $planner->periodFingerprint(
                        (string) $recheck['database'],
                        (string) $recheck['table'],
                        (string) $recheck['period_column'],
                        $recheck['period'] ?? null
                    )
                );
            }

            $result = [
                'status' => 'ok',
                'compressed_bytes' => (int) filesize($spec['gzip_path']),
                'uncompressed_bytes' => $verified['uncompressed_bytes'],
                'sha256_compressed' => $verified['compressed_sha256'],
                'sha256_uncompressed' => $verified['uncompressed_sha256'],
                'stable' => $stable,
                'stderr' => $this->stderrTail((string) $spec['stderr_path']),
                'seconds' => round(microtime(true) - $startedAt, 1),
            ];
        } catch (Throwable $exception) {
            $result = [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'stderr' => $this->stderrTail((string) $spec['stderr_path']),
            ];
        }

        @unlink((string) $spec['stderr_path']);
        file_put_contents($spec['result_path'], json_encode($result, JSON_UNESCAPED_SLASHES));

        return $result['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }

    private function stderrTail(string $path): string
    {
        if (! is_file($path) || (int) filesize($path) === 0) {
            return '';
        }

        $content = (string) file_get_contents($path, false, null, max(0, (int) filesize($path) - 4096));

        return substr(trim(preg_replace('/\s+/', ' ', $content) ?? $content), 0, 1000);
    }
}
