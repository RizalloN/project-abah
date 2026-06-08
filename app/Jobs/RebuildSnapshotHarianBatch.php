<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\DashboardHarianSnapshotService;
use App\Support\SnapshotSourceSignatureService;
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

class RebuildSnapshotHarianBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 1200; // 20 minutes
    public $backoff = [60, 300];

    private ?string $periodHint;
    private ?string $deleteId;

    public function __construct(?string $periodHint = null, ?string $deleteId = null)
    {
        $this->periodHint = $periodHint;
        $this->deleteId = $deleteId;
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        $scope = strtolower(trim((string) $this->periodHint)) ?: 'all';

        return [
            new DeferSnapshotJobsDuringImport(),
            (new WithoutOverlapping('snapshot:dashboard_harian:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(DashboardHarianSnapshotService $service): void
    {
        $startTime = now();

        try {
            $this->updateProgress('Memulai rebuild Dashboard Harian...');

            $result = $service->rebuild($this->periodHint, true, $this->makeHeartbeatCallback());

            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

            $this->markSnapshotSignatures($result);

            $duration = $startTime->diffInSeconds(now());

            Log::info('RebuildSnapshotHarianBatch selesai', [
                'period' => $this->periodHint,
                'duration_seconds' => $duration,
                'batch_size' => $result['inserted_rows'] ?? 0,
            ]);

            $this->updateProgress("Selesai (${duration}s)", 'success');

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('RebuildSnapshotHarianBatch gagal', [
                'period' => $this->periodHint,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
            ]);

            $this->updateProgress('Gagal: ' . $e->getMessage(), 'failed');
            throw $e;
        }
    }

    /**
     * Mark snapshot signature for every source that fed the rebuild. Dashboard
     * Harian aggregates multiple sources; only those with rows for the period
     * actually contributed and will be marked.
     *
     * @param mixed $result
     */
    private function markSnapshotSignatures(mixed $result): void
    {
        $candidates = [
            ['source_table' => 'ssa_simpanan', 'period_column' => 'Month_Day_Year_of_Posisi'],
            ['source_table' => 'ssa_pinjaman', 'period_column' => 'month_day_year_of_periode'],
            ['source_table' => 'dly_kap_resegmentasi', 'period_column' => 'periode'],
            ['source_table' => 'l1133', 'period_column' => 'periode'],
            ['source_table' => 'gi405_recovery', 'period_column' => 'periode'],
            ['source_table' => 'daily_loan_dinamis', 'period_column' => 'periode'],
            ['source_table' => 'simpanan_multipn', 'period_column' => 'posisi'],
            ['source_table' => 'lw325_ph', 'period_column' => 'periode'],
        ];

        $periods = $this->resolvePeriodsToMark($result);
        if ($periods === []) {
            return;
        }

        $service = app(SnapshotSourceSignatureService::class);

        foreach ($periods as $period) {
            try {
                $service->markBuiltForApplicableSources(
                    'dashboard_harian_snapshots',
                    $period,
                    $candidates,
                    ['job' => static::class]
                );
            } catch (\Throwable $e) {
                Log::debug('Gagal menandai snapshot signature setelah rebuild Dashboard Harian.', [
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

    private function makeHeartbeatCallback(): callable
    {
        return function (array $progress) {
            if ($this->deleteId) {
                $message = sprintf(
                    '%s (%d/%d)',
                    $progress['message'] ?? 'Processing...',
                    $progress['completed_units'] ?? 0,
                    $progress['total_units'] ?? 0
                );
                $this->updateProgress($message);
            }
        };
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
                    'job_type' => 'snapshot_harian',
                ],
                now()->addHours(1)
            );
        } catch (\Exception $e) {
            Log::debug('Gagal update progress cache', ['error' => $e->getMessage()]);
        }
    }
}
