<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\SnapshotDirtyPeriodService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSnapshotDirtyPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SnapshotJobRetryWindow;

    public $tries = 40;
    public $timeout = 3600;
    public $backoff = [60, 300];

    /**
     * @param array<string, mixed> $claim
     */
    public function __construct(private readonly array $claim)
    {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        $sourceTable = (string) ($this->claim['source_table'] ?? '');
        $period = (string) ($this->claim['period_key'] ?? '');

        return [
            new DeferSnapshotJobsDuringImport(sourceTable: $sourceTable),
            (new WithoutOverlapping('snapshot:dirty:' . $sourceTable . ':' . $period))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(SnapshotDirtyPeriodService $dirtyPeriods): void
    {
        $table = (string) ($this->claim['source_table'] ?? '');
        $period = (string) ($this->claim['period_key'] ?? '');

        try {
            Log::info('Processing snapshot dirty period.', [
                'source_table' => $table,
                'period' => $period,
                'shard_type' => $this->claim['shard_type'] ?? null,
                'shard_key' => $this->claim['shard_key'] ?? null,
            ]);

            $job = new EnsureImportedSnapshotsFreshJob($table, $period, static::class);
            app()->call([$job, 'handle']);

            $dirtyPeriods->clearClaim($this->claim);
        } catch (\Throwable $e) {
            $dirtyPeriods->releaseClaim($this->claim, $e);

            Log::error('Processing snapshot dirty period failed.', [
                'source_table' => $table,
                'period' => $period,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
