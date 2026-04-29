<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
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

            $builder->rebuildDashboard($this->periodHint, true);

            ReportDataSyncService::analyzeTable('dashboard_pinjaman_snapshots');

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
}
