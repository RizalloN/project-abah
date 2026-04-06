<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Throwable;

class DashboardPinjamanReportController extends Controller
{
    private const PH_TABLE = 'lw325_ph';
    private const RAW_QUALITY_BUCKETS = ['L', 'LR', 'SML1', 'SML2', 'SML3', 'NPL', 'PH', 'Pay'];

    private const QUALITY_BUCKETS = ['L', 'LR', 'SML1', 'SML2', 'SML3', 'NPL'];

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

        $cacheKey = 'dashboard_pinjaman_filters:' . md5(json_encode([
            'periode' => $selectedPeriod,
            'filters' => $filters,
        ]));

        $payload = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($selectedPeriod, $filters) {
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
        });

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

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $cacheKey = 'dashboard_pinjaman_matrix:v4:' . md5(json_encode([
            'periode' => $selectedPeriod,
            'comparison' => $comparisonPeriod,
            'filters' => $filters,
        ]));

        $lockKey = $cacheKey . ':lock';

        $cachedPayload = Cache::get($cacheKey);

        if ($cachedPayload) {
            [$matrixRows, $grandTotals, $grandTotalValue] = $cachedPayload;
        } else {
            $lock = Cache::lock($lockKey, 30);

            try {
                $cachedPayload = $lock->block(5, function () use ($cacheKey, $selectedPeriod, $comparisonPeriod, $filters) {
                    return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($selectedPeriod, $comparisonPeriod, $filters) {
                        return $this->buildMatrixData($selectedPeriod, $comparisonPeriod, $filters);
                    });
                });
            } finally {
                optional($lock)->release();
            }

            [$matrixRows, $grandTotals, $grandTotalValue] = $cachedPayload;
        }

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

        $previousQualityMap = [];
        $previousBalanceMap = [];
        $currentAccounts = [];
        $phAccounts = [];
        $phPeriod = $this->resolvePhPeriod($selectedPeriod);

        if ($phPeriod) {
            DB::table(self::PH_TABLE)
                ->where('periode', $phPeriod)
                ->whereNotNull('acctno')
                ->select('acctno', 'pokok')
                ->orderBy('acctno')
                ->chunk(5000, function ($rows) use (&$phAccounts) {
                    foreach ($rows as $row) {
                        $accountNumber = $this->normalizeAccountNumber($row->acctno);

                        if ($accountNumber === '') {
                            continue;
                        }

                        if ((float) ($row->pokok ?? 0) > 0) {
                            $phAccounts[$accountNumber] = true;
                        }
                    }
                });
        }

        if ($comparisonPeriod) {
            DB::table('daily_loan_dinamis')
                ->where('periode', $comparisonPeriod)
                ->select('id', 'nomor_rekening1', 'baki_debet1', 'kolek_detail', 'umur_tunggakan', 'flag_restruk')
                ->orderBy('id')
                ->chunkById(5000, function ($rows) use (&$previousQualityMap, &$previousBalanceMap) {
                    foreach ($rows as $row) {
                        $accountNumber = $this->normalizeAccountNumber($row->nomor_rekening1);

                        if ($accountNumber === '') {
                            continue;
                        }

                        $previousQualityMap[$accountNumber] = $this->mapQualityBucket(
                            $row->kolek_detail,
                            $row->umur_tunggakan,
                            $row->flag_restruk
                        );
                        $previousBalanceMap[$accountNumber] = (float) ($row->baki_debet1 ?? 0);
                    }
                });
        }

        $bucketMap = [];
        $metricMap = [];

        $currentQuery = DB::table('daily_loan_dinamis')
            ->where('periode', $selectedPeriod)
            ->select(
                'id',
                'nomor_rekening1',
                'baki_debet1',
                'kolek_detail',
                'umur_tunggakan',
                'flag_restruk',
                'segmen_dashboard',
                'produk_dashboard',
                'cabang1',
                'unit1'
            )
            ->orderBy('id');

        $this->applyFilterConstraint($currentQuery, 'segmen_dashboard', $filters['segmen']);
        $this->applyFilterConstraint($currentQuery, 'produk_dashboard', $filters['produk']);
        $this->applyFilterConstraint($currentQuery, 'cabang1', $filters['cabang']);
        $this->applyFilterConstraint($currentQuery, 'unit1', $filters['unit']);

        $currentQuery->chunkById(5000, function ($rows) use (&$bucketMap, &$metricMap, &$currentAccounts, $previousQualityMap, $previousBalanceMap) {
            foreach ($rows as $row) {
                $accountNumber = $this->normalizeAccountNumber($row->nomor_rekening1);
                $after = $this->mapQualityBucket($row->kolek_detail, $row->umur_tunggakan, $row->flag_restruk);
                $before = $accountNumber !== '' && isset($previousQualityMap[$accountNumber])
                    ? $previousQualityMap[$accountNumber]
                    : 'New Account';

                if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($after, self::RAW_QUALITY_BUCKETS, true)) {
                    continue;
                }

                if ($accountNumber !== '') {
                    $currentAccounts[$accountNumber] = true;
                }

                $currentBalance = (float) ($row->baki_debet1 ?? 0);
                $previousBalance = $accountNumber !== '' ? (float) ($previousBalanceMap[$accountNumber] ?? 0) : 0;

                $bucketMap[$before][$after] = ($bucketMap[$before][$after] ?? 0) + $currentBalance;
                $metricMap[$before]['principal_reduction'] = ($metricMap[$before]['principal_reduction'] ?? 0)
                    + max($previousBalance - $currentBalance, 0);
                $metricMap[$before]['suplesi'] = ($metricMap[$before]['suplesi'] ?? 0)
                    + max($currentBalance - $previousBalance, 0);
            }
        });

        foreach ($previousQualityMap as $accountNumber => $before) {
            if (!in_array($before, self::BEFORE_ROWS, true) || isset($currentAccounts[$accountNumber])) {
                continue;
            }

            $previousBalance = (float) ($previousBalanceMap[$accountNumber] ?? 0);
            if ($previousBalance <= 0) {
                continue;
            }

            if (isset($phAccounts[$accountNumber])) {
                $metricMap[$before]['ph'] = ($metricMap[$before]['ph'] ?? 0) + $previousBalance;
                continue;
            }

            $metricMap[$before]['lunas'] = ($metricMap[$before]['lunas'] ?? 0) + $previousBalance;
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

    private function fetchDistinctValues(string $column, bool $desc = false): Collection
    {
        try {
            return Cache::remember("dashboard_pinjaman_distinct:{$column}:" . ($desc ? 'desc' : 'asc'), now()->addMinutes(15), function () use ($column, $desc) {
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
}
