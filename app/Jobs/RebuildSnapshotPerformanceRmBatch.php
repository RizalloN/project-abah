<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\SnapshotSourceSignatureService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildSnapshotPerformanceRmBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 1200;
    public $backoff = [60, 300];

    public function __construct(
        private readonly ?string $periodHint = null,
        private readonly ?string $deleteId = null
    ) {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        $scope = strtolower(trim((string) $this->periodHint)) ?: 'all';

        return [
            new DeferSnapshotJobsDuringImport(),
            (new WithoutOverlapping('snapshot:performance_rm:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder): void
    {
        $startTime = now();

        try {
            Log::info('RebuildSnapshotPerformanceRmBatch: Memulai rebuild Performance RM.', [
                'period' => $this->periodHint,
            ]);

            $result = $builder->rebuildPerformanceRm($this->periodHint, true);

            ReportDataSyncService::analyzeTable('performance_rm_snapshots');

            $this->markSnapshotSignatures($result);

            if ($result !== []) {
                $cacheVersion = (int) Cache::get('report_cache_version:global', 1) + 1;
                Cache::put('report_cache_version:global', $cacheVersion, now()->addHours(24));
            }

            $duration = $startTime->diffInSeconds(now());
            Log::info('RebuildSnapshotPerformanceRmBatch: Selesai.', [
                'period' => $this->periodHint,
                'periods' => array_keys($result),
                'rows' => array_sum($result),
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            Log::error('RebuildSnapshotPerformanceRmBatch: Gagal.', [
                'period' => $this->periodHint,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function markSnapshotSignatures(mixed $result): void
    {
        $candidates = [
            ['source_table' => 'daily_loan_dinamis', 'period_column' => 'periode'],
            ['source_table' => 'simpanan_multipn', 'period_column' => 'posisi'],
        ];

        $periods = $this->resolvePeriodsToMark($result);
        if ($periods === []) {
            return;
        }

        $service = app(SnapshotSourceSignatureService::class);

        foreach ($periods as $period) {
            try {
                $service->markBuiltForApplicableSources(
                    'performance_rm_snapshots',
                    $period,
                    $candidates,
                    ['job' => static::class]
                );
            } catch (\Throwable $e) {
                Log::debug('Gagal menandai snapshot signature setelah rebuild Performance RM.', [
                    'period' => $period,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param mixed $result
     * @return array<int, string>
     */
    private function resolvePeriodsToMark(mixed $result): array
    {
        $periodHint = trim((string) $this->periodHint);
        if ($periodHint !== '') {
            return [$periodHint];
        }

        if (!is_array($result)) {
            return [];
        }

        $periods = [];
        foreach ($result as $key => $value) {
            $period = trim((string) $key);
            if ($period === '' || $period === 'inserted_rows') {
                continue;
            }
            if ((int) (is_numeric($value) ? $value : 0) <= 0) {
                continue;
            }
            $periods[] = $period;
        }

        return $periods;
    }
}
