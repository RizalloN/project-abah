<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureDashboardSnapshotJob;
use App\Support\ReportIndexHintResolver;
use App\Support\LoanQualityBucketMapper;
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
        $this->releaseSessionLockIfNeeded();

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
                'segments' => $this->fetchPeriodDistinctValues('segmen_dashboard', $selectedPeriod, $filters),
                'products' => $this->fetchPeriodDistinctValues('produk_dashboard', $selectedPeriod, $filters, function (Builder $query) use ($filters) {
                    $this->applyFilterConstraint($query, 'segmen_dashboard', $filters['segmen']);
                }),
                'branches' => $this->fetchPeriodDistinctValues('cabang1', $selectedPeriod, $filters, function (Builder $query) use ($filters) {
                    $this->applyFilterConstraint($query, 'segmen_dashboard', $filters['segmen']);
                    $this->applyFilterConstraint($query, 'produk_dashboard', $filters['produk']);
                }),
                'units' => $this->fetchPeriodDistinctValues('unit1', $selectedPeriod, $filters, function (Builder $query) use ($filters) {
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
        $this->releaseSessionLockIfNeeded();

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

        $usesSnapshot = $this->shouldUseSnapshot($selectedPeriod, $filters)
            && (!$comparisonPeriod || $this->shouldUseSnapshot($comparisonPeriod, $filters));

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

        $startedAt = microtime(true);
        $phPeriod = $this->resolvePhPeriod($selectedPeriod);
        $bucketMap = [];
        $metricMap = [];

        // Movement comparison must stay database-side so large portfolios do not require PHP in-memory joins.
        $matrixRowsRaw = $this->buildMovementMatrixAggregateQuery($selectedPeriod, $comparisonPeriod, $filters)->get();
        foreach ($matrixRowsRaw as $row) {
            $before = (string) ($row->before_bucket ?? 'New Account');
            $after = (string) ($row->after_bucket ?? '');
            $amountCents = (int) ($row->amount_cents ?? 0);

            if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($after, self::QUALITY_BUCKETS, true) || $amountCents <= 0) {
                continue;
            }

            $bucketMap[$before][$after] = $amountCents;
        }

        $metricRowsRaw = $this->buildMovementMetricAggregateQuery($selectedPeriod, $comparisonPeriod, $phPeriod, $filters)->get();
        foreach ($metricRowsRaw as $row) {
            $before = (string) ($row->before_bucket ?? 'New Account');
            $metric = (string) ($row->metric_type ?? '');
            $amountCents = (int) ($row->amount_cents ?? 0);

            if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($metric, ['principal_reduction', 'suplesi', 'ph', 'lunas'], true) || $amountCents <= 0) {
                continue;
            }

            $metricMap[$before][$metric] = ($metricMap[$before][$metric] ?? 0) + $amountCents;
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

        Log::info('Dashboard pinjaman matrix query aggregated.', [
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'uses_snapshot' => $this->shouldUseSnapshot($selectedPeriod, $filters)
                && (!$comparisonPeriod || $this->shouldUseSnapshot($comparisonPeriod, $filters)),
            'matrix_row_count' => $matrixRowsRaw->count(),
            'metric_row_count' => $metricRowsRaw->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return [$matrixRows, $grandTotals, $grandTotalCents > 0 ? $this->centsToAmount($grandTotalCents) : null];
    }

    private function buildMovementMatrixAggregateQuery(string $selectedPeriod, ?string $comparisonPeriod, array $filters)
    {
        $currentSnapshot = $this->buildAggregatedLoanSnapshotQuery($selectedPeriod, $filters, 'curr');
        $previousSnapshot = $comparisonPeriod
            ? $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev')
            : $this->buildEmptyAggregatedLoanSnapshotQuery();

        $joinedCurrent = DB::query()
            ->fromSub($currentSnapshot, 'curr')
            ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                $join->on('curr.account_number', '=', 'prev.account_number');
            })
            ->selectRaw("
                COALESCE(prev.bucket, 'New Account') as before_bucket,
                curr.bucket as after_bucket,
                SUM(curr.balance_cents) as amount_cents
            ")
            ->whereNotNull('curr.bucket')
            ->whereIn('curr.bucket', self::QUALITY_BUCKETS)
            ->groupByRaw("COALESCE(prev.bucket, 'New Account'), curr.bucket");

        return DB::query()
            ->fromSub($joinedCurrent->unionAll($this->buildAnonymousCurrentMovementQuery($selectedPeriod, $filters)), 'movement_matrix')
            ->selectRaw('before_bucket, after_bucket, SUM(amount_cents) as amount_cents')
            ->groupBy('before_bucket', 'after_bucket');
    }

    private function buildMovementMetricAggregateQuery(string $selectedPeriod, ?string $comparisonPeriod, ?string $phPeriod, array $filters)
    {
        $currentSnapshot = $this->buildAggregatedLoanSnapshotQuery($selectedPeriod, $filters, 'curr');
        $previousSnapshot = $comparisonPeriod
            ? $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev')
            : $this->buildEmptyAggregatedLoanSnapshotQuery();

        $principalReductionQuery = DB::query()
            ->fromSub($currentSnapshot, 'curr')
            ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                $join->on('curr.account_number', '=', 'prev.account_number');
            })
            ->selectRaw("
                COALESCE(prev.bucket, 'New Account') as before_bucket,
                'principal_reduction' as metric_type,
                SUM(
                    CASE
                        WHEN COALESCE(prev.balance_cents, 0) > 0
                         AND curr.balance_cents > 0
                         AND prev.balance_cents > curr.balance_cents
                        THEN prev.balance_cents - curr.balance_cents
                        ELSE 0
                    END
                ) as amount_cents
            ")
            ->whereNotNull('curr.bucket')
            ->groupByRaw("COALESCE(prev.bucket, 'New Account')");

        $suplesiQuery = DB::query()
            ->fromSub($currentSnapshot, 'curr')
            ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                $join->on('curr.account_number', '=', 'prev.account_number');
            })
            ->selectRaw("
                COALESCE(prev.bucket, 'New Account') as before_bucket,
                'suplesi' as metric_type,
                SUM(
                    CASE
                        WHEN COALESCE(prev.balance_cents, 0) <= 0 AND curr.balance_cents > 0
                        THEN curr.balance_cents
                        WHEN curr.balance_cents > COALESCE(prev.balance_cents, 0)
                        THEN curr.balance_cents - COALESCE(prev.balance_cents, 0)
                        ELSE 0
                    END
                ) as amount_cents
            ")
            ->whereNotNull('curr.bucket')
            ->groupByRaw("COALESCE(prev.bucket, 'New Account')");

        $exitQuery = DB::query()
            ->fromSub($previousSnapshot, 'prev')
            ->leftJoinSub($currentSnapshot, 'curr', function ($join) {
                $join->on('prev.account_number', '=', 'curr.account_number');
            })
            ->leftJoinSub($this->buildPhSnapshotQuery($phPeriod), 'ph', function ($join) {
                $join->on('prev.account_number', '=', 'ph.account_number');
            })
            ->selectRaw("
                prev.bucket as before_bucket,
                CASE WHEN ph.account_number IS NOT NULL THEN 'ph' ELSE 'lunas' END as metric_type,
                SUM(prev.balance_cents) as amount_cents
            ")
            ->whereNull('curr.account_number')
            ->whereNotNull('prev.bucket')
            ->whereIn('prev.bucket', self::BEFORE_ROWS)
            ->groupByRaw("prev.bucket, CASE WHEN ph.account_number IS NOT NULL THEN 'ph' ELSE 'lunas' END");

        return DB::query()
            ->fromSub(
                $principalReductionQuery
                    ->unionAll($suplesiQuery)
                    ->unionAll($this->buildAnonymousCurrentMetricQuery($selectedPeriod, $filters))
                    ->unionAll($exitQuery),
                'movement_metrics'
            )
            ->selectRaw('before_bucket, metric_type, SUM(amount_cents) as amount_cents')
            ->whereIn('before_bucket', self::BEFORE_ROWS)
            ->groupBy('before_bucket', 'metric_type');
    }

    private function buildAggregatedLoanSnapshotQuery(string $period, array $filters, string $alias)
    {
        $baseQuery = $this->buildLoanSnapshotQuery($period, $filters, $alias);
        $balanceColumn = $alias === 'curr' ? 'current_balance' : 'previous_balance';
        $bucketColumn = $alias === 'curr' ? 'after_bucket' : 'before_bucket';
        $bucketRankExpression = $this->buildMovementBucketRankExpression("base.{$bucketColumn}");

        $aggregated = DB::query()
            ->fromSub($baseQuery, 'base')
            ->selectRaw("
                base.account_number,
                CAST(ROUND(SUM(COALESCE(base.{$balanceColumn}, 0)) * 100, 0) AS SIGNED) as balance_cents,
                MAX({$bucketRankExpression}) as bucket_rank
            ")
            ->groupBy('base.account_number');

        return DB::query()
            ->fromSub($aggregated, $alias . '_agg')
            ->selectRaw("
                account_number,
                balance_cents,
                {$this->buildMovementBucketLabelExpressionFromRank($alias . '_agg.bucket_rank')} as bucket
            ");
    }

    private function buildEmptyAggregatedLoanSnapshotQuery()
    {
        return DB::query()
            ->selectRaw("'' as account_number, 0 as balance_cents, NULL as bucket")
            ->whereRaw('1 = 0');
    }

    private function buildAnonymousCurrentMovementQuery(string $period, array $filters)
    {
        $alias = 'anon';
        $bucketExpression = $this->buildQualityBucketExpression($alias);

        $rowQuery = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $period)
            ->where(function ($query) use ($alias) {
                $query->whereNull("{$alias}.nomor_rekening1")
                    ->orWhereRaw("TRIM({$alias}.nomor_rekening1) = ''");
            })
            ->selectRaw("
                {$bucketExpression} as after_bucket,
                COALESCE({$alias}.baki_debet1, 0) as loan_balance
            ");

        $this->applyFilterConstraint($rowQuery, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($rowQuery, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($rowQuery, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($rowQuery, "{$alias}.unit1", $filters['unit']);

        $baseQuery = DB::query()
            ->fromSub($rowQuery, 'anon_rows')
            ->selectRaw("
                after_bucket,
                CAST(ROUND(SUM(COALESCE(loan_balance, 0)) * 100, 0) AS SIGNED) as amount_cents
            ")
            ->groupBy('after_bucket');

        return DB::query()
            ->fromSub($baseQuery, 'anon_matrix')
            ->selectRaw("'New Account' as before_bucket, after_bucket, amount_cents")
            ->whereIn('after_bucket', self::QUALITY_BUCKETS)
            ->where('amount_cents', '>', 0);
    }

    private function buildAnonymousCurrentMetricQuery(string $period, array $filters)
    {
        return DB::query()
            ->fromSub($this->buildAnonymousCurrentMovementQuery($period, $filters), 'anon_metric')
            ->selectRaw("before_bucket, 'suplesi' as metric_type, SUM(amount_cents) as amount_cents")
            ->groupBy('before_bucket');
    }

    private function buildLoanSnapshotQuery(string $period, array $filters, string $alias)
    {
        if ($this->shouldUseSnapshot($period, $filters)) {
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
        $query = DB::table(DB::raw($this->qualifyIndexedSource(self::PH_TABLE, 'ph', [self::PH_LOOKUP_INDEX])))
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

    private function buildLoanSnapshotSource(string $alias, array $filters): string
    {
        $preferredIndexes = [self::LOAN_REKENING_INDEX];

        if (!empty($filters['segmen']) || !empty($filters['produk'])) {
            array_unshift($preferredIndexes, self::LOAN_FILTER_INDEX);
        } elseif (!empty($filters['cabang']) || !empty($filters['unit'])) {
            array_unshift($preferredIndexes, self::LOAN_CABANG_UNIT_INDEX);
        }

        return $this->qualifyIndexedSource('daily_loan_dinamis', $alias, $preferredIndexes);
    }

    private function qualifyIndexedSource(string $table, string $alias, array $preferredIndexes = []): string
    {
        return $this->reportIndexHintResolver()->qualify($table, $alias, $preferredIndexes);
    }

    private function reportIndexHintResolver(): ReportIndexHintResolver
    {
        return app(ReportIndexHintResolver::class);
    }

    private function buildQualityBucketExpression(string $alias): string
    {
        return LoanQualityBucketMapper::buildSqlExpression($alias);
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

    private function buildMovementBucketRankExpression(string $column): string
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
                WHEN 'NPL' THEN 8
                WHEN 'PH' THEN 9
                WHEN 'Pay' THEN 10
                WHEN 'PAY' THEN 10
                ELSE NULL
            END
        ";
    }

    private function buildMovementBucketLabelExpressionFromRank(string $column): string
    {
        return "
            CASE {$column}
                WHEN 0 THEN 'L'
                WHEN 1 THEN 'LR'
                WHEN 2 THEN 'DPK 1'
                WHEN 3 THEN 'DPK 2'
                WHEN 4 THEN 'DPK 3'
                WHEN 5 THEN 'KL'
                WHEN 6 THEN 'D1'
                WHEN 7 THEN 'D2'
                WHEN 8 THEN 'M'
                WHEN 9 THEN 'PH'
                WHEN 10 THEN 'Pay'
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

    private function fetchPeriodDistinctValues(string $column, string $period, array $filters = [], ?callable $scope = null): Collection
    {
        try {
            $table = $this->shouldUseSnapshot($period, $filters) ? self::SNAPSHOT_TABLE : 'daily_loan_dinamis';

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
            return $lock->block(2, function () use ($cacheKey, $latestKey, $ttl, $callback, $forceRefresh) {
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

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
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
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        $exists = DB::table(self::SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->exists();

        if ($exists) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            return true;
        }

        $hasSourceRows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->exists();

        if (!$hasSourceRows) {
            Cache::put($cacheKey, false, now()->addSeconds(30));
            return false;
        }

        $lock = Cache::lock('snapshot:dashboard:auto-rebuild:' . $period, 10);
        $pendingKey = 'snapshot:dashboard:auto-rebuild:pending:' . $period;
        $jobDispatched = false;

        try {
            if ($lock->get()) {
                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                    EnsureDashboardSnapshotJob::dispatch($period, static::class . '::hasDashboardSnapshot')
                        ->onQueue('reports');
                    $jobDispatched = true;
                }
            }
        } finally {
            optional($lock)->release();
        }

        Log::info('Dashboard snapshot unavailable; using source query fallback.', [
            'period' => $period,
            'job_dispatched' => $jobDispatched,
        ]);

        Cache::put($cacheKey, false, now()->addSeconds(30));

        return false;
    }

    private function shouldUseSnapshot(?string $period, array $filters): bool
    {
        if (!$period) {
            return false;
        }

        return $this->hasDashboardSnapshot($period);
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

    private function mapQualityBucket($kolekDetail, $umurTunggakan, $flagRestruk, $kolAdk1 = null, $kolek = null): string
    {
        return LoanQualityBucketMapper::map($kolekDetail, $umurTunggakan, $flagRestruk, $kolAdk1, $kolek);
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
