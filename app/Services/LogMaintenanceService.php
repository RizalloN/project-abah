<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LogMaintenanceService
{
    private const DEFAULT_MAX_BYTES = 33554432; // 32 MB
    private const DEFAULT_KEEP_ARCHIVES = 7;

    /**
     * @return array<string, mixed>
     */
    public function maintain(bool $dryRun = false): array
    {
        $logDir = storage_path('logs');
        $archiveDir = $logDir . DIRECTORY_SEPARATOR . 'archive';
        $maxBytes = max(10485760, (int) config('performance.log_maintenance.max_bytes', self::DEFAULT_MAX_BYTES));
        $keepArchives = max(1, (int) config('performance.log_maintenance.keep_archives', self::DEFAULT_KEEP_ARCHIVES));

        $summary = [
            'dry_run' => $dryRun,
            'max_bytes' => $maxBytes,
            'rotated' => [],
            'skipped' => [],
            'deleted_archives' => [],
        ];

        if (!is_dir($logDir)) {
            return $summary;
        }

        foreach (glob($logDir . DIRECTORY_SEPARATOR . '*.log') ?: [] as $path) {
            $size = is_file($path) ? (int) filesize($path) : 0;
            if ($size <= $maxBytes) {
                continue;
            }

            try {
                $archivePath = $this->archiveOversizedLog($path, $archiveDir, $dryRun);
                $summary['rotated'][] = [
                    'file' => basename($path),
                    'bytes' => $size,
                    'archive' => $archivePath ? basename($archivePath) : null,
                ];
            } catch (Throwable $e) {
                $summary['skipped'][] = [
                    'file' => basename($path),
                    'bytes' => $size,
                    'error' => $e->getMessage(),
                ];

                Log::warning('Log maintenance failed to rotate oversized log.', [
                    'file' => $path,
                    'bytes' => $size,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $summary['deleted_archives'] = $this->pruneArchives($archiveDir, $keepArchives, $dryRun);

        return $summary;
    }

    private function archiveOversizedLog(string $path, string $archiveDir, bool $dryRun): ?string
    {
        if ($dryRun) {
            return null;
        }

        if (!is_dir($archiveDir) && !mkdir($archiveDir, 0775, true) && !is_dir($archiveDir)) {
            throw new RuntimeException('Unable to create log archive directory.');
        }

        $archivePath = $archiveDir . DIRECTORY_SEPARATOR
            . pathinfo($path, PATHINFO_FILENAME)
            . '-' . now()->format('Ymd-His')
            . '.log.gz';

        $source = fopen($path, 'rb');
        if ($source === false) {
            throw new RuntimeException('Unable to open source log.');
        }

        $archive = gzopen($archivePath, 'wb6');
        if ($archive === false) {
            fclose($source);
            throw new RuntimeException('Unable to open archive log.');
        }

        try {
            while (!feof($source)) {
                $chunk = fread($source, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read source log.');
                }

                gzwrite($archive, $chunk);
            }
        } finally {
            fclose($source);
            gzclose($archive);
        }

        $truncate = fopen($path, 'cb');
        if ($truncate === false) {
            throw new RuntimeException('Unable to reopen log for truncation.');
        }

        try {
            if (!flock($truncate, LOCK_EX)) {
                throw new RuntimeException('Unable to lock log for truncation.');
            }

            ftruncate($truncate, 0);
            fflush($truncate);
            flock($truncate, LOCK_UN);
        } finally {
            fclose($truncate);
        }

        return $archivePath;
    }

    /**
     * @return array<int, string>
     */
    private function pruneArchives(string $archiveDir, int $keepArchives, bool $dryRun): array
    {
        if (!is_dir($archiveDir)) {
            return [];
        }

        $archives = glob($archiveDir . DIRECTORY_SEPARATOR . '*.log.gz') ?: [];
        usort($archives, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $deleted = [];
        foreach (array_slice($archives, $keepArchives) as $archive) {
            $deleted[] = basename($archive);
            if (!$dryRun) {
                @unlink($archive);
            }
        }

        return $deleted;
    }
}
