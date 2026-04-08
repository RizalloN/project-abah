<?php

namespace App\Http\Controllers;

use App\Support\ReportDataSyncService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

class DashboardPinjamanReportController extends Controller
{
    private const PH_TABLE = 'lw325_ph';
    private const SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const LOAN_REKENING_INDEX = 'idx_dld_periode_rekening';
    private const LOAN_FILTER_INDEX = 'idx_dld_periode_segmen_produk_cabang_unit';
    private const LOAN_CABANG_UNIT_INDEX = 'idx_dld_periode_cabang_unit';
    private const PH_LOOKUP_INDEX = 'idx_lw325ph_periode_acctno_pokok';
    private const RAW_QUALITY_BUCKETS = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M', 'NPL', 'PH', 'Pay'];

    private const QUALITY_BUCKETS = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];
    private const HEALTHY_BUCKETS = ['L', 'LR'];

    private const BEFORE_ROWS = ['New Account', 'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];

    private const OUTPUT_COLUMNS = ['Turunan Pokok', 'Suplesi', 'PH', 'Lunas'];

    public function index(Request $request)
    {
        $periods = $this->fetchPeriods();

        $requestedPeriod = $request->input('periode');
        $selectedPeriod = $this->resolveEffectivePeriod($requestedPeriod);
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        return view('report.dashboard-pinjaman', [
            'periods' => $periods,
            'filters' => $filters,
            'selectedPeriod' => $selectedPeriod,
            'comparisonPeriod' => $comparisonPeriod,
            'matrixColumns' => self::QUALITY_BUCKETS,
            'requestedPeriod' => $requestedPeriod,
        ]);
    }

    public function filters(Request $request)
    {
        @set_time_limit(30);

        $selectedPeriod = $this->resolveEffectivePeriod($request->input('periode'));
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);
        $forceRefresh = $request->boolean('refresh');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        if (!$selectedPeriod) {
            return response()->json([
                'selected_period' => null,
                'comparison_period' => null,
                'segments' => [],
                'products' => [],
                'branches' => [],
                'units' => [],
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_filters:v2:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'filters' => $filters,
        ]));

        $payload = $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod, $filters) {
            return [
                'segments' => $this->fetchPeriodDistinctValues('segmen_dashboard', $selectedPeriod),
                'products' => $this->fetchPeriodDistinctValues('produk_dashboard', $selectedPeriod, function (Builder $query) use ($filters) {
                    $this->applyFilterConstraint($query, 'segmen_dashboard', $filters['segmen']);
                }),
                'branches' => $this->fetchPeriodDistinctValues('cabang1', $selectedPeriod, function (Builder $query) use ($filters) {
                    $this->applyFilterConstraint($query, 'segmen_dashboard', $filters['segmen']);
                    $this->applyFilterConstraint($query, 'produk_dashboard', $filters['produk']);
                }),
                'units' => $this->fetchPeriodDistinctValues('unit1', $selectedPeriod, function (Builder $query) use ($filters) {
                    $this->applyFilterConstraint($query, 'segmen_dashboard', $filters['segmen']);
                    $this->applyFilterConstraint($query, 'produk_dashboard', $filters['produk']);
                    $this->applyFilterConstraint($query, 'cabang1', $filters['cabang']);
                }),
            ];
        }, $forceRefresh);

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'segments' => $payload['segments']->all(),
            'products' => $payload['products']->all(),
            'branches' => $payload['branches']->all(),
            'units' => $payload['units']->all(),
        ]);
    }

    public function data(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();

        $selectedPeriod = $this->resolveEffectivePeriod($request->input('periode'));
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);
        $forceRefresh = $request->boolean('refresh');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $phPeriod = $this->resolvePhPeriod($selectedPeriod);

        $cacheKey = 'dashboard_pinjaman_matrix_direct:v1:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'comparison' => $comparisonPeriod,
            'ph_period' => $phPeriod,
            'filters' => $filters,
        ]));

        [$matrixRows, $grandTotals, $grandTotalValue] = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(3),
            fn () => $this->buildMatrixData($selectedPeriod, $comparisonPeriod, $filters),
            $forceRefresh
        );

        $usesSnapshot = $this->hasDashboardSnapshot($selectedPeriod)
            && (!$comparisonPeriod || $this->hasDashboardSnapshot($comparisonPeriod));

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'matrix_columns' => self::QUALITY_BUCKETS,
            'output_columns' => self::OUTPUT_COLUMNS,
            'matrix_rows' => $matrixRows,
            'grand_totals' => $grandTotals,
            'grand_total_value' => $grandTotalValue,
            'data_source' => $usesSnapshot ? self::SNAPSHOT_TABLE : 'daily_loan_dinamis',
        ]);
    }

    private function buildPureQuerySnapshotData(?string $selectedPeriod, array $filters): array
    {
        if (!$selectedPeriod) {
            return [[], null];
        }

        $alias = 'dld';
        $bucketExpression = $this->buildQualityBucketExpression($alias);
        $rowsByBucket = [];

        $baseQuery = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $selectedPeriod)
            ->selectRaw("
                {$bucketExpression} as bucket,
                COALESCE({$alias}.baki_debet1, 0) as loan_balance
            ");

        $this->applyFilterConstraint($baseQuery, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.unit1", $filters['unit']);

        $query = DB::query()
            ->fromSub($baseQuery, 'snapshot_query')
            ->selectRaw('bucket, SUM(loan_balance) as total_balance')
            ->groupBy('bucket');

        foreach ($query->get() as $row) {
            $bucket = $this->normalizeDashboardBucket((string) ($row->bucket ?? ''));

            if (!in_array($bucket, self::QUALITY_BUCKETS, true)) {
                continue;
            }

            $rowsByBucket[$bucket] = (float) ($row->total_balance ?? 0);
        }

        $snapshotRows = collect(self::QUALITY_BUCKETS)
            ->map(function (string $bucket) use ($rowsByBucket) {
                return [
                    'bucket' => $bucket,
                    'total_balance' => array_key_exists($bucket, $rowsByBucket)
                        ? (float) $rowsByBucket[$bucket]
                        : null,
                ];
            })
            ->all();

        $grandTotalValue = collect($snapshotRows)
            ->sum(fn (array $row) => (float) ($row['total_balance'] ?? 0));

        return [$snapshotRows, $grandTotalValue > 0 ? $grandTotalValue : null];
    }

    private function buildMatrixPayloadFromSnapshotRows(array $snapshotRows): array
    {
        $totalsByBucket = collect($snapshotRows)
            ->mapWithKeys(fn (array $row) => [
                (string) ($row['bucket'] ?? '') => (float) ($row['total_balance'] ?? 0),
            ])
            ->all();

        $matrixRows = collect(self::BEFORE_ROWS)
            ->map(function (string $label) use ($totalsByBucket) {
                $values = array_fill(0, count(self::QUALITY_BUCKETS), null);

                if (in_array($label, self::QUALITY_BUCKETS, true)) {
                    $index = array_search($label, self::QUALITY_BUCKETS, true);
                    $bucketTotal = $totalsByBucket[$label] ?? 0;
                    $values[$index] = $bucketTotal > 0 ? $bucketTotal : null;
                }

                return [
                    'label' => $label,
                    'values' => $values,
                    'metrics' => [
                        'principal_reduction' => null,
                        'suplesi' => null,
                        'ph' => null,
                        'lunas' => null,
                    ],
                    'total' => in_array($label, self::QUALITY_BUCKETS, true)
                        ? (($totalsByBucket[$label] ?? 0) > 0 ? (float) $totalsByBucket[$label] : null)
                        : null,
                ];
            })
            ->all();

        $grandTotals = [
            'matrix' => collect(self::QUALITY_BUCKETS)
                ->map(fn (string $bucket) => (($totalsByBucket[$bucket] ?? 0) > 0 ? (float) $totalsByBucket[$bucket] : null))
                ->all(),
            'metrics' => [
                'principal_reduction' => null,
                'suplesi' => null,
                'ph' => null,
                'lunas' => null,
            ],
        ];

        return [$matrixRows, $grandTotals];
    }

    private function buildMatrixData(?string $selectedPeriod, ?string $comparisonPeriod, array $filters): array
    {
        $emptyRows = collect(self::BEFORE_ROWS)->map(function (string $label) {
            return [
                'label' => $label,
                'values' => array_fill(0, count(self::QUALITY_BUCKETS), null),
                'metrics' => [
                    'principal_reduction' => null,
                    'suplesi' => null,
                    'ph' => null,
                    'lunas' => null,
                ],
                'total' => null,
            ];
        })->all();

        $emptyTotals = [
            'matrix' => array_fill(0, count(self::QUALITY_BUCKETS), null),
            'metrics' => [
                'principal_reduction' => null,
                'suplesi' => null,
                'ph' => null,
                'lunas' => null,
            ],
        ];

        if (!$selectedPeriod) {
            return [$emptyRows, $emptyTotals, null];
        }

        $phPeriod = $this->resolvePhPeriod($selectedPeriod);
        $bucketMap = [];
        $metricMap = [];
        $currentSnapshot = $this->loadLoanSnapshotMap($selectedPeriod, $filters, 'curr');
        $previousSnapshot = $comparisonPeriod
            ? $this->loadLoanSnapshotMap($comparisonPeriod, $filters, 'prev')
            : [];
        $previousMetricSnapshot = $previousSnapshot;
        $phAccounts = $this->loadPhAccountAmountMap($phPeriod);

        foreach ($this->buildLoanSnapshotQuery($selectedPeriod, $filters, 'curr')->cursor() as $row) {
            $accountNumber = (string) ($row->account_number ?? '');
            $currentBalanceCents = $this->amountToCents($row->current_balance ?? 0);
            $after = $this->normalizeDashboardBucket((string) ($row->after_bucket ?? ''));

            if ($accountNumber === '' || !in_array($after, self::RAW_QUALITY_BUCKETS, true)) {
                continue;
            }

            $previous = $previousSnapshot[$accountNumber] ?? null;
            $before = $this->normalizeDashboardBucket((string) ($previous['bucket'] ?? 'New Account'));

            if (!in_array($before, self::BEFORE_ROWS, true)) {
                $before = 'New Account';
            }

            if (in_array($after, self::QUALITY_BUCKETS, true)) {
                $bucketMap[$before][$after] = ($bucketMap[$before][$after] ?? 0) + $currentBalanceCents;
            }
        }

        foreach ($currentSnapshot as $accountNumber => $current) {
            $currentBalanceCents = (int) ($current['balance_cents'] ?? 0);
            $after = $this->normalizeDashboardBucket((string) ($current['bucket'] ?? ''));

            if ($accountNumber === '' || $currentBalanceCents <= 0 || !in_array($after, self::RAW_QUALITY_BUCKETS, true)) {
                continue;
            }

            $previous = $previousMetricSnapshot[$accountNumber] ?? null;
            $before = $this->normalizeDashboardBucket((string) ($previous['bucket'] ?? 'New Account'));
            $previousBalanceCents = (int) ($previous['balance_cents'] ?? 0);

            $principalReduction = $this->calculatePrincipalReduction($before, $after, $previousBalanceCents, $currentBalanceCents);
            if ($principalReduction > 0) {
                $metricMap[$before]['principal_reduction'] = ($metricMap[$before]['principal_reduction'] ?? 0) + $principalReduction;
            }

            $suplesi = $this->calculateSuplesi($before, $after, $previousBalanceCents, $currentBalanceCents);
            if ($suplesi > 0) {
                $metricMap[$before]['suplesi'] = ($metricMap[$before]['suplesi'] ?? 0) + $suplesi;
            }

            unset($previousMetricSnapshot[$accountNumber]);
        }

        foreach ($this->loadAnonymousCurrentBucketTotals($selectedPeriod, $filters) as $after => $amount) {
            $amountCents = $this->amountToCents($amount);

            if ($amountCents <= 0 || !in_array($after, self::QUALITY_BUCKETS, true)) {
                continue;
            }

            $bucketMap['New Account'][$after] = ($bucketMap['New Account'][$after] ?? 0) + $amountCents;
            $metricMap['New Account']['suplesi'] = ($metricMap['New Account']['suplesi'] ?? 0) + $amountCents;
        }

        foreach ($previousMetricSnapshot as $accountNumber => $previous) {
            $before = $this->normalizeDashboardBucket((string) ($previous['bucket'] ?? 'New Account'));
            $previousBalanceCents = (int) ($previous['balance_cents'] ?? 0);

            if ($previousBalanceCents <= 0 || !in_array($before, self::BEFORE_ROWS, true)) {
                continue;
            }

            $phBalanceCents = (int) ($phAccounts[$accountNumber] ?? 0);

            if ($phBalanceCents > 0) {
                $metricMap[$before]['ph'] = ($metricMap[$before]['ph'] ?? 0) + $phBalanceCents;
                continue;
            }

            $metricMap[$before]['lunas'] = ($metricMap[$before]['lunas'] ?? 0) + $previousBalanceCents;
        }

        $matrixRows = [];
        foreach (self::BEFORE_ROWS as $beforeLabel) {
            $values = [];
            foreach (self::QUALITY_BUCKETS as $afterLabel) {
                $valueCents = $bucketMap[$beforeLabel][$afterLabel] ?? null;
                $values[] = $valueCents !== null ? $this->centsToAmount($valueCents) : null;
            }

            $rowTotalCents = collect(self::QUALITY_BUCKETS)
                ->sum(fn (string $afterLabel) => (int) ($bucketMap[$beforeLabel][$afterLabel] ?? 0));

            $matrixRows[] = [
                'label' => $beforeLabel,
                'values' => $values,
                'metrics' => [
                    'principal_reduction' => (($metricMap[$beforeLabel]['principal_reduction'] ?? 0) > 0)
                        ? $this->centsToAmount((int) $metricMap[$beforeLabel]['principal_reduction'])
                        : null,
                    'suplesi' => (($metricMap[$beforeLabel]['suplesi'] ?? 0) > 0)
                        ? $this->centsToAmount((int) $metricMap[$beforeLabel]['suplesi'])
                        : null,
                    'ph' => (($metricMap[$beforeLabel]['ph'] ?? 0) > 0)
                        ? $this->centsToAmount((int) $metricMap[$beforeLabel]['ph'])
                        : null,
                    'lunas' => (($metricMap[$beforeLabel]['lunas'] ?? 0) > 0)
                        ? $this->centsToAmount((int) $metricMap[$beforeLabel]['lunas'])
                        : null,
                ],
                'total' => $rowTotalCents > 0 ? $this->centsToAmount($rowTotalCents) : null,
            ];
        }

        $matrixGrandTotals = [];
        foreach (self::QUALITY_BUCKETS as $index => $unusedBucket) {
            $columnTotalCents = collect(self::BEFORE_ROWS)
                ->sum(fn (string $beforeLabel) => (int) ($bucketMap[$beforeLabel][self::QUALITY_BUCKETS[$index]] ?? 0));

            $matrixGrandTotals[] = $columnTotalCents > 0 ? $this->centsToAmount($columnTotalCents) : null;
        }

        $grandTotals = [
            'matrix' => $matrixGrandTotals,
            'metrics' => [
                'principal_reduction' => ($this->sumMetricCents($metricMap, 'principal_reduction') > 0)
                    ? $this->centsToAmount($this->sumMetricCents($metricMap, 'principal_reduction'))
                    : null,
                'suplesi' => ($this->sumMetricCents($metricMap, 'suplesi') > 0)
                    ? $this->centsToAmount($this->sumMetricCents($metricMap, 'suplesi'))
                    : null,
                'ph' => ($this->sumMetricCents($metricMap, 'ph') > 0)
                    ? $this->centsToAmount($this->sumMetricCents($metricMap, 'ph'))
                    : null,
                'lunas' => ($this->sumMetricCents($metricMap, 'lunas') > 0)
                    ? $this->centsToAmount($this->sumMetricCents($metricMap, 'lunas'))
                    : null,
            ],
        ];

        $grandTotalCents = collect(self::BEFORE_ROWS)
            ->sum(fn (string $beforeLabel) => collect(self::QUALITY_BUCKETS)
                ->sum(fn (string $afterLabel) => (int) ($bucketMap[$beforeLabel][$afterLabel] ?? 0)));

        return [$matrixRows, $grandTotals, $grandTotalCents > 0 ? $this->centsToAmount($grandTotalCents) : null];
    }

    private function loadAnonymousCurrentBucketTotals(string $period, array $filters): array
    {
        $alias = 'anon';
        $bucketExpression = $this->buildQualityBucketExpression($alias);
        $rowsByBucket = [];

        $baseQuery = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $period)
            ->where(function (Builder $query) use ($alias) {
                $query->whereNull("{$alias}.nomor_rekening1")
                    ->orWhereRaw("TRIM({$alias}.nomor_rekening1) = ''");
            })
            ->selectRaw("
                {$bucketExpression} as bucket,
                COALESCE({$alias}.baki_debet1, 0) as loan_balance
            ");

        $this->applyFilterConstraint($baseQuery, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.unit1", $filters['unit']);

        $query = DB::query()
            ->fromSub($baseQuery, 'anonymous_snapshot_query')
            ->selectRaw('bucket, SUM(loan_balance) as total_balance')
            ->groupBy('bucket');

        foreach ($query->get() as $row) {
            $bucket = $this->normalizeDashboardBucket((string) ($row->bucket ?? ''));

            if (!in_array($bucket, self::QUALITY_BUCKETS, true)) {
                continue;
            }

            $rowsByBucket[$bucket] = (float) ($row->total_balance ?? 0);
        }

        return $rowsByBucket;
    }

    private function loadLoanSnapshotMap(string $period, array $filters, string $alias): array
    {
        $snapshot = [];

        foreach ($this->buildLoanSnapshotQuery($period, $filters, $alias)->cursor() as $row) {
            $accountNumber = (string) ($row->account_number ?? '');

            if ($accountNumber === '') {
                continue;
            }

            $balanceColumn = $alias === 'curr' ? 'current_balance' : 'previous_balance';
            $bucketColumn = $alias === 'curr' ? 'after_bucket' : 'before_bucket';
            $balanceCents = $this->amountToCents($row->{$balanceColumn} ?? 0);
            $bucket = $this->normalizeDashboardBucket((string) ($row->{$bucketColumn} ?? ''));

            if (isset($snapshot[$accountNumber])) {
                $snapshot[$accountNumber]['balance_cents'] += $balanceCents;
                $snapshot[$accountNumber]['bucket'] = $bucket;
                continue;
            }

            $snapshot[$accountNumber] = [
                'balance_cents' => $balanceCents,
                'bucket' => $bucket,
            ];
        }

        return $snapshot;
    }

    private function loadPhAccountAmountMap(?string $period): array
    {
        if (!$period) {
            return [];
        }

        $accounts = [];

        foreach ($this->buildPhSnapshotAmountQuery($period)->cursor() as $row) {
            $accountNumber = (string) ($row->account_number ?? '');
            $balanceCents = $this->amountToCents($row->ph_balance ?? 0);

            if ($accountNumber !== '' && $balanceCents > 0) {
                $accounts[$accountNumber] = (int) (($accounts[$accountNumber] ?? 0) + $balanceCents);
            }
        }

        return $accounts;
    }

    private function buildLoanSnapshotQuery(string $period, array $filters, string $alias)
    {
        if ($this->hasDashboardSnapshot($period)) {
            $query = DB::table(self::SNAPSHOT_TABLE . " as {$alias}")
                ->where("{$alias}.periode", $period)
                ->whereNotNull("{$alias}.account_number")
                ->whereRaw("TRIM({$alias}.account_number) <> ''")
                ->selectRaw("
                    TRIM({$alias}.account_number) as account_number,
                    COALESCE({$alias}.loan_balance, 0) as " . ($alias === 'curr' ? 'current_balance' : 'previous_balance') . ",
                    {$alias}.quality_bucket as " . ($alias === 'curr' ? 'after_bucket' : 'before_bucket')
                );

            $this->applyFilterConstraint($query, "{$alias}.segmen_dashboard", $filters['segmen']);
            $this->applyFilterConstraint($query, "{$alias}.produk_dashboard", $filters['produk']);
            $this->applyFilterConstraint($query, "{$alias}.cabang1", $filters['cabang']);
            $this->applyFilterConstraint($query, "{$alias}.unit1", $filters['unit']);

            return $query;
        }

        $bucketExpression = $this->buildQualityBucketExpression($alias);

        $query = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $period)
            ->whereNotNull("{$alias}.nomor_rekening1")
            ->whereRaw("TRIM({$alias}.nomor_rekening1) <> ''")
            ->selectRaw("
                TRIM({$alias}.nomor_rekening1) as account_number,
                COALESCE({$alias}.baki_debet1, 0) as " . ($alias === 'curr' ? 'current_balance' : 'previous_balance') . ",
                {$bucketExpression} as " . ($alias === 'curr' ? 'after_bucket' : 'before_bucket')
            );

        $this->applyFilterConstraint($query, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($query, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($query, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($query, "{$alias}.unit1", $filters['unit']);

        return $query;
    }

    private function buildNormalizedLoanBalanceExpression(string $column): string
    {
        $base = $this->loanBalanceRoundingBase();

        if ($base <= 1) {
            return "COALESCE({$column}, 0)";
        }

        return "FLOOR(COALESCE({$column}, 0) / {$base}) * {$base}";
    }

    private function loanBalanceRoundingBase(): int
    {
        $configured = (int) config('reports.dashboard_pinjaman.row_rounding_base', 1);

        return $configured > 0 ? $configured : 1;
    }

    private function buildEmptyLoanSnapshotQuery()
    {
        return DB::query()->selectRaw("
            '' as account_number,
            0 as previous_balance,
            'New Account' as before_bucket
        ")->whereRaw('1 = 0');
    }

    private function buildPhSnapshotQuery(?string $period)
    {
        $query = DB::table(DB::raw(self::PH_TABLE . ' as ph FORCE INDEX (' . self::PH_LOOKUP_INDEX . ')'))
            ->selectRaw('DISTINCT TRIM(ph.acctno) as account_number')
            ->whereNotNull('ph.acctno')
            ->where('ph.acctno', '<>', '')
            ->where('ph.pokok', '>', 0);

        if ($period) {
            $query->where('ph.periode', $period);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function buildPhSnapshotAmountQuery(?string $period)
    {
        $query = DB::table(DB::raw(self::PH_TABLE . ' as ph FORCE INDEX (' . self::PH_LOOKUP_INDEX . ')'))
            ->selectRaw('TRIM(ph.acctno) as account_number, SUM(COALESCE(ph.pokok, 0)) as ph_balance')
            ->whereNotNull('ph.acctno')
            ->where('ph.acctno', '<>', '')
            ->where('ph.pokok', '>', 0)
            ->groupByRaw('TRIM(ph.acctno)');

        if ($period) {
            $query->where('ph.periode', $period);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function buildLoanSnapshotSource(string $alias, array $filters): string
    {
        $indexName = self::LOAN_REKENING_INDEX;

        if (!empty($filters['segmen']) || !empty($filters['produk'])) {
            $indexName = self::LOAN_FILTER_INDEX;
        } elseif (!empty($filters['cabang']) || !empty($filters['unit'])) {
            $indexName = self::LOAN_CABANG_UNIT_INDEX;
        }

        return "daily_loan_dinamis as {$alias} FORCE INDEX ({$indexName})";
    }

    private function buildQualityBucketExpression(string $alias): string
    {
        $normalizedKolekDetail = "UPPER(TRIM(COALESCE({$alias}.kolek_detail, '')))";

        $kolekFixExpression = "
            CASE
                WHEN {$normalizedKolekDetail} NOT IN ('', '0', '-') THEN {$normalizedKolekDetail}
                WHEN {$alias}.umur_tunggakan <= 0 THEN 'L'
                WHEN {$alias}.umur_tunggakan <= 30 THEN 'DPK 1'
                WHEN {$alias}.umur_tunggakan <= 60 THEN 'DPK 2'
                WHEN {$alias}.umur_tunggakan <= 90 THEN 'DPK 3'
                WHEN {$alias}.umur_tunggakan <= 120 THEN 'KL'
                WHEN {$alias}.umur_tunggakan <= 180 THEN 'D1'
                ELSE 'M'
            END
        ";

        return "
            CASE
                WHEN ({$kolekFixExpression}) = 'L' AND UPPER(COALESCE({$alias}.flag_restruk, '')) = 'Y' THEN 'LR'
                WHEN ({$kolekFixExpression}) IN ('DPK1', 'SML1') THEN 'DPK 1'
                WHEN ({$kolekFixExpression}) IN ('DPK2', 'SML2') THEN 'DPK 2'
                WHEN ({$kolekFixExpression}) IN ('DPK3', 'SML3') THEN 'DPK 3'
                WHEN ({$kolekFixExpression}) = 'NPL' THEN 'M'
                WHEN ({$kolekFixExpression}) IN ('PAY', 'LUNAS') THEN 'Pay'
                ELSE ({$kolekFixExpression})
            END
        ";
    }

    private function buildBucketRankExpression(string $column): string
    {
        return "
            CASE {$column}
                WHEN 'L' THEN 0
                WHEN 'LR' THEN 1
                WHEN 'DPK 1' THEN 2
                WHEN 'DPK 2' THEN 3
                WHEN 'DPK 3' THEN 4
                WHEN 'KL' THEN 5
                WHEN 'D1' THEN 6
                WHEN 'D2' THEN 7
                WHEN 'M' THEN 8
                ELSE NULL
            END
        ";
    }

    private function fetchDistinctValues(string $column, bool $desc = false): Collection
    {
        try {
            $cacheKey = 'dashboard_pinjaman_distinct:v2:' . md5(json_encode([
                'cache_version' => $this->reportCacheVersion(),
                'column' => $column,
                'direction' => $desc ? 'desc' : 'asc',
            ]));

            return $this->rememberPayload($cacheKey, now()->addMinutes(5), function () use ($column, $desc) {
                $query = DB::table('daily_loan_dinamis')
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->select($column)
                    ->distinct();

                $query = $desc ? $query->orderByDesc($column) : $query->orderBy($column);

                return $query->pluck($column)->values();
            });
        } catch (Throwable) {
            return collect();
        }
    }

    private function fetchPeriodDistinctValues(string $column, string $period, ?callable $scope = null): Collection
    {
        try {
            $table = $this->hasDashboardSnapshot($period) ? self::SNAPSHOT_TABLE : 'daily_loan_dinamis';

            $query = DB::table($table)
                ->where('periode', $period)
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->select($column)
                ->distinct()
                ->orderBy($column);

            if ($scope) {
                $scope($query);
            }

            return $query->pluck($column)->values();
        } catch (Throwable) {
            return collect();
        }
    }

    private function fetchPeriods(): Collection
    {
        $cacheKey = 'dashboard_pinjaman_periods:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return $this->fetchDistinctValues('periode', desc: true)
                ->map(function ($periode) {
                    try {
                        return Carbon::parse($periode)->format('Y-m-d');
                    } catch (Throwable) {
                        return (string) $periode;
                    }
                })
                ->values();
        });
    }

    private function resolveEffectivePeriod(?string $requestedPeriod): ?string
    {
        try {
            if ($requestedPeriod) {
                return DB::table('daily_loan_dinamis')
                    ->where('periode', '<=', Carbon::parse($requestedPeriod)->format('Y-m-d'))
                    ->max('periode');
            }

            $cacheKey = 'dashboard_pinjaman_latest_period:v' . $this->reportCacheVersion();

            return Cache::remember($cacheKey, now()->addMinutes(10), function () {
                return DB::table('daily_loan_dinamis')->max('periode');
            });
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveComparisonPeriod(?string $selectedPeriod): ?string
    {
        if (!$selectedPeriod) {
            return null;
        }

        try {
            $previousMonthEnd = Carbon::parse($selectedPeriod)
                ->copy()
                ->subMonthNoOverflow()
                ->endOfMonth()
                ->format('Y-m-d');

            return DB::table('daily_loan_dinamis')
                ->where('periode', '<=', $previousMonthEnd)
                ->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function resolvePhPeriod(?string $selectedPeriod): ?string
    {
        if (!$selectedPeriod) {
            return null;
        }

        try {
            return DB::table(self::PH_TABLE)
                ->where('periode', '<=', $selectedPeriod)
                ->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function rememberPayload(string $cacheKey, $ttl, callable $callback, bool $forceRefresh = false)
    {
        $latestKey = $cacheKey . ':latest';

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        try {
            return $lock->block(15, function () use ($cacheKey, $latestKey, $ttl, $callback, $forceRefresh) {
                if (!$forceRefresh) {
                    $cached = Cache::get($cacheKey);
                    if ($cached !== null) {
                        return $cached;
                    }
                }

                $payload = $callback();
                Cache::put($cacheKey, $payload, $ttl);
                Cache::put($latestKey, $payload, now()->addMinutes(10));

                return $payload;
            });
        } catch (LockTimeoutException) {
            $latest = Cache::get($latestKey);
            if ($latest !== null) {
                return $latest;
            }

            $payload = $callback();
            Cache::put($cacheKey, $payload, $ttl);
            Cache::put($latestKey, $payload, now()->addMinutes(10));

            return $payload;
        } finally {
            optional($lock)->release();
        }
    }

    private function hasDashboardSnapshot(?string $period): bool
    {
        if (!$period || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return false;
        }

        $cacheKey = 'dashboard_pinjaman_snapshot_exists:v' . $this->reportCacheVersion() . ':' . $period;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($period) {
            $exists = DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->exists();

            if ($exists) {
                return true;
            }

            $hasSourceRows = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->exists();

            if (!$hasSourceRows) {
                return false;
            }

            $lock = Cache::lock('snapshot:dashboard:auto-rebuild:' . $period, 60);

            if ($lock->get()) {
                defer(function () use ($period, $lock) {
                    try {
                        app(ReportDataSyncService::class)->syncImportedTable(
                            'daily_loan_dinamis',
                            $period,
                            source: static::class . '::hasDashboardSnapshot'
                        );
                    } catch (Throwable $e) {
                        Log::warning('Auto rebuild dashboard snapshot gagal: ' . $e->getMessage(), [
                            'period' => $period,
                        ]);
                    } finally {
                        $lock->release();
                    }
                });
            }

            return DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->exists();
        });
    }

    private function reportCacheVersion(): int
    {
        return (int) Cache::get('report_cache_version:global', 1);
    }

    private function buildTableWideVersionSignature(string $table): string
    {
        try {
            $timestampExpression = $this->buildLatestTimestampExpression($table);
            $identityColumn = $this->resolveIdentityColumn($table);
            $row = DB::table($table)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX({$identityColumn}), '') as max_identity,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                (int) ($row->total_rows ?? 0),
                (string) ($row->max_identity ?? ''),
                (string) ($row->latest_change ?? '1970-01-01 00:00:00'),
            ]);
        } catch (Throwable) {
            return $table . '|fallback';
        }
    }

    private function buildTableVersionSignature(string $table, string $periodColumn, ?string $periodValue): string
    {
        if (!$periodValue) {
            return 'null-period';
        }

        try {
            $timestampExpression = $this->buildLatestTimestampExpression($table);
            $identityColumn = $this->resolveIdentityColumn($table);
            $row = DB::table($table)
                ->where($periodColumn, $periodValue)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX({$identityColumn}), '') as max_identity,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                $periodValue,
                (int) ($row->total_rows ?? 0),
                (string) ($row->max_identity ?? ''),
                (string) ($row->latest_change ?? '1970-01-01 00:00:00'),
            ]);
        } catch (Throwable) {
            return $periodValue . '|fallback';
        }
    }

    private function buildLatestTimestampExpression(string $table): string
    {
        $hasUpdated = Schema::hasColumn($table, 'updated_at');
        $hasCreated = Schema::hasColumn($table, 'created_at');

        if ($hasUpdated && $hasCreated) {
            return 'COALESCE(updated_at, created_at)';
        }

        if ($hasUpdated) {
            return 'updated_at';
        }

        if ($hasCreated) {
            return 'created_at';
        }

        return $this->resolveIdentityColumn($table);
    }

    private function resolveIdentityColumn(string $table): string
    {
        if (Schema::hasColumn($table, 'uniqueid_dps')) {
            return 'uniqueid_dps';
        }

        if (Schema::hasColumn($table, 'uniqueid_rcds')) {
            return 'uniqueid_rcds';
        }

        if (Schema::hasColumn($table, 'uniqueid_rds')) {
            return 'uniqueid_rds';
        }

        if (Schema::hasColumn($table, 'uniqueid_namareport')) {
            return 'uniqueid_namareport';
        }

        if (Schema::hasColumn($table, 'uniqueid_SMPN')) {
            return 'uniqueid_SMPN';
        }

        if (Schema::hasColumn($table, 'id')) {
            return 'id';
        }

        $columns = Schema::getColumnListing($table);
        if (!empty($columns)) {
            return $columns[0];
        }

        return 'id';
    }

    private function normalizeAccountNumber($accountNumber): string
    {
        return trim((string) $accountNumber);
    }

    private function normalizeFilterValues($value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->flatMap(function ($item) {
                $stringValue = trim((string) $item);

                if ($stringValue === '') {
                    return [];
                }

                if (str_contains($stringValue, ',')) {
                    return collect(explode(',', $stringValue))
                        ->map(fn ($part) => trim((string) $part))
                        ->filter(fn (string $part) => $part !== '')
                        ->values()
                        ->all();
                }

                return [$stringValue];
            })
            ->filter(fn (string $item) => $item !== '')
            ->values()
            ->all();
    }

    private function applyFilterConstraint(Builder $query, string $column, array $values): void
    {
        if (!empty($values)) {
            $query->whereIn($column, $values);
        }
    }

    private function mapQualityBucket($kolekDetail, $umurTunggakan, $flagRestruk, $kolek = null): string
    {
        $normalizedKolekDetail = trim((string) ($kolekDetail ?? ''));
        $kolekFix = strtoupper($normalizedKolekDetail);
        if ($kolekFix === '' || $kolekFix === '0' || $kolekFix === '-') {
            $days = (int) ($umurTunggakan ?? 0);

            if ($days <= 0) {
                $kolekFix = 'L';
            } elseif ($days <= 30) {
                $kolekFix = 'DPK 1';
            } elseif ($days <= 60) {
                $kolekFix = 'DPK 2';
            } elseif ($days <= 90) {
                $kolekFix = 'DPK 3';
            } elseif ($days <= 120) {
                $kolekFix = 'KL';
            } elseif ($days <= 180) {
                $kolekFix = 'D1';
            } else {
                $kolekFix = 'M';
            }
        }

        if ($kolekFix === 'L' && strtoupper((string) ($flagRestruk ?? '')) === 'Y') {
            return 'LR';
        }

        return match ($kolekFix) {
            'DPK1', 'SML1' => 'DPK 1',
            'DPK2', 'SML2' => 'DPK 2',
            'DPK3', 'SML3' => 'DPK 3',
            'NPL' => 'M',
            'PAY', 'LUNAS' => 'Pay',
            default => $kolekFix,
        };
    }

    private function normalizeDashboardBucket(string $bucket): string
    {
        $normalized = trim($bucket);
        $value = strtoupper($normalized);

        return match ($value) {
            'L' => 'L',
            'LR' => 'LR',
            'DPK 1' => 'DPK 1',
            'DPK 2' => 'DPK 2',
            'DPK 3' => 'DPK 3',
            'SML1' => 'DPK 1',
            'SML2' => 'DPK 2',
            'SML3' => 'DPK 3',
            'KL' => 'KL',
            'D1' => 'D1',
            'D2' => 'D2',
            'M' => 'M',
            'NPL' => 'M',
            'PH' => 'PH',
            'PAY' => 'Pay',
            default => $normalized,
        };
    }

    private function calculatePrincipalReduction(string $before, string $after, float $previousBalance, float $currentBalance): float
    {
        if ($before === 'New Account' || $previousBalance <= 0 || $currentBalance <= 0) {
            return 0.0;
        }

        $difference = $previousBalance - $currentBalance;

        if ($difference <= 0) {
            return 0.0;
        }

        return $difference;
    }

    private function calculateSuplesi(string $before, string $after, float $previousBalance, float $currentBalance): float
    {
        if ($currentBalance <= 0) {
            return 0.0;
        }

        if ($before === 'New Account') {
            return $currentBalance;
        }

        $difference = $currentBalance - $previousBalance;

        if ($difference <= 0) {
            return 0.0;
        }

        return $difference;
    }

    private function amountToCents($amount): int
    {
        $normalized = trim((string) $amount);

        if ($normalized === '') {
            return 0;
        }

        $sign = 1;
        if (str_starts_with($normalized, '-')) {
            $sign = -1;
            $normalized = substr($normalized, 1);
        } elseif (str_starts_with($normalized, '+')) {
            $normalized = substr($normalized, 1);
        }

        if ($normalized === '') {
            return 0;
        }

        if (!str_contains($normalized, '.')) {
            return $sign * ((int) $normalized * 100);
        }

        [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '0');
        $whole = $whole === '' ? '0' : $whole;
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');

        return $sign * (((int) $whole * 100) + (int) $decimal);
    }

    private function centsToAmount(int $cents): float
    {
        return $cents / 100;
    }

    private function sumMetricCents(array $metricMap, string $metric): int
    {
        return (int) collect($metricMap)
            ->sum(fn (array $metrics) => (int) ($metrics[$metric] ?? 0));
    }

    private function isHealthyBucket(string $bucket): bool
    {
        return in_array($bucket, self::HEALTHY_BUCKETS, true);
    }

    private function qualityRank(string $bucket): ?int
    {
        $rank = array_search($bucket, self::QUALITY_BUCKETS, true);

        return $rank === false ? null : $rank;
    }
}
