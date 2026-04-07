<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $cacheKey = 'dashboard_pinjaman_matrix:v6:' . md5(json_encode([
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

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'matrix_columns' => self::QUALITY_BUCKETS,
            'output_columns' => self::OUTPUT_COLUMNS,
            'matrix_rows' => $matrixRows,
            'grand_totals' => $grandTotals,
            'grand_total_value' => $grandTotalValue,
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

        $phPeriod = $this->resolvePhPeriod($selectedPeriod);
        $bucketMap = [];
        $metricMap = [];
        $previousSnapshot = $comparisonPeriod
            ? $this->loadLoanSnapshotMap($comparisonPeriod, $filters, 'prev')
            : [];
        $phAccounts = $this->loadPhAccountSet($phPeriod);

        foreach ($this->buildLoanSnapshotQuery($selectedPeriod, $filters, 'curr')->cursor() as $row) {
            $accountNumber = (string) ($row->account_number ?? '');
            $currentBalance = (float) ($row->current_balance ?? 0);
            $after = $this->normalizeDashboardBucket((string) ($row->after_bucket ?? ''));

            if ($accountNumber === '' || !in_array($after, self::RAW_QUALITY_BUCKETS, true)) {
                continue;
            }

            $previous = $previousSnapshot[$accountNumber] ?? null;
            $before = $this->normalizeDashboardBucket((string) ($previous['bucket'] ?? 'New Account'));
            $previousBalance = (float) ($previous['balance'] ?? 0);

            if (!in_array($before, self::BEFORE_ROWS, true)) {
                $before = 'New Account';
            }

            if (in_array($after, self::QUALITY_BUCKETS, true)) {
                $bucketMap[$before][$after] = ($bucketMap[$before][$after] ?? 0) + $currentBalance;
            }

            $principalReduction = $this->calculatePrincipalReduction($before, $after, $previousBalance, $currentBalance);
            if ($principalReduction > 0) {
                $metricMap[$before]['principal_reduction'] = ($metricMap[$before]['principal_reduction'] ?? 0) + $principalReduction;
            }

            $suplesi = $this->calculateSuplesi($before, $after, $previousBalance, $currentBalance);
            if ($suplesi > 0) {
                $metricMap[$before]['suplesi'] = ($metricMap[$before]['suplesi'] ?? 0) + $suplesi;
            }

            unset($previousSnapshot[$accountNumber]);
        }

        foreach ($previousSnapshot as $accountNumber => $previous) {
            $before = $this->normalizeDashboardBucket((string) ($previous['bucket'] ?? 'New Account'));
            $previousBalance = (float) ($previous['balance'] ?? 0);

            if ($previousBalance <= 0 || !in_array($before, self::BEFORE_ROWS, true)) {
                continue;
            }

            $metricKey = isset($phAccounts[$accountNumber]) ? 'ph' : 'lunas';
            $metricMap[$before][$metricKey] = ($metricMap[$before][$metricKey] ?? 0) + $previousBalance;
        }

        $matrixRows = [];
        foreach (self::BEFORE_ROWS as $beforeLabel) {
            $values = [];
            foreach (self::QUALITY_BUCKETS as $afterLabel) {
                $values[] = $bucketMap[$beforeLabel][$afterLabel] ?? null;
            }

            $rowTotal = collect($values)->filter(fn ($value) => $value !== null)->sum();

            $matrixRows[] = [
                'label' => $beforeLabel,
                'values' => $values,
                'metrics' => [
                    'principal_reduction' => (($metricMap[$beforeLabel]['principal_reduction'] ?? 0) > 0)
                        ? (float) $metricMap[$beforeLabel]['principal_reduction']
                        : null,
                    'suplesi' => (($metricMap[$beforeLabel]['suplesi'] ?? 0) > 0)
                        ? (float) $metricMap[$beforeLabel]['suplesi']
                        : null,
                    'ph' => (($metricMap[$beforeLabel]['ph'] ?? 0) > 0)
                        ? (float) $metricMap[$beforeLabel]['ph']
                        : null,
                    'lunas' => (($metricMap[$beforeLabel]['lunas'] ?? 0) > 0)
                        ? (float) $metricMap[$beforeLabel]['lunas']
                        : null,
                ],
                'total' => $rowTotal > 0 ? $rowTotal : null,
            ];
        }

        $matrixGrandTotals = [];
        foreach (self::QUALITY_BUCKETS as $index => $unusedBucket) {
            $columnTotal = collect($matrixRows)
                ->filter(fn (array $row) => $row['values'][$index] !== null)
                ->sum(fn (array $row) => (float) $row['values'][$index]);

            $matrixGrandTotals[] = $columnTotal > 0 ? $columnTotal : null;
        }

        $grandTotals = [
            'matrix' => $matrixGrandTotals,
            'metrics' => [
                'principal_reduction' => collect($matrixRows)
                    ->sum(fn (array $row) => (float) ($row['metrics']['principal_reduction'] ?? 0)) ?: null,
                'suplesi' => collect($matrixRows)
                    ->sum(fn (array $row) => (float) ($row['metrics']['suplesi'] ?? 0)) ?: null,
                'ph' => collect($matrixRows)
                    ->sum(fn (array $row) => (float) ($row['metrics']['ph'] ?? 0)) ?: null,
                'lunas' => collect($matrixRows)
                    ->sum(fn (array $row) => (float) ($row['metrics']['lunas'] ?? 0)) ?: null,
            ],
        ];

        $grandTotalValue = collect($matrixRows)
            ->filter(fn (array $row) => $row['total'] !== null)
            ->sum(fn (array $row) => (float) $row['total']);

        return [$matrixRows, $grandTotals, $grandTotalValue > 0 ? $grandTotalValue : null];
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
            $balance = (float) ($row->{$balanceColumn} ?? 0);
            $bucket = $this->normalizeDashboardBucket((string) ($row->{$bucketColumn} ?? ''));

            if (isset($snapshot[$accountNumber])) {
                $snapshot[$accountNumber]['balance'] += $balance;
                $snapshot[$accountNumber]['bucket'] = $bucket;
                continue;
            }

            $snapshot[$accountNumber] = [
                'balance' => $balance,
                'bucket' => $bucket,
            ];
        }

        return $snapshot;
    }

    private function loadPhAccountSet(?string $period): array
    {
        if (!$period) {
            return [];
        }

        $accounts = [];

        foreach ($this->buildPhSnapshotQuery($period)->cursor() as $row) {
            $accountNumber = (string) ($row->account_number ?? '');

            if ($accountNumber !== '') {
                $accounts[$accountNumber] = true;
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
                ->where("{$alias}.account_number", '<>', '')
                ->selectRaw("
                    {$alias}.account_number as account_number,
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
            ->where("{$alias}.nomor_rekening1", '<>', '')
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
        $rawQualityExpression = "
            CASE
                WHEN TRIM(COALESCE({$alias}.kolek_detail, '')) = '' OR TRIM(COALESCE({$alias}.kolek_detail, '')) = '0' THEN
                    CASE
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 0 THEN 'L'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 30 THEN 'DPK 1'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 60 THEN 'DPK 2'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 90 THEN 'DPK 3'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 120 THEN 'KL'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 150 THEN 'D1'
                        WHEN COALESCE({$alias}.umur_tunggakan, 0) <= 180 THEN 'D2'
                        ELSE 'M'
                    END
                ELSE UPPER(TRIM(COALESCE({$alias}.kolek_detail, '')))
            END
        ";

        return "
            CASE
                WHEN ({$rawQualityExpression}) = 'L' AND UPPER(COALESCE({$alias}.flag_restruk, '')) = 'Y' THEN 'LR'
                WHEN ({$rawQualityExpression}) = 'L' THEN 'L'
                WHEN ({$rawQualityExpression}) IN ('DPK 1', 'SML1') THEN 'DPK 1'
                WHEN ({$rawQualityExpression}) IN ('DPK 2', 'SML2') THEN 'DPK 2'
                WHEN ({$rawQualityExpression}) IN ('DPK 3', 'SML3') THEN 'DPK 3'
                WHEN ({$rawQualityExpression}) = 'KL' THEN 'KL'
                WHEN ({$rawQualityExpression}) = 'D1' THEN 'D1'
                WHEN ({$rawQualityExpression}) = 'D2' THEN 'D2'
                WHEN ({$rawQualityExpression}) IN ('M', 'NPL') THEN 'M'
                WHEN ({$rawQualityExpression}) = 'PH' THEN 'PH'
                WHEN ({$rawQualityExpression}) = 'PAY' THEN 'Pay'
                ELSE 'L'
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
        return Cache::remember('dashboard_pinjaman_periods', now()->addMinutes(10), function () {
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

            return Cache::remember('dashboard_pinjaman_latest_period', now()->addMinutes(10), function () {
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

        return Cache::remember('dashboard_pinjaman_snapshot_exists:' . $period, now()->addMinutes(10), function () use ($period) {
            return DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->exists();
        });
    }

    private function buildTableWideVersionSignature(string $table): string
    {
        try {
            $timestampExpression = $this->buildLatestTimestampExpression($table);
            $row = DB::table($table)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX(id), 0) as max_id,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                (int) ($row->total_rows ?? 0),
                (int) ($row->max_id ?? 0),
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
            $row = DB::table($table)
                ->where($periodColumn, $periodValue)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX(id), 0) as max_id,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                $periodValue,
                (int) ($row->total_rows ?? 0),
                (int) ($row->max_id ?? 0),
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

    private function mapQualityBucket($kolekDetail, $umurTunggakan, $flagRestruk): string
    {
        $normalizedKolekDetail = trim((string) ($kolekDetail ?? ''));
        $rawQuality = strtoupper($normalizedKolekDetail);

        if ($normalizedKolekDetail === '' || $normalizedKolekDetail === '0') {
            $days = (int) ($umurTunggakan ?? 0);

            if ($days <= 0) {
                $rawQuality = 'L';
            } elseif ($days <= 30) {
                $rawQuality = 'DPK 1';
            } elseif ($days <= 60) {
                $rawQuality = 'DPK 2';
            } elseif ($days <= 90) {
                $rawQuality = 'DPK 3';
            } elseif ($days <= 120) {
                $rawQuality = 'KL';
            } elseif ($days <= 150) {
                $rawQuality = 'D1';
            } elseif ($days <= 180) {
                $rawQuality = 'D2';
            } else {
                $rawQuality = 'M';
            }
        }

        if ($rawQuality === 'L' && strtoupper((string) ($flagRestruk ?? '')) === 'Y') {
            return 'LR';
        }

        return match ($rawQuality) {
            'L' => 'L',
            'DPK 1', 'SML1' => 'DPK 1',
            'DPK 2', 'SML2' => 'DPK 2',
            'DPK 3', 'SML3' => 'DPK 3',
            'KL' => 'KL',
            'D1' => 'D1',
            'D2' => 'D2',
            'M', 'NPL' => 'M',
            'PH' => 'PH',
            'PAY' => 'Pay',
            default => 'L',
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
        if ($previousBalance <= $currentBalance || $before === 'New Account') {
            return 0.0;
        }

        if ($this->isHealthyBucket($before) && $this->isHealthyBucket($after)) {
            return $previousBalance - $currentBalance;
        }

        $beforeRank = $this->qualityRank($before);
        $afterRank = $this->qualityRank($after);

        if ($beforeRank === null || $afterRank === null || $afterRank >= $beforeRank) {
            return 0.0;
        }

        return $previousBalance - $currentBalance;
    }

    private function calculateSuplesi(string $before, string $after, float $previousBalance, float $currentBalance): float
    {
        if ($currentBalance <= $previousBalance) {
            return 0.0;
        }

        if ($before === 'New Account') {
            return $currentBalance - $previousBalance;
        }

        if (!$this->isHealthyBucket($before) || !$this->isHealthyBucket($after)) {
            return 0.0;
        }

        return $currentBalance - $previousBalance;
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
