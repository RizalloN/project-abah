<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSnapshotDirtyPeriodJob;
use App\Support\SnapshotDirtyPeriodService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class DrainSnapshotDirtyPeriodsCommand extends Command
{
    protected $signature = 'reports:snapshot:drain-dirty
        {--limit=50 : Maximum dirty scopes claimed per drain pass}
        {--source-table= : Optional source table filter}
        {--period= : Optional period filter}
        {--max-runtime=55 : Self-loop runtime budget in seconds}
        {--max-rebuild-concurrency=4 : Maximum jobs dispatched per pass}
        {--queue-threshold=50 : Skip dispatch when snapshots-parallel queue is above this size}';

    protected $description = 'Drain persistent snapshot dirty periods and dispatch incremental rebuild jobs';

    public function handle(SnapshotDirtyPeriodService $dirtyPeriods): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxDispatch = max(1, (int) $this->option('max-rebuild-concurrency'));
        $queueThreshold = max(0, (int) $this->option('queue-threshold'));
        $sourceTable = trim((string) $this->option('source-table')) ?: null;
        $period = trim((string) $this->option('period')) ?: null;

        $passes = 1;
        $dispatched = 0;

        if ($this->snapshotQueueIsBackpressured($queueThreshold)) {
            $this->audit('snapshot_dirty_drain', 'skipped', [
                'message' => 'snapshots-parallel queue above threshold',
                'context' => ['queue_threshold' => $queueThreshold],
            ]);
        } else {
            $claims = $dirtyPeriods->claimDue(min($limit, $maxDispatch), $sourceTable, $period);

            foreach ($claims as $claim) {
                ProcessSnapshotDirtyPeriodJob::dispatch($claim)->onQueue('snapshots-parallel');
                $dispatched++;

                $this->audit('snapshot_dirty_claim', 'success', [
                    'table_name' => (string) ($claim['source_table'] ?? 'snapshot_dirty_periods'),
                    'period_hint' => (string) ($claim['period_key'] ?? ''),
                    'context' => [
                        'shard_type' => $claim['shard_type'] ?? null,
                        'shard_key' => $claim['shard_key'] ?? null,
                        'attempts' => $claim['attempts'] ?? null,
                    ],
                ]);
            }
        }

        $this->line(json_encode([
            'passes' => $passes,
            'dispatched' => $dispatched,
            'pending' => $dirtyPeriods->pendingCount($sourceTable),
            'claimable' => $dirtyPeriods->claimableCount($sourceTable, $period),
        ], JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function snapshotQueueIsBackpressured(int $threshold): bool
    {
        if ($threshold <= 0) {
            return false;
        }

        try {
            return Queue::size('snapshots-parallel') > $threshold;
        } catch (\Throwable $e) {
            Log::debug('Unable to read snapshots-parallel queue size.', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function audit(string $action, string $status, array $payload = []): void
    {
        if (! Schema::hasTable('report_sync_audits')) {
            return;
        }

        try {
            DB::table('report_sync_audits')->insert([
                'import_job_id' => null,
                'source' => static::class,
                'table_name' => $payload['table_name'] ?? 'snapshot_dirty_periods',
                'period_hint' => $payload['period_hint'] ?? null,
                'action' => $action,
                'status' => $status,
                'duration_ms' => $payload['duration_ms'] ?? null,
                'affected_rows' => $payload['affected_rows'] ?? null,
                'message' => $payload['message'] ?? null,
                'context' => isset($payload['context']) ? json_encode($payload['context'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Failed to write snapshot dirty audit.', [
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
