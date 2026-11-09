<?php

namespace App\Console\Commands;

use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportCacheVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillRestrukSnapshotCommand extends Command
{
    protected $signature = 'snapshot:backfill-restruk
        {--period= : One snapshot period (Y-m-d)}
        {--from= : First snapshot period to include (Y-m-d)}';

    protected $description = 'Fill the LR/LNR kol-1 split on existing Dashboard Harian snapshots without rebuilding other columns';

    public function handle(DashboardHarianSnapshotService $snapshots): int
    {
        $periods = $this->option('period')
            ? [$this->option('period')]
            : DB::table('dashboard_harian_snapshots')
                ->when($this->option('from'), fn ($query, $from) => $query->where('snapshot_period', '>=', $from))
                ->distinct()
                ->orderByDesc('snapshot_period')
                ->pluck('snapshot_period')
                ->map(fn ($period): string => (string) $period)
                ->all();

        $updated = 0;
        $skipped = [];
        $failed = [];
        $bar = $this->output->createProgressBar(count($periods));

        foreach ($periods as $period) {
            try {
                $rows = $snapshots->backfillRestrukMetrics($period, false);
                $updated += $rows;
                if ($rows === 0) {
                    $skipped[] = $period;
                }
            } catch (Throwable $e) {
                $failed[] = $period;
                $this->newLine();
                $this->warn("{$period}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // One bump for the whole run: every period above changed the same report family.
        if ($updated > 0) {
            ReportCacheVersion::bump('harian');
        }

        $this->info(sprintf('%d periods, %d rows written, %d without SSA restruk source, %d failed.', count($periods), $updated, count($skipped), count($failed)));
        if ($skipped !== []) {
            $this->line('Without SSA source: ' . implode(', ', $skipped));
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
