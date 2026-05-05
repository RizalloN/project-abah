<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CacheMaintenanceService
{
    private const JOB_BATCH_KEEP_DAYS = 2;

    /**
     * @return array<string, mixed>
     */
    public function maintain(bool $dryRun = false): array
    {
        $summary = [
            'dry_run'                    => $dryRun,
            'file_cache_expired_pruned'  => 0,
            'file_sessions_pruned'       => 0,
            'db_sessions_pruned'         => 0,
            'job_batches_pruned'         => 0,
            'errors'                     => [],
        ];

        $summary['file_cache_expired_pruned'] = $this->pruneExpiredFileCache($dryRun, $summary['errors']);
        $summary['file_sessions_pruned']      = $this->pruneExpiredFileSessions($dryRun, $summary['errors']);
        $summary['db_sessions_pruned']        = $this->pruneOrphanedDbSessions($dryRun, $summary['errors']);
        $summary['job_batches_pruned']        = $this->pruneOldJobBatches($dryRun, $summary['errors']);

        return $summary;
    }

    /**
     * Laravel file cache format: first 10 bytes = Unix expiry timestamp (0 = forever).
     *
     * @param  array<int, string>  $errors
     */
    private function pruneExpiredFileCache(bool $dryRun, array &$errors): int
    {
        $cacheDir = storage_path('framework/cache/data');
        if (!is_dir($cacheDir)) {
            return 0;
        }

        $pruned = 0;
        $now    = time();

        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();

                try {
                    // Read only the 10-byte expiry header — no need to load full payload.
                    $header = @file_get_contents($path, false, null, 0, 10);
                    if ($header === false) {
                        continue;
                    }

                    $expire = (int) $header;

                    // expire = 0 means cache-forever; skip those.
                    if ($expire !== 0 && $now >= $expire) {
                        if (!$dryRun) {
                            @unlink($path);
                        }
                        $pruned++;
                    }
                } catch (Throwable) {
                    // Unreadable file — skip silently.
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'file_cache: ' . $e->getMessage();
        }

        return $pruned;
    }

    /**
     * Prune file sessions whose mtime is older than SESSION_LIFETIME.
     *
     * @param  array<int, string>  $errors
     */
    private function pruneExpiredFileSessions(bool $dryRun, array &$errors): int
    {
        if (config('session.driver') !== 'file') {
            return 0;
        }

        $sessDir = storage_path('framework/sessions');
        if (!is_dir($sessDir)) {
            return 0;
        }

        $lifetime = (int) config('session.lifetime', 120) * 60; // minutes → seconds
        $cutoff   = time() - $lifetime;
        $pruned   = 0;

        try {
            foreach (glob($sessDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }

                if ((@filemtime($path) ?: 0) < $cutoff) {
                    if (!$dryRun) {
                        @unlink($path);
                    }
                    $pruned++;
                }
            }
        } catch (Throwable $e) {
            $errors[] = 'file_sessions: ' . $e->getMessage();
        }

        return $pruned;
    }

    /**
     * Prune rows in the `sessions` DB table that are past SESSION_LIFETIME.
     * Covers orphaned rows left over from the database→file driver migration.
     *
     * @param  array<int, string>  $errors
     */
    private function pruneOrphanedDbSessions(bool $dryRun, array &$errors): int
    {
        try {
            if (!Schema::hasTable('sessions')) {
                return 0;
            }

            $lifetime = (int) config('session.lifetime', 120) * 60;
            $cutoff   = time() - $lifetime;

            if ($dryRun) {
                return (int) DB::table('sessions')->where('last_activity', '<', $cutoff)->count();
            }

            return (int) DB::table('sessions')->where('last_activity', '<', $cutoff)->delete();
        } catch (Throwable $e) {
            $errors[] = 'db_sessions: ' . $e->getMessage();

            return 0;
        }
    }

    /**
     * Prune finished job_batches older than JOB_BATCH_KEEP_DAYS days.
     *
     * @param  array<int, string>  $errors
     */
    private function pruneOldJobBatches(bool $dryRun, array &$errors): int
    {
        try {
            if (!Schema::hasTable('job_batches')) {
                return 0;
            }

            $cutoff = now()->subDays(self::JOB_BATCH_KEEP_DAYS)->timestamp;

            $query = DB::table('job_batches')
                ->whereNotNull('finished_at')
                ->where('finished_at', '<', $cutoff);

            if ($dryRun) {
                return (int) $query->count();
            }

            return (int) $query->delete();
        } catch (Throwable $e) {
            $errors[] = 'job_batches: ' . $e->getMessage();

            return 0;
        }
    }
}
