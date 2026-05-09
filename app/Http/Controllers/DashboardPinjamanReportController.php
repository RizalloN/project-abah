<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureDashboardSnapshotJob;
use App\Support\DashboardHarianSnapshotService;
use App\Support\DashboardPinjamanChartPeriodikService;
use App\Support\ReportIndexHintResolver;
use App\Support\LoanQualityBucketMapper;
use App\Support\DashboardPinjamanKreditService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
    private const SMALL_ARREARS_AREA_ALL = 'AREA_6_ALL';
    private const SMALL_ARREARS_AREA_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const SMALL_ARREARS_ALL_UKER = 'ALL_UKER';
    private const KOLEK_MISMATCH_AREA_ALL = 'AREA_6_ALL';

    private const BEFORE_ROWS = ['New Account', 'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];

    private const OUTPUT_COLUMNS = ['Turunan Pokok', 'Suplesi', 'PH', 'Lunas'];
    private const KOLEK_MISMATCH_RULE_LABEL = 'kolek_vs_umur_tunggakan_v2';
    private const MATRIX_MODAL_COLUMNS = [
        'pivot_before_bucket',
        'pivot_after_bucket',
        'periode',
        'cabang1',
        'unit1',
        'cifno',
        'nomor_rekening1',
        'nama_debitur1',
        'plafon',
        'baki_debet1',
        'kol_adk1',
        'kolek_detail',
        'kolek',
        'total_kewajiban',
        'tunggakan_pokok',
        'tunggakan_bunga',
        'tunggakan_penalti',
        'umur_tunggakan',
        'tgl_realisasi',
        'tgl_jatuh_tempo',
        'tanggal_menunggak',
        'tgl_bayar_terakhir',
        'next_pmt_date',
        'next_pmt_int_date',
        'bap',
        'payment_amount',
        'final_payment_amount',
        'sai_deffered',
        'sai1',
        'freq_payment',
        'freq_int_payment',
        'pn_pengelola1',
        'segmen_dashboard',
        'produk_dashboard',
        'tgl_akad_restruk',
        'flag_restruk',
    ];
    private const MATRIX_PIVOT_DETAIL_COLUMNS = [
        'pivot_before_bucket',
        'pivot_after_bucket',
        'pivot_previous_balance',
    ];

    public function summaryIndex(Request $request)
    {
        return $this->renderIndex($request, 'summary');
    }

    public function matrixIndex(Request $request)
    {
        return $this->renderIndex($request, 'matrix');
    }

    public function mismatchIndex(Request $request)
    {
        return $this->renderIndex($request, 'mismatch');
    }

    public function kreditIndex(Request $request)
    {
        $periods = $this->fetchKreditPeriods();
        $selectedPeriod = $this->resolveKreditEffectivePeriod($request->input('periode'));
        $selectedCategory = $request->input('kategori', 'SME');

        return view('report.dashboard-pinjaman.kredit', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'selectedCategory' => $selectedCategory,
            'categories' => ['SME', 'Consumer', 'Mikro'],
        ]);
    }

    public function kreditData(Request $request)
    {
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveKreditEffectivePeriod($request->input('periode'));
        $selectedCategory = $request->input('kategori', 'SME');
        $forceRefresh = $request->boolean('refresh');

        if (!$selectedPeriod) {
            return response()->json([
                'selected_period' => null,
                'category' => $selectedCategory,
                'os' => [],
                'sml' => [],
                'npl' => [],
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_kredit_unified:v3:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'kategori' => $selectedCategory,
        ]));

        $data = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(10),
            fn () => app(DashboardPinjamanKreditService::class)->getUnifiedSegmentData($selectedPeriod, $selectedCategory),
            $forceRefresh,
            fn () => [
                'status' => 'warming',
                'os' => [],
                'sml' => [],
                'npl' => [],
            ]
        );

        return response()->json(array_merge([
            'selected_period' => $selectedPeriod,
            'category' => $selectedCategory,
        ], $data));
    }

    public function chartPeriodikIndex(Request $request)
    {
        $payload = $this->chartPeriodikService()->buildIndexPayload(
            $request->input('periode'),
            $request->input('cabang1'),
            $request->input('unit1')
        );

        return view('report.dashboard-pinjaman.chart-periodik', $payload);
    }

    public function chartPeriodikFilters(Request $request)
    {
        $this->releaseSessionLockIfNeeded();

        $payload = $this->chartPeriodikService()->buildFilterPayload(
            $request->input('periode'),
            $request->input('cabang1')
        );

        return response()->json($payload);
    }

    public function chartPeriodikData(Request $request)
    {
        $this->releaseSessionLockIfNeeded();

        $payload = $this->chartPeriodikService()->buildChartPayload(
            $request->input('periode'),
            $request->input('cabang1'),
            $request->input('unit1')
        );

        return response()->json($payload);
    }

    public function smallArrearsIndex(Request $request)
    {
        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $selectedBranches = $branchSelection['selected_values'];
        $effectiveBranches = $branchSelection['effective_branches'];
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $selectedUnits = $unitSelection['selected_values'];
        $branchOptions = $this->smallArrearsBranchOptions();
        $unitOptions = $branchSelection['is_area_all']
            ? collect()
            : collect([self::SMALL_ARREARS_ALL_UKER])->merge($this->fetchSmallArrearsDistinctValues('unit1', $selectedPeriod, $effectiveBranches))->values();
        $selectedUnits = $branchSelection['is_area_all']
            ? []
            : array_values(array_intersect($selectedUnits, $unitOptions->all()));
        if (!$branchSelection['is_area_all'] && $selectedUnits === []) {
            $selectedUnits = [self::SMALL_ARREARS_ALL_UKER];
        }

        return view('report.dashboard-pinjaman.tunggakan-kecil', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedBranches' => $selectedBranches,
            'effectiveBranches' => $effectiveBranches,
            'isAreaAllSelected' => $branchSelection['is_area_all'],
            'selectedUnits' => $selectedUnits,
            'branchOptions' => $branchOptions,
            'unitOptions' => $unitOptions,
            'isAllUkerSelected' => in_array(self::SMALL_ARREARS_ALL_UKER, $selectedUnits, true) || (!$branchSelection['is_area_all'] && $selectedUnits === []),
        ]);
    }

    public function smallArrearsFilters(Request $request)
    {
        @set_time_limit(30);
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $selectedBranches = $branchSelection['selected_values'];
        $effectiveBranches = $branchSelection['effective_branches'];
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $forceRefresh = $request->boolean('refresh');

        $cacheKey = 'dashboard_pinjaman_tunggakan_kecil_filters:v1:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'cabang1' => $selectedBranches,
            'unit1' => $unitSelection['selected_values'],
        ]));

        $payload = $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod, $selectedBranches, $effectiveBranches, $branchSelection, $unitSelection) {
            $unitOptions = $branchSelection['is_area_all']
                ? collect()
                : collect([self::SMALL_ARREARS_ALL_UKER])->merge($this->fetchSmallArrearsDistinctValues('unit1', $selectedPeriod, $effectiveBranches))->values();

            return [
                'branch_options' => $this->smallArrearsBranchOptions()->all(),
                'unit_options' => $unitOptions->all(),
                'selected_branches' => $selectedBranches,
                'effective_branches' => $effectiveBranches,
                'is_area_all' => $branchSelection['is_area_all'],
                'selected_units' => $unitSelection['selected_values'],
                'is_all_uker' => $unitSelection['is_all_uker'],
            ];
        }, $forceRefresh, fn () => [
            'status' => 'warming',
            'branch_options' => $this->smallArrearsBranchOptions()->all(),
            'unit_options' => [],
            'selected_branches' => $selectedBranches,
            'effective_branches' => $effectiveBranches,
            'is_area_all' => $branchSelection['is_area_all'],
            'selected_units' => $unitSelection['selected_values'],
            'is_all_uker' => $unitSelection['is_all_uker'],
        ]);

        return response()->json([
            'available_periods' => $availablePeriods->all(),
            'selected_period' => $selectedPeriod,
            'branch_options' => $payload['branch_options'],
            'unit_options' => $payload['unit_options'],
            'selected_branches' => $payload['selected_branches'],
            'effective_branches' => $payload['effective_branches'],
            'is_area_all' => $payload['is_area_all'],
            'selected_units' => $payload['selected_units'],
            'is_all_uker' => $payload['is_all_uker'],
        ]);
    }

    public function smallArrearsData(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $selectedBranches = $branchSelection['selected_values'];
        $effectiveBranches = $branchSelection['effective_branches'];
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $selectedUnits = $unitSelection['selected_values'];
        $effectiveUnits = $unitSelection['effective_units'];
        $forceRefresh = $request->boolean('refresh');

        if (!$selectedPeriod) {
            return response()->json([
                'selected_period' => null,
                'selected_branches' => [self::SMALL_ARREARS_AREA_ALL],
                'effective_branches' => self::SMALL_ARREARS_AREA_BRANCHES,
                'is_area_all' => true,
                'selected_units' => [],
                'effective_units' => [],
                'is_all_uker' => true,
                'group_label' => 'BRANCH OFFICE',
                'rows' => [],
                'total' => [
                    'current' => 0,
                    'ytd' => 0,
                    'mtd' => 0,
                    'current_tunggakan' => 0.0,
                    'ytd_tunggakan' => 0.0,
                    'mtd_tunggakan' => 0.0,
                    'total_tunggakan' => 0.0,
                ],
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_tunggakan_kecil_data:v3:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'cabang1' => $selectedBranches,
            'unit1' => $effectiveUnits,
        ]));

        $payload = $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod, $effectiveBranches, $effectiveUnits, $branchSelection) {
            return $this->buildSmallArrearsPayload($selectedPeriod, $effectiveBranches, $effectiveUnits, $branchSelection['is_area_all']);
        }, $forceRefresh, fn () => [
            'status' => 'warming',
            'group_label' => $branchSelection['is_area_all'] ? 'BRANCH OFFICE' : 'UNIT KERJA',
            'rows' => [],
            'total' => [
                'current' => 0,
                'ytd' => 0,
                'mtd' => 0,
                'current_tunggakan' => 0.0,
                'ytd_tunggakan' => 0.0,
                'mtd_tunggakan' => 0.0,
                'total_tunggakan' => 0.0,
            ],
        ]);

        return response()->json(array_merge([
            'selected_period' => $selectedPeriod,
            'selected_branches' => $selectedBranches,
            'effective_branches' => $effectiveBranches,
            'is_area_all' => $branchSelection['is_area_all'],
            'selected_units' => $selectedUnits,
            'effective_units' => $effectiveUnits,
            'is_all_uker' => $unitSelection['is_all_uker'],
        ], $payload));
    }

    public function smallArrearsExport(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);

        abort_if(!$selectedPeriod, 422, 'Periode wajib dipilih.');

        $effectiveBranches = $branchSelection['effective_branches'];
        $effectiveUnits = $unitSelection['effective_units'];
        $exportColumns = $this->collectSmallArrearsExportColumns();

        $filename = sprintf(
            'tunggakan-kecil_%s_%s_%s.xlsx',
            str_replace('-', '', $selectedPeriod),
            $branchSelection['is_area_all']
                ? 'area-6'
                : $this->sanitizeExportToken(implode('-', $branchSelection['selected_values'])),
            $effectiveUnits === []
                ? 'all-uker'
                : $this->sanitizeExportToken(implode('-', $effectiveUnits))
        );

        Log::info('Dashboard pinjaman small arrears export generated.', [
            'selected_period' => $selectedPeriod,
            'selected_branches' => $branchSelection['selected_values'],
            'effective_branches' => $effectiveBranches,
            'effective_units' => $effectiveUnits,
        ]);

        return response()->streamDownload(function () use ($selectedPeriod, $effectiveBranches, $effectiveUnits, $exportColumns) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tunggakan Kecil');

            foreach ($exportColumns as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $column);
            }

            $rowIndex = 2;
            foreach ($this->buildSmallArrearsExportQuery($selectedPeriod, $effectiveBranches, $effectiveUnits)->cursor() as $row) {
                $rowData = (array) $row;
                foreach ($exportColumns as $columnIndex => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex,
                        $rowData[$column] ?? ''
                    );
                }
                $rowIndex++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function fetchKreditPeriods(): Collection
    {
        $cacheKey = 'dashboard_pinjaman_kredit_periods:v2' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(30), function () {
            return DB::table('dashboard_harian_snapshots')
                ->select('snapshot_period')
                ->distinct()
                ->orderByDesc('snapshot_period')
                ->pluck('snapshot_period')
                ->map(fn ($p) => (string) $p)
                ->values();
            });
    }

    private function chartPeriodikService(): DashboardPinjamanChartPeriodikService
    {
        return app(DashboardPinjamanChartPeriodikService::class);
    }

    private function resolveKreditEffectivePeriod(?string $requestedPeriod): ?string
    {
        if ($requestedPeriod) {
            return $requestedPeriod;
        }

        $periods = $this->fetchKreditPeriods();
        return $periods->first() ?? null;
    }

    private function renderIndex(Request $request, string $mode)
    {
        $isMatrixMode = $this->normalizeReportMode($mode) === 'matrix';
        $periods = $isMatrixMode ? $this->fetchRecoveryReportPeriods() : $this->fetchPeriods();

        $requestedPeriod = $request->input('periode');
        $selectedPeriod = $isMatrixMode
            ? $this->resolveRecoveryReportPeriod($requestedPeriod)
            : $this->resolveEffectivePeriod($requestedPeriod);
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        return view("report.dashboard-pinjaman.{$mode}", [
            'periods' => $periods,
            'filters' => $filters,
            'selectedPeriod' => $selectedPeriod,
            'comparisonPeriod' => $comparisonPeriod,
            'matrixColumns' => self::QUALITY_BUCKETS,
            'requestedPeriod' => $isMatrixMode ? $selectedPeriod : $requestedPeriod,
            'selectedMode' => $this->normalizeReportMode($mode),
            'mismatchRequestedPeriod' => $request->input('mismatch_periode'),
            'mismatchSelectedPeriod' => $this->resolveEffectivePeriod($request->input('mismatch_periode')),
            'mismatchSelectedBranches' => $this->resolveKolekMismatchBranchSelection($request->input('mismatch_cabang1'))['selected_values'],
        ]);
    }

    public function filters(Request $request)
    {
        @set_time_limit(30);
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveRecoveryReportPeriod($request->input('periode'));
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
        }, $forceRefresh, fn () => [
            'status' => 'warming',
            'segments' => collect(),
            'products' => collect(),
            'branches' => collect(),
            'units' => collect(),
        ]);

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

        $selectedPeriod = $this->resolveRecoveryReportPeriod($request->input('periode'));
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);
        $forceRefresh = $request->boolean('refresh');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $phPeriod = $this->resolvePhPeriod($selectedPeriod);

        $cacheKey = 'dashboard_pinjaman_matrix_direct:v2:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'comparison' => $comparisonPeriod,
            'ph_period' => $phPeriod,
            'recovery_source' => $this->shouldUseLw325RecoveryMetrics($selectedPeriod) ? self::PH_TABLE : 'loan_movement',
            'filters' => $filters,
        ]));

        [$matrixRows, $grandTotals, $grandTotalValue] = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(3),
            fn () => $this->buildMatrixData($selectedPeriod, $comparisonPeriod, $filters),
            $forceRefresh,
            fn () => [[], [], 0.0]
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

    public function matrixDetail(Request $request)
    {
        @set_time_limit(60);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveRecoveryReportPeriod($request->input('periode'));
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);
        $beforeBucket = trim((string) $request->input('before_bucket', ''));
        $limit = max(10, min(50, (int) $request->input('limit', 25)));
        $offset = max(0, (int) $request->input('offset', 0));

        abort_if(!$selectedPeriod || !in_array($beforeBucket, self::BEFORE_ROWS, true), 422, 'Periode dan bucket pivot wajib valid.');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $columns = $this->collectMatrixModalColumns();
        $rows = $this->buildMatrixDrilldownQuery($selectedPeriod, $comparisonPeriod, $filters, $beforeBucket, $columns)
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->map(fn ($row) => (array) $row)->values();

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'before_bucket' => $beforeBucket,
            'columns' => $columns,
            'rows' => $rows,
            'limit' => $limit,
            'offset' => $offset,
            'next_offset' => $hasMore ? $offset + $limit : null,
            'has_more' => $hasMore,
        ]);
    }

    public function matrixExport(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveRecoveryReportPeriod($request->input('periode'));
        $comparisonPeriod = $this->resolveComparisonPeriod($selectedPeriod);
        $beforeBucket = trim((string) $request->input('before_bucket', ''));

        abort_if(!$selectedPeriod || !in_array($beforeBucket, self::BEFORE_ROWS, true), 422, 'Periode dan bucket pivot wajib valid.');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $exportColumns = $this->collectMatrixDetailColumns();
        $query = $this->buildMatrixDrilldownQuery($selectedPeriod, $comparisonPeriod, $filters, $beforeBucket, $exportColumns);
        $filename = sprintf(
            'matrix-pergeseran-kolek_%s_%s.xlsx',
            str_replace('-', '', $selectedPeriod),
            $this->sanitizeExportToken($beforeBucket)
        );

        return response()->streamDownload(function () use ($query, $exportColumns) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Detail Matrix Kolek');

            foreach ($exportColumns as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $column);
            }

            $rowIndex = 2;
            foreach ($query->cursor() as $row) {
                foreach ($exportColumns as $columnIndex => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($columnIndex + 1) . $rowIndex,
                        $row->{$column} ?? ''
                    );
                }
                $rowIndex++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function mismatchFilters(Request $request)
    {
        @set_time_limit(30);
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveEffectivePeriod($request->input('periode'));

        if (!$selectedPeriod) {
            return response()->json([
                'selected_period' => null,
                'branches' => [],
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_kolek_mismatch_filters:v2:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
        ]));

        $branches = $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod) {
            $availableBranches = DB::table('daily_loan_dinamis')
                ->where('periode', $selectedPeriod)
                ->whereNotNull('cabang1')
                ->where('cabang1', '<>', '')
                ->distinct()
                ->orderBy('cabang1')
                ->pluck('cabang1')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            $areaBranches = collect(self::SMALL_ARREARS_AREA_BRANCHES)
                ->filter(fn (string $branch) => $availableBranches->contains($branch))
                ->values();

            return collect([self::KOLEK_MISMATCH_AREA_ALL])
                ->merge($areaBranches)
                ->merge($availableBranches->reject(fn ($branch) => $areaBranches->contains($branch)))
                ->unique()
                ->values();
        });

        return response()->json([
            'selected_period' => $selectedPeriod,
            'branches' => $branches->all(),
        ]);
    }

    public function mismatchData(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveEffectivePeriod($request->input('periode'));
        $branchSelection = $this->resolveKolekMismatchBranchSelection($request->input('cabang1'));
        $selectedBranches = $branchSelection['effective_branches'];
        $forceRefresh = $request->boolean('refresh');

        if (!$selectedPeriod || $selectedBranches === []) {
            return response()->json([
                'selected_period' => $selectedPeriod,
                'selected_branch' => null,
                'selected_branches' => $branchSelection['selected_values'],
                'effective_branches' => $selectedBranches,
                'is_area_all' => $branchSelection['is_area_all'],
                'summary_rows' => [],
                'audit' => [
                    'rule' => self::KOLEK_MISMATCH_RULE_LABEL,
                    'scanned_rows' => 0,
                    'matched_rows' => 0,
                    'mismatch_rows' => 0,
                    'units_with_mismatch' => 0,
                    'total_outstanding_balance' => 0.0,
                ],
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_kolek_mismatch_data:v8:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'cabang1' => $selectedBranches,
        ]));

        $payload = $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($selectedPeriod, $selectedBranches) {
            return $this->buildKolekMismatchSummary($selectedPeriod, $selectedBranches, count($selectedBranches) > 1);
        }, $forceRefresh, fn () => [
            'status' => 'warming',
            'summary_rows' => [],
            'audit' => [
                'rule' => self::KOLEK_MISMATCH_RULE_LABEL,
                'scanned_rows' => 0,
                'matched_rows' => 0,
                'mismatch_rows' => 0,
                'units_with_mismatch' => 0,
                'total_outstanding_balance' => 0.0,
            ],
        ]);

        return response()->json([
            'selected_period' => $selectedPeriod,
            'selected_branch' => $branchSelection['label'],
            'selected_branches' => $branchSelection['selected_values'],
            'effective_branches' => $selectedBranches,
            'is_area_all' => $branchSelection['is_area_all'],
            'summary_rows' => $payload['summary_rows'],
            'audit' => $payload['audit'],
        ]);
    }

    public function mismatchExport(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveEffectivePeriod($request->input('periode'));
        $selectedBranch = trim((string) (is_array($request->input('cabang1')) ? ($request->input('cabang1')[0] ?? '') : $request->input('cabang1', '')));
        $selectedUnit = trim((string) $request->input('unit1', ''));

        abort_if(!$selectedPeriod || $selectedBranch === '', 422, 'Periode dan cabang wajib dipilih.');

        $rows = $this->fetchKolekMismatchRows($selectedPeriod, $selectedBranch, $selectedUnit);
        $exportColumns = $this->collectKolekExportColumns();

        Log::info('Dashboard pinjaman mismatch export generated.', [
            'selected_period' => $selectedPeriod,
            'selected_branch' => $selectedBranch,
            'selected_unit' => $selectedUnit !== '' ? $selectedUnit : 'ALL UKER',
            'mismatch_rows' => count($rows),
            'rule' => self::KOLEK_MISMATCH_RULE_LABEL,
        ]);

        $filename = sprintf(
            'kolek-tidak-sesuai_%s_%s_%s.xlsx',
            str_replace('-', '', $selectedPeriod),
            $this->sanitizeExportToken($selectedBranch),
            $selectedUnit !== '' ? $this->sanitizeExportToken($selectedUnit) : 'all-uker'
        );

        return response()->streamDownload(function () use ($rows, $exportColumns) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kolek Tidak Sesuai');

            foreach ($exportColumns as $index => $column) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($index + 1) . '1',
                    $column
                );
            }

            foreach ($rows as $rowIndex => $row) {
                foreach ($exportColumns as $columnIndex => $column) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 2),
                        Arr::get($row, $column, '')
                    );
                }
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
        $useCurrentSnapshot = $this->shouldUseSnapshot($selectedPeriod, $filters);
        $useComparisonSnapshot = $comparisonPeriod ? $this->shouldUseSnapshot($comparisonPeriod, $filters) : false;
        $useLw325RecoveryMetrics = $this->shouldUseLw325RecoveryMetrics($selectedPeriod);

        // Ensure both periods use the same source to avoid account_number format mismatches
        // If one period doesn't have snapshot, both must use daily_loan_dinamis
        if ($comparisonPeriod && $useCurrentSnapshot !== $useComparisonSnapshot) {
            $useCurrentSnapshot = $useCurrentSnapshot && $useComparisonSnapshot;
            $useComparisonSnapshot = $useCurrentSnapshot;
        }

        $bucketMap = [];
        $metricMap = [];

        // Optimize: Set read-only connection mode for better database optimization
        try {
            // Movement comparison must stay database-side so large portfolios do not require PHP in-memory joins.
            $matrixRowsRaw = $this->buildMovementMatrixAggregateQuery(
                $selectedPeriod,
                $comparisonPeriod,
                $filters,
                $useCurrentSnapshot,
                $useComparisonSnapshot
            )->get();
            
            $matrixRowsCount = $matrixRowsRaw->count();
            
            foreach ($matrixRowsRaw as $row) {
                $before = (string) ($row->before_bucket ?? 'New Account');
                $after = (string) ($row->after_bucket ?? '');
                $amountCents = (int) ($row->amount_cents ?? 0);

                if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($after, self::QUALITY_BUCKETS, true) || $amountCents <= 0) {
                    continue;
                }

                $bucketMap[$before][$after] = $amountCents;
            }

            $metricRowsRaw = $this->buildMovementMetricAggregateQuery(
                $selectedPeriod,
                $comparisonPeriod,
                $phPeriod,
                $filters,
                $useCurrentSnapshot,
                $useComparisonSnapshot,
                $useLw325RecoveryMetrics
            )->get();
            
            $metricRowsCount = $metricRowsRaw->count();
            
            foreach ($metricRowsRaw as $row) {
                $before = (string) ($row->before_bucket ?? 'New Account');
                $metric = (string) ($row->metric_type ?? '');
                $amountCents = (int) ($row->amount_cents ?? 0);

                if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($metric, ['principal_reduction', 'suplesi', 'ph', 'lunas'], true) || $amountCents <= 0) {
                    continue;
                }

                $metricMap[$before][$metric] = ($metricMap[$before][$metric] ?? 0) + $amountCents;
            }
        } catch (Throwable $e) {
            Log::error('Dashboard pinjaman matrix query failed.', [
                'error' => $e->getMessage(),
                'selected_period' => $selectedPeriod,
                'comparison_period' => $comparisonPeriod,
            ]);
            return [$emptyRows, $emptyTotals, null];
        }

        $matrixRows = [];
        $matrixGrandTotals = array_fill(0, count(self::QUALITY_BUCKETS), 0);
        $metricNames = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
        $metricTotals = array_fill_keys($metricNames, 0);
        $grandTotalCents = 0;

        foreach (self::BEFORE_ROWS as $beforeLabel) {
            $values = [];
            $rowTotalCents = 0;
            $rowMetrics = [];

            foreach (self::QUALITY_BUCKETS as $index => $afterLabel) {
                $valueCents = (int) ($bucketMap[$beforeLabel][$afterLabel] ?? 0);
                $rowTotalCents += $valueCents;
                $matrixGrandTotals[$index] += $valueCents;
                $values[] = $valueCents > 0 ? $this->centsToAmount($valueCents) : null;
            }

            foreach ($metricNames as $metricName) {
                $metricCents = (int) ($metricMap[$beforeLabel][$metricName] ?? 0);
                $metricTotals[$metricName] += $metricCents;
                $rowMetrics[$metricName] = $metricCents > 0 ? $this->centsToAmount($metricCents) : null;
            }

            $grandTotalCents += $rowTotalCents;

            $matrixRows[] = [
                'label' => $beforeLabel,
                'values' => $values,
                'metrics' => $rowMetrics,
                'total' => $rowTotalCents > 0 ? $this->centsToAmount($rowTotalCents) : null,
            ];
        }

        $grandTotals = [
            'matrix' => array_map(
                fn (int $columnTotalCents) => $columnTotalCents > 0 ? $this->centsToAmount($columnTotalCents) : null,
                $matrixGrandTotals
            ),
            'metrics' => array_map(
                fn (int $metricCents) => $metricCents > 0 ? $this->centsToAmount($metricCents) : null,
                $metricTotals
            ),
        ];

        Log::info('Dashboard pinjaman matrix query aggregated.', [
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'uses_snapshot' => $useCurrentSnapshot && (!$comparisonPeriod || $useComparisonSnapshot),
            'matrix_row_count' => $matrixRowsCount ?? 0,
            'metric_row_count' => $metricRowsCount ?? 0,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return [$matrixRows, $grandTotals, $grandTotalCents > 0 ? $this->centsToAmount($grandTotalCents) : null];
    }

    private function buildMatrixDrilldownQuery(string $selectedPeriod, ?string $comparisonPeriod, array $filters, string $beforeBucket, array $columns): Builder
    {
        $currentAlias = 'curr_detail';
        $previousAlias = 'prev_detail';
        $currentBucketExpression = $this->buildQualityBucketExpression($currentAlias);
        $previousBucketExpression = $this->buildQualityBucketExpression($previousAlias);
        $pivotColumns = array_values(array_intersect($columns, self::MATRIX_PIVOT_DETAIL_COLUMNS));
        $sourceColumns = array_values(array_filter(
            array_diff($columns, self::MATRIX_PIVOT_DETAIL_COLUMNS),
            fn (string $column) => Schema::hasColumn('daily_loan_dinamis', $column)
        ));
        $pivotSelects = [];

        if (in_array('pivot_before_bucket', $pivotColumns, true)) {
            $pivotSelects[] = "
                CASE
                    WHEN {$previousAlias}.nomor_rekening1 IS NULL THEN 'New Account'
                    ELSE {$previousBucketExpression}
                END as pivot_before_bucket
            ";
        }
        if (in_array('pivot_after_bucket', $pivotColumns, true)) {
            $pivotSelects[] = "{$currentBucketExpression} as pivot_after_bucket";
        }
        if (in_array('pivot_previous_balance', $pivotColumns, true)) {
            $pivotSelects[] = "COALESCE({$previousAlias}.baki_debet1, 0) as pivot_previous_balance";
        }

        $query = DB::table(DB::raw($this->buildLoanSnapshotSource($currentAlias, $filters)))
            ->leftJoin(DB::raw($this->buildLoanSnapshotSource($previousAlias, $filters)), function ($join) use ($currentAlias, $previousAlias, $comparisonPeriod, $filters) {
                $join->on("{$currentAlias}.nomor_rekening1", '=', "{$previousAlias}.nomor_rekening1");

                if ($comparisonPeriod) {
                    $join->where("{$previousAlias}.periode", '=', $comparisonPeriod);
                } else {
                    $join->whereRaw('1 = 0');
                }

                if (!empty($filters['segmen'])) {
                    $join->whereIn("{$previousAlias}.segmen_dashboard", $filters['segmen']);
                }
                if (!empty($filters['produk'])) {
                    $join->whereIn("{$previousAlias}.produk_dashboard", $filters['produk']);
                }
                if (!empty($filters['cabang'])) {
                    $join->whereIn("{$previousAlias}.cabang1", $filters['cabang']);
                }
                if (!empty($filters['unit'])) {
                    $join->whereIn("{$previousAlias}.unit1", $filters['unit']);
                }
            })
            ->where("{$currentAlias}.periode", $selectedPeriod)
            ->whereIn(DB::raw($currentBucketExpression), self::QUALITY_BUCKETS);

        if (!empty($pivotSelects)) {
            $query->selectRaw(implode(",\n", $pivotSelects));
        }

        foreach ($sourceColumns as $column) {
            $query->addSelect(DB::raw("{$currentAlias}.`{$column}` as `{$column}`"));
        }

        $this->applyFilterConstraint($query, "{$currentAlias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($query, "{$currentAlias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($query, "{$currentAlias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($query, "{$currentAlias}.unit1", $filters['unit']);

        if ($beforeBucket === 'New Account') {
            $query->where(function (Builder $where) use ($currentAlias, $previousAlias) {
                $where->whereNull("{$previousAlias}.nomor_rekening1")
                    ->orWhereNull("{$currentAlias}.nomor_rekening1")
                    ->orWhere("{$currentAlias}.nomor_rekening1", '');
            });
        } else {
            $query->whereNotNull("{$currentAlias}.nomor_rekening1")
                ->where("{$currentAlias}.nomor_rekening1", '<>', '')
                ->whereNotNull("{$previousAlias}.nomor_rekening1")
                ->whereRaw("({$previousBucketExpression}) = ?", [$beforeBucket]);
        }

        return $query;
    }

    private function collectMatrixModalColumns(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $available = array_fill_keys(Schema::getColumnListing('daily_loan_dinamis'), true);
        $cached = array_values(array_filter(self::MATRIX_MODAL_COLUMNS, function (string $column) use ($available) {
            return in_array($column, self::MATRIX_PIVOT_DETAIL_COLUMNS, true) || isset($available[$column]);
        }));

        return $cached;
    }

    private function collectMatrixDetailColumns(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = array_values(array_unique(array_merge(
            self::MATRIX_PIVOT_DETAIL_COLUMNS,
            Schema::getColumnListing('daily_loan_dinamis')
        )));

        return $cached;
    }

    private function buildMovementMatrixAggregateQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useCurrentSnapshot = null,
        ?bool $useComparisonSnapshot = null
    )
    {
        $currentSnapshot = $this->buildAggregatedLoanSnapshotQuery($selectedPeriod, $filters, 'curr', $useCurrentSnapshot);
        $previousSnapshot = $comparisonPeriod
            ? $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev', $useComparisonSnapshot)
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

    private function buildMovementMetricAggregateQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        ?string $phPeriod,
        array $filters,
        ?bool $useCurrentSnapshot = null,
        ?bool $useComparisonSnapshot = null,
        bool $useLw325RecoveryMetrics = false
    )
    {
        $currentSnapshot = $this->buildAggregatedLoanSnapshotQuery($selectedPeriod, $filters, 'curr', $useCurrentSnapshot);
        $previousSnapshot = $comparisonPeriod
            ? $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev', $useComparisonSnapshot)
            : $this->buildEmptyAggregatedLoanSnapshotQuery();
        $principalReductionMetric = $useLw325RecoveryMetrics ? 'NULL' : "'principal_reduction'";
        $lunasMetric = $useLw325RecoveryMetrics ? 'NULL' : "'lunas'";

        // Consolidated metrics query: single pass instead of 4 UNIONs
        $joinedMetrics = DB::query()
            ->fromSub($currentSnapshot, 'curr')
            ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                $join->on('curr.account_number', '=', 'prev.account_number');
            })
            ->leftJoinSub($this->buildPhSnapshotQuery($phPeriod), 'ph', function ($join) {
                $join->on('curr.account_number', '=', 'ph.account_number');
            })
            ->selectRaw("
                COALESCE(prev.bucket, 'New Account') as before_bucket,
                CASE
                    WHEN COALESCE(prev.balance_cents, 0) > 0 
                        AND curr.balance_cents > 0 
                        AND prev.balance_cents > curr.balance_cents
                    THEN {$principalReductionMetric}
                    WHEN curr.balance_cents > 0
                    THEN 'suplesi'
                    ELSE NULL
                END as metric_type,
                CASE
                    WHEN COALESCE(prev.balance_cents, 0) > 0 
                        AND curr.balance_cents > 0 
                        AND prev.balance_cents > curr.balance_cents
                    THEN prev.balance_cents - curr.balance_cents
                    WHEN COALESCE(prev.balance_cents, 0) <= 0 AND curr.balance_cents > 0
                    THEN curr.balance_cents
                    WHEN curr.balance_cents > COALESCE(prev.balance_cents, 0)
                    THEN curr.balance_cents - COALESCE(prev.balance_cents, 0)
                    ELSE 0
                END as amount_cents
            ")
            ->whereNotNull('curr.bucket');

        // Exit metrics (PH and Lunas) - separate since it's a different join pattern
        $exitMetrics = DB::query()
            ->fromSub($previousSnapshot, 'prev')
            ->leftJoinSub($currentSnapshot, 'curr', function ($join) {
                $join->on('prev.account_number', '=', 'curr.account_number');
            })
            ->leftJoinSub($this->buildPhSnapshotQuery($phPeriod), 'ph', function ($join) {
                $join->on('prev.account_number', '=', 'ph.account_number');
            })
            ->selectRaw("
                prev.bucket as before_bucket,
                CASE WHEN ph.account_number IS NOT NULL THEN 'ph' ELSE {$lunasMetric} END as metric_type,
                prev.balance_cents as amount_cents
            ")
            ->whereNull('curr.account_number')
            ->whereNotNull('prev.bucket')
            ->whereIn('prev.bucket', self::BEFORE_ROWS);

        // Anonymous metrics
        $anonMetrics = DB::query()
            ->fromSub($this->buildAnonymousCurrentMovementQuery($selectedPeriod, $filters), 'anon_metric')
            ->selectRaw("before_bucket, 'suplesi' as metric_type, amount_cents");

        $movementMetrics = $joinedMetrics
            ->unionAll($exitMetrics)
            ->unionAll($anonMetrics);

        if ($useLw325RecoveryMetrics) {
            $movementMetrics->unionAll(
                $this->buildLw325RecoveryMetricQuery($selectedPeriod, $comparisonPeriod, $filters, $useComparisonSnapshot)
            );
        }

        return DB::query()
            ->fromSub($movementMetrics, 'movement_metrics')
            ->selectRaw('before_bucket, metric_type, SUM(amount_cents) as amount_cents')
            ->whereNotNull('metric_type')
            ->whereIn('before_bucket', self::BEFORE_ROWS)
            ->where('amount_cents', '>', 0)
            ->groupBy('before_bucket', 'metric_type');
    }

    private function buildLw325RecoveryMetricQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useComparisonSnapshot = null
    ) {
        $currentPhPeriod = $this->resolveExactPhPeriod($selectedPeriod);
        $previousPhPeriod = $currentPhPeriod ? $this->resolvePreviousMonthPhPeriod($currentPhPeriod) : null;

        if (!$currentPhPeriod || !$previousPhPeriod || !$comparisonPeriod) {
            return DB::query()
                ->selectRaw("'New Account' as before_bucket, 'principal_reduction' as metric_type, 0 as amount_cents")
                ->whereRaw('1 = 0');
        }

        $previousSnapshot = $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev_recovery', $useComparisonSnapshot);
        $currentAccountKeySql = $this->phAccountKeySql('n');
        $previousAccountKeySql = $this->phAccountKeySql('o');

        $tupokQuery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($previousPhPeriod, $currentPhPeriod, $currentAccountKeySql, $previousAccountKeySql) {
                $join->whereRaw("{$currentAccountKeySql} = {$previousAccountKeySql}")
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->whereRaw('n.periode = ?', [$currentPhPeriod])
                    ->whereRaw('o.periode = ?', [$previousPhPeriod]);
            })
            ->leftJoinSub($previousSnapshot, 'prev_bucket', function ($join) use ($previousAccountKeySql) {
                $join->whereRaw("{$previousAccountKeySql} = prev_bucket.account_number");
            })
            ->selectRaw("COALESCE(prev_bucket.bucket, 'New Account') as before_bucket")
            ->selectRaw("'principal_reduction' as metric_type")
            ->selectRaw("CAST(ROUND((COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) * 100, 0) AS SIGNED) as amount_cents")
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '');

        $this->applyLw325RecoveryFilters($tupokQuery, 'n', $filters);

        $previousSnapshotForLunas = $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev_recovery_lunas', $useComparisonSnapshot);
        $lunasQuery = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($currentPhPeriod, $currentAccountKeySql, $previousAccountKeySql) {
                $join->whereRaw("{$previousAccountKeySql} = {$currentAccountKeySql}")
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->whereRaw('n.periode = ?', [$currentPhPeriod]);
            })
            ->leftJoinSub($previousSnapshotForLunas, 'prev_bucket', function ($join) use ($previousAccountKeySql) {
                $join->whereRaw("{$previousAccountKeySql} = prev_bucket.account_number");
            })
            ->where('o.periode', $previousPhPeriod)
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("COALESCE(prev_bucket.bucket, 'New Account') as before_bucket")
            ->selectRaw("'lunas' as metric_type")
            ->selectRaw("CAST(ROUND(COALESCE(o.pokok, 0) * 100, 0) AS SIGNED) as amount_cents");

        $this->applyLw325RecoveryFilters($lunasQuery, 'o', $filters);

        return $tupokQuery->unionAll($lunasQuery);
    }

    private function buildAggregatedLoanSnapshotQuery(string $period, array $filters, string $alias, ?bool $useSnapshot = null)
    {
        $baseQuery = $this->buildLoanSnapshotQuery($period, $filters, $alias, $useSnapshot);
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
                    ->orWhere("{$alias}.nomor_rekening1", '=', '');
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

    private function buildLoanSnapshotQuery(string $period, array $filters, string $alias, ?bool $useSnapshot = null)
    {
        $shouldUseSnapshot = $useSnapshot ?? $this->shouldUseSnapshot($period, $filters);

        if ($shouldUseSnapshot) {
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

    private function fetchRecoveryReportPeriods(): Collection
    {
        $cacheKey = 'dashboard_pinjaman_recovery_periods:v2:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $periods = collect();

            if (Schema::hasTable('cognos_recovery')) {
                $periods = $periods->merge(
                    DB::table('cognos_recovery')
                        ->whereNotNull('periode')
                        ->distinct()
                        ->pluck('periode')
                );
            }

            if (Schema::hasTable('lw325_ph')) {
                $periods = $periods->merge(
                    DB::table('lw325_ph')
                        ->whereNotNull('periode')
                        ->distinct()
                        ->pluck('periode')
                );
            }

            return $periods
                ->map(function ($periode) {
                    try {
                        return Carbon::parse($periode)->format('Y-m-d');
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter(fn (?string $periode) => $periode !== null && $this->isMonthEndPeriod($periode))
                ->unique()
                ->sortDesc()
                ->values();
        });
    }

    private function resolveSmallArrearsSelectedPeriod($value, ?Collection $availablePeriods = null): ?string
    {
        $availablePeriods ??= $this->fetchPeriods();
        $requestedPeriod = trim((string) (is_array($value) ? ($value[0] ?? '') : $value));

        if ($requestedPeriod !== '') {
            try {
                return Carbon::parse($requestedPeriod)->format('Y-m-d');
            } catch (Throwable) {
                return $availablePeriods->first();
            }
        }

        return $availablePeriods->first();
    }

    private function smallArrearsBranchOptions(): Collection
    {
        return collect([self::SMALL_ARREARS_AREA_ALL, ...self::SMALL_ARREARS_AREA_BRANCHES]);
    }

    private function resolveKolekMismatchBranchSelection($value): array
    {
        $normalized = $this->normalizeFilterValues($value);

        if ($normalized === [] || in_array(self::KOLEK_MISMATCH_AREA_ALL, $normalized, true)) {
            return [
                'selected_values' => [self::KOLEK_MISMATCH_AREA_ALL],
                'effective_branches' => self::SMALL_ARREARS_AREA_BRANCHES,
                'is_area_all' => true,
                'label' => 'Area 6 - All',
            ];
        }

        $selectedBranch = collect($normalized)
            ->first(fn (string $branch) => in_array($branch, self::SMALL_ARREARS_AREA_BRANCHES, true));

        if ($selectedBranch === null) {
            return [
                'selected_values' => [self::KOLEK_MISMATCH_AREA_ALL],
                'effective_branches' => self::SMALL_ARREARS_AREA_BRANCHES,
                'is_area_all' => true,
                'label' => 'Area 6 - All',
            ];
        }

        return [
            'selected_values' => [$selectedBranch],
            'effective_branches' => [$selectedBranch],
            'is_area_all' => false,
            'label' => $selectedBranch,
        ];
    }

    private function resolveSmallArrearsBranchSelection($value): array
    {
        $normalized = $this->normalizeFilterValues($value);

        if ($normalized === [] || in_array(self::SMALL_ARREARS_AREA_ALL, $normalized, true)) {
            return [
                'selected_values' => [self::SMALL_ARREARS_AREA_ALL],
                'effective_branches' => self::SMALL_ARREARS_AREA_BRANCHES,
                'is_area_all' => true,
            ];
        }

        $effectiveBranches = array_values(array_intersect(self::SMALL_ARREARS_AREA_BRANCHES, $normalized));

        if ($effectiveBranches === []) {
            return [
                'selected_values' => [self::SMALL_ARREARS_AREA_ALL],
                'effective_branches' => self::SMALL_ARREARS_AREA_BRANCHES,
                'is_area_all' => true,
            ];
        }

        return [
            'selected_values' => $effectiveBranches,
            'effective_branches' => $effectiveBranches,
            'is_area_all' => false,
        ];
    }

    private function resolveSmallArrearsUnitSelection($value, bool $isAreaAll = false): array
    {
        if ($isAreaAll) {
            return [
                'selected_values' => [],
                'effective_units' => [],
                'is_all_uker' => true,
            ];
        }

        $normalized = $this->normalizeFilterValues($value);

        if ($normalized === [] || in_array(self::SMALL_ARREARS_ALL_UKER, $normalized, true)) {
            return [
                'selected_values' => [self::SMALL_ARREARS_ALL_UKER],
                'effective_units' => [],
                'is_all_uker' => true,
            ];
        }

        return [
            'selected_values' => $normalized,
            'effective_units' => $normalized,
            'is_all_uker' => false,
        ];
    }

    private function fetchSmallArrearsDistinctValues(string $column, ?string $selectedPeriod, array $selectedBranches = []): Collection
    {
        if (!$selectedPeriod) {
            return collect();
        }

        $cacheKey = 'dashboard_pinjaman_tunggakan_kecil_distinct:v1:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'column' => $column,
            'periode' => $selectedPeriod,
            'branches' => array_values($selectedBranches),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($column, $selectedPeriod, $selectedBranches) {
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $selectedPeriod)
                ->whereNotNull($column)
                ->where($column, '<>', '')
                ->select($column)
                ->distinct()
                ->orderBy($column);

            if ($column === 'unit1' && $selectedBranches !== []) {
                $query->whereIn('cabang1', $selectedBranches);
            }

            return $query->pluck($column)
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();
        });
    }

    private function fetchSmallArrearsUnitLabelsByBranches(array $selectedBranches): array
    {
        if ($selectedBranches === []) {
            return [];
        }

        $cacheKey = 'dashboard_pinjaman_tunggakan_kecil_units_by_branch:v1:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'branches' => array_values($selectedBranches),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($selectedBranches) {
            return DB::table('daily_loan_dinamis')
                ->whereIn('cabang1', $selectedBranches)
                ->whereNotNull('unit1')
                ->where('unit1', '<>', '')
                ->distinct()
                ->orderBy('unit1')
                ->pluck('unit1')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values()
                ->all();
        });
    }

    private function buildSmallArrearsPayload(string $selectedPeriod, array $selectedBranches, array $selectedUnits, bool $isAreaAll = false): array
    {
        $isAllUker = !$isAreaAll && $selectedUnits === [];
        $groupColumn = $isAreaAll ? 'cabang1' : 'unit1';
        $groupLabel = $isAreaAll ? 'BRANCH OFFICE' : 'UKER';
        $comparisonPeriods = $this->resolveSmallArrearsComparisonPeriods($selectedPeriod);
        $accountColumn = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? 'nomor_rekening1'
            : $this->resolveIdentityColumn('daily_loan_dinamis');
        $penaltyColumn = Schema::hasColumn('daily_loan_dinamis', 'tunggakan_penalti')
            ? 'tunggakan_penalti'
            : (Schema::hasColumn('daily_loan_dinamis', 'tunggakan_pinalti') ? 'tunggakan_pinalti' : null);
        $totalExpression = 'COALESCE(tunggakan_pokok, 0) + COALESCE(tunggakan_bunga, 0)';
        if ($penaltyColumn !== null) {
            $totalExpression .= " + COALESCE({$penaltyColumn}, 0)";
        }
        $qualifiedAccountExpression = "CASE WHEN ({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000 THEN {$accountColumn} END";
        $qualifiedAmountExpression = "CASE WHEN ({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000 THEN ({$totalExpression}) ELSE 0 END";
        $currentPeriodHasData = DB::table('daily_loan_dinamis')
            ->where('periode', $selectedPeriod)
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('unit1', $selectedUnits))
            ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
            ->exists();

        $periodsToQuery = collect([
            $selectedPeriod,
            $comparisonPeriods['mtd'],
            $comparisonPeriods['ytd'],
        ])->filter()->unique()->values()->all();

        $rows = DB::table('daily_loan_dinamis')
            ->selectRaw("{$groupColumn} as grouping_label")
            ->selectRaw("COUNT(DISTINCT CASE WHEN periode = ? THEN {$qualifiedAccountExpression} END) as current_count", [$selectedPeriod])
            ->selectRaw("COUNT(DISTINCT CASE WHEN periode = ? THEN {$qualifiedAccountExpression} END) as ytd_base", [$comparisonPeriods['ytd']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN periode = ? THEN {$qualifiedAccountExpression} END) as mtd_base", [$comparisonPeriods['mtd']])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN {$qualifiedAmountExpression} ELSE 0 END) as current_total_tunggakan", [$selectedPeriod])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN {$qualifiedAmountExpression} ELSE 0 END) as ytd_total_tunggakan", [$comparisonPeriods['ytd']])
            ->selectRaw("SUM(CASE WHEN periode = ? THEN {$qualifiedAmountExpression} ELSE 0 END) as mtd_total_tunggakan", [$comparisonPeriods['mtd']])
            ->whereIn('periode', $periodsToQuery)
            ->whereNotNull($groupColumn)
            ->where($groupColumn, '<>', '')
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('unit1', $selectedUnits))
            ->groupBy('grouping_label')
            ->orderBy('grouping_label')
            ->get();

        $resultRows = [];
        $totals = [
            'current' => 0,
            'ytd' => 0,
            'mtd' => 0,
            'current_tunggakan' => 0.0,
            'ytd_tunggakan' => 0.0,
            'mtd_tunggakan' => 0.0,
            'total_tunggakan' => 0.0,
        ];

        foreach ($rows as $row) {
            $label = trim((string) ($row->grouping_label ?? ''));
            if ($label === '') {
                continue;
            }

            $current = (int) ($row->current_count ?? 0);
            $ytdBase = (int) ($row->ytd_base ?? 0);
            $mtdBase = (int) ($row->mtd_base ?? 0);
            $currentTotalTunggakan = (float) ($row->current_total_tunggakan ?? 0);
            $ytdTotalTunggakan = (float) ($row->ytd_total_tunggakan ?? 0);
            $mtdTotalTunggakan = (float) ($row->mtd_total_tunggakan ?? 0);

            $mappedRow = [
                'label' => $label,
                'ytd' => $currentPeriodHasData ? $ytdBase : 0,
                'mtd' => $currentPeriodHasData ? $mtdBase : 0,
                'current' => $currentPeriodHasData ? $current : 0,
                'ytd_tunggakan' => $currentPeriodHasData ? $ytdTotalTunggakan : 0.0,
                'mtd_tunggakan' => $currentPeriodHasData ? $mtdTotalTunggakan : 0.0,
                'current_tunggakan' => $currentPeriodHasData ? $currentTotalTunggakan : 0.0,
                'total_tunggakan' => $currentPeriodHasData ? $currentTotalTunggakan : 0.0,
            ];

            $resultRows[] = $mappedRow;
            $totals['current'] += $mappedRow['current'];
            $totals['ytd'] += $mappedRow['ytd'];
            $totals['mtd'] += $mappedRow['mtd'];
            $totals['current_tunggakan'] += $mappedRow['current_tunggakan'];
            $totals['ytd_tunggakan'] += $mappedRow['ytd_tunggakan'];
            $totals['mtd_tunggakan'] += $mappedRow['mtd_tunggakan'];
            $totals['total_tunggakan'] += $mappedRow['total_tunggakan'];
        }

        $fallbackLabels = $isAllUker
            ? $this->fetchSmallArrearsUnitLabelsByBranches($selectedBranches)
            : ($selectedBranches !== [] ? $selectedBranches : []);

        if ($groupColumn === 'cabang1') {
            $resultRows = $this->completeSmallArrearsBranchRows($resultRows, $fallbackLabels);
        } elseif ($resultRows === []) {
            foreach ($fallbackLabels as $label) {
                $resultRows[] = [
                    'label' => $label,
                    'ytd' => 0,
                    'mtd' => 0,
                    'current' => 0,
                    'ytd_tunggakan' => 0.0,
                    'mtd_tunggakan' => 0.0,
                    'current_tunggakan' => 0.0,
                    'total_tunggakan' => 0.0,
                ];
            }
        }

        return [
            'group_label' => $groupLabel,
            'rows' => $resultRows,
            'total' => $totals,
            'labels' => [
                'current' => $selectedPeriod,
                'ytd' => $comparisonPeriods['ytd'],
                'mtd' => $comparisonPeriods['mtd'],
            ],
        ];
    }

    private function buildSmallArrearsExportQuery(string $selectedPeriod, array $selectedBranches, array $selectedUnits): Builder
    {
        $penaltyColumn = Schema::hasColumn('daily_loan_dinamis', 'tunggakan_penalti')
            ? 'tunggakan_penalti'
            : (Schema::hasColumn('daily_loan_dinamis', 'tunggakan_pinalti') ? 'tunggakan_pinalti' : null);
        $totalExpression = 'COALESCE(tunggakan_pokok, 0) + COALESCE(tunggakan_bunga, 0)';
        if ($penaltyColumn !== null) {
            $totalExpression .= " + COALESCE({$penaltyColumn}, 0)";
        }

        $orderingColumn = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? 'nomor_rekening1'
            : $this->resolveIdentityColumn('daily_loan_dinamis');

        return DB::table('daily_loan_dinamis')
            ->select('daily_loan_dinamis.*')
            ->selectRaw("({$totalExpression}) as total_tunggakan_terhitung")
            ->where('periode', $selectedPeriod)
            ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('unit1', $selectedUnits))
            ->orderBy('cabang1')
            ->orderBy('unit1')
            ->orderBy($orderingColumn);
    }

    private function collectSmallArrearsExportColumns(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $excluded = ['created_at', 'updated_at', 'uniqueid_namareport'];
        $cached = array_values(array_filter(
            Schema::getColumnListing('daily_loan_dinamis'),
            fn (string $col) => !in_array($col, $excluded, true)
        ));
        $cached[] = 'total_tunggakan_terhitung';

        return $cached;
    }

    private function completeSmallArrearsBranchRows(array $rows, array $branches): array
    {
        $rowsByLabel = collect($rows)->keyBy('label');
        $orderedRows = [];

        foreach ($branches as $branch) {
            $orderedRows[] = $rowsByLabel->get($branch, [
                'label' => $branch,
                'ytd' => 0,
                'mtd' => 0,
                'current' => 0,
                'ytd_tunggakan' => 0.0,
                'mtd_tunggakan' => 0.0,
                'current_tunggakan' => 0.0,
                'total_tunggakan' => 0.0,
            ]);
        }

        return $orderedRows;
    }

    private function resolveSmallArrearsComparisonPeriods(string $selectedPeriod): array
    {
        $cacheKey = 'dashboard_pinjaman_tunggakan_kecil_compare:v1:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($selectedPeriod) {
            $currentDate = Carbon::parse($selectedPeriod);

            return [
                'ytd' => DB::table('daily_loan_dinamis')
                    ->where('periode', '<=', $currentDate->copy()->subYearNoOverflow()->endOfYear()->toDateString())
                    ->max('periode'),
                'mtd' => DB::table('daily_loan_dinamis')
                    ->where('periode', '<=', $currentDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString())
                    ->max('periode'),
            ];
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

    private function resolveRecoveryReportPeriod(?string $requestedPeriod): ?string
    {
        $periods = $this->fetchRecoveryReportPeriods();

        if ($periods->isEmpty()) {
            return $this->resolveEffectivePeriod($requestedPeriod);
        }

        if ($requestedPeriod) {
            try {
                $requested = Carbon::parse($requestedPeriod)->format('Y-m-d');

                return $periods
                    ->first(fn (string $period) => $period <= $requested)
                    ?? $periods->last();
            } catch (Throwable) {
                return $periods->first();
            }
        }

        return $periods->first();
    }

    private function isMonthEndPeriod(string $period): bool
    {
        try {
            $date = Carbon::parse($period);

            return $date->toDateString() === $date->copy()->endOfMonth()->toDateString();
        } catch (Throwable) {
            return false;
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

    private function shouldUseLw325RecoveryMetrics(?string $selectedPeriod): bool
    {
        if (!$selectedPeriod || !Schema::hasTable('lw325_ph')) {
            return false;
        }

        try {
            $normalizedPeriod = Carbon::parse($selectedPeriod)->format('Y-m-d');

            return DB::table('lw325_ph')->where('periode', $normalizedPeriod)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function resolveExactPhPeriod(?string $selectedPeriod): ?string
    {
        if (!$selectedPeriod) {
            return null;
        }

        try {
            $normalizedPeriod = Carbon::parse($selectedPeriod)->format('Y-m-d');

            return DB::table('lw325_ph')->where('periode', $normalizedPeriod)->exists()
                ? $normalizedPeriod
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolvePreviousMonthPhPeriod(string $period): ?string
    {
        try {
            $current = Carbon::parse($period);
            $target = $current->copy()->subMonthNoOverflow();
            $monthStart = $target->copy()->startOfMonth()->toDateString();
            $targetDate = $target->toDateString();
            $monthEnd = $target->copy()->endOfMonth()->toDateString();

            return DB::table('lw325_ph')
                ->whereBetween('periode', [$monthStart, $targetDate])
                ->max('periode')
                ?: DB::table('lw325_ph')
                    ->whereBetween('periode', [$monthStart, $monthEnd])
                    ->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function rememberPayload(string $cacheKey, $ttl, callable $callback, bool $forceRefresh = false, ?callable $fallback = null)
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

            if ($fallback) {
                return $fallback();
            }

            return $callback();
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
                        ->onQueue((string) config('queue.report_queue', 'default'));
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

    private function buildKolekMismatchSummary(string $selectedPeriod, array $selectedBranches, bool $groupByBranch = false): array
    {
        $startedAt = microtime(true);
        $scannedRows = 0;
        $matchedRows = 0;
        $mismatchRows = 0;
        $totalOutstandingBalance = 0.0;
        $unitSummaries = [];

        foreach ($this->buildKolekMismatchBaseQuery($selectedPeriod, $selectedBranches)->cursor() as $row) {
            $scannedRows++;

            $actualKolek = $this->normalizeKolekValue($row->kolek ?? null);
            $expectedKolek = $this->expectedKolekFromUmurTunggakan($row->umur_tunggakan ?? null);

            if ($actualKolek === null || $expectedKolek === null) {
                continue;
            }

            if ($actualKolek === $expectedKolek) {
                $matchedRows++;
                continue;
            }

            $mismatchRows++;
            $branch = trim((string) ($row->cabang1 ?? 'Tanpa Cabang'));
            $branch = $branch !== '' ? $branch : 'Tanpa Cabang';
            $unit = trim((string) ($row->unit1 ?? 'Tanpa Unit'));
            $unit = $unit !== '' ? $unit : 'Tanpa Unit';
            $summaryKey = $groupByBranch ? $branch : $branch . "\n" . $unit;
            $keterangan = $this->determineKeterangan($row);

            if (!isset($unitSummaries[$summaryKey])) {
                $unitSummaries[$summaryKey] = [
                    'branch' => $branch,
                    'unit' => $groupByBranch ? '' : $unit,
                    'label' => $groupByBranch ? $branch : $unit,
                    'is_branch_summary' => $groupByBranch,
                    'mismatch_count' => 0,
                    'memburuk_count' => 0,
                    'memburuk_os' => 0.0,
                    'belum_waktunya_penyesuaian_count' => 0,
                    'belum_waktunya_penyesuaian_os' => 0.0,
                    'kolek_membaik_count' => 0,
                    'kolek_membaik_os' => 0.0,
                    'outstanding_balance' => 0.0,
                ];
            }

            $outstandingBalance = $this->normalizeAmountValue($row->baki_debet1 ?? 0);
            $unitSummaries[$summaryKey]['mismatch_count']++;
            $unitSummaries[$summaryKey]['outstanding_balance'] += $outstandingBalance;
            $totalOutstandingBalance += $outstandingBalance;

            if ($keterangan === 'memburuk') {
                $unitSummaries[$summaryKey]['memburuk_count']++;
                $unitSummaries[$summaryKey]['memburuk_os'] += $outstandingBalance;
            } elseif ($keterangan === 'belum waktunya penyesuaian') {
                $unitSummaries[$summaryKey]['belum_waktunya_penyesuaian_count']++;
                $unitSummaries[$summaryKey]['belum_waktunya_penyesuaian_os'] += $outstandingBalance;
            } elseif ($keterangan === 'kolek membaik') {
                $unitSummaries[$summaryKey]['kolek_membaik_count']++;
                $unitSummaries[$summaryKey]['kolek_membaik_os'] += $outstandingBalance;
            }
        }

        if ($groupByBranch) {
            foreach ($selectedBranches as $branch) {
                if (isset($unitSummaries[$branch])) {
                    continue;
                }

                $unitSummaries[$branch] = [
                    'branch' => $branch,
                    'unit' => '',
                    'label' => $branch,
                    'is_branch_summary' => true,
                    'mismatch_count' => 0,
                    'memburuk_count' => 0,
                    'memburuk_os' => 0.0,
                    'belum_waktunya_penyesuaian_count' => 0,
                    'belum_waktunya_penyesuaian_os' => 0.0,
                    'kolek_membaik_count' => 0,
                    'kolek_membaik_os' => 0.0,
                    'outstanding_balance' => 0.0,
                ];
            }
        }

        $summaryRows = collect($unitSummaries)
            ->map(fn (array $summary) => [
                'branch' => $summary['branch'],
                'unit' => $summary['unit'],
                'label' => $summary['label'],
                'is_branch_summary' => $summary['is_branch_summary'],
                'unit_sort_group' => $this->unitLabelSortGroup($summary['unit']),
                'mismatch_count' => $summary['mismatch_count'],
                'memburuk_count' => $summary['memburuk_count'],
                'memburuk_os' => $summary['memburuk_os'],
                'belum_waktunya_penyesuaian_count' => $summary['belum_waktunya_penyesuaian_count'],
                'belum_waktunya_penyesuaian_os' => $summary['belum_waktunya_penyesuaian_os'],
                'kolek_membaik_count' => $summary['kolek_membaik_count'],
                'kolek_membaik_os' => $summary['kolek_membaik_os'],
                'outstanding_balance' => $summary['outstanding_balance'],
            ])
            ->sortBy([
                ['branch', 'asc'],
                ['unit_sort_group', 'asc'],
                ['mismatch_count', 'desc'],
                ['unit', 'asc'],
            ])
            ->map(function (array $summary) {
                unset($summary['unit_sort_group']);

                return $summary;
            })
            ->values()
            ->all();

        Log::info('Dashboard pinjaman mismatch scan completed.', [
            'selected_period' => $selectedPeriod,
            'selected_branches' => $selectedBranches,
            'scanned_rows' => $scannedRows,
            'matched_rows' => $matchedRows,
            'mismatch_rows' => $mismatchRows,
            'units_with_mismatch' => collect($summaryRows)->where('mismatch_count', '>', 0)->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'rule' => self::KOLEK_MISMATCH_RULE_LABEL,
        ]);

        return [
            'summary_rows' => $summaryRows,
            'audit' => [
                'rule' => self::KOLEK_MISMATCH_RULE_LABEL,
                'scanned_rows' => $scannedRows,
                'matched_rows' => $matchedRows,
                'mismatch_rows' => $mismatchRows,
                'units_with_mismatch' => collect($summaryRows)->where('mismatch_count', '>', 0)->count(),
                'total_outstanding_balance' => $totalOutstandingBalance,
            ],
        ];
    }

    private function fetchKolekMismatchRows(string $selectedPeriod, string $selectedBranch, ?string $selectedUnit = null): array
    {
        $rows = [];
        $excluded = ['created_at', 'updated_at'];

        foreach ($this->buildKolekMismatchBaseQuery($selectedPeriod, [$selectedBranch], $selectedUnit)->cursor() as $row) {
            $actualKolek = $this->normalizeKolekValue($row->kolek ?? null);
            $expectedKolek = $this->expectedKolekFromUmurTunggakan($row->umur_tunggakan ?? null);

            if ($actualKolek === null || $expectedKolek === null || $actualKolek === $expectedKolek) {
                continue;
            }

            // Use array_diff_key to avoid creating a Collection per row (P4)
            $rowData = array_diff_key((array) $row, array_flip($excluded));
            $rowData['kolek_seharusnya'] = $expectedKolek;
            $rowData['keterangan'] = $this->determineKeterangan($row);

            $rows[] = $rowData;
        }

        return $rows;
    }

    private function buildKolekMismatchBaseQuery(string $selectedPeriod, array $selectedBranches, ?string $selectedUnit = null): Builder
    {
        // Cache schema check per request lifecycle to avoid repeated inspections (B4, B5, P1)
        static $orderingColumn = null;
        if ($orderingColumn === null) {
            $orderingColumn = Schema::hasColumn('daily_loan_dinamis', 'norek')
                ? 'norek'
                : $this->resolveIdentityColumn('daily_loan_dinamis');
        }

        $query = DB::table('daily_loan_dinamis')
            ->where('periode', $selectedPeriod)
            ->whereIn('cabang1', $selectedBranches)
            ->orderBy('unit1')
            ->orderBy($orderingColumn);

        if ($selectedUnit !== null && $selectedUnit !== '') {
            $query->where('unit1', $selectedUnit);
        }

        return $query;
    }

    private function collectKolekExportColumns(): array
    {
        // Cache column listing for the duration of the request (P2)
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $excluded = ['created_at', 'updated_at', 'uniqueid_namareport'];
        $cached = array_values(array_filter(
            Schema::getColumnListing('daily_loan_dinamis'),
            fn (string $col) => !in_array($col, $excluded, true)
        ));
        $cached[] = 'kolek_seharusnya';
        $cached[] = 'keterangan';

        return $cached;
    }

    private function normalizeKolekValue($value): ?int
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        // Match only a standalone single digit 1–5 to avoid false positives (B6)
        // Examples: "1", "KOL 3", "Kolek-2" should work; "15" or "51" should not
        if (preg_match('/^\D*([1-5])\D*$/', $normalized, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function expectedKolekFromUmurTunggakan($value): ?int
    {
        $umurTunggakan = $this->normalizeUmurTunggakanValue($value);

        if ($umurTunggakan === null) {
            return null;
        }

        return match (true) {
            $umurTunggakan <= 0 => 1,
            $umurTunggakan <= 90 => 2,
            $umurTunggakan <= 120 => 3,
            $umurTunggakan <= 180 => 4,
            default => 5,
        };
    }

    private function determineKeterangan($row): string
    {
        $actualKolek = $this->normalizeKolekValue($row->kolek ?? null);
        $expectedKolek = $this->expectedKolekFromUmurTunggakan($row->umur_tunggakan ?? null);

        if ($actualKolek !== null && $expectedKolek !== null && $actualKolek === $expectedKolek) {
            return 'tetap';
        }

        $periode = $row->periode ?? null;
        $tglAkad = $row->tgl_akad_restruk ?? null;

        $hasNoTunggakan = $this->isValueEmptyOrZero($row->tunggakan_pokok ?? null)
            && $this->isValueEmptyOrZero($row->tunggakan_bunga ?? null)
            && $this->isValueEmptyOrZero($row->tunggakan_penalti ?? null);

        $isNplMethodN = strtoupper(trim((string) ($row->npl_method ?? ''))) === 'N';

        if ($hasNoTunggakan && $isNplMethodN && $periode && $tglAkad) {
            try {
                // Hitung selisih hari: periode - tgl_akad_restruk (B1 fix: correct direction)
                // Positif artinya periode lebih akhir dari tgl_akad (normal case)
                $periodeDate = Carbon::parse($periode)->startOfDay();
                $akadDate   = Carbon::parse($tglAkad)->startOfDay();
                $days = $akadDate->diffInDays($periodeDate, false);

                return $days > 90 ? 'kolek membaik' : 'belum waktunya penyesuaian';
            } catch (\Throwable) {
                // Fallback to memburuk on parse error
            }
        }

        if ($actualKolek !== null && $expectedKolek !== null && $actualKolek > $expectedKolek) {
            return 'kolek membaik';
        }

        return 'memburuk';
    }

    /**
     * Returns true when a field value represents zero / empty / null. (C8 refactor)
     */
    private function isValueEmptyOrZero($val): bool
    {
        if ($val === null || $val === '') {
            return true;
        }

        if (is_numeric($val) && (float) $val == 0) {
            return true;
        }

        $cleared = str_replace([',', '.'], '', trim((string) $val));

        return is_numeric($cleared) && (float) $cleared == 0;
    }

    private function normalizeUmurTunggakanValue($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/-?\d+/', str_replace(',', '', $normalized), $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function normalizeAmountValue($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return 0.0;
        }

        $normalized = str_replace(' ', '', $normalized);
        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif ($hasComma) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function unitLabelSortGroup(string $unit): int
    {
        $normalized = trim($unit);

        if (preg_match('/^KC\b/i', $normalized) === 1) {
            return 0;
        }

        if (preg_match('/^KCP\b/i', $normalized) === 1) {
            return 1;
        }

        return 2;
    }

    private function normalizeReportMode(?string $value): string
    {
        return in_array($value, ['matrix', 'mismatch'], true) ? $value : 'matrix';
    }

    private function sanitizeExportToken(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]+/', '-', trim($value)) ?? 'export';
        $sanitized = trim($sanitized, '-');

        return $sanitized !== '' ? $sanitized : 'export';
    }

    protected function reportCacheVersion(): int
    {
        return (int) Cache::get('report_cache_version:global', 1);
    }

    private function resolveIdentityColumn(string $table): string
    {
        // Priority-ordered list of known identity columns
        $candidates = [
            'uniqueid_dps',
            'uniqueid_rcds',
            'uniqueid_rds',
            'uniqueid_namareport',
            'uniqueid_SMPN',
            'id',
        ];

        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        $columns = Schema::getColumnListing($table);

        return $columns[0] ?? 'id';
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

    private function applyLw325RecoveryFilters(Builder $query, string $alias, array $filters): void
    {
        $this->applyFilterConstraint($query, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($query, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($query, "{$alias}.kanca", $filters['cabang']);
        $this->applyFilterConstraint($query, "{$alias}.unit", $filters['unit']);
    }

    private function phAccountKeySql(string $alias): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LTRIM(TRIM(COALESCE({$alias}.acctno, '')), '0')";
        }

        return "TRIM(LEADING '0' FROM TRIM(COALESCE({$alias}.acctno, '')))";
    }

    private function applyTrimmedInConstraint(Builder $query, string $column, array $values): void
    {
        $normalized = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $values
        ), fn (string $value) => $value !== ''));

        if ($normalized === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($normalized), '?'));
        $query->whereIn($column, $normalized);
    }


    private function centsToAmount(int $cents): float
    {
        return $cents / 100;
    }

}
