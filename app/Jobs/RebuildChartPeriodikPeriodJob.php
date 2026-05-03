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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RebuildChartPeriodikPeriodJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 600;
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
            (new WithoutOverlapping('snapshot:chart_periodik:' . $this->period))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder): void
    {
        $startTime = now();

        try {
            $this->updateProgress("Membangun Chart Periodik untuk periode {$this->period}...");

            $result = $builder->buildChartPeriodikPeriodSnapshot($this->period, $this->force);

            $this->updateProgress("Menyegarkan statistik Chart Periodik untuk {$this->period}...");
            $this->refreshTableStatistics('dashboard_pinjaman_chart_periodik_snapshots', $this->period);

            $duration = $startTime->diffInSeconds(now());

            Log::info('RebuildChartPeriodikPeriodJob selesai', [
                'period' => $this->period,
                'duration_seconds' => $duration,
                'inserted_rows' => $result ?? 0,
            ]);

            $this->updateProgress("Periode {$this->period} selesai (${duration}s)", 'success');

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('RebuildChartPeriodikPeriodJob gagal', [
                'period' => $this->period,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);

            $this->updateProgress("Periode {$this->period} gagal: " . $e->getMessage(), 'failed');
            throw $e;
        }
    }

    private function refreshTableStatistics(string $tableName, string $period): void
    {
        try {
            DB::statement("ANALYZE TABLE `{$tableName}`");
        } catch (\Exception $e) {
            Log::warning("Gagal refresh statistics untuk {$tableName}", [
                'error' => $e->getMessage(),
            ]);
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
                    'job_type' => 'snapshot_chart_periodik_period',
                    'period' => $this->period,
                ],
                now()->addHours(1)
            );
        } catch (\Exception $e) {
            Log::debug('Gagal update progress cache', ['error' => $e->getMessage()]);
        }
    }
}
