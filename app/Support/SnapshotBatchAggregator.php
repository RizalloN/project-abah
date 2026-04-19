<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SnapshotBatchAggregator
{
    private const BATCH_CACHE_PREFIX = 'snapshot:batch:';
    private const BATCH_LOCK_PREFIX = 'snapshot:batch:lock:';
    private const BATCH_METRICS_PREFIX = 'snapshot:batch:metrics:';

    // Use config values with fallback to constants
    private static ?array $config = null;

    public function registerSyncRequest(string $tableName, ?string $periodHint = null, ?int $jobId = null, ?string $source = null, ?string $rebuildId = null): array
    {
        // Check if batching is globally disabled
        if (!SnapshotBatchConfig::ENABLED) {
            return ['batched' => false, 'reason' => 'batching_disabled'];
        }

        $normalizedTable = strtolower(trim($tableName));
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

                $batch['request_count'] = count($batch['requests']);
                $batch['last_updated_at'] = now()->toIso8601String();

                Cache::put(
                    self::BATCH_CACHE_PREFIX . $batchKey,
                    $batch,
                    now()->addSeconds(SnapshotBatchConfig::BATCH_TTL_SECONDS)
                );

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

    public function flushDueBatches(): array
    {
        $flushed = [];

        try {
            $pattern = self::BATCH_CACHE_PREFIX . '*';
            if (function_exists('apcu_delete') && ini_get('apc.enabled')) {
                foreach (apcu_cache_info() as $entry) {
                    $key = $entry['key'] ?? null;
                    if (is_string($key) && str_starts_with($key, self::BATCH_CACHE_PREFIX)) {
                        $batch = Cache::get($key);
                        if ($batch !== null && $this->isTimeToFlush($batch)) {
                            $batchKey = substr($key, strlen(self::BATCH_CACHE_PREFIX));
                            $result = $this->flushBatch($batchKey);
                            $flushed[] = $result;
                        }
                    }
                }
            } else {
                Log::debug('APCu not available for batch cache enumeration, skipping auto-flush scan.');
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

        $requests = (array) ($batch['requests'] ?? []);
        if (empty($requests)) {
            Cache::forget(self::BATCH_CACHE_PREFIX . $batchKey);

            return ['batched' => false, 'reason' => 'empty_batch'];
        }

        try {
            \App\Jobs\ExecuteBatchedSnapshotJob::dispatch($batchKey, $requests)
                ->onQueue((string) config('queue.report_queue', 'default'));

            Cache::forget(self::BATCH_CACHE_PREFIX . $batchKey);

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
}
