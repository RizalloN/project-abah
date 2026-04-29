<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validate shadow columns completion status untuk daily_loan_dinamis
 * 
 * Gunakan command ini untuk:
 * - Verifikasi shadow columns terisi 100%
 * - Monitoring progress backfill
 * - Detect data inconsistencies
 * - Pre-flight check sebelum snapshot rebuild
 */
class ValidateShadowColumnsCommand extends Command
{
    protected $signature = 'shadow:validate
        {--periods= : Comma-separated periods (default: 2026-04-25,2026-04-26)}
        {--watch : Monitor with auto-refresh every 5 seconds}
        {--verbose : Show detailed row samples}
        {--json : Output as JSON for automation}
    ';

    protected $description = 'Validate shadow columns completion status and detect data inconsistencies';

    private array $validationResults = [];

    public function handle(): int
    {
        try {
            $periods = $this->getPeriods();
            $watch = (bool) $this->option('watch');
            $verbose = (bool) $this->option('verbose');
            $jsonOutput = (bool) $this->option('json');

            do {
                $this->validatePeriods($periods, $verbose, $jsonOutput);

                if ($watch) {
                    $this->line("\n📊 Monitoring... Next refresh in 5 seconds (Ctrl+C to stop)\n");
                    sleep(5);
                    $this->output->clear();
                }
            } while ($watch);

            return $this->hasIssues() ? self::FAILURE : self::SUCCESS;

        } catch (Throwable $e) {
            $this->error("Validation failed: {$e->getMessage()}");
            Log::error('ValidateShadowColumnsCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    private function getPeriods(): array
    {
        $periodInput = trim((string) $this->option('periods'));
        if (empty($periodInput)) {
            return ['2026-04-25', '2026-04-26'];
        }
        return array_map('trim', explode(',', $periodInput));
    }

    private function validatePeriods(array $periods, bool $verbose, bool $jsonOutput): void
    {
        if (!$jsonOutput) {
            $this->info('╔════════════════════════════════════════════════════════════════╗');
            $this->info('║         Shadow Columns Validation Report                       ║');
            $this->info('╚════════════════════════════════════════════════════════════════╝');
            $this->newLine();
        }

        $results = [];

        foreach ($periods as $period) {
            $result = $this->validatePeriod($period, $verbose);
            $results[$period] = $result;
            $this->validationResults[$period] = $result;

            if (!$jsonOutput) {
                $this->displayPeriodResult($period, $result);
            }
        }

        if ($jsonOutput) {
            $this->line(json_encode(['periods' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->displaySummary($results);
        }
    }

    private function validatePeriod(string $period, bool $verbose): array
    {
        $cacheKey = "shadow_validation:{$period}";

        if (!$this->option('watch')) {
            $cached = cache()->get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $totalRows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->count();

        if ($totalRows === 0) {
            $result = [
                'period' => $period,
                'total_rows' => 0,
                'status' => 'EMPTY',
                'columns' => [],
                'issues' => ['No data found for this period'],
            ];
            cache()->put($cacheKey, $result, 300);
            return $result;
        }

        $shadowColumns = [
            'segmen_kinerja',
            'produk_kinerja',
            'cabang_normalized',
            'unit_normalized',
            'branch_normalized',
            'rm_normalized',
            'pn_pemutus_normalized',
            'cifno_clean',
        ];

        $columnStats = [];
        $issues = [];

        $stats = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw(implode(', ', array_map(
                fn ($col) => "COUNT({$col}) as `filled_{$col}`",
                $shadowColumns
            )))
            ->first();

        foreach ($shadowColumns as $column) {
            $filledCount = $stats->{"filled_{$column}"};
            $nullCount = $totalRows - $filledCount;
            $fillPercentage = $totalRows > 0 ? (100.0 * $filledCount / $totalRows) : 100.0;

            $columnStats[$column] = [
                'filled' => $filledCount,
                'null' => $nullCount,
                'percentage' => round($fillPercentage, 2),
                'status' => $fillPercentage === 100.0 ? '✓' : '⚠',
            ];

            if ($fillPercentage < 100.0) {
                $issues[] = "{$column}: {$nullCount} NULL values (" . round($fillPercentage, 2) . "% filled)";
            }

            if ($verbose) {
                $this->displayColumnSamples($period, $column, $filledCount === 0);
            }
        }

        $consistencyIssues = $this->checkDataConsistency($period);
        $issues = array_merge($issues, $consistencyIssues);

        $allFilled = count(array_filter(
            $columnStats,
            fn ($stat) => $stat['percentage'] === 100.0
        )) === count($shadowColumns);

        $result = [
            'period' => $period,
            'total_rows' => $totalRows,
            'status' => $allFilled ? 'OK' : ($issues ? 'WARNING' : 'EMPTY'),
            'columns' => $columnStats,
            'issues' => $issues,
        ];

        if (!$this->option('watch')) {
            cache()->put($cacheKey, $result, 300);
        }

        return $result;
    }

    private function checkDataConsistency(string $period): array
    {
        $issues = [];

        // Check for rows with segmen_dashboard but segmen_kinerja is null
        $inconsistentSegmen = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('segmen_dashboard')
            ->whereNull('segmen_kinerja')
            ->count();

        if ($inconsistentSegmen > 0) {
            $issues[] = "Data inconsistency: {$inconsistentSegmen} rows have segmen_dashboard but segmen_kinerja is NULL";
        }

        // Check for rows with produk_dashboard but produk_kinerja is null
        $inconsistentProduk = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('produk_dashboard')
            ->whereNull('produk_kinerja')
            ->count();

        if ($inconsistentProduk > 0) {
            $issues[] = "Data inconsistency: {$inconsistentProduk} rows have produk_dashboard but produk_kinerja is NULL";
        }

        // Check cifno_clean format (should be numeric-only)
        $invalidCifno = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('cifno_clean')
            ->where(DB::raw('cifno_clean REGEXP "[^0-9]"'), true)
            ->count();

        if ($invalidCifno > 0) {
            $issues[] = "Data quality: {$invalidCifno} rows have non-numeric cifno_clean values";
        }

        return $issues;
    }

    private function displayPeriodResult(string $period, array $result): void
    {
        $statusIcon = match ($result['status']) {
            'OK' => '✓',
            'WARNING' => '⚠',
            'EMPTY' => '✗',
            default => '?',
        };

        $statusColor = match ($result['status']) {
            'OK' => 'green',
            'WARNING' => 'yellow',
            'EMPTY' => 'red',
            default => 'white',
        };

        $this->line("<fg={$statusColor}>{$statusIcon}</> Period: <fg=cyan>{$period}</> (Total: <fg=yellow>" . number_format($result['total_rows']) . "</> rows)");

        if ($result['total_rows'] === 0) {
            $this->line("   No data found");
            $this->newLine();
            return;
        }

        $tableRows = [];
        foreach ($result['columns'] as $column => $stats) {
            $statusIcon = $stats['status'] === '✓' ? '✓' : '⚠';
            $statusColor = $stats['status'] === '✓' ? 'green' : 'yellow';

            $tableRows[] = [
                "<fg={$statusColor}>{$statusIcon}</>",
                $column,
                number_format($stats['filled']),
                number_format($stats['null']),
                $stats['percentage'] . '%',
            ];
        }

        $this->table(['', 'Column', 'Filled', 'NULL', '% Complete'], $tableRows);

        if (!empty($result['issues'])) {
            $this->warn('Issues detected:');
            foreach ($result['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }

        $this->newLine();
    }

    private function displayColumnSamples(string $period, string $column, bool $isEmpty): void
    {
        if ($isEmpty) {
            return;
        }

        $samples = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull($column)
            ->select('id', $column, 'periode')
            ->limit(3)
            ->get();

        if ($samples->isEmpty()) {
            return;
        }

        $this->line("   <fg=blue>Sample values for {$column}:</>");
        foreach ($samples as $sample) {
            $this->line("     - ID {$sample->id}: {$sample->{$column}}");
        }
    }

    private function displaySummary(array $results): void
    {
        $this->newLine();
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║                      SUMMARY                                  ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');

        $totalRows = 0;
        $totalOk = 0;
        $totalWarnings = 0;
        $totalEmpty = 0;

        foreach ($results as $result) {
            $totalRows += $result['total_rows'];
            match ($result['status']) {
                'OK' => $totalOk++,
                'WARNING' => $totalWarnings++,
                'EMPTY' => $totalEmpty++,
                default => null,
            };
        }

        $statusLine = "Periods: <fg=green>{$totalOk}</> OK";
        if ($totalWarnings > 0) {
            $statusLine .= ", <fg=yellow>{$totalWarnings}</> with warnings";
        }
        if ($totalEmpty > 0) {
            $statusLine .= ", <fg=red>{$totalEmpty}</> empty";
        }

        $this->line($statusLine);
        $this->line("Total rows analyzed: <fg=cyan>" . number_format($totalRows) . "</>");

        if ($totalOk === count($results) && $totalRows > 0) {
            $this->line("\n<fg=green>✓ All shadow columns are properly filled!</>");
            $this->line("  Ready to rebuild snapshots: <fg=cyan>php artisan snapshot:rebuild-rm --period=YYYY-MM-DD</>");
        } elseif ($totalWarnings > 0) {
            $this->line("\n<fg=yellow>⚠ Some columns need backfill. Run:</>");
            $this->line("  <fg=cyan>php artisan shadow:backfill --periods=YYYY-MM-DD</>");
        }

        $this->newLine();
    }

    private function hasIssues(): bool
    {
        foreach ($this->validationResults as $result) {
            if ($result['status'] !== 'OK' && $result['total_rows'] > 0) {
                return true;
            }
        }
        return false;
    }
}
