<?php

namespace App\Services\Reports;

use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunOffReportService
{
    private const TABLE = 'daily_loan_dinamis';

    private const REQUIRED_COLUMNS = [
        'periode',
        'cabang1',
        'nomor_rekening1',
        'segmen_dashboard',
        'produk_dashboard',
        'description',
        'npb_pokok_la',
        'next_pmt_date',
        'next_pmt_int_date',
    ];

    private const BRANCHES = [
        'MADIUN' => 'KC Madiun',
        'MAGETAN' => 'KC Magetan',
        'NGAWI' => 'KC Ngawi',
        'PONOROGO' => 'KC Ponorogo',
    ];

    private const SEGMENTS = [
        'MICRO' => 'MICRO TOTAL',
        'CONSUMER' => 'CONSUMER TOTAL',
        'SMALL' => 'SMALL TOTAL',
    ];

    private const PRODUCT_ORDER = [
        'KUR KECIL',
        'KUPEDES',
        'BRIGUNA',
        'KUR MIKRO',
        'KPP',
        'KPR',
        'BRIGUNA RITEL',
        'COMMERCIAL',
        'CASHCALL',
    ];

    /**
     * @param array{label?: string, plain_label?: string}|null $scope
     * @return array<string, mixed>
     */
    public function build(?array $scope = null, bool $refresh = false): array
    {
        $schemaError = $this->schemaError();
        if ($schemaError !== null) {
            return $this->emptyReport($schemaError);
        }

        $cacheVersion = ReportCacheVersion::get('pinjaman');
        $periodContextKey = 'report:run_off:daily_loan:periods:v1:' . $cacheVersion;
        if ($refresh) {
            Cache::forget($periodContextKey);
        }

        $periodContext = Cache::remember(
            $periodContextKey,
            now()->addMinutes(10),
            fn (): array => $this->resolvePeriodContext()
        );
        $latestPeriod = $periodContext['latest_period'];
        if ($latestPeriod === null) {
            return $this->emptyReport('Daily Loan Dinamis belum memiliki periode yang valid.');
        }

        $current = Carbon::parse($latestPeriod);
        $baselinePeriod = $periodContext['baseline_period'];
        if (!$periodContext['baseline_available']) {
            return $this->emptyReport(
                'Data posisi akhir bulan sebelumnya (' . Carbon::parse($baselinePeriod)->translatedFormat('d M Y') . ') belum tersedia.',
                $latestPeriod,
                $baselinePeriod
            );
        }

        $cacheKey = $this->cacheKey($cacheVersion, $latestPeriod, $baselinePeriod);
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        $aggregates = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            fn (): array => $this->aggregateRunOff(
                $baselinePeriod,
                $latestPeriod,
                $current->copy()->startOfMonth()->toDateString(),
                $current->copy()->endOfMonth()->toDateString()
            )
        );

        $selectedBranches = $this->selectedBranches($scope);

        return [
            'title' => 'Monitoring Run OFF Daily Loan',
            'latest_period' => $latestPeriod,
            'baseline_period' => $baselinePeriod,
            'report_month' => $current->translatedFormat('F Y'),
            'rows' => $this->buildRows(
                $aggregates['baseline']['data'],
                $aggregates['remaining']['data'],
                $aggregates['product_labels'],
                $selectedBranches,
                $scope !== null
            ),
            'fetched_at' => $aggregates['fetched_at'],
            'error' => null,
        ];
    }

    private function schemaError(): ?string
    {
        if (!Schema::hasTable(self::TABLE)) {
            return 'Tabel daily_loan_dinamis belum tersedia.';
        }

        $available = array_fill_keys(array_map(
            static fn (string $column): string => strtolower($column),
            Schema::getColumnListing(self::TABLE)
        ), true);
        $missing = array_values(array_filter(
            self::REQUIRED_COLUMNS,
            static fn (string $column): bool => !isset($available[strtolower($column)])
        ));

        return $missing === []
            ? null
            : 'Kolom Daily Loan belum lengkap: ' . implode(', ', $missing) . '.';
    }

    /**
     * @return array{latest_period: ?string, baseline_period: ?string, baseline_available: bool}
     */
    private function resolvePeriodContext(): array
    {
        $period = DB::table(self::TABLE)->whereNotNull('periode')->max('periode');

        if ($period === null || trim((string) $period) === '') {
            return [
                'latest_period' => null,
                'baseline_period' => null,
                'baseline_available' => false,
            ];
        }

        try {
            $latestPeriod = Carbon::parse((string) $period)->toDateString();
            $baselinePeriod = Carbon::parse($latestPeriod)
                ->subMonthNoOverflow()
                ->endOfMonth()
                ->toDateString();

            return [
                'latest_period' => $latestPeriod,
                'baseline_period' => $baselinePeriod,
                'baseline_available' => DB::table(self::TABLE)->where('periode', $baselinePeriod)->exists(),
            ];
        } catch (\Throwable) {
            return [
                'latest_period' => null,
                'baseline_period' => null,
                'baseline_available' => false,
            ];
        }
    }

    /**
     * @return array{
     *     baseline: array{data: array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>>},
     *     remaining: array{data: array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>>},
     *     product_labels: array<string, string>,
     *     fetched_at: string
     * }
     */
    private function aggregateRunOff(
        string $baselinePeriod,
        string $latestPeriod,
        string $dueStart,
        string $dueEnd
    ): array
    {
        $latestPaymentDates = DB::table(self::TABLE . ' as latest_source')
            ->selectRaw('TRIM(latest_source.nomor_rekening1) AS account_key, MIN(latest_source.next_pmt_date) AS next_pmt_date')
            ->where('latest_source.periode', $latestPeriod)
            ->whereNotNull('latest_source.nomor_rekening1')
            ->whereRaw("TRIM(latest_source.nomor_rekening1) <> ''")
            ->groupByRaw('TRIM(latest_source.nomor_rekening1)');

        $productExpression = $this->productExpression('baseline_source');
        $baselinePopulation = DB::table(self::TABLE . ' as baseline_source')
            ->selectRaw(
                'TRIM(baseline_source.nomor_rekening1) AS account_key, '
                . 'baseline_source.cabang1 AS cabang, '
                . 'baseline_source.segmen_dashboard AS segment, '
                . "{$productExpression} AS product_label, "
                . 'COALESCE(baseline_source.npb_pokok_la, 0) AS npb_pokok_la'
            )
            ->where('baseline_source.periode', $baselinePeriod)
            ->whereNotNull('baseline_source.nomor_rekening1')
            ->whereRaw("TRIM(baseline_source.nomor_rekening1) <> ''")
            ->whereBetween('baseline_source.next_pmt_date', [$dueStart, $dueEnd])
            ->whereRaw("UPPER(TRIM(baseline_source.segmen_dashboard)) IN ('MICRO', 'MIKRO', 'CONSUMER', 'KONSUMER', 'SMALL')")
            ->whereRaw("UPPER(TRIM(baseline_source.cabang1)) IN ('KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO')")
            ->whereRaw("({$productExpression}) <> ''");

        $rows = DB::query()
            ->fromSub($baselinePopulation, 'baseline')
            ->leftJoinSub($latestPaymentDates, 'latest_payment', function ($join): void {
                $join->on('baseline.account_key', '=', 'latest_payment.account_key');
            })
            ->selectRaw(
                'baseline.cabang, baseline.segment, baseline.product_label, '
                . 'COUNT(*) AS baseline_accounts, '
                . 'SUM(baseline.npb_pokok_la) AS baseline_amount, '
                . 'SUM(CASE WHEN latest_payment.next_pmt_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS remaining_accounts, '
                . 'SUM(CASE WHEN latest_payment.next_pmt_date BETWEEN ? AND ? '
                . 'THEN baseline.npb_pokok_la ELSE 0 END) AS remaining_amount',
                [$dueStart, $dueEnd, $dueStart, $dueEnd]
            )
            ->groupBy('baseline.cabang', 'baseline.segment', 'baseline.product_label')
            ->get();

        $baseline = [];
        $remaining = [];
        $productLabels = [];
        foreach ($rows as $row) {
            $branchKey = $this->branchKey((string) $row->cabang);
            $segmentKey = $this->segmentKey((string) $row->segment);
            $productLabel = trim((string) $row->product_label);
            $productKey = $this->normalizeKey($productLabel);

            if ($branchKey === null || $segmentKey === null || $productKey === '') {
                continue;
            }

            $productLabels[$productKey] ??= $productLabel;
            $baseline[$segmentKey][$productKey][$branchKey] = [
                'accounts' => (int) $row->baseline_accounts,
                'amount_cents' => $this->decimalToCents((string) $row->baseline_amount),
            ];
            $remaining[$segmentKey][$productKey][$branchKey] = [
                'accounts' => (int) $row->remaining_accounts,
                'amount_cents' => $this->decimalToCents((string) $row->remaining_amount),
            ];
        }

        return [
            'baseline' => ['data' => $baseline],
            'remaining' => ['data' => $remaining],
            'product_labels' => $productLabels,
            'fetched_at' => now()->toDateTimeString(),
        ];
    }

    private function productExpression(string $alias): string
    {
        $segment = "UPPER(TRIM({$alias}.segmen_dashboard))";
        $product = "UPPER(TRIM({$alias}.produk_dashboard))";
        $description = "UPPER(TRIM(REPLACE({$alias}.description, CHAR(160), ' ')))";

        return "(CASE "
            . "WHEN {$segment} IN ('MICRO', 'MIKRO') THEN CASE {$description} "
            . "WHEN 'KREDIT MIKRO - CASH COLLATERAL' THEN 'KUPEDES' "
            . "WHEN 'KREDIT MIKRO - GBT' THEN 'BRIGUNA' "
            . "WHEN 'KREDIT MIKRO - KUR RITEL 2015' THEN 'KUR KECIL' "
            . "WHEN 'KREDITMIKRO - KPP' THEN 'KPP' "
            . "WHEN 'KUPEDES' THEN 'KUPEDES' "
            . "WHEN 'KUPEDES RAKYAT' THEN 'KUPEDES' "
            . "WHEN 'KUR MIKRO BARU' THEN 'KUR MIKRO' "
            . "ELSE '' END "
            . "WHEN {$segment} IN ('CONSUMER', 'KONSUMER') THEN CASE {$product} "
            . "WHEN 'BRIGUNA-KONSUMER' THEN 'BRIGUNA RITEL' "
            . "WHEN 'KPR' THEN 'KPR' "
            . "ELSE '' END "
            . "WHEN {$segment} = 'SMALL' THEN CASE {$product} "
            . "WHEN 'COMMERCIAL' THEN 'COMMERCIAL' "
            . "WHEN 'CASHCALL' THEN 'CASHCALL' "
            . "ELSE '' END "
            . "ELSE '' END)";
    }

    /**
     * @param array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>> $baseline
     * @param array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>> $remaining
     * @param array<string, string> $productLabels
     * @param array<string, string> $branches
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(
        array $baseline,
        array $remaining,
        array $productLabels,
        array $branches,
        bool $isBranchScoped
    ): array {
        $rows = [];

        foreach (self::SEGMENTS as $segmentKey => $segmentLabel) {
            $products = array_values(array_unique(array_merge(
                array_keys($baseline[$segmentKey] ?? []),
                array_keys($remaining[$segmentKey] ?? [])
            )));
            $products = $this->sortProducts($products, $productLabels);

            if ($products === []) {
                continue;
            }

            $segmentMetrics = $this->sumProducts($baseline, $remaining, $segmentKey, $products, $branches);
            $this->appendCategoryRows($rows, $segmentLabel, 'segment', $segmentMetrics, $branches, $isBranchScoped);

            foreach ($products as $productKey) {
                $productMetrics = [];
                foreach ($branches as $branchKey => $branchLabel) {
                    $productMetrics[$branchKey] = $this->combineMetrics(
                        $baseline[$segmentKey][$productKey][$branchKey] ?? null,
                        $remaining[$segmentKey][$productKey][$branchKey] ?? null
                    );
                }

                $this->appendCategoryRows(
                    $rows,
                    $productLabels[$productKey] ?? $productKey,
                    'product',
                    $productMetrics,
                    $branches,
                    $isBranchScoped
                );
            }
        }

        return $rows;
    }

    /**
     * @param array<string, array{baseline_accounts: int, baseline_amount_cents: int, remaining_accounts: int, remaining_amount_cents: int, paid_accounts: int, paid_amount_cents: int}> $metrics
     * @param array<string, string> $branches
     * @param array<int, array<string, mixed>> $rows
     */
    private function appendCategoryRows(
        array &$rows,
        string $category,
        string $level,
        array $metrics,
        array $branches,
        bool $isBranchScoped
    ): void {
        if (!$isBranchScoped) {
            $rows[] = $this->row($category, 'Area 6', $level, true, $this->sumBranchMetrics($metrics));
        }

        foreach ($branches as $branchKey => $branchLabel) {
            $rows[] = $this->row(
                $category,
                $branchLabel,
                $level,
                false,
                $metrics[$branchKey] ?? $this->combineMetrics(null, null)
            );
        }
    }

    /** @param array<string, int> $metrics */
    private function row(string $category, string $branch, string $level, bool $isSummary, array $metrics): array
    {
        return [
            'category' => $category,
            'branch' => $branch,
            'level' => $level,
            'is_summary' => $isSummary,
            ...$metrics,
        ];
    }

    /**
     * @param array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>> $baseline
     * @param array<string, array<string, array<string, array{accounts: int, amount_cents: int}>>> $remaining
     * @param array<int, string> $products
     * @param array<string, string> $branches
     * @return array<string, array<string, int>>
     */
    private function sumProducts(array $baseline, array $remaining, string $segment, array $products, array $branches): array
    {
        $result = [];
        foreach ($branches as $branchKey => $branchLabel) {
            $metric = $this->combineMetrics(null, null);
            foreach ($products as $productKey) {
                $product = $this->combineMetrics(
                    $baseline[$segment][$productKey][$branchKey] ?? null,
                    $remaining[$segment][$productKey][$branchKey] ?? null
                );
                foreach ($metric as $key => $value) {
                    $metric[$key] += $product[$key];
                }
            }
            $result[$branchKey] = $metric;
        }

        return $result;
    }

    /** @return array{baseline_accounts: int, baseline_amount_cents: int, remaining_accounts: int, remaining_amount_cents: int, paid_accounts: int, paid_amount_cents: int} */
    private function combineMetrics(?array $baseline, ?array $remaining): array
    {
        $baselineAccounts = (int) ($baseline['accounts'] ?? 0);
        $baselineAmount = (int) ($baseline['amount_cents'] ?? 0);
        $remainingAccounts = (int) ($remaining['accounts'] ?? 0);
        $remainingAmount = (int) ($remaining['amount_cents'] ?? 0);

        return [
            'baseline_accounts' => $baselineAccounts,
            'baseline_amount_cents' => $baselineAmount,
            'remaining_accounts' => $remainingAccounts,
            'remaining_amount_cents' => $remainingAmount,
            'paid_accounts' => $baselineAccounts - $remainingAccounts,
            'paid_amount_cents' => $baselineAmount - $remainingAmount,
        ];
    }

    /** @param array<string, array<string, int>> $metrics */
    private function sumBranchMetrics(array $metrics): array
    {
        $total = $this->combineMetrics(null, null);
        foreach ($metrics as $metric) {
            foreach ($total as $key => $value) {
                $total[$key] += (int) ($metric[$key] ?? 0);
            }
        }

        return $total;
    }

    /** @return array<string, string> */
    private function selectedBranches(?array $scope): array
    {
        if ($scope === null) {
            return self::BRANCHES;
        }

        $key = $this->branchKey((string) ($scope['label'] ?? $scope['plain_label'] ?? ''));

        return $key !== null ? [$key => self::BRANCHES[$key]] : [];
    }

    private function branchKey(string $value): ?string
    {
        $normalized = $this->normalizeKey(preg_replace('/^KC\s+/i', '', trim($value)) ?? $value);

        return isset(self::BRANCHES[$normalized]) ? $normalized : null;
    }

    private function segmentKey(string $value): ?string
    {
        return match ($this->normalizeKey($value)) {
            'MICRO', 'MIKRO' => 'MICRO',
            'CONSUMER', 'KONSUMER' => 'CONSUMER',
            'SMALL' => 'SMALL',
            default => null,
        };
    }

    private function normalizeKey(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    /** @param array<int, string> $products @param array<string, string> $labels */
    private function sortProducts(array $products, array $labels): array
    {
        usort($products, function (string $left, string $right) use ($labels): int {
            $leftOrder = array_search($left, self::PRODUCT_ORDER, true);
            $rightOrder = array_search($right, self::PRODUCT_ORDER, true);
            $leftOrder = $leftOrder === false ? PHP_INT_MAX : $leftOrder;
            $rightOrder = $rightOrder === false ? PHP_INT_MAX : $rightOrder;

            return $leftOrder <=> $rightOrder
                ?: strcasecmp($labels[$left] ?? $left, $labels[$right] ?? $right);
        });

        return $products;
    }

    private function decimalToCents(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function cacheKey(int $cacheVersion, string $latestPeriod, string $baselinePeriod): string
    {
        return 'report:run_off:daily_loan:v2:'
            . $cacheVersion
            . ':' . $latestPeriod . ':' . $baselinePeriod;
    }

    private function emptyReport(string $message, ?string $latestPeriod = null, ?string $baselinePeriod = null): array
    {
        return [
            'title' => 'Monitoring Run OFF Daily Loan',
            'latest_period' => $latestPeriod,
            'baseline_period' => $baselinePeriod,
            'report_month' => $latestPeriod ? Carbon::parse($latestPeriod)->translatedFormat('F Y') : null,
            'rows' => [],
            'fetched_at' => now()->toDateTimeString(),
            'error' => $message,
        ];
    }
}
