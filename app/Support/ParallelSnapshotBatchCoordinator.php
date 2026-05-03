<?php

namespace App\Support;

use App\Jobs\RebuildChartPeriodikPeriodJob;
use App\Jobs\RebuildDashboardPeriodJob;
use App\Jobs\RebuildDormantPeriodJob;
use App\Jobs\RebuildHarianPeriodJob;
use App\Jobs\RebuildLoanChartPeriodikSnapshotJob;
use App\Jobs\RebuildLoanDashboardSnapshotJob;
use App\Jobs\RebuildRasioPeriodJob;
use App\Jobs\RebuildSimpananPeriodJob;
use App\Jobs\RebuildSnapshotDormantBatch;
use App\Jobs\RebuildSnapshotHarianBatch;
use App\Jobs\RebuildSnapshotPerformanceRmBatch;
use App\Jobs\RebuildSnapshotRasioBatch;
use App\Jobs\RebuildSnapshotSimpleBatch;
use App\Jobs\WarmReportCacheJob;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Coordinates parallel execution of snapshot rebuild jobs via Bus::batch().
 *
 * Simpanan (5 jobs in parallel, ~8-10 min):
 *   Dashboard Simpanan, Dashboard Harian, Rekening Dormant, Rasio CASA, Performance RM
 *
 * Daily Loan (5 jobs in parallel, ~10-15 min):
 *   Dashboard Pinjaman, Dashboard Harian, Rasio CASA, Performance RM, Chart Periodik
 *
 * All jobs run on the 'snapshots-parallel' queue.
 * Workers must listen: php artisan queue:work --queue=snapshots-parallel,imports-high,default
 */
class ParallelSnapshotBatchCoordinator
{
    /**
     * Dispatch 5 Simpanan snapshot rebuild jobs to run in parallel.
     *
     * @param string $periodHint Period to rebuild (e.g., "202604")
     * @param string|null $deleteId Progress tracking ID for UI feedback
     * @param string|null $source Source of the rebuild trigger
     * @return string Batch ID for monitoring
     */
    public static function dispatchParallelRebuild(
        string $periodHint,
        ?string $deleteId = null,
        ?string $source = null
    ): string {
        Log::info('Dispatching parallel Simpanan snapshot rebuild batch', [
            'period' => $periodHint,
            'source' => $source,
            'jobs_count' => 5,
        ]);

        $jobs = [
            new RebuildSnapshotSimpleBatch($periodHint, $deleteId),
            new RebuildSnapshotHarianBatch($periodHint, $deleteId),
            new RebuildSnapshotDormantBatch($periodHint, $deleteId),
            new RebuildSnapshotPerformanceRmBatch($periodHint, $deleteId),
            new RebuildSnapshotRasioBatch($periodHint, $deleteId),
        ];

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function (Batch $batch) use ($periodHint, $source) {
                self::handleBatchSuccess($batch, $periodHint, $source);
                WarmReportCacheJob::dispatch();
            })
            ->catch(fn (Batch $batch, Throwable $e) => self::handleBatchFailure($batch, $periodHint, $e))
            ->finally(fn (Batch $batch) => self::handleBatchCompletion($batch, $periodHint))
            ->name('simpanan:' . $periodHint)
            ->onQueue('snapshots-parallel')
            ->dispatch();

        Log::info('Simpanan parallel snapshot batch dispatched', [
            'batch_id' => $batch->id,
            'period' => $periodHint,
        ]);

        return $batch->id;
    }

    /**
     * Dispatch 5 Daily Loan snapshot rebuild jobs to run in parallel.
     *
     * @param string|null $periodHint Period to rebuild (e.g., "202604"), null for global rebuild
     * @param string|null $deleteId Progress tracking ID for UI feedback
     * @param string|null $source Source of the rebuild trigger
     * @return string Batch ID for monitoring
     */
    public static function dispatchDailyLoanParallelRebuild(
        ?string $periodHint = null,
        ?string $deleteId = null,
        ?string $source = null
    ): string {
        Log::info('Dispatching parallel Daily Loan snapshot rebuild batch', [
            'period' => $periodHint,
            'source' => $source,
            'jobs_count' => 5,
        ]);

        $jobs = [
            new RebuildLoanDashboardSnapshotJob($periodHint, $deleteId),
            new RebuildSnapshotHarianBatch($periodHint, $deleteId),
            new RebuildLoanChartPeriodikSnapshotJob($periodHint, $deleteId),
            new RebuildSnapshotPerformanceRmBatch($periodHint, $deleteId),
            new RebuildSnapshotRasioBatch($periodHint, $deleteId),
        ];

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function (Batch $batch) use ($periodHint, $source) {
                self::handleBatchSuccess($batch, $periodHint, $source);
                WarmReportCacheJob::dispatch();
            })
            ->catch(fn (Batch $batch, Throwable $e) => self::handleBatchFailure($batch, $periodHint, $e))
            ->finally(fn (Batch $batch) => self::handleBatchCompletion($batch, $periodHint))
            ->name('daily_loan:' . $periodHint)
            ->onQueue('snapshots-parallel')
            ->dispatch();

        Log::info('Daily Loan parallel snapshot batch dispatched', [
            'batch_id' => $batch->id,
            'period' => $periodHint,
        ]);

        return $batch->id;
    }

    private static function handleBatchSuccess(Batch $batch, ?string $periodHint, ?string $source): void
    {
        $duration = $batch->createdAt->diffInSeconds(now());
        $total = $batch->totalJobs;
        $periodScope = $periodHint ?: 'all';

        Log::info('Parallel snapshot rebuild batch COMPLETED', [
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'period_scope' => $periodScope,
            'total_jobs' => $total,
            'duration_seconds' => $duration,
            'duration_formatted' => self::formatDuration($duration),
            'source' => $source ?? 'unknown',
        ]);
    }

    private static function handleBatchFailure(Batch $batch, ?string $periodHint, Throwable $e): void
    {
        $periodScope = $periodHint ?: 'all';

        Log::error('Parallel snapshot rebuild batch FAILED', [
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'period_scope' => $periodScope,
            'failed_jobs' => $batch->failedJobs,
            'total_jobs' => $batch->totalJobs,
            'error_message' => $e->getMessage(),
            'error_class' => $e::class,
        ]);
    }

    private static function handleBatchCompletion(Batch $batch, ?string $periodHint): void
    {
        $periodScope = $periodHint ?: 'all';

        $stats = [
            'batch_id' => $batch->id,
            'batch_name' => $batch->name,
            'period_scope' => $periodScope,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
        ];

        Log::info('Parallel snapshot rebuild batch COMPLETION CALLBACK', $stats);

        if ($batch->failedJobs > 0) {
            Log::warning('Parallel snapshot batch has failed jobs - manual intervention may be needed', $stats);
        }
    }

    /**
     * Dispatch individual period jobs for Simpanan snapshots (4x-6x faster with 4-6 workers).
     *
     * Instead of one batch job processing all periods sequentially, dispatch individual
     * jobs per period to the queue. With 4-6 workers, periods process in parallel.
     *
     * @param array<string> $periods Periods to rebuild (e.g., ["202604", "202605"])
     * @param string|null $deleteId Progress tracking ID
     * @param string|null $source Source of the rebuild trigger
     * @return string Batch ID
     */
    public static function dispatchParallelPeriodRebuild(
        array $periods,
        ?string $deleteId = null,
        ?string $source = null
    ): string {
        if (empty($periods)) {
            throw new \InvalidArgumentException('At least one period must be provided');
        }

        $jobsCount = count($periods) * 5; // 5 snapshots per period

        Log::info('Dispatching individual period jobs for parallel Simpanan snapshot rebuild', [
            'periods' => $periods,
            'source' => $source,
            'jobs_count' => $jobsCount,
            'workers_recommended' => 4,
        ]);

        $jobs = [];
        foreach ($periods as $period) {
            $jobs[] = new RebuildSimpananPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildHarianPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildDormantPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildRasioPeriodJob($period, true, $deleteId);
        }

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function (Batch $batch) use ($periods, $source) {
                self::handleBatchSuccess($batch, implode(',', $periods), $source);
                WarmReportCacheJob::dispatch();
            })
            ->catch(fn (Batch $batch, Throwable $e) => self::handleBatchFailure($batch, implode(',', $periods), $e))
            ->finally(fn (Batch $batch) => self::handleBatchCompletion($batch, implode(',', $periods)))
            ->name('simpanan-periods:' . implode('_', $periods))
            ->onQueue('snapshots-parallel')
            ->dispatch();

        Log::info('Individual period jobs dispatched', [
            'batch_id' => $batch->id,
            'periods' => $periods,
            'total_jobs' => $jobsCount,
        ]);

        return $batch->id;
    }

    /**
     * Dispatch individual period jobs for Daily Loan snapshots.
     *
     * @param array<string> $periods Periods to rebuild
     * @param string|null $deleteId Progress tracking ID
     * @param string|null $source Source of the rebuild trigger
     * @return string Batch ID
     */
    public static function dispatchDailyLoanParallelPeriodRebuild(
        array $periods,
        ?string $deleteId = null,
        ?string $source = null
    ): string {
        if (empty($periods)) {
            throw new \InvalidArgumentException('At least one period must be provided');
        }

        $jobsCount = count($periods) * 5; // 5 snapshots per period

        Log::info('Dispatching individual period jobs for parallel Daily Loan snapshot rebuild', [
            'periods' => $periods,
            'source' => $source,
            'jobs_count' => $jobsCount,
            'workers_recommended' => 4,
        ]);

        $jobs = [];
        foreach ($periods as $period) {
            $jobs[] = new RebuildDashboardPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildHarianPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildChartPeriodikPeriodJob($period, true, $deleteId);
            $jobs[] = new RebuildRasioPeriodJob($period, true, $deleteId);
        }

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function (Batch $batch) use ($periods, $source) {
                self::handleBatchSuccess($batch, implode(',', $periods), $source);
                WarmReportCacheJob::dispatch();
            })
            ->catch(fn (Batch $batch, Throwable $e) => self::handleBatchFailure($batch, implode(',', $periods), $e))
            ->finally(fn (Batch $batch) => self::handleBatchCompletion($batch, implode(',', $periods)))
            ->name('daily-loan-periods:' . implode('_', $periods))
            ->onQueue('snapshots-parallel')
            ->dispatch();

        Log::info('Individual Daily Loan period jobs dispatched', [
            'batch_id' => $batch->id,
            'periods' => $periods,
            'total_jobs' => $jobsCount,
        ]);

        return $batch->id;
    }

    /**
     * Monitor batch progress (call periodically from UI)
     */
    public static function getBatchProgress(string $batchId): array
    {
        try {
            $batch = Bus::findBatch($batchId);

            if (!$batch) {
                return [
                    'found' => false,
                    'batch_id' => $batchId,
                ];
            }

            $total = max($batch->totalJobs, 1);
            $completed = $total - $batch->pendingJobs - $batch->failedJobs;

            return [
                'found' => true,
                'batch_id' => $batchId,
                'batch_name' => $batch->name,
                'status' => $batch->failedJobs > 0 ? 'failed' : ($batch->pendingJobs > 0 ? 'processing' : 'completed'),
                'progress_percent' => round(($completed / $total) * 100),
                'completed_jobs' => $completed,
                'failed_jobs' => $batch->failedJobs,
                'total_jobs' => $total,
                'created_at' => $batch->createdAt?->toDateTimeString(),
                'finished_at' => $batch->finishedAt?->toDateTimeString(),
                'duration_seconds' => $batch->createdAt?->diffInSeconds(now() ?? $batch->finishedAt),
            ];
        } catch (Throwable $e) {
            Log::debug('Gagal mengambil batch progress', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            return [
                'found' => false,
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format duration into human-readable format
     */
    private static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "${seconds}s";
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($secs === 0) {
            return "${minutes}m";
        }

        return "${minutes}m ${secs}s";
    }
}
