<?php

namespace App\Jobs;

use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job to rebuild Dashboard Harian snapshot for specific period(s)
 * 
 * This job is dispatched:
 * 1. When new SSA Pinjaman data is imported (trigger in import controller)
 * 2. When new SSA Simpanan data is imported (trigger in import controller)
 * 3. Runs in background queue - doesn't block the user response
 * 
 * Queue: imports-high (priority queue for import-related tasks)
 */
class RebuildDashboardHarianSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 0;
    
    private array $periods;

    public function __construct($period = null, private bool $consumeDirtyPeriods = false, private bool $force = false)
    {
        $this->periods = is_array($period) ? $period : [$period];
    }

    public function middleware(): array
    {
        $scope = $this->consumeDirtyPeriods
            ? 'dirty'
            : (implode(',', $this->normalizedPeriods()) ?: '__auto__');

        return [
            (new WithoutOverlapping('snapshot:dashboard_harian:rebuild:' . md5($scope)))
                ->releaseAfter(10)
                ->expireAfter(900),
        ];
    }

    public function handle(DashboardHarianSnapshotService $service, DashboardHarianSnapshotDirtyPeriodQueue $dirtyPeriods): void
    {
        try {
            $periods = $this->consumeDirtyPeriods
                ? $dirtyPeriods->consume()
                : $this->normalizedPeriods();

            Log::info('RebuildDashboardHarianSnapshotJob: Starting.', [
                'periods' => $periods,
                'consume_dirty_periods' => $this->consumeDirtyPeriods,
                'force' => $this->force,
            ]);

            if ($this->consumeDirtyPeriods && $periods === []) {
                Log::info('RebuildDashboardHarianSnapshotJob: No dirty periods found; skipping.');
                return;
            }

            if ($periods === null || $periods === []) {
                $result = $service->syncDuePeriods();
                Log::info('RebuildDashboardHarianSnapshotJob: Synced automatic due periods.', $result);
                return;
            }

            if ($this->force) {
                $totalRows = 0;
                foreach ($periods as $period) {
                    try {
                        $rows = $service->buildPeriodSnapshot($period, true);
                        $totalRows += $rows;
                        Log::info("RebuildDashboardHarianSnapshotJob: Force rebuilt period $period with $rows rows");
                    } catch (\Throwable $e) {
                        Log::error("RebuildDashboardHarianSnapshotJob failed for period $period", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info('RebuildDashboardHarianSnapshotJob: Completed force rebuild.', ['total_rows' => $totalRows]);
                return;
            }

            $result = $service->syncDuePeriods($periods);
            Log::info('RebuildDashboardHarianSnapshotJob: Completed due-period sync.', $result);
        } catch (\Throwable $e) {
            Log::error("RebuildDashboardHarianSnapshotJob failed", ['error' => $e->getMessage()]);
            $this->fail($e);
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizedPeriods(): array
    {
        return array_values(array_filter(
            array_map(fn ($period) => trim((string) $period), $this->periods),
            fn (string $period) => $period !== ''
        ));
    }
}
