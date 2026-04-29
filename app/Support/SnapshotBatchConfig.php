<?php

namespace App\Support;

/**
 * Centralized configuration for snapshot batching system.
 * Adjust these constants based on your import volume and performance requirements.
 */
class SnapshotBatchConfig
{
    /**
     * Cache time-to-live for batches (in seconds).
     * Shorter = fresher data, more immediate dispatches
     * Longer = better batching, reduced job queue size
     * Typical: 10-20 seconds
     */
    public const BATCH_TTL_SECONDS = 15;

    /**
     * Lock timeout for batch coordination (in seconds).
     * Prevents race conditions when multiple requests try to update same batch.
     * Typical: 2-5 seconds
     */
    public const BATCH_LOCK_SECONDS = 3;

    /**
     * Maximum requests per batch before auto-dispatch.
     * If batch reaches this size, dispatch immediately without waiting.
     * Typical: 5-15 requests
     */
    public const MAX_BATCH_SIZE = 10;

    /**
     * Timeout before auto-flushing batch (in seconds).
     * If batch waits this long without reaching MAX_BATCH_SIZE, dispatch anyway.
     * Prevents indefinite waiting for small batches.
     * Typical: 8-15 seconds
     */
    public const AUTO_FLUSH_TIMEOUT = 12;

    /**
     * Enable or disable batching system.
     * Set to false to fall back to direct job dispatch (useful for debugging).
     */
    public const ENABLED = true;

    /**
     * Maximum retries for batch dispatch before fallback.
     * If batching fails this many times, give up and use direct dispatch.
     */
    public const MAX_DISPATCH_RETRIES = 3;

    /**
     * Queue name for batched jobs.
     * Where the ExecuteBatchedSnapshotJob will be queued.
     * Typical: 'default' or 'imports-high'
     */
    public const BATCH_QUEUE = 'default';

    /**
     * Tables that should NOT be batched (dispatch directly instead).
     * Some tables might have real-time requirements or compatibility issues.
     */
    public const BYPASS_BATCHING_TABLES = [
        'jumlah_merchant_detail' => true,
        'sv_merchant' => true,
        'jumlah_merchant_qris_detail' => true,
        'merchant_qris' => true,
        'merchant_qris_volume' => true,
    ];

    /**
     * Thresholds for different import volumes.
     * Helps adjust batching behavior based on import concurrency.
     */
    public const VOLUME_THRESHOLDS = [
        'low' => [
            'max_batch_size' => 5,
            'auto_flush_timeout' => 10,
        ],
        'normal' => [
            'max_batch_size' => 10,
            'auto_flush_timeout' => 12,
        ],
        'high' => [
            'max_batch_size' => 15,
            'auto_flush_timeout' => 8,
        ],
        'critical' => [
            'max_batch_size' => 20,
            'auto_flush_timeout' => 5,
        ],
    ];

    /**
     * Get configuration for import volume level.
     * Automatically adjusts batching parameters based on queue size.
     */
    public static function forVolume(int $queueSize): array
    {
        $volume = match (true) {
            $queueSize > 30 => 'critical',
            $queueSize > 15 => 'high',
            $queueSize > 5 => 'normal',
            default => 'low',
        };

        return self::VOLUME_THRESHOLDS[$volume];
    }

    /**
     * Check if table should bypass batching.
     */
    public static function shouldBypassBatching(string $tableName): bool
    {
        return isset(self::BYPASS_BATCHING_TABLES[strtolower($tableName)]);
    }

    /**
     * Get effective batch size threshold based on current queue.
     */
    public static function getEffectiveBatchSize(): int
    {
        $queueSize = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $config = self::forVolume($queueSize);

        return $config['max_batch_size'] ?? self::MAX_BATCH_SIZE;
    }

    /**
     * Get effective auto-flush timeout based on current queue.
     */
    public static function getEffectiveAutoFlushTimeout(): int
    {
        $queueSize = \Illuminate\Support\Facades\DB::table('jobs')->count();
        $config = self::forVolume($queueSize);

        return $config['auto_flush_timeout'] ?? self::AUTO_FLUSH_TIMEOUT;
    }
}
