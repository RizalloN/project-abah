<?php

namespace App\Console\Commands;

use App\Jobs\RebuildDashboardHarianSnapshotJob;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildDashboardHarianCommand extends Command
{
    protected $signature = 'snapshot:rebuild-harian
        {--period= : Rebuild specific period (e.g., 2026-04-20)}
        {--auto : Rebuild missing/stale periods}
        {--async : Dispatch to queue instead of blocking}
        {--force : Force rebuild all periods (SLOW)}';

    protected $description = 'Rebuild or sync Dashboard Harian snapshots';

    public function __construct(private DashboardHarianSnapshotService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $period = trim((string) $this->option('period'));
            $async = (bool) $this->option('async');
            $force = (bool) $this->option('force');

            if ($force) {
                $this->info('Force rebuilding all shared Dashboard Harian snapshot periods...');
                $result = $this->service->rebuild(null, true);
                $this->line(json_encode([
                    'built' => count(array_filter($result)),
                    'periods' => array_keys($result),
                ], JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            if ($period !== '') {
                if ($async) {
                    dispatch(new RebuildDashboardHarianSnapshotJob($period, false, true))
                        ->onQueue('snapshots-parallel');
                    $this->info("Queued force rebuild for Dashboard Harian period {$period}.");
                    Log::info('Dashboard Harian period rebuild dispatched', ['period' => $period]);

                    return self::SUCCESS;
                }

                $rows = $this->service->buildPeriodSnapshot($period, true);
                $this->line(json_encode([
                    'period' => $period,
                    'rows' => $rows,
                ], JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $result = $this->service->syncDuePeriods();
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Dashboard Harian snapshot rebuild failed: ' . $e->getMessage());
            Log::error('Dashboard Harian snapshot rebuild failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
