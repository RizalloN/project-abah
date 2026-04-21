<?php

namespace App\Console\Commands;

use App\Support\DashboardHarianSnapshotService;
use App\Jobs\RebuildDashboardHarianSnapshotJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPTIMIZED: Rebuild Dashboard Harian Snapshots
 * 
 * Improvements over the original sync command:
 * 1. Only rebuilds MISSING periods by default (not all 152)
 * 2. Supports async dispatch to background queue for large rebuilds
 * 3. Much faster for incremental updates
 * 
 * Usage:
 *   # Rebuild only missing periods (fast)
 *   php artisan snapshot:rebuild-harian --auto
 *   
 *   # Rebuild specific period only (very fast for new data)
 *   php artisan snapshot:rebuild-harian --period=2026-04-20
 *   
 *   # Dispatch to background queue (doesn't block)
 *   php artisan snapshot:rebuild-harian --period=2026-04-20 --async
 *   
 *   # Force rebuild all (slow, only when needed)
 *   php artisan snapshot:rebuild-harian --force
 */
class RebuildDashboardHarianCommand extends Command
{
    protected $signature = 'snapshot:rebuild-harian
        {--period= : Rebuild specific period (e.g., 2026-04-20)}
        {--auto : Rebuild only missing periods}
        {--async : Dispatch to queue instead of blocking}
        {--force : Force rebuild all periods (SLOW)}';

    protected $description = 'Rebuild Dashboard Harian snapshots (optimized)';

    public function __construct(private DashboardHarianSnapshotService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $period = $this->option('period');
            $auto = $this->option('auto');
            $async = $this->option('async');
            $force = $this->option('force');

            if ($force) {
                $this->info('🔄 Force rebuild mode: rebuilding ALL periods (slow)');
                return $this->rebuildAll();
            }

            if ($period) {
                $this->info("🔄 Rebuilding specific period: $period");
                
                if ($async) {
                    $this->info('📤 Dispatching to background queue...');
                    dispatch(new RebuildDashboardHarianSnapshotJob($period))->onQueue('imports-high');
                    $this->info('✅ Job dispatched. Rebuilding in background queue.');
                    Log::info("Snapshot rebuild dispatched for period $period");
                    return self::SUCCESS;
                }

                $rows = $this->service->buildPeriodSnapshot($period, true);
                $this->info("✅ Rebuilt $period: $rows rows");
                Log::info("Snapshot rebuild completed for period $period", ['rows' => $rows]);
                return self::SUCCESS;
            }

            if ($auto) {
                $this->info('🔄 Auto mode: rebuilding MISSING periods only');
                return $this->rebuildMissing();
            }

            // Default: rebuild missing periods
            $this->info('🔄 Rebuilding missing periods...');
            return $this->rebuildMissing();
        } catch (Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error('Snapshot rebuild failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    private function rebuildMissing(): int
    {
        try {
            $sharedPeriods = $this->getSharedPeriods();
            if (empty($sharedPeriods)) {
                $this->warn('No shared periods found in SSA tables');
                return self::SUCCESS;
            }

            $existingSnapshots = DB::table('dashboard_harian_snapshots')
                ->select('snapshot_period')
                ->distinct()
                ->pluck('snapshot_period')
                ->map(fn ($val) => (string) $val)
                ->all();

            $missingPeriods = array_diff($sharedPeriods, $existingSnapshots);
            
            if (empty($missingPeriods)) {
                $this->info("✅ All snapshots are up to date ({count($existingSnapshots)} periods)");
                return self::SUCCESS;
            }

            $this->info("Found " . count($missingPeriods) . " missing periods to rebuild");

            $bar = $this->output->createProgressBar(count($missingPeriods));
            $bar->start();

            $built = 0;
            $failed = 0;

            foreach ($missingPeriods as $period) {
                try {
                    $this->service->buildPeriodSnapshot((string) $period, false);
                    $built++;
                } catch (Throwable $e) {
                    $this->error("\n❌ Failed to rebuild $period: " . $e->getMessage());
                    $failed++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Rebuild complete: $built successful" . ($failed > 0 ? ", $failed failed" : ""));
            Log::info("Snapshot rebuild completed", [
                'missing_periods' => count($missingPeriods),
                'built' => $built,
                'failed' => $failed,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function rebuildAll(): int
    {
        try {
            $sharedPeriods = $this->getSharedPeriods();
            if (empty($sharedPeriods)) {
                $this->warn('No shared periods found in SSA tables');
                return self::SUCCESS;
            }

            $this->info("Force rebuilding all " . count($sharedPeriods) . " periods (slow)");

            $bar = $this->output->createProgressBar(count($sharedPeriods));
            $bar->start();

            $built = 0;
            $failed = 0;

            foreach ($sharedPeriods as $period) {
                try {
                    $this->service->buildPeriodSnapshot((string) $period, true);
                    $built++;
                } catch (Throwable $e) {
                    $this->error("\n❌ Failed to rebuild $period: " . $e->getMessage());
                    $failed++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("✅ Force rebuild complete: $built successful" . ($failed > 0 ? ", $failed failed" : ""));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function getSharedPeriods(): array
    {
        try {
            $loanPeriods = DB::table('ssa_pinjaman')
                ->select('month_day_year_of_periode')
                ->distinct()
                ->pluck('month_day_year_of_periode')
                ->map(fn ($val) => (string) $val)
                ->filter()
                ->all();

            $savingsPeriods = DB::table('ssa_simpanan')
                ->select('Month_Day_Year_of_Posisi')
                ->distinct()
                ->pluck('Month_Day_Year_of_Posisi')
                ->map(fn ($val) => (string) $val)
                ->filter()
                ->all();

            $shared = array_intersect($loanPeriods, $savingsPeriods);
            rsort($shared);

            return array_values($shared);
        } catch (Throwable $e) {
            Log::error('Error getting shared periods', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
