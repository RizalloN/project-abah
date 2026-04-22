<?php

namespace App\Console\Commands;

use App\Support\SnapshotBatchAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageSnapshotBatches extends Command
{
    protected $signature = 'snapshot:manage-batches
                          {action : Action to perform (status|flush|flush-due|reset|config)}
                          {--batch-key= : Specific batch key to manage}
                          {--force : Force action without confirmation}';

    protected $description = 'Manage snapshot batches (view status, flush, reset)';

    public function __construct(
        private readonly SnapshotBatchAggregator $aggregator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus(),
            'flush' => $this->flushBatch(),
            'flush-due' => $this->flushDueBatches(),
            'reset' => $this->resetBatches(),
            'config' => $this->showConfig(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    private function showStatus(): int
    {
        $batches = $this->aggregator->getActiveBatches();

        if (empty($batches)) {
            $this->info('No active batches found.');
        } else {
            $this->info("Active batches: " . count($batches));
            $this->newLine();

            $headers = ['Batch Key', 'Requests', 'Created At', 'Status'];
            $rows = [];

            foreach ($batches as $key => $batch) {
                $batchKey = str_replace('snapshot:batch:', '', $key);
                $requestCount = count($batch['requests'] ?? []);
                $createdAt = $batch['first_requested_at'] ?? 'unknown';
                $willFlush = $this->willFlush($batch) ? 'WILL FLUSH' : 'WAITING';

                $rows[] = [
                    $batchKey,
                    $requestCount,
                    $createdAt,
                    $willFlush,
                ];
            }

            $this->table($headers, $rows);
        }

        // Show job queue status
        $jobCount = DB::table('jobs')->count();
        $failedCount = DB::table('failed_jobs')->count();

        $this->newLine();
        $this->info("Queue Status:");
        $this->line("  Pending jobs: {$jobCount}");
        $this->line("  Failed jobs: {$failedCount}");

        return 0;
    }

    private function flushBatch(): int
    {
        $batchKey = $this->option('batch-key');

        if (!$batchKey) {
            $this->error('--batch-key option is required for flush action');
            return 1;
        }

        $result = $this->aggregator->flushBatch($batchKey);

        if ($result['batched'] ?? false) {
            $this->info("✓ Batch flushed successfully");
            $this->line("  Batch key: {$batchKey}");
            $this->line("  Requests dispatched: " . ($result['request_count'] ?? 0));
        } else {
            $this->error("✗ Failed to flush batch");
            $this->line("  Reason: " . ($result['reason'] ?? 'unknown'));
        }

        return 0;
    }

    private function flushDueBatches(): int
    {
        $this->info('Flushing batches that are ready to dispatch...');

        $result = $this->aggregator->flushDueBatches();

        if (empty($result)) {
            $this->info('No batches were ready to flush.');
        } else {
            $this->info("✓ Flushed " . count($result) . " batch(es)");
            foreach ($result as $flushResult) {
                if ($flushResult['batched'] ?? false) {
                    $this->line("  - {$flushResult['batch_key']}: {$flushResult['request_count']} requests");
                }
            }
        }

        return 0;
    }

    private function resetBatches(): int
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to reset ALL batches? This will discard pending requests.')) {
            $this->line('Aborted.');
            return 0;
        }

        $count = $this->aggregator->resetActiveBatches();

        $this->info("✓ Reset {$count} batch(es)");

        return 0;
    }

    private function showConfig(): int
    {
        $this->info('Snapshot Batching Configuration:');
        $this->newLine();

        $config = [
            'Enabled' => \App\Support\SnapshotBatchConfig::ENABLED ? 'Yes' : 'No',
            'Batch TTL (seconds)' => \App\Support\SnapshotBatchConfig::BATCH_TTL_SECONDS,
            'Lock Timeout (seconds)' => \App\Support\SnapshotBatchConfig::BATCH_LOCK_SECONDS,
            'Default Max Batch Size' => \App\Support\SnapshotBatchConfig::MAX_BATCH_SIZE,
            'Default Auto-Flush Timeout (seconds)' => \App\Support\SnapshotBatchConfig::AUTO_FLUSH_TIMEOUT,
            'Batch Queue' => \App\Support\SnapshotBatchConfig::BATCH_QUEUE,
        ];

        foreach ($config as $key => $value) {
            $this->line("{$key}: {$value}");
        }

        // Show current dynamic thresholds
        $this->newLine();
        $jobCount = DB::table('jobs')->count();
        $this->info("Current Queue Size: {$jobCount} jobs");
        $this->newLine();

        $currentConfig = \App\Support\SnapshotBatchConfig::forVolume($jobCount);
        $this->info('Current Dynamic Configuration (based on queue size):');
        $this->line("  Max Batch Size: " . ($currentConfig['max_batch_size'] ?? 'N/A'));
        $this->line("  Auto-Flush Timeout: " . ($currentConfig['auto_flush_timeout'] ?? 'N/A') . " seconds");

        return 0;
    }

    private function willFlush(array $batch): bool
    {
        $timeout = \App\Support\SnapshotBatchConfig::getEffectiveAutoFlushTimeout();
        $maxSize = \App\Support\SnapshotBatchConfig::getEffectiveBatchSize();

        $requestCount = count($batch['requests'] ?? []);
        if ($requestCount >= $maxSize) {
            return true;
        }

        $firstRequestedAt = $batch['first_requested_at'] ?? null;
        if (!$firstRequestedAt) {
            return false;
        }

        try {
            $flushTime = \Carbon\Carbon::parse($firstRequestedAt)->addSeconds($timeout);
            return $flushTime->lessThanOrEqualTo(now());
        } catch (\Throwable) {
            return false;
        }
    }
}
