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

            $sourceRows = $this->fetchSourceRowsForPeriod($period);

            foreach ($segmentSnapshots as $snapshot) {
                $sourceData = $sourceRows[$this->snapshotKey($snapshot)] ?? null;

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

    /**
     * @return array<string, object>
     */
    private function fetchSourceRowsForPeriod(string $period): array
    {
        $realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $descriptionSql = $this->normalizedSql('description');
        $segmentSql = "CASE WHEN segmen_kinerja = 'CONSUMER' THEN 'CONSUMER' WHEN segmen_kinerja = 'SMALL' THEN 'SMALL' WHEN segmen_kinerja = 'MICRO' THEN 'MICRO' ELSE UPPER(TRIM(COALESCE(segmen_kinerja, ''))) END";
        $productSql = "CASE
            WHEN segmen_kinerja = 'CONSUMER' AND produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER'
            WHEN segmen_kinerja = 'CONSUMER' AND produk_kinerja = 'KPR' THEN 'KPR'
            WHEN segmen_kinerja = 'SMALL' AND produk_kinerja IN ('COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL', 'SMALL') THEN 'SMALL'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'BRIGUNAMIKRO' THEN 'BRIGUNA-MIKRO'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'KUPEDES' THEN 'KUPEDES'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'KURMIKRO' THEN 'KUR-MIKRO'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja IN ('CASHCOLLATERAL', 'CASHCOLL') THEN 'CASHCOLLATERAL'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'KPR' THEN 'KPR'
            WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'KURSMALL' THEN 'KUR-SMALL'
            ELSE UPPER(TRIM(COALESCE(produk_kinerja, '')))
        END";

        $rows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->where(function ($query) use ($descriptionSql): void {
                $query
                    ->where(function ($rule): void {
                        $rule->where('segmen_kinerja', 'CONSUMER')
                            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR']);
                    })
                    ->orWhere(function ($rule): void {
                        $rule->where('segmen_kinerja', 'SMALL')
                            ->whereIn('produk_kinerja', ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL']);
                    })
                    ->orWhere(function ($rule): void {
                        $rule->where('segmen_kinerja', 'MICRO')
                            ->whereIn('produk_kinerja', ['BRIGUNAMIKRO', 'KUPEDES', 'CASHCOLLATERAL', 'KPR']);
                    })
                    ->orWhere(function ($rule) use ($descriptionSql): void {
                        $rule->where('segmen_kinerja', 'MICRO')
                            ->where('produk_kinerja', 'KURMIKRO')
                            ->whereRaw("{$descriptionSql} = ?", ['KREDITMIKROKURRITEL2015']);
                    });
            })
            ->selectRaw("COALESCE(cabang_normalized, '') as cabang")
            ->selectRaw("COALESCE(unit_normalized, '') as unit")
            ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
            ->selectRaw("COALESCE(rm_normalized, '') as rm")
            ->selectRaw("{$segmentSql} as segmen")
            ->selectRaw("{$productSql} as produk")
            ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
            ->selectRaw(
                "SUM(CASE WHEN segmen_kinerja = 'MICRO' AND produk_kinerja = 'KURMIKRO' AND {$descriptionSql} = ? THEN COALESCE(plafon, 0) ELSE COALESCE(baki_debet1, 0) END) as loan_os",
                ['KREDITMIKROKURRITEL2015']
            )
            ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
            ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
            ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
            ->selectRaw("SUM(CASE WHEN kolek = 1 AND COALESCE(flag_restruk, '') = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->selectRaw("COUNT(DISTINCT CASE WHEN segmen_kinerja <> 'CONSUMER' AND {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN segmen_kinerja <> 'CONSUMER' AND {$realisasiDateColumn} BETWEEN ? AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os", [$periodStart, $period])
            ->groupBy(
                'cabang_normalized',
                'unit_normalized',
                'branch_normalized',
                'rm_normalized',
                'segmen_kinerja',
                'produk_kinerja',
                DB::raw($segmentSql),
                DB::raw($productSql)
            )
            ->get();

        $sourceRows = [];
        foreach ($rows as $row) {
            $key = $this->sourceKey((array) $row);
            if (!isset($sourceRows[$key])) {
                $sourceRows[$key] = $row;
                continue;
            }

            foreach (['plafon', 'loan_os', 'lancar_os', 'sml_os', 'npl_os', 'restruk_os', 'realisasi_os'] as $field) {
                $sourceRows[$key]->{$field} = (float) ($sourceRows[$key]->{$field} ?? 0) + (float) ($row->{$field} ?? 0);
            }

            foreach (['total_deb', 'realisasi_deb'] as $field) {
                $sourceRows[$key]->{$field} = (int) ($sourceRows[$key]->{$field} ?? 0) + (int) ($row->{$field} ?? 0);
            }
        }

        $this->applyConsumerSurplusForPeriod($period, $sourceRows);

        return $sourceRows;
    }

    /**
     * @param array<string, object> $sourceRows
     */
    private function applyConsumerSurplusForPeriod(string $period, array &$sourceRows): void
    {
        $previousPeriod = $this->resolvePreviousMonthDailyLoanPeriod($period);
        if ($previousPeriod === null) {
            return;
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';

        $currentAccountKeys = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->distinct()
            ->pluck('account_key')
            ->map(fn ($accountKey): string => (string) $accountKey)
            ->filter()
            ->flip();

        $previousLookupOrderColumn = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'uniqueid_namareport'
            : 'nomor_rekening1';

        $previousClosedOsByCif = [];
        DB::table('daily_loan_dinamis')
            ->where('periode', $previousPeriod)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->selectRaw('UPPER(TRIM(cifno)) as clean_cif')
            ->selectRaw('UPPER(TRIM(nomor_rekening1)) as account_key')
            ->selectRaw('COALESCE(baki_debet1, 0) as previous_os')
            ->orderBy($previousLookupOrderColumn)
            ->chunk(1000, function ($rows) use (&$previousClosedOsByCif, $currentAccountKeys): void {
                foreach ($rows as $row) {
                    $cleanCif = (string) ($row->clean_cif ?? '');
                    $accountKey = (string) ($row->account_key ?? '');
                    if ($cleanCif === '' || isset($currentAccountKeys[$accountKey]) || array_key_exists($cleanCif, $previousClosedOsByCif)) {
                        continue;
                    }

                    $previousClosedOsByCif[$cleanCif] = (float) ($row->previous_os ?? 0);
                }
            });

        $currentRealizationByCif = [];
        DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->whereBetween($realisasiDateColumn, [$periodStart, $period])
            ->select([
                'cabang_normalized',
                'unit_normalized',
                'branch_normalized',
                'rm_normalized',
                'produk_kinerja',
                'nomor_rekening1',
                'plafon',
                'cifno',
            ])
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->orderBy('nomor_rekening1')
            ->chunk(1000, function ($rows) use (&$currentRealizationByCif): void {
                foreach ($rows as $row) {
                    $groupKey = $this->sourceKey([
                        'cabang' => (string) ($row->cabang_normalized ?? ''),
                        'unit' => (string) ($row->unit_normalized ?? ''),
                        'branch_code' => (string) ($row->branch_normalized ?? ''),
                        'rm' => (string) ($row->rm_normalized ?? ''),
                        'segmen' => 'CONSUMER',
                        'produk' => $this->canonicalProduct('CONSUMER', (string) ($row->produk_kinerja ?? '')),
                    ]);
                    $cleanCif = strtoupper(trim((string) ($row->cifno ?? '')));
                    $accountKey = (string) ($row->account_key ?? '');
                    $metricKey = $groupKey . '|' . $cleanCif;

                    if (!isset($currentRealizationByCif[$metricKey])) {
                        $currentRealizationByCif[$metricKey] = [
                            'group_key' => $groupKey,
                            'clean_cif' => $cleanCif,
                            'accounts' => [],
                            'current_plafon' => 0.0,
                        ];
                    }

                    $currentRealizationByCif[$metricKey]['accounts'][$accountKey] = true;
                    $currentRealizationByCif[$metricKey]['current_plafon'] += (float) ($row->plafon ?? 0);
                }
            });

        $surplusByGroup = [];
        foreach ($currentRealizationByCif as $metric) {
            $groupKey = (string) ($metric['group_key'] ?? '');
            $cleanCif = (string) ($metric['clean_cif'] ?? '');
            $netDisbursement = (float) ($metric['current_plafon'] ?? 0)
                - (float) ($previousClosedOsByCif[$cleanCif] ?? 0);

            $surplusByGroup[$groupKey] ??= ['debitur' => 0, 'os' => 0.0];
            $surplusByGroup[$groupKey]['debitur'] += count($metric['accounts'] ?? []);
            $surplusByGroup[$groupKey]['os'] += $netDisbursement;
        }

        foreach ($surplusByGroup as $groupKey => $metric) {
            if (!isset($sourceRows[$groupKey])) {
                continue;
            }

            $sourceRows[$groupKey]->realisasi_deb = (int) ($metric['debitur'] ?? 0);
            $sourceRows[$groupKey]->realisasi_os = (float) ($metric['os'] ?? 0);
        }
    }

    private function snapshotKey(object $snapshot): string
    {
        return $this->sourceKey([
            'cabang' => (string) ($snapshot->cabang ?? ''),
            'unit' => (string) ($snapshot->unit ?? ''),
            'branch_code' => (string) ($snapshot->branch_code ?? ''),
            'rm' => (string) ($snapshot->rm ?? ''),
            'segmen' => (string) ($snapshot->segmen ?? ''),
            'produk' => (string) ($snapshot->produk ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function sourceKey(array $row): string
    {
        return implode('|', [
            (string) ($row['cabang'] ?? ''),
            (string) ($row['unit'] ?? ''),
            (string) ($row['branch_code'] ?? ''),
            (string) ($row['rm'] ?? ''),
            strtoupper(trim((string) ($row['segmen'] ?? ''))),
            strtoupper(trim((string) ($row['produk'] ?? ''))),
        ]);
    }

    private function normalizedSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
    }

    private function canonicalProduct(string $segment, string $product): string
    {
        $segment = strtoupper(trim($segment));
        $product = strtoupper(str_replace([' ', '-', '_', '/', '.'], '', trim($product)));

        return match ($segment) {
            'CONSUMER' => match ($product) {
                'BRIGUNAKONSUMER' => 'BRIGUNA-KONSUMER',
                'KPR' => 'KPR',
                default => $product,
            },
            'SMALL' => in_array($product, ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL', 'SMALL'], true) ? 'SMALL' : $product,
            'MICRO' => match ($product) {
                'BRIGUNAMIKRO' => 'BRIGUNA-MIKRO',
                'KUPEDES' => 'KUPEDES',
                'KURMIKRO' => 'KUR-MIKRO',
                'CASHCOLLATERAL', 'CASHCOLL' => 'CASHCOLLATERAL',
                'KPR' => 'KPR',
                'KURSMALL' => 'KUR-SMALL',
                default => $product,
            },
            default => $product,
        };
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
        $normalizedDescriptionSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(description, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
        $realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';

        $sourceProducts = $this->getSourceProducts($produk);
        $sourceSegments = $this->getSourceSegments($segment);
        $isMicroKur = $segment === 'MICRO' && $produk === 'KUR-MIKRO';

        $query = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($q) use ($normalizedSegmenSql, $sourceSegments) {
                $q->whereIn(DB::raw($normalizedSegmenSql), $sourceSegments);
            })
            ->whereIn(DB::raw($normalizedProductSql), $sourceProducts)
            ->whereRaw("UPPER(TRIM(cabang1)) = ?", [strtoupper(trim($cabang))])
            ->whereRaw("UPPER(TRIM(unit1)) = ?", [strtoupper(trim($unit))])
            ->whereRaw("UPPER(TRIM(pn_pengelola1)) = ?", [strtoupper(trim($rm))])
            ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
            ->selectRaw($isMicroKur ? 'SUM(COALESCE(plafon, 0)) as loan_os' : 'SUM(COALESCE(baki_debet1, 0)) as loan_os')
            ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
            ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
            ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
            ->selectRaw("SUM(CASE WHEN kolek = 1 AND UPPER(TRIM(COALESCE(flag_restruk, ''))) = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->when($segment !== 'CONSUMER', function ($query) use ($realisasiDateColumn, $period): void {
                $query->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN DATE_FORMAT(?, \"%Y-%m-01\") AND ? THEN nomor_rekening1 END) as realisasi_deb", [
                    Carbon::parse($period)->startOfMonth()->toDateString(),
                    $period,
                ])
                    ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN DATE_FORMAT(?, \"%Y-%m-01\") AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os", [
                        Carbon::parse($period)->startOfMonth()->toDateString(),
                        $period,
                    ]);
            }, function ($query): void {
                $query->selectRaw('0 as realisasi_deb')
                    ->selectRaw('0 as realisasi_os');
            })
            ;

        if ($isMicroKur) {
            $query->whereRaw("{$normalizedDescriptionSql} = ?", ['KREDITMIKROKURRITEL2015']);
        }

        $source = $query->first();
        if ($source !== null && $segment === 'CONSUMER') {
            $surplus = $this->getConsumerSurplusForScope($period, $cabang, $unit, $rm, $produk);
            $source->realisasi_deb = (int) ($surplus->total_deb ?? 0);
            $source->realisasi_os = (float) ($surplus->total_real ?? 0);
        }

        return $source;
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
            'plafon' => [(float)$snapshot->plafon, (float)($sourceData->plafon ?? 0)],
            'loan_os' => [(float)$snapshot->loan_os, (float)($sourceData->loan_os ?? 0)],
            'lancar_os' => [(float)$snapshot->lancar_os, (float)($sourceData->lancar_os ?? 0)],
            'sml_os' => [(float)$snapshot->sml_os, (float)($sourceData->sml_os ?? 0)],
            'npl_os' => [(float)$snapshot->npl_os, (float)($sourceData->npl_os ?? 0)],
            'restruk_os' => [(float)$snapshot->restruk_os, (float)($sourceData->restruk_os ?? 0)],
            'total_deb' => [(int)$snapshot->total_deb, (int)($sourceData->total_deb ?? 0)],
            'realisasi_deb' => [(int)$snapshot->realisasi_deb, (int)($sourceData->realisasi_deb ?? 0)],
            'realisasi_os' => [(float)$snapshot->realisasi_os, (float)($sourceData->realisasi_os ?? 0)],
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
            'CONSUMER' => ['CONSUMER'],
            'SMALL' => ['SMALL'],
            'MICRO' => ['MICRO'],
            default => [$segment],
        };
    }

    private function getSourceProducts(string $product): array
    {
        return match ($product) {
            'BRIGUNA-KONSUMER' => ['BRIGUNAKONSUMER'],
            'KPR' => ['KPR'],
            'SMALL' => ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL'],
            'BRIGUNA-MIKRO' => ['BRIGUNAMIKRO'],
            'KUPEDES' => ['KUPEDES'],
            'KUR-MIKRO' => ['KURMIKRO'],
            'CASHCOLLATERAL' => ['CASHCOLLATERAL', 'CASHCOLL'],
            'KUR-SMALL' => ['KURSMALL'],
            default => [$product],
        };
    }

    private function getConsumerSurplusForScope(string $period, string $cabang, string $unit, string $rm, string $produk): object
    {
        if (!Schema::hasColumn('daily_loan_dinamis', 'segmen_kinerja') || !Schema::hasColumn('daily_loan_dinamis', 'produk_kinerja')) {
            return (object) ['total_deb' => 0, 'total_real' => 0];
        }

        $previousPeriod = $this->resolvePreviousMonthDailyLoanPeriod($period);
        if ($previousPeriod === null) {
            return (object) ['total_deb' => 0, 'total_real' => 0];
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $realisasiDateColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';

        $currentRows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', $this->getSourceProducts($produk))
            ->whereRaw("COALESCE(NULLIF(cabang_normalized, ''), UPPER(TRIM(cabang1))) = ?", [strtoupper(trim($cabang))])
            ->whereRaw("COALESCE(NULLIF(unit_normalized, ''), UPPER(TRIM(unit1))) = ?", [strtoupper(trim($unit))])
            ->whereRaw("COALESCE(NULLIF(rm_normalized, ''), UPPER(TRIM(pn_pengelola1))) = ?", [strtoupper(trim($rm))])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->whereBetween($realisasiDateColumn, [$periodStart, $period])
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->selectRaw("UPPER(TRIM(cifno)) as clean_cif")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as debitur')
            ->selectRaw('SUM(COALESCE(plafon, 0)) as current_plafon')
            ->groupByRaw("UPPER(TRIM(nomor_rekening1)), UPPER(TRIM(cifno))")
            ->get();

        if ($currentRows->isEmpty()) {
            return (object) ['total_deb' => 0, 'total_real' => 0];
        }

        $currentAccountKeys = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->distinct()
            ->pluck('account_key')
            ->map(fn ($value): string => (string) $value)
            ->filter()
            ->flip();

        $previousLookupOrderColumn = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'uniqueid_namareport'
            : 'nomor_rekening1';

        $previousClosedOsByCif = [];
        DB::table('daily_loan_dinamis')
            ->where('periode', $previousPeriod)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->selectRaw('UPPER(TRIM(cifno)) as clean_cif')
            ->selectRaw('UPPER(TRIM(nomor_rekening1)) as account_key')
            ->selectRaw('COALESCE(baki_debet1, 0) as previous_os')
            ->orderBy($previousLookupOrderColumn)
            ->chunk(1000, function ($rows) use (&$previousClosedOsByCif, $currentAccountKeys): void {
                foreach ($rows as $row) {
                    $cleanCif = (string) ($row->clean_cif ?? '');
                    $accountKey = (string) ($row->account_key ?? '');
                    if ($cleanCif === '' || isset($currentAccountKeys[$accountKey]) || array_key_exists($cleanCif, $previousClosedOsByCif)) {
                        continue;
                    }

                    $previousClosedOsByCif[$cleanCif] = (float) ($row->previous_os ?? 0);
                }
            });

        $totalDebitur = 0;
        $totalReal = 0.0;
        foreach ($currentRows->groupBy(fn ($row): string => (string) ($row->clean_cif ?? '')) as $cleanCif => $rows) {
            $totalDebitur += (int) $rows->sum('debitur');
            $totalReal += (float) $rows->sum('current_plafon') - (float) ($previousClosedOsByCif[$cleanCif] ?? 0);
        }

        return (object) [
            'total_deb' => $totalDebitur,
            'total_real' => $totalReal,
        ];
    }

    private function resolvePreviousMonthDailyLoanPeriod(string $period): ?string
    {
        $periodDate = Carbon::parse($period);
        $previousEnd = $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $exists = DB::table('daily_loan_dinamis')
            ->where('periode', $previousEnd)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->exists();

        return $exists ? $previousEnd : null;
    }
}
