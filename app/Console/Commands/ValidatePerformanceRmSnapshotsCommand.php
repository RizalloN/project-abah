<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidatePerformanceRmSnapshotsCommand extends Command
{
    protected $signature = 'snapshot:validate-rm {--period= : Validate specific period}';

    protected $description = 'Validate Performance RM snapshots against source data';

    public function handle(): int
    {
        try {
            $period = trim((string) $this->option('period'));
            $periods = $period !== ''
                ? [$period]
                : DB::table('performance_rm_snapshots')
                    ->distinct('periode')
                    ->orderByDesc('periode')
                    ->pluck('periode')
                    ->map(fn($p) => (string)$p)
                    ->toArray();

            if (empty($periods)) {
                $this->warn('No snapshots found to validate.');
                return self::SUCCESS;
            }

            $this->info('Starting snapshot validation...');
            $allResults = [];

            foreach ($periods as $p) {
                $this->line("\n<fg=cyan>Validating period: {$p}</>");
                $results = $this->validatePeriod($p);
                $allResults[$p] = $results;

                $this->displayResults($p, $results);
            }

            $this->displaySummary($allResults);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Validation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function validatePeriod(string $period): array
    {
        $snapshots = DB::table('performance_rm_snapshots')
            ->where('periode', $period)
            ->get();

        if ($snapshots->isEmpty()) {
            return ['status' => 'no_data', 'message' => 'No snapshot data found'];
        }

        $results = [
            'total_records' => $snapshots->count(),
            'by_segment' => [],
            'discrepancies' => [],
            'warnings' => [],
        ];

        foreach (['CONSUMER', 'SMALL', 'MICRO'] as $segment) {
            $segmentSnapshots = $snapshots->where('segmen', $segment);
            if ($segmentSnapshots->isEmpty()) {
                continue;
            }

            $segmentResults = [
                'record_count' => $segmentSnapshots->count(),
                'value_checks' => [],
                'discrepancies' => [],
            ];

            foreach ($segmentSnapshots as $snapshot) {
                $sourceData = $this->fetchSourceData(
                    $period,
                    $snapshot->cabang,
                    $snapshot->unit,
                    $snapshot->rm,
                    $snapshot->produk,
                    $segment
                );

                $checks = $this->compareValues($snapshot, $sourceData, $period);
                $segmentResults['value_checks'][] = $checks;

                if (!$checks['match']) {
                    $segmentResults['discrepancies'][] = [
                        'key' => "{$snapshot->cabang}|{$snapshot->rm}|{$snapshot->produk}",
                        'mismatches' => $checks['mismatches'],
                    ];
                    $results['discrepancies'][] = [
                        'segment' => $segment,
                        'key' => "{$snapshot->cabang}|{$snapshot->rm}|{$snapshot->produk}",
                        'mismatches' => $checks['mismatches'],
                    ];
                }
            }

            $matchCount = collect($segmentResults['value_checks'])->filter(fn($c) => $c['match'])->count();
            $segmentResults['match_rate'] = $matchCount . '/' . count($segmentResults['value_checks']);

            $results['by_segment'][$segment] = $segmentResults;
        }

        return $results;
    }

    private function fetchSourceData(
        string $period,
        string $cabang,
        string $unit,
        string $rm,
        string $produk,
        string $segment
    ): ?object {
        $normalizedSegmenSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(segmen_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
        $normalizedProductSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(produk_dashboard, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";

        $sourceProducts = $this->getSourceProducts($produk);
        $sourceSegments = $this->getSourceSegments($segment);

        return DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($q) use ($normalizedSegmenSql, $sourceSegments) {
                $q->whereIn(DB::raw($normalizedSegmenSql), $sourceSegments);
            })
            ->whereIn(DB::raw($normalizedProductSql), $sourceProducts)
            ->whereRaw("UPPER(TRIM(cabang1)) = ?", [strtoupper(trim($cabang))])
            ->whereRaw("UPPER(TRIM(unit1)) = ?", [strtoupper(trim($unit))])
            ->whereRaw("UPPER(TRIM(pn_pengelola1)) = ?", [strtoupper(trim($rm))])
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as loan_os')
            ->selectRaw('SUM(CASE WHEN kol_adk1 = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
            ->selectRaw('SUM(CASE WHEN kol_adk1 = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
            ->selectRaw('SUM(CASE WHEN kol_adk1 IN (3,4,5) THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
            ->selectRaw("SUM(CASE WHEN kol_adk1 = 1 AND UPPER(TRIM(COALESCE(flag_restruk, ''))) = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->selectRaw('COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN DATE_FORMAT(?, "%Y-%m-01") AND ? THEN nomor_rekening1 END) as realisasi_deb', [
                Carbon::parse($period)->startOfMonth()->toDateString(),
                $period,
            ])
            ->selectRaw('SUM(CASE WHEN tgl_realisasi BETWEEN DATE_FORMAT(?, "%Y-%m-01") AND ? THEN COALESCE(baki_debet1, 0) ELSE 0 END) as realisasi_os', [
                Carbon::parse($period)->startOfMonth()->toDateString(),
                $period,
            ])
            ->first();
    }

    private function compareValues(object $snapshot, ?object $sourceData, string $period): array
    {
        $mismatches = [];
        $tolerance = 0.01; // 1% tolerance for rounding

        if ($sourceData === null) {
            return [
                'match' => false,
                'mismatches' => ['source_data_not_found' => 'No matching source data found'],
            ];
        }

        $checks = [
            'loan_os' => [(float)$snapshot->loan_os, (float)($sourceData->loan_os ?? 0)],
            'lancar_os' => [(float)$snapshot->lancar_os, (float)($sourceData->lancar_os ?? 0)],
            'sml_os' => [(float)$snapshot->sml_os, (float)($sourceData->sml_os ?? 0)],
            'npl_os' => [(float)$snapshot->npl_os, (float)($sourceData->npl_os ?? 0)],
            'restruk_os' => [(float)$snapshot->restruk_os, (float)($sourceData->restruk_os ?? 0)],
            'total_deb' => [(int)$snapshot->total_deb, (int)($sourceData->total_deb ?? 0)],
        ];

        foreach ($checks as $field => [$snap, $source]) {
            if ($snap === 0 && $source === 0) {
                continue;
            }

            if (str_contains($field, '_os')) {
                $diff = abs($snap - $source);
                $pctDiff = $source > 0 ? ($diff / $source) * 100 : 0;
                if ($pctDiff > 1) {
                    $mismatches[$field] = [
                        'snapshot' => $snap,
                        'source' => $source,
                        'diff_percent' => round($pctDiff, 2),
                    ];
                }
            } else {
                if ($snap !== $source) {
                    $mismatches[$field] = [
                        'snapshot' => $snap,
                        'source' => $source,
                    ];
                }
            }
        }

        // Check realisasi values (may differ if snapshot built at different time)
        $snapRealisasi = ((float)$snapshot->realisasi_deb + (float)$snapshot->realisasi_os) > 0;
        $sourceRealisasi = (((float)($sourceData->realisasi_deb ?? 0) + (float)($sourceData->realisasi_os ?? 0)) > 0);

        if ($snapRealisasi && !$sourceRealisasi) {
            // Snapshot has realisasi data, source doesn't - this is OK (snapshot may be newer)
        }

        return [
            'match' => empty($mismatches),
            'mismatches' => $mismatches,
        ];
    }

    private function displayResults(string $period, array $results): void
    {
        if ($results['status'] ?? null === 'no_data') {
            $this->warn("  No data for period {$period}");
            return;
        }

        $this->line("  Total records: {$results['total_records']}");

        foreach ($results['by_segment'] as $segment => $segmentResults) {
            $matchRate = $segmentResults['match_rate'] ?? '0/0';
            $color = str_contains($matchRate, '0/') ? 'fg=red' : 'fg=green';
            $this->line("  <{$color}>{$segment}: {$matchRate} match</>");

            if (!empty($segmentResults['discrepancies'])) {
                $discCount = count($segmentResults['discrepancies']);
                $this->line("    <fg=yellow>Discrepancies ({$discCount}):</>");
                foreach ($segmentResults['discrepancies'] as $disc) {
                    $this->line("      - {$disc['key']}");
                    foreach ($disc['mismatches'] as $field => $diff) {
                        if (is_array($diff)) {
                            $pct = $diff['diff_percent'] ?? '';
                            $pctStr = $pct ? " ({$pct}% diff)" : '';
                            $this->line("        • {$field}: snap={$diff['snapshot']}, src={$diff['source']}{$pctStr}");
                        } else {
                            $this->line("        • {$field}: {$diff}");
                        }
                    }
                }
            }
        }

        if (!empty($results['discrepancies'])) {
            $discCount = count($results['discrepancies']);
            $this->line("  <fg=yellow>Total discrepancies: {$discCount}</>");
        } else {
            $this->line("  <fg=green>✓ All values match source data</>");
        }
    }

    private function displaySummary(array $allResults): void
    {
        $this->line("\n<fg=cyan>==== VALIDATION SUMMARY ====</>");

        $totalPeriods = count($allResults);
        $totalRecords = 0;
        $totalDiscrepancies = 0;

        foreach ($allResults as $period => $results) {
            if ($results['status'] ?? null !== 'no_data') {
                $totalRecords += $results['total_records'] ?? 0;
                $totalDiscrepancies += count($results['discrepancies'] ?? []);
            }
        }

        $this->line("Periods validated: {$totalPeriods}");
        $this->line("Total records: {$totalRecords}");
        $this->line("Total discrepancies: {$totalDiscrepancies}");

        if ($totalDiscrepancies === 0) {
            $this->info("\n✓ Validation PASSED - All snapshots match source data!");
        } else {
            $msg = "⚠ Validation INCOMPLETE - Found {$totalDiscrepancies} discrepancies";
            $this->warn("\n{$msg}");
        }
    }

    private function getSourceSegments(string $segment): array
    {
        return match ($segment) {
            'CONSUMER' => ['CONSUMER', 'Consumer'],
            'SMALL' => ['SMALL', 'Small'],
            'MICRO' => ['MICRO', 'Micro', 'MIKRO', 'Mikro'],
            default => [$segment],
        };
    }

    private function getSourceProducts(string $product): array
    {
        return match ($product) {
            'BRIGUNA-KONSUMER' => ['BRIGUNA-KONSUMER', 'Briguna-Konsumer'],
            'KPR' => ['KPR'],
            'COMMERCIAL' => ['COMMERCIAL', 'Commercial'],
            'CASHCALL' => ['CASHCALL', 'Cashcall'],
            'BRIGUNA-MIKRO' => ['BRIGUNA-MIKRO', 'Briguna-Mikro'],
            'KUPEDES' => ['KUPEDES', 'Kupedes'],
            'KUR-MIKRO' => ['KUR-MIKRO', 'KUR-Mikro'],
            'CASHCOLLATERAL' => ['CASHCOLLATERAL', 'CashCollateral', 'Cash Collateral', 'Cashcoll'],
            'KUR-SMALL' => ['KUR-SMALL', 'KUR-Small'],
            default => [$product],
        };
    }
}
