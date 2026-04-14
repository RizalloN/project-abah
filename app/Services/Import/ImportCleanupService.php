<?php

namespace App\Services\Import;

use App\Jobs\SyncImportedReportJob;
use App\Support\ReportDataSyncService;
use Illuminate\Support\Facades\Cache;

class ImportCleanupService
{
    private const SYNC_PENDING_TTL_MINUTES = 15;
    private const SYNC_COORDINATOR_LOCK_SECONDS = 5;
    private const DEFAULT_SYNC_QUEUE = 'reports-low';

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

        $resolvedQueue = $this->resolveSyncQueue($queue);
        $normalizedTableName = $this->normalizeSyncScopeValue($tableName);
        if ($normalizedTableName === null) {
            SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $tableName, $periodHint, $source)
                ->onQueue($resolvedQueue);
            return;
        }

        $pendingKey = $this->syncPendingKey($normalizedTableName, $periodHint);
        $rerunKey = $this->syncRerunKey($normalizedTableName, $periodHint);
        $lock = Cache::lock($this->syncCoordinatorLockKey($normalizedTableName, $periodHint), self::SYNC_COORDINATOR_LOCK_SECONDS);

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
                        : self::DEFAULT_SYNC_QUEUE;

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

    private function normalizeSyncScopeValue(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    private function resolveSyncQueue(?string $queue): string
    {
        $normalized = trim((string) $queue);

        return $normalized !== '' ? $normalized : self::DEFAULT_SYNC_QUEUE;
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
