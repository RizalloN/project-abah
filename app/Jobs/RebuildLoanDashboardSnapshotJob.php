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
use Illuminate\Support\Facades\Log;

class RebuildLoanDashboardSnapshotJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 1800;
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
            (new WithoutOverlapping('snapshot:dashboard_pinjaman:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder): void
    {
        $startTime = now();

        try {
            Log::info('RebuildLoanDashboardSnapshotJob: Memulai rebuild Dashboard Pinjaman.', [
                'period' => $this->periodHint,
            ]);

            $result = $builder->rebuildDashboard($this->periodHint, true);

            ReportDataSyncService::analyzeTable('dashboard_pinjaman_snapshots');

            $this->markSnapshotSignatures($result);

            $duration = $startTime->diffInSeconds(now());
            Log::info('RebuildLoanDashboardSnapshotJob: Selesai.', [
                'period' => $this->periodHint,
                'duration_seconds' => $duration,
            ]);
        } catch (\Throwable $e) {
            Log::error('RebuildLoanDashboardSnapshotJob: Gagal.', [
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
        ];

        $periods = $this->resolvePeriodsToMark($result);
        if ($periods === []) {
            return;
        }

        $service = app(SnapshotSourceSignatureService::class);

        foreach ($periods as $period) {
            try {
                $service->markBuiltForApplicableSources(
                    'dashboard_pinjaman_snapshots',
                    $period,
                    $candidates,
                    ['job' => static::class]
                );
            } catch (\Throwable $e) {
                Log::debug('Gagal menandai snapshot signature setelah rebuild Dashboard Pinjaman.', [
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
