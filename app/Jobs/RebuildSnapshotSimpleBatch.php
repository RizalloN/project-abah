<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\ReportSnapshotBuilder;
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

class RebuildSnapshotSimpleBatch implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 1200; // 20 minutes
    public $backoff = [60, 300]; // Retry after 1 min, then 5 min

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
            (new WithoutOverlapping('snapshot:dashboard_simpanan:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder): void
    {
        $startTime = now();

        try {
            $this->updateProgress('Memulai rebuild Dashboard Simpanan...');

            $result = $builder->rebuildDashboardSimpanan(
                $this->periodHint,
                false,
                $this->makeHeartbeatCallback()
            );

            ReportDataSyncService::analyzeTable('dashboard_simpanan_snapshots');
            ReportDataSyncService::analyzeTable('dashboard_simpanan_branch_snapshots');

            $this->markSnapshotSignatures();

            $duration = $startTime->diffInSeconds(now());

            Log::info('RebuildSnapshotSimpleBatch selesai', [
                'period' => $this->periodHint,
                'duration_seconds' => $duration,
                'batch_size' => $result['inserted_rows'] ?? 0,
            ]);

            $this->updateProgress("Selesai (${duration}s)", 'success');

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('RebuildSnapshotSimpleBatch gagal', [
                'period' => $this->periodHint,
                'duration_seconds' => $duration,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress('Gagal: ' . $e->getMessage(), 'failed');
            throw $e;
        }
    }

    private function markSnapshotSignatures(): void
    {
        $period = trim((string) $this->periodHint);
        if ($period === '') {
            return;
        }

        try {
            app(SnapshotSourceSignatureService::class)->markBuiltForApplicableSources(
                'dashboard_simpanan_snapshots',
                $period,
                [
                    ['source_table' => 'simpanan_multipn', 'period_column' => 'posisi'],
                ],
                ['job' => static::class]
            );
        } catch (\Throwable $e) {
            Log::debug('Gagal menandai snapshot signature setelah rebuild Dashboard Simpanan.', [
                'period' => $period,
                'message' => $e->getMessage(),
            ]);
        }
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
                    'job_type' => 'snapshot_simpanan',
                ],
                now()->addHours(1)
            );
        } catch (\Exception $e) {
            Log::debug('Gagal update progress cache', ['error' => $e->getMessage()]);
        }
    }
}
