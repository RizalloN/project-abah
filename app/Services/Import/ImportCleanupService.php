<?php

namespace App\Services\Import;

use App\Jobs\SyncImportedReportJob;
use App\Support\ReportDataSyncService;
use App\Support\SnapshotBatchAggregator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCleanupService
{
    private const SYNC_PENDING_TTL_MINUTES = 15;
    private const SYNC_COORDINATOR_LOCK_SECONDS = 5;
    private const DEFAULT_SYNC_QUEUE = 'default';
    private const DAILY_LOAN_SYNC_QUEUE = 'imports-daily-loan';
    private const DAILY_LOAN_TABLE = 'daily_loan_dinamis';
    private const DAILY_LOAN_REPORT_ID = 8;
    private const USE_BATCHING = true;

    private ?SnapshotBatchAggregator $batchAggregator = null;

    public function cleanupPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public function syncImportedJob(int $jobId, ?string $tableName = null, ?string $periodHint = null, ?string $source = null): void
    {
        app(ReportDataSyncService::class)->syncImportedJob($jobId, $tableName, $periodHint, $source);
    }

    public function dispatchImportedJobSync(int $jobId, ?string $tableName = null, ?string $periodHint = null, ?string $source = null, ?string $queue = null): void
    {
        if ($jobId <= 0 && (!$tableName || $tableName === '')) {
            return;
        }

        $normalizedTableName = $this->normalizeSyncScopeValue($tableName)
            ?? $this->resolveJobTableName($jobId);
        if ($normalizedTableName === null) {
            SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $tableName, $periodHint, $source)
                ->onQueue($this->resolveSyncQueue($queue, null, $jobId));
            return;
        }

        if ($normalizedTableName === self::DAILY_LOAN_TABLE) {
            $periodHints = $this->resolveSyncPeriodHints($jobId, $periodHint);

            foreach ($periodHints as $resolvedPeriodHint) {
                $this->dispatchWithoutBatching($jobId, $normalizedTableName, $resolvedPeriodHint, $source, $queue);
            }

            return;
        }

        if (self::USE_BATCHING) {
            $this->dispatchWithBatching($jobId, $tableName, $periodHint, $source);

            return;
        }

        $this->dispatchWithoutBatching($jobId, $tableName, $periodHint, $source, $queue);
    }

    private function dispatchWithBatching(int $jobId, ?string $tableName, ?string $periodHint, ?string $source): void
    {
        try {
            $aggregator = $this->getBatchAggregator();
            $result = $aggregator->registerSyncRequest(
                tableName: (string) $tableName,
                periodHint: $periodHint,
                jobId: $jobId > 0 ? $jobId : null,
                source: $source ?? static::class
            );

            if ($result['batched'] ?? false) {
                Log::debug('Snapshot sync request batched.', [
                    'batch_key' => $result['batch_key'] ?? null,
                    'batch_size' => $result['batch_size'] ?? 0,
                ]);

                return;
            }

            Log::warning('Failed to batch snapshot sync, falling back to direct dispatch.', [
                'table_name' => $tableName,
                'reason' => $result['reason'] ?? 'unknown',
            ]);

            $this->dispatchWithoutBatching($jobId, $tableName, $periodHint, $source, null);
        } catch (\Throwable $e) {
            Log::warning('Error during batching attempt, falling back to direct dispatch: ' . $e->getMessage(), [
                'table_name' => $tableName,
                'exception' => $e::class,
            ]);

            $this->dispatchWithoutBatching($jobId, $tableName, $periodHint, $source, null);
        }
    }

    private function dispatchWithoutBatching(int $jobId, ?string $tableName, ?string $periodHint, ?string $source, ?string $queue): void
    {
        $normalizedTableName = $this->normalizeSyncScopeValue($tableName)
            ?? $this->resolveJobTableName($jobId);
        $resolvedQueue = $this->resolveSyncQueue($queue, $normalizedTableName, $jobId);

        $pendingKey = $this->syncPendingKey((string) $normalizedTableName, $periodHint);
        $rerunKey = $this->syncRerunKey((string) $normalizedTableName, $periodHint);
        $lock = Cache::lock($this->syncCoordinatorLockKey((string) $normalizedTableName, $periodHint), self::SYNC_COORDINATOR_LOCK_SECONDS);

        try {
            $lock->block(2, function () use ($jobId, $tableName, $periodHint, $source, $pendingKey, $rerunKey, $resolvedQueue): void {
                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(self::SYNC_PENDING_TTL_MINUTES))) {
                    SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $tableName, $periodHint, $source)
                        ->onQueue($resolvedQueue);
                    return;
                }

                Cache::put($rerunKey, $resolvedQueue, now()->addMinutes(self::SYNC_PENDING_TTL_MINUTES));
            });
        } finally {
            optional($lock)->release();
        }
    }

    public function dispatchSnapshotRefresh(string $tableName, ?string $periodHint = null, ?string $source = null, ?string $queue = null): void
    {
        $normalizedTableName = $this->normalizeSyncScopeValue($tableName);
        if ($normalizedTableName === null) {
            return;
        }

        $this->dispatchImportedJobSync(0, $normalizedTableName, $periodHint, $source, $queue);
    }

    public function finalizeImportedJobSyncDispatch(int $jobId, ?string $tableName = null, ?string $periodHint = null, ?string $source = null): void
    {
        $normalizedTableName = $this->normalizeSyncScopeValue($tableName);
        if ($normalizedTableName === null) {
            return;
        }

        $pendingKey = $this->syncPendingKey($normalizedTableName, $periodHint);
        $rerunKey = $this->syncRerunKey($normalizedTableName, $periodHint);
        $lock = Cache::lock($this->syncCoordinatorLockKey($normalizedTableName, $periodHint), self::SYNC_COORDINATOR_LOCK_SECONDS);

        try {
            $lock->block(2, function () use ($jobId, $tableName, $periodHint, $source, $pendingKey, $rerunKey): void {
                $rerunQueue = Cache::pull($rerunKey);
                $shouldRerun = $rerunQueue !== null && $rerunQueue !== false;

                if ($shouldRerun) {
                    $resolvedQueue = is_string($rerunQueue) && trim($rerunQueue) !== ''
                        ? trim($rerunQueue)
                        : $this->resolveSyncQueue(null, $this->normalizeSyncScopeValue($tableName), $jobId);

                    SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $tableName, $periodHint, $source)
                        ->onQueue($resolvedQueue);
                    return;
                }

                Cache::forget($pendingKey);
            });
        } finally {
            optional($lock)->release();
        }
    }

    private function getBatchAggregator(): SnapshotBatchAggregator
    {
        return $this->batchAggregator ??= app(SnapshotBatchAggregator::class);
    }

    private function normalizeSyncScopeValue(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveSyncQueue(?string $queue, ?string $tableName = null, int $jobId = 0): string
    {
        $normalized = trim((string) $queue);
        $normalizedTableName = $this->normalizeSyncScopeValue($tableName) ?? $this->resolveJobTableName($jobId);

        if ($normalizedTableName === self::DAILY_LOAN_TABLE) {
            return self::DAILY_LOAN_SYNC_QUEUE;
        }

        return $normalized !== '' ? $normalized : (string) config('queue.report_queue', self::DEFAULT_SYNC_QUEUE);
    }

    private function resolveJobTableName(int $jobId): ?string
    {
        if ($jobId <= 0) {
            return null;
        }

        try {
            $job = DB::table('import_jobs')->where('id', $jobId)->first(['id_report', 'job_context']);
            if (!$job) {
                return null;
            }

            $context = json_decode((string) ($job->job_context ?? ''), true);
            $tableName = is_array($context)
                ? $this->normalizeSyncScopeValue((string) ($context['table_name'] ?? ''))
                : null;

            if ($tableName !== null) {
                return $tableName;
            }

            return (int) ($job->id_report ?? 0) === self::DAILY_LOAN_REPORT_ID
                ? self::DAILY_LOAN_TABLE
                : null;
        } catch (\Throwable $e) {
            Log::debug('Unable to resolve import job table for sync queue.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Daily Loan snapshot rebuilds are period-scoped. If the import job already
     * detected the source periods, preserve that scope instead of dispatching a
     * global sync job.
     *
     * @return array<int, string|null>
     */
    private function resolveSyncPeriodHints(int $jobId, ?string $periodHint): array
    {
        $normalizedPeriodHint = trim((string) $periodHint);
        if ($normalizedPeriodHint !== '') {
            return [$normalizedPeriodHint];
        }

        if ($jobId <= 0) {
            return [null];
        }

        try {
            $contextJson = DB::table('import_jobs')
                ->where('id', $jobId)
                ->value('job_context');
            $context = json_decode((string) $contextJson, true);

            if (!is_array($context)) {
                return [null];
            }

            $periods = $context['backend_detected_periods'] ?? $context['detected_periods'] ?? [];
            if (!is_array($periods)) {
                $periods = [$periods];
            }

            $normalized = [];
            foreach ($periods as $period) {
                $value = trim((string) $period);
                if ($value !== '') {
                    $normalized[$value] = $value;
                }
            }

            return $normalized !== [] ? array_values($normalized) : [null];
        } catch (\Throwable $e) {
            Log::debug('Unable to resolve import job periods for sync queue.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [null];
        }
    }

    private function normalizeSyncPeriodHint(?string $periodHint): string
    {
        $normalized = trim((string) $periodHint);

        return $normalized !== '' ? $normalized : '__all__';
    }

    private function syncScopeFragment(string $tableName, ?string $periodHint): string
    {
        return $tableName . ':' . $this->normalizeSyncPeriodHint($periodHint);
    }

    private function syncPendingKey(string $tableName, ?string $periodHint): string
    {
        return 'snapshot:sync:pending:' . $this->syncScopeFragment($tableName, $periodHint);
    }

    private function syncRerunKey(string $tableName, ?string $periodHint): string
    {
        return 'snapshot:sync:rerun:' . $this->syncScopeFragment($tableName, $periodHint);
    }

    private function syncCoordinatorLockKey(string $tableName, ?string $periodHint): string
    {
        return 'snapshot:sync:coord:' . $this->syncScopeFragment($tableName, $periodHint);
    }
}
