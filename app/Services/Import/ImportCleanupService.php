<?php

namespace App\Services\Import;

use App\Jobs\SyncImportedReportJob;
use App\Support\ReportDataSyncService;
use App\Support\SnapshotBatchAggregator;
use App\Support\StrictDateParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportCleanupService
{
    private const SYNC_PENDING_TTL_MINUTES = 15;
    private const SYNC_STALE_PENDING_SECONDS = 60;
    private const SYNC_COORDINATOR_LOCK_SECONDS = 5;
    private const DEFAULT_SYNC_QUEUE = 'default';
    private const DAILY_LOAN_SYNC_QUEUE = 'imports-high';
    private const DAILY_LOAN_TABLE = 'daily_loan_dinamis';
    private const DAILY_LOAN_REPORT_ID = 8;
    private const SSA_TABLES = ['ssa_simpanan', 'ssa_pinjaman'];
    private const IMMEDIATE_SYNC_TABLES = [
        self::DAILY_LOAN_TABLE,
        'ssa_simpanan',
        'ssa_pinjaman',
        'lw325_ph',
        'performance_pis_per_produk',
    ];
    private const LIGHTWEIGHT_SYNC_TABLES = [
        'jumlah_merchant_detail',
        'sv_merchant',
        'jumlah_merchant_qris_detail',
        'merchant_qris',
        'merchant_qris_volume',
    ];
    private const IMPORT_PERIOD_COLUMNS = [
        'ssa_pinjaman' => 'month_day_year_of_periode',
        'ssa_simpanan' => 'Month_Day_Year_of_Posisi',
        'lw325_ph' => 'periode',
    ];
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

        $periodHints = $this->resolveSyncPeriodHints($jobId, $periodHint, $normalizedTableName);

        if ($this->shouldDispatchSyncImmediately($normalizedTableName)
            || $this->isLightweightSyncTable($normalizedTableName)
        ) {
            foreach ($periodHints as $resolvedPeriodHint) {
                $this->dispatchWithoutBatching($jobId, $normalizedTableName, $resolvedPeriodHint, $source, $queue);
            }

            return;
        }

        if (self::USE_BATCHING) {
            foreach ($periodHints as $resolvedPeriodHint) {
                $this->dispatchWithBatching($jobId, $normalizedTableName, $resolvedPeriodHint, $source);
            }

            return;
        }

        foreach ($periodHints as $resolvedPeriodHint) {
            $this->dispatchWithoutBatching($jobId, $normalizedTableName, $resolvedPeriodHint, $source, $queue);
        }
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
        if ($normalizedTableName === null) {
            return;
        }

        $resolvedQueue = $this->resolveSyncQueue($queue, $normalizedTableName, $jobId);

        $pendingKey = $this->syncPendingKey($normalizedTableName, $periodHint);
        $rerunKey = $this->syncRerunKey($normalizedTableName, $periodHint);
        $lock = Cache::lock($this->syncCoordinatorLockKey($normalizedTableName, $periodHint), self::SYNC_COORDINATOR_LOCK_SECONDS);

        try {
            $lock->block(2, function () use ($jobId, $normalizedTableName, $periodHint, $source, $pendingKey, $rerunKey, $resolvedQueue): void {
                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(self::SYNC_PENDING_TTL_MINUTES))) {
                    SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $normalizedTableName, $periodHint, $source)
                        ->onQueue($resolvedQueue);
                    return;
                }

                $pendingSince = Cache::get($pendingKey);
                if ($this->isPendingMarkerStillFresh($pendingSince)) {
                    Cache::put($rerunKey, $resolvedQueue, now()->addMinutes(self::SYNC_PENDING_TTL_MINUTES));
                    return;
                }

                if (!$this->hasActiveQueuedSyncJob($normalizedTableName, $periodHint)) {
                    Cache::put($pendingKey, now()->toIso8601String(), now()->addMinutes(self::SYNC_PENDING_TTL_MINUTES));
                    Cache::forget($rerunKey);

                    SyncImportedReportJob::dispatch($jobId > 0 ? $jobId : null, $normalizedTableName, $periodHint, $source)
                        ->onQueue($resolvedQueue);

                    Log::warning('Recovered stale snapshot sync pending marker by dispatching a fresh job.', [
                        'table_name' => $normalizedTableName,
                        'period_hint' => $periodHint,
                        'job_id' => $jobId,
                        'queue' => $resolvedQueue,
                    ]);

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

    private function isLightweightSyncTable(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), self::LIGHTWEIGHT_SYNC_TABLES, true);
    }

    private function shouldDispatchSyncImmediately(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), self::IMMEDIATE_SYNC_TABLES, true);
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
    private function resolveSyncPeriodHints(int $jobId, ?string $periodHint, ?string $tableName = null): array
    {
        $normalizedPeriodHint = trim((string) $periodHint);
        if ($normalizedPeriodHint !== '') {
            return [$this->normalizeSnapshotPeriodHint($normalizedPeriodHint) ?? $normalizedPeriodHint];
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
                    $normalizedValue = $this->normalizeSnapshotPeriodHint($value) ?? $value;
                    $normalized[$normalizedValue] = $normalizedValue;
                }
            }

            if ($normalized !== []) {
                return array_values($normalized);
            }

            $contextTable = $this->normalizeSyncScopeValue((string) ($context['table_name'] ?? ''));
            $resolvedTable = $tableName ?? $contextTable;
            $resolvedFromSource = $this->resolveRecentlyImportedPeriods($jobId, $resolvedTable);

            return $resolvedFromSource !== [] ? $resolvedFromSource : [null];
        } catch (\Throwable $e) {
            Log::debug('Unable to resolve import job periods for sync queue.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $resolvedFromSource = $this->resolveRecentlyImportedPeriods($jobId, $tableName);

            return $resolvedFromSource !== [] ? $resolvedFromSource : [null];
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecentlyImportedPeriods(int $jobId, ?string $tableName): array
    {
        $normalizedTable = $this->normalizeSyncScopeValue($tableName);
        if ($jobId <= 0 || !isset(self::IMPORT_PERIOD_COLUMNS[$normalizedTable])) {
            return [];
        }

        $periodColumn = self::IMPORT_PERIOD_COLUMNS[$normalizedTable];

        try {
            $job = DB::table('import_jobs')
                ->where('id', $jobId)
                ->first(['created_at', 'updated_at']);

            if (!$job) {
                return $this->resolveLatestSourcePeriod($normalizedTable, $periodColumn);
            }

            $createdAt = \Carbon\Carbon::parse((string) $job->created_at)->subMinutes(5);
            $updatedAt = \Carbon\Carbon::parse((string) $job->updated_at)->addMinutes(10);

            $periods = DB::table($normalizedTable)
                ->whereBetween('updated_at', [$createdAt, $updatedAt])
                ->select($periodColumn)
                ->distinct()
                ->orderByDesc($periodColumn)
                ->pluck($periodColumn)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->map(fn (string $value) => $this->normalizeSnapshotPeriodHint($value) ?? $value)
                ->values()
                ->all();

            return $periods !== [] ? $periods : $this->resolveLatestSourcePeriod($normalizedTable, $periodColumn);
        } catch (\Throwable $e) {
            Log::debug('Unable to resolve import periods from source table.', [
                'job_id' => $jobId,
                'table_name' => $normalizedTable,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveLatestSourcePeriod(string $tableName, string $periodColumn): array
    {
        try {
            $latest = DB::table($tableName)->max($periodColumn);
            $normalized = $this->normalizeSnapshotPeriodHint((string) $latest)
                ?? trim((string) $latest);

            return $normalized !== '' ? [$normalized] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function normalizeSnapshotPeriodHint(?string $periodHint): ?string
    {
        $value = trim((string) $periodHint);
        if ($value === '') {
            return null;
        }

        return StrictDateParser::normalize($value) ?? $value;
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

    private function hasActiveQueuedSyncJob(string $tableName, ?string $periodHint): bool
    {
        try {
            $period = trim((string) $periodHint);
            $query = DB::table('jobs')
                ->where('payload', 'like', '%' . class_basename(SyncImportedReportJob::class) . '%')
                ->where('payload', 'like', '%' . $tableName . '%');

            if ($period !== '') {
                $query->where('payload', 'like', '%' . $period . '%');
            } else {
                $query->where('payload', 'like', '%periodHint%');
            }

            return $query->exists();
        } catch (\Throwable $e) {
            Log::debug('Unable to inspect active snapshot sync jobs.', [
                'table_name' => $tableName,
                'period_hint' => $periodHint,
                'message' => $e->getMessage(),
            ]);

            return true;
        }
    }

    private function isPendingMarkerStillFresh(mixed $pendingSince): bool
    {
        if (!is_string($pendingSince) || trim($pendingSince) === '') {
            return true;
        }

        try {
            return \Carbon\Carbon::parse($pendingSince)
                ->addSeconds(self::SYNC_STALE_PENDING_SECONDS)
                ->greaterThan(now());
        } catch (\Throwable) {
            return true;
        }
    }
}
