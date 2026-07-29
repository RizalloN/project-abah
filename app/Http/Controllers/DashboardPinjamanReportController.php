<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureDashboardSnapshotJob;
use App\Support\DashboardPinjamanChartPeriodikService;
use App\Support\ReportIndexHintResolver;
use App\Support\ReportCacheVersion;
use App\Support\LoanQualityBucketMapper;
use App\Support\DashboardPinjamanKreditService;
use App\Support\UserBranchScope;
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
    private const KREDIT_AREA_6_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];
    private const LOAN_REKENING_INDEX = 'idx_loan_periode_rek';
    private const LOAN_FILTER_INDEX = 'idx_loan_periode_segmen';
    private const LOAN_CABANG_UNIT_INDEX = 'idx_daily_loan_report_filter_covering';
    private const PH_LOOKUP_INDEX = 'idx_lw325ph_period_acct_kanca_unit';
    private const RAW_QUALITY_BUCKETS = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M', 'NPL', 'PH', 'Pay'];
    private const PH_RECOVERY_MIN_ACCOUNT_DISTINCT_RATIO = 0.95;

    private const QUALITY_BUCKETS = ['L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];
    private const HEALTHY_BUCKETS = ['L', 'LR'];
    private const SMALL_ARREARS_AREA_ALL = 'AREA_6_ALL';
    private const SMALL_ARREARS_AREA_BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private const SMALL_ARREARS_ALL_UKER = 'ALL_UKER';
    private const UG_NPL_ALL_SEGMENTS = 'ALL_SEGMEN';
    private const KOLEK_MISMATCH_AREA_ALL = 'AREA_6_ALL';

    private const BEFORE_ROWS = ['New Account', 'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M'];

    private const OUTPUT_COLUMNS = ['Turunan Pokok', 'Suplesi', 'PH', 'Lunas'];

    private array $lw325RecoveryPeriodQuality = [];
    private const KOLEK_MISMATCH_RULE_LABEL = 'kolek_vs_umur_tunggakan_v3';
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
    private const DAILY_LOAN_HELPER_OUTPUT_COLUMNS = [
        'segmen_kinerja',
        'produk_kinerja',
        'cabang_normalized',
        'unit_normalized',
        'branch_normalized',
        'rm_normalized',
        'pn_pemutus_normalized',
        'cifno_clean',
        'shadow_built_at',
        'cabang_normalized_gc',
        'unit_normalized_gc',
        'branch_normalized_gc',
        'rm_normalized_gc',
        'pn_pemutus_normalized_gc',
        'cifno_clean_gc',
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
        $selectedKanca = $this->resolveKreditBranch($request->input('kanca'));

        return view('report.dashboard-pinjaman.kredit', [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'selectedCategory' => $selectedCategory,
            'selectedKanca' => $selectedKanca,
            'kancaOptions' => $this->kreditBranchOptions(),
            'categories' => ['SME', 'Consumer', 'Mikro'],
        ]);
    }

    public function kreditData(Request $request)
    {
        $this->releaseSessionLockIfNeeded();

        $selectedPeriod = $this->resolveKreditEffectivePeriod($request->input('periode'));
        $selectedCategory = $request->input('kategori', 'SME');
        $selectedKanca = $this->resolveKreditBranch($request->input('kanca'));
        $forceRefresh = $request->boolean('refresh');

        if (!$selectedPeriod) {
            return response()->json([
                'selected_period' => null,
                'category' => $selectedCategory,
                'kanca' => $selectedKanca,
                'os' => [],
                'sml' => [],
                'npl' => [],
            ]);
        }

        $kreditService = app(DashboardPinjamanKreditService::class);
        $periodReferences = $kreditService->calculatePeriodReferences($selectedPeriod);

        $cacheKey = 'dashboard_pinjaman_kredit_unified:v18-strict-uker-kind-rka-cache-refresh:' . md5(json_encode([
            'cache_version' => $this->kreditCacheVersion(),
            'snapshot_signature' => $this->kreditSnapshotSignature($periodReferences, $selectedKanca),
            'periode' => $selectedPeriod,
            'kategori' => $selectedCategory,
            'kanca' => $selectedKanca,
        ]));

        $data = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(10),
            fn () => $kreditService->getUnifiedSegmentData(
                $selectedPeriod,
                $selectedCategory,
                $selectedKanca === 'all' ? null : $selectedKanca
            ),
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
            'kanca' => $selectedKanca,
            'kanca_label' => $selectedKanca === 'all' ? 'Area 6' : $selectedKanca,
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

    public function sixMonthArrearsIndex(Request $request)
    {
        $availablePeriods = $this->fetchSixMonthArrearsPeriods();
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

        $months = (int) $request->input('range_months', 6);
        if (!in_array($months, [4, 6], true)) {
            $months = 6;
        }

        return view('report.dashboard-pinjaman.realisasi-6-bulan-menunggak', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedBranches' => $selectedBranches,
            'effectiveBranches' => $effectiveBranches,
            'isAreaAllSelected' => $branchSelection['is_area_all'],
            'selectedUnits' => $selectedUnits,
            'branchOptions' => $branchOptions,
            'unitOptions' => $unitOptions,
            'isAllUkerSelected' => in_array(self::SMALL_ARREARS_ALL_UKER, $selectedUnits, true) || (!$branchSelection['is_area_all'] && $selectedUnits === []),
            'targetMonthLabel' => $selectedPeriod ? $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months) : '-',
            'rangeMonths' => $months,
        ]);
    }

    public function sixMonthArrearsFilters(Request $request)
    {
        @set_time_limit(30);
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchSixMonthArrearsPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $effectiveBranches = $branchSelection['effective_branches'];

        $unitOptions = $branchSelection['is_area_all']
            ? collect()
            : collect([self::SMALL_ARREARS_ALL_UKER])->merge($this->fetchSmallArrearsDistinctValues('unit1', $selectedPeriod, $effectiveBranches))->values();

        $months = (int) $request->input('range_months', 6);
        if (!in_array($months, [4, 6], true)) {
            $months = 6;
        }

        return response()->json([
            'available_periods' => $availablePeriods->all(),
            'selected_period' => $selectedPeriod,
            'target_month_label' => $selectedPeriod ? $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months) : '-',
            'branch_options' => $this->smallArrearsBranchOptions()->all(),
            'unit_options' => $unitOptions->all(),
            'is_area_all' => $branchSelection['is_area_all'],
            'range_months' => $months,
        ]);
    }

    public function sixMonthArrearsData(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchSixMonthArrearsPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $effectiveUnits = $unitSelection['effective_units'];
        $forceRefresh = $request->boolean('refresh');

        $months = (int) $request->input('range_months', 6);
        if (!in_array($months, [4, 6], true)) {
            $months = 6;
        }

        if (!$selectedPeriod) {
            return response()->json($this->emptySixMonthArrearsPayload(null, [], [], true, $months));
        }

        $cacheKey = 'dashboard_pinjaman_realisasi_6_bulan_menunggak:v4:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'cabang1' => $branchSelection['selected_values'],
            'unit1' => $unitSelection['selected_values'],
            'range_months' => $months,
        ]));

        $payload = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(3),
            fn () => $this->buildSixMonthArrearsPayload(
                $selectedPeriod,
                $branchSelection['effective_branches'],
                $effectiveUnits,
                $branchSelection['is_area_all'],
                $months
            ),
            $forceRefresh,
            fn () => $this->emptySixMonthArrearsPayload($selectedPeriod, $branchSelection['effective_branches'], $effectiveUnits, $branchSelection['is_area_all'], $months)
        );

        return response()->json($payload);
    }

    public function ugNplIndex(Request $request)
    {
        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $unitOptions = $branchSelection['is_area_all']
            ? collect()
            : collect([self::SMALL_ARREARS_ALL_UKER])->merge($this->fetchSmallArrearsDistinctValues('unit1', $selectedPeriod, $branchSelection['effective_branches']))->values();
        $segmentSelection = $this->resolveUgNplSegmentSelection($request->input('segmen_dashboard'));
        $segmentOptions = collect([self::UG_NPL_ALL_SEGMENTS])
            ->merge($this->fetchSmallArrearsDistinctValues('segmen_dashboard', $selectedPeriod, $branchSelection['effective_branches']))
            ->unique()
            ->values();

        return view('report.dashboard-pinjaman.analisa-ug-npl', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedBranches' => $branchSelection['selected_values'],
            'effectiveBranches' => $branchSelection['effective_branches'],
            'isAreaAllSelected' => $branchSelection['is_area_all'],
            'selectedUnits' => $unitSelection['selected_values'],
            'branchOptions' => $this->smallArrearsBranchOptions(),
            'unitOptions' => $unitOptions,
            'selectedSegments' => $segmentSelection['selected_values'],
            'segmentOptions' => $segmentOptions,
            'selectedAction' => $this->resolveUgNplAction($request->input('action')),
            'selectedHorizonDays' => $this->resolveUgNplHorizonDays($request->input('horizon_days')),
            'actionOptions' => $this->ugNplActionOptions(),
            'horizonOptions' => $this->ugNplHorizonOptions(),
        ]);
    }

    public function ugNplData(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);
        $segmentSelection = $this->resolveUgNplSegmentSelection($request->input('segmen_dashboard'));
        $selectedAction = $this->resolveUgNplAction($request->input('action'));
        $horizonDays = $this->resolveUgNplHorizonDays($request->input('horizon_days'));
        $forceRefresh = $request->boolean('refresh');

        if (!$selectedPeriod) {
            return response()->json($this->emptyUgNplPayload(null, $selectedAction, $horizonDays, $segmentSelection['effective_segments']));
        }

        $cacheKey = 'dashboard_pinjaman_ug_npl:v4-dl-sml1-segment:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'cabang1' => $branchSelection['selected_values'],
            'unit1' => $unitSelection['selected_values'],
            'segmen_dashboard' => $segmentSelection['selected_values'],
            'action' => $selectedAction,
            'horizon_days' => $horizonDays,
        ]));

        $payload = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(5),
            fn () => $this->buildUgNplPayload(
                $selectedPeriod,
                $branchSelection['effective_branches'],
                $unitSelection['effective_units'],
                $branchSelection['is_area_all'],
                $selectedAction,
                $horizonDays,
                $segmentSelection['effective_segments']
            ),
            $forceRefresh,
            fn () => $this->emptyUgNplPayload($selectedPeriod, $selectedAction, $horizonDays, $segmentSelection['effective_segments'])
        );

        return response()->json($payload);
    }

    public function sixMonthArrearsExport(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();
        $this->releaseSessionLockIfNeeded();

        $availablePeriods = $this->fetchSixMonthArrearsPeriods();
        $selectedPeriod = $this->resolveSmallArrearsSelectedPeriod($request->input('periode'), $availablePeriods);
        $branchSelection = $this->resolveSmallArrearsBranchSelection($request->input('cabang1'));
        $unitSelection = $this->resolveSmallArrearsUnitSelection($request->input('unit1'), $branchSelection['is_area_all']);

        abort_if(!$selectedPeriod, 422, 'Periode wajib dipilih.');

        $months = (int) $request->input('range_months', 6);
        if (!in_array($months, [4, 6], true)) {
            $months = 6;
        }

        $rows = $this->fetchSixMonthArrearsRows($selectedPeriod, $branchSelection['effective_branches'], $unitSelection['effective_units'], $months);
        $exportColumns = $this->collectSixMonthArrearsExportColumns();
        $filename = sprintf(
            'realisasi-%d-bulan-menunggak_%s_%s_%s.xlsx',
            $months,
            str_replace('-', '', $selectedPeriod),
            $branchSelection['is_area_all'] ? 'area-6-all' : $this->sanitizeExportToken(implode('-', $branchSelection['selected_values'])),
            $unitSelection['is_all_uker'] ? 'all-uker' : $this->sanitizeExportToken(implode('-', $unitSelection['selected_values']))
        );

        return response()->streamDownload(function () use ($rows, $exportColumns, $months) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Realisasi ' . $months . ' Bulan');

            foreach ($exportColumns as $index => $column) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '1', $column);
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
        $cacheKey = 'dashboard_pinjaman_kredit_periods:v3:' . $this->kreditCacheVersion();

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

    private function kreditCacheVersion(): int
    {
        return ReportCacheVersion::composite(['harian', 'pinjaman', 'simpanan']);
    }

    private function kreditSnapshotSignature(array $periods, string $selectedKanca): string
    {
        if (!Schema::hasTable('dashboard_harian_snapshots')) {
            return 'missing-table';
        }

        $periodValues = array_values(array_unique(array_filter(
            array_map(static fn ($period): string => trim((string) $period), $periods),
            static fn (string $period): bool => $period !== ''
        )));

        if ($periodValues === []) {
            return 'empty-periods';
        }

        $branches = $selectedKanca === 'all'
            ? self::KREDIT_AREA_6_BRANCHES
            : [$selectedKanca];

        $columns = ['snapshot_period', 'kanca_label'];
        foreach (['kanca_key', 'unit_key', 'source_signature', 'updated_at'] as $column) {
            if (Schema::hasColumn('dashboard_harian_snapshots', $column)) {
                $columns[] = $column;
            }
        }

        $query = DB::table('dashboard_harian_snapshots')
            ->whereIn('snapshot_period', $periodValues)
            ->whereIn('kanca_label', $branches);

        if (
            Schema::hasColumn('dashboard_harian_snapshots', 'kanca_key')
            && Schema::hasColumn('dashboard_harian_snapshots', 'unit_key')
            && $selectedKanca === 'all'
        ) {
            $query->whereColumn('kanca_key', 'unit_key');
        }

        $rows = $query
            ->orderBy('snapshot_period')
            ->orderBy('kanca_label')
            ->when(Schema::hasColumn('dashboard_harian_snapshots', 'unit_key'), fn ($query) => $query->orderBy('unit_key'))
            ->get($columns)
            ->map(static fn ($row): array => (array) $row)
            ->all();

        return sha1(json_encode([
            'periods' => $periodValues,
            'branches' => $branches,
            'rows' => $rows,
        ]));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function kreditBranchOptions(): array
    {
        $scope = UserBranchScope::current();
        if ($scope !== null) {
            return [[
                'value' => $scope['label'],
                'label' => $scope['label'],
            ]];
        }

        return array_merge(
            [['value' => 'all', 'label' => 'Area 6']],
            array_map(
                fn (string $branch): array => ['value' => $branch, 'label' => $branch],
                self::KREDIT_AREA_6_BRANCHES
            )
        );
    }

    private function resolveKreditBranch(mixed $requestedBranch): string
    {
        $branch = trim((string) $requestedBranch);

        if ($branch === '' || strtolower($branch) === 'all') {
            return 'all';
        }

        return in_array($branch, self::KREDIT_AREA_6_BRANCHES, true) ? $branch : 'all';
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

        $cacheKey = 'dashboard_pinjaman_filters:v3-combination-map:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'filters' => $filters,
        ]));

        $payload = $this->rememberPayload($cacheKey, now()->addMinutes(5), function () use ($selectedPeriod, $filters, $forceRefresh) {
            return $this->buildPeriodFilterOptions($selectedPeriod, $filters, $forceRefresh);
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

        $warmingPeriods = $this->queueMissingMatrixSnapshots([
            $selectedPeriod,
            $comparisonPeriod,
        ]);

        if ($warmingPeriods !== []) {
            return response()->json([
                'status' => 'warming',
                'retry_after_ms' => 5000,
                'warming_periods' => $warmingPeriods,
                'selected_period' => $selectedPeriod,
                'comparison_period' => $comparisonPeriod,
                'matrix_columns' => self::QUALITY_BUCKETS,
                'output_columns' => self::OUTPUT_COLUMNS,
                'matrix_rows' => [],
                'grand_totals' => [],
                'grand_total_value' => 0.0,
                'data_source' => 'daily_loan_dinamis',
            ]);
        }

        $cacheKey = 'dashboard_pinjaman_matrix_direct:v10-daily-ph-and-cras-exit:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $selectedPeriod,
            'comparison' => $comparisonPeriod,
            'ph_period' => $phPeriod,
            'recovery_source' => $this->shouldUseLw325RecoveryMetrics($selectedPeriod) ? self::PH_TABLE : 'loan_movement',
            'filters' => $filters,
        ]));

        [$matrixRows, $grandTotals, $grandTotalValue] = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(15),
            fn () => $this->buildMatrixData($selectedPeriod, $comparisonPeriod, $filters),
            $forceRefresh,
            fn () => [[], [], 0.0]
        );

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'matrix_columns' => self::QUALITY_BUCKETS,
            'output_columns' => self::OUTPUT_COLUMNS,
            'matrix_rows' => $matrixRows,
            'grand_totals' => $grandTotals,
            'grand_total_value' => $grandTotalValue,
            'data_source' => 'daily_loan_dinamis',
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
        $afterBucket = trim((string) $request->input('after_bucket', ''));
        $limit = max(10, min(50, (int) $request->input('limit', 25)));
        $offset = max(0, (int) $request->input('offset', 0));

        abort_if(
            !$selectedPeriod
                || !in_array($beforeBucket, self::BEFORE_ROWS, true)
                || !in_array($afterBucket, self::QUALITY_BUCKETS, true),
            422,
            'Periode dan bucket pivot wajib valid.'
        );

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $columns = $this->collectMatrixModalColumns();
        $rows = $this->buildMatrixDrilldownQuery($selectedPeriod, $comparisonPeriod, $filters, $beforeBucket, $columns, $afterBucket)
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->map(fn ($row) => (array) $row)->values();

        return response()->json([
            'selected_period' => $selectedPeriod,
            'comparison_period' => $comparisonPeriod,
            'before_bucket' => $beforeBucket,
            'after_bucket' => $afterBucket,
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
        $afterBucket = trim((string) $request->input('after_bucket', ''));

        abort_if(!$selectedPeriod || !in_array($beforeBucket, self::BEFORE_ROWS, true), 422, 'Periode dan bucket pivot wajib valid.');
        abort_if($afterBucket !== '' && !in_array($afterBucket, self::QUALITY_BUCKETS, true), 422, 'Bucket tujuan pivot wajib valid.');

        $filters = [
            'segmen' => $this->normalizeFilterValues($request->input('segmen_dashboard')),
            'produk' => $this->normalizeFilterValues($request->input('produk_dashboard')),
            'cabang' => $this->normalizeFilterValues($request->input('cabang1')),
            'unit' => $this->normalizeFilterValues($request->input('unit1')),
        ];

        $exportColumns = $this->collectMatrixDetailColumns();
        $query = $this->buildMatrixDrilldownQuery($selectedPeriod, $comparisonPeriod, $filters, $beforeBucket, $exportColumns, $afterBucket ?: null);
        $filename = sprintf(
            'matrix-pergeseran-kolek_%s_%s%s.xlsx',
            str_replace('-', '', $selectedPeriod),
            $this->sanitizeExportToken($beforeBucket),
            $afterBucket !== '' ? '_' . $this->sanitizeExportToken($afterBucket) : ''
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

        $scope = UserBranchScope::current();
        if ($scope !== null) {
            $branches = $branches
                ->filter(fn ($branch): bool => strcasecmp(trim((string) $branch), $scope['label']) === 0)
                ->values();
        }

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

        $cacheKey = 'dashboard_pinjaman_kolek_mismatch_data:v9:' . md5(json_encode([
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
        $useComparisonSnapshot = $comparisonPeriod
            ? $this->shouldUseSnapshot($comparisonPeriod, $filters)
            : false;
        $useLw325RecoveryMetrics = $this->shouldUseLw325RecoveryMetrics($selectedPeriod);

        // Ensure both periods use the same source to avoid account_number format mismatches
        // If one period doesn't have snapshot, both must use daily_loan_dinamis
        if ($comparisonPeriod && $useCurrentSnapshot !== $useComparisonSnapshot) {
            $useCurrentSnapshot = $useCurrentSnapshot && $useComparisonSnapshot;
            $useComparisonSnapshot = $useCurrentSnapshot;
        }

        $bucketMap = [];
        $metricMap = [];
        $metricSeen = array_fill_keys(['principal_reduction', 'suplesi', 'ph', 'lunas'], false);

        // Optimize: Set read-only connection mode for better database optimization
        try {
            if ($useLw325RecoveryMetrics) {
                $matrixRowsRaw = $this->buildMovementMatrixAndSuplesiAggregateQuery(
                    $selectedPeriod,
                    $comparisonPeriod,
                    $filters,
                    $useCurrentSnapshot,
                    $useComparisonSnapshot
                )->get();
            } else {
                // Movement comparison must stay database-side so large portfolios do not require PHP in-memory joins.
                $matrixRowsRaw = $this->buildMovementMatrixAggregateQuery(
                    $selectedPeriod,
                    $comparisonPeriod,
                    $filters,
                    $useCurrentSnapshot,
                    $useComparisonSnapshot
                )->get();
            }
            
            $matrixRowsCount = $matrixRowsRaw->count();
            
            foreach ($matrixRowsRaw as $row) {
                $before = (string) ($row->before_bucket ?? 'New Account');
                $after = (string) ($row->after_bucket ?? '');
                $amountCents = (int) ($row->amount_cents ?? 0);

                if (!in_array($before, self::BEFORE_ROWS, true) || !in_array($after, self::QUALITY_BUCKETS, true) || $amountCents <= 0) {
                    continue;
                }

                $bucketMap[$before][$after] = $amountCents;

                $suplesiCents = (int) ($row->suplesi_cents ?? 0);
                if ($useLw325RecoveryMetrics && $suplesiCents > 0) {
                    $metricMap[$before]['suplesi'] = ($metricMap[$before]['suplesi'] ?? 0) + $suplesiCents;
                }
            }

            $metricRowsRaw = $useLw325RecoveryMetrics
                ? $this->buildLw325MatrixMetricAggregateQuery(
                    $selectedPeriod,
                    $comparisonPeriod,
                    $filters,
                    $useCurrentSnapshot,
                    $useComparisonSnapshot
                )->get()
                : $this->buildMovementMetricAggregateQuery(
                    $selectedPeriod,
                    $comparisonPeriod,
                    $phPeriod,
                    $filters,
                    $useCurrentSnapshot,
                    $useComparisonSnapshot,
                    false
                )->get();
            
            $metricRowsCount = $metricRowsRaw->count();
            
            foreach ($metricRowsRaw as $row) {
                $before = (string) ($row->before_bucket ?? 'New Account');
                $metric = (string) ($row->metric_type ?? '');
                $amountCents = (int) ($row->amount_cents ?? 0);

                if (
                    !in_array($before, self::BEFORE_ROWS, true)
                    || !in_array($metric, ['principal_reduction', 'suplesi', 'ph', 'lunas'], true)
                    || $amountCents < 0
                    || ($amountCents === 0 && $metric !== 'ph')
                ) {
                    continue;
                }

                $metricMap[$before][$metric] = ($metricMap[$before][$metric] ?? 0) + $amountCents;
                $metricSeen[$metric] = true;
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
                $hasMetric = array_key_exists($metricName, $metricMap[$beforeLabel] ?? []);
                $metricTotals[$metricName] += $metricCents;
                $rowMetrics[$metricName] = $hasMetric ? $this->centsToAmount($metricCents) : null;
            }

            $grandTotalCents += $rowTotalCents;

            $matrixRows[] = [
                'label' => $beforeLabel,
                'values' => $values,
                'metrics' => $rowMetrics,
                'total' => $rowTotalCents > 0 ? $this->centsToAmount($rowTotalCents) : null,
            ];
        }

        $metricGrandTotals = [];
        foreach ($metricTotals as $metricName => $metricCents) {
            $metricGrandTotals[$metricName] = ($metricSeen[$metricName] ?? false)
                ? $this->centsToAmount($metricCents)
                : null;
        }

        $grandTotals = [
            'matrix' => array_map(
                fn (int $columnTotalCents) => $columnTotalCents > 0 ? $this->centsToAmount($columnTotalCents) : null,
                $matrixGrandTotals
            ),
            'metrics' => $metricGrandTotals,
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

    private function buildMatrixDrilldownQuery(string $selectedPeriod, ?string $comparisonPeriod, array $filters, string $beforeBucket, array $columns, ?string $afterBucket = null): Builder
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

        if ($afterBucket !== null) {
            $query->whereRaw("({$currentBucketExpression}) = ?", [$afterBucket]);
        }

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
        $cached = $this->filterDailyLoanOutputColumns($cached);

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
            $this->filterDailyLoanOutputColumns(Schema::getColumnListing('daily_loan_dinamis'))
        )));

        return $cached;
    }

    private function dailyLoanOutputExcludedColumns(array $extra = []): array
    {
        return array_values(array_unique(array_merge(
            self::DAILY_LOAN_HELPER_OUTPUT_COLUMNS,
            $extra
        )));
    }

    private function filterDailyLoanOutputColumns(array $columns, array $extraExcluded = []): array
    {
        $excluded = array_fill_keys($this->dailyLoanOutputExcludedColumns($extraExcluded), true);

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => !isset($excluded[$column])
        ));
    }

    private function qualifyDailyLoanColumns(array $columns): array
    {
        return array_map(
            static fn (string $column): string => 'daily_loan_dinamis.' . $column,
            $columns
        );
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
            ->fromSub(
                $joinedCurrent
                    ->unionAll($this->buildAnonymousCurrentMovementQuery($selectedPeriod, $filters)),
                'movement_matrix'
            )
            ->selectRaw('before_bucket, after_bucket, SUM(amount_cents) as amount_cents')
            ->groupBy('before_bucket', 'after_bucket');
    }

    private function buildMovementMatrixAndSuplesiAggregateQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useCurrentSnapshot = null,
        ?bool $useComparisonSnapshot = null
    ) {
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
                SUM(curr.balance_cents) as amount_cents,
                SUM(
                    CASE
                        WHEN curr.balance_cents > COALESCE(prev.balance_cents, 0)
                        THEN curr.balance_cents - COALESCE(prev.balance_cents, 0)
                        ELSE 0
                    END
                ) as suplesi_cents
            ")
            ->whereNotNull('curr.bucket')
            ->whereIn('curr.bucket', self::QUALITY_BUCKETS)
            ->groupByRaw("COALESCE(prev.bucket, 'New Account'), curr.bucket");

        $anonymousCurrent = DB::query()
            ->fromSub($this->buildAnonymousCurrentMovementQuery($selectedPeriod, $filters), 'anon_matrix')
            ->selectRaw('before_bucket, after_bucket, amount_cents, amount_cents as suplesi_cents');

        return DB::query()
            ->fromSub($joinedCurrent->unionAll($anonymousCurrent), 'movement_matrix')
            ->selectRaw('before_bucket, after_bucket, SUM(amount_cents) as amount_cents, SUM(suplesi_cents) as suplesi_cents')
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
        if ($useLw325RecoveryMetrics) {
            $joinedMetrics = DB::query()
                ->fromSub($currentSnapshot, 'curr')
                ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                    $join->on('curr.account_number', '=', 'prev.account_number');
                })
                ->selectRaw("
                    COALESCE(prev.bucket, 'New Account') as before_bucket,
                    'suplesi' as metric_type,
                    curr.balance_cents - COALESCE(prev.balance_cents, 0) as amount_cents
                ")
                ->whereNotNull('curr.bucket')
                ->whereRaw('curr.balance_cents > COALESCE(prev.balance_cents, 0)');
        } else {
            // Consolidated metrics query: single pass instead of 4 UNIONs
            $joinedMetrics = DB::query()
                ->fromSub($currentSnapshot, 'curr')
                ->leftJoinSub($previousSnapshot, 'prev', function ($join) {
                    $join->on('curr.account_number', '=', 'prev.account_number');
                })
                ->selectRaw("
                    COALESCE(prev.bucket, 'New Account') as before_bucket,
                    CASE
                        WHEN COALESCE(prev.balance_cents, 0) > 0 
                            AND curr.balance_cents > 0 
                            AND prev.balance_cents > curr.balance_cents
                        THEN 'principal_reduction'
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
        }

        // Anonymous metrics
        $anonMetrics = DB::query()
            ->fromSub($this->buildAnonymousCurrentMovementQuery($selectedPeriod, $filters), 'anon_metric')
            ->selectRaw("before_bucket, 'suplesi' as metric_type, amount_cents");

        $movementMetrics = $joinedMetrics->unionAll($anonMetrics);

        $movementMetrics->unionAll(
            $this->buildDailyLoanExitMetricQuery($selectedPeriod, $comparisonPeriod, $filters, $useCurrentSnapshot, $useComparisonSnapshot)
        );

        $movementMetrics->unionAll(
            $this->buildDailyLoanPhMetricQuery($selectedPeriod, $comparisonPeriod, $filters, $useComparisonSnapshot)
        );

        if ($useLw325RecoveryMetrics) {
            $movementMetrics->unionAll(
                $this->buildLw325RecoveryMetricQuery($selectedPeriod, $comparisonPeriod, $filters, $useComparisonSnapshot)
            );
        }

        return $this->buildRecoveryMetricAggregateQuery($movementMetrics);
    }

    private function buildRecoveryMetricAggregateQuery($movementMetrics)
    {
        return DB::query()
            ->fromSub($movementMetrics, 'movement_metrics')
            ->selectRaw('before_bucket, metric_type, SUM(amount_cents) as amount_cents')
            ->whereNotNull('metric_type')
            ->whereIn('before_bucket', self::BEFORE_ROWS)
            ->where(function (Builder $query): void {
                $query->where('amount_cents', '>', 0)
                    ->orWhere('metric_type', 'ph');
            })
            ->groupBy('before_bucket', 'metric_type');
    }

    private function buildLw325MatrixMetricAggregateQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useCurrentSnapshot = null,
        ?bool $useComparisonSnapshot = null
    ) {
        $metrics = $this->buildLw325RecoveryMetricQuery(
            $selectedPeriod,
            $comparisonPeriod,
            $filters,
            $useComparisonSnapshot
        );

        $metrics->unionAll(
            $this->buildDailyLoanPhMetricQuery(
                $selectedPeriod,
                $comparisonPeriod,
                $filters,
                $useComparisonSnapshot
            )
        );

        $metrics->unionAll(
            $this->buildDailyLoanExitMetricQuery(
                $selectedPeriod,
                $comparisonPeriod,
                $filters,
                $useCurrentSnapshot,
                $useComparisonSnapshot
            )
        );

        return $this->buildRecoveryMetricAggregateQuery($metrics);
    }

    private function buildDailyLoanExitMetricQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useCurrentSnapshot = null,
        ?bool $useComparisonSnapshot = null
    ): Builder {
        if (!$comparisonPeriod) {
            return DB::query()
                ->selectRaw("'New Account' as before_bucket, 'lunas' as metric_type, 0 as amount_cents")
                ->whereRaw('1 = 0');
        }

        $currentSnapshot = $this->buildAggregatedLoanSnapshotQuery($selectedPeriod, $filters, 'curr_exit_metric', $useCurrentSnapshot);
        $previousSnapshot = $this->buildAggregatedLoanSnapshotQuery($comparisonPeriod, $filters, 'prev_exit_metric', $useComparisonSnapshot);
        $currentPhPeriod = $this->resolveCurrentMonthPhPeriod($selectedPeriod);
        $hasCurrentPhData = $currentPhPeriod !== null && $this->hasUsableLw325RecoveryPeriod($currentPhPeriod);

        $exitQuery = DB::query()
            ->fromSub($previousSnapshot, 'prev_exit_metric')
            ->leftJoinSub($currentSnapshot, 'curr_exit_metric', function ($join) {
                $join->on('prev_exit_metric.account_number', '=', 'curr_exit_metric.account_number');
            })
            ->whereNull('curr_exit_metric.account_number')
            ->whereNotNull('prev_exit_metric.bucket')
            ->whereIn('prev_exit_metric.bucket', self::BEFORE_ROWS);

        if ($hasCurrentPhData) {
            $phAccountKey = $this->phAccountKeySql('ph');
            $previousAccountKey = $this->accountKeySql('prev_exit_metric.account_number');
            $phAmount = $this->buildPhRecoveryIntegerAmountExpression('ph.pokok');
            $phAccounts = DB::table('lw325_ph as ph')
                ->where('ph.periode', $currentPhPeriod)
                ->whereNotNull('ph.acctno')
                ->where('ph.acctno', '<>', '')
                ->where('ph.pokok', '>', 0)
                ->selectRaw("{$phAccountKey} as account_number")
                ->selectRaw("CAST(ROUND(SUM(COALESCE({$phAmount}, 0)) * 100, 0) AS SIGNED) as ph_amount_cents")
                ->groupByRaw($phAccountKey);

            $exitQuery
                ->leftJoinSub($phAccounts, 'current_ph_accounts', function ($join) use ($previousAccountKey) {
                    $join->on(DB::raw($previousAccountKey), '=', 'current_ph_accounts.account_number');
                })
                ->selectRaw("
                    prev_exit_metric.bucket as before_bucket,
                    CASE
                        WHEN current_ph_accounts.account_number IS NOT NULL THEN 'ph'
                        ELSE 'lunas'
                    END as metric_type,
                    CASE
                        WHEN current_ph_accounts.account_number IS NOT NULL THEN current_ph_accounts.ph_amount_cents
                        ELSE prev_exit_metric.balance_cents
                    END as amount_cents
                ");
        } else {
            $exitQuery->selectRaw("
                prev_exit_metric.bucket as before_bucket,
                'lunas' as metric_type,
                prev_exit_metric.balance_cents as amount_cents
            ");
        }

        return $exitQuery;
    }

    private function buildDailyLoanPhMetricQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useComparisonSnapshot = null
    ) {
        if (!Schema::hasColumn('daily_loan_dinamis', 'status_rekening1')) {
            return DB::query()
                ->selectRaw("'New Account' as before_bucket, 'ph' as metric_type, 0 as amount_cents")
                ->whereRaw('1 = 0');
        }

        $alias = 'ph_loan';
        $balanceExpression = $this->buildExcelSnapshotOsHelperExpression("{$alias}.baki_debet1");
        $currentAccountKey = $this->accountKeySql("{$alias}.nomor_rekening1");
        $previousSnapshot = $comparisonPeriod
            ? $this->buildPreviousBucketLookupQuery($comparisonPeriod, $filters, 'prev_ph_loan', $useComparisonSnapshot)
            : $this->buildEmptyAggregatedLoanSnapshotQuery();
        $previousAccountKey = $this->accountKeySql('prev_bucket.account_number');

        $rows = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->leftJoinSub($previousSnapshot, 'prev_bucket', function ($join) use ($currentAccountKey, $previousAccountKey) {
                $join->on(DB::raw($currentAccountKey), '=', DB::raw($previousAccountKey));
            })
            ->where("{$alias}.periode", $selectedPeriod)
            ->whereRaw("TRIM(COALESCE({$alias}.status_rekening1, '')) = '5'")
            ->whereNotNull("{$alias}.nomor_rekening1")
            ->where("{$alias}.nomor_rekening1", '<>', '')
            ->selectRaw("{$currentAccountKey} as account_number")
            ->selectRaw("COALESCE(prev_bucket.bucket, 'New Account') as before_bucket")
            ->selectRaw("COALESCE({$balanceExpression}, 0) as balance_amount");

        $this->applyFilterConstraint($rows, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($rows, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($rows, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($rows, "{$alias}.unit1", $filters['unit']);

        $accounts = DB::query()
            ->fromSub($rows, 'daily_loan_ph_rows')
            ->selectRaw('account_number, before_bucket, MAX(balance_amount) as balance_amount')
            ->groupBy('account_number', 'before_bucket');

        $query = DB::query()
            ->fromSub($accounts, 'daily_loan_ph_accounts')
            ->selectRaw('before_bucket')
            ->selectRaw("'ph' as metric_type")
            ->selectRaw('CAST(ROUND(SUM(COALESCE(balance_amount, 0)) * 100, 0) AS SIGNED) as amount_cents')
            ->groupBy('before_bucket');

        return DB::query()
            ->fromSub($query, 'daily_loan_ph_metrics')
            ->selectRaw('before_bucket, metric_type, amount_cents');
    }

    private function buildLw325RecoveryMetricQuery(
        string $selectedPeriod,
        ?string $comparisonPeriod,
        array $filters,
        ?bool $useComparisonSnapshot = null
    ) {
        $currentPhPeriod = $this->resolveCurrentMonthPhPeriod($selectedPeriod);
        $previousPhPeriod = $currentPhPeriod ? $this->resolvePreviousMonthPhPeriod($currentPhPeriod) : null;

        if (
            !$currentPhPeriod
            || !$previousPhPeriod
            || !$comparisonPeriod
            || !$this->isPreviousMonthEndPhPeriod($currentPhPeriod, $previousPhPeriod)
            || !$this->hasUsableLw325RecoveryPeriod($currentPhPeriod)
            || !$this->hasUsableLw325RecoveryPeriod($previousPhPeriod)
        ) {
            return DB::query()
                ->selectRaw("'New Account' as before_bucket, 'principal_reduction' as metric_type, 0 as amount_cents")
                ->whereRaw('1 = 0');
        }

        $previousSnapshot = $this->buildPreviousBucketLookupQuery($comparisonPeriod, $filters, 'prev_recovery', $useComparisonSnapshot);
        $oldPokok = $this->buildPhRecoveryIntegerAmountExpression('o.pokok');
        $currentPokok = $this->buildPhRecoveryIntegerAmountExpression('n.pokok');
        $currentAccountKey = $this->phAccountKeySql('n');
        $previousAccountKey = $this->phAccountKeySql('o');
        $previousBucketAccountKey = $this->accountKeySql('prev_bucket.account_number');

        $previousAccounts = DB::table('lw325_ph as o')
            ->where('o.periode', $previousPhPeriod)
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("{$previousAccountKey} as account_number")
            ->selectRaw("SUM(COALESCE({$oldPokok}, 0)) as pokok_amount")
            ->groupByRaw($previousAccountKey);
        $this->applyLw325RecoveryFilters($previousAccounts, 'o', $filters);

        $currentAccounts = DB::table('lw325_ph as n')
            ->where('n.periode', $currentPhPeriod)
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->selectRaw("{$currentAccountKey} as account_number")
            ->selectRaw("SUM(COALESCE({$currentPokok}, 0)) as pokok_amount")
            ->groupByRaw($currentAccountKey);

        $tupokQuery = DB::query()
            ->fromSub($previousAccounts, 'old_ph')
            ->joinSub($currentAccounts, 'current_ph', function ($join) {
                $join->on('old_ph.account_number', '=', 'current_ph.account_number');
            })
            ->leftJoinSub($previousSnapshot, 'prev_bucket', function ($join) use ($previousBucketAccountKey) {
                $join->on('old_ph.account_number', '=', DB::raw($previousBucketAccountKey));
            })
            ->selectRaw("COALESCE(prev_bucket.bucket, 'New Account') as before_bucket")
            ->selectRaw("'principal_reduction' as metric_type")
            ->selectRaw('CAST(ROUND((old_ph.pokok_amount - current_ph.pokok_amount) * 100, 0) AS SIGNED) as amount_cents')
            ->whereRaw('(old_ph.pokok_amount - current_ph.pokok_amount) > 0');

        return $tupokQuery;
    }

    private function buildPhRecoveryIntegerAmountExpression(string $column): string
    {
        return $this->buildExcelSnapshotOsHelperExpression($column);
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

    private function buildPreviousBucketLookupQuery(string $period, array $filters, string $alias, ?bool $useSnapshot = null)
    {
        $shouldUseSnapshot = $useSnapshot ?? $this->shouldUseSnapshot($period, $filters);

        if (!$shouldUseSnapshot) {
            return $this->buildAggregatedLoanSnapshotQuery($period, $filters, $alias, $useSnapshot);
        }

        $bucketRankExpression = $this->buildMovementBucketRankExpression("{$alias}.quality_bucket");
        $baseQuery = DB::table(self::SNAPSHOT_TABLE . " as {$alias}")
            ->where("{$alias}.periode", $period)
            ->whereNotNull("{$alias}.account_number")
            ->where("{$alias}.account_number", '<>', '')
            ->selectRaw("
                {$alias}.account_number,
                MAX({$bucketRankExpression}) as bucket_rank
            ")
            ->groupBy("{$alias}.account_number");

        $this->applyFilterConstraint($baseQuery, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($baseQuery, "{$alias}.unit1", $filters['unit']);

        return DB::query()
            ->fromSub($baseQuery, $alias . '_bucket')
            ->selectRaw("
                account_number,
                {$this->buildMovementBucketLabelExpressionFromRank($alias . '_bucket.bucket_rank')} as bucket
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
        $balanceExpression = $this->buildExcelSnapshotOsHelperExpression("{$alias}.baki_debet1");

        $rowQuery = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $period)
            ->where(function ($query) use ($alias) {
                $query->whereNull("{$alias}.nomor_rekening1")
                    ->orWhere("{$alias}.nomor_rekening1", '=', '');
            })
            ->selectRaw("
                {$bucketExpression} as after_bucket,
                {$balanceExpression} as loan_balance
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
        $balanceExpression = $this->buildExcelSnapshotOsHelperExpression("{$alias}.baki_debet1");

        $query = DB::table(DB::raw($this->buildLoanSnapshotSource($alias, $filters)))
            ->where("{$alias}.periode", $period)
            ->whereNotNull("{$alias}.nomor_rekening1")
            ->where("{$alias}.nomor_rekening1", '<>', '')
            ->selectRaw("
                TRIM({$alias}.nomor_rekening1) as account_number,
                {$balanceExpression} as " . ($alias === 'curr' ? 'current_balance' : 'previous_balance') . ",
                {$bucketExpression} as " . ($alias === 'curr' ? 'after_bucket' : 'before_bucket')
            );

        $this->applyFilterConstraint($query, "{$alias}.segmen_dashboard", $filters['segmen']);
        $this->applyFilterConstraint($query, "{$alias}.produk_dashboard", $filters['produk']);
        $this->applyFilterConstraint($query, "{$alias}.cabang1", $filters['cabang']);
        $this->applyFilterConstraint($query, "{$alias}.unit1", $filters['unit']);

        return $query;
    }

    private function buildExcelSnapshotOsHelperExpression(string $column): string
    {
        $wholeRupiah = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(COALESCE({$column}, 0) AS INTEGER)"
            : "TRUNCATE(COALESCE({$column}, 0), 0)";
        $absolute = "ABS({$wholeRupiah})";
        $sign = "CASE WHEN {$wholeRupiah} < 0 THEN -1 ELSE 1 END";

        // Mirrors the Excel snapshot helper "OS BARU/OS LAMA" without storing
        // helper columns in daily_loan_dinamis.
        return "
            CASE
                WHEN {$absolute} >= 1000
                    AND {$absolute} < 1000000
                    AND ({$absolute} % 10) = 0
                    THEN ({$sign}) * CASE
                        WHEN ({$absolute} % 1000) = 0 THEN {$absolute} / 1000
                        WHEN ({$absolute} % 100) = 0 THEN {$absolute} / 100
                        ELSE {$absolute} / 10
                    END
                ELSE {$wholeRupiah}
            END
        ";
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

    /**
     * Build selector options from the two existing covering indexes. Keeping the
     * dimensions in pairs avoids expensive table-row lookups on the 4 GB source.
     *
     * @return array{segments: Collection, products: Collection, branches: Collection, units: Collection}
     */
    private function buildPeriodFilterOptions(string $period, array $filters, bool $forceRefresh = false): array
    {
        $cacheKey = 'dashboard_pinjaman_filter_dimensions:v2-covering-pairs:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periode' => $period,
        ]));

        $dimensions = $this->rememberPayload(
            $cacheKey,
            now()->addMinutes(10),
            function () use ($period): array {
                $segmentProductSource = $this->qualifyIndexedSource(
                    'daily_loan_dinamis',
                    'filter_sp',
                    [self::LOAN_FILTER_INDEX]
                );
                $branchUnitSource = $this->qualifyIndexedSource(
                    'daily_loan_dinamis',
                    'filter_bu',
                    [self::LOAN_CABANG_UNIT_INDEX]
                );

                return [
                    'segment_products' => DB::table(DB::raw($segmentProductSource))
                        ->where('filter_sp.periode', $period)
                        ->select(['filter_sp.segmen_dashboard', 'filter_sp.produk_dashboard'])
                        ->distinct()
                        ->get(),
                    'branch_units' => DB::table(DB::raw($branchUnitSource))
                        ->where('filter_bu.periode', $period)
                        ->select(['filter_bu.cabang1', 'filter_bu.unit1'])
                        ->distinct()
                        ->get(),
                ];
            },
            $forceRefresh,
            fn () => ['segment_products' => collect(), 'branch_units' => collect()]
        );

        $segmentProducts = $dimensions['segment_products'] ?? collect();
        $branchUnits = $dimensions['branch_units'] ?? collect();

        return [
            'segments' => $this->filterOptionsFromCombinations($segmentProducts, 'segmen_dashboard'),
            'products' => $this->filterOptionsFromCombinations($segmentProducts, 'produk_dashboard', [
                'segmen_dashboard' => $filters['segmen'] ?? [],
            ]),
            'branches' => $this->filterOptionsFromCombinations($branchUnits, 'cabang1'),
            'units' => $this->filterOptionsFromCombinations($branchUnits, 'unit1', [
                'cabang1' => $filters['cabang'] ?? [],
            ]),
        ];
    }

    private function filterOptionsFromCombinations(Collection $combinations, string $column, array $constraints = []): Collection
    {
        $normalizedConstraints = [];
        foreach ($constraints as $constraintColumn => $selectedValues) {
            $normalizedConstraints[$constraintColumn] = array_map(
                fn ($value): string => mb_strtoupper(trim((string) $value)),
                $selectedValues
            );
        }

        return $combinations
            ->filter(function ($row) use ($normalizedConstraints): bool {
                foreach ($normalizedConstraints as $constraintColumn => $selectedValues) {
                    if ($selectedValues === []) {
                        continue;
                    }

                    $rowValue = mb_strtoupper(trim((string) ($row->{$constraintColumn} ?? '')));

                    if (!in_array($rowValue, $selectedValues, true)) {
                        return false;
                    }
                }

                return true;
            })
            ->map(fn ($row): string => (string) ($row->{$column} ?? ''))
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->unique()
            ->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))
            ->values();
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
        $cacheKey = 'dashboard_pinjaman_recovery_periods:v3:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $periods = DB::table('daily_loan_dinamis')
                ->whereNotNull('periode')
                ->distinct()
                ->pluck('periode')
                ->map(function ($periode) {
                    try {
                        return Carbon::parse($periode)->format('Y-m-d');
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter();

            $comparisonPeriods = $periods
                ->map(function (string $period): ?string {
                    try {
                        return Carbon::parse($period)->startOfMonth()->subDay()->format('Y-m-d');
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->unique()
                ->values();

            $comparisonLookup = $comparisonPeriods->isEmpty()
                ? collect()
                : DB::table('daily_loan_dinamis')
                    ->whereIn('periode', $comparisonPeriods->all())
                    ->distinct()
                    ->pluck('periode')
                    ->map(function ($periode) {
                        try {
                            return Carbon::parse($periode)->format('Y-m-d');
                        } catch (Throwable) {
                            return (string) $periode;
                        }
                    })
                    ->flip();

            return $periods
                ->filter(function (string $period) use ($comparisonLookup): bool {
                    try {
                        $comparisonPeriod = Carbon::parse($period)->startOfMonth()->subDay()->format('Y-m-d');

                        return $comparisonLookup->has($comparisonPeriod);
                    } catch (Throwable) {
                        return false;
                    }
                })
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
        $scope = UserBranchScope::current();
        if ($scope !== null) {
            return collect([$scope['label']]);
        }

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

            if (in_array($column, ['unit1', 'segmen_dashboard'], true) && $selectedBranches !== []) {
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
            ->select($this->qualifyDailyLoanColumns($this->collectSmallArrearsExportSourceColumns()))
            ->selectRaw("({$totalExpression}) as total_tunggakan_terhitung")
            ->where('periode', $selectedPeriod)
            ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('unit1', $selectedUnits))
            ->orderBy('cabang1')
            ->orderBy('unit1')
            ->orderBy($orderingColumn);
    }

    private function collectSmallArrearsExportSourceColumns(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = $this->filterDailyLoanOutputColumns(
            Schema::getColumnListing('daily_loan_dinamis'),
            ['created_at', 'updated_at', 'uniqueid_namareport']
        );

        return $cached;
    }

    private function collectSmallArrearsExportColumns(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = $this->collectSmallArrearsExportSourceColumns();
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
                ->startOfMonth()
                ->subDay()
                ->format('Y-m-d');

            return DB::table('daily_loan_dinamis')
                ->where('periode', $previousMonthEnd)
                ->value('periode');
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
            $normalizedPeriod = $this->resolveCurrentMonthPhPeriod($selectedPeriod);

            return $normalizedPeriod !== null && $this->hasUsableLw325RecoveryPeriod($normalizedPeriod);
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

    private function resolveCurrentMonthPhPeriod(?string $selectedPeriod): ?string
    {
        if (!$selectedPeriod || !Schema::hasTable('lw325_ph')) {
            return null;
        }

        try {
            $period = Carbon::parse($selectedPeriod);

            return DB::table('lw325_ph')
                ->whereBetween('periode', [
                    $period->copy()->startOfMonth()->toDateString(),
                    $period->toDateString(),
                ])
                ->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function resolvePreviousMonthPhPeriod(string $period): ?string
    {
        try {
            $monthEnd = Carbon::parse($period)
                ->startOfMonth()
                ->subDay()
                ->toDateString();

            return DB::table('lw325_ph')
                ->where('periode', $monthEnd)
                ->value('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function isPreviousMonthEndPhPeriod(string $currentPeriod, ?string $comparisonPeriod): bool
    {
        if ($comparisonPeriod === null) {
            return false;
        }

        try {
            $expectedPreviousMonthEnd = Carbon::parse($currentPeriod)
                ->startOfMonth()
                ->subDay()
                ->toDateString();

            return Carbon::parse($comparisonPeriod)->toDateString() === $expectedPreviousMonthEnd;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasUsableLw325RecoveryPeriod(string $period): bool
    {
        if (array_key_exists($period, $this->lw325RecoveryPeriodQuality)) {
            return $this->lw325RecoveryPeriodQuality[$period];
        }

        try {
            $stats = DB::table('lw325_ph')
                ->where('periode', $period)
                ->selectRaw('COUNT(*) as row_count')
                ->selectRaw("COUNT(DISTINCT NULLIF(TRIM(COALESCE(acctno, '')), '')) as distinct_account_count")
                ->selectRaw("
                    SUM(CASE
                        WHEN UPPER(TRIM(COALESCE(acctno, ''))) LIKE '%E+%'
                            OR UPPER(TRIM(COALESCE(acctno, ''))) LIKE '%E-%'
                            OR UPPER(TRIM(COALESCE(acctno, ''))) LIKE '%,%E%'
                        THEN 1 ELSE 0
                    END) as scientific_account_count
                ")
                ->first();

            $rowCount = (int) ($stats->row_count ?? 0);
            $distinctAccountCount = (int) ($stats->distinct_account_count ?? 0);
            $scientificAccountCount = (int) ($stats->scientific_account_count ?? 0);

            $usable = $rowCount > 0
                && $scientificAccountCount === 0
                && $distinctAccountCount > 0;

            return $this->lw325RecoveryPeriodQuality[$period] = $usable;
        } catch (Throwable) {
            return $this->lw325RecoveryPeriodQuality[$period] = false;
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

        $lock = Cache::lock('snapshot:dashboard:auto-rebuild:' . $period, 60);
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
        } catch (Throwable $e) {
            Log::warning('Auto rebuild dashboard snapshot gagal: ' . $e->getMessage(), [
                'period' => $period,
            ]);
        } finally {
            optional($lock)->release();
        }

        Log::info('Dashboard snapshot is being prepared asynchronously.', [
            'period' => $period,
            'job_dispatched' => $jobDispatched,
        ]);

        Cache::put($cacheKey, false, now()->addSeconds(30));

        return false;
    }

    /**
     * Queue only the missing snapshots that have source data. This keeps the
     * matrix endpoint responsive instead of rebuilding hundreds of thousands
     * of snapshot rows during a browser request.
     */
    private function queueMissingMatrixSnapshots(array $periods): array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE) || !Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        $warmingPeriods = [];
        foreach (array_values(array_unique(array_filter($periods))) as $period) {
            if ($this->hasDashboardSnapshot($period)) {
                continue;
            }

            if (DB::table('daily_loan_dinamis')->where('periode', $period)->exists()) {
                $warmingPeriods[] = $period;
            }
        }

        return $warmingPeriods;
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
        $excluded = $this->dailyLoanOutputExcludedColumns(['created_at', 'updated_at']);

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
            ->whereIn(DB::raw('TRIM(status_rekening1)'), ['1', '3'])
            ->where('baki_debet1', '>', 0)
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

        $cached = $this->filterDailyLoanOutputColumns(
            Schema::getColumnListing('daily_loan_dinamis'),
            ['created_at', 'updated_at', 'uniqueid_namareport']
        );
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
        return ReportCacheVersion::get('pinjaman');
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

    private function fetchSixMonthArrearsPeriods(): Collection
    {
        $cacheKey = 'dashboard_pinjaman_realisasi_6_bulan_periods:v1:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return DB::table('daily_loan_dinamis')
                ->whereNotNull('periode')
                ->distinct()
                ->pluck('periode')
                ->map(function ($period): ?string {
                    try {
                        return Carbon::parse($period)->format('Y-m-d');
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->groupBy(fn (string $period): string => substr($period, 0, 7))
                ->map(fn (Collection $monthPeriods): string => $monthPeriods->sortDesc()->first())
                ->sortDesc()
                ->values();
        });
    }

    private function ugNplActionOptions(): array
    {
        return [
            'all' => 'Semua Analisa',
            'due_lancar' => 'Jatuh Tempo -> Lancar',
            'periodic_lancar' => 'Periodik -> Lancar',
            'general_lancar' => 'Umum -> Lancar',
            'general_sml3' => 'Umum NPL -> SML 3',
            'dl_lancar' => 'DL / RK -> Lancar',
            'dl_sml3' => 'DL / RK -> SML 3',
        ];
    }

    private function ugNplHorizonOptions(): array
    {
        return [
            0 => 'Hari Ini',
            7 => '7 Hari',
            30 => '30 Hari',
        ];
    }

    private function resolveUgNplAction($value): string
    {
        $action = trim((string) (is_array($value) ? ($value[0] ?? '') : $value));

        return array_key_exists($action, $this->ugNplActionOptions()) ? $action : 'all';
    }

    private function resolveUgNplHorizonDays($value): int
    {
        $days = (int) (is_array($value) ? ($value[0] ?? 0) : ($value ?? 0));

        return array_key_exists($days, $this->ugNplHorizonOptions()) ? $days : 30;
    }

    private function resolveUgNplSegmentSelection($value): array
    {
        $normalized = $this->normalizeFilterValues($value);

        if ($normalized === [] || in_array(self::UG_NPL_ALL_SEGMENTS, $normalized, true)) {
            return [
                'selected_values' => [self::UG_NPL_ALL_SEGMENTS],
                'effective_segments' => [],
            ];
        }

        return [
            'selected_values' => [$normalized[0]],
            'effective_segments' => [$normalized[0]],
        ];
    }

    private function emptyUgNplPayload(?string $selectedPeriod, string $selectedAction, int $horizonDays, array $selectedSegments = []): array
    {
        return [
            'selected_period' => $selectedPeriod,
            'selected_action' => $selectedAction,
            'horizon_days' => $horizonDays,
            'segment_label' => $selectedSegments === [] ? 'Semua Segmen' : implode(', ', $selectedSegments),
            'summary' => $this->emptyUgNplSummary(),
            'actions' => $this->emptyUgNplActionSummaries(),
            'rows' => [],
            'row_limit' => 250,
            'row_count' => 0,
        ];
    }

    private function emptyUgNplSummary(): array
    {
        return [
            'accounts' => 0,
            'outstanding' => 0.0,
            'current_arrears' => 0.0,
            'estimated_payment' => 0.0,
            'estimated_principal' => 0.0,
            'estimated_interest' => 0.0,
            'estimated_penalty' => 0.0,
            'cycles' => 0,
        ];
    }

    private function emptyUgNplActionSummaries(): array
    {
        $summaries = [];
        foreach ($this->ugNplActionOptions() as $key => $label) {
            if ($key === 'all') {
                continue;
            }
            $summaries[$key] = array_merge($this->emptyUgNplSummary(), [
                'key' => $key,
                'label' => $label,
            ]);
        }

        return $summaries;
    }

    private function buildUgNplPayload(
        string $selectedPeriod,
        array $selectedBranches,
        array $selectedUnits,
        bool $isAreaAll,
        string $selectedAction,
        int $horizonDays,
        array $selectedSegments = []
    ): array {
        $summary = $this->emptyUgNplSummary();
        $actions = $this->emptyUgNplActionSummaries();
        $rows = [];
        $rowLimit = 250;

        foreach ($this->fetchUgNplRows($selectedPeriod, $selectedBranches, $selectedUnits, $selectedSegments) as $row) {
            $analysis = $this->mapUgNplRow($row, $horizonDays);
            if ($analysis === null) {
                continue;
            }

            $this->accumulateUgNplSummary($summary, $analysis);
            if (isset($actions[$analysis['action_key']])) {
                $this->accumulateUgNplSummary($actions[$analysis['action_key']], $analysis);
            }

            if ($selectedAction === 'all' || $selectedAction === $analysis['action_key']) {
                $rows[] = $analysis;
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['estimated_payment'] <=> $a['estimated_payment']);
        $rows = array_slice($rows, 0, $rowLimit);

        return [
            'selected_period' => $selectedPeriod,
            'selected_action' => $selectedAction,
            'horizon_days' => $horizonDays,
            'scope_label' => $isAreaAll ? 'Area 6 - All' : implode(', ', $selectedBranches),
            'unit_label' => $selectedUnits === [] ? 'ALL UKER' : implode(', ', $selectedUnits),
            'segment_label' => $selectedSegments === [] ? 'Semua Segmen' : implode(', ', $selectedSegments),
            'summary' => $summary,
            'actions' => array_values($actions),
            'rows' => $rows,
            'row_limit' => $rowLimit,
            'row_count' => count($rows),
        ];
    }

    private function fetchUgNplRows(string $selectedPeriod, array $selectedBranches, array $selectedUnits, array $selectedSegments = []): \Generator
    {
        $preferredIndexes = $selectedSegments === []
            ? [self::LOAN_REKENING_INDEX]
            : [self::LOAN_FILTER_INDEX, self::LOAN_REKENING_INDEX];
        $query = DB::table(DB::raw($this->qualifyIndexedSource('daily_loan_dinamis', 'd', $preferredIndexes)))
            ->where('d.periode', $selectedPeriod)
            ->whereNotNull('d.nomor_rekening1')
            ->where('d.nomor_rekening1', '<>', '')
            ->whereRaw("CAST(TRIM(COALESCE(d.kolek, '0')) AS UNSIGNED) BETWEEN 2 AND 5")
            ->whereRaw('(COALESCE(d.tunggakan_pokok, 0) + COALESCE(d.tunggakan_bunga, 0) + COALESCE(d.tunggakan_penalti, 0)) > 0')
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('d.cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('d.unit1', $selectedUnits))
            ->when($selectedSegments !== [], fn (Builder $query) => $query->whereIn('d.segmen_dashboard', $selectedSegments))
            ->select([
                'd.periode',
                'd.cabang1',
                'd.unit1',
                'd.nomor_rekening1',
                'd.nama_debitur1',
                'd.segmen_dashboard',
                'd.ln_type',
                'd.kolek',
                'd.flag_restruk',
                'd.umur_tunggakan',
                'd.tgl_jatuh_tempo',
                'd.plafon',
                'd.baki_debet1',
                'd.tunggakan_pokok',
                'd.tunggakan_bunga',
                'd.tunggakan_penalti',
                'd.npb_pokok_la',
                'd.npb_bunga_la',
                'd.freq_payment',
                'd.freq_int_payment',
                'd.next_pmt_date',
                'd.next_pmt_int_date',
            ])
            ->orderByDesc('d.baki_debet1');

        foreach ($query->cursor() as $row) {
            yield $row;
        }
    }

    private function mapUgNplRow($row, int $horizonDays): ?array
    {
        $bucket = $this->kolekDetailFromFormula($row->kolek ?? null, $row->flag_restruk ?? null, $row->umur_tunggakan ?? null);
        $age = $this->normalizeUmurTunggakanValue($row->umur_tunggakan ?? null) ?? 0;
        $ageForDecision = max(0, $age + $horizonDays);
        $effectiveMonths = $this->ugNplEffectiveOverdueMonths($ageForDecision);
        $principalArrears = (float) ($row->tunggakan_pokok ?? 0);
        $interestArrears = (float) ($row->tunggakan_bunga ?? 0);
        $estimatedPenalty = (float) ($row->tunggakan_penalti ?? 0);
        $totalArrears = $principalArrears + $interestArrears + $estimatedPenalty;
        $loanType = strtoupper(trim((string) ($row->ln_type ?? '')));
        $isDlLoan = $loanType === 'DL';
        $freqPayment = (int) ($row->freq_payment ?? 0);
        $freqIntPayment = (int) ($row->freq_int_payment ?? 0);
        $isPeriodic = $freqPayment > 0 && $freqIntPayment > 0 && $freqPayment !== $freqIntPayment;
        $isPastDue = $this->isUgNplPastDue($row->periode ?? null, $row->tgl_jatuh_tempo ?? null);
        $isNplBucket = in_array($bucket, ['KL', 'D1', 'D2', 'M'], true);

        $cycles = 1;
        $targetBucket = 'Lancar';
        $estimatedPrincipal = 0.0;
        $estimatedInterest = 0.0;
        $paymentRule = '';

        if ($isPastDue) {
            $actionKey = 'due_lancar';
            $actionLabel = 'Jatuh Tempo -> Lancar';
            $estimatedPrincipal = $principalArrears;
            $estimatedInterest = $interestArrears;
            $paymentRule = 'Jatuh tempo: pokok + bunga + penalti';
        } elseif ($isDlLoan) {
            $cycles = $isNplBucket ? $this->ugNplPaymentsToSml3($effectiveMonths) : 1;
            $targetBucket = $isNplBucket ? 'SML 3' : 'Lancar';
            $actionKey = $isNplBucket ? 'dl_sml3' : 'dl_lancar';
            $actionLabel = $isNplBucket ? 'DL / RK -> SML 3' : 'DL / RK -> Lancar';
            $estimatedInterest = $isNplBucket
                ? $this->ugNplProratedArrears($interestArrears, $effectiveMonths, $cycles)
                : $interestArrears;
            $paymentRule = $isNplBucket
                ? 'DL belum jatuh tempo: bunga proporsional + penalti'
                : 'DL belum jatuh tempo: bunga + penalti';
        } elseif ($isPeriodic) {
            $actionKey = 'periodic_lancar';
            $actionLabel = 'Periodik -> Lancar';
            $estimatedPrincipal = $principalArrears;
            $estimatedInterest = $interestArrears;
            $paymentRule = 'Periodik: pokok + bunga + penalti';
        } elseif ($isNplBucket) {
            $cycles = $this->ugNplPaymentsToSml3($effectiveMonths);
            $targetBucket = 'SML 3';
            $actionKey = 'general_sml3';
            $actionLabel = 'Umum NPL -> SML 3';
            $estimatedPrincipal = $this->ugNplProratedArrears($principalArrears, $effectiveMonths, $cycles);
            $estimatedInterest = $this->ugNplProratedArrears($interestArrears, $effectiveMonths, $cycles);
            $paymentRule = 'Umum NPL: (pokok + bunga) proporsional + penalti';
        } else {
            $actionKey = 'general_lancar';
            $actionLabel = 'Umum -> Lancar';
            $estimatedPrincipal = $principalArrears;
            $estimatedInterest = $interestArrears;
            $paymentRule = 'Umum SML: pokok + bunga + penalti';
        }

        $isDlSml1ToLancar = $isDlLoan && $bucket === 'SML 1' && $targetBucket === 'Lancar';
        if (($isDlLoan && !$isPastDue) || $isDlSml1ToLancar) {
            $estimatedPrincipal = 0.0;
        }
        if ($isDlSml1ToLancar) {
            $paymentRule = 'DL SML 1 -> Lancar: bunga + penalti (pokok 0)';
        }

        $installment = $cycles > 0
            ? ($estimatedPrincipal + $estimatedInterest) / $cycles
            : ($estimatedPrincipal + $estimatedInterest);
        $estimatedPayment = $estimatedPrincipal + $estimatedInterest + $estimatedPenalty;

        if ($estimatedPayment <= 0) {
            return null;
        }

        return [
            'action_key' => $actionKey,
            'action_label' => $actionLabel,
            'target_bucket' => $targetBucket,
            'periode' => (string) ($row->periode ?? ''),
            'cabang1' => (string) ($row->cabang1 ?? ''),
            'unit1' => (string) ($row->unit1 ?? ''),
            'nomor_rekening1' => (string) ($row->nomor_rekening1 ?? ''),
            'nama_debitur1' => (string) ($row->nama_debitur1 ?? ''),
            'segmen_dashboard' => (string) ($row->segmen_dashboard ?? ''),
            'current_bucket' => $bucket,
            'kolek' => (string) ($row->kolek ?? ''),
            'loan_type' => $loanType,
            'payment_rule' => $paymentRule,
            'freq_payment' => $freqPayment,
            'freq_int_payment' => $freqIntPayment,
            'tgl_jatuh_tempo' => $row->tgl_jatuh_tempo ?? null,
            'is_past_due' => $isPastDue,
            'effective_months' => $effectiveMonths,
            'umur_tunggakan' => $age,
            'cycles' => $cycles,
            'installment' => $installment,
            'npb_pokok_la' => 0.0,
            'npb_bunga_la' => 0.0,
            'estimated_principal' => $estimatedPrincipal,
            'estimated_interest' => $estimatedInterest,
            'estimated_penalty' => $estimatedPenalty,
            'estimated_payment' => $estimatedPayment,
            'current_arrears' => $totalArrears,
            'outstanding' => (float) ($row->baki_debet1 ?? 0),
            'plafon' => (float) ($row->plafon ?? 0),
            'next_pmt_date' => $row->next_pmt_date ?? null,
            'next_pmt_int_date' => $row->next_pmt_int_date ?? null,
        ];
    }

    private function ugNplEffectiveOverdueMonths(int $age): int
    {
        return max(1, (int) ceil(max(1, $age) / 30));
    }

    private function ugNplPaymentsToSml3(int $effectiveMonths): int
    {
        return max(1, $effectiveMonths - 3 + 1);
    }

    private function ugNplProratedArrears(float $amount, int $effectiveMonths, int $cycles): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return ($amount / max(1, $effectiveMonths)) * max(1, $cycles);
    }

    private function isUgNplPastDue($period, $maturityDate): bool
    {
        if ($period === null || $maturityDate === null || trim((string) $maturityDate) === '') {
            return false;
        }

        try {
            $periodDate = Carbon::parse($period)->startOfDay();
            $dueDate = Carbon::parse($maturityDate)->startOfDay();
        } catch (Throwable) {
            return false;
        }

        return $periodDate->gt($dueDate);
    }

    private function accumulateUgNplSummary(array &$summary, array $analysis): void
    {
        $summary['accounts']++;
        $summary['outstanding'] += $analysis['outstanding'];
        $summary['current_arrears'] += $analysis['current_arrears'];
        $summary['estimated_payment'] += $analysis['estimated_payment'];
        $summary['estimated_principal'] += $analysis['estimated_principal'];
        $summary['estimated_interest'] += $analysis['estimated_interest'];
        $summary['estimated_penalty'] += $analysis['estimated_penalty'];
        $summary['cycles'] += $analysis['cycles'];
    }

    private function emptySixMonthArrearsPayload(?string $selectedPeriod, array $selectedBranches, array $selectedUnits, bool $isAreaAll, int $months = 6): array
    {
        return [
            'selected_period' => $selectedPeriod,
            'target_month_label' => $selectedPeriod ? $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months) : '-',
            'scope_label' => $isAreaAll ? 'Area 6 - All' : implode(', ', $selectedBranches),
            'unit_label' => $selectedUnits === [] ? 'ALL UKER' : implode(', ', $selectedUnits),
            'range_months' => $months,
            'rows' => [],
            'summary' => [
                'debitur' => 0,
                'outstanding' => 0.0,
                'tunggakan_pokok' => 0.0,
                'total_tunggakan' => 0.0,
                'target_month' => $selectedPeriod ? $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months) : '-',
            ],
        ];
    }

    private function buildSixMonthArrearsPayload(string $selectedPeriod, array $selectedBranches, array $selectedUnits, bool $isAreaAll, int $months = 6): array
    {
        $rows = collect($this->fetchSixMonthArrearsRows($selectedPeriod, $selectedBranches, $selectedUnits, $months));

        return [
            'selected_period' => $selectedPeriod,
            'target_month_label' => $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months),
            'scope_label' => $isAreaAll ? 'Area 6 - All' : implode(', ', $selectedBranches),
            'unit_label' => $selectedUnits === [] ? 'ALL UKER' : implode(', ', $selectedUnits),
            'range_months' => $months,
            'rows' => $rows->map(fn (array $row): array => [
                'periode' => $row['periode'] ?? '',
                'cabang1' => $row['cabang1'] ?? '',
                'unit1' => $row['unit1'] ?? '',
                'nomor_rekening1' => $row['nomor_rekening1'] ?? '',
                'nama_debitur1' => $row['nama_debitur1'] ?? '',
                'tgl_realisasi' => $row['tgl_realisasi'] ?? '',
                'plafon' => (float) ($row['plafon'] ?? 0),
                'baki_debet1' => (float) ($row['baki_debet1'] ?? 0),
                'tunggakan_pokok' => (float) ($row['tunggakan_pokok'] ?? 0),
                'tunggakan_bunga' => (float) ($row['tunggakan_bunga'] ?? 0),
                'tunggakan_penalti' => (float) ($row['tunggakan_penalti'] ?? 0),
                'total_tunggakan' => (float) ($row['total_tunggakan'] ?? 0),
                'umur_tunggakan' => $row['umur_tunggakan'] ?? '',
                'kolek' => $row['kolek'] ?? '',
                'kolek_detail' => $row['kolek_detail'] ?? '',
            ])->values()->all(),
            'summary' => [
                'debitur' => $rows->pluck('nomor_rekening1')->filter()->unique()->count(),
                'outstanding' => (float) $rows->sum(fn (array $row): float => floor((float) ($row['baki_debet1'] ?? 0))),
                'tunggakan_pokok' => (float) $rows->sum(fn (array $row): float => (float) ($row['total_tunggakan'] ?? 0)),
                'total_tunggakan' => (float) $rows->sum(fn (array $row): float => (float) ($row['total_tunggakan'] ?? 0)),
                'target_month' => $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months),
            ],
        ];
    }

    private function fetchSixMonthArrearsRows(string $selectedPeriod, array $selectedBranches, array $selectedUnits, int $months = 6): array
    {
        if (!Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi') && !Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1')) {
            return [];
        }

        if (!Schema::hasColumn('daily_loan_dinamis', 'kolek')) {
            return [];
        }

        $tunggakanColumns = array_values(array_filter(
            ['tunggakan_pokok', 'tunggakan_bunga', 'tunggakan_penalti'],
            fn (string $column): bool => Schema::hasColumn('daily_loan_dinamis', $column)
        ));

        if ($tunggakanColumns === []) {
            return [];
        }

        [$targetStart, $targetEnd] = $this->sixMonthArrearsTargetRange($selectedPeriod, $months);
        $realisasiColumn = Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1') ? 'tgl_realisasi1' : 'tgl_realisasi';
        $totalTunggakanExpression = collect($tunggakanColumns)
            ->map(fn (string $column): string => "COALESCE(`{$column}`, 0)")
            ->implode(' + ');
        $kolekCast = DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(kolek AS INTEGER)'
            : 'CAST(kolek AS UNSIGNED)';
        $accountColumn = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? 'nomor_rekening1'
            : $this->resolveIdentityColumn('daily_loan_dinamis');

        $query = DB::table('daily_loan_dinamis')
            ->select('daily_loan_dinamis.*')
            ->selectRaw("({$totalTunggakanExpression}) as total_tunggakan")
            ->where('periode', $selectedPeriod)
            ->whereBetween($realisasiColumn, [$targetStart, $targetEnd])
            ->whereRaw("{$kolekCast} >= 2")
            ->whereRaw("({$totalTunggakanExpression}) > 0")
            ->when($selectedBranches !== [], fn (Builder $query) => $query->whereIn('cabang1', $selectedBranches))
            ->when($selectedUnits !== [], fn (Builder $query) => $query->whereIn('unit1', $selectedUnits))
            ->orderBy('cabang1')
            ->orderBy('unit1')
            ->orderBy($accountColumn);

        $rows = [];
        $excluded = array_diff_key(array_flip($this->dailyLoanOutputExcludedColumns(['created_at', 'updated_at', 'uniqueid_namareport'])), []);
        foreach ($query->cursor() as $row) {
            $rowData = array_diff_key((array) $row, $excluded);
            $rowData['bulan_realisasi_target'] = $this->sixMonthArrearsTargetMonthLabel($selectedPeriod, $months);
            $rowData['tgl_realisasi'] = $rowData[$realisasiColumn] ?? ($rowData['tgl_realisasi'] ?? '');
            $rowData['kolek_detail'] = $this->kolekDetailFromFormula($row->kolek ?? null, $row->flag_restruk ?? null, $row->umur_tunggakan ?? null);
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function collectSixMonthArrearsExportColumns(): array
    {
        $columns = $this->filterDailyLoanOutputColumns(
            Schema::getColumnListing('daily_loan_dinamis'),
            ['created_at', 'updated_at', 'uniqueid_namareport']
        );

        $columns[] = 'total_tunggakan';
        $columns[] = 'bulan_realisasi_target';
        $columns[] = 'kolek_detail';

        return $columns;
    }

    private function sixMonthArrearsTargetRange(string $selectedPeriod, int $months = 6): array
    {
        $selectedDate = Carbon::parse($selectedPeriod);
        $targetStartMonth = $selectedDate->copy()->subMonthsNoOverflow($months);

        return [
            $targetStartMonth->copy()->startOfMonth()->toDateString(),
            $selectedDate->copy()->toDateString(),
        ];
    }

    private function sixMonthArrearsTargetMonthLabel(string $selectedPeriod, int $months = 6): string
    {
        [$targetStart, $targetEnd] = $this->sixMonthArrearsTargetRange($selectedPeriod, $months);

        return Carbon::parse($targetStart)->translatedFormat('F Y')
            . ' - '
            . Carbon::parse($targetEnd)->translatedFormat('F Y');
    }

    private function kolekDetailFromFormula($kolekValue, $flagRestruk, $umurTunggakanValue): string
    {
        $kolek = $this->normalizeKolekValue($kolekValue);
        $umur = $this->normalizeUmurTunggakanValue($umurTunggakanValue) ?? 0;
        $isRestruk = strtoupper(trim((string) $flagRestruk)) === 'Y';

        return match ($kolek) {
            1 => $isRestruk ? 'LR' : 'L',
            2 => $umur < 31 ? 'SML 1' : ($umur < 61 ? 'SML 2' : 'SML 3'),
            3 => 'KL',
            4 => $umur < 150 ? 'D1' : 'D2',
            default => 'M',
        };
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

    private function accountKeySql(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LTRIM(TRIM(COALESCE({$column}, '')), '0')";
        }

        return "TRIM(LEADING '0' FROM TRIM(COALESCE({$column}, '')))";
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
