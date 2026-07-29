<?php

namespace App\Support;

use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SnapshotBatchAggregator
{
    private const BATCH_CACHE_PREFIX = 'snapshot:batch:';
    private const BATCH_LOCK_PREFIX = 'snapshot:batch:lock:';
    private const BATCH_METRICS_PREFIX = 'snapshot:batch:metrics:';
    private const BATCH_REGISTRY_KEY = 'snapshot:batch:active_keys';
    private const BATCH_REGISTRY_LOCK = 'snapshot:batch:active_keys:lock';

    // Use config values with fallback to constants
    private static ?array $config = null;

    public function registerSyncRequest(string $tableName, ?string $periodHint = null, ?int $jobId = null, ?string $source = null, ?string $rebuildId = null): array
    {
        $normalizedTable = strtolower(trim($tableName));
        $this->persistSyncRequest($normalizedTable, $periodHint);

        // Check if batching is globally disabled
        if (!SnapshotBatchConfig::ENABLED) {
            return ['batched' => false, 'reason' => 'batching_disabled'];
        }

        if ($normalizedTable === '') {
            return ['batched' => false, 'reason' => 'empty_table_name'];
        }

        // Check if this table should bypass batching
        if (SnapshotBatchConfig::shouldBypassBatching($normalizedTable)) {
            return ['batched' => false, 'reason' => 'table_bypass_batching'];
        }

        $batchKey = $this->resolveBatchKey($normalizedTable, $periodHint);
        $lockTimeout = SnapshotBatchConfig::BATCH_LOCK_SECONDS;
        $lock = Cache::lock(self::BATCH_LOCK_PREFIX . $batchKey, $lockTimeout);

        try {
            $lock->block(2, function () use ($batchKey, $normalizedTable, $periodHint, $jobId, $source, $rebuildId): void {
                $batch = $this->getBatch($batchKey);
                $isNew = $batch === null;

                if ($batch === null) {
                    $batch = $this->createEmptyBatch($batchKey, $normalizedTable, $periodHint);
                }

                $batch['requests'][] = [
                    'table_name' => $normalizedTable,
                    'period_hint' => $periodHint,
                    'job_id' => $jobId,
                    'source' => $source,
                    'rebuild_id' => $rebuildId,
                    'requested_at' => now()->toIso8601String(),
                ];
                $batch['requests'] = $this->compactRequests($batch['requests']);

                $batch['request_count'] = count($batch['requests']);
                $batch['last_updated_at'] = now()->toIso8601String();

                Cache::put(
                    self::BATCH_CACHE_PREFIX . $batchKey,
                    $batch,
                    now()->addSeconds(SnapshotBatchConfig::BATCH_TTL_SECONDS)
                );

                $this->rememberActiveBatchKey($batchKey);

                // Track metrics for monitoring
                $this->recordBatchMetric($batchKey, 'size', count($batch['requests']));

                if ($isNew) {
                    Log::info('Started new snapshot batch.', [
                        'batch_key' => $batchKey,
                        'table_name' => $normalizedTable,
                        'period_hint' => $periodHint,
                    ]);
                }
            });

            $batch = $this->getBatch($batchKey);
            $maxBatchSize = SnapshotBatchConfig::getEffectiveBatchSize();
            $shouldFlush = $batch !== null && (
                (int) ($batch['request_count'] ?? 0) >= $maxBatchSize ||
                $this->isTimeToFlush($batch)
            );

            if ($shouldFlush) {
                return $this->flushBatch($batchKey);
            }

            return [
                'batched' => true,
                'batch_key' => $batchKey,
                'batch_size' => (int) ($batch['request_count'] ?? 0),
                'max_batch_size' => $maxBatchSize,
                'will_auto_flush_at' => Carbon::parse($batch['first_requested_at'] ?? now())
                    ->addSeconds(SnapshotBatchConfig::getEffectiveAutoFlushTimeout())
                    ->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to batch snapshot sync request: ' . $e->getMessage(), [
                'batch_key' => $batchKey,
                'table_name' => $normalizedTable,
                'exception' => $e::class,
            ]);

            return ['batched' => false, 'reason' => 'lock_failed', 'error' => $e->getMessage()];
        }
    }

    private function persistSyncRequest(string $tableName, ?string $periodHint): void
    {
        $period = trim((string) $periodHint);
        if ($tableName === '' || $period === '') {
            return;
        }

        try {
            app(SnapshotDirtyPeriodService::class)->mark($tableName, $period);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist snapshot sync request.', [
                'table_name' => $tableName,
                'period_hint' => $period,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function flushDueBatches(): array
    {
        $flushed = [];

        try {
            foreach ($this->getActiveBatchKeys() as $batchKey) {
                $batch = $this->getBatch($batchKey);

                if ($batch === null) {
                    $this->forgetActiveBatchKey($batchKey);
                    continue;
                }

                if ($this->isTimeToFlush($batch)) {
                    $result = $this->flushBatch($batchKey);
                    $flushed[] = $result;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Error during batch auto-flush scan: ' . $e->getMessage());
        }

        return $flushed;
    }

    public function flushBatch(string $batchKey): array
    {
        $batch = $this->getBatch($batchKey);
        if ($batch === null) {
            return ['batched' => false, 'reason' => 'batch_not_found'];
        }

        $requests = $this->compactRequests((array) ($batch['requests'] ?? []));
        if (empty($requests)) {
            Cache::forget(self::BATCH_CACHE_PREFIX . $batchKey);
            $this->forgetActiveBatchKey($batchKey);

            return ['batched' => false, 'reason' => 'empty_batch'];
        }

        try {
            if ($this->hasActiveImportProcessing()) {
                Log::info('Snapshot batch flush ditunda karena import masih berjalan.', [
                    'batch_key' => $batchKey,
                    'request_count' => count($requests),
                ]);

                return [
                    'batched' => true,
                    'batch_key' => $batchKey,
                    'request_count' => count($requests),
                    'flushed' => false,
                    'reason' => 'import_active',
                ];
            }

            \App\Jobs\ExecuteBatchedSnapshotJob::dispatch($batchKey, $requests)
                ->onQueue((string) config('queue.report_queue', 'default'));

            Cache::forget(self::BATCH_CACHE_PREFIX . $batchKey);
            $this->forgetActiveBatchKey($batchKey);

            Log::info('Flushed snapshot batch to job queue.', [
                'batch_key' => $batchKey,
                'request_count' => count($requests),
            ]);

            return [
                'batched' => true,
                'batch_key' => $batchKey,
                'request_count' => count($requests),
                'flushed' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch batched snapshot job: ' . $e->getMessage(), [
                'batch_key' => $batchKey,
                'exception' => $e::class,
            ]);

            return ['batched' => false, 'reason' => 'dispatch_failed', 'error' => $e->getMessage()];
        }
    }

    public function resetActiveBatches(): int
    {
        $batchKeys = $this->getActiveBatchKeys();

        foreach ($batchKeys as $batchKey) {
            Cache::forget(self::BATCH_CACHE_PREFIX . $batchKey);
        }

        Cache::forget(self::BATCH_REGISTRY_KEY);

        return count($batchKeys);
    }

    public function resolveBatchKey(string $tableName, ?string $periodHint = null): string
    {
        $period = strtolower(trim((string) $periodHint));

        return strtolower(trim($tableName)) . ':' . ($period !== '' ? $period : '__all__');
    }

    private function createEmptyBatch(string $batchKey, string $tableName, ?string $periodHint): array
    {
        $now = now()->toIso8601String();

        return [
            'batch_key' => $batchKey,
            'table_name' => $tableName,
            'period_hint' => $periodHint,
            'first_requested_at' => $now,
            'last_updated_at' => $now,
            'request_count' => 0,
            'requests' => [],
        ];
    }

    private function getBatch(string $batchKey): ?array
    {
        $batch = Cache::get(self::BATCH_CACHE_PREFIX . $batchKey);

        return is_array($batch) ? $batch : null;
    }

    private function rememberActiveBatchKey(string $batchKey): void
    {
        $this->withRegistryLock(function () use ($batchKey): void {
            $keys = $this->getActiveBatchKeys();
            $keys[] = $batchKey;

            Cache::put(
                self::BATCH_REGISTRY_KEY,
                array_values(array_unique($keys)),
                now()->addSeconds(SnapshotBatchConfig::BATCH_TTL_SECONDS * 2)
            );
        });
    }

    private function forgetActiveBatchKey(string $batchKey): void
    {
        $this->withRegistryLock(function () use ($batchKey): void {
            $keys = array_values(array_filter(
                $this->getActiveBatchKeys(),
                fn (string $key) => $key !== $batchKey
            ));

            if ($keys === []) {
                Cache::forget(self::BATCH_REGISTRY_KEY);
                return;
            }

            Cache::put(
                self::BATCH_REGISTRY_KEY,
                $keys,
                now()->addSeconds(SnapshotBatchConfig::BATCH_TTL_SECONDS * 2)
            );
        });
    }

    private function getActiveBatchKeys(): array
    {
        $keys = Cache::get(self::BATCH_REGISTRY_KEY, []);

        if (!is_array($keys)) {
            return [];
        }

        return array_values(array_unique(array_filter($keys, 'is_string')));
    }

    private function withRegistryLock(callable $callback): void
    {
        try {
            Cache::lock(self::BATCH_REGISTRY_LOCK, SnapshotBatchConfig::BATCH_LOCK_SECONDS)
                ->block(2, $callback);
        } catch (\Throwable $e) {
            Log::debug('Snapshot batch registry lock failed: ' . $e->getMessage());
            $callback();
        }
    }

    private function isTimeToFlush(array $batch): bool
    {
        $firstRequestedAt = $batch['first_requested_at'] ?? null;
        if (!$firstRequestedAt) {
            return false;
        }

        try {
            $timeout = SnapshotBatchConfig::getEffectiveAutoFlushTimeout();
            $flushTime = Carbon::parse($firstRequestedAt)->addSeconds($timeout);

            return $flushTime->lessThanOrEqualTo(now());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record batch metrics for monitoring and analysis.
     */
    private function recordBatchMetric(string $batchKey, string $metric, mixed $value): void
    {
        try {
            $metricsKey = self::BATCH_METRICS_PREFIX . $batchKey;
            $metrics = Cache::get($metricsKey, []);
            if (!is_array($metrics)) {
                $metrics = [];
            }

            $metrics[$metric] = $value;
            $metrics['updated_at'] = now()->toIso8601String();

            Cache::put($metricsKey, $metrics, now()->addHours(1));
        } catch (\Throwable $e) {
            Log::debug('Failed to record batch metric: ' . $e->getMessage());
        }
    }

    /**
     * Keep only the newest request per table/period scope.
     *
     * Snapshot rebuilds are period scoped, so replaying multiple queued requests
     * for the same scope only repeats the same expensive database work.
     *
     * @param array<int, mixed> $requests
     * @return array<int, array<string, mixed>>
     */
    private function compactRequests(array $requests): array
    {
        $compacted = [];

        foreach ($requests as $request) {
            if (!is_array($request)) {
                continue;
            }

            $tableName = strtolower(trim((string) ($request['table_name'] ?? '')));
            if ($tableName === '') {
                continue;
            }

            $periodHint = trim((string) ($request['period_hint'] ?? ''));
            $rebuildId = trim((string) ($request['rebuild_id'] ?? ''));
            $scope = $tableName . ':' . ($periodHint !== '' ? $periodHint : '__all__');

            $compacted[$scope] = $request;
            $compacted[$scope]['table_name'] = $tableName;
            $compacted[$scope]['period_hint'] = $periodHint !== '' ? $periodHint : null;
            $compacted[$scope]['job_id'] = isset($request['job_id']) && (int) $request['job_id'] > 0 ? (int) $request['job_id'] : null;
            $compacted[$scope]['source'] = $request['source'] ?? null;
            $compacted[$scope]['rebuild_id'] = $rebuildId !== '' ? $rebuildId : null;
        }

        return array_values($compacted);
    }

    private function hasActiveImportProcessing(): bool
    {
        try {
            return app(ImportProgressService::class)->hasActiveProcessingJobs();
        } catch (\Throwable $e) {
            Log::debug('Failed to detect active import processing for snapshot batch flush.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get batch statistics for monitoring and debugging.
     */
    public function getBatchStats(string $batchKey): ?array
    {
        try {
            $metricsKey = self::BATCH_METRICS_PREFIX . $batchKey;
            $metrics = Cache::get($metricsKey);

            return is_array($metrics) ? $metrics : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get all active batches (for monitoring).
     */
    public function getActiveBatches(): array
    {
        $batches = [];

        try {
            foreach ($this->getActiveBatchKeys() as $batchKey) {
                $batch = $this->getBatch($batchKey);
                if (is_array($batch)) {
                    $batches[$batchKey] = $batch;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to get active batches: ' . $e->getMessage());
        }

        return $batches;
    }
}
