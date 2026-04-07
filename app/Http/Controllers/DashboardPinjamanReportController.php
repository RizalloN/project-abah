<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Throwable;

class DashboardPinjamanReportController extends Controller
{
    private const PH_TABLE = 'lw325_ph';
    private const RAW_QUALITY_BUCKETS = ['L', 'LR', 'SML1', 'SML2', 'SML3', 'NPL', 'PH', 'Pay'];

    private const QUALITY_BUCKETS = ['L', 'LR', 'SML1', 'SML2', 'SML3', 'NPL'];
    private const HEALTHY_BUCKETS = ['L', 'LR'];

    private const BEFORE_ROWS = ['New Account', 'L', 'LR', 'SML1', 'SML2', 'SML3', 'NPL'];

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
            'version' => $this->buildTableVersionSignature('daily_loan_dinamis', 'periode', $selectedPeriod),
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

        $cacheKey = 'dashboard_pinjaman_matrix:v5:' . md5(json_encode([
            'periode' => $selectedPeriod,
            'comparison' => $comparisonPeriod,
            'ph_period' => $phPeriod,
            'filters' => $filters,
            'versions' => [
                'current' => $selectedPeriod ? $this->buildTableVersionSignature('daily_loan_dinamis', 'periode', $selectedPeriod) : null,
                'comparison' => $comparisonPeriod ? $this->buildTableVersionSignature('daily_loan_dinamis', 'periode', $comparisonPeriod) : null,
                'ph' => $phPeriod ? $this->buildTableVersionSignature(self::PH_TABLE, 'periode', $phPeriod) : null,
            ],
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

        $currentSnapshot = $this->buildLoanSnapshotQuery($selectedPeriod, $filters, 'curr');
        $previousSnapshot = $comparisonPeriod
            ? $this->buildLoanSnapshotQuery($comparisonPeriod, $filters, 'prev')
            : null;
        $phSnapshot = $this->buildPhSnapshotQuery($phPeriod);

        $transitionRows = DB::query()
            ->fromSub($currentSnapshot, 'curr')
            ->leftJoinSub($previousSnapshot ?? $this->buildEmptyLoanSnapshotQuery(), 'prev', 'prev.account_number', '=', 'curr.account_number')
            ->selectRaw("
                COALESCE(prev.before_bucket, 'New Account') as before_bucket,
                curr.after_bucket as after_bucket,
                SUM(curr.current_balance) as current_total,
                SUM(
                    CASE
                        WHEN prev.previous_balance > curr.current_balance
                            AND prev.before_bucket <> 'New Account'
                            AND (
                                (prev.before_bucket IN ('L', 'LR') AND curr.after_bucket IN ('L', 'LR'))
                                OR (
                                    {$this->buildBucketRankExpression('curr.after_bucket')} IS NOT NULL
                                    AND {$this->buildBucketRankExpression('prev.before_bucket')} IS NOT NULL
                                    AND {$this->buildBucketRankExpression('curr.after_bucket')} < {$this->buildBucketRankExpression('prev.before_bucket')}
                                )
                            )
                        THEN prev.previous_balance - curr.current_balance
                        ELSE 0
                    END
                ) as principal_reduction_total,
                SUM(
                    CASE
                        WHEN curr.current_balance > COALESCE(prev.previous_balance, 0)
                            AND (
                                prev.before_bucket IS NULL
                                OR prev.before_bucket = 'New Account'
                                OR (prev.before_bucket IN ('L', 'LR') AND curr.after_bucket IN ('L', 'LR'))
                            )
                        THEN curr.current_balance - COALESCE(prev.previous_balance, 0)
                        ELSE 0
                    END
                ) as suplesi_total
            ")
            ->groupByRaw("COALESCE(prev.before_bucket, 'New Account'), curr.after_bucket")
            ->get();

        foreach ($transitionRows as $row) {
            $before = (string) $row->before_bucket;
            $after = (string) $row->after_bucket;

            if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($after, self::RAW_QUALITY_BUCKETS, true)) {
                continue;
            }

            if (in_array($after, self::QUALITY_BUCKETS, true)) {
                $bucketMap[$before][$after] = (float) ($row->current_total ?? 0);
            }

            if ((float) ($row->principal_reduction_total ?? 0) > 0) {
                $metricMap[$before]['principal_reduction'] = (float) ($row->principal_reduction_total ?? 0);
            }

            if ((float) ($row->suplesi_total ?? 0) > 0) {
                $metricMap[$before]['suplesi'] = (float) ($row->suplesi_total ?? 0);
            }
        }

        if ($previousSnapshot) {
            $vanishedRows = DB::query()
                ->fromSub($previousSnapshot, 'prev')
                ->leftJoinSub($currentSnapshot, 'curr', 'curr.account_number', '=', 'prev.account_number')
                ->leftJoinSub($phSnapshot, 'ph', 'ph.account_number', '=', 'prev.account_number')
                ->whereNull('curr.account_number')
                ->selectRaw("
                    prev.before_bucket as before_bucket,
                    SUM(CASE WHEN ph.account_number IS NOT NULL THEN prev.previous_balance ELSE 0 END) as ph_total,
                    SUM(CASE WHEN ph.account_number IS NULL THEN prev.previous_balance ELSE 0 END) as lunas_total
                ")
                ->groupBy('prev.before_bucket')
                ->get();

            foreach ($vanishedRows as $row) {
                $before = (string) $row->before_bucket;

                if (!in_array($before, self::BEFORE_ROWS, true)) {
                    continue;
                }

                if ((float) ($row->ph_total ?? 0) > 0) {
                    $metricMap[$before]['ph'] = (float) ($row->ph_total ?? 0);
                }

                if ((float) ($row->lunas_total ?? 0) > 0) {
                    $metricMap[$before]['lunas'] = (float) ($row->lunas_total ?? 0);
                }
            }
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

    private function buildLoanSnapshotQuery(string $period, array $filters, string $alias)
    {
        $bucketExpression = $this->buildQualityBucketExpression($alias);

        $query = DB::table("daily_loan_dinamis as {$alias}")
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
        $query = DB::table(self::PH_TABLE . ' as ph')
            ->selectRaw('DISTINCT TRIM(ph.acctno) as account_number')
            ->whereRaw("TRIM(COALESCE(ph.acctno, '')) <> ''")
            ->where('ph.pokok', '>', 0);

        if ($period) {
            $query->where('ph.periode', $period);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
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
                WHEN ({$rawQualityExpression}) IN ('DPK 1', 'SML1') THEN 'SML1'
                WHEN ({$rawQualityExpression}) IN ('DPK 2', 'SML2') THEN 'SML2'
                WHEN ({$rawQualityExpression}) IN ('DPK 3', 'SML3') THEN 'SML3'
                WHEN ({$rawQualityExpression}) IN ('KL', 'D1', 'D2', 'M', 'NPL') THEN 'NPL'
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
                WHEN 'SML1' THEN 2
                WHEN 'SML2' THEN 3
                WHEN 'SML3' THEN 4
                WHEN 'NPL' THEN 5
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
                'version' => $this->buildTableWideVersionSignature('daily_loan_dinamis'),
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
            $query = DB::table('daily_loan_dinamis')
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
        return $this->fetchDistinctValues('periode', desc: true)
            ->map(function ($periode) {
                try {
                    return Carbon::parse($periode)->format('Y-m-d');
                } catch (Throwable) {
                    return (string) $periode;
                }
            })
            ->values();
    }

    private function resolveEffectivePeriod(?string $requestedPeriod): ?string
    {
        try {
            if ($requestedPeriod) {
                return DB::table('daily_loan_dinamis')
                    ->where('periode', '<=', Carbon::parse($requestedPeriod)->format('Y-m-d'))
                    ->max('periode');
            }

            return DB::table('daily_loan_dinamis')->max('periode');
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
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        try {
            return $lock->block(5, function () use ($cacheKey, $ttl, $callback, $forceRefresh) {
                if (!$forceRefresh) {
                    $cached = Cache::get($cacheKey);
                    if ($cached !== null) {
                        return $cached;
                    }
                }

                $payload = $callback();
                Cache::put($cacheKey, $payload, $ttl);

                return $payload;
            });
        } finally {
            optional($lock)->release();
        }
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
            ->map(fn ($item) => trim((string) $item))
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
            'DPK 1', 'SML1' => 'SML1',
            'DPK 2', 'SML2' => 'SML2',
            'DPK 3', 'SML3' => 'SML3',
            'KL', 'D1', 'D2', 'M', 'NPL' => 'NPL',
            'PH' => 'PH',
            'PAY' => 'Pay',
            default => 'L',
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
