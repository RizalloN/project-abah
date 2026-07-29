<?php

namespace App\Console\Commands;

use App\Services\Import\ImportProgressService;
use App\Support\SnapshotDirtyPeriodService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportHealthCheckCommand extends Command
{
    protected $signature = 'import:health-check
        {--fix : Repair stale metadata and auto-terminate stuck jobs}
        {--retry-dead-letters : Requeue unresolved snapshot dirty-period dead letters}
        {--hours=2 : Hours threshold for stuck detection}';

    protected $description = 'Health check for stuck imports, snapshot queues, dead letters, and slow queries';

    public function handle(ImportProgressService $progressService, SnapshotDirtyPeriodService $dirtyPeriods): int
    {
        try {
            $hoursThreshold = max(1, (int) $this->option('hours'));
            $shouldFix = (bool) $this->option('fix');
            $shouldRetryDeadLetters = (bool) $this->option('retry-dead-letters');

            $this->info('Running import health check...');
            $this->reconcileProcessingJobs($progressService);

            $deadLetterRecovery = ($shouldFix || $shouldRetryDeadLetters)
                ? $dirtyPeriods->recoverFailed(25, $shouldRetryDeadLetters)
                : ['pruned' => 0, 'retried' => 0, 'unresolved' => 0];

            $stuckJobs = $this->findStuckImportJobs($hoursThreshold);
            $deferredSnapshots = $this->countDeferredSnapshots();
            $failedSnapshotPeriods = $this->countFailedSnapshotDirtyPeriods();

            $this->displayStatus($stuckJobs, $deferredSnapshots, $failedSnapshotPeriods, $hoursThreshold);

            if ($deadLetterRecovery['pruned'] > 0 || $deadLetterRecovery['retried'] > 0) {
                $this->line(sprintf(
                    'Snapshot dead-letter recovery: %d stale marker(s) pruned, %d unresolved item(s) requeued.',
                    $deadLetterRecovery['pruned'],
                    $deadLetterRecovery['retried']
                ));
            }

            if ($shouldFix && !empty($stuckJobs)) {
                $this->fixStuckJobs($stuckJobs);
            }

            $this->info('Checking for slow database queries...');
            $killedQueries = $this->killSlowQueries($shouldFix);
            if (!empty($killedQueries)) {
                $message = $shouldFix
                    ? 'Auto-terminated '.count($killedQueries).' stuck database query/queries.'
                    : 'Detected '.count($killedQueries).' long-running database query/queries; rerun with --fix to terminate them.';
                $this->line("<fg=yellow>WARNING: {$message}</>");
            } else {
                $this->line('<fg=green>✓ No stuck database queries detected.</>');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            Log::error('ImportHealthCheckCommand failed', ['exception' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    private function reconcileProcessingJobs(ImportProgressService $progressService): void
    {
        $jobIds = DB::table('import_jobs')
            ->where('status', 'processing')
            ->orderBy('updated_at')
            ->limit(100)
            ->pluck('id');

        foreach ($jobIds as $jobId) {
            $progressService->getStatusPayload((int) $jobId);
        }
    }

    private function findStuckImportJobs(int $hoursThreshold): array
    {
        $cutoff = now()->subHours($hoursThreshold);

        return DB::table('import_jobs')
            ->where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'id_report',
                'file_name',
                'status',
                'total_success',
                'total_failed',
                'updated_at',
                'created_at',
            ])
            ->toArray();
    }

    private function countDeferredSnapshots(): int
    {
        try {
            return DB::table('jobs')
                ->where('queue', 'snapshots-parallel')
                ->whereNull('reserved_at')
                ->where('payload', 'like', '%ExecuteBatchedSnapshotJob%')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countFailedSnapshotDirtyPeriods(): int
    {
        try {
            if (! Schema::hasTable('failed_snapshot_dirty_periods')) {
                return 0;
            }

            return (int) DB::table('failed_snapshot_dirty_periods')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function displayStatus(array $stuckJobs, int $deferredCount, int $failedSnapshotPeriods, int $threshold): void
    {
        $this->line("\n<fg=cyan>=== IMPORT HEALTH STATUS ===</>");

        if (empty($stuckJobs)) {
            $this->line('<fg=green>✓ No stuck import jobs detected.</>');
        } else {
            $this->line("<fg=red>✗ Found " . count($stuckJobs) . " stuck import job(s) (threshold: {$threshold}h)</>");
            $this->table(
                ['Job ID', 'Report ID', 'File', 'Status', 'Stuck Since', 'Success', 'Failed'],
                array_map(function ($job) {
                    $stuckSince = Carbon::parse($job->updated_at)->diffForHumans();
                    return [
                        $job->id,
                        $job->id_report,
                        substr($job->file_name, 0, 20),
                        $job->status,
                        $stuckSince,
                        $job->total_success,
                        $job->total_failed,
                    ];
                }, $stuckJobs)
            );
        }

        $this->line("\n<fg=cyan>SNAPSHOT QUEUE STATUS</>");
        if ($deferredCount === 0) {
            $this->line('<fg=green>✓ No deferred snapshot jobs pending.</>');
        } else {
            $this->line("<fg=yellow>⚠ {$deferredCount} snapshot rebuild job(s) waiting in queue (deferred)</>");
        }

        if ($failedSnapshotPeriods === 0) {
            $this->line('<fg=green>✓ No snapshot dirty-period dead letters.</>');
        } else {
            $this->line("<fg=yellow>WARNING: {$failedSnapshotPeriods} snapshot dirty-period item(s) require review in failed_snapshot_dirty_periods.</>");
        }

        if (!empty($stuckJobs) && $deferredCount > 0) {
            $this->line("\n<fg=red>⚠ WARNING: Stuck import job is blocking snapshot rebuilds!</>");
            $this->line('  → Run with --fix flag to auto-terminate stuck jobs and resume snapshots');
        }
    }

    private function fixStuckJobs(array $stuckJobs): void
    {
        $this->line("\n<fg=yellow>Attempting to fix stuck jobs...</>");

        foreach ($stuckJobs as $job) {
            try {
                DB::table('import_jobs')
                    ->where('id', $job->id)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                Log::warning('Auto-terminated stuck import job', [
                    'job_id' => $job->id,
                    'report_id' => $job->id_report,
                    'file' => $job->file_name,
                    'stuck_duration_hours' => now()->diffInHours(Carbon::parse($job->updated_at)),
                ]);

                $this->line("  ✓ Marked job #{$job->id} as failed");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to fix job #{$job->id}: " . $e->getMessage());
            }
        }

        $this->line("\n<fg=green>Snapshot queue should resume processing within 60 seconds.</>");
    }

    /**
     * Inspect MySQL processlist and terminate stuck queries running longer than 60 seconds.
     */
    private function killSlowQueries(bool $shouldFix): array
    {
        $killed = [];
        try {
            $processes = DB::select("SHOW FULL PROCESSLIST");
            foreach ($processes as $p) {
                $queryInfo = strtolower((string) ($p->Info ?? ''));
                $queryState = strtolower((string) ($p->State ?? ''));
                $runningSeconds = (int) ($p->Time ?? 0);
                $lockWaitThreshold = max(60, (int) config('import.health.lock_wait_kill_seconds', 300));
                $genericThreshold = max($lockWaitThreshold, (int) config('import.health.generic_query_kill_seconds', 3600));
                $isLockWait = str_contains($queryState, 'lock') || str_contains($queryState, 'waiting');
                $isPastSafeThreshold = ($isLockWait && $runningSeconds > $lockWaitThreshold)
                    || $runningSeconds > $genericThreshold;

                if (
                    isset($p->Id, $p->Time, $p->Command, $p->Info) &&
                    in_array($p->Command, ['Query', 'Execute'], true) &&
                    $isPastSafeThreshold &&
                    !$this->shouldIgnoreSlowQuery($queryInfo)
                ) {
                    if ($shouldFix) {
                        DB::statement("KILL {$p->Id}");
                        Log::warning("Auto-killed stuck database query to prevent lockup", [
                            'process_id' => $p->Id,
                            'time_seconds' => $p->Time,
                            'query_info' => substr($p->Info, 0, 500),
                        ]);
                        $killed[] = $p->Id;
                    } else {
                        $this->line("<fg=yellow>⚠ Detected slow query (ID: {$p->Id}, Time: {$p->Time}s): " . substr($p->Info, 0, 100) . "...</>");
                        $killed[] = $p->Id;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to check or kill slow database queries: " . $e->getMessage());
        }

        return $killed;
    }

    private function shouldIgnoreSlowQuery(string $queryInfo): bool
    {
        if ($queryInfo === '') {
            return true;
        }

        if (str_contains($queryInfo, 'alter table')
            || str_contains($queryInfo, 'create index')
            || str_contains($queryInfo, 'show full processlist')) {
            return true;
        }

        if (str_contains($queryInfo, 'load data')) {
            return true;
        }

        if (str_contains($queryInfo, '_snapshots')
            || str_contains($queryInfo, 'snapshot_dirty_periods')
            || str_contains($queryInfo, 'snapshot_source_signatures')) {
            return true;
        }

        return str_contains($queryInfo, 'simpanan_multipn')
            && (
                str_contains($queryInfo, 'delete from')
                || str_contains($queryInfo, 'insert into')
                || str_contains($queryInfo, 'update ')
            );
    }
}
