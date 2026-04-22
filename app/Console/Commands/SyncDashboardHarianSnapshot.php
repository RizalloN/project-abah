<?php

namespace App\Console\Commands;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDashboardHarianSnapshot extends Command
{
    protected $signature = 'snapshot:sync-harian-dashboard {--force : Force rebuild even if snapshot exists}';

    protected $description = 'Sync Dashboard Harian snapshots with missing/stale SSA and PH source data';

    public function __construct(private DashboardHarianSnapshotService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $force = (bool) $this->option('force');

            if ($force) {
                $this->info('Force rebuilding all shared Dashboard Harian snapshot periods...');
                $result = $this->service->rebuild(null, true);
                $summary = [
                    'built' => count(array_filter($result)),
                    'periods' => array_keys($result),
                ];
            } else {
                $this->info('Syncing missing/stale Dashboard Harian snapshot periods...');
                $summary = $this->service->syncDuePeriods();
            }

            $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));

            Log::info('Dashboard Harian snapshot sync completed', [
                'forced' => $force,
                'result' => $summary,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Dashboard Harian snapshot sync failed: ' . $e->getMessage());
            Log::error('Dashboard Harian snapshot sync failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
