<?php

namespace App\Support;

use App\Jobs\RebuildSnapshotDormantBatch;
use App\Jobs\RebuildSnapshotHarianBatch;
use App\Jobs\RebuildSnapshotRasioBatch;
use App\Jobs\RebuildSnapshotSimpleBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coordinates parallel execution of 4 snapshot rebuild jobs
 *
 * Instead of sequential rebuild (40+ minutes):
 *   Dashboard Simpanan (5-10 min)
 *   └─ Dashboard Harian (5-10 min)
 *   └─ Rekening Dormant (5-10 min)
 *   └─ Rasio CASA (5-10 min)
 *   = 40+ minutes total
 *
 * Parallel rebuild (8-10 minutes):
 *   Dashboard Simpanan (5-10 min) ┐
 *   Dashboard Harian (5-10 min)   ├─ All 4 run simultaneously
 *   Rekening Dormant (5-10 min)   │
 *   Rasio CASA (5-10 min)         ┘
 *   = 8-10 minutes total (80% reduction!)
 *
 * Usage:
 *   ParallelSnapshotBatchCoordinator::dispatchParallelRebuild($periodHint, $deleteId, $source);
 */
class ParallelSnapshotBatchCoordinator
{
    /**
     * Dispatch 4 snapshot rebuild jobs to run in parallel
     *
     * @param string $periodHint Period to rebuild (e.g., "202604")
     * @param string|null $deleteId Progress tracking ID for UI feedback
     * @param string|null $source Source of the rebuild trigger (e.g., "import", "manual")
     * @return string Batch ID for monitoring
     */
    public static function dispatchParallelRebuild(
        string $periodHint,
        ?string $deleteId = null,
        ?string $source = null
    ): string {
        Log::info('Dispatching parallel snapshot rebuild batch', [
            'period' => $periodHint,
            'delete_id' => $deleteId,
            'source' => $source,
            'jobs_count' => 4,
            'expected_duration_minutes' => '8-10',
        ]);

        $jobs = [
            new RebuildSnapshotSimpleBatch($periodHint, $deleteId),
            new RebuildSnapshotHarianBatch($periodHint, $deleteId),
            new RebuildSnapshotDormantBatch($periodHint, $deleteId),
            new RebuildSnapshotRasioBatch($periodHint, $deleteId),
        ];

        $batchId = Bus::batch($jobs)
            ->then(function (Batch $batch) use ($periodHint, $source) {
                self::handleBatchSuccess($batch, $periodHint, $source);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($periodHint) {
                self::handleBatchFailure($batch, $periodHint, $e);
            })
            ->finally(function (Batch $batch) use ($periodHint) {
                self::handleBatchCompletion($batch, $periodHint);
            })
            ->onQueue('snapshots-parallel')
            ->dispatch();

        Log::info('Parallel snapshot rebuild batch dispatched', [
            'batch_id' => $batchId,
            'period' => $periodHint,
        ]);

        return $batchId;
    }

    /**
     * Called when all 4 jobs complete successfully
     */
    private static function handleBatchSuccess(Batch $batch, string $periodHint, ?string $source): void
    {
        $duration = $batch->createdAt->diffInSeconds(now());

        Log::info('✓ Parallel snapshot rebuild batch COMPLETED', [
            'batch_id' => $batch->id,
            'period' => $periodHint,
            'total_jobs' => 4,
            'duration_seconds' => $duration,
            'duration_formatted' => self::formatDuration($duration),
            'source' => $source ?? 'unknown',
            'throughput' => round(4 / max($duration, 1), 2) . ' jobs/sec',
        ]);
    }

    /**
     * Called if any job in the batch fails
     */
    private static function handleBatchFailure(Batch $batch, string $periodHint, Throwable $e): void
    {
        Log::error('✗ Parallel snapshot rebuild batch FAILED', [
            'batch_id' => $batch->id,
            'period' => $periodHint,
            'failed_jobs' => $batch->failedJobs,
            'total_jobs' => 4,
            'error_message' => $e->getMessage(),
            'error_class' => $e::class,
        ]);
    }

    /**
     * Called when batch completes (success or failure)
     */
    private static function handleBatchCompletion(Batch $batch, string $periodHint): void
    {
        $stats = [
            'batch_id' => $batch->id,
            'period' => $periodHint,
            'total_jobs' => 4,
            'pending_jobs' => $batch->pendingJobs,
            'failed_jobs' => $batch->failedJobs,
            'processed_jobs' => 4 - $batch->pendingJobs - $batch->failedJobs,
        ];

        Log::info('Parallel snapshot rebuild batch COMPLETION CALLBACK', $stats);

        // Optional: Send notification to admins if any failures
        if ($batch->failedJobs > 0) {
            Log::warning('Parallel snapshot batch has failed jobs - manual intervention may be needed', $stats);
        }
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

            return [
                'found' => true,
                'batch_id' => $batchId,
                'status' => $batch->failedJobs > 0 ? 'failed' : ($batch->pendingJobs > 0 ? 'processing' : 'completed'),
                'progress_percent' => round(((4 - $batch->pendingJobs) / 4) * 100),
                'completed_jobs' => 4 - $batch->pendingJobs - $batch->failedJobs,
                'failed_jobs' => $batch->failedJobs,
                'total_jobs' => 4,
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
