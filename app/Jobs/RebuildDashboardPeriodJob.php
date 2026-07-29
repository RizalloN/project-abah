<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use App\Support\ReportDataSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RebuildDashboardPeriodJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SnapshotJobRetryWindow;

    public $tries = 40;
    public $timeout = 600; // 10 minutes per period
    public $backoff = [60, 300];

    public function __construct(
        private readonly string $period,
        private readonly bool $force = false,
        private readonly ?string $deleteId = null,
    ) {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        return [
            new DeferSnapshotJobsDuringImport(),
            (new WithoutOverlapping('snapshot:dashboard:' . $this->period))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder): void
    {
        $startTime = now();

        try {
            $this->updateProgress("Membangun snapshot Dashboard Pinjaman untuk periode {$this->period}...");

            $result = (int) ($builder->rebuildDashboard($this->period, $this->force)[$this->period] ?? 0);

            ReportDataSyncService::analyzeTable('dashboard_pinjaman_snapshots');

            $duration = $startTime->diffInSeconds(now());

            Log::info('RebuildDashboardPeriodJob selesai', [
                'period' => $this->period,
                'duration_seconds' => $duration,
                'inserted_rows' => $result ?? 0,
            ]);

            $this->updateProgress("Periode {$this->period} selesai (${duration}s)", 'success');

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('RebuildDashboardPeriodJob gagal', [
                'period' => $this->period,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);

            $this->updateProgress("Periode {$this->period} gagal: " . $e->getMessage(), 'failed');
            throw $e;
        }
    }

    private function updateProgress(string $message, string $status = 'processing'): void
    {
        if (!$this->deleteId) {
            return;
        }

        try {
            Cache::put(
                "delete_progress:{$this->deleteId}",
                [
                    'status' => $status,
                    'message' => $message,
                    'updated_at' => now()->timestamp,
                    'job_type' => 'snapshot_dashboard_period',
                    'period' => $this->period,
                ],
                now()->addHours(1)
            );
        } catch (\Exception $e) {
            Log::debug('Gagal update progress cache', ['error' => $e->getMessage()]);
        }
    }
}
