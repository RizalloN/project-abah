<?php

namespace App\Console\Commands;

use App\Support\SnapshotIntegrityGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ValidateSnapshotDataIntegrityCommand extends Command
{
    protected $signature = 'snapshot:validate-integrity {--period= : Validate specific period} {--segment= : Validate specific segment} {--report= : Validate specific report (all_snapshots|snapshot_guard|performance_rm|ssa_simpanan|dashboard_simpanan|dashboard_harian|dormant_account)} {--sample : Sample-based validation (faster)}';

    protected $description = 'Validate snapshot data integrity across all materialized snapshot tables';

    public function handle(): int
    {
        try {
            $period = trim((string) $this->option('period')) ?: null;
            $segment = trim((string) $this->option('segment')) ?: null;
            $report = trim((string) $this->option('report')) ?: null;
            $useSample = (bool) $this->option('sample');

            $this->info('Starting snapshot data integrity validation...');

            if (in_array($report, ['all_snapshots', 'snapshot_guard'], true)) {
                $this->validateSnapshotGuard($period, $useSample, null);
            } elseif ($report === 'ssa_simpanan') {
                $this->validateSnapshotGuard($period, $useSample, $report);
                $this->validateSsaSimpanan($period, $useSample);
            } elseif ($report === 'dashboard_simpanan') {
                $this->validateSnapshotGuard($period, $useSample, $report);
                $this->validateDashboardSimpanan($period, $useSample);
            } elseif ($report === 'dashboard_harian') {
                $this->validateSnapshotGuard($period, $useSample, $report);
                $this->validateDashboardHarian($period, $useSample);
            } elseif ($report === 'dormant_account') {
                $this->validateSnapshotGuard($period, $useSample, $report);
                $this->validateDormantAccount($period, $useSample);
            } else {
                $this->validateSnapshotGuard($period, $useSample, $report ?: 'performance_rm');
                // Default: validate performance_rm
                if ($useSample) {
                    $this->validateWithSampling($period, $segment);
                } else {
                    $this->validateAggregate($period, $segment);
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Validation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function validateSnapshotGuard(?string $period, bool $useSample, ?string $report): void
    {
        $this->line('<fg=cyan>== SNAPSHOT GUARD VALIDATION ==</>');

        $guard = app(SnapshotIntegrityGuard::class);
        $reportTables = [
            'dashboard_harian' => ['dashboard_harian_snapshots'],
            'dashboard_simpanan' => ['dashboard_simpanan_snapshots', 'dashboard_simpanan_branch_snapshots'],
            'ssa_simpanan' => ['ssa_simpanan_snapshots'],
            'dormant_account' => ['rekening_dormant_snapshots'],
            'performance_rm' => ['performance_rm_snapshots', 'performance_rm_cabang_snapshots'],
        ];

        $tables = $report && isset($reportTables[$report])
            ? $reportTables[$report]
            : $guard->snapshotTables();
        $limit = $useSample ? 3 : 10;

        foreach ($tables as $table) {
            foreach ($guard->inspectTable($table, $period, $limit) as $result) {
                $status = (string) ($result['status'] ?? 'skipped');
                $line = sprintf(
                    '  %s | period=%s | rows=%d | duplicate_groups=%d | status=%s',
                    (string) ($result['snapshot_table'] ?? $table),
                    (string) (($result['period'] ?? '') ?: '-'),
                    (int) ($result['row_count'] ?? 0),
                    (int) ($result['duplicate_group_count'] ?? 0),
                    $status
                );

                if (!empty($result['reason'])) {
                    $line .= ' | reason=' . (string) $result['reason'];
                }

                if ($status === 'anomaly' || $status === 'error') {
                    $this->warn($line);
                } elseif ($status === 'skipped') {
                    $this->line('<fg=yellow>' . $line . '</>');
                } else {
                    $this->line('<fg=green>' . $line . '</>');
                }
            }
        }
    }

    private function validateAggregate(?string $period = null, ?string $segment = null): void
    {
        $this->line('<fg=cyan>== AGGREGATE VALIDATION ==</>');

        $periods = $period
            ? [$period]
            : DB::table('performance_rm_snapshots')
                ->distinct('periode')
                ->orderByDesc('periode')
                ->limit(5)
                ->pluck('periode')
                ->map(fn($p) => (string)$p)
                ->toArray();

        $segments = $segment ? [$segment] : ['CONSUMER', 'SMALL', 'MICRO'];

        foreach ($periods as $p) {
            $this->line("\n<fg=yellow>Period: {$p}</>");

            foreach ($segments as $seg) {
                $snapAgg = DB::table('performance_rm_snapshots')
                    ->where('periode', $p)
                    ->where('segmen', $seg)
                    ->selectRaw('COUNT(*) as cnt, SUM(loan_os) as total_loan, SUM(lancar_os) as total_lancar, SUM(realisasi_os) as total_real')
                    ->first();

                if (!$snapAgg || $snapAgg->cnt === 0) {
                    $this->line("  {$seg}: <fg=gray>No data</>");
                    continue;
                }

                $sourceAgg = $this->getSourceAggregate($p, $seg);

                $loanMatch = $this->compareValues($snapAgg->total_loan, $sourceAgg->total_loan);
                $lancarMatch = $this->compareValues($snapAgg->total_lancar, $sourceAgg->total_lancar);
                $realMatch = $this->compareValues($snapAgg->total_real, $sourceAgg->total_real);

                $status = $loanMatch && $lancarMatch ? '✓' : '✗';
                $color = $loanMatch && $lancarMatch ? 'fg=green' : 'fg=red';

                $this->line("  <{$color}>{$status} {$seg} ({$snapAgg->cnt} records)</>");
                $this->line("    Loan OS: snap=" . $this->formatNum($snapAgg->total_loan) . " vs src=" . $this->formatNum($sourceAgg->total_loan) . " ({$this->getDiff($snapAgg->total_loan, $sourceAgg->total_loan)}%)");
                $this->line("    Lancar OS: snap=" . $this->formatNum($snapAgg->total_lancar) . " vs src=" . $this->formatNum($sourceAgg->total_lancar) . " ({$this->getDiff($snapAgg->total_lancar, $sourceAgg->total_lancar)}%)");

                if ($sourceAgg->total_real > 0 || $snapAgg->total_real > 0) {
                    $realStatus = $realMatch ? '✓' : '!';
                    $this->line("    {$realStatus} Realisasi: snap=" . $this->formatNum($snapAgg->total_real) . " vs src=" . $this->formatNum($sourceAgg->total_real));
                }
            }
        }
    }

    private function validateWithSampling(?string $period = null, ?string $segment = null): void
    {
        $this->line('<fg=cyan>== SAMPLE-BASED VALIDATION (10 records per segment) ==</>');

        $periods = $period
            ? [$period]
            : DB::table('performance_rm_snapshots')
                ->distinct('periode')
                ->orderByDesc('periode')
                ->limit(3)
                ->pluck('periode')
                ->map(fn($p) => (string)$p)
                ->toArray();

        $segments = $segment ? [$segment] : ['CONSUMER', 'SMALL', 'MICRO'];

        foreach ($periods as $p) {
            $this->line("\n<fg=yellow>Period: {$p}</>");

            foreach ($segments as $seg) {
                $samples = DB::table('performance_rm_snapshots')
                    ->where('periode', $p)
                    ->where('segmen', $seg)
                    ->select('cabang', 'unit', 'rm', 'produk', 'plafon', 'loan_os', 'lancar_os', 'total_deb', 'realisasi_os')
                    ->inRandomOrder()
                    ->limit(10)
                    ->get();

                if ($samples->isEmpty()) {
                    $this->line("  {$seg}: <fg=gray>No data</>");
                    continue;
                }

                $matchCount = 0;
                $issues = [];

                foreach ($samples as $snap) {
                    $src = $this->getSourceData($p, $snap->cabang, $snap->unit, $snap->rm, $snap->produk, $seg);

                    if (!$src) {
                        $issues[] = "{$snap->rm}|{$snap->produk}: source not found";
                        continue;
                    }

                    if ($this->compareValues($snap->plafon, $src->plafon) &&
                        $this->compareValues($snap->loan_os, $src->loan_os) &&
                        $this->compareValues($snap->lancar_os, $src->lancar_os) &&
                        $snap->total_deb == $src->total_deb) {
                        $matchCount++;
                    } else {
                        $issues[] = "{$snap->rm}|{$snap->produk}: loan_os diff=" . $this->getDiff($snap->loan_os, $src->loan_os) . "%";
                    }
                }

                $color = $matchCount >= 8 ? 'fg=green' : ($matchCount >= 5 ? 'fg=yellow' : 'fg=red');
                $this->line("  <{$color}>{$seg}: {$matchCount}/10 samples match</>");

                if (!empty($issues)) {
                    foreach (array_slice($issues, 0, 3) as $issue) {
                        $this->line("    - {$issue}");
                    }
                    if (count($issues) > 3) {
                        $this->line("    ... and " . (count($issues) - 3) . " more");
                    }
                }
            }
        }
    }

    private function getSourceAggregate(string $period, string $segment): object
    {
        $sourceSegments = $this->getSourceSegments($segment);
        $sourceSegmentTokens = $this->getSourceSegmentTokens($segment);
        $consumerSurplus = strtoupper(trim($segment)) === 'CONSUMER'
            ? $this->getConsumerSurplusAggregate($period)
            : null;

        $query = DB::table('daily_loan_dinamis')
            ->where('periode', $period);

        if (Schema::hasColumn('daily_loan_dinamis', 'segmen_kinerja')) {
            $query->whereIn('segmen_kinerja', $sourceSegmentTokens);
        } else {
            $query->whereIn('segmen_dashboard', $sourceSegments);
        }

        return $query
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as total_loan')
            ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as total_lancar')
            ->when($consumerSurplus === null, function ($query) use ($period): void {
                $query->selectRaw('SUM(CASE WHEN tgl_realisasi BETWEEN DATE_FORMAT(?, "%Y-%m-01") AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as total_real', [
                    Carbon::parse($period)->startOfMonth()->toDateString(),
                    $period,
                ]);
            }, function ($query) use ($consumerSurplus): void {
                $query->selectRaw('? as total_real', [(float) ($consumerSurplus->total_real ?? 0)]);
            })
            ->first() ?? (object) [
            'total_loan' => 0,
            'total_lancar' => 0,
            'total_real' => 0,
        ];
    }

    private function getSourceData(string $period, string $cabang, string $unit, string $rm, string $produk, string $segment): ?object
    {
        $sourceProducts = $this->getSourceProducts($produk);
        $sourceSegments = $this->getSourceSegments($segment);
        $sourceProductTokens = $this->getSourceProductTokens($produk);
        $sourceSegmentTokens = $this->getSourceSegmentTokens($segment);
        $isMicroKur = $segment === 'MICRO' && $produk === 'KUR-MIKRO';
        $normalizedDescriptionSql = "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(description, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";

        $query = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
            ->selectRaw($isMicroKur ? 'SUM(COALESCE(plafon, 0)) as loan_os' : 'SUM(COALESCE(baki_debet1, 0)) as loan_os')
            ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->when($segment !== 'CONSUMER', function ($query) use ($period): void {
                $query->selectRaw('SUM(CASE WHEN tgl_realisasi BETWEEN DATE_FORMAT(?, "%Y-%m-01") AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os', [
                    Carbon::parse($period)->startOfMonth()->toDateString(),
                    $period,
                ]);
            }, function ($query): void {
                $query->selectRaw('0 as realisasi_os');
            });

        if (Schema::hasColumn('daily_loan_dinamis', 'segmen_kinerja') && Schema::hasColumn('daily_loan_dinamis', 'produk_kinerja')) {
            $query->whereIn('segmen_kinerja', $sourceSegmentTokens)
                ->whereIn('produk_kinerja', $sourceProductTokens)
                ->whereRaw("COALESCE(NULLIF(cabang_normalized, ''), UPPER(TRIM(cabang1))) = ?", [strtoupper(trim($cabang))])
                ->whereRaw("COALESCE(NULLIF(unit_normalized, ''), UPPER(TRIM(unit1))) = ?", [strtoupper(trim($unit))])
                ->whereRaw("COALESCE(NULLIF(rm_normalized, ''), UPPER(TRIM(pn_pengelola1))) = ?", [strtoupper(trim($rm))]);
        } else {
            $query->whereIn('segmen_dashboard', $sourceSegments)
                ->whereIn('produk_dashboard', $sourceProducts)
                ->whereRaw("UPPER(TRIM(cabang1)) = ?", [strtoupper(trim($cabang))])
                ->whereRaw("UPPER(TRIM(unit1)) = ?", [strtoupper(trim($unit))])
                ->whereRaw("UPPER(TRIM(pn_pengelola1)) = ?", [strtoupper(trim($rm))]);
        }

        if ($isMicroKur) {
            $query->whereRaw("{$normalizedDescriptionSql} = ?", ['KREDITMIKROKURRITEL2015']);
        }

        $source = $query->first();
        if ($source !== null && $segment === 'CONSUMER') {
            $surplus = $this->getConsumerSurplusForScope($period, $cabang, $unit, $rm, $produk);
            $source->realisasi_os = (float) ($surplus->total_real ?? 0);
            $source->realisasi_deb = (int) ($surplus->total_deb ?? 0);
        }

        return $source;
    }

    private function getConsumerSurplusAggregate(string $period): object
    {
        $query = $this->buildConsumerSurplusQuery($period);

        return $query === null
            ? (object) ['total_deb' => 0, 'total_real' => 0]
            : DB::query()
                ->fromSub($query, 'surplus')
                ->selectRaw('SUM(CASE WHEN previous_plafon > 0 AND current_plafon > previous_plafon THEN total_deb ELSE 0 END) as total_deb')
                ->selectRaw('SUM(CASE WHEN previous_plafon > 0 AND current_plafon > previous_plafon THEN current_plafon - previous_plafon ELSE 0 END) as total_real')
                ->first();
    }

    private function getConsumerSurplusForScope(string $period, string $cabang, string $unit, string $rm, string $produk): object
    {
        $query = $this->buildConsumerSurplusQuery($period, $cabang, $unit, $rm, $produk);

        return $query === null
            ? (object) ['total_deb' => 0, 'total_real' => 0]
            : DB::query()
                ->fromSub($query, 'surplus')
                ->selectRaw('SUM(CASE WHEN previous_plafon > 0 AND current_plafon > previous_plafon THEN total_deb ELSE 0 END) as total_deb')
                ->selectRaw('SUM(CASE WHEN previous_plafon > 0 AND current_plafon > previous_plafon THEN current_plafon - previous_plafon ELSE 0 END) as total_real')
                ->first();
    }

    private function buildConsumerSurplusQuery(
        string $period,
        ?string $cabang = null,
        ?string $unit = null,
        ?string $rm = null,
        ?string $produk = null
    ): ?\Illuminate\Database\Query\Builder {
        if (!Schema::hasColumn('daily_loan_dinamis', 'segmen_kinerja') || !Schema::hasColumn('daily_loan_dinamis', 'produk_kinerja')) {
            return null;
        }

        $previousPeriod = $this->resolvePreviousMonthDailyLoanPeriod($period);
        if ($previousPeriod === null) {
            return null;
        }

        $productSql = "CASE WHEN produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' ELSE produk_kinerja END";

        $current = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("COALESCE(cabang_normalized, '') as cabang")
            ->selectRaw("COALESCE(unit_normalized, '') as unit")
            ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
            ->selectRaw("COALESCE(rm_normalized, '') as rm")
            ->selectRaw("{$productSql} as produk")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->selectRaw('SUM(COALESCE(plafon, 0)) as current_plafon');

        if ($cabang !== null) {
            $current->whereRaw("COALESCE(NULLIF(cabang_normalized, ''), UPPER(TRIM(cabang1))) = ?", [strtoupper(trim($cabang))]);
        }
        if ($unit !== null) {
            $current->whereRaw("COALESCE(NULLIF(unit_normalized, ''), UPPER(TRIM(unit1))) = ?", [strtoupper(trim($unit))]);
        }
        if ($rm !== null) {
            $current->whereRaw("COALESCE(NULLIF(rm_normalized, ''), UPPER(TRIM(pn_pengelola1))) = ?", [strtoupper(trim($rm))]);
        }
        if ($produk !== null) {
            $current->whereIn('produk_kinerja', $this->getSourceProductTokens($produk));
        }

        $current->groupByRaw("COALESCE(cabang_normalized, ''), COALESCE(unit_normalized, ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, ''), {$productSql}");

        $previous = DB::table('daily_loan_dinamis')
            ->where('periode', $previousPeriod)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("COALESCE(cabang_normalized, '') as cabang")
            ->selectRaw("COALESCE(unit_normalized, '') as unit")
            ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
            ->selectRaw("COALESCE(rm_normalized, '') as rm")
            ->selectRaw("{$productSql} as produk")
            ->selectRaw('SUM(COALESCE(plafon, 0)) as previous_plafon')
            ->groupByRaw("COALESCE(cabang_normalized, ''), COALESCE(unit_normalized, ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, ''), {$productSql}");

        return DB::query()
            ->fromSub($current, 'cur')
            ->leftJoinSub($previous, 'prev', function ($join): void {
                $join->on('prev.cabang', '=', 'cur.cabang')
                    ->on('prev.unit', '=', 'cur.unit')
                    ->on('prev.branch_code', '=', 'cur.branch_code')
                    ->on('prev.rm', '=', 'cur.rm')
                    ->on('prev.produk', '=', 'cur.produk');
            })
            ->selectRaw('cur.total_deb, cur.current_plafon, prev.previous_plafon as previous_plafon');
    }

    private function resolvePreviousMonthDailyLoanPeriod(string $period): ?string
    {
        $periodDate = Carbon::parse($period);

        $previous = DB::table('daily_loan_dinamis')
            ->whereBetween('periode', [
                $periodDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ])
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->max('periode');

        return $previous !== null ? (string) $previous : null;
    }

    private function compareValues($snap, $source): bool
    {
        if ($snap == 0 && $source == 0) {
            return true;
        }

        if ($source == 0) {
            return $snap == 0;
        }

        $diff = abs($snap - $source) / $source * 100;
        return $diff <= 1;
    }

    private function getDiff($snap, $source): string
    {
        if ($source == 0) {
            return $snap == 0 ? '0' : 'INF';
        }

        $diff = abs($snap - $source) / $source * 100;
        return round($diff, 2);
    }

    private function formatNum($val): string
    {
        if ($val == 0) {
            return '0';
        }

        if (abs($val) >= 1000000000) {
            return round($val / 1000000000, 1) . 'M';
        }

        if (abs($val) >= 1000000) {
            return round($val / 1000000, 1) . 'K';
        }

        return round($val, 0);
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
            'SMALL' => ['SMALL', 'COMMERCIAL', 'Commercial', 'CASHCALL', 'Cashcall', 'CASHCOLLATERAL', 'CashCollateral', 'Cash Collateral', 'Cashcoll'],
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

    private function getSourceSegmentTokens(string $segment): array
    {
        return match ($segment) {
            'CONSUMER' => ['CONSUMER'],
            'SMALL' => ['SMALL'],
            'MICRO' => ['MICRO'],
            default => [$this->normalizeToken($segment)],
        };
    }

    private function getSourceProductTokens(string $product): array
    {
        return match ($product) {
            'BRIGUNA-KONSUMER' => ['BRIGUNAKONSUMER'],
            'KPR' => ['KPR'],
            'SMALL' => ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL'],
            'COMMERCIAL' => ['COMMERCIAL'],
            'CASHCALL' => ['CASHCALL'],
            'BRIGUNA-MIKRO' => ['BRIGUNAMIKRO'],
            'KUPEDES' => ['KUPEDES'],
            'KUR-MIKRO' => ['KURMIKRO'],
            'CASHCOLLATERAL' => ['CASHCOLLATERAL', 'CASHCOLL'],
            'KUR-SMALL' => ['KURSMALL'],
            default => [$this->normalizeToken($product)],
        };
    }

    private function normalizeToken(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '';
    }

    private function validateSsaSimpanan(?string $period = null, bool $useSample = false): void
    {
        $this->line('<fg=cyan>== SSA SIMPANAN SNAPSHOT VALIDATION ==</>');
        $periods = $period ? [$period] : DB::table('ssa_simpanan_snapshots')
            ->distinct('Month_Day_Year_of_Posisi')
            ->orderByDesc('Month_Day_Year_of_Posisi')
            ->limit(5)
            ->pluck('Month_Day_Year_of_Posisi')
            ->map(fn($p) => (string)$p)
            ->toArray();

        if (empty($periods)) {
            $this->line('<fg=yellow>No SSA Simpanan snapshots found.</>');
            return;
        }

        foreach ($periods as $p) {
            $count = DB::table('ssa_simpanan_snapshots')
                ->where('Month_Day_Year_of_Posisi', $p)
                ->count();
            $color = $count > 0 ? 'fg=green' : 'fg=yellow';
            $this->line("  <{$color}>Period {$p}: {$count} records</>");
        }
    }

    private function validateDashboardSimpanan(?string $period = null, bool $useSample = false): void
    {
        $this->line('<fg=cyan>== DASHBOARD SIMPANAN SNAPSHOT VALIDATION ==</>');
        $periods = $period ? [$period] : DB::table('dashboard_simpanan_snapshots')
            ->distinct('snapshot_period')
            ->orderByDesc('snapshot_period')
            ->limit(5)
            ->pluck('snapshot_period')
            ->map(fn($p) => (string)$p)
            ->toArray();

        if (empty($periods)) {
            $this->line('<fg=yellow>No Dashboard Simpanan snapshots found.</>');
            return;
        }

        foreach ($periods as $p) {
            $count = DB::table('dashboard_simpanan_snapshots')
                ->where('snapshot_period', $p)
                ->count();
            $color = $count > 0 ? 'fg=green' : 'fg=yellow';
            $this->line("  <{$color}>Period {$p}: {$count} records</>");
        }
    }

    private function validateDashboardHarian(?string $period = null, bool $useSample = false): void
    {
        $this->line('<fg=cyan>== DASHBOARD HARIAN SNAPSHOT VALIDATION ==</>');
        $periods = $period ? [$period] : DB::table('dashboard_harian_snapshots')
            ->distinct('snapshot_period')
            ->orderByDesc('snapshot_period')
            ->limit(5)
            ->pluck('snapshot_period')
            ->map(fn($p) => (string)$p)
            ->toArray();

        if (empty($periods)) {
            $this->line('<fg=yellow>No Dashboard Harian snapshots found.</>');
            return;
        }

        foreach ($periods as $p) {
            $count = DB::table('dashboard_harian_snapshots')
                ->where('snapshot_period', $p)
                ->count();
            $color = $count > 0 ? 'fg=green' : 'fg=yellow';
            $this->line("  <{$color}>Period {$p}: {$count} records</>");
        }
    }

    private function validateDormantAccount(?string $period = null, bool $useSample = false): void
    {
        $this->line('<fg=cyan>== REKENING DORMANT SNAPSHOT VALIDATION ==</>');
        $periods = $period ? [$period] : DB::table('rekening_dormant_snapshots')
            ->distinct('posisi')
            ->orderByDesc('posisi')
            ->limit(5)
            ->pluck('posisi')
            ->map(fn($p) => (string)$p)
            ->toArray();

        if (empty($periods)) {
            $this->line('<fg=yellow>No Rekening Dormant snapshots found.</>');
            return;
        }

        foreach ($periods as $p) {
            $count = DB::table('rekening_dormant_snapshots')
                ->where('posisi', $p)
                ->count();
            $color = $count > 0 ? 'fg=green' : 'fg=yellow';
            $this->line("  <{$color}>Period {$p}: {$count} records</>");
        }
    }
}
