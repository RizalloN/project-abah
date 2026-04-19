<?php

namespace App\Console\Commands;

use App\Support\DashboardHarianSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildRecoverySnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snapshot:rebuild-recovery {--period= : Specific period to rebuild (optional)} {--force : Force rebuild even if snapshot exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild recovery DH snapshots with case-insensitive filter support';

    /**
     * The dashboard snapshot service.
     *
     * @var DashboardHarianSnapshotService
     */
    protected DashboardHarianSnapshotService $snapshotService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(DashboardHarianSnapshotService $snapshotService)
    {
        parent::__construct();
        $this->snapshotService = $snapshotService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== RECOVERY DH SNAPSHOT REBUILD ===');
        $this->newLine();

        $force = $this->option('force');
        $specificPeriod = $this->option('period');

        try {
            // Get periods to rebuild
            if ($specificPeriod) {
                $periods = [$specificPeriod];
                $this->info("Rebuilding snapshot for period: $specificPeriod");
            } else {
                // Get last 5 periods
                $this->info('Fetching available periods...');
                $periods = DB::table('lw325_ph')
                    ->select(DB::raw('DISTINCT periode'))
                    ->orderBy('periode', 'desc')
                    ->limit(5)
                    ->pluck('periode')
                    ->toArray();

                $this->info("Found " . count($periods) . " recent periods");
            }

            if (empty($periods)) {
                $this->error('No periods found in lw325_ph table');
                return 1;
            }

            $this->newLine();
            $this->info('Periods to process:');
            foreach ($periods as $period) {
                $this->line("  - $period");
            }

            $this->newLine();
            $this->info('Rebuilding snapshots' . ($force ? ' (force mode)' : '') . '...');

            $totalRows = 0;
            $progressBar = $this->output->createProgressBar(count($periods));

            foreach ($periods as $period) {
                try {
                    $rowCount = $this->snapshotService->buildPeriodSnapshot($period, $force);
                    $totalRows += $rowCount;
                    $progressBar->advance();
                } catch (\Exception $e) {
                    $progressBar->advance();
                    $this->warn("Error building snapshot for $period: " . $e->getMessage());
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Verify recovery data
            $this->info('Verifying recovery data in rebuilt snapshots...');
            $recoveryData = DB::table('dashboard_harian_snapshots')
                ->select('snapshot_period',
                    DB::raw('SUM(rec_dh_small) as rec_dh_small'),
                    DB::raw('SUM(rec_dh_consumer) as rec_dh_consumer'),
                    DB::raw('SUM(rec_dh_micro) as rec_dh_micro'),
                    DB::raw('SUM(rec_dh_total) as rec_dh_total')
                )
                ->whereIn('snapshot_period', $periods)
                ->groupBy('snapshot_period')
                ->orderBy('snapshot_period', 'desc')
                ->get();

            if ($recoveryData->isEmpty()) {
                $this->warn('No recovery data found in rebuilt snapshots');
            } else {
                $this->newLine();
                $this->info('Recovery data summary:');
                foreach ($recoveryData as $row) {
                    $this->line("Period: <fg=cyan>{$row->snapshot_period}</>");
                    $this->line("  Small:    " . number_format($row->rec_dh_small, 2));
                    $this->line("  Consumer: " . number_format($row->rec_dh_consumer, 2));
                    $this->line("  Micro:    " . number_format($row->rec_dh_micro, 2));
                    $this->line("  <fg=yellow>Total:    " . number_format($row->rec_dh_total, 2) . "</>");
                }
            }

            // Test with Madiun filter
            $this->newLine();
            $this->info('Testing recovery data with Madiun filter...');

            if (!empty($periods)) {
                $testPeriod = $periods[0];
                $madiun = DB::table('lw325_ph')
                    ->select(DB::raw("DISTINCT TRIM(COALESCE(kanca, '')) as kanca"))
                    ->where('periode', $testPeriod)
                    ->pluck('kanca')
                    ->first(fn ($k) => stripos($k, 'madiun') !== false);

                if ($madiun) {
                    $this->line("Found Madiun variant: <fg=cyan>$madiun</>");

                    $prevPeriod = DB::table('lw325_ph')
                        ->select('periode')
                        ->where('periode', '<', $testPeriod)
                        ->orderBy('periode', 'desc')
                        ->limit(1)
                        ->pluck('periode')
                        ->first();

                    if ($prevPeriod) {
                        $recovery = DB::table('lw325_ph as n')
                            ->join('lw325_ph as o', function ($join) use ($testPeriod, $prevPeriod) {
                                $join->on('n.acctno', '=', 'o.acctno')
                                    ->on('n.kanca', '=', 'o.kanca')
                                    ->on('n.unit', '=', 'o.unit')
                                    ->where('n.periode', $testPeriod)
                                    ->where('o.periode', $prevPeriod);
                            })
                            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
                            ->where(DB::raw("UPPER(TRIM(COALESCE(n.kanca, '')))"), strtoupper($madiun))
                            ->selectRaw('COUNT(*) as count')
                            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as total_recovery')
                            ->first();

                        if ($recovery && $recovery->total_recovery > 0) {
                            $this->info('<fg=green>✓ Recovery data found for Madiun!</>');
                            $this->line("Accounts with decreased principal: " . number_format($recovery->count));
                            $this->line("Total recovery amount: " . number_format($recovery->total_recovery, 2));
                        } else {
                            $this->warn("No recovery data found for Madiun in period $testPeriod");
                        }
                    }
                } else {
                    $this->warn('No Madiun variant found in database');
                }
            }

            $this->newLine();
            $this->info('<fg=green>✓ Snapshot rebuild complete!</>');
            $this->line("Total rows built: <fg=cyan>$totalRows</>");
            $this->newLine();
            $this->info('Dashboard should now display recovery data correctly when filtered by Madiun or other branches.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }
    }
}
