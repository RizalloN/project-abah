<?php

namespace App\Console\Commands;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncDashboardHarianSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snapshot:sync-harian-dashboard {--force : Force rebuild even if snapshot exists}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Sync dashboard harian snapshots with SSA pinjaman and simpanan periods';

    public function __construct(private DashboardHarianSnapshotService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $force = $this->option('force');
            
            $this->info('🔄 Starting Dashboard Harian Snapshot sync...');

            // Get all shared periods from SSA tables
            $sharedPeriods = $this->resolveSharedPeriods();

            if (empty($sharedPeriods)) {
                $this->warn('No shared periods found in SSA tables');
                return self::SUCCESS;
            }

            $this->info("Found " . count($sharedPeriods) . " shared periods");

            // Get existing snapshots
            $existingSnapshots = DB::table('dashboard_harian_snapshots')
                ->select('snapshot_period')
                ->distinct()
                ->pluck('snapshot_period')
                ->map(fn ($val) => (string) $val)
                ->all();

            // Find missing periods
            $missingPeriods = array_diff($sharedPeriods, $existingSnapshots);
            $missingPeriods = array_values($missingPeriods); // Re-index

            if (empty($missingPeriods) && !$force) {
                $this->info('✅ All snapshots are up to date');
                Log::info('Dashboard Harian snapshots are up to date');
                return self::SUCCESS;
            }

            if ($force) {
                // Rebuild all shared periods
                $periodsToRebuild = $sharedPeriods;
                $this->info("Force rebuild mode: rebuilding all " . count($periodsToRebuild) . " periods");
            } else {
                // Only rebuild missing periods
                $periodsToRebuild = $missingPeriods;
                $this->info("Found " . count($missingPeriods) . " missing periods to rebuild");
            }

            // Build snapshots with progress bar
            $progressBar = $this->output->createProgressBar(count($periodsToRebuild));
            $progressBar->start();

            $results = [];
            foreach ($periodsToRebuild as $period) {
                try {
                    $count = $this->service->buildPeriodSnapshot($period, $force);
                    $results[$period] = $count;
                    $progressBar->advance();
                } catch (Throwable $e) {
                    $this->error("\n❌ Error rebuilding period {$period}: " . $e->getMessage());
                    Log::error("Snapshot rebuild failed for period {$period}", ['error' => $e->getMessage()]);
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $successCount = count(array_filter($results));
            $failureCount = count($results) - $successCount;

            $this->info("✅ Rebuild complete: {$successCount} successful" . ($failureCount > 0 ? ", {$failureCount} failed" : ""));
            
            // Log summary
            Log::info('Dashboard Harian snapshot sync completed', [
                'total_periods' => count($periodsToRebuild),
                'successful' => $successCount,
                'failed' => $failureCount,
                'forced' => $force,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            Log::error('Dashboard Harian snapshot sync failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Get shared periods from both SSA tables.
     * This mirrors the logic in DashboardHarianSnapshotService::resolveSharedPeriods()
     */
    private function resolveSharedPeriods(): array
    {
        try {
            $loanPeriods = DB::table('ssa_pinjaman')
                ->select('month_day_year_of_periode')
                ->distinct()
                ->pluck('month_day_year_of_periode')
                ->map(fn ($val) => $this->normalizeDate((string) $val))
                ->filter()
                ->values()
                ->all();

            $savingsPeriods = DB::table('ssa_simpanan')
                ->select('Month_Day_Year_of_Posisi')
                ->distinct()
                ->pluck('Month_Day_Year_of_Posisi')
                ->map(fn ($val) => $this->normalizeDate((string) $val))
                ->filter()
                ->values()
                ->all();

            $shared = array_values(array_intersect($loanPeriods, $savingsPeriods));
            rsort($shared);

            return $shared;
        } catch (Throwable $e) {
            Log::error('Error resolving shared periods', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Normalize date to YYYY-MM-DD format.
     */
    private function normalizeDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
