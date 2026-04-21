<?php

namespace App\Jobs;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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
    public $timeout = 120;
    
    private array $periods;

    public function __construct($period)
    {
        $this->periods = is_array($period) ? $period : [$period];
    }

    public function handle(DashboardHarianSnapshotService $service): void
    {
        try {
            Log::info("RebuildDashboardHarianSnapshotJob: Starting for periods", ['periods' => $this->periods]);

            $totalRows = 0;
            foreach ($this->periods as $period) {
                try {
                    $rows = $service->buildPeriodSnapshot((string) $period, true);
                    $totalRows += $rows;
                    Log::info("RebuildDashboardHarianSnapshotJob: Rebuilt period $period with $rows rows");
                } catch (\Throwable $e) {
                    Log::error("RebuildDashboardHarianSnapshotJob failed for period $period", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info("RebuildDashboardHarianSnapshotJob: Completed successfully", ['total_rows' => $totalRows]);
        } catch (\Throwable $e) {
            Log::error("RebuildDashboardHarianSnapshotJob failed", ['error' => $e->getMessage()]);
            $this->fail($e);
        }
    }
}
