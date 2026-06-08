<?php

namespace App\Services;

use App\Http\Controllers\Import\ImportIndexController;
use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use App\Jobs\EnsureDashboardSnapshotJob;
use App\Jobs\EnsureRasioCasaSnapshotJob;
use App\Jobs\EnsureRekeningDormantSnapshotJob;
use App\Jobs\RunManagedReportDeleteJob;
use App\Jobs\RunManagedReportLoadJob;
use App\Jobs\RunManagedReportSnapshotRebuildJob;
use App\Jobs\SyncImportedReportJob;
use App\Jobs\WarmReportCacheJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Support\ManagedReportLoadCoordinator;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JobHealthService
{
    private const SWEEP_LOCK_KEY = 'job_health:sweep:lock';
    private const SWEEP_LAST_RUN_KEY = 'job_health:sweep:last_run';
    private const SWEEP_INTERVAL_SECONDS = 60;
    private const MANAGED_QUEUE_STALE_SECONDS = 15 * 60;
    private const REPORT_QUEUE_STALE_SECONDS = 20 * 60;

    public function __construct(
        private readonly ImportProgressService $importProgressService,
        private readonly ImportExecutionService $importExecutionService,
        private readonly ManagedReportLoadCoordinator $managedReportLoadCoordinator,
        private readonly ManagedReportSnapshotRebuildCoordinator $managedReportSnapshotRebuildCoordinator,
        private readonly ImportIndexController $importIndexController
    ) {
    }

    public function sweepIfDue(): void
    {
        $this->sweep(false);
    }

    public function sweepNow(): array
    {
        return $this->sweep(true);
    }

    private function sweep(bool $force): array
    {
        if (!$force) {
            $lastRunAt = Cache::get(self::SWEEP_LAST_RUN_KEY);
            if (is_string($lastRunAt) && trim($lastRunAt) !== '') {
                try {
                    if (now()->diffInSeconds($lastRunAt) < self::SWEEP_INTERVAL_SECONDS) {
                        return [];
                    }
                } catch (\Throwable) {
                }
            }
        }

        $lock = Cache::lock(self::SWEEP_LOCK_KEY, 55);
        if (!$lock->get()) {
            return [];
        }

        try {
            $recoveredImportIds = $this->importExecutionService->recoverOrphanedZeroProgressJobs();
            $queuedImports = $this->importProgressService->purgeStaleQueuedJobs();
            $processingImports = $this->importProgressService->purgeStaleProcessingJobs();
            $managedLoads = $this->managedReportLoadCoordinator->sweepStaleStates();
            $managedRebuilds = $this->managedReportSnapshotRebuildCoordinator->sweepStaleStates();
            $managedDeletes = $this->importIndexController->sweepManagedReportDeleteStates();
            $purgedQueueRows = $this->purgeStaleQueueRows();

            Cache::put(self::SWEEP_LAST_RUN_KEY, now()->toIso8601String(), now()->addMinutes(10));

            $summary = [
                'orphaned_imports_requeued' => count($recoveredImportIds),
                'orphaned_import_job_ids' => $recoveredImportIds,
                'stale_imports_queued' => $queuedImports,
                'stale_imports_processing' => $processingImports,
                'managed_loads_reconciled' => $managedLoads,
                'managed_rebuilds_reconciled' => $managedRebuilds,
                'managed_deletes_reconciled' => $managedDeletes,
                'purged_queue_rows' => $purgedQueueRows,
            ];

            $purgedQueueRowCount = array_sum($purgedQueueRows);

            if ((count($recoveredImportIds) + $queuedImports + $processingImports + $managedLoads + $managedRebuilds + $managedDeletes + $purgedQueueRowCount) > 0) {
                Log::info('Job health sweep dijalankan.', $summary);
            }

            return $summary;
        } finally {
            optional($lock)->release();
        }
    }

    private function purgeStaleQueueRows(): array
    {
        $reportQueue = trim((string) config('queue.report_queue', 'default')) ?: 'default';

        return [
            // Database queue retry_after owns pending/reserved import rows. Removing
            // them here can race a legitimate long-running import.
            'imports' => 0,
            'reserved_imports' => 0,
            'managed_imports' => $this->deletePendingQueueRows(
                ['imports-high', 'reports-low', 'default'],
                [RunManagedReportLoadJob::class, RunManagedReportDeleteJob::class],
                self::MANAGED_QUEUE_STALE_SECONDS
            ),
            'reports_low' => $this->deletePendingQueueRows(
                ['reports-low'],
                [
                    SyncImportedReportJob::class,
                    WarmReportCacheJob::class,
                    EnsureDashboardSnapshotJob::class,
                    EnsureDashboardSimpananSnapshotJob::class,
                    EnsureRasioCasaSnapshotJob::class,
                    EnsureRekeningDormantSnapshotJob::class,
                    RunManagedReportSnapshotRebuildJob::class,
                ],
                self::REPORT_QUEUE_STALE_SECONDS
            ),
            'configured_report_queue' => $this->deletePendingQueueRows(
                [$reportQueue],
                [
                    SyncImportedReportJob::class,
                    WarmReportCacheJob::class,
                    EnsureDashboardSnapshotJob::class,
                    EnsureDashboardSimpananSnapshotJob::class,
                    EnsureRasioCasaSnapshotJob::class,
                    EnsureRekeningDormantSnapshotJob::class,
                    RunManagedReportSnapshotRebuildJob::class,
                ],
                self::REPORT_QUEUE_STALE_SECONDS
            ),
            'reserved_reports' => $this->deleteReservedQueueRows(
                [$reportQueue, 'reports-low'],
                [
                    SyncImportedReportJob::class,
                    WarmReportCacheJob::class,
                    EnsureDashboardSnapshotJob::class,
                    EnsureDashboardSimpananSnapshotJob::class,
                    EnsureRasioCasaSnapshotJob::class,
                    EnsureRekeningDormantSnapshotJob::class,
                ],
                self::REPORT_QUEUE_STALE_SECONDS
            ),
        ];
    }

    private function deletePendingQueueRows(array $queues, array $jobClasses, int $olderThanSeconds): int
    {
        if ($queues === [] || $jobClasses === []) {
            return 0;
        }

        try {
            $threshold = now()->subSeconds(max(1, $olderThanSeconds))->timestamp;

            return DB::table('jobs')
                ->whereNull('reserved_at')
                ->whereIn('queue', $queues)
                // Use available_at: reflects the last time the job became runnable (initial queue or after retry backoff)
                ->where('available_at', '<=', $threshold)
                ->where(function ($query) use ($jobClasses): void {
                    foreach ($jobClasses as $jobClass) {
                        // Match full qualified class name in JSON payload for precise, collision-free detection
                        $escapedClass = str_replace('\\', '\\\\', $jobClass);
                        $query->orWhere('payload', 'like', '%"' . $escapedClass . '"%');
                    }
                })
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Gagal membersihkan row jobs stale dari queue monitor: ' . $e->getMessage(), [
                'queues' => $queues,
                'job_classes' => array_map(static fn (string $class): string => class_basename($class), $jobClasses),
                'older_than_seconds' => $olderThanSeconds,
            ]);

            return 0;
        }
    }

    private function deleteReservedQueueRows(array $queues, array $jobClasses, int $olderThanSeconds): int
    {
        if ($queues === [] || $jobClasses === []) {
            return 0;
        }

        try {
            $threshold = now()->subSeconds(max(1, $olderThanSeconds))->timestamp;

            return DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->whereIn('queue', $queues)
                ->where('reserved_at', '<=', $threshold)
                ->where(function ($query) use ($jobClasses): void {
                    foreach ($jobClasses as $jobClass) {
                        $escapedClass = str_replace('\\', '\\\\', $jobClass);
                        $query->orWhere('payload', 'like', '%"' . $escapedClass . '"%');
                    }
                })
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Gagal membersihkan reserved queue rows stale: ' . $e->getMessage(), [
                'queues' => $queues,
                'job_classes' => array_map(static fn (string $class): string => class_basename($class), $jobClasses),
                'older_than_seconds' => $olderThanSeconds,
            ]);

            return 0;
        }
    }
}
