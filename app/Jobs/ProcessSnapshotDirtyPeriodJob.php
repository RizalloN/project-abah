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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProcessSnapshotDirtyPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SnapshotJobRetryWindow;

    public $tries = 240;
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
                ->expireAfter($this->timeout + 300)
                ->shared(),
            (new WithoutOverlapping('snapshot:source-period:' . strtolower(trim($sourceTable)) . ':' . trim($period)))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300)
                ->shared(),
        ];
    }

    public function handle(SnapshotDirtyPeriodService $dirtyPeriods): void
    {
        $table = (string) ($this->claim['source_table'] ?? '');
        $period = (string) ($this->claim['period_key'] ?? '');

        if ($this->hasActiveParallelSnapshotPipeline($table, $period)) {
            Log::info('Snapshot dirty period menunggu pipeline rebuild yang sudah aktif.', [
                'source_table' => $table,
                'period' => $period,
            ]);
            $this->release(60);

            return;
        }

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

    private function hasActiveParallelSnapshotPipeline(string $table, string $period): bool
    {
        $normalizedTable = strtolower(trim($table));
        $period = trim($period);
        $batchPrefix = match ($normalizedTable) {
            'daily_loan_dinamis' => 'daily_loan:',
            'simpanan_multipn' => 'simpanan:',
            default => null,
        };

        if ($batchPrefix === null || $period === '') {
            return false;
        }

        try {
            if (Schema::hasTable('job_batches')
                && DB::table('job_batches')
                    ->where('name', $batchPrefix . $period)
                    ->where('pending_jobs', '>', 0)
                    ->whereNull('cancelled_at')
                    ->exists()) {
                return true;
            }

            if (!Schema::hasTable('jobs')) {
                return false;
            }

            $classes = $normalizedTable === 'daily_loan_dinamis'
                ? [
                    RebuildLoanDashboardSnapshotJob::class,
                    RebuildLoanChartPeriodikSnapshotJob::class,
                    RebuildSnapshotPerformanceRmBatch::class,
                    RebuildSnapshotRasioBatch::class,
                    RebuildSnapshotHarianBatch::class,
                ]
                : [
                    RebuildSnapshotSimpleBatch::class,
                    RebuildSnapshotHarianBatch::class,
                    RebuildSnapshotDormantBatch::class,
                    RebuildSnapshotPerformanceRmBatch::class,
                    RebuildSnapshotRasioBatch::class,
                ];

            return DB::table('jobs')
                ->where('payload', 'like', '%' . $period . '%')
                ->where(function ($query) use ($classes): void {
                    foreach ($classes as $class) {
                        $query->orWhere('payload', 'like', '%' . class_basename($class) . '%');
                    }
                })
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('Tidak dapat memeriksa pipeline snapshot aktif; dirty period ditunda aman.', [
                'source_table' => $normalizedTable,
                'period' => $period,
                'message' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
