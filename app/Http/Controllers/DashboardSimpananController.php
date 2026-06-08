<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureDashboardSimpananSnapshotJob;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\SimpananMultiPnSnapshotGate;
use App\Support\DashboardDanaService;
use App\Support\ReportCacheVersion;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Http\Request;
use Throwable;

class DashboardSimpananController extends Controller
{
    private const PAYLOAD_CACHE_MINUTES = 1440;
    private const SUMMARY_CACHE_MINUTES = 1440;
    private const SUMMARY_LATEST_CACHE_MINUTES = 1440;
    private const TOP_BRANCH_CACHE_MINUTES = 1440;
    private const DIGITAL_PERFORMANCE_CACHE_MINUTES = 1440;
    private const LOAN_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const LANDING_SOURCE_CACHE_VERSION = 'harian_snapshot_v13';
    private const CACHE_LOCK_SECONDS = 20;
    private const SNAPSHOT_SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const SNAPSHOT_BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const AREA_6_BRANCH_LABELS = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private array $snapshotExistsMemo = [];
    private array $snapshotPeriodMemo = [];

    private static array $hasTableMemo = [];
    private static array $hasColumnMemo = [];

    private function hasTable(string $table): bool
    {
        if (!isset(self::$hasTableMemo[$table])) {
            self::$hasTableMemo[$table] = \Illuminate\Support\Facades\Schema::hasTable($table);
        }
        return self::$hasTableMemo[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!isset(self::$hasColumnMemo[$key])) {
            self::$hasColumnMemo[$key] = \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        }
        return self::$hasColumnMemo[$key];
    }

    public function index(Request $request): View
    {
        $periodsService = app(\App\Support\DashboardHarianSnapshotService::class);
        $availablePeriods = $periodsService->fetchPeriods();
        
        $selectedPeriod = $request->input('periode');
        if ($selectedPeriod && !$availablePeriods->contains($selectedPeriod)) {
            $selectedPeriod = $availablePeriods->first();
        } else {
            $selectedPeriod ??= $availablePeriods->first();
        }

        $dashboard = $this->buildDashboardPayload($selectedPeriod);

        return view('dashboard', [
            'dashboard' => $dashboard,
            'periods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
        ]);
    }

    public function dashboardDanaIndex(Request $request): View
    {
        $service = app(DashboardDanaService::class);
        $periods = $service->fetchPeriods();
        $categories = $service->fetchCategories();
        $rkaPeriods = $service->fetchRkaPeriods();

        $selectedPeriod = $request->input('periode') ?? $periods->first();
        $selectedCategory = $request->input('kategori') ?? 'all';
        $selectedRka = $request->input('rka_periode') ?? $rkaPeriods->first();

        return view('report.dashboard-dana', [
            'periods' => $periods,
            'categories' => $categories,
            'rkaPeriods' => $rkaPeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedCategory' => $selectedCategory,
            'selectedRka' => $selectedRka,
        ]);
    }

    public function dashboardDanaData(Request $request)
    {
        $service = app(DashboardDanaService::class);
        $period = $request->input('periode');
        $category = $request->input('kategori');
        $rkaPeriod = $request->input('rka_periode');

        $data = $service->getDashboardData($period, $category, $rkaPeriod);

        return response()->json($data);
    }

    public function presentationData(Request $request)
    {
        $period = $this->resolvePresentationPeriod($request->query('periode'));
        $payload = $this->cachedPresentationPayload($period);

        if ($request->boolean('warm')) {
            return response()->noContent();
        }

        return response()->json($payload);
    }

    public function presentationKtsData(Request $request)
    {
        $requestedPeriod = trim((string) $request->query('periode'));
        $period = $this->resolveArea6DailyLoanPeriod($requestedPeriod !== '' ? $requestedPeriod : null);

        return response()->json([
            'kts' => $this->cachedPresentationKtsPayload($period),
        ]);
    }

    public function area6Data(Request $request)
    {
        $selectedPeriod = $request->query('periode');
        $loanPeriods = $this->resolveLoanDashboardPeriods($selectedPeriod ?: null);
        $loanPeriod = $loanPeriods[0] ?? null;

        $area6Portfolio = $this->buildArea6PortfolioLanding($loanPeriod);

        return response()->json([
            'area6_portfolio' => $area6Portfolio,
        ]);
    }

    public function presentation(Request $request)
    {
        $periods = app(DashboardHarianSnapshotService::class)->fetchPeriods();
        $period = $this->resolvePresentationPeriod($request->query('periode'), $periods);
        $payload = $this->cachedPresentationPayloadIfAvailable($period);

        return view('presentation', [
            'selectedPeriod' => $period,
            'periods' => $periods,
            'presentationPayload' => $payload,
        ]);
    }

    private function cachedPresentationPayload(?string $period): array
    {
        $cacheKey = $this->presentationPayloadCacheKey($period);

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period) {
            return $this->buildPresentationPayload($period);
        });
    }

    private function cachedPresentationPayloadIfAvailable(?string $period): ?array
    {
        $cacheKey = $this->presentationPayloadCacheKey($period);

        return Cache::has($cacheKey)
            ? Cache::get($cacheKey)
            : null;
    }

    private function cachedPresentationKtsPayload(?string $period): array
    {
        $cacheKey = 'dashboard_simpanan:presentation_kts_payload:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . ($period ?? 'null');

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period) {
            return $this->buildPresentationKts([], $period);
        });
    }

    private function presentationPayloadCacheKey(?string $period): string
    {
        return 'dashboard_simpanan:presentation_payload:'
            . ($period ?? 'null') . ':'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':lazy_kts_v1';
    }

    private function resolvePresentationPeriod(mixed $requestedPeriod, ?Collection $periods = null): ?string
    {
        $requested = trim((string) $requestedPeriod);
        $periods ??= app(DashboardHarianSnapshotService::class)->fetchPeriods();

        if ($requested !== '' && $periods->contains($requested)) {
            return $requested;
        }

        return $periods->first()
            ?: (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)
                ? DB::table(self::HARIAN_SNAPSHOT_TABLE)->max('snapshot_period')
                : null);
    }

    private function buildPresentationPayload(?string $selectedPeriod): array
    {
        $dashboard = $this->buildDashboardPayload($selectedPeriod);
        $dashboardPeriod = (string) data_get($dashboard, 'period', $selectedPeriod);
        $loanPeriods = $this->resolveLoanDashboardPeriods($dashboardPeriod ?: $selectedPeriod);
        $loanPeriod = $loanPeriods[0] ?? null;
        $dailyLoanPeriod = $this->resolveArea6DailyLoanPeriod($loanPeriod ?? $dashboardPeriod ?: null);
        $area6Portfolio = data_get($dashboard, 'area6_portfolio', []);
        $digitalPerformance = data_get($dashboard, 'digital_performance', []);

        return [
            'meta' => [
                'title' => 'Area 6 - Region Malang',
                'subtitle' => 'Materi Pendukung Asistensi',
                'period' => $dashboardPeriod ?: null,
                'period_label' => $this->formatPeriodLabel($dashboardPeriod ?: null),
                'loan_period' => $loanPeriod,
                'loan_period_label' => $this->formatPeriodLabel($loanPeriod),
                'daily_loan_period' => $dailyLoanPeriod,
                'daily_loan_period_label' => $this->formatPeriodLabel($dailyLoanPeriod),
                'generated_at' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d H:i:s'),
                'source_note' => 'Angka diambil dari payload landing dan tabel snapshot/report existing; tidak memakai angka dummy.',
            ],
            'assets' => $this->buildPresentationAssets(),
            'summary' => $this->buildPresentationSummary($dashboard, $dashboardPeriod ?: null, $loanPeriod),
            'performance_overview' => $this->buildPresentationPerformanceOverview($area6Portfolio, $dashboardPeriod ?: null),
            'timeseries' => $this->buildPresentationTimeseries($dashboardPeriod ?: null),
            'cover_card_timeseries' => $this->buildPresentationCoverCardTimeseries($dashboardPeriod ?: null, $dailyLoanPeriod),
            'micro' => $this->buildPresentationMicro($dailyLoanPeriod),
            'quality' => $this->buildPresentationQuality($area6Portfolio),
            'kts' => $this->buildPresentationKtsSummary($dailyLoanPeriod),
            'digital_strategy' => $this->buildPresentationDigitalStrategy($digitalPerformance),
        ];
    }

    private function buildPresentationAssets(): array
    {
        return [
            'bri_logo' => asset('images/bri-logo-template.png'),
            'danantara_logo' => asset('images/danantara-logo-template.png'),
            'cover_base' => asset('images/ppt-template/cover-base.png'),
        ];
    }

    private function buildPresentationSummary(array $dashboard, ?string $period, ?string $loanPeriod): array
    {
        $liveReports = collect(data_get($dashboard, 'live_reports', []));
        $simpananReport = $liveReports->firstWhere('key', 'simpanan') ?? [];
        $pinjamanReport = $liveReports->firstWhere('key', 'pinjaman') ?? [];
        $portfolioReport = $liveReports->firstWhere('key', 'portfolio') ?? [];
        $area6Metrics = $period ? $this->area6ScopeSnapshotMetrics($period, 'cabang_konsol') : (object) [];
        $harianAvailable = $period && Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)
            ? $this->area6HarianSnapshotSummaryQuery()
                ->where('snapshot_period', $period)
                ->exists()
            : false;
        $simpananSummary = $period ? $this->buildPeriodSummary($period) : $this->emptySummary();
        $loanSummary = $loanPeriod ? $this->buildLoanSummary($loanPeriod) : $this->emptyLoanSummary();
        $simpananRaw = (float) ($simpananSummary['total_balance'] ?? 0);
        $loanRaw = (float) ($loanSummary['total_balance'] ?? 0);
        $osRaw = (float) ($area6Metrics->total_os_non_commercial ?? 0.0);
        $smlRaw = (float) ($area6Metrics->total_sml_abs_non_commercial ?? 0.0);
        $nplRaw = (float) ($area6Metrics->total_npl_abs_non_commercial ?? 0.0);
        $smlRatio = $this->percentOf($smlRaw, $osRaw);
        $nplRatio = $this->percentOf($nplRaw, $osRaw);
        $unavailable = 'Data belum tersedia';

        return [
            'cards' => [
                [
                    'key' => 'simpanan',
                    'label' => 'Total Simpanan',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? data_get($simpananReport, 'value', $this->formatCurrencyCompact($simpananRaw)) : $unavailable,
                    'value_raw' => $harianAvailable ? $simpananRaw : null,
                    'trend' => data_get($simpananReport, 'trend', '0,00%'),
                    'meta' => data_get($simpananReport, 'meta', '-'),
                    'source' => data_get($simpananReport, 'detail_payload.source_table', self::HARIAN_SNAPSHOT_TABLE),
                ],
                [
                    'key' => 'os',
                    'label' => 'Total OS Non Commercial',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($osRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $osRaw : null,
                    'trend' => data_get($pinjamanReport, 'trend', '0,00%'),
                    'meta' => 'OS Area 6 Cabang Konsol',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
                [
                    'key' => 'ldr',
                    'label' => 'LDR',
                    'available' => $harianAvailable && $simpananRaw > 0,
                    'value' => $harianAvailable && $simpananRaw > 0
                        ? data_get($portfolioReport, 'value', $this->formatRatio($loanRaw, $simpananRaw))
                        : $unavailable,
                    'value_raw' => $harianAvailable && $simpananRaw > 0 ? $loanRaw / $simpananRaw : null,
                    'trend' => data_get($portfolioReport, 'trend', '0,00%'),
                    'meta' => data_get($portfolioReport, 'meta', '-'),
                    'source' => 'Landing LDR',
                ],
                [
                    'key' => 'sml',
                    'label' => 'SML',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($smlRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $smlRaw : null,
                    'ratio' => $harianAvailable ? $this->formatPercentTwo($smlRatio) : $unavailable,
                    'ratio_raw' => $harianAvailable ? $smlRatio : null,
                    'meta' => 'Nominal dan rasio SML',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
                [
                    'key' => 'npl',
                    'label' => 'NPL',
                    'available' => $harianAvailable,
                    'value' => $harianAvailable ? $this->formatCurrencyCompact($nplRaw) : $unavailable,
                    'value_raw' => $harianAvailable ? $nplRaw : null,
                    'ratio' => $harianAvailable ? $this->formatPercentTwo($nplRatio) : $unavailable,
                    'ratio_raw' => $harianAvailable ? $nplRatio : null,
                    'meta' => 'Nominal dan rasio NPL',
                    'source' => self::HARIAN_SNAPSHOT_TABLE,
                ],
            ],
            'highlights' => [
                $harianAvailable ? data_get($simpananReport, 'detail', 'Simpanan mengikuti snapshot landing.') : $unavailable,
                $harianAvailable ? data_get($pinjamanReport, 'detail', 'Pinjaman mengikuti snapshot landing.') : $unavailable,
                $harianAvailable ? 'SML ' . $this->formatCurrencyCompact($smlRaw) . ' (' . $this->formatPercentTwo($smlRatio) . ')' : $unavailable,
                $harianAvailable ? 'NPL ' . $this->formatCurrencyCompact($nplRaw) . ' (' . $this->formatPercentTwo($nplRatio) . ')' : $unavailable,
            ],
            'composition_dpk' => [
                'tabungan_pct' => $simpananRaw > 0 ? (($simpananSummary['tabungan_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
                'giro_pct' => $simpananRaw > 0 ? (($simpananSummary['giro_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
                'other_pct' => $simpananRaw > 0 ? (($simpananSummary['other_balance'] ?? 0) / $simpananRaw) * 100 : 0.0,
            ]
        ];
    }

    private function buildPresentationPerformanceOverview(array $area6Portfolio, ?string $period = null): array
    {
        $scope = data_get($area6Portfolio, 'scopes.cabang_konsol', $area6Portfolio);

        return [
            'period_label' => data_get($area6Portfolio, 'period_label', '-'),
            'rka_month_year' => data_get($scope, 'segment_performance.rka_month_year', null),
            'segments' => data_get($scope, 'segment_performance.segments', []),
            'total' => data_get($scope, 'segment_performance.total', []),
            'composition' => data_get($scope, 'segment_performance.composition', []),
            'branches' => array_values(data_get($area6Portfolio, 'ranking_modes.cabang_konsol.branches', [])),
            'scope_cards' => data_get($area6Portfolio, 'scopes', []),
            'matrix' => $this->buildPresentationPerformanceMatrix($period),
        ];
    }

    private function buildPresentationPerformanceMatrix(?string $period): array
    {
        $empty = [
            'available' => false,
            'unit' => 'Rupiah',
            'periods' => [],
            'metrics' => [
                'simpanan' => ['label' => 'Simpanan', 'tone' => 'blue'],
                'os' => ['label' => 'OS', 'tone' => 'green'],
                'sml' => ['label' => 'SML', 'tone' => 'amber'],
                'npl' => ['label' => 'NPL', 'tone' => 'red'],
            ],
            'scope_options' => [],
            'rows' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $comparisonPeriods = $this->buildPresentationComparisonPeriods($period);
        $periodValues = array_values(array_filter(array_column($comparisonPeriods, 'period')));
        if (empty($periodValues)) {
            return $empty;
        }

        $branchRows = $this->fetchPresentationMatrixRows($periodValues, true);
        $unitRows = $this->fetchPresentationMatrixRows($periodValues, false);
        $currentPeriod = Carbon::parse($period)->toDateString();

        $rows = [
            'area6' => $this->formatPresentationMatrixRows($branchRows, $comparisonPeriods, $currentPeriod, true),
        ];

        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $branchKey = strtoupper(trim($branchName));
            $branchUnitRows = $unitRows->filter(function ($row) use ($branchKey): bool {
                return strtoupper(trim((string) ($row->branch_label ?? ''))) === $branchKey;
            });

            $rows[$branchKey] = $this->formatPresentationMatrixRows($branchUnitRows, $comparisonPeriods, $currentPeriod, false);
        }

        return [
            'available' => $branchRows->isNotEmpty() || $unitRows->isNotEmpty(),
            'unit' => 'Rupiah',
            'periods' => $comparisonPeriods,
            'metrics' => $empty['metrics'],
            'scope_options' => array_merge(
                [['key' => 'area6', 'label' => 'Area 6 Konsol']],
                collect(self::AREA_6_BRANCH_LABELS)
                    ->map(fn (string $branch): array => [
                        'key' => strtoupper(trim($branch)),
                        'label' => $branch,
                    ])
                    ->all()
            ),
            'rows' => $rows,
        ];
    }

    private function buildPresentationComparisonPeriods(string $period): array
    {
        $current = Carbon::parse($period)->toDateString();
        $endOfPreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $sameDatePreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->toDateString();
        $prevYearEnd = Carbon::parse($current)->subYear()->endOfYear()->toDateString();

        $ytdPeriod = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', '<=', $prevYearEnd)
            ->orderBy('snapshot_period', 'desc')
            ->value('snapshot_period') ?: $prevYearEnd;

        $mtmPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($sameDatePreviousMonth) ?: $sameDatePreviousMonth;
        $mtdPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($endOfPreviousMonth) ?: $endOfPreviousMonth;

        return [
            'ytd' => [
                'key' => 'ytd',
                'period' => Carbon::parse($ytdPeriod)->toDateString(),
                'label' => 'YtD',
                'display' => Carbon::parse($ytdPeriod)->translatedFormat('d M y'),
            ],
            'mtm' => [
                'key' => 'mtm',
                'period' => Carbon::parse($mtmPeriod)->toDateString(),
                'label' => 'MtM',
                'display' => Carbon::parse($mtmPeriod)->translatedFormat('d M y'),
            ],
            'mtd' => [
                'key' => 'mtd',
                'period' => Carbon::parse($mtdPeriod)->toDateString(),
                'label' => 'MtD',
                'display' => Carbon::parse($mtdPeriod)->translatedFormat('d M y'),
            ],
            'current' => [
                'key' => 'current',
                'period' => $current,
                'label' => 'Posisi',
                'display' => Carbon::parse($current)->translatedFormat('d M y'),
            ],
        ];
    }

    private function fetchPresentationMatrixRows(array $periods, bool $summaryRows): Collection
    {
        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = $summaryRows
            ? $branchLabelExpression
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
                ? 'unit_label'
                : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''"));

        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn(DB::raw('UPPER(TRIM(' . $branchLabelExpression . '))'), $this->dashboardBranchNames());

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        $query
            ->selectRaw('snapshot_period')
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_simpanan')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_os')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(sme_os, 0)), 0) as sme_os')
            ->selectRaw('COALESCE(SUM(COALESCE(consumer_os, 0)), 0) as consumer_os')
            ->selectRaw('COALESCE(SUM(COALESCE(micro_os, 0)), 0) as micro_os')
            ->groupBy('snapshot_period');

        // GROUP BY must use the raw column name (not a COALESCE expression) to satisfy
        // MySQL ONLY_FULL_GROUP_BY mode. When the expression is a literal string (e.g. "''")
        // we skip grouping on it — there is only one distinct value so it's not needed.
        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }
        if ($unitLabelExpression !== "''" && $unitLabelExpression !== $branchLabelExpression) {
            $query->groupBy($unitLabelExpression);
        }

        return $query->get();
    }

    private function formatPresentationMatrixRows(Collection $rows, array $comparisonPeriods, string $currentPeriod, bool $summaryRows): array
    {
        $grouped = $rows->groupBy(function ($row): string {
            return strtoupper(trim((string) ($row->branch_label ?? ''))) . '|'
                . strtoupper(trim((string) ($row->unit_label ?? '')));
        });

        $rkaService = null;
        $rkaYear = null;
        $monthColumn = null;
        $definitions = [
            'simpanan'     => ['mata_anggaran' => ['A.1. DPK Retail Funding Total', 'A.2. DPK Korporasi']],
            'os'           => ['mata_anggaran' => ['B. KREDIT TOTAL']],
            'sme_os'       => ['mata_anggaran' => ['B.2. SMALL', 'B.3. MEDIUM']],
            'consumer_os'  => ['mata_anggaran' => ['B.4. KONSUMER']],
            'micro_os'     => ['mata_anggaran' => ['B.1. MIKRO']],
        ];
        try {
            $rkaService = app(\App\Support\RkaLookupService::class);
            $rkaYear = (int) Carbon::parse($currentPeriod)->format('Y');
            $monthColumn = $rkaService->resolveMonthColumn(Carbon::parse($currentPeriod));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to initialize RkaLookupService in formatPresentationMatrixRows: ' . $e->getMessage());
        }

        return $grouped
            ->map(function (Collection $periodRows) use ($comparisonPeriods, $currentPeriod, $summaryRows, $rkaService, $rkaYear, $monthColumn, $definitions): ?array {
                $indexed = $periodRows->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());
                $current = $indexed->get($currentPeriod);
                if (!$current) {
                    return null;
                }

                $metricValues = [
                    'simpanan'    => fn ($row): float => (float) ($row->total_simpanan ?? 0.0),
                    'os'          => fn ($row): float => (float) ($row->total_os ?? 0.0),
                    'sml'         => fn ($row): float => (float) ($row->sml_abs ?? 0.0),
                    'npl'         => fn ($row): float => (float) ($row->npl_abs ?? 0.0),
                    'sme_os'      => fn ($row): float => (float) ($row->sme_os ?? 0.0),
                    'consumer_os' => fn ($row): float => (float) ($row->consumer_os ?? 0.0),
                    'micro_os'    => fn ($row): float => (float) ($row->micro_os ?? 0.0),
                ];

                $rkaValues = [];
                if ($rkaService && $monthColumn) {
                    $kanca = (string) ($current->branch_label ?? '');
                    $unit = $summaryRows ? null : (string) ($current->unit_label ?? '');
                    try {
                        $rkaValues = $rkaService->aggregateForScope($definitions, $monthColumn, $kanca, $unit, $rkaYear);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('RkaLookupService aggregateForScope failed in formatPresentationMatrixRows: ' . $e->getMessage());
                    }
                }

                $metrics = [];
                foreach ($metricValues as $metricKey => $resolver) {
                    $latest = $resolver($current);
                    $baselineValues = [];
                    foreach ($comparisonPeriods as $periodInfo) {
                        $row = $indexed->get((string) $periodInfo['period']);
                        $baselineValues[$periodInfo['key']] = $row ? $resolver($row) : 0.0;
                    }

                    $osNonCommercial = (float) ($current->total_os_non_commercial ?? 0.0);
                    $ratio = in_array($metricKey, ['sml', 'npl'], true) && $osNonCommercial > 0
                        ? ($latest / $osNonCommercial) * 100
                        : null;

                    $metricData = [
                        'latest_raw' => $latest,
                        'latest' => $this->formatCurrencyCompact($latest),
                        'ytd_raw' => $latest - ($baselineValues['ytd'] ?? 0.0),
                        'ytd' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['ytd'] ?? 0.0)),
                        'mtm_raw' => $latest - ($baselineValues['mtm'] ?? 0.0),
                        'mtm' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['mtm'] ?? 0.0)),
                        'mtd_raw' => $latest - ($baselineValues['mtd'] ?? 0.0),
                        'mtd' => $this->formatPresentationMatrixDelta($latest - ($baselineValues['mtd'] ?? 0.0)),
                        'series' => [
                            round(($baselineValues['ytd'] ?? 0.0) / 1000000),
                            round(($baselineValues['mtm'] ?? 0.0) / 1000000),
                            round(($baselineValues['mtd'] ?? 0.0) / 1000000),
                            round($latest / 1000000),
                        ],
                        'ratio_raw' => $ratio,
                        'ratio' => $ratio === null ? null : $this->formatPercentTwo($ratio),
                    ];

                    if (in_array($metricKey, ['simpanan', 'os', 'sme_os', 'consumer_os', 'micro_os'], true)) {
                        $targetVal = (float) ($rkaValues[$metricKey] ?? 0.0);
                        if ($targetVal > 0.0) {
                            $gap = $latest - $targetVal;
                            $penc = ($latest / $targetVal) * 100;

                            $metricData['rka_raw'] = $targetVal;
                            $metricData['rka_fmt'] = $this->formatCurrencyCompact($targetVal);

                            $gapDelta = $this->formatPresentationMatrixDelta($gap);
                            $metricData['gap_raw'] = $gap;
                            $metricData['gap_fmt'] = $gapDelta['value'];
                            $metricData['gap_class'] = $gapDelta['class'];

                            $metricData['penc_raw'] = $penc;
                            $metricData['penc_fmt'] = $this->formatPercentTwo($penc);
                        } else {
                            $metricData['rka_raw'] = 0.0;
                            $metricData['rka_fmt'] = '-';

                            $metricData['gap_raw'] = 0.0;
                            $metricData['gap_fmt'] = '-';
                            $metricData['gap_class'] = '';

                            $metricData['penc_raw'] = 0.0;
                            $metricData['penc_fmt'] = '-';
                        }
                    }

                    $metrics[$metricKey] = $metricData;
                }

                return [
                    'label' => $summaryRows ? (string) ($current->branch_label ?? '-') : (string) ($current->unit_label ?? '-'),
                    'branch' => (string) ($current->branch_label ?? '-'),
                    'type' => $summaryRows ? 'Cabang Konsol' : 'Unit',
                    'metrics' => $metrics,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row): float => (float) data_get($row, 'metrics.simpanan.latest_raw', 0.0))
            ->values()
            ->all();
    }

    private function formatPresentationMatrixDelta(float $delta): array
    {
        $prefix = $delta >= 0 ? '+' : '-';
        $class = $delta >= 0 ? 'pos' : 'neg';

        return [
            'value' => $prefix . $this->formatCurrencyCompact(abs($delta)),
            'class' => $class,
        ];
    }

    private function buildPresentationTimeseries(?string $period): array
    {
        $empty = [
            'available' => false,
            'source' => self::HARIAN_SNAPSHOT_TABLE,
            'unit' => 'Rp Juta',
            'labels' => [],
            'series' => [],
        ];

        if (!$period || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $comp = $this->buildPresentationComparisonPeriods($period);
        $resolvedPeriods = [
            $comp['ytd']['period'],
            $comp['mtm']['period'],
            $comp['mtd']['period'],
            $comp['current']['period']
        ];
        usort($resolvedPeriods, function ($a, $b) {
            return strcmp($a, $b);
        });
        $resolvedPeriods = array_values(array_unique($resolvedPeriods));

        $rows = $this->area6HarianSnapshotSummaryQuery()
            ->whereIn('snapshot_period', $resolvedPeriods)
            ->selectRaw('snapshot_period')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as simpanan_total')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as os_total')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_nominal')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_nominal')
            ->groupBy('snapshot_period')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

        $labels = [];
        $series = [
            'simpanan_total' => ['key' => 'simpanan_total', 'label' => 'Realisasi Simpanan Total', 'values' => [], 'display_values' => []],
            'os_total' => ['key' => 'os_total', 'label' => 'Realisasi OS Total', 'values' => [], 'display_values' => []],
            'sml_nominal' => ['key' => 'sml_nominal', 'label' => 'Realisasi SML', 'values' => [], 'display_values' => []],
            'npl_nominal' => ['key' => 'npl_nominal', 'label' => 'Realisasi NPL', 'values' => [], 'display_values' => []],
        ];

        foreach ($resolvedPeriods as $resolvedPeriod) {
            $date = Carbon::parse($resolvedPeriod)->toDateString();
            $row = $rows->get($date);
            if (!$row) {
                continue;
            }

            // Find matching prefix
            $prefix = 'Posisi';
            if ($date === $comp['ytd']['period']) {
                $prefix = 'YtD';
            } elseif ($date === $comp['mtm']['period']) {
                $prefix = 'MtM';
            } elseif ($date === $comp['mtd']['period']) {
                $prefix = 'MtD';
            }

            $labels[] = $prefix . ' (' . Carbon::parse($date)->translatedFormat('d M y') . ')';

            foreach (array_keys($series) as $key) {
                $raw = (float) ($row->{$key} ?? 0.0);
                $series[$key]['values'][] = round($raw / 1000000);
                $series[$key]['display_values'][] = $this->formatCurrencyCompact($raw);
            }
        }

        return [
            'available' => $rows->isNotEmpty(),
            'source' => self::HARIAN_SNAPSHOT_TABLE,
            'unit' => 'Rp Juta',
            'labels' => $labels,
            'series' => array_values($series),
        ];
    }

    private function buildPresentationCoverCardTimeseries(?string $period, ?string $dailyLoanPeriod): array
    {
        $pointKeys = ['ytd', 'mtm', 'mtd', 'current'];
        $defaultPeriods = [
            'ytd' => ['key' => 'ytd', 'label' => 'YtD', 'period' => null, 'display' => '-'],
            'mtm' => ['key' => 'mtm', 'label' => 'MtM', 'period' => null, 'display' => '-'],
            'mtd' => ['key' => 'mtd', 'label' => 'MtD', 'period' => null, 'display' => '-'],
            'current' => ['key' => 'current', 'label' => 'Posisi', 'period' => null, 'display' => '-'],
        ];
        $snapshotPeriods = $defaultPeriods;

        $cards = [];
        $emptyFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : $this->formatCurrencyCompact($value);
        $ratioFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : number_format($value, 2, ',', '.') . 'x';
        $integerFormatter = fn (?float $value): string => $value === null ? 'Data belum tersedia' : $this->formatInteger((int) $value);

        $makeCard = function (
            string $key,
            string $label,
            string $unit,
            string $tone,
            array $periods,
            callable $resolver,
            callable $formatter,
            ?string $meta = null
        ) use ($pointKeys): array {
            $points = [];

            foreach ($pointKeys as $pointKey) {
                $periodInfo = $periods[$pointKey] ?? ['key' => $pointKey, 'label' => strtoupper($pointKey), 'period' => null, 'display' => '-'];
                $value = $periodInfo['period'] ? $resolver((string) $periodInfo['period']) : null;
                $points[] = [
                    'key' => $pointKey,
                    'label' => (string) ($periodInfo['label'] ?? strtoupper($pointKey)),
                    'period' => $periodInfo['period'] ?? null,
                    'period_label' => (string) ($periodInfo['display'] ?? '-'),
                    'value' => $value,
                    'display_value' => $formatter($value),
                ];
            }

            return [
                'key' => $key,
                'label' => $label,
                'unit' => $unit,
                'tone' => $tone,
                'meta' => $meta,
                'available' => collect($points)->contains(fn (array $point): bool => $point['value'] !== null),
                'points' => $points,
            ];
        };

        if ($period && Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            $snapshotPeriods = $this->buildPresentationComparisonPeriods($period);
            $periodValues = array_values(array_unique(array_filter(array_map(
                fn (string $key): ?string => $snapshotPeriods[$key]['period'] ?? null,
                $pointKeys
            ))));

            $totalRows = $this->area6HarianSnapshotSummaryQuery()
                ->whereIn('snapshot_period', $periodValues)
                ->selectRaw('snapshot_period')
                ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as simpanan_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as os_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as os_non_commercial_total')
                ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_nominal')
                ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_nominal')
                ->groupBy('snapshot_period')
                ->get()
                ->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

            $branchRows = $this->fetchPresentationMatrixRows($periodValues, true);
            $currentPeriod = (string) ($snapshotPeriods['current']['period'] ?? '');
            $currentBranchRows = $branchRows->filter(fn ($row): bool => Carbon::parse($row->snapshot_period)->toDateString() === $currentPeriod);
            $topSimpananBranch = $currentBranchRows->sortByDesc(fn ($row): float => (float) ($row->total_simpanan ?? 0))->first();
            $topOsBranch = $currentBranchRows->sortByDesc(fn ($row): float => (float) ($row->total_os ?? 0))->first();
            $branchGroups = $branchRows->groupBy(fn ($row): string => strtoupper(trim((string) ($row->branch_label ?? ''))));

            $branchResolver = function ($branchRow, string $column) use ($branchGroups): callable {
                $branchKey = strtoupper(trim((string) ($branchRow->branch_label ?? '')));
                $rows = $branchGroups->get($branchKey, collect())->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

                return fn (string $pointPeriod): ?float => $rows->has($pointPeriod)
                    ? (float) ($rows->get($pointPeriod)->{$column} ?? 0.0)
                    : null;
            };

            $cards['simpanan'] = $makeCard(
                'simpanan',
                'Simpanan',
                'currency',
                '#059669',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->simpanan_total ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['os'] = $makeCard(
                'os',
                'OS',
                'currency',
                '#2563eb',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->os_non_commercial_total ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['ldr'] = $makeCard(
                'ldr',
                'LDR',
                'ratio',
                '#7c3aed',
                $snapshotPeriods,
                function (string $pointPeriod) use ($totalRows): ?float {
                    if (!$totalRows->has($pointPeriod)) {
                        return null;
                    }

                    $row = $totalRows->get($pointPeriod);
                    $simpanan = (float) ($row->simpanan_total ?? 0.0);
                    if ($simpanan <= 0.0) {
                        return null;
                    }

                    return (float) ($row->os_non_commercial_total ?? 0.0) / $simpanan;
                },
                $ratioFormatter
            );

            $cards['sml'] = $makeCard(
                'sml',
                'SML',
                'currency',
                '#ea580c',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->sml_nominal ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['npl'] = $makeCard(
                'npl',
                'NPL',
                'currency',
                '#dc2626',
                $snapshotPeriods,
                fn (string $pointPeriod): ?float => $totalRows->has($pointPeriod) ? (float) ($totalRows->get($pointPeriod)->npl_nominal ?? 0.0) : null,
                $emptyFormatter
            );

            $cards['top_simpanan'] = $makeCard(
                'top_simpanan',
                'Top Simpanan',
                'currency',
                '#d97706',
                $snapshotPeriods,
                $topSimpananBranch ? $branchResolver($topSimpananBranch, 'total_simpanan') : fn (string $pointPeriod): ?float => null,
                $emptyFormatter,
                $topSimpananBranch ? (string) ($topSimpananBranch->branch_label ?? '') : null
            );

            $cards['top_os'] = $makeCard(
                'top_os',
                'Top OS',
                'currency',
                '#0891b2',
                $snapshotPeriods,
                $topOsBranch ? $branchResolver($topOsBranch, 'total_os') : fn (string $pointPeriod): ?float => null,
                $emptyFormatter,
                $topOsBranch ? (string) ($topOsBranch->branch_label ?? '') : null
            );
        }

        $ktsPeriods = $dailyLoanPeriod ? $this->buildPresentationDailyLoanComparisonPeriods($dailyLoanPeriod) : $defaultPeriods;
        $cards['kts_membaik'] = $makeCard(
            'kts_membaik',
            'KTS Membaik',
            'rekening',
            '#10b981',
            $ktsPeriods ?: $defaultPeriods,
            fn (string $pointPeriod): ?float => null,
            $integerFormatter,
            'Ritel + Micro'
        );
        $cards['kts_memburuk'] = $makeCard(
            'kts_memburuk',
            'KTS Memburuk',
            'rekening',
            '#ef4444',
            $ktsPeriods ?: $defaultPeriods,
            fn (string $pointPeriod): ?float => null,
            $integerFormatter,
            'Ritel + Micro'
        );

        return [
            'source' => [
                'harian' => self::HARIAN_SNAPSHOT_TABLE,
                'kts' => 'daily_loan_dinamis',
            ],
            'periods' => $snapshotPeriods,
            'cards' => $cards,
        ];
    }

    private function buildPresentationDailyLoanComparisonPeriods(?string $period): array
    {
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        $current = Carbon::parse($period)->toDateString();
        $endOfPreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $sameDatePreviousMonth = Carbon::parse($current)->subMonthNoOverflow()->toDateString();
        $prevYearEnd = Carbon::parse($current)->subYear()->endOfYear()->toDateString();

        $periods = [
            'ytd' => $this->resolveArea6DailyLoanPeriod($prevYearEnd),
            'mtm' => $this->resolveArea6DailyLoanPeriod($sameDatePreviousMonth),
            'mtd' => $this->resolveArea6DailyLoanPeriod($endOfPreviousMonth),
            'current' => $this->resolveArea6DailyLoanPeriod($current),
        ];

        return collect($periods)->map(function (?string $resolvedPeriod, string $key): array {
            $labels = [
                'ytd' => 'YtD',
                'mtm' => 'MtM',
                'mtd' => 'MtD',
                'current' => 'Posisi',
            ];

            return [
                'key' => $key,
                'period' => $resolvedPeriod,
                'label' => $labels[$key] ?? strtoupper($key),
                'display' => $resolvedPeriod ? Carbon::parse($resolvedPeriod)->translatedFormat('d M y') : '-',
            ];
        })->all();
    }

    private function buildPresentationMicro(?string $period): array
    {
        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'decision' => $this->buildPresentationDecisionEvaluation($period),
            'mantri_productivity' => $this->buildPresentationMantriProductivity($period),
            'rm_kur_micro' => $this->buildPresentationRmKurMicro($period),
        ];
    }

    private function buildPresentationDecisionEvaluation(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('unit_pemutus', $period, true);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['mtd_total_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'kaunit_deb' => (int) ($row['kaunit_mtd_deb'] ?? 0),
                'mbm_deb' => (int) ($row['mbm_mtd_deb'] ?? 0),
                'pinca_deb' => (int) ($row['pinca_mtd_deb'] ?? 0),
                'rmbh_deb' => (int) ($row['rmbh_mtd_deb'] ?? 0),
                'total_deb' => (int) ($row['mtd_total_deb'] ?? 0),
                'total_os' => (float) ($row['mtd_total_os'] ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($row['mtd_total_os'] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja RM Mikro - Unit per Pemutus',
            'rows' => $rows,
            'total' => [
                'total_deb' => (int) data_get($payload, 'total.mtd_total_deb', 0),
                'total_os' => (float) data_get($payload, 'total.mtd_total_os', 0.0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.mtd_total_os', 0.0)),
            ],
        ];
    }

    private function buildPresentationMantriProductivity(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('produktivitas_mantri', $period, true);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['realisasi_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'nama_mantri' => (string) ($row['nama_mantri'] ?? $row['pn_pengelola'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'realisasi_deb' => (int) ($row['realisasi_deb'] ?? 0),
                'realisasi_os' => (float) ($row['realisasi_os'] ?? 0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) ($row['realisasi_os'] ?? 0)),
                'ratas_mantri_hk' => (float) ($row['ratas_mantri_hk'] ?? 0),
                'tiket_size' => (float) ($row['tiket_size'] ?? 0),
                'ket' => (string) ($row['ket'] ?? '-'),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja Mantri - Produktivitas per Mantri',
            'working_days' => (int) data_get($payload, 'working_days', 0),
            'rows' => $rows,
            'total' => [
                'jumlah_mantri' => (int) data_get($payload, 'total.jumlah_mantri', 0),
                'realisasi_deb' => (int) data_get($payload, 'total.realisasi_deb', 0),
                'realisasi_os' => (float) data_get($payload, 'total.realisasi_os', 0.0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.realisasi_os', 0.0)),
            ],
        ];
    }

    private function buildPresentationRmKurMicro(?string $period): array
    {
        $payload = $this->invokeKinerjaRmMikroPayload('per_rm', $period, false);
        $rows = collect($payload['rows'] ?? [])
            ->sortByDesc(fn (array $row) => (float) ($row['realisasi_os'] ?? 0))
            ->take(24)
            ->map(fn (array $row): array => [
                'nama' => (string) ($row['nama'] ?? $row['rm'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'total_deb' => (int) ($row['total_deb'] ?? 0),
                'total_os' => (float) ($row['total_os'] ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($row['total_os'] ?? 0)),
                'realisasi_deb' => (int) ($row['realisasi_deb'] ?? 0),
                'realisasi_os' => (float) ($row['realisasi_os'] ?? 0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) ($row['realisasi_os'] ?? 0)),
            ])
            ->values()
            ->all();

        return [
            'available' => !empty($rows),
            'source' => 'Kinerja RM Mikro - RM KUR Mikro',
            'rows' => $rows,
            'total' => [
                'total_deb' => (int) data_get($payload, 'total.total_deb', 0),
                'total_os' => (float) data_get($payload, 'total.total_os', 0.0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.total_os', 0.0)),
                'realisasi_deb' => (int) data_get($payload, 'total.realisasi_deb', 0),
                'realisasi_os' => (float) data_get($payload, 'total.realisasi_os', 0.0),
                'realisasi_os_fmt' => $this->formatCurrencyCompact((float) data_get($payload, 'total.realisasi_os', 0.0)),
            ],
        ];
    }

    private function invokeKinerjaRmMikroPayload(string $category, ?string $period, bool $mantri): array
    {
        if (!$period) {
            return ['rows' => [], 'total' => []];
        }

        $cacheKey = 'dashboard_simpanan:kinerja_rm_mikro:'
            . ($mantri ? 'mantri' : 'report') . ':'
            . $category . ':'
            . $period . ':v'
            . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($category, $period, $mantri) {
            try {
                $controller = app(\App\Http\Controllers\Report\KinerjaRmMikroReportController::class);
                $method = new \ReflectionMethod($controller, $mantri ? 'buildMantriPayload' : 'buildReportPayload');
                $method->setAccessible(true);

                return (array) $method->invoke($controller, $category, $period);
            } catch (Throwable $e) {
                Log::warning('Payload presentasi Kinerja RM Mikro gagal dibaca.', [
                    'category' => $category,
                    'period' => $period,
                    'error' => $e->getMessage(),
                ]);

                return ['rows' => [], 'total' => [], 'message' => 'Data belum tersedia'];
            }
        });
    }

    private function buildPresentationQuality(array $area6Portfolio): array
    {
        $scope = data_get($area6Portfolio, 'scopes.cabang_konsol', $area6Portfolio);
        $cards = collect(data_get($scope, 'cards', []))->keyBy('key');
        $rankingModes = data_get($area6Portfolio, 'ranking_modes', []);

        return [
            'sml' => [
                'title' => 'Kinerja SML Area 6 - Region Malang',
                'card' => $cards->get('sml', []),
                'ritel_nominal' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 SML Nominal'),
                'micro_nominal' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 SML Nominal'),
                'ritel_ratio' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 SML Rasio'),
                'micro_ratio' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 SML Rasio'),
            ],
            'npl' => [
                'title' => 'Kinerja NPL Area 6 - Region Malang',
                'card' => $cards->get('npl', []),
                'ritel_nominal' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 NPL Nominal'),
                'micro_nominal' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 NPL Nominal'),
                'ritel_ratio' => $this->extractPresentationRankingRows($rankingModes, 'ritel', '5 NPL Rasio'),
                'micro_ratio' => $this->extractPresentationRankingRows($rankingModes, 'micro', '5 NPL Rasio'),
            ],
        ];
    }

    private function buildPresentationKts(array $area6Portfolio, ?string $period): array
    {
        $ktsRetail = $this->buildArea6KtsRanking($period, 'retail');
        $ktsMicro = $this->buildArea6KtsRanking($period, 'unit');
        $categories = [
            'membaik' => [
                'label' => 'KTS Membaik',
                'ritel' => $this->buildPresentationKtsCategoryRanking($period, 'retail', 'membaik'),
                'micro' => $this->buildPresentationKtsCategoryRanking($period, 'unit', 'membaik'),
            ],
            'memburuk' => [
                'label' => 'KTS Memburuk',
                'ritel' => $this->buildPresentationKtsCategoryRanking($period, 'retail', 'memburuk'),
                'micro' => $this->buildPresentationKtsCategoryRanking($period, 'unit', 'memburuk'),
            ],
        ];

        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'source' => 'daily_loan_dinamis',
            'ritel_total' => $ktsRetail['total_count'] ?? 0,
            'ritel' => $ktsRetail['rows'] ?? [],
            'micro_total' => $ktsMicro['total_count'] ?? 0,
            'micro' => $ktsMicro['rows'] ?? [],
            'categories' => $categories,
        ];
    }

    private function buildPresentationKtsSummary(?string $period): array
    {
        $emptyScope = [
            'total_count' => 0,
            'total_os' => 0.0,
            'total_os_fmt' => 'Rp0',
            'rows' => [],
            'branches' => [],
        ];

        return [
            'period' => $period,
            'period_label' => $this->formatPeriodLabel($period),
            'source' => 'daily_loan_dinamis',
            'loading_details' => true,
            'ritel_total' => 0,
            'ritel' => [],
            'micro_total' => 0,
            'micro' => [],
            'categories' => [
                'membaik' => [
                    'label' => 'KTS Membaik',
                    'ritel' => $emptyScope,
                    'micro' => $emptyScope,
                ],
                'memburuk' => [
                    'label' => 'KTS Memburuk',
                    'ritel' => $emptyScope,
                    'micro' => $emptyScope,
                ],
            ],
        ];
    }

    private function buildPresentationKtsCategoryRanking(?string $period, string $scope, string $category): array
    {
        $empty = ['total_count' => 0, 'total_os' => 0.0, 'total_os_fmt' => 'Rp0', 'rows' => [], 'branches' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan', 'nomor_rekening1', 'nama_debitur1'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:presentation_kts_category:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . $period . ':' . $scope . ':' . $category;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope, $category): array {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";
            $directionSql = $category === 'membaik'
                ? "{$actualKolekExpression} < {$expectedKolekExpression}"
                : "{$actualKolekExpression} > {$expectedKolekExpression}";
            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];

            $baseQuery = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw($directionSql);

            $this->applyArea6DailyLoanUnitScope($baseQuery, $scope);

            $rankedRows = (clone $baseQuery)
                ->select($groupColumns)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->groupBy($groupColumns)
                ->orderByDesc('mismatch_count')
                ->orderByDesc('outstanding_balance')
                ->limit(18)
                ->get();

            $total = (clone $baseQuery)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->first();

            $branchTotals = (clone $baseQuery)
                ->select('cabang1')
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->groupBy('cabang1')
                ->get()
                ->keyBy('cabang1');

            $detailLimitPerBranch = 30;
            $branchesData = [];
            foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
                $branchTotal = $branchTotals->get($branchName);
                $branchTotalCount = (int) ($branchTotal->mismatch_count ?? 0);
                $branchTotalOs = (float) ($branchTotal->outstanding_balance ?? 0);

                $debiturs = (clone $baseQuery)
                    ->where('cabang1', $branchName)
                    ->select([
                        'nomor_rekening1',
                        'nama_debitur1',
                        'baki_debet1',
                        'kolek',
                        'umur_tunggakan',
                        'unit1'
                    ])
                    ->orderByDesc('baki_debet1')
                    ->limit($detailLimitPerBranch)
                    ->get();

                $debitursMapped = $debiturs->map(function ($deb, int $idx): array {
                    $arrears = (int) ($deb->umur_tunggakan ?? 0);
                    $expected = 1;
                    if ($arrears <= 0) {
                        $expected = 1;
                    } elseif ($arrears <= 90) {
                        $expected = 2;
                    } elseif ($arrears <= 120) {
                        $expected = 3;
                    } elseif ($arrears <= 180) {
                        $expected = 4;
                    } else {
                        $expected = 5;
                    }

                    $actual = (int) ($deb->kolek ?? 1);

                    return [
                        'rank' => $idx + 1,
                        'nomor_rekening' => (string) ($deb->nomor_rekening1 ?? '-'),
                        'nama_debitur' => (string) ($deb->nama_debitur1 ?? '-'),
                        'baki_debet' => (float) ($deb->baki_debet1 ?? 0),
                        'baki_debet_fmt' => $this->formatCurrencyCompact((float) ($deb->baki_debet1 ?? 0)),
                        'kolek_aktual' => $actual,
                        'kolek_seharusnya' => $expected,
                        'umur_tunggakan' => $arrears,
                        'unit' => (string) ($deb->unit1 ?? '-'),
                    ];
                })->all();

                $branchesData[] = [
                    'branch_name' => $branchName,
                    'total_count' => $branchTotalCount,
                    'total_os' => $branchTotalOs,
                    'total_os_fmt' => $this->formatCurrencyCompact($branchTotalOs),
                    'shown_count' => count($debitursMapped),
                    'is_limited' => $branchTotalCount > count($debitursMapped),
                    'debiturs' => $debitursMapped,
                ];
            }

            return [
                'total_count' => (int) ($total->mismatch_count ?? 0),
                'total_os' => (float) ($total->outstanding_balance ?? 0),
                'total_os_fmt' => $this->formatCurrencyCompact((float) ($total->outstanding_balance ?? 0)),
                'rows' => $rankedRows->map(function ($row, int $index) use ($scope): array {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja', 'retail'], true) ? (string) ($row->cabang1 ?? 'Area 6') : 'Area 6',
                        'value' => $this->formatInteger((int) ($row->mismatch_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->outstanding_balance ?? 0)),
                    ];
                })->all(),
                'branches' => $branchesData,
            ];
        });
    }

    private function queryPresentationKtsCategoryTotalsForPeriods(array $periods): array
    {
        $periods = array_values(array_unique(array_filter(array_map(
            fn ($period): ?string => $period ? Carbon::parse($period)->toDateString() : null,
            $periods
        ))));

        if (empty($periods) || !Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        foreach (['periode', 'cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return [];
            }
        }

        $cacheKey = 'dashboard_simpanan:presentation_kts_category_totals:'
            . self::LANDING_SOURCE_CACHE_VERSION . ':v'
            . $this->reportCacheVersion() . ':'
            . md5(implode('|', $periods));

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($periods): array {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";
            $presentationScopeSql = "(UPPER(TRIM(unit1)) LIKE 'KC %'
                OR UPPER(TRIM(unit1)) LIKE 'KCP %'
                OR UPPER(TRIM(unit1)) LIKE 'UNIT %')";

            return DB::table('daily_loan_dinamis')
                ->whereIn('periode', $periods)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereRaw($presentationScopeSql)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->selectRaw('periode')
                ->selectRaw("SUM(CASE WHEN {$actualKolekExpression} < {$expectedKolekExpression} THEN 1 ELSE 0 END) as membaik")
                ->selectRaw("SUM(CASE WHEN {$actualKolekExpression} > {$expectedKolekExpression} THEN 1 ELSE 0 END) as memburuk")
                ->groupBy('periode')
                ->get()
                ->mapWithKeys(fn ($row): array => [
                    Carbon::parse($row->periode)->toDateString() => [
                        'membaik' => (int) ($row->membaik ?? 0),
                        'memburuk' => (int) ($row->memburuk ?? 0),
                    ],
                ])
                ->all();
        });
    }

    private function queryPresentationKtsCategoryTotal(?string $period, string $category): ?float
    {
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return null;
            }
        }

        $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
        $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
        $expectedKolekExpression = "CASE
            WHEN {$umurTunggakanExpression} <= 0 THEN 1
            WHEN {$umurTunggakanExpression} <= 90 THEN 2
            WHEN {$umurTunggakanExpression} <= 120 THEN 3
            WHEN {$umurTunggakanExpression} <= 180 THEN 4
            ELSE 5
        END";
        $directionSql = $category === 'membaik'
            ? "{$actualKolekExpression} < {$expectedKolekExpression}"
            : "{$actualKolekExpression} > {$expectedKolekExpression}";

        $total = 0;

        foreach (['retail', 'unit'] as $scope) {
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw($directionSql);

            $this->applyArea6DailyLoanUnitScope($query, $scope);

            $total += (int) $query->count();
        }

        return (float) $total;
    }

    private function extractPresentationRankingRows(array $rankingModes, string $scope, string $title): array
    {
        $groups = data_get($rankingModes, $scope . '.rankings', []);
        foreach ($groups as $group) {
            if (($group['title'] ?? '') === $title) {
                return array_values($group['rows'] ?? []);
            }
        }

        return [];
    }

    private function buildPresentationDigitalStrategy(array $digitalPerformance): array
    {
        $cards = collect(data_get($digitalPerformance, 'cards', []))->keyBy('key');
        $order = ['edc', 'qris', 'qlola', 'brimo', 'brilink', 'casa', 'dormant', 'payroll'];

        return [
            'updated_at' => data_get($digitalPerformance, 'updated_at'),
            'cards' => collect($order)->map(function (string $key) use ($cards): array {
                $card = $cards->get($key);
                if (!$card) {
                    return [
                        'key' => $key,
                        'title' => strtoupper($key),
                        'available' => false,
                        'current_value' => 'Data belum tersedia',
                        'secondary_value' => '-',
                        'trend' => '-',
                        'source' => $this->defaultDigitalSourceTable($key),
                    ];
                }

                return [
                    'key' => $key,
                    'title' => (string) data_get($card, 'title', strtoupper($key)),
                    'available' => data_get($card, 'current_value') !== '-',
                    'current_value' => (string) data_get($card, 'current_value', 'Data belum tersedia'),
                    'current_label' => (string) data_get($card, 'current_label', ''),
                    'secondary_value' => (string) data_get($card, 'secondary_value', '-'),
                    'secondary_label' => (string) data_get($card, 'secondary_label', ''),
                    'trend' => (string) data_get($card, 'trend', '-'),
                    'source_updated_at' => data_get($card, 'source_updated_at'),
                    'source' => (string) data_get($card, 'source_table', $this->defaultDigitalSourceTable($key)),
                    'stats' => data_get($card, 'stats', []),
                ];
            })->all(),
        ];
    }

    private function buildDashboardPayload(?string $selectedPeriod = null): array
    {
        $cacheVersion = $this->reportCacheVersion();
        
        if ($selectedPeriod) {
            $payloadCacheKey = 'dashboard_simpanan:payload:' . $selectedPeriod . ':' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion;
            
            return Cache::remember($payloadCacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($selectedPeriod) {
                return $this->buildDashboardPayloadFresh($selectedPeriod);
            });
        }

        $payloadCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion;
        $latestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:v' . $cacheVersion;
        $stableLatestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:stable:v' . $cacheVersion;
        $cachedPayload = Cache::get($payloadCacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $latestPayload = Cache::get($latestCacheKey);

        if (is_array($latestPayload)) {
            Cache::put($payloadCacheKey, $latestPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
            $this->deferDashboardPayloadRefresh($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);

            return $latestPayload;
        }

        $stableLatestPayload = Cache::get($stableLatestCacheKey);

        if (is_array($stableLatestPayload)) {
            Cache::put($payloadCacheKey, $stableLatestPayload, now()->addSeconds(30));
            $this->deferDashboardPayloadRefresh($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);

            return $stableLatestPayload;
        }

        $lock = Cache::lock($payloadCacheKey . ':lock', self::CACHE_LOCK_SECONDS);
        $locked = false;

        try {
            $locked = $lock->get();

            if ($locked) {
                return $this->cacheFreshDashboardPayload($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);
            }
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan payload gagal dimuat langsung.', [
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        return $this->emptyDashboard(false);
    }

    private function cacheFreshDashboardPayload(string $payloadCacheKey, string $latestCacheKey, string $stableLatestCacheKey): array
    {
        $freshPayload = $this->buildDashboardPayloadFresh();
        Cache::put($payloadCacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
        Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
        Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

        return $freshPayload;
    }

    private function deferDashboardPayloadRefresh(string $payloadCacheKey, string $latestCacheKey, string $stableLatestCacheKey): void
    {
        app()->terminating(function () use ($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey) {
            $lock = Cache::lock($payloadCacheKey . ':lock', self::CACHE_LOCK_SECONDS);
            $locked = false;

            try {
                $locked = $lock->get();

                if (!$locked) {
                    return;
                }

                $this->cacheFreshDashboardPayload($payloadCacheKey, $latestCacheKey, $stableLatestCacheKey);
            } catch (Throwable $e) {
                Log::warning('Dashboard simpanan payload gagal dihangatkan setelah response.', [
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($locked) {
                    $lock->release();
                }
            }
        });
    }

    private function buildDashboardPayloadFresh(?string $selectedPeriod = null): array
    {
        if (!Schema::hasTable('simpanan_multipn') && !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $this->emptyDashboard();
        }

        [$currentPeriod, $previousPeriod, $yoyPeriod] = $this->resolveDashboardPeriods($selectedPeriod);
        [$loanCurrentPeriod, $loanPreviousPeriod, $loanYoyPeriod] = $this->resolveLoanDashboardPeriods($selectedPeriod);

        if (!$currentPeriod) {
            return $this->emptyDashboard();
        }

        $currentSummary = $this->buildPeriodSummary($currentPeriod);
        $previousSummary = $previousPeriod ? $this->buildPeriodSummary($previousPeriod) : $this->emptySummary();
        $yoySummary = $yoyPeriod ? $this->buildPeriodSummary($yoyPeriod) : $this->emptySummary();
        $loanCurrentSummary = $loanCurrentPeriod ? $this->buildLoanSummary($loanCurrentPeriod) : $this->emptyLoanSummary();
        $loanPreviousSummary = $loanPreviousPeriod ? $this->buildLoanSummary($loanPreviousPeriod) : $this->emptyLoanSummary();
        $loanYoySummary = $loanYoyPeriod ? $this->buildLoanSummary($loanYoyPeriod) : $this->emptyLoanSummary();

        $topBranches = $this->fetchTopBranches($currentPeriod);
        $loanTopBranches = $loanCurrentPeriod ? $this->fetchLoanTopBranches($loanCurrentPeriod) : collect();
        $composition = $this->buildComposition($currentSummary);
        $latestUpdatedAt = $currentSummary['source_updated_at'] ?? null;
        $topBranchLabel = data_get($topBranches->first(), 'label', 'Cabang belum tersedia');
        $topBranchDisplay = data_get($topBranches->first(), 'display', '-');
        $loanTopBranchLabel = data_get($loanTopBranches->first(), 'label', 'Cabang belum tersedia');
        $loanTopBranchDisplay = data_get($loanTopBranches->first(), 'display', '-');
        $savingsMoM = $this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance']);
        $loanMoM = $this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance']);
        $coverageNow = $this->formatRatio($loanCurrentSummary['total_balance'], $currentSummary['total_balance']);
        $coveragePrev = $this->formatRatio($loanPreviousSummary['total_balance'], $previousSummary['total_balance']);
        $coverageChange = $this->percentChange(
            $currentSummary['total_balance'] > 0 ? $loanCurrentSummary['total_balance'] / $currentSummary['total_balance'] : 0,
            $previousSummary['total_balance'] > 0 ? $loanPreviousSummary['total_balance'] / $previousSummary['total_balance'] : 0
        );
        $latestCombinedLabel = trim(sprintf(
            'Simpanan %s | Pinjaman %s',
            $this->formatPeriodLabel($currentPeriod),
            $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data'
        ));
        $digitalPerformance = $this->buildDigitalPerformance();
        $timeseries = $this->buildTimeseriesPayload($currentPeriod, $loanCurrentPeriod);
        $area6Portfolio = $this->buildArea6PortfolioLanding($loanCurrentPeriod);
        $simpananSourceDetail = $this->buildLandingSourceDetail(
            'Simpanan Realtime',
            $currentPeriod,
            $currentSummary['source_table'] ?? 'simpanan_multipn',
            [
                ['label' => 'Total saldo', 'value' => $this->formatCurrencyFull($currentSummary['total_balance']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'Rekening', 'value' => $this->formatInteger($currentSummary['account_count']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'CIF', 'value' => $this->formatInteger($currentSummary['cif_count']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'Top cabang', 'value' => $topBranchLabel . ' - ' . $topBranchDisplay, 'source' => $currentSummary['branch_source_table'] ?? $currentSummary['source_table'] ?? 'simpanan_multipn'],
            ],
            $currentSummary['source_note'] ?? 'Snapshot dashboard simpanan; fallback hanya ke simpanan_multipn jika snapshot belum tersedia.'
        );
        $pinjamanSourceDetail = $this->buildLandingSourceDetail(
            'Pinjaman Realtime',
            $loanCurrentPeriod,
            $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis',
            [
                ['label' => 'Total outstanding', 'value' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Rekening', 'value' => $this->formatInteger($loanCurrentSummary['account_count']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Cabang', 'value' => $this->formatInteger($loanCurrentSummary['branch_count']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Top cabang', 'value' => $loanTopBranchLabel . ' - ' . $loanTopBranchDisplay, 'source' => $loanCurrentSummary['branch_source_table'] ?? $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
            ],
            $loanCurrentSummary['source_note'] ?? 'Snapshot dashboard pinjaman; fallback hanya ke daily_loan_dinamis jika snapshot belum tersedia.'
        );
        $portfolioSourceDetail = $this->buildLandingSourceDetail(
            'LDR (Loan to Deposit Ratio)',
            $latestCombinedLabel,
            ($loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis') . ' + ' . ($currentSummary['source_table'] ?? 'simpanan_multipn'),
            [
                ['label' => 'Total OS pinjaman', 'value' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']), 'source' => $loanCurrentSummary['source_table'] ?? 'daily_loan_dinamis'],
                ['label' => 'Total dana simpanan', 'value' => $this->formatCurrencyFull($currentSummary['total_balance']), 'source' => $currentSummary['source_table'] ?? 'simpanan_multipn'],
                ['label' => 'LDR', 'value' => $coverageNow, 'source' => 'Hasil bagi OS pinjaman / dana simpanan'],
                ['label' => 'LDR pembanding', 'value' => $coveragePrev, 'source' => 'Periode sebelumnya'],
            ],
            'Tidak memakai angka sisipan dari dashboard lain; dihitung dari dua ringkasan sumber yang tampil di kartu ini.'
        );

        return [
            'period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'yoy_period' => $yoyPeriod,
            'hero' => [
                'title' => 'A-SIX',
                'kicker' => 'DASHBOARD AREA 6',
                'subtitle' => 'Ringkasan posisi keuangan Area 6 secara realtime.',
                'badge' => 'A-SIX LIVE PORTFOLIO',
                'updated_label' => $latestCombinedLabel,
                'stats' => [
                    [
                        'label' => 'Total Dana (Simpanan)',
                        'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                        'posisi' => $currentPeriod ? $this->formatPeriodLabel($currentPeriod) : '-',
                        'icon' => 'fas fa-piggy-bank'
                    ],
                    [
                        'label' => 'Total OS (Pinjaman)',
                        'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                        'posisi' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : '-',
                        'icon' => 'fas fa-hand-holding-usd'
                    ]
                ],
            ],
            'health' => [
                'title' => $composition['status_label'],
                'badge' => $composition['badge'],
                'badge_class' => $composition['badge_class'],
                'progress' => $composition['known_ratio'],
                'items' => [
                    [
                        'label' => 'Tabungan',
                        'value' => $this->formatPercent($composition['tabungan_pct']),
                    ],
                    [
                        'label' => 'Giro',
                        'value' => $this->formatPercent($composition['giro_pct']),
                    ],
                    [
                        'label' => 'Tipe Terpetakan',
                        'value' => $this->formatPercent($composition['known_ratio']),
                    ],
                ],
            ],
            'live_reports' => [
                [
                    'key' => 'simpanan',
                    'title' => 'Simpanan Realtime',
                    'eyebrow' => 'Snapshot aktif',
                    'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($savingsMoM),
                    'trend_class' => $this->deltaClass($savingsMoM),
                    'meta' => $currentSummary['account_count'] . ' rekening | ' . $currentSummary['cif_count'] . ' CIF',
                    'detail' => 'Top cabang ' . $topBranchLabel . ' ' . $topBranchDisplay,
                    'updated' => $this->formatPeriodLabel($currentPeriod),
                    'badge' => 'Simpanan',
                    'badge_class' => 'badge-primary',
                    'icon' => 'fas fa-piggy-bank',
                    'icon_bg' => 'rgba(13, 110, 253, 0.12)',
                    'tone' => 'primary',
                    'link' => route('dashboard'),
                    'link_label' => 'Buka report simpanan',
                    'detail_payload' => $simpananSourceDetail,
                ],
                [
                    'key' => 'pinjaman',
                    'title' => 'Pinjaman Realtime',
                    'eyebrow' => 'Outstanding aktif',
                    'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($loanMoM),
                    'trend_class' => $this->deltaClass($loanMoM),
                    'meta' => $loanCurrentSummary['account_count'] . ' rekening | ' . $loanCurrentSummary['branch_count'] . ' cabang',
                    'detail' => 'Top cabang ' . $loanTopBranchLabel . ' ' . $loanTopBranchDisplay,
                    'updated' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data',
                    'badge' => 'Pinjaman',
                    'badge_class' => 'badge-info',
                    'icon' => 'fas fa-hand-holding-usd',
                    'icon_bg' => 'rgba(23, 162, 184, 0.12)',
                    'tone' => 'info',
                    'link' => route('report.dashboard-pinjaman'),
                    'link_label' => 'Buka report pinjaman',
                    'detail_payload' => $pinjamanSourceDetail,
                ],
                [
                    'key' => 'portfolio',
                    'title' => 'LDR (Loan to Deposit Ratio)',
                    'eyebrow' => 'Cross report',
                    'value' => $this->formatRatio($loanCurrentSummary['total_balance'], $currentSummary['total_balance']),
                    'trend' => $this->formatSignedPercent($coverageChange),
                    'trend_class' => $this->deltaClass($coverageChange),
                    'meta' => 'Gap pinjaman vs simpanan ' . $this->formatCurrencyCompact($loanCurrentSummary['total_balance'] - $currentSummary['total_balance']),
                    'detail' => 'LDR periode saat ini ' . $coverageNow . ' vs ' . $coveragePrev,
                    'updated' => $latestCombinedLabel,
                    'badge' => 'LDR',
                    'badge_class' => 'badge-success',
                    'icon' => 'fas fa-layer-group',
                    'icon_bg' => 'rgba(40, 167, 69, 0.12)',
                    'tone' => 'success',
                    'link' => route('dashboard.harian'),
                    'link_label' => 'Lihat portfolio harian',
                    'detail_payload' => $portfolioSourceDetail,
                ],
            ],
            'digital_performance' => $digitalPerformance,
            'timeseries' => $timeseries,
            'area6_portfolio' => $area6Portfolio,
            'metrics' => [
                [
                    'label' => 'Total Simpanan',
                    'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                    'delta' => $this->formatInteger($currentSummary['account_count']) . ' rekening aktif',
                    'delta_class' => 'text-muted',
                    'icon' => 'fas fa-building',
                    'icon_class' => 'text-primary',
                    'icon_bg' => 'rgba(13, 110, 253, 0.12)',
                ],
                [
                    'label' => 'Total Pinjaman',
                    'value' => $this->formatCurrencyCompact($loanCurrentSummary['total_balance']),
                    'delta' => $this->formatInteger($loanCurrentSummary['account_count']) . ' rekening aktif',
                    'delta_class' => 'text-muted',
                    'icon' => 'fas fa-chart-line',
                    'icon_class' => 'text-info',
                    'icon_bg' => 'rgba(23, 162, 184, 0.13)',
                ],
                [
                    'label' => 'Growth Simpanan MtM',
                    'value' => $this->formatSignedPercent($savingsMoM),
                    'delta' => 'vs ' . ($previousPeriod ? $this->formatPeriodLabel($previousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($savingsMoM),
                    'icon' => 'fas fa-wallet',
                    'icon_class' => 'text-warning',
                    'icon_bg' => 'rgba(255, 193, 7, 0.16)',
                ],
                [
                    'label' => 'Growth Pinjaman MtM',
                    'value' => $this->formatSignedPercent($loanMoM),
                    'delta' => 'vs ' . ($loanPreviousPeriod ? $this->formatPeriodLabel($loanPreviousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($loanMoM),
                    'icon' => 'fas fa-database',
                    'icon_class' => 'text-success',
                    'icon_bg' => 'rgba(40, 167, 69, 0.14)',
                ],
            ],
            'performance' => [
                'title' => 'Performa Simpanan',
                'subtitle' => 'Ringkasan kontribusi saldo per jenis simpanan dan konsentrasi cabang terbesar.',
                'updated_at' => $latestUpdatedAt
                    ? Carbon::parse($latestUpdatedAt)->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i')
                    : null,
                'bars' => [
                    [
                        'label' => 'Tabungan',
                        'value' => $composition['tabungan_pct'],
                        'display' => $this->formatPercent($composition['tabungan_pct']),
                        'class' => 'bg-primary',
                    ],
                    [
                        'label' => 'Giro',
                        'value' => $composition['giro_pct'],
                        'display' => $this->formatPercent($composition['giro_pct']),
                        'class' => 'bg-success',
                    ],
                    [
                        'label' => 'Produk Lain / Belum Terpetakan',
                        'value' => $composition['other_pct'],
                        'display' => $this->formatPercent($composition['other_pct']),
                        'class' => 'bg-info',
                    ],
                    [
                        'label' => 'Kontribusi Top 5 Cabang',
                        'value' => $this->percentOf($topBranches->sum('balance'), $currentSummary['total_balance']),
                        'display' => $this->formatPercent($this->percentOf($topBranches->sum('balance'), $currentSummary['total_balance'])),
                        'class' => 'bg-warning',
                    ],
                ],
            ],
            'priorities' => [
                [
                    'badge' => '01',
                    'badge_class' => 'badge-primary',
                    'title' => 'Pantau Pergerakan Simpanan & Pinjaman',
                    'text' => 'Posisi simpanan ' . $this->formatCurrencyFull($currentSummary['total_balance']) . ' dan pinjaman ' . $this->formatCurrencyFull($loanCurrentSummary['total_balance']) . ' sama-sama sudah ter-update.',
                ],
                [
                    'badge' => '02',
                    'badge_class' => 'badge-warning',
                    'title' => 'Jaga Kualitas Mapping Produk',
                    'text' => $this->formatPercent($composition['known_ratio']) . ' saldo sudah terpetakan ke jenis simpanan utama.',
                ],
                [
                    'badge' => '03',
                    'badge_class' => 'badge-success',
                    'title' => 'Fokus Cabang Kontributor',
                    'text' => $topBranchLabel . ' unggul di simpanan, sementara pinjaman terbesar datang dari ' . $loanTopBranchLabel . '.',
                ],
            ],
            'activities' => $this->buildActivities(
                $currentSummary,
                $previousSummary,
                $loanCurrentSummary,
                $loanPreviousSummary,
                $composition,
                $currentPeriod,
                $loanCurrentPeriod,
                $topBranchLabel,
                $topBranchDisplay,
                $loanTopBranchLabel,
                $loanTopBranchDisplay
            ),
            'agenda' => [
                [
                    'title' => 'Review Posisi Simpanan',
                    'time' => $this->formatPeriodLabel($currentPeriod),
                    'tag' => 'Data',
                ],
                [
                    'title' => 'Review Posisi Pinjaman',
                    'time' => $loanCurrentPeriod ? $this->formatPeriodLabel($loanCurrentPeriod) : 'Belum ada data',
                    'tag' => 'Loan',
                ],
                [
                    'title' => 'Bandingkan Coverage',
                    'time' => $coverageNow,
                    'tag' => 'Cross',
                ],
            ],
            'data_quality' => [
                'snapshot_completeness' => $currentSummary['snapshot_completeness'] ?? 'complete',
                'partial_branches' => $currentSummary['partial_branches'] ?? [],
            ],
            'top_branches' => $topBranches->all(),
            'loan_top_branches' => $loanTopBranches->all(),
        ];
    }

    private function buildPeriodSummary(string $period): array
    {
        $cacheKey = 'dashboard_simpanan:summary:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;
        $latestKey = $cacheKey . ':latest';
        $ttl = now()->addMinutes(self::SUMMARY_CACHE_MINUTES);
        $latestTtl = now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);

        try {
            return $lock->block(2, function () use ($cacheKey, $latestKey, $ttl, $latestTtl, $period) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }

                $summary = $this->queryPeriodSummary($period);
                Cache::put($cacheKey, $summary, $ttl);
                Cache::put($latestKey, $summary, $latestTtl);

                return $summary;
            });
        } catch (Throwable) {
            $latest = Cache::get($latestKey);
            if (is_array($latest)) {
                return $latest;
            }

            return $this->queryPeriodSummary($period);
        }
    }

    private function queryPeriodSummary(string $period): array
    {
        $harianSummary = $this->querySimpananSummaryFromHarianSnapshot($period);
        if ($harianSummary !== null) {
            return $harianSummary;
        }

        $snapshotSummary = $this->queryPeriodSummaryFromSnapshot($period);
        if ($snapshotSummary !== null) {
            return $snapshotSummary;
        }

        $summary = DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT no_rekening) as account_count')
            ->selectRaw('COUNT(DISTINCT CIFNO) as cif_count')
            ->selectRaw('COUNT(DISTINCT kantor_cabang) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit_kerja) as unit_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as tabungan_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as giro_balance")
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        $totalBalance = (float) ($summary->total_balance ?? 0);
        $cifCount = (int) ($summary->cif_count ?? 0);

        return [
            'total_balance' => $totalBalance,
            'account_count' => (int) ($summary->account_count ?? 0),
            'cif_count' => $cifCount,
            'branch_count' => (int) ($summary->branch_count ?? 0),
            'unit_count' => (int) ($summary->unit_count ?? 0),
            'tabungan_balance' => (float) ($summary->tabungan_balance ?? 0),
            'giro_balance' => (float) ($summary->giro_balance ?? 0),
            'other_balance' => max(0, $totalBalance - (float) ($summary->tabungan_balance ?? 0) - (float) ($summary->giro_balance ?? 0)),
            'avg_balance_per_cif' => $cifCount > 0 ? $totalBalance / $cifCount : 0,
            'source_updated_at' => $summary->source_updated_at ?? null,
            'source_table' => 'simpanan_multipn',
            'branch_source_table' => 'simpanan_multipn',
            'source_note' => 'Agregasi langsung dari simpanan_multipn untuk posisi yang sama.',
            'snapshot_completeness' => 'complete',
            'partial_branches' => [],
        ];
    }

    private function fetchTopBranches(string $period): Collection
    {
        $cacheKey = 'dashboard_simpanan:top_branches:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;

        $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), function () use ($period) {
            $harianRows = $this->queryTopBranchesFromHarianSnapshot($period);
            if ($harianRows !== null) {
                return $harianRows;
            }

            $snapshotRows = $this->queryTopBranchesFromSnapshot($period);
            if ($snapshotRows !== null) {
                return $snapshotRows;
            }

            return DB::table('simpanan_multipn')
                ->where('posisi', $period)
                ->whereNotNull('kantor_cabang')
                ->where('kantor_cabang', '<>', '')
                ->selectRaw('kantor_cabang, COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
                ->groupBy('kantor_cabang')
                ->orderByDesc('total_balance')
                ->limit(5)
                ->get();
        });

        return collect($rows)->map(function ($row) {
            $balance = (float) ($row->total_balance ?? 0);

            return [
                'label' => $this->simplifyBranchLabel((string) ($row->kantor_cabang ?? '-')),
                'full_label' => (string) ($row->kantor_cabang ?? '-'),
                'balance' => $balance,
                'display' => $this->formatCurrencyCompact($balance),
            ];
        });
    }

    private function buildComposition(array $summary): array
    {
        $total = (float) ($summary['total_balance'] ?? 0);
        $tabungan = (float) ($summary['tabungan_balance'] ?? 0);
        $giro = (float) ($summary['giro_balance'] ?? 0);
        $other = (float) ($summary['other_balance'] ?? 0);
        $knownRatio = $this->percentOf($tabungan + $giro, $total);

        return [
            'tabungan_pct' => $this->percentOf($tabungan, $total),
            'giro_pct' => $this->percentOf($giro, $total),
            'other_pct' => $this->percentOf($other, $total),
            'known_ratio' => $knownRatio,
            'status_label' => $knownRatio >= 75 ? 'Komposisi Simpanan Terbaca' : 'Perlu Review Mapping',
            'badge' => $knownRatio >= 75 ? 'Healthy' : 'Check',
            'badge_class' => $knownRatio >= 75 ? 'badge-success' : 'badge-warning',
        ];
    }

    private function buildLandingSourceDetail(string $title, ?string $period, string $sourceTable, array $rows, string $note): array
    {
        return [
            'title' => $title,
            'period' => $this->formatSourcePeriodLabel($period),
            'source_table' => $sourceTable,
            'note' => $note,
            'rows' => array_values($rows),
        ];
    }

    private function buildArea6PortfolioLanding(?string $loanPeriod): array
    {
        $dailyLoanPeriod = $this->resolveArea6DailyLoanPeriod($loanPeriod);
        $cacheVersion = $this->reportCacheVersion();
        $cacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');
        $latestCacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');
        $stableLatestCacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:stable:v' . $cacheVersion . ':' . ($loanPeriod ?? 'none') . ':daily:' . ($dailyLoanPeriod ?? 'none');
        $cachedPayload = Cache::get($cacheKey);

        if (is_array($cachedPayload)) {
            return $cachedPayload;
        }

        $latestPayload = Cache::get($latestCacheKey);

        if (is_array($latestPayload)) {
            Cache::put($cacheKey, $latestPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
            $this->deferArea6PortfolioRefresh($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod);

            return $latestPayload;
        }

        $stableLatestPayload = Cache::get($stableLatestCacheKey);

        if (is_array($stableLatestPayload)) {
            Cache::put($cacheKey, $stableLatestPayload, now()->addSeconds(30));
            $this->deferArea6PortfolioRefresh($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod);

            return $stableLatestPayload;
        }

        $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);
        $locked = false;

        try {
            try {
                $locked = $lock->block(5);
            } catch (Throwable $e) {
                $locked = $lock->get();
            }

            if ($locked) {
                $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
                Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
                Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
                Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

                return $freshPayload;
            }
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan Area 6 lock failed, generating fresh directly.', [
                'period' => $loanPeriod,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }

        try {
            return $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan Area 6 fallback digunakan.', [
                'period' => $loanPeriod,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->emptyArea6PortfolioLanding();
    }

    private function deferArea6PortfolioRefresh(string $cacheKey, string $latestCacheKey, string $stableLatestCacheKey, ?string $loanPeriod, ?string $dailyLoanPeriod): void
    {
        app()->terminating(function () use ($cacheKey, $latestCacheKey, $stableLatestCacheKey, $loanPeriod, $dailyLoanPeriod) {
            $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);
            $locked = false;

            try {
                $locked = $lock->get();
                if (!$locked) {
                    return;
                }

                $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
                Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
                Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
                Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
            } catch (Throwable $e) {
                Log::warning('Dashboard simpanan Area 6 gagal dihangatkan setelah response.', [
                    'period' => $loanPeriod,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($locked) {
                    $lock->release();
                }
            }
        });
    }

    private function buildArea6PortfolioLandingFresh(?string $loanPeriod, ?string $dailyLoanPeriod = null): array
    {
        $harian = $this->fetchArea6HarianPortfolio();
        $dailyLoanPeriod ??= $this->resolveArea6DailyLoanPeriod($loanPeriod);

        // Fetch unified rankings (KC, KCP, and Unit combined)
        $period = $harian['period'];
        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''");

        $allUkerRows = [];
        if ($period) {
            $allUkerRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
                ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
                ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
                ->selectRaw('COALESCE(total_simpanan, 0) as total_simpanan')
                ->selectRaw('COALESCE(total_os, 0) as total_os')
                ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
                ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
                ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
                ->get()
                ->map(function ($row) {
                    $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                    $smlAbs = (float) ($row->sml_abs ?? 0);
                    $nplAbs = (float) ($row->npl_abs ?? 0);
                    $simpanan = (float) ($row->total_simpanan ?? 0);

                    return [
                        'branch' => trim((string) ($row->branch_label ?? '')),
                        'unit' => trim((string) ($row->unit_label ?? '')),
                        'total_os' => (float) ($row->total_os ?? 0),
                        'total_os_non_commercial' => $osNonCommercial,
                        'total_simpanan' => $simpanan,
                        'sml_abs' => $smlAbs,
                        'npl_abs' => $nplAbs,
                        'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                        'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                    ];
                })
                ->filter(fn (array $row) => $row['unit'] !== '')
                ->values()
                ->all();
        }

        $retailRows = array_values(array_filter(
            $allUkerRows,
            fn (array $row): bool => $this->isArea6RetailLabel($row['unit'] ?? null)
        ));
        $microRows = array_values(array_filter(
            $allUkerRows,
            fn (array $row): bool => $this->isArea6MicroLabel($row['unit'] ?? null)
        ));

        $ktsRetail = $this->buildArea6KtsRanking($dailyLoanPeriod, 'retail');
        $smallArrearsRetail = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'retail');
        $retailRankings = $this->buildArea6RankingGroups($retailRows, $ktsRetail, $smallArrearsRetail, 'unit_kerja');

        $ktsMicro = $this->buildArea6KtsRanking($dailyLoanPeriod, 'unit');
        $smallArrearsMicro = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'unit');
        $microRankings = $this->buildArea6RankingGroups($microRows, $ktsMicro, $smallArrearsMicro, 'unit');

        // Fetch Cabang Konsol Data (Madiun, Magetan, Ngawi, Ponorogo)
        $branchRows = [];
        if ($period) {
            $branchRows = $this->area6HarianSnapshotScopeQuery((string) $period, true)
                ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
                ->selectRaw('COALESCE(total_simpanan, 0) as total_simpanan')
                ->selectRaw('COALESCE(total_os, 0) as total_os')
                ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
                ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
                ->selectRaw('COALESCE(total_sml_pct_non_commercial, 0) as sml_pct')
                ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
                ->selectRaw('COALESCE(total_npl_pct_non_commercial, 0) as npl_pct')
                ->get();
        }

        $branchesIndexed = collect($branchRows)->keyBy(function ($row) {
            return strtoupper(trim($row->branch_label));
        });

        $simpananRka = [];
        $pinjamanRka = [];
        if ($period) {
            try {
                $rkaService = app(\App\Support\RkaLookupService::class);
                $rkaYear = (int) Carbon::parse($period)->format('Y');
                $monthColumn = $rkaService->resolveMonthColumn(Carbon::parse($period));
                
                $definitions = [
                    'simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total', 'A.2. DPK Korporasi']],
                    'pinjaman' => ['mata_anggaran' => ['B. KREDIT TOTAL']],
                ];
                
                $branchesToQuery = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
                $rkaBranchAggregates = $rkaService->aggregateByKancaWithSummaryFallback(
                    $definitions,
                    $monthColumn,
                    $branchesToQuery,
                    $rkaYear
                );
                
                $simpananRka = $rkaBranchAggregates['simpanan'] ?? [];
                $pinjamanRka = $rkaBranchAggregates['pinjaman'] ?? [];
            } catch (\Throwable $e) {
                // Safe fallback if service fails or RKA table is missing
            }
        }

        $maxSimpanan = 0.0;
        $maxPinjaman = 0.0;
        $maxSmlNominal = 0.0;
        $maxNplNominal = 0.0;
        $maxSmlPct = 0.0;
        $maxNplPct = 0.0;

        $totalArea6Simpanan = 0.0;
        $totalArea6Pinjaman = 0.0;
        $totalArea6SmlAbs = 0.0;
        $totalArea6NplAbs = 0.0;
        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $key = strtoupper(trim($branchName));
            $row = $branchesIndexed->get($key);
            $totalArea6Simpanan += $row ? (float) $row->total_simpanan : 0.0;
            $totalArea6Pinjaman += $row ? (float) $row->total_os : 0.0;
            $totalArea6SmlAbs += $row ? (float) $row->sml_abs : 0.0;
            $totalArea6NplAbs += $row ? (float) $row->npl_abs : 0.0;
        }

        $restrukByBranch = [];
        if ($period && $this->hasTable('daily_loan_dinamis') && $this->hasColumn('daily_loan_dinamis', 'baki_debet1') && $this->hasColumn('daily_loan_dinamis', 'cabang1')) {
            try {
                $restrukByBranch = DB::table('daily_loan_dinamis')
                    ->where('periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), array_map('strtoupper', self::AREA_6_BRANCH_LABELS))
                    ->where('kolek', 1)
                    ->where(DB::raw("UPPER(TRIM(COALESCE(flag_restruk, '')))"), 'Y')
                    ->selectRaw('UPPER(TRIM(cabang1)) as branch_key')
                    ->selectRaw('SUM(baki_debet1) as restruk_abs')
                    ->groupBy('branch_key')
                    ->get()
                    ->pluck('restruk_abs', 'branch_key')
                    ->toArray();
            } catch (\Throwable $e) {
                $restrukByBranch = [];
            }
        }

        $branchesData = [];
        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $key = strtoupper(trim($branchName));
            $row = $branchesIndexed->get($key);

            $simpanan = $row ? (float) $row->total_simpanan : 0.0;
            $pinjaman = $row ? (float) $row->total_os : 0.0;
            $smlAbs = $row ? (float) $row->sml_abs : 0.0;
            $smlPct = $row ? (float) $row->sml_pct : 0.0;
            $nplAbs = $row ? (float) $row->npl_abs : 0.0;
            $nplPct = $row ? (float) $row->npl_pct : 0.0;

            $restrukAbs = (float) ($restrukByBranch[$key] ?? 0.0);
            $larAbs = $restrukAbs + $smlAbs + $nplAbs;
            $larPct = $pinjaman > 0 ? ($larAbs / $pinjaman) * 100 : 0.0;

            if ($simpanan > $maxSimpanan) $maxSimpanan = $simpanan;
            if ($pinjaman > $maxPinjaman) $maxPinjaman = $pinjaman;
            if ($smlAbs > $maxSmlNominal) $maxSmlNominal = $smlAbs;
            if ($smlPct > $maxSmlPct) $maxSmlPct = $smlPct;
            if ($nplAbs > $maxNplNominal) $maxNplNominal = $nplAbs;
            if ($nplPct > $maxNplPct) $maxNplPct = $nplPct;

            $smlShare = $totalArea6SmlAbs > 0 ? ($smlAbs / $totalArea6SmlAbs) * 100 : 0.0;
            $nplShare = $totalArea6NplAbs > 0 ? ($nplAbs / $totalArea6NplAbs) * 100 : 0.0;

            $simpananTarget = (float) ($simpananRka[$key] ?? 0.0);
            $pinjamanTarget = (float) ($pinjamanRka[$key] ?? 0.0);

            if ($simpananTarget > 0.0) {
                $simpShare = ($simpanan / $simpananTarget) * 100;
                $simpWidth = min(100.0, $simpShare);
            } else {
                $simpShare = $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0;
                $simpWidth = $maxSimpanan > 0 ? ($simpanan / $maxSimpanan) * 100 : 0.0;
            }

            if ($pinjamanTarget > 0.0) {
                $pinjShare = ($pinjaman / $pinjamanTarget) * 100;
                $pinjWidth = min(100.0, $pinjShare);
            } else {
                $pinjShare = $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0;
                $pinjWidth = $maxPinjaman > 0 ? ($pinjaman / $maxPinjaman) * 100 : 0.0;
            }

            $branchesData[$branchName] = [
                'name' => $branchName,
                'simpanan' => $simpanan,
                'simpanan_fmt' => $this->formatCurrencyCompact($simpanan),
                'simpanan_target' => $simpananTarget > 0.0 ? $simpananTarget : null,
                'simpanan_target_fmt' => $simpananTarget > 0.0 ? $this->formatCurrencyCompact($simpananTarget) : null,
                'simpanan_share_fmt' => $this->formatPercentTwo($simpShare),
                'simpanan_contribution_pct' => $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0,
                'simpanan_contribution_pct_fmt' => $this->formatPercentTwo($totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0),
                'simpanan_width' => $simpWidth,
                'pinjaman' => $pinjaman,
                'pinjaman_fmt' => $this->formatCurrencyCompact($pinjaman),
                'pinjaman_target' => $pinjamanTarget > 0.0 ? $pinjamanTarget : null,
                'pinjaman_target_fmt' => $pinjamanTarget > 0.0 ? $this->formatCurrencyCompact($pinjamanTarget) : null,
                'pinjaman_share_fmt' => $this->formatPercentTwo($pinjShare),
                'pinjaman_contribution_pct' => $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0,
                'pinjaman_contribution_pct_fmt' => $this->formatPercentTwo($totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0),
                'pinjaman_width' => $pinjWidth,
                'sml_abs' => $smlAbs,
                'sml_abs_fmt' => $this->formatCurrencyCompact($smlAbs),
                'sml_pct' => $smlPct,
                'sml_pct_fmt' => $this->formatPercentTwo($smlPct),
                'sml_share_fmt' => $this->formatPercentTwo($smlShare),
                'npl_abs' => $nplAbs,
                'npl_abs_fmt' => $this->formatCurrencyCompact($nplAbs),
                'npl_pct' => $nplPct,
                'npl_pct_fmt' => $this->formatPercentTwo($nplPct),
                'npl_share_fmt' => $this->formatPercentTwo($nplShare),
                'restruk_abs' => $restrukAbs,
                'restruk_abs_fmt' => $this->formatCurrencyCompact($restrukAbs),
                'restruk_pct' => $pinjaman > 0 ? ($restrukAbs / $pinjaman) * 100 : 0.0,
                'restruk_pct_fmt' => $this->formatPercentTwo($pinjaman > 0 ? ($restrukAbs / $pinjaman) * 100 : 0.0),
                'lar_abs' => $larAbs,
                'lar_abs_fmt' => $this->formatCurrencyCompact($larAbs),
                'lar_pct' => $larPct,
                'lar_pct_fmt' => $this->formatPercentTwo($larPct),
            ];
        }

        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $branchesData[$branchName]['sml_width'] = $maxSmlNominal > 0 ? ($branchesData[$branchName]['sml_abs'] / $maxSmlNominal) * 100 : 0;
            $branchesData[$branchName]['npl_width'] = $maxNplNominal > 0 ? ($branchesData[$branchName]['npl_abs'] / $maxNplNominal) * 100 : 0;
            $branchesData[$branchName]['sml_pct_width'] = $maxSmlPct > 0 ? ($branchesData[$branchName]['sml_pct'] / $maxSmlPct) * 100 : 0;
            $branchesData[$branchName]['npl_pct_width'] = $maxNplPct > 0 ? ($branchesData[$branchName]['npl_pct'] / $maxNplPct) * 100 : 0;
        }

        $periodLabel = $this->formatSourcePeriodLabel($harian['period']);
        $loanPeriodLabel = $this->formatSourcePeriodLabel($loanPeriod);
        $dailyLoanPeriodLabel = $this->formatSourcePeriodLabel($dailyLoanPeriod);

        $periodDate = $harian['period'] ? Carbon::parse($harian['period']) : null;
        $periodFormat = $periodDate ? $periodDate->translatedFormat('d F Y') : '19 Mei 2026';
        $rkaMonthYear = $periodDate ? $periodDate->translatedFormat('F y') : 'Mei 26';

        // Aggregate Retail (KC + KCP) branch performance
        $maxSimpananRetail = 0.0;
        $maxPinjamanRetail = 0.0;
        $maxSmlNominalRetail = 0.0;
        $maxNplNominalRetail = 0.0;
        $maxSmlPctRetail = 0.0;
        $maxNplPctRetail = 0.0;

        $retailBranchesData = [];
        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $key = strtoupper(trim($branchName));
            $branchRetailRows = array_filter($retailRows, function ($r) use ($key) {
                return strtoupper(trim($r['branch'])) === $key;
            });

            $simpanan = 0.0;
            $pinjaman = 0.0;
            $osNonCommercial = 0.0;
            $smlAbs = 0.0;
            $nplAbs = 0.0;

            foreach ($branchRetailRows as $r) {
                $simpanan += (float) ($r['total_simpanan'] ?? 0.0);
                $pinjaman += (float) ($r['total_os'] ?? 0.0);
                $osNonCommercial += (float) ($r['total_os_non_commercial'] ?? 0.0);
                $smlAbs += (float) ($r['sml_abs'] ?? 0.0);
                $nplAbs += (float) ($r['npl_abs'] ?? 0.0);
            }

            $smlPct = $osNonCommercial > 0 ? ($smlAbs / $osNonCommercial) * 100 : 0.0;
            $nplPct = $osNonCommercial > 0 ? ($nplAbs / $osNonCommercial) * 100 : 0.0;

            if ($simpanan > $maxSimpananRetail) $maxSimpananRetail = $simpanan;
            if ($pinjaman > $maxPinjamanRetail) $maxPinjamanRetail = $pinjaman;
            if ($smlAbs > $maxSmlNominalRetail) $maxSmlNominalRetail = $smlAbs;
            if ($smlPct > $maxSmlPctRetail) $maxSmlPctRetail = $smlPct;
            if ($nplAbs > $maxNplNominalRetail) $maxNplNominalRetail = $nplAbs;
            if ($nplPct > $maxNplPctRetail) $maxNplPctRetail = $nplPct;

            $simpShare = $totalArea6Simpanan > 0 ? ($simpanan / $totalArea6Simpanan) * 100 : 0.0;
            $pinjShare = $totalArea6Pinjaman > 0 ? ($pinjaman / $totalArea6Pinjaman) * 100 : 0.0;
            $smlShare = $totalArea6SmlAbs > 0 ? ($smlAbs / $totalArea6SmlAbs) * 100 : 0.0;
            $nplShare = $totalArea6NplAbs > 0 ? ($nplAbs / $totalArea6NplAbs) * 100 : 0.0;

            $retailBranchesData[$branchName] = [
                'name' => $branchName,
                'simpanan' => $simpanan,
                'simpanan_fmt' => $this->formatCurrencyCompact($simpanan),
                'simpanan_share_fmt' => $this->formatPercentTwo($simpShare),
                'pinjaman' => $pinjaman,
                'pinjaman_fmt' => $this->formatCurrencyCompact($pinjaman),
                'pinjaman_share_fmt' => $this->formatPercentTwo($pinjShare),
                'sml_abs' => $smlAbs,
                'sml_abs_fmt' => $this->formatCurrencyCompact($smlAbs),
                'sml_pct' => $smlPct,
                'sml_pct_fmt' => $this->formatPercentTwo($smlPct),
                'sml_share_fmt' => $this->formatPercentTwo($smlShare),
                'npl_abs' => $nplAbs,
                'npl_abs_fmt' => $this->formatCurrencyCompact($nplAbs),
                'npl_pct' => $nplPct,
                'npl_pct_fmt' => $this->formatPercentTwo($nplPct),
                'npl_share_fmt' => $this->formatPercentTwo($nplShare),
            ];
        }

        foreach (self::AREA_6_BRANCH_LABELS as $branchName) {
            $retailBranchesData[$branchName]['simpanan_width'] = $maxSimpananRetail > 0 ? ($retailBranchesData[$branchName]['simpanan'] / $maxSimpananRetail) * 100 : 0;
            $retailBranchesData[$branchName]['pinjaman_width'] = $maxPinjamanRetail > 0 ? ($retailBranchesData[$branchName]['pinjaman'] / $maxPinjamanRetail) * 100 : 0;
            $retailBranchesData[$branchName]['sml_width'] = $maxSmlNominalRetail > 0 ? ($retailBranchesData[$branchName]['sml_abs'] / $maxSmlNominalRetail) * 100 : 0;
            $retailBranchesData[$branchName]['npl_width'] = $maxNplNominalRetail > 0 ? ($retailBranchesData[$branchName]['npl_abs'] / $maxNplNominalRetail) * 100 : 0;
            $retailBranchesData[$branchName]['sml_pct_width'] = $maxSmlPctRetail > 0 ? ($retailBranchesData[$branchName]['sml_pct'] / $maxSmlPctRetail) * 100 : 0;
            $retailBranchesData[$branchName]['npl_pct_width'] = $maxNplPctRetail > 0 ? ($retailBranchesData[$branchName]['npl_pct'] / $maxNplPctRetail) * 100 : 0;
        }

        $scopePayloads = [
            'cabang_konsol' => $this->buildArea6PortfolioScopePayload('cabang_konsol', $harian['period'], $periodFormat, $rkaMonthYear, null),
            'ritel' => $this->buildArea6PortfolioScopePayload('ritel', $harian['period'], $periodFormat, $rkaMonthYear, $this->area6ScopeUnitKeys((string) $harian['period'], 'ritel')),
            'micro' => $this->buildArea6PortfolioScopePayload('micro', $harian['period'], $periodFormat, $rkaMonthYear, $this->area6ScopeUnitKeys((string) $harian['period'], 'micro')),
        ];

        return [
            'title' => 'Kinerja Area 6',
            'subtitle' => 'Ringkasan cepat dari snapshot Dashboard Harian dan Pinjaman. Pilih Cabang Konsol, Ritel, atau Micro.',
            'period_label' => $periodLabel,
            'loan_period_label' => $loanPeriodLabel,
            'loan_detail_period_label' => $dailyLoanPeriodLabel,
            'default_scope' => 'cabang_konsol',
            'cards' => $scopePayloads['cabang_konsol']['cards'],
            'segment_performance' => $scopePayloads['cabang_konsol']['segment_performance'],
            'ranking_modes' => [
                'cabang_konsol' => [
                    'label' => 'Cabang Konsol',
                    'description' => 'Semua unit kerja termasuk KC, KCP, dan unit.',
                    'branches' => array_values($branchesData),
                ],
                'ritel' => [
                    'label' => 'Ritel',
                    'description' => 'KC dan KCP.',
                    'branches' => array_values($retailBranchesData),
                ],
                'micro' => [
                    'label' => 'Micro',
                    'description' => 'Unit mikro.',
                    'rankings' => $microRankings,
                ],
            ],
            'rankings' => $retailRankings,
            'overall_trends' => $scopePayloads['cabang_konsol']['overall_trends'],
            'scopes' => $scopePayloads,
        ];
    }

    private function buildArea6PortfolioScopePayload(string $scopeKey, ?string $period, string $periodFormat, string $rkaMonthYear, ?array $unitKeys): array
    {
        $service = app(DashboardHarianSnapshotService::class);
        $periodPayload = $service->buildDashboardPayload($period, null, $this->dashboardBranchNames(), $unitKeys);
        $rows = collect($periodPayload['rows'] ?? []);

        $osRow = $rows->firstWhere('key', 'total_os_non_commercial');
        $smlRow = $rows->firstWhere('key', 'total_sml_abs_non_commercial');
        $nplRow = $rows->firstWhere('key', 'total_npl_abs_non_commercial');
        $snapshotMetrics = $this->area6ScopeSnapshotMetrics((string) $period, $scopeKey);

        $osRealization = (float) ($snapshotMetrics->total_os_non_commercial ?? $osRow['values']['current'] ?? 0.0);
        $osTarget = (float) ($osRow['values']['rka'] ?? 0.0);
        $osPct = (float) ($osRow['values']['penc_pct'] ?? 0.0);
        $osGap = $osRealization - $osTarget;

        $smlRealization = (float) ($snapshotMetrics->total_sml_abs_non_commercial ?? $smlRow['values']['current'] ?? 0.0);
        $smlTarget = (float) ($smlRow['values']['rka'] ?? 0.0);
        $smlPct = $smlRealization > 0 ? ($smlTarget / $smlRealization) * 100 : 100.0;
        $smlGap = $smlTarget - $smlRealization;

        $nplRealization = (float) ($snapshotMetrics->total_npl_abs_non_commercial ?? $nplRow['values']['current'] ?? 0.0);
        $nplTarget = (float) ($nplRow['values']['rka'] ?? 0.0);
        $nplPct = $nplRealization > 0 ? ($nplTarget / $nplRealization) * 100 : 100.0;
        $nplGap = $nplTarget - $nplRealization;

        $scopeLabel = $this->area6ScopeLabel($scopeKey);
        $overallTrends = $this->buildArea6ScopeOverallTrends(
            $scopeKey,
            $period,
            data_get($periodPayload, 'comparison_periods.mtm.period'),
            data_get($periodPayload, 'comparison_periods.mtd.period'),
            [
                'os' => [$osRealization, $osTarget, $osPct, $osGap],
                'sml' => [$smlRealization, $smlTarget, $smlPct, $smlGap],
                'npl' => [$nplRealization, $nplTarget, $nplPct, $nplGap],
            ]
        );

        $osMomDelta = (float) data_get($overallTrends, 'os.mom_delta', 0.0);
        $smlMomDelta = (float) data_get($overallTrends, 'sml.mom_delta', 0.0);
        $nplMomDelta = (float) data_get($overallTrends, 'npl.mom_delta', 0.0);

        $cards = [
            [
                'key' => 'os',
                'header_title' => 'OUTSTANDING (OS)',
                'realization_value' => number_format(round($osRealization / 1000000), 0, ',', '.'),
                'realization_label' => 'OS per ' . $periodFormat,
                'target_value' => number_format(round($osTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($osPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($osPct, 'os'),
                'gap_value' => $this->formatArea6CardGap($osGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $osGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) ($osRow['deltas']['dtd'] ?? 0.0), 'os'),
                    'mtd' => $this->formatArea6CardDelta((float) ($osRow['deltas']['mtd'] ?? 0.0), 'os'),
                    'ytd' => $this->formatArea6CardDelta((float) ($osRow['deltas']['ytd'] ?? 0.0), 'os'),
                    'mom' => $this->formatArea6CardDelta($osMomDelta, 'os'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-chart-line',
                'detail_payload' => $this->buildLandingSourceDetail('Pinjaman Outstanding Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'Total OS', 'value' => $this->formatCurrencyFull($osRealization), 'source' => 'SUM total_os_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
            [
                'key' => 'sml',
                'header_title' => 'SPECIAL MENTION LOAN (SML)',
                'realization_value' => number_format(round($smlRealization / 1000000), 0, ',', '.'),
                'realization_label' => 'SML per ' . $periodFormat,
                'target_value' => number_format(round($smlTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($smlPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($smlPct, 'sml'),
                'gap_value' => $this->formatArea6CardGap($smlGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $smlGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) ($smlRow['deltas']['dtd'] ?? 0.0), 'sml'),
                    'mtd' => $this->formatArea6CardDelta((float) ($smlRow['deltas']['mtd'] ?? 0.0), 'sml'),
                    'ytd' => $this->formatArea6CardDelta((float) ($smlRow['deltas']['ytd'] ?? 0.0), 'sml'),
                    'mom' => $this->formatArea6CardDelta($smlMomDelta, 'sml'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-search',
                'detail_payload' => $this->buildLandingSourceDetail('SML Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'SML (ABS)', 'value' => $this->formatCurrencyFull($smlRealization), 'source' => 'SUM total_sml_abs_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
            [
                'key' => 'npl',
                'header_title' => 'NON-PERFORMING LOAN (NPL)',
                'realization_value' => number_format(round($nplRealization / 1000000), 0, ',', '.'),
                'realization_label' => 'NPL per ' . $periodFormat,
                'target_value' => number_format(round($nplTarget / 1000000), 0, ',', '.'),
                'target_label' => 'RKA ' . $rkaMonthYear,
                'pct_value' => number_format($nplPct, 2, ',', '.') . '%',
                'pct_label' => '% Penc. RKA ' . $rkaMonthYear,
                'pct_color' => $this->getArea6AchievementColor($nplPct, 'npl'),
                'gap_value' => $this->formatArea6CardGap($nplGap),
                'gap_label' => 'Gap thd RKA ' . $rkaMonthYear,
                'gap_color' => $nplGap >= 0 ? 'green' : 'red',
                'deltas' => [
                    'dtd' => $this->formatArea6CardDelta((float) ($nplRow['deltas']['dtd'] ?? 0.0), 'npl'),
                    'mtd' => $this->formatArea6CardDelta((float) ($nplRow['deltas']['mtd'] ?? 0.0), 'npl'),
                    'ytd' => $this->formatArea6CardDelta((float) ($nplRow['deltas']['ytd'] ?? 0.0), 'npl'),
                    'mom' => $this->formatArea6CardDelta($nplMomDelta, 'npl'),
                ],
                'tone' => 'blue',
                'icon' => 'fas fa-shield-alt',
                'detail_payload' => $this->buildLandingSourceDetail('NPL Area 6 - ' . $scopeLabel, $period, self::HARIAN_SNAPSHOT_TABLE, [
                    ['label' => 'NPL (ABS)', 'value' => $this->formatCurrencyFull($nplRealization), 'source' => 'SUM total_npl_abs_non_commercial scope ' . $scopeLabel],
                    ['label' => 'Unit scope', 'value' => $scopeLabel, 'source' => self::HARIAN_SNAPSHOT_TABLE],
                ], 'Sumber mengikuti snapshot Dashboard Harian terbaru sesuai toggle ' . $scopeLabel . '.'),
            ],
        ];

        $segmentPerformance = $this->buildArea6ScopeSegmentPerformance($scopeKey, $rows, $snapshotMetrics, $rkaMonthYear, $periodFormat, $period, $unitKeys);

        return [
            'cards' => $cards,
            'segment_performance' => $segmentPerformance,
            'overall_trends' => $overallTrends,
        ];
    }

    private function buildArea6ScopeOverallTrends(string $scopeKey, ?string $period, ?string $mtmPeriod, ?string $mtdPeriod, array $currentMetrics): array
    {
        $date4 = $period ?? '2026-05-19';
        
        $endOfPreviousMonth = Carbon::parse($date4)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $date3 = $mtdPeriod ?: $this->resolveHarianSnapshotPeriodOnOrBefore($endOfPreviousMonth) ?: $endOfPreviousMonth;
        
        $sameDatePreviousMonth = Carbon::parse($date4)->subMonthNoOverflow()->toDateString();
        $date2 = $mtmPeriod ?: $this->resolveHarianSnapshotPeriodOnOrBefore($sameDatePreviousMonth) ?: $sameDatePreviousMonth;
        
        $prevYearEnd = Carbon::parse($date4)->subYear()->endOfYear()->format('Y-m-d');
        $date1 = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', '<=', $prevYearEnd)
            ->orderBy('snapshot_period', 'desc')
            ->value('snapshot_period') ?? '2025-12-31';

        $resolvedDates = [$date1, $date2, $date3, $date4];
        $historicalQuery = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->whereIn('snapshot_period', $resolvedDates)
            ->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        $this->applyArea6PortfolioScope($historicalQuery, $scopeKey);

        $historicalMetrics = $historicalQuery
            ->selectRaw('
                snapshot_period,
                SUM(total_os_non_commercial) as total_os,
                SUM(total_sml_abs_non_commercial) as total_sml,
                SUM(total_npl_abs_non_commercial) as total_npl
            ')
            ->groupBy('snapshot_period')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->snapshot_period)->toDateString());

        $values = ['os' => [], 'sml' => [], 'npl' => []];
        foreach ($resolvedDates as $date) {
            $row = $historicalMetrics->get(Carbon::parse($date)->toDateString());
            $values['os'][] = $row ? round($row->total_os / 1000000) : 0;
            $values['sml'][] = $row ? round($row->total_sml / 1000000) : 0;
            $values['npl'][] = $row ? round($row->total_npl / 1000000) : 0;
        }

        $prefixes = ['YtD', 'MtM', 'MtD', 'Posisi'];
        $formattedDates = [];
        foreach ($resolvedDates as $idx => $d) {
            $formattedDates[] = $prefixes[$idx] . ' (' . Carbon::parse($d)->translatedFormat('d-M-y') . ')';
        }

        return [
            'dates' => $formattedDates,
            'os' => $this->buildArea6TrendMetric($values['os'], $currentMetrics['os'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, 'total_os'), 'os'),
            'sml' => $this->buildArea6TrendMetric($values['sml'], $currentMetrics['sml'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, 'total_sml'), 'sml'),
            'npl' => $this->buildArea6TrendMetric($values['npl'], $currentMetrics['npl'], $this->snapshotMetricDelta($historicalMetrics, $date4, $date2, 'total_npl'), 'npl'),
        ];
    }

    private function buildArea6TrendMetric(array $values, array $metric, float $momDelta, string $type): array
    {
        [$realization, $target, $pct, $gap] = $metric;
        $points = $this->calculateSvgPoints($values);
        $path = '';
        foreach ($points as $idx => $point) {
            $path .= ($idx === 0 ? 'M' : 'L') . $point['x'] . ',' . $point['y'] . ' ';
        }

        $threshold = $type === 'os' ? 95 : 80;

        return [
            'values' => $values,
            'points' => $points,
            'path' => trim($path),
            'latest' => number_format(round($realization / 1000000), 0, ',', '.'),
            'rka' => number_format(round($target / 1000000), 0, ',', '.'),
            'pct' => number_format($pct, 2, ',', '.') . '%',
            'gap' => $this->formatArea6CardGap($gap),
            'gap_color' => $gap >= 0 ? 'green' : 'red',
            'status_arrow' => $gap >= 0 ? 'up' : 'down',
            'status_bg' => $gap >= 0 ? 'green' : 'red',
            'pct_color' => $pct >= 100 ? 'green' : ($pct >= $threshold ? 'amber' : 'red'),
            'mom_delta' => $momDelta,
        ];
    }

    private function area6ScopeSnapshotMetrics(string $period, string $scopeKey): object
    {
        $empty = (object) [
            'total_os_non_commercial' => 0.0,
            'total_sml_abs_non_commercial' => 0.0,
            'total_npl_abs_non_commercial' => 0.0,
            'sme_os' => 0.0,
            'consumer_os' => 0.0,
            'micro_os' => 0.0,
            'sme_sml' => 0.0,
            'consumer_sml' => 0.0,
            'micro_sml' => 0.0,
            'sme_npl' => 0.0,
            'consumer_npl' => 0.0,
            'micro_npl' => 0.0,
        ];

        if ($period === '' || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $period)
            ->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        $this->applyArea6PortfolioScope($query, $scopeKey);

        return $query
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as total_sml_abs_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as total_npl_abs_non_commercial')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_os, 0) <> 0 THEN COALESCE(sme_os, 0) ELSE COALESCE(kecil_non_cashcoll_os, 0) + COALESCE(cashcoll_os, 0) END), 0) as sme_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_os, 0) <> 0 THEN COALESCE(consumer_os, 0) ELSE COALESCE(briguna_konsumer_os, 0) + COALESCE(kpr_os, 0) + COALESCE(kkb_os, 0) END), 0) as consumer_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_os, 0) <> 0 THEN COALESCE(micro_os, 0) ELSE COALESCE(briguna_mikro_os, 0) + COALESCE(kupedes_os, 0) + COALESCE(kur_mikro_os, 0) + COALESCE(kur_kecil_os, 0) + COALESCE(kur_kpp_os, 0) END), 0) as micro_os')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_sml, 0) <> 0 THEN COALESCE(sme_sml, 0) ELSE COALESCE(kecil_non_cashcoll_sml, 0) + COALESCE(cashcoll_sml, 0) END), 0) as sme_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_sml, 0) <> 0 THEN COALESCE(consumer_sml, 0) ELSE COALESCE(briguna_konsumer_sml, 0) + COALESCE(kpr_sml, 0) + COALESCE(kkb_sml, 0) END), 0) as consumer_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_sml, 0) <> 0 THEN COALESCE(micro_sml, 0) ELSE COALESCE(briguna_mikro_sml, 0) + COALESCE(kupedes_sml, 0) + COALESCE(kur_mikro_sml, 0) + COALESCE(kur_kecil_sml, 0) + COALESCE(kur_kpp_sml, 0) END), 0) as micro_sml')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(sme_npl, 0) <> 0 THEN COALESCE(sme_npl, 0) ELSE COALESCE(kecil_non_cashcoll_npl, 0) + COALESCE(cashcoll_npl, 0) END), 0) as sme_npl')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(consumer_npl, 0) <> 0 THEN COALESCE(consumer_npl, 0) ELSE COALESCE(briguna_konsumer_npl, 0) + COALESCE(kpr_npl, 0) + COALESCE(kkb_npl, 0) END), 0) as consumer_npl')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(micro_npl, 0) <> 0 THEN COALESCE(micro_npl, 0) ELSE COALESCE(briguna_mikro_npl, 0) + COALESCE(kupedes_npl, 0) + COALESCE(kur_mikro_npl, 0) + COALESCE(kur_kecil_npl, 0) + COALESCE(kur_kpp_npl, 0) END), 0) as micro_npl')
            ->first() ?: $empty;
    }

    private function parseSegmentMetricWithCurrent(?array $row, string $type, float $current): array
    {
        $metric = $this->parseSegmentMetric($row, $type);
        $target = (float) ($metric['target'] ?? 0.0);
        $pct = $type === 'os'
            ? ($target > 0 ? ($current / $target) * 100 : 0.0)
            : ($current > 0 ? ($target / $current) * 100 : 100.0);
        $maxVal = max($current, $target);

        $metric['realization'] = $current;
        $metric['realization_scaled'] = round($current / 1000000);
        $metric['realization_fmt'] = number_format($metric['realization_scaled'], 0, ',', '.');
        $metric['pct'] = $pct;
        $metric['pct_fmt'] = number_format($pct, 2, ',', '.') . '%';
        $metric['pct_color'] = $this->getArea6AchievementColor($pct, $type);
        $metric['penc_bar_width'] = $maxVal > 0 ? ($current / $maxVal) * 100 : 0.0;
        $metric['rka_bar_width'] = $maxVal > 0 ? ($target / $maxVal) * 100 : 0.0;

        return $metric;
    }

    private function buildArea6ScopeSegmentPerformance(string $scopeKey, Collection $rows, object $snapshotMetrics, string $rkaMonthYear, string $periodFormat, ?string $period = null, ?array $unitKeys = null): array
    {
        $segmentDefinitions = match ($scopeKey) {
            'ritel' => [
                ['label' => 'OS SME', 'icon' => 'fas fa-briefcase', 'os' => 'sme_os', 'sml' => 'sme_sml', 'npl' => 'sme_npl'],
                ['label' => 'OS KONSUMER', 'icon' => 'fas fa-users', 'os' => 'consumer_os', 'sml' => 'consumer_sml', 'npl' => 'consumer_npl'],
            ],
            'micro' => [
                ['label' => 'OS MIKRO', 'icon' => 'fas fa-store', 'os' => 'micro_os', 'sml' => 'micro_sml', 'npl' => 'micro_npl'],
            ],
            default => [
                ['label' => 'OS SME', 'icon' => 'fas fa-briefcase', 'os' => 'sme_os', 'sml' => 'sme_sml', 'npl' => 'sme_npl'],
                ['label' => 'OS KONSUMER', 'icon' => 'fas fa-users', 'os' => 'consumer_os', 'sml' => 'consumer_sml', 'npl' => 'consumer_npl'],
                ['label' => 'OS MIKRO', 'icon' => 'fas fa-store', 'os' => 'micro_os', 'sml' => 'micro_sml', 'npl' => 'micro_npl'],
            ],
        };

        $segments = [];
        $totals = [
            'os' => ['realization' => 0.0, 'target' => 0.0],
            'sml' => ['realization' => 0.0, 'target' => 0.0],
            'npl' => ['realization' => 0.0, 'target' => 0.0],
        ];

        foreach ($segmentDefinitions as $definition) {
            $os = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['os']), 'os', (float) ($snapshotMetrics->{$definition['os']} ?? 0.0));
            $sml = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['sml']), 'sml', (float) ($snapshotMetrics->{$definition['sml']} ?? 0.0));
            $npl = $this->parseSegmentMetricWithCurrent($rows->firstWhere('key', $definition['npl']), 'npl', (float) ($snapshotMetrics->{$definition['npl']} ?? 0.0));

            foreach (['os' => $os, 'sml' => $sml, 'npl' => $npl] as $metricKey => $metric) {
                $totals[$metricKey]['realization'] += (float) $metric['realization'];
                $totals[$metricKey]['target'] += (float) $metric['target'];
            }

            $segments[] = [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'os' => $os,
                'sml' => $sml,
                'npl' => $npl,
            ];
        }

        $totalOs = $this->formatArea6ScopeSegmentTotal($totals['os']['realization'], $totals['os']['target'], 'os');
        $totalSml = $this->formatArea6ScopeSegmentTotal($totals['sml']['realization'], $totals['sml']['target'], 'sml');
        $totalNpl = $this->formatArea6ScopeSegmentTotal($totals['npl']['realization'], $totals['npl']['target'], 'npl');
        
        // Fetch restruk_os (kolek 1 flag_restruk = Y)
        $restrukOs = 0.0;
        if ($period && $this->hasTable('daily_loan_dinamis')) {
            $q = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $this->dashboardBranchNames());
            
            if (!empty($unitKeys)) {
                $q->whereIn(DB::raw('UPPER(TRIM(unit1))'), array_map('strtoupper', $unitKeys));
            }
            
            $restrukOs = (float) $q->where('kolek', 1)
                ->where(DB::raw("UPPER(TRIM(COALESCE(flag_restruk, '')))"), 'Y')
                ->sum('baki_debet1');
        }

        $totalOsRealization = $totals['os']['realization'];
        $smlRealization = $totals['sml']['realization'];
        $nplRealization = $totals['npl']['realization'];
        $larRealization = $restrukOs + $smlRealization + $nplRealization;

        $smlPct = $totalOsRealization > 0 ? ($smlRealization / $totalOsRealization) * 100 : 0.0;
        $nplPct = $totalOsRealization > 0 ? ($nplRealization / $totalOsRealization) * 100 : 0.0;
        $larPct = $totalOsRealization > 0 ? ($larRealization / $totalOsRealization) * 100 : 0.0;
        $healthyPct = max(0.0, 100.0 - $larPct);
        $lrPct = $totalOsRealization > 0 ? ($restrukOs / $totalOsRealization) * 100 : 0.0;

        return [
            'rka_month_year' => $rkaMonthYear,
            'period_format' => $periodFormat,
            'segments' => $segments,
            'total' => [
                'os' => $totalOs,
                'sml' => $totalSml,
                'npl' => $totalNpl,
            ],
            'composition' => [
                'os' => [
                    'name' => 'LAR',
                    'value' => number_format(round($larRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($larPct, 2, ',', '.') . '%',
                    'raw_pct' => $larPct,
                ],
                'sml' => [
                    'value' => number_format(round($smlRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($smlPct, 2, ',', '.') . '%',
                    'raw_pct' => $smlPct,
                ],
                'npl' => [
                    'value' => number_format(round($nplRealization / 1000000), 0, ',', '.'),
                    'pct' => number_format($nplPct, 2, ',', '.') . '%',
                    'raw_pct' => $nplPct,
                ],
                'total' => [
                    'value' => number_format(round($totalOsRealization / 1000000), 0, ',', '.'),
                ],
                'angles' => [
                    'healthy' => $healthyPct,
                    'lr' => $healthyPct + $lrPct,
                    'sml' => $healthyPct + $lrPct + $smlPct,
                ],
                'center' => [
                    'pct' => number_format($larPct, 2, ',', '.') . '%',
                    'label' => 'LAR SHARE',
                ]
            ],
        ];
    }

    private function formatArea6ScopeSegmentTotal(float $realization, float $target, string $type): array
    {
        $pct = $type === 'os'
            ? ($target > 0 ? ($realization / $target) * 100 : 0.0)
            : ($realization > 0 ? ($target / $realization) * 100 : 100.0);
        $max = max($realization, $target);

        return [
            'realization_fmt' => number_format(round($realization / 1000000), 0, ',', '.'),
            'target_fmt' => number_format(round($target / 1000000), 0, ',', '.'),
            'pct_fmt' => number_format($pct, 2, ',', '.') . '%',
            'pct_color' => $this->getArea6AchievementColor($pct, $type),
            'penc_bar_width' => $max > 0 ? ($realization / $max) * 100 : 0.0,
            'rka_bar_width' => $max > 0 ? ($target / $max) * 100 : 0.0,
        ];
    }

    private function formatArea6CompositionMetric(float $value, float $total): array
    {
        $pct = $total > 0 ? ($value / $total) * 100 : 0.0;

        return [
            'value' => number_format(round($value / 1000000), 0, ',', '.'),
            'pct' => number_format($pct, 2, ',', '.') . '%',
            'raw_pct' => $pct,
        ];
    }

    private function area6ScopeUnitKeys(string $period, string $scopeKey): array
    {
        if ($period === '' || !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [];
        }

        $query = $this->area6HarianSnapshotScopeQuery($period, false)
            ->select('unit_key');
        $this->applyArea6UnitLabelScope($query, $scopeKey);

        return $query->pluck('unit_key')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyArea6PortfolioScope($query, string $scopeKey): void
    {
        if ($scopeKey === 'cabang_konsol') {
            $query->whereRaw('kanca_key = unit_key');

            return;
        }

        $query->whereRaw('kanca_key <> unit_key');
        $this->applyArea6UnitLabelScope($query, $scopeKey);
    }

    private function applyArea6UnitLabelScope($query, string $scopeKey): void
    {
        $labelColumn = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : null);

        if ($labelColumn === null) {
            return;
        }

        if ($scopeKey === 'ritel') {
            $query->where(function ($nested) use ($labelColumn): void {
                $nested->whereRaw("UPPER(TRIM({$labelColumn})) LIKE 'KC%'")
                    ->orWhereRaw("UPPER(TRIM({$labelColumn})) LIKE 'KCP%'");
            });

            return;
        }

        if ($scopeKey === 'micro') {
            $query->whereRaw("UPPER(TRIM({$labelColumn})) LIKE 'UNIT%'");
        }
    }

    private function area6ScopeLabel(string $scopeKey): string
    {
        return match ($scopeKey) {
            'ritel' => 'Ritel',
            'micro' => 'Micro',
            default => 'Cabang Konsol',
        };
    }

    private function snapshotMetricDelta(Collection $metricsByPeriod, ?string $currentPeriod, ?string $comparisonPeriod, string $column): float
    {
        if (!$currentPeriod || !$comparisonPeriod) {
            return 0.0;
        }

        $currentKey = Carbon::parse($currentPeriod)->toDateString();
        $comparisonKey = Carbon::parse($comparisonPeriod)->toDateString();
        $current = $metricsByPeriod->get($currentKey);
        $comparison = $metricsByPeriod->get($comparisonKey);

        return (float) ($current->{$column} ?? 0.0) - (float) ($comparison->{$column} ?? 0.0);
    }

    private function calculateSvgPoints(array $values, int $width = 110, int $height = 50, int $padding = 12): array
    {
        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        
        $xCoords = [10, 40, 70, 100];
        $points = [];
        
        foreach ($values as $index => $val) {
            $x = $xCoords[$index] ?? 10;
            if ($range > 0) {
                // Map values so that max is at top ($padding) and min is at bottom ($height - $padding)
                $y = $height - $padding - (($val - $min) / $range) * ($height - 2 * $padding);
            } else {
                $y = $height / 2;
            }
            $points[] = [
                'x' => $x,
                'y' => round($y, 1),
                'val' => $val,
                'val_fmt' => number_format($val, 0, ',', '.')
            ];
        }
        
        return $points;
    }

    private function formatArea6CardDelta(float $delta, string $metricType): array
    {
        $scaled = round($delta / 1000000);
        $isNegative = $scaled < 0;
        $absVal = abs($scaled);
        
        $formattedVal = number_format($absVal, 0, ',', '.');
        if ($isNegative) {
            $valueStr = '(' . $formattedVal . ')';
            $type = 'down';
        } else {
            $valueStr = '+' . $formattedVal;
            $type = 'up';
        }
        
        if ($metricType === 'os') {
            $color = $isNegative ? 'red' : 'green';
        } else {
            // SML/NPL: always red in the mockup
            $color = 'red';
        }
        
        return [
            'raw' => $delta,
            'value' => $valueStr,
            'type' => $type,
            'color' => $color,
        ];
    }

    private function formatArea6CardGap(float $gap): string
    {
        $scaled = round($gap / 1000000);
        $isNegative = $scaled < 0;
        $absVal = abs($scaled);
        
        $formattedVal = number_format($absVal, 0, ',', '.');
        if ($isNegative) {
            return '(' . $formattedVal . ')';
        } else {
            return '+' . $formattedVal;
        }
    }

    private function getArea6AchievementColor(float $pct, string $metricType): string
    {
        if ($metricType === 'os') {
            if ($pct >= 100) {
                return 'green';
            } elseif ($pct >= 95) {
                return 'amber';
            } else {
                return 'red';
            }
        } else {
            if ($pct >= 100) {
                return 'green';
            } elseif ($pct >= 90) {
                return 'amber';
            } else {
                return 'red';
            }
        }
    }

    private function parseSegmentMetric(?array $row, string $type): array
    {
        $realization = (float) ($row['values']['current'] ?? 0.0);
        $target = (float) ($row['values']['rka'] ?? 0.0);

        if ($type === 'os') {
            $pct = $target > 0 ? ($realization / $target) * 100 : 0.0;
            $color = $this->getArea6AchievementColor($pct, 'os');
        } else {
            $pct = $realization > 0 ? ($target / $realization) * 100 : 100.0;
            $color = $this->getArea6AchievementColor($pct, $type);
        }

        $maxVal = max($realization, $target);
        $pencBarWidth = $maxVal > 0 ? ($realization / $maxVal) * 100 : 0.0;
        $rkaBarWidth = $maxVal > 0 ? ($target / $maxVal) * 100 : 0.0;

        $realizationScaled = round($realization / 1000000);
        $targetScaled = round($target / 1000000);

        return [
            'realization' => $realization,
            'target' => $target,
            'realization_scaled' => $realizationScaled,
            'target_scaled' => $targetScaled,
            'realization_fmt' => number_format($realizationScaled, 0, ',', '.'),
            'target_fmt' => number_format($targetScaled, 0, ',', '.'),
            'pct' => $pct,
            'pct_fmt' => number_format($pct, 2, ',', '.') . '%',
            'pct_color' => $color,
            'penc_bar_width' => $pencBarWidth,
            'rka_bar_width' => $rkaBarWidth,
        ];
    }

    private function emptyArea6PortfolioLanding(): array
    {
        return [
            'title' => 'Ringkasan Area 6',
            'subtitle' => 'Data lintas report belum tersedia.',
            'period_label' => 'Belum ada data',
            'loan_period_label' => 'Belum ada data',
            'loan_detail_period_label' => 'Belum ada data',
            'cards' => [],
            'rankings' => [],
            'segment_performance' => [],
            'overall_trends' => [],
        ];
    }

    private function resolveArea6DailyLoanPeriod(?string $requestedPeriod): ?string
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        $cacheKey = 'dashboard_simpanan:area6_daily_loan_period:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . ($requestedPeriod ?? 'latest');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($requestedPeriod) {
            $query = DB::table('daily_loan_dinamis');

            if ($requestedPeriod) {
                $period = $query
                    ->where('periode', '<=', $requestedPeriod)
                    ->orderByDesc('periode')
                    ->value('periode');

                if ($period) {
                    return Carbon::parse($period)->toDateString();
                }
            }

            $period = $query
                ->orderByDesc('periode')
                ->value('periode');

            return $period ? Carbon::parse($period)->toDateString() : null;
        });
    }

    private function fetchArea6HarianPortfolio(): array
    {
        $empty = [
            'period' => null,
            'totals' => [
                'total_os' => 0.0,
                'total_os_non_commercial' => 0.0,
                'total_simpanan' => 0.0,
                'sml_abs' => 0.0,
                'npl_abs' => 0.0,
                'sml_pct' => 0.0,
                'npl_pct' => 0.0,
                'ldr_pct' => 0.0,
                'casa_pct' => 0.0,
                'total_casa' => 0.0,
                'rec_dh_total' => 0.0,
            ],
            'unit_rows' => [],
            'branch_rows' => [],
        ];

        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $period = DB::table(self::HARIAN_SNAPSHOT_TABLE)->max('snapshot_period');
        if (!$period) {
            return $empty;
        }

        $summaryQuery = $this->area6HarianSnapshotScopeQuery((string) $period, true);
        $summary = $summaryQuery
            ->selectRaw('COUNT(*) as row_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_os')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os_non_commercial, 0)), 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_simpanan')
            ->selectRaw('COALESCE(SUM(COALESCE(total_sml_abs_non_commercial, 0)), 0) as sml_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_npl_abs_non_commercial, 0)), 0) as npl_abs')
            ->selectRaw('COALESCE(SUM(COALESCE(total_casa, 0)), 0) as total_casa')
            ->selectRaw('COALESCE(SUM(COALESCE(rec_dh_total, 0)), 0) as rec_dh_total')
            ->first();

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");
        $unitLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_label')
            ? 'unit_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'uker_label') ? 'uker_label' : "''");

        $unitRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->branch_label ?? '')),
                    'unit' => trim((string) ($row->unit_label ?? '')),
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['unit'] !== '' && $this->isArea6MicroLabel($row['unit']))
            ->values()
            ->all();

        $summaryBranchRows = $this->area6HarianSnapshotScopeQuery((string) $period, true)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->branch_label ?? '')),
                    'unit' => 'Ritel Area 6',
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['branch'] !== '')
            ->values()
            ->all();

        $retailRows = $this->area6HarianSnapshotScopeQuery((string) $period, false)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as branch_label")
            ->selectRaw("COALESCE({$unitLabelExpression}, '') as unit_label")
            ->selectRaw('COALESCE(total_os, 0) as total_os')
            ->selectRaw('COALESCE(total_os_non_commercial, 0) as total_os_non_commercial')
            ->selectRaw('COALESCE(total_sml_abs_non_commercial, 0) as sml_abs')
            ->selectRaw('COALESCE(total_npl_abs_non_commercial, 0) as npl_abs')
            ->get()
            ->map(function ($row) {
                $osNonCommercial = (float) ($row->total_os_non_commercial ?? 0);
                $smlAbs = (float) ($row->sml_abs ?? 0);
                $nplAbs = (float) ($row->npl_abs ?? 0);

                return [
                    'branch' => trim((string) ($row->unit_label ?? '')),
                    'unit' => trim((string) ($row->branch_label ?? '')),
                    'total_os' => (float) ($row->total_os ?? 0),
                    'total_os_non_commercial' => $osNonCommercial,
                    'sml_abs' => $smlAbs,
                    'npl_abs' => $nplAbs,
                    'sml_pct' => $this->percentOf($smlAbs, $osNonCommercial),
                    'npl_pct' => $this->percentOf($nplAbs, $osNonCommercial),
                ];
            })
            ->filter(fn (array $row) => $row['branch'] !== '' && $this->isArea6RetailLabel($row['branch']))
            ->values()
            ->all();

        $totalOsNonCommercial = (float) ($summary->total_os_non_commercial ?? 0);
        $totalSimpanan = (float) ($summary->total_simpanan ?? 0);
        $smlAbs = (float) ($summary->sml_abs ?? 0);
        $nplAbs = (float) ($summary->npl_abs ?? 0);
        $totalCasa = (float) ($summary->total_casa ?? 0);

        return [
            'period' => (string) $period,
            'totals' => [
                'total_os' => (float) ($summary->total_os ?? 0),
                'total_os_non_commercial' => $totalOsNonCommercial,
                'total_simpanan' => $totalSimpanan,
                'sml_abs' => $smlAbs,
                'npl_abs' => $nplAbs,
                'sml_pct' => $this->percentOf($smlAbs, $totalOsNonCommercial),
                'npl_pct' => $this->percentOf($nplAbs, $totalOsNonCommercial),
                'ldr_pct' => $this->percentOf($totalOsNonCommercial, $totalSimpanan),
                'casa_pct' => $this->percentOf($totalCasa, $totalSimpanan),
                'total_casa' => $totalCasa,
                'rec_dh_total' => (float) ($summary->rec_dh_total ?? 0),
            ],
            'unit_rows' => $unitRows,
            'branch_rows' => $retailRows ?: $summaryBranchRows,
        ];
    }

    private function isArea6RetailLabel(?string $label): bool
    {
        return preg_match('/^(KC|KCP)\b/i', trim((string) $label)) === 1;
    }

    private function isArea6MicroLabel(?string $label): bool
    {
        return preg_match('/^UNIT\b/i', trim((string) $label)) === 1;
    }

    private function area6HarianSnapshotScopeQuery(string $period, bool $summaryRows)
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE)
            ->where('snapshot_period', $period);

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        return $query;
    }

    private function area6HarianSnapshotSummaryQuery()
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE);

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && $this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $query->whereColumn('kanca_key', 'unit_key');
        }

        if ($this->hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', 'branch');
        }

        return $query;
    }

    private function querySimpananSummaryFromHarianSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COUNT(*) as branch_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(tabungan_ritel, 0) + COALESCE(tabungan_mikro, 0) + COALESCE(tabungan_wholesale, 0)), 0) as tabungan_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(giro_ritel, 0) + COALESCE(giro_mikro, 0) + COALESCE(giro_wholesale, 0)), 0) as giro_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(source_savings_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row || (int) ($row->branch_count ?? 0) === 0) {
            return null;
        }

        $totalBalance = (float) ($row->total_balance ?? 0);
        $tabunganBalance = (float) ($row->tabungan_balance ?? 0);
        $giroBalance = (float) ($row->giro_balance ?? 0);
        $sourceRows = (int) ($row->source_row_count ?? 0);

        return [
            'total_balance' => $totalBalance,
            'account_count' => $sourceRows,
            'cif_count' => 0,
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => $this->countHarianUnitRows($period),
            'tabungan_balance' => $tabunganBalance,
            'giro_balance' => $giroBalance,
            'other_balance' => max(0, $totalBalance - $tabunganBalance - $giroBalance),
            'avg_balance_per_cif' => 0,
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari summary kanca Dashboard Harian untuk posisi yang sama.',
            'snapshot_completeness' => 'complete',
            'partial_branches' => [],
        ];
    }

    private function queryLoanSummaryFromHarianSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $row = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw('COUNT(*) as branch_count')
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_balance')
            ->selectRaw('COALESCE(SUM(COALESCE(source_loan_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row || (int) ($row->branch_count ?? 0) === 0) {
            return null;
        }

        return [
            'total_balance' => (float) ($row->total_balance ?? 0),
            'account_count' => (int) ($row->source_row_count ?? 0),
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => $this->countHarianUnitRows($period),
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::HARIAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari summary kanca Dashboard Harian untuk posisi yang sama.',
        ];
    }

    private function countHarianUnitRows(string $period): int
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return 0;
        }

        return (int) $this->area6HarianSnapshotScopeQuery($period, false)->count();
    }

    private function queryTopBranchesFromHarianSnapshot(string $period): ?Collection
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");

        $query = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as kantor_cabang")
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_balance');

        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }

        $rows = $query->orderByDesc('total_balance')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : null;
    }

    private function queryLoanTopBranchesFromHarianSnapshot(string $period): ?Collection
    {
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $branchLabelExpression = Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')
            ? 'kanca_label'
            : (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : "''");

        $query = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as cabang1")
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_balance');

        if ($branchLabelExpression !== "''") {
            $query->groupBy($branchLabelExpression);
        }

        $rows = $query->orderByDesc('total_balance')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : null;
    }

    private function buildArea6RankingGroups(array $rows, array $kts, array $smallArrears, string $scope): array
    {
        $targetLabel = $scope === 'unit' ? 'unit kerja' : 'KC/KCP';

        return [
            [
                'title' => '5 OS Terbesar',
                'tone' => 'blue',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'desc', 5, 'currency', false, null, $scope),
            ],
            [
                'title' => '5 OS Terkecil',
                'tone' => 'slate',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'asc', 5, 'currency', true, null, $scope),
            ],
            [
                'title' => '5 SML Nominal',
                'tone' => 'amber',
                'rows' => $this->rankHarianUnits($rows, 'sml_abs', 'desc', 5, 'currency', false, 'sml_pct', $scope),
            ],
            [
                'title' => '5 SML Rasio',
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'sml_pct', 'desc', 5, 'percent', false, 'sml_abs', $scope),
            ],
            [
                'title' => '5 NPL Nominal',
                'tone' => 'orange',
                'rows' => $this->rankHarianUnits($rows, 'npl_abs', 'desc', 5, 'currency', false, 'npl_pct', $scope),
            ],
            [
                'title' => '5 NPL Rasio',
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'npl_pct', 'desc', 5, 'percent', false, 'npl_abs', $scope),
            ],
            [
                'title' => '5 KTS Terbanyak',
                'tone' => 'orange',
                'rows' => $kts['rows'],
            ],
            [
                'title' => '5 Tunggakan Kecil',
                'tone' => 'teal',
                'rows' => $smallArrears['rows'],
            ],
        ];
    }

    private function rankHarianUnits(array $rows, string $field, string $direction, int $limit, string $format, bool $positiveOnly = false, ?string $secondaryField = null, string $scope = 'unit'): array
    {
        $labelField = in_array($scope, ['unit', 'unit_kerja']) ? 'unit' : 'branch';
        $metaField = in_array($scope, ['unit', 'unit_kerja']) ? 'branch' : 'unit';

        $sorted = collect($rows)
            ->filter(fn (array $row) => !$positiveOnly || (float) ($row[$field] ?? 0) > 0)
            ->sortBy([
                [$field, $direction],
                [$labelField, 'asc'],
            ])
            ->take($limit)
            ->values();

        return $sorted->map(function (array $row, int $index) use ($field, $format, $secondaryField, $labelField, $metaField) {
            $value = (float) ($row[$field] ?? 0);
            $secondary = $secondaryField ? (float) ($row[$secondaryField] ?? 0) : null;

            return [
                'rank' => $index + 1,
                'label' => $row[$labelField] ?: '-',
                'meta' => $row[$metaField] ?: 'Area 6',
                'value' => $format === 'percent' ? $this->formatPercentTwo($value) : $this->formatCurrencyCompact($value),
                'sub' => $secondaryField
                    ? ($secondaryField === 'sml_pct' || $secondaryField === 'npl_pct'
                        ? $this->formatPercentTwo((float) $secondary)
                        : $this->formatCurrencyCompact((float) $secondary))
                    : null,
            ];
        })->all();
    }

    private function buildArea6KtsRanking(?string $period, string $scope = 'unit'): array
    {
        $empty = ['total_count' => 0, 'total_os' => 0.0, 'rows' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'status_rekening1', 'baki_debet1', 'kolek', 'umur_tunggakan'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:area6_kts_top5:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope) {
            $actualKolekExpression = "CAST(kolek AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(umur_tunggakan AS SIGNED)";
            $expectedKolekExpression = "CASE
                WHEN {$umurTunggakanExpression} <= 0 THEN 1
                WHEN {$umurTunggakanExpression} <= 90 THEN 2
                WHEN {$umurTunggakanExpression} <= 120 THEN 3
                WHEN {$umurTunggakanExpression} <= 180 THEN 4
                ELSE 5
            END";

            $baseQuery = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereIn('status_rekening1', ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereIn('kolek', ['1', '2', '3', '4', '5'])
                ->whereNotNull('umur_tunggakan')
                ->whereRaw("{$actualKolekExpression} <> {$expectedKolekExpression}");

            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];
            $rankedRows = (clone $baseQuery)
                ->select($groupColumns)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance');

            $this->applyArea6DailyLoanUnitScope($rankedRows, $scope);

            $rankedRows = $rankedRows
                ->groupBy($groupColumns)
                ->orderByDesc('mismatch_count')
                ->orderByDesc('outstanding_balance')
                ->limit(5)
                ->get();

            $total = (clone $baseQuery)
                ->selectRaw('COUNT(*) as mismatch_count')
                ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as outstanding_balance')
                ->first();

            $ranked = $rankedRows
                ->map(function ($row, int $index) use ($scope) {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja']) ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
                        'value' => $this->formatInteger((int) ($row->mismatch_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->outstanding_balance ?? 0)),
                    ];
                })
                ->all();

            return [
                'total_count' => (int) ($total->mismatch_count ?? 0),
                'total_os' => (float) ($total->outstanding_balance ?? 0),
                'rows' => $ranked,
            ];
        });
    }

    private function buildArea6SmallArrearsRanking(?string $period, string $scope = 'unit'): array
    {
        $empty = ['total_count' => 0, 'total_amount' => 0.0, 'rows' => []];
        if (!$period || !Schema::hasTable('daily_loan_dinamis')) {
            return $empty;
        }

        foreach (['cabang1', 'unit1', 'tunggakan_pokok', 'tunggakan_bunga'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $empty;
            }
        }

        $cacheKey = 'dashboard_simpanan:area6_small_arrears_top5:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope) {
            $accountColumn = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1') ? 'nomor_rekening1' : null;
            $penaltyColumn = Schema::hasColumn('daily_loan_dinamis', 'tunggakan_penalti')
                ? 'tunggakan_penalti'
                : (Schema::hasColumn('daily_loan_dinamis', 'tunggakan_pinalti') ? 'tunggakan_pinalti' : null);
            $totalExpression = 'COALESCE(tunggakan_pokok, 0) + COALESCE(tunggakan_bunga, 0)';
            if ($penaltyColumn !== null) {
                $totalExpression .= " + COALESCE({$penaltyColumn}, 0)";
            }

            $groupColumns = $scope === 'branch' ? ['cabang1'] : ['cabang1', 'unit1'];
            $query = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
                ->select($groupColumns)
                ->selectRaw('SUM(' . $totalExpression . ') as total_amount');

            if ($scope !== 'branch') {
                $this->applyArea6DailyLoanUnitScope($query, $scope);
            }

            if ($accountColumn !== null) {
                $query->selectRaw("COUNT(DISTINCT {$accountColumn}) as current_count");
            } else {
                $query->selectRaw('COUNT(*) as current_count');
            }

            $rows = $query
                ->groupBy($groupColumns)
                ->orderByDesc('current_count')
                ->orderByDesc('total_amount')
                ->limit(5)
                ->get();

            $total = DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS)
                ->whereRaw("({$totalExpression}) > 0 AND ({$totalExpression}) <= 100000")
                ->selectRaw(($accountColumn !== null ? "COUNT(DISTINCT {$accountColumn})" : 'COUNT(*)') . ' as total_count')
                ->selectRaw('SUM(' . $totalExpression . ') as total_amount')
                ->first();

            return [
                'total_count' => (int) ($total->total_count ?? 0),
                'total_amount' => (float) ($total->total_amount ?? 0),
                'rows' => $rows->map(function ($row, int $index) use ($scope) {
                    return [
                        'rank' => $index + 1,
                        'label' => $scope === 'branch' ? (string) ($row->cabang1 ?? '-') : (string) ($row->unit1 ?? '-'),
                        'meta' => in_array($scope, ['unit', 'unit_kerja']) ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
                        'value' => $this->formatInteger((int) ($row->current_count ?? 0)) . ' rek',
                        'sub' => $this->formatCurrencyCompact((float) ($row->total_amount ?? 0)),
                    ];
                })->all(),
            ];
        });
    }

    private function applyArea6DailyLoanUnitScope($query, string $scope): void
    {
        if ($scope === 'branch') {
            return;
        }

        $query->whereNotNull('unit1')->where('unit1', '<>', '');

        if ($scope === 'unit_kerja') {
            return;
        }

        if ($scope === 'retail') {
            $query->where(function ($nested): void {
                $nested
                    ->whereRaw('UPPER(TRIM(unit1)) LIKE ?', ['KC %'])
                    ->orWhereRaw('UPPER(TRIM(unit1)) LIKE ?', ['KCP %']);
            });

            return;
        }

        $query->whereRaw('UPPER(TRIM(unit1)) LIKE ?', ['UNIT %']);
    }

    private function formatSourcePeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return $period;
        }

        return $this->formatPeriodLabel($period);
    }

    private function buildActivities(
        array $currentSummary,
        array $previousSummary,
        array $loanCurrentSummary,
        array $loanPreviousSummary,
        array $composition,
        string $period,
        ?string $loanPeriod,
        string $topBranchLabel,
        string $topBranchDisplay,
        string $loanTopBranchLabel,
        string $loanTopBranchDisplay
    ): array {
        return [
            [
                'class' => 'badge-success',
                'title' => 'Posisi simpanan ' . $this->formatPeriodLabel($period) . ' sudah terbaca',
                'time' => $this->formatCurrencyFull($currentSummary['total_balance']),
            ],
            [
                'class' => $this->deltaClass($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance']), true),
                'title' => 'Growth saldo dibanding periode sebelumnya',
                'time' => $this->formatSignedPercent($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance'])),
            ],
            [
                'class' => 'badge-primary',
                'title' => 'Kontributor simpanan terbesar: ' . $topBranchLabel,
                'time' => $topBranchDisplay,
            ],
            [
                'class' => 'badge-info',
                'title' => 'Posisi pinjaman ' . ($loanPeriod ? $this->formatPeriodLabel($loanPeriod) : 'belum tersedia'),
                'time' => $this->formatCurrencyFull($loanCurrentSummary['total_balance']),
            ],
            [
                'class' => $this->deltaClass($this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance']), true),
                'title' => 'Growth pinjaman dibanding periode sebelumnya',
                'time' => $this->formatSignedPercent($this->percentChange($loanCurrentSummary['total_balance'], $loanPreviousSummary['total_balance'])),
            ],
            [
                'class' => $composition['badge_class'],
                'title' => 'Fokus cabang pinjaman: ' . $loanTopBranchLabel,
                'time' => $loanTopBranchDisplay,
            ],
        ];
    }

    private function emptyDashboard(bool $includeDigitalPerformance = true): array
    {
        return [
            'period' => null,
            'previous_period' => null,
            'yoy_period' => null,
            'hero' => [
                'title' => 'A-SIX',
                'kicker' => 'DASHBOARD AREA 6',
                'subtitle' => 'Data simpanan belum tersedia untuk ditampilkan.',
                'badge' => 'A-SIX OVERVIEW',
                'updated_label' => 'Belum ada data',
                'stats' => [
                    ['label' => 'Total Dana (Simpanan)', 'value' => 'Rp0', 'posisi' => '-', 'icon' => 'fas fa-piggy-bank'],
                    ['label' => 'Total OS (Pinjaman)', 'value' => 'Rp0', 'posisi' => '-', 'icon' => 'fas fa-hand-holding-usd']
                ],
            ],
            'health' => [
                'title' => 'Menunggu Data',
                'badge' => 'Pending',
                'badge_class' => 'badge-secondary',
                'progress' => 0,
                'items' => [
                    ['label' => 'Tabungan', 'value' => '0,0%'],
                    ['label' => 'Giro', 'value' => '0,0%'],
                    ['label' => 'Tipe Terpetakan', 'value' => '0,0%'],
                ],
            ],
            'metrics' => [
                ['label' => 'Total Simpanan', 'value' => 'Rp0', 'delta' => '0 rekening aktif', 'delta_class' => 'text-muted', 'icon' => 'fas fa-building', 'icon_class' => 'text-primary', 'icon_bg' => 'rgba(13, 110, 253, 0.12)'],
                ['label' => 'Total Pinjaman', 'value' => 'Rp0', 'delta' => '0 rekening aktif', 'delta_class' => 'text-muted', 'icon' => 'fas fa-chart-line', 'icon_class' => 'text-info', 'icon_bg' => 'rgba(23, 162, 184, 0.13)'],
                ['label' => 'Growth Simpanan MtM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-wallet', 'icon_class' => 'text-warning', 'icon_bg' => 'rgba(255, 193, 7, 0.16)'],
                ['label' => 'Growth Pinjaman MtM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-database', 'icon_class' => 'text-success', 'icon_bg' => 'rgba(40, 167, 69, 0.14)'],
            ],
            'performance' => [
                'title' => 'Performa Simpanan',
                'subtitle' => 'Ringkasan akan muncul setelah data tersedia.',
                'updated_at' => null,
                'bars' => [
                    ['label' => 'Tabungan', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-primary'],
                    ['label' => 'Giro', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-success'],
                    ['label' => 'Produk Lain / Belum Terpetakan', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-info'],
                    ['label' => 'Kontribusi Top 5 Cabang', 'value' => 0, 'display' => '0,0%', 'class' => 'bg-warning'],
                ],
            ],
            'priorities' => [
                ['badge' => '01', 'badge_class' => 'badge-primary', 'title' => 'Import Data Simpanan', 'text' => 'Upload data simpanan terbaru agar dashboard dapat menampilkan ringkasan aktual.'],
                ['badge' => '02', 'badge_class' => 'badge-warning', 'title' => 'Periksa Periode Posisi', 'text' => 'Pastikan kolom posisi pada `simpanan_multipn` berisi tanggal snapshot yang valid.'],
                ['badge' => '03', 'badge_class' => 'badge-success', 'title' => 'Cek Mapping Jenis Simpanan', 'text' => 'Jenis simpanan yang rapi akan membuat komposisi dashboard lebih akurat.'],
            ],
            'activities' => [
                ['class' => 'badge-secondary', 'title' => 'Belum ada data simpanan yang bisa diringkas', 'time' => 'Menunggu import'],
            ],
            'agenda' => [
                ['title' => 'Import Simpanan', 'time' => 'Belum ada data', 'tag' => 'Data'],
            ],
            'top_branches' => [],
            'loan_top_branches' => [],
            'digital_performance' => $includeDigitalPerformance ? $this->buildDigitalPerformance() : [
                'title' => 'Performance Digital Area 6',
                'subtitle' => 'Ringkasan digital belum dimuat.',
                'updated_at' => null,
                'cards' => [],
            ],
            'area6_portfolio' => $this->emptyArea6PortfolioLanding(),
            'live_reports' => [
                ['key' => 'simpanan', 'title' => 'Simpanan Realtime', 'eyebrow' => 'Snapshot aktif', 'value' => 'Rp0', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => '0 rekening | 0 CIF', 'detail' => 'Top cabang belum tersedia', 'updated' => 'Belum ada data', 'badge' => 'Simpanan', 'badge_class' => 'badge-primary', 'icon' => 'fas fa-piggy-bank', 'icon_bg' => 'rgba(13, 110, 253, 0.12)', 'tone' => 'primary', 'link' => route('dashboard'), 'link_label' => 'Buka report simpanan', 'detail_payload' => $this->buildLandingSourceDetail('Simpanan Realtime', null, 'simpanan_multipn', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'simpanan_multipn']], 'Tabel sumber belum memiliki posisi yang bisa ditampilkan.')],
                ['key' => 'pinjaman', 'title' => 'Pinjaman Realtime', 'eyebrow' => 'Outstanding aktif', 'value' => 'Rp0', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => '0 rekening | 0 cabang', 'detail' => 'Top cabang belum tersedia', 'updated' => 'Belum ada data', 'badge' => 'Pinjaman', 'badge_class' => 'badge-info', 'icon' => 'fas fa-hand-holding-usd', 'icon_bg' => 'rgba(23, 162, 184, 0.12)', 'tone' => 'info', 'link' => route('report.dashboard-pinjaman'), 'link_label' => 'Buka report pinjaman', 'detail_payload' => $this->buildLandingSourceDetail('Pinjaman Realtime', null, 'daily_loan_dinamis', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'daily_loan_dinamis']], 'Tabel sumber belum memiliki periode yang bisa ditampilkan.')],
                ['key' => 'portfolio', 'title' => 'LDR (Loan to Deposit Ratio)', 'eyebrow' => 'Cross report', 'value' => '0,00x', 'trend' => '0,0%', 'trend_class' => 'text-muted', 'meta' => 'Gap pinjaman vs simpanan Rp0', 'detail' => 'LDR periode saat ini 0,00x vs 0,00x', 'updated' => 'Belum ada data', 'badge' => 'LDR', 'badge_class' => 'badge-success', 'icon' => 'fas fa-layer-group', 'icon_bg' => 'rgba(40, 167, 69, 0.12)', 'tone' => 'success', 'link' => route('dashboard.harian'), 'link_label' => 'Lihat portfolio harian', 'detail_payload' => $this->buildLandingSourceDetail('LDR (Loan to Deposit Ratio)', null, 'daily_loan_dinamis + simpanan_multipn', [['label' => 'Status', 'value' => 'Belum ada data', 'source' => 'Sumber pinjaman dan simpanan']], 'LDR kosong karena salah satu sumber belum tersedia.')],
            ],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_balance' => 0,
            'account_count' => 0,
            'cif_count' => 0,
            'branch_count' => 0,
            'unit_count' => 0,
            'tabungan_balance' => 0,
            'giro_balance' => 0,
            'other_balance' => 0,
            'avg_balance_per_cif' => 0,
            'source_updated_at' => null,
            'source_table' => 'simpanan_multipn',
            'branch_source_table' => 'simpanan_multipn',
            'source_note' => 'Belum ada data simpanan untuk periode ini.',
        ];
    }

    private function emptyLoanSummary(): array
    {
        return [
            'total_balance' => 0,
            'account_count' => 0,
            'branch_count' => 0,
            'unit_count' => 0,
            'source_updated_at' => null,
            'source_table' => 'daily_loan_dinamis',
            'branch_source_table' => 'daily_loan_dinamis',
            'source_note' => 'Belum ada data pinjaman untuk periode ini.',
        ];
    }

    private function resolveLoanDashboardPeriods(?string $selectedPeriod = null): array
    {
        if ($selectedPeriod) {
            $currentPeriod = Carbon::parse($selectedPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($previousCandidate) ?: $previousCandidate;
            $yoyPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($yoyCandidate) ?: $yoyCandidate;

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        }

        $cacheKey = 'dashboard_pinjaman:periods:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $harianPeriods = $this->resolveHarianDashboardPeriods();
            if ($harianPeriods[0] !== null) {
                return $harianPeriods;
            }

            if (!Schema::hasTable('daily_loan_dinamis')) {
                return [null, null, null];
            }

            $latestPeriod = DB::table('daily_loan_dinamis')->max('periode');
            if (!$latestPeriod) {
                return [null, null, null];
            }

            $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = DB::table('daily_loan_dinamis')
                ->where('periode', '<=', $previousCandidate)
                ->max('periode');

            $yoyPeriod = DB::table('daily_loan_dinamis')
                ->where('periode', '<=', $yoyCandidate)
                ->max('periode');

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        });
    }

    private function buildLoanSummary(string $period): array
    {
        $harianSummary = $this->queryLoanSummaryFromHarianSnapshot($period);
        if ($harianSummary !== null) {
            return $harianSummary;
        }

        $snapshotSummary = $this->queryLoanSummaryFromSnapshot($period);
        if ($snapshotSummary !== null) {
            return $snapshotSummary;
        }

        if (!Schema::hasTable('daily_loan_dinamis')) {
            return $this->emptyLoanSummary();
        }

        foreach (['periode', 'baki_debet1', 'cabang1', 'unit1'] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return $this->emptyLoanSummary();
            }
        }

        $accountCountExpression = Schema::hasColumn('daily_loan_dinamis', 'nomor_rekening1')
            ? 'COUNT(DISTINCT nomor_rekening1)'
            : 'COUNT(*)';
        $sourceUpdatedExpression = Schema::hasColumn('daily_loan_dinamis', 'updated_at')
            ? 'MAX(updated_at)'
            : 'NULL';

        $summary = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_balance')
            ->selectRaw($accountCountExpression . ' as account_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw($sourceUpdatedExpression . ' as source_updated_at')
            ->first();

        return [
            'total_balance' => (float) ($summary->total_balance ?? 0),
            'account_count' => (int) ($summary->account_count ?? 0),
            'branch_count' => (int) ($summary->branch_count ?? 0),
            'unit_count' => (int) ($summary->unit_count ?? 0),
            'source_updated_at' => $summary->source_updated_at ?? null,
            'source_table' => 'daily_loan_dinamis',
            'branch_source_table' => 'daily_loan_dinamis',
            'source_note' => 'Agregasi langsung dari daily_loan_dinamis untuk periode yang sama.',
        ];
    }

    private function queryLoanSummaryFromSnapshot(string $period): ?array
    {
        if (!Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)) {
            return null;
        }

        foreach (['periode', 'loan_balance', 'account_number', 'cabang1', 'unit1'] as $column) {
            if (!Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, $column)) {
                return null;
            }
        }

        $row = DB::table(self::LOAN_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(loan_balance, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT account_number) as account_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'total_balance' => (float) ($row->total_balance ?? 0),
            'account_count' => (int) ($row->account_count ?? 0),
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => (int) ($row->unit_count ?? 0),
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::LOAN_SNAPSHOT_TABLE,
            'branch_source_table' => self::LOAN_SNAPSHOT_TABLE,
            'source_note' => 'Agregasi dari snapshot dashboard pinjaman untuk periode yang sama.',
        ];
    }

    private function fetchLoanTopBranches(string $period): Collection
    {
        $cacheKey = 'dashboard_pinjaman:top_branches:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . $period;

        $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), function () use ($period) {
            $harianRows = $this->queryLoanTopBranchesFromHarianSnapshot($period);
            if ($harianRows !== null) {
                return $harianRows;
            }

            if (
                Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'periode')
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'cabang1')
                && Schema::hasColumn(self::LOAN_SNAPSHOT_TABLE, 'loan_balance')
            ) {
                return DB::table(self::LOAN_SNAPSHOT_TABLE)
                    ->where('periode', $period)
                    ->whereNotNull('cabang1')
                    ->where('cabang1', '<>', '')
                    ->selectRaw('cabang1, COALESCE(SUM(COALESCE(loan_balance, 0)), 0) as total_balance')
                    ->groupBy('cabang1')
                    ->orderByDesc('total_balance')
                    ->limit(5)
                    ->get();
            }

            if (
                !Schema::hasTable('daily_loan_dinamis')
                || !Schema::hasColumn('daily_loan_dinamis', 'periode')
                || !Schema::hasColumn('daily_loan_dinamis', 'cabang1')
                || !Schema::hasColumn('daily_loan_dinamis', 'baki_debet1')
            ) {
                return collect();
            }

            return DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereNotNull('cabang1')
                ->where('cabang1', '<>', '')
                ->selectRaw('cabang1, COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_balance')
                ->groupBy('cabang1')
                ->orderByDesc('total_balance')
                ->limit(5)
                ->get();
        });

        return collect($rows)->map(function ($row) {
            $balance = (float) ($row->total_balance ?? 0);

            return [
                'label' => $this->simplifyBranchLabel((string) ($row->cabang1 ?? '-')),
                'full_label' => (string) ($row->cabang1 ?? '-'),
                'balance' => $balance,
                'display' => $this->formatCurrencyCompact($balance),
            ];
        });
    }

    private function queryPeriodSummaryFromSnapshot(string $period): ?array
    {
        if (!$this->hasSimpananSnapshot($period)) {
            return null;
        }

        $row = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
            ->where('snapshot_period', $period)
            ->first();

        if (!$row) {
            return null;
        }

        $totalBalance = (float) ($row->total_balance ?? 0);
        $cifCount = (int) ($row->cif_count ?? 0);
        $tabunganBalance = (float) ($row->tabungan_balance ?? 0);
        $giroBalance = (float) ($row->giro_balance ?? 0);
        $otherBalance = (float) ($row->other_balance ?? max(0, $totalBalance - $tabunganBalance - $giroBalance));

        return [
            'total_balance' => $totalBalance,
            'account_count' => (int) ($row->account_count ?? 0),
            'cif_count' => $cifCount,
            'branch_count' => (int) ($row->branch_count ?? 0),
            'unit_count' => (int) ($row->unit_count ?? 0),
            'tabungan_balance' => $tabunganBalance,
            'giro_balance' => $giroBalance,
            'other_balance' => $otherBalance,
            'avg_balance_per_cif' => $cifCount > 0 ? $totalBalance / $cifCount : 0,
            'source_updated_at' => $row->source_updated_at ?? null,
            'source_table' => self::SNAPSHOT_SUMMARY_TABLE,
            'branch_source_table' => self::SNAPSHOT_BRANCH_TABLE,
            'source_note' => 'Agregasi dari snapshot dashboard simpanan untuk posisi yang sama.',
            'snapshot_completeness' => (string) ($row->snapshot_completeness ?? 'complete'),
            'partial_branches' => $this->decodePartialBranches($row->partial_branches ?? null),
        ];
    }

    private function decodePartialBranches(mixed $value): array
    {
        if ($value === null || trim((string) $value) === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($branch): bool => is_string($branch) && trim($branch) !== ''));
    }

    private function queryTopBranchesFromSnapshot(string $period): ?Collection
    {
        if (!$this->hasSimpananSnapshot($period)) {
            return null;
        }

        if (!Schema::hasTable(self::SNAPSHOT_BRANCH_TABLE)) {
            return null;
        }

        $rows = DB::table(self::SNAPSHOT_BRANCH_TABLE)
            ->where('snapshot_period', $period)
            ->orderBy('rank_order')
            ->limit(5)
            ->get();

        return $rows->isNotEmpty() ? $rows : collect();
    }

    private function hasSimpananSnapshot(string $period): bool
    {
        if (!Schema::hasTable(self::SNAPSHOT_SUMMARY_TABLE) || !Schema::hasTable('simpanan_multipn')) {
            return false;
        }

        if (array_key_exists($period, $this->snapshotExistsMemo)) {
            return $this->snapshotExistsMemo[$period];
        }

        $cacheKey = 'dashboard_simpanan:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $period;
        $knownExists = Cache::get($cacheKey);
        if ($knownExists === true) {
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        $exists = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
            ->where('snapshot_period', $period)
            ->exists();

        if ($exists) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        $hasSourceRows = DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->exists();

        if (!$hasSourceRows) {
            Cache::put($cacheKey, false, now()->addSeconds(30));
            $this->snapshotExistsMemo[$period] = false;

            return false;
        }

        if (!$this->isSimpananMultiPnSnapshotReady($period)) {
            $missingBranches = app(SimpananMultiPnSnapshotGate::class)->getMissingBranches($period);

            Log::info('Dashboard simpanan snapshot ditunda karena Area 6 belum lengkap.', [
                'period' => $period,
                'missing_branches' => $missingBranches,
            ]);

            Cache::put($cacheKey, false, now()->addSeconds(30));
            $this->snapshotExistsMemo[$period] = false;

            return false;
        }

        $lock = Cache::lock('snapshot:dashboard_simpanan:auto-rebuild:' . $period, 60);
        $pendingKey = 'snapshot:dashboard_simpanan:auto-rebuild:pending:' . $period;
        $jobDispatched = false;
        $built = false;

        try {
            if ($lock->get()) {
                try {
                    $builder = app(\App\Support\ReportSnapshotBuilder::class);
                    $builder->rebuildDashboardSimpanan($period, false);
                    $builder->rebuildRekeningDormant($period, false);
                    $builder->rebuildRasioCasa($period, false);
                    $builder->rebuildPerformanceRm($period, false);

                    app(\App\Support\DashboardHarianSnapshotService::class)->rebuild($period, false);

                    $built = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
                        ->where('snapshot_period', $period)
                        ->exists();
                } catch (Throwable $builderEx) {
                    Log::warning('Synchronous rebuild dashboard simpanan failed, falling back: ' . $builderEx->getMessage());
                }

                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                    EnsureDashboardSimpananSnapshotJob::dispatch($period, static::class . '::hasSimpananSnapshot')
                        ->onQueue((string) config('queue.report_queue', 'default'));
                    $jobDispatched = true;
                }
            }
        } catch (Throwable $e) {
            Log::warning('Auto rebuild dashboard simpanan snapshot gagal: ' . $e->getMessage(), [
                'period' => $period,
            ]);
        } finally {
            optional($lock)->release();
        }

        if ($built) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            $this->snapshotExistsMemo[$period] = true;

            return true;
        }

        Log::info('Dashboard simpanan snapshot unavailable; using source query fallback.', [
            'period' => $period,
            'job_dispatched' => $jobDispatched,
        ]);

        Cache::put($cacheKey, false, now()->addSeconds(30));
        $this->snapshotExistsMemo[$period] = false;

        return false;
    }

    private function isSimpananMultiPnSnapshotReady(string $period): bool
    {
        return app(SimpananMultiPnSnapshotGate::class)->isReady($period);
    }

    private function resolveDashboardPeriods(?string $selectedPeriod = null): array
    {
        if ($selectedPeriod) {
            $currentPeriod = Carbon::parse($selectedPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($previousCandidate) ?: $previousCandidate;
            $yoyPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($yoyCandidate) ?: $yoyCandidate;

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        }

        $cacheKey = 'dashboard_simpanan:periods:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $harianPeriods = $this->resolveHarianDashboardPeriods();
            if ($harianPeriods[0] !== null) {
                return $harianPeriods;
            }

            if (!Schema::hasTable('simpanan_multipn')) {
                return [null, null, null];
            }

            $latestPeriod = DB::table('simpanan_multipn')->max('posisi');
            if (!$latestPeriod) {
                return [null, null, null];
            }

            $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
            $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
            $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

            $previousPeriod = DB::table('simpanan_multipn')
                ->where('posisi', '<=', $previousCandidate)
                ->max('posisi');

            $yoyPeriod = DB::table('simpanan_multipn')
                ->where('posisi', '<=', $yoyCandidate)
                ->max('posisi');

            return [$currentPeriod, $previousPeriod, $yoyPeriod];
        });
    }

    private function resolveHarianDashboardPeriods(): array
    {
        if (!$this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return [null, null, null];
        }

        $baseQuery = $this->area6HarianSnapshotSummaryQuery();
        $latestPeriod = (clone $baseQuery)->max('snapshot_period');
        if (!$latestPeriod) {
            return [null, null, null];
        }

        $currentPeriod = Carbon::parse($latestPeriod)->toDateString();
        $previousCandidate = Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString();
        $yoyCandidate = Carbon::parse($currentPeriod)->subYearNoOverflow()->endOfMonth()->toDateString();

        $previousPeriod = (clone $baseQuery)
            ->where('snapshot_period', '<=', $previousCandidate)
            ->max('snapshot_period');

        $yoyPeriod = (clone $baseQuery)
            ->where('snapshot_period', '<=', $yoyCandidate)
            ->max('snapshot_period');

        return [$currentPeriod, $previousPeriod, $yoyPeriod];
    }

    private function resolveHarianSnapshotPeriodOnOrBefore(string $period): ?string
    {
        if (array_key_exists($period, $this->snapshotPeriodMemo)) {
            return $this->snapshotPeriodMemo[$period];
        }

        if (!$this->hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $this->snapshotPeriodMemo[$period] = null;
        }

        $actualPeriod = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', '<=', $period)
            ->max('snapshot_period');

        return $this->snapshotPeriodMemo[$period] = ($actualPeriod ? Carbon::parse($actualPeriod)->toDateString() : null);
    }

    private function buildDigitalPerformance(): array
    {
        $cacheKey = 'dashboard_simpanan:digital_performance:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(self::DIGITAL_PERFORMANCE_CACHE_MINUTES), function () {
            $cards = array_values(array_filter([
                $this->buildEdcPerformanceCard(),
                $this->buildQrisPerformanceCard(),
                $this->buildQlolaPerformanceCard(),
                $this->buildBrimoPerformanceCard(),
                $this->buildBrilinkPerformanceCard(),
                $this->buildCasaDebiturKpiCard(),
                $this->buildRekeningDormantKpiCard(),
                $this->buildPayrollPerformanceCard(),
            ]));

            $latestSource = collect($cards)
                ->pluck('source_updated_at')
                ->filter()
                ->map(function ($value) {
                    try {
                        return Carbon::parse($value)->timestamp;
                    } catch (Throwable) {
                        return null;
                    }
                })
                ->filter()
                ->max();

            return [
                'title' => 'Performance Digital Area 6',
                'subtitle' => 'Snapshot realtime untuk 8 strategi: EDC, QRIS, QLola, BRIMO, BRILink, Casa Debitur, Rekening Dormant, dan Payroll.',
                'updated_at' => $latestSource
                    ? Carbon::createFromTimestamp($latestSource)->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i')
                    : null,
                'cards' => $cards,
            ];
        });
    }

    private function buildEdcPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('jumlah_merchant_detail')) {
                return null;
            }

            $latestPeriod = DB::table('jumlah_merchant_detail')->max(DB::raw('DATE(POSISI)'));
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table('jumlah_merchant_detail')
                    ->whereDate('POSISI', $period)
                    ->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $branches)
                    ->selectRaw('COUNT(DISTINCT MID) as merchant_count')
                    ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(SALES_VOLUME, 0) >= 15000000 THEN MID END) as productive_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(SALES_VOLUME, 0)), 0) as volume')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'merchant_count' => (int) ($row->merchant_count ?? 0),
                    'productive_count' => (int) ($row->productive_count ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['merchant_count' => 0, 'productive_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'edc',
                'title' => 'Performance EDC',
                'subtitle' => 'MID aktif, merchant produktif, dan volume penjualan tersaji dalam satu kartu ringkas.',
                'badge' => 'EDC',
                'badge_class' => 'badge-primary',
                'tone' => 'digital-edc',
                'icon' => 'fas fa-credit-card',
                'link' => route('report.edc'),
                'link_label' => 'Buka report EDC',
                'current_value' => $this->formatInteger((int) $current['merchant_count']),
                'current_label' => 'MID Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'secondary_label' => 'Sales Volume',
                'trend_reference' => $this->formatInteger((int) $previous['merchant_count']) . ' MID sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['merchant_count'], (float) $previous['merchant_count']),
                'series' => array_column($timeline, 'merchant_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Merchant Produktif',
                        'value' => $this->formatInteger((int) $current['productive_count']),
                    ],
                    [
                        'label' => 'Volume Total',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital EDC gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildQrisPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('jumlah_merchant_qris_detail')) {
                return null;
            }

            $latestPeriod = DB::table('jumlah_merchant_qris_detail')->max(DB::raw('DATE(POSISI)'));
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $salesVolumeExpression = "COALESCE(CAST(NULLIF(REPLACE(AKUMULASI_SV_TOTAL, ',', ''), '') AS DECIMAL(20,2)), 0)";
                $row = DB::table('jumlah_merchant_qris_detail')
                    ->whereDate('POSISI', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(MBDESC))'), $branches)
                    ->selectRaw('COUNT(DISTINCT STOREID) as merchant_count')
                    ->selectRaw("COUNT(DISTINCT CASE WHEN {$salesVolumeExpression} >= 50000 THEN STOREID END) as productive_count")
                    ->selectRaw("COALESCE(SUM({$salesVolumeExpression}), 0) as volume")
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'merchant_count' => (int) ($row->merchant_count ?? 0),
                    'productive_count' => (int) ($row->productive_count ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['merchant_count' => 0, 'productive_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'qris',
                'title' => 'Performance QRIS',
                'subtitle' => 'Sales volume, merchant tercatat, dan merchant produktif dikemas untuk pemantauan cepat.',
                'badge' => 'QRIS',
                'badge_class' => 'badge-info',
                'tone' => 'digital-qris',
                'icon' => 'fas fa-qrcode',
                'link' => route('report.qris'),
                'link_label' => 'Buka report QRIS',
                'current_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'current_label' => 'Sales Volume',
                'secondary_value' => $this->formatInteger((int) $current['merchant_count']),
                'secondary_label' => 'Merchant Tercatat',
                'trend_reference' => $this->formatCurrencyCompact((float) $previous['volume']) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['volume'], (float) $previous['volume']),
                'series' => array_column($timeline, 'volume'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Merchant Produktif',
                        'value' => $this->formatInteger((int) $current['productive_count']),
                    ],
                    [
                        'label' => 'Volume Akumulasi',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital QRIS gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildBrimoPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('user_brimo_rpt_v2') || !Schema::hasTable('user_brimo_fin')) {
                return null;
            }

            $latestRek = DB::table('user_brimo_rpt_v2')->max('posisi');
            $latestFin = DB::table('user_brimo_fin')->max('posisi');
            $latestCandidates = array_filter([$latestRek, $latestFin]);
            if (empty($latestCandidates)) {
                return null;
            }

            $latestPeriod = Carbon::parse(max($latestCandidates))->toDateString();
            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $rekRow = DB::table('user_brimo_rpt_v2')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches)
                    ->selectRaw('COALESCE(SUM(COALESCE(jumlah, 0)), 0) as total')
                    ->selectRaw('COUNT(*) as row_count')
                    ->first();

                $finRow = DB::table('user_brimo_fin')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches)
                    ->selectRaw('COALESCE(SUM(COALESCE(jumlah, 0)), 0) as total')
                    ->selectRaw('COUNT(*) as row_count')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'rekening_total' => (float) ($rekRow->total ?? 0),
                    'rekening_rows' => (int) ($rekRow->row_count ?? 0),
                    'fin_total' => (float) ($finRow->total ?? 0),
                    'fin_rows' => (int) ($finRow->row_count ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['rekening_total' => 0, 'rekening_rows' => 0, 'fin_total' => 0, 'fin_rows' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;
            $currentTotal = (float) $current['rekening_total'] + (float) $current['fin_total'];
            $previousTotal = (float) $previous['rekening_total'] + (float) $previous['fin_total'];

            return $this->buildDigitalCard([
                'key' => 'brimo',
                'title' => 'Performance BRIMO',
                'subtitle' => 'Gabungan Ureg Rekening dan Finansial untuk memantau aktivitas BRIMO Area 6.',
                'badge' => 'BRIMO',
                'badge_class' => 'badge-primary',
                'tone' => 'digital-brimo',
                'icon' => 'fas fa-mobile-alt',
                'link' => route('report.brimo'),
                'link_label' => 'Buka report BRIMO',
                'current_value' => $this->formatCurrencyCompact($currentTotal),
                'current_label' => 'Total Ureg',
                'secondary_value' => $this->formatInteger((int) $current['rekening_rows'] + (int) $current['fin_rows']),
                'secondary_label' => 'Baris Tersedia',
                'trend_reference' => $this->formatCurrencyCompact($previousTotal) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange($currentTotal, $previousTotal),
                'series' => array_map(
                    fn ($item) => (float) ($item['rekening_total'] ?? 0) + (float) ($item['fin_total'] ?? 0),
                    $timeline
                ),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Ureg Rekening',
                        'value' => $this->formatCurrencyCompact((float) $current['rekening_total']),
                    ],
                    [
                        'label' => 'Ureg Finansial',
                        'value' => $this->formatCurrencyCompact((float) $current['fin_total']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital BRIMO gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildBrilinkPerformanceCard(): ?array
    {
        try {
            if (!Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')) {
                return null;
            }

            $latestPeriod = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')->max('periode');
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendMonthPeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->where('periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $branches)
                    ->selectRaw('COUNT(*) as agen')
                    ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 750000 THEN 1 ELSE 0 END) as juragan')
                    ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 150000 THEN 1 ELSE 0 END) as bep')
                    ->selectRaw('COALESCE(SUM(COALESCE(total_transaksi, 0)), 0) as trx')
                    ->selectRaw('COALESCE(SUM(COALESCE(total_nominal, 0)), 0) as volume')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('M Y'),
                    'agen' => (int) ($row->agen ?? 0),
                    'juragan' => (int) ($row->juragan ?? 0),
                    'bep' => (int) ($row->bep ?? 0),
                    'trx' => (float) ($row->trx ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['agen' => 0, 'juragan' => 0, 'bep' => 0, 'trx' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'brilink',
                'title' => 'Performance BRILink',
                'subtitle' => 'Agen, transaksi, dan volume akumulasi disusun untuk melihat produktivitas jaringan.',
                'badge' => 'BRILink',
                'badge_class' => 'badge-success',
                'tone' => 'digital-brilink',
                'icon' => 'fas fa-network-wired',
                'link' => route('report.brilink'),
                'link_label' => 'Buka report BRILink',
                'current_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'current_label' => 'Volume Aktif',
                'secondary_value' => $this->formatInteger((int) $current['agen']),
                'secondary_label' => 'Agen Tercatat',
                'trend_reference' => $this->formatCurrencyCompact((float) $previous['volume']) . ' periode sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['volume'], (float) $previous['volume']),
                'series' => array_column($timeline, 'volume'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Agen Juragan',
                        'value' => $this->formatInteger((int) $current['juragan']),
                    ],
                    [
                        'label' => 'Volume Trx',
                        'value' => $this->formatCurrencyCompact((float) $current['volume']),
                    ],
                    [
                        'label' => 'Transaksi',
                        'value' => $this->formatInteger((int) $current['trx']),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital BRILink gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildPayrollPerformanceCard(): ?array
    {
        try {
            $snapshotCard = $this->buildPayrollPerformanceCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            if (!Schema::hasTable('performance_pis_per_produk')) {
                return null;
            }

            $latestPeriod = DB::table('performance_pis_per_produk')->max('posisi');
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $monthStart = Carbon::parse($period)->startOfMonth()->toDateString();
                $monthEnd = Carbon::parse($period)->endOfMonth()->toDateString();

                $row = DB::table('performance_pis_per_produk')
                    ->whereDate('posisi', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(kanca))'), $branches)
                    ->selectRaw('COUNT(*) as rekening_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(saldo_britama_kerjasama, 0)), 0) as saldo')
                    ->whereBetween('tanggal_pembuatan_rekening', [$monthStart, $monthEnd])
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'rekening_count' => (int) ($row->rekening_count ?? 0),
                    'saldo' => (float) ($row->saldo ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['rekening_count' => 0, 'saldo' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'payroll',
                'title' => 'Performance Lainnya',
                'subtitle' => 'Performance PIS per produk untuk melihat kontribusi payroll dan saldo kerjasama.',
                'badge' => 'PIS',
                'badge_class' => 'badge-warning',
                'tone' => 'digital-payroll',
                'icon' => 'fas fa-briefcase',
                'link' => route('report.kinerja.newpayroll'),
                'link_label' => 'Buka report payroll',
                'current_value' => $this->formatInteger((int) $current['rekening_count']),
                'current_label' => 'Rekening Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['saldo']),
                'secondary_label' => 'Saldo Kerjasama',
                'trend_reference' => $this->formatInteger((int) $previous['rekening_count']) . ' rekening sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['rekening_count'], (float) $previous['rekening_count']),
                'series' => array_column($timeline, 'rekening_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    [
                        'label' => 'Rekening',
                        'value' => $this->formatInteger((int) $current['rekening_count']),
                    ],
                    [
                        'label' => 'Saldo',
                        'value' => $this->formatCurrencyCompact((float) $current['saldo']),
                    ],
                    [
                        'label' => 'Periode',
                        'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    ],
                ],
                'source_updated_at' => $latestPeriod,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital payroll gagal disusun: ' . $e->getMessage());

            return null;
        }
    }

    private function buildPayrollPerformanceCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('performance_new_payroll_snapshots')) {
            return null;
        }

        $latestPeriod = DB::table('performance_new_payroll_snapshots')->max('snapshot_posisi');
        if (!$latestPeriod) {
            return null;
        }

        $branches = $this->dashboardBranchNames();
        $row = DB::table('performance_new_payroll_snapshots')
            ->whereDate('snapshot_posisi', $latestPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(branch))'), $branches)
            ->selectRaw('COALESCE(SUM(COALESCE(rekening_curr, 0)), 0) as rekening_curr')
            ->selectRaw('COALESCE(SUM(COALESCE(rekening_prev, 0)), 0) as rekening_prev')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_curr, 0)), 0) as saldo_curr')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_prev, 0)), 0) as saldo_prev')
            ->first();

        $rekeningCurrent = (int) ($row->rekening_curr ?? 0);
        $rekeningPrevious = (int) ($row->rekening_prev ?? 0);
        $saldoCurrent = (float) ($row->saldo_curr ?? 0);
        $saldoPrevious = (float) ($row->saldo_prev ?? 0);

        return $this->buildDigitalCard([
            'key' => 'payroll',
            'title' => 'Kinerja New Payroll',
            'subtitle' => 'Snapshot rekening dan saldo payroll Area 6.',
            'badge' => 'PIS',
            'badge_class' => 'badge-warning',
            'tone' => 'digital-payroll',
            'icon' => 'fas fa-briefcase',
            'link' => route('report.kinerja.newpayroll'),
            'link_label' => 'Buka report payroll',
            'current_value' => $this->formatInteger($rekeningCurrent),
            'current_label' => 'Rekening Aktif',
            'secondary_value' => $this->formatCurrencyCompact($saldoCurrent),
            'secondary_label' => 'Saldo Kerjasama',
            'trend_reference' => $this->formatInteger($rekeningPrevious) . ' rekening sebelumnya',
            'trend_direction' => $this->percentChange($rekeningCurrent, $rekeningPrevious),
            'series' => [$rekeningPrevious, $rekeningCurrent],
            'series_labels' => ['Sebelumnya', Carbon::parse($latestPeriod)->translatedFormat('d M')],
            'stats' => [
                ['label' => 'Rekening', 'value' => $this->formatInteger($rekeningCurrent)],
                ['label' => 'Saldo', 'value' => $this->formatCurrencyCompact($saldoCurrent)],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $latestPeriod,
            'source_table' => 'performance_new_payroll_snapshots',
            'source_note' => 'Kartu payroll landing memakai snapshot ringkas, bukan agregasi ulang tabel detail.',
        ]);
    }

    private function buildQlolaPerformanceCard(): ?array
    {
        try {
            if (!$this->hasTable('usak_ibbiz_uker') && !$this->hasTable('ibbisniz_corp')) {
                // Return stub card jika tabel tidak ada
                return [
                    'key' => 'qlola',
                    'title' => 'Performance QLola',
                    'subtitle' => 'Platform cash management QLola untuk monitoring likuiditas nasabah korporasi.',
                    'badge' => 'QLOLA',
                    'badge_class' => 'badge-warning',
                    'tone' => 'digital-qlola',
                    'icon' => 'fas fa-university',
                    'link' => route('report.qlola'),
                    'link_label' => 'Buka report QLola',
                    'current_value' => '-',
                    'current_label' => 'Nasabah Aktif',
                    'secondary_value' => '-',
                    'secondary_label' => 'Volume',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Performance QLola', null, 'usak_ibbiz_uker / ibbisniz_corp', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel QLola belum ada.'),
                ];
            }

            $latestUserPeriod = $this->hasTable('usak_ibbiz_uker') ? DB::table('usak_ibbiz_uker')->max('periode') : null;
            $latestCorpPeriod = $this->hasTable('ibbisniz_corp') ? DB::table('ibbisniz_corp')->max('periode') : null;
            $latestPeriod = $latestUserPeriod ?? $latestCorpPeriod;

            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            $usakBranchExpression = "UPPER(TRIM(CASE WHEN LOCATE(' - ', kanca) > 0 THEN SUBSTRING(kanca, LOCATE(' - ', kanca) + 3) ELSE kanca END))";
            $corpBranchExpression = "UPPER(TRIM(CASE WHEN LOCATE(' - ', cabang) > 0 THEN SUBSTRING(cabang, LOCATE(' - ', cabang) + 3) ELSE cabang END))";

            foreach ($periods as $period) {
                $userCount = 0;
                if ($this->hasTable('usak_ibbiz_uker')) {
                    $userCount = DB::table('usak_ibbiz_uker')
                        ->whereDate('periode', $period)
                        ->whereIn(DB::raw($usakBranchExpression), $branches)
                        ->whereIn(DB::raw('UPPER(TRIM(deskripsi))'), ['ACTIVE', 'ACTIVATED'])
                        ->count();
                }

                $volume = 0.0;
                if ($this->hasTable('ibbisniz_corp')) {
                    $volume = (float) DB::table('ibbisniz_corp')
                        ->whereDate('periode', $period)
                        ->whereIn(DB::raw($corpBranchExpression), $branches)
                        ->sum('nominal');
                }

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'nasabah_count' => $userCount,
                    'volume' => $volume,
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['nasabah_count' => 0, 'volume' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'qlola',
                'title' => 'Performance QLola',
                'subtitle' => 'Platform cash management QLola untuk monitoring likuiditas nasabah korporasi.',
                'badge' => 'QLOLA',
                'badge_class' => 'badge-warning',
                'tone' => 'digital-qlola',
                'icon' => 'fas fa-university',
                'link' => route('report.qlola'),
                'link_label' => 'Buka report QLola',
                'current_value' => $this->formatInteger((int) $current['nasabah_count']),
                'current_label' => 'Nasabah Aktif',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['volume']),
                'secondary_label' => 'Volume',
                'trend_reference' => $this->formatInteger((int) $previous['nasabah_count']) . ' nasabah sebelumnya',
                'trend_direction' => $this->percentChange((float) $current['nasabah_count'], (float) $previous['nasabah_count']),
                'series' => array_column($timeline, 'nasabah_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    ['label' => 'Nasabah', 'value' => $this->formatInteger((int) $current['nasabah_count'])],
                    ['label' => 'Volume', 'value' => $this->formatCurrencyCompact((float) $current['volume'])],
                    ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => 'usak_ibbiz_uker / ibbisniz_corp',
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital QLola gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildCasaDebiturKpiCard(): ?array
    {
        try {
            $snapshotCard = $this->buildCasaDebiturKpiCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            // Coba tabel rasio casa debitur
            $tableName = null;
            foreach (['rasio_casa_debitur', 'casa_debitur_summary', 'rekening_transaksi_debitur'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $tableName = $tbl;
                    break;
                }
            }

            if (!$tableName) {
                return [
                    'key' => 'casa',
                    'title' => 'Rasio Casa Debitur',
                    'subtitle' => 'Rasio kepemilikan rekening tabungan oleh debitur aktif Area 6.',
                    'badge' => 'CASA',
                    'badge_class' => 'badge-info',
                    'tone' => 'digital-casa',
                    'icon' => 'fas fa-percentage',
                    'link' => route('report.rasiocasa.debitur'),
                    'link_label' => 'Buka report Casa',
                    'current_value' => '-',
                    'current_label' => 'Rasio Casa',
                    'secondary_value' => '-',
                    'secondary_label' => 'Debitur Aktif',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'value' => '–',
                    'meta' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Rasio Casa Debitur', null, 'rasio_casa_debitur / casa_debitur_summary / rekening_transaksi_debitur', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel CASA belum ada.'),
                ];
            }

            // Cari kolom periode
            $periodCol = Schema::hasColumn($tableName, 'posisi') ? 'posisi'
                : (Schema::hasColumn($tableName, 'periode') ? 'periode' : 'created_at');
            $latestPeriod = DB::table($tableName)->max($periodCol);
            if (!$latestPeriod) {
                return null;
            }

            $row = DB::table($tableName)
                ->where($periodCol, $latestPeriod)
                ->selectRaw('COUNT(*) as total_debitur')
                ->selectRaw('SUM(CASE WHEN COALESCE(flag_casa, 0) = 1 THEN 1 ELSE 0 END) as casa_count')
                ->first();

            $totalDebitur = (int) ($row->total_debitur ?? 0);
            $casaCount = (int) ($row->casa_count ?? 0);
            $rasio = $totalDebitur > 0 ? ($casaCount / $totalDebitur) * 100 : 0;

            return $this->buildDigitalCard([
                'key' => 'casa',
                'title' => 'Rasio Casa Debitur',
                'subtitle' => 'Rasio kepemilikan rekening tabungan oleh debitur aktif Area 6.',
                'badge' => 'CASA',
                'badge_class' => 'badge-info',
                'tone' => 'digital-casa',
                'icon' => 'fas fa-percentage',
                'link' => route('report.rasiocasa.debitur'),
                'link_label' => 'Buka report Casa',
                'current_value' => $this->formatPercent($rasio),
                'current_label' => 'Rasio Casa',
                'secondary_value' => $this->formatInteger($totalDebitur),
                'secondary_label' => 'Total Debitur',
                'trend_reference' => $this->formatInteger($casaCount) . ' debitur punya CASA',
                'value' => $this->formatPercent($rasio),
                'meta' => $this->formatInteger($casaCount) . ' debitur punya CASA',
                'trend_direction' => $rasio - 50,
                'series' => [$rasio],
                'series_labels' => [Carbon::parse($latestPeriod)->translatedFormat('d M')],
                'stats' => [
                    ['label' => 'Debitur CASA', 'value' => $this->formatInteger($casaCount)],
                    ['label' => 'Total Debitur', 'value' => $this->formatInteger($totalDebitur)],
                    ['label' => 'Rasio', 'value' => $this->formatPercent($rasio)],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => $tableName,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital Casa Debitur gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildCasaDebiturKpiCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('rasio_casa_debitur_snapshots')) {
            return null;
        }

        $latestPeriod = DB::table('rasio_casa_debitur_snapshots')->max('loan_period');
        if (!$latestPeriod) {
            return null;
        }

        $summary = DB::table('rasio_casa_debitur_snapshots')
            ->whereDate('loan_period', $latestPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames())
            ->selectRaw('COALESCE(SUM(COALESCE(os_amount, 0)), 0) as os_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(casa_amount, 0)), 0) as casa_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(source_row_count, 0)), 0) as source_row_count')
            ->selectRaw('MAX(casa_period) as casa_period')
            ->first();

        if (!$summary) {
            return null;
        }

        $osAmount = (float) ($summary->os_amount ?? 0);
        $casaAmount = (float) ($summary->casa_amount ?? 0);
        $ratio = $this->percentOf($casaAmount, $osAmount);

        return $this->buildDigitalCard([
            'key' => 'casa',
            'title' => 'Rasio Casa Debitur',
            'subtitle' => 'Snapshot CASA debitur Area 6.',
            'badge' => 'CASA',
            'badge_class' => 'badge-info',
            'tone' => 'digital-casa',
            'icon' => 'fas fa-percentage',
            'link' => route('report.rasiocasa.debitur'),
            'link_label' => 'Buka report Casa',
            'current_value' => $this->formatPercent($ratio),
            'current_label' => 'Rasio Casa',
            'secondary_value' => $this->formatCurrencyCompact($osAmount),
            'secondary_label' => 'OS Debitur',
            'trend_reference' => $this->formatCurrencyCompact($casaAmount) . ' CASA',
            'value' => $this->formatPercent($ratio),
            'meta' => $this->formatCurrencyCompact($casaAmount) . ' CASA',
            'trend_direction' => $ratio,
            'series' => [$ratio],
            'series_labels' => [Carbon::parse($latestPeriod)->translatedFormat('d M')],
            'stats' => [
                ['label' => 'Total CASA', 'value' => $this->formatCurrencyCompact($casaAmount)],
                ['label' => 'OS Debitur', 'value' => $this->formatCurrencyCompact($osAmount)],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $summary->casa_period ?? $latestPeriod,
            'source_table' => 'rasio_casa_debitur_snapshots',
            'source_note' => 'Kartu CASA landing memakai snapshot rasio CASA debitur yang sudah dibangun.',
        ]);
    }

    private function buildRekeningDormantKpiCard(): ?array
    {
        try {
            $snapshotCard = $this->buildRekeningDormantKpiCardFromSnapshot();
            if ($snapshotCard !== null) {
                return $snapshotCard;
            }

            $tableName = null;
            foreach (['rekening_dormant', 'rekening_dormant_detail', 'dormant_summary'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $tableName = $tbl;
                    break;
                }
            }

            if (!$tableName) {
                return [
                    'key' => 'dormant',
                    'title' => 'Rekening Dormant',
                    'subtitle' => 'Monitoring rekening tidak aktif untuk menjaga kualitas DPK Area 6.',
                    'badge' => 'DORMANT',
                    'badge_class' => 'badge-danger',
                    'tone' => 'digital-dormant',
                    'icon' => 'fas fa-bed',
                    'link' => route('report.rekening-dormant'),
                    'link_label' => 'Buka report Dormant',
                    'current_value' => '-',
                    'current_label' => 'Rekening Dormant',
                    'secondary_value' => '-',
                    'secondary_label' => 'Saldo Tertahan',
                    'trend' => '0,0%',
                    'trend_class' => 'text-muted',
                    'trend_value' => 0,
                    'trend_reference' => 'Data belum tersedia',
                    'chart' => ['points' => [], 'path' => '', 'area_path' => ''],
                    'series' => [],
                    'series_labels' => [],
                    'stats' => [
                        ['label' => 'Status', 'value' => 'Menunggu Data'],
                        ['label' => 'Periode', 'value' => '-'],
                        ['label' => 'Link', 'value' => 'Lihat Detail'],
                    ],
                    'source_updated_at' => null,
                    'is_stub' => true,
                    'detail_payload' => $this->buildLandingSourceDetail('Rekening Dormant', null, 'rekening_dormant / rekening_dormant_detail / dormant_summary', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel dormant belum ada.'),
                ];
            }

            $periodCol = Schema::hasColumn($tableName, 'posisi') ? 'posisi'
                : (Schema::hasColumn($tableName, 'periode') ? 'periode' : 'created_at');
            $latestPeriod = DB::table($tableName)->max($periodCol);
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $branchCol = Schema::hasColumn($tableName, 'kanca') ? 'kanca'
                : (Schema::hasColumn($tableName, 'cabang') ? 'cabang' : 'kantor_cabang');
            $saldoCol = Schema::hasColumn($tableName, 'saldo_idr') ? 'saldo_idr'
                : (Schema::hasColumn($tableName, 'saldo') ? 'saldo' : '0');
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table($tableName)
                    ->whereDate($periodCol, $period)
                    ->whereIn(DB::raw('UPPER(TRIM(' . $branchCol . '))'), $branches)
                    ->selectRaw('COUNT(*) as dormant_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(' . $saldoCol . ', 0)), 0) as saldo')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'dormant_count' => (int) ($row->dormant_count ?? 0),
                    'saldo' => (float) ($row->saldo ?? 0),
                    'source_updated_at' => $period,
                ];
            }

            $current = $timeline[array_key_last($timeline)] ?? ['dormant_count' => 0, 'saldo' => 0];
            $previous = $timeline[count($timeline) - 2] ?? $current;

            return $this->buildDigitalCard([
                'key' => 'dormant',
                'title' => 'Rekening Dormant',
                'subtitle' => 'Monitoring rekening tidak aktif untuk menjaga kualitas DPK Area 6.',
                'badge' => 'DORMANT',
                'badge_class' => 'badge-danger',
                'tone' => 'digital-dormant',
                'icon' => 'fas fa-bed',
                'link' => route('report.rekening-dormant'),
                'link_label' => 'Buka report Dormant',
                'current_value' => $this->formatInteger((int) $current['dormant_count']),
                'current_label' => 'Rekening Dormant',
                'secondary_value' => $this->formatCurrencyCompact((float) $current['saldo']),
                'secondary_label' => 'Saldo Tertahan',
                'trend_reference' => $this->formatInteger((int) $previous['dormant_count']) . ' rek. periode sebelumnya',
                'trend_direction' => -$this->percentChange((float) $current['dormant_count'], (float) $previous['dormant_count']),
                'series' => array_column($timeline, 'dormant_count'),
                'series_labels' => array_column($timeline, 'label'),
                'stats' => [
                    ['label' => 'Rekening', 'value' => $this->formatInteger((int) $current['dormant_count'])],
                    ['label' => 'Saldo', 'value' => $this->formatCurrencyCompact((float) $current['saldo'])],
                    ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
                ],
                'source_updated_at' => $latestPeriod,
                'source_table' => $tableName,
            ]);
        } catch (Throwable $e) {
            Log::warning('Dashboard digital Rekening Dormant gagal disusun: ' . $e->getMessage());
            return null;
        }
    }

    private function buildRekeningDormantKpiCardFromSnapshot(): ?array
    {
        if (!Schema::hasTable('rekening_dormant_snapshots')) {
            return null;
        }

        $latestPeriod = DB::table('rekening_dormant_snapshots')->max('posisi');
        if (!$latestPeriod) {
            return null;
        }

        $periods = $this->buildTrendDatePeriods($latestPeriod);
        $timeline = [];

        foreach ($periods as $period) {
            $row = DB::table('rekening_dormant_snapshots')
                ->whereDate('posisi', $period)
                ->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames())
                ->selectRaw('COALESCE(SUM(COALESCE(dormant_count, 0)), 0) as dormant_count')
                ->first();

            $timeline[] = [
                'label' => Carbon::parse($period)->translatedFormat('d M'),
                'dormant_count' => (int) ($row->dormant_count ?? 0),
                'source_updated_at' => $period,
            ];
        }

        $current = $timeline[array_key_last($timeline)] ?? ['dormant_count' => 0];
        $previous = $timeline[count($timeline) - 2] ?? $current;

        return $this->buildDigitalCard([
            'key' => 'dormant',
            'title' => 'Rekening Dormant',
            'subtitle' => 'Snapshot rekening dormant Area 6.',
            'badge' => 'DORMANT',
            'badge_class' => 'badge-danger',
            'tone' => 'digital-dormant',
            'icon' => 'fas fa-bed',
            'link' => route('report.rekening-dormant'),
            'link_label' => 'Buka report Dormant',
            'current_value' => $this->formatInteger((int) $current['dormant_count']),
            'current_label' => 'Rekening Dormant',
            'secondary_value' => '-',
            'secondary_label' => 'Saldo Tertahan',
            'trend_reference' => $this->formatInteger((int) $previous['dormant_count']) . ' rek. periode sebelumnya',
            'trend_direction' => -$this->percentChange((float) $current['dormant_count'], (float) $previous['dormant_count']),
            'series' => array_column($timeline, 'dormant_count'),
            'series_labels' => array_column($timeline, 'label'),
            'stats' => [
                ['label' => 'Rekening', 'value' => $this->formatInteger((int) $current['dormant_count'])],
                ['label' => 'Saldo', 'value' => '-'],
                ['label' => 'Periode', 'value' => Carbon::parse($latestPeriod)->translatedFormat('d M Y')],
            ],
            'source_updated_at' => $latestPeriod,
            'source_table' => 'rekening_dormant_snapshots',
            'source_note' => 'Kartu dormant landing memakai snapshot rekening dormant yang sudah dibangun.',
        ]);
    }

    private function buildTimeseriesPayload(?string $simpananPeriod, ?string $loanPeriod): array
    {
        $cacheKey = 'dashboard_simpanan:timeseries:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . ($simpananPeriod ?? 'null') . ':' . ($loanPeriod ?? 'null');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($simpananPeriod, $loanPeriod) {
            $points = 6;
            $simpananTimeline = [];
            $loanTimeline = [];
            $labels = [];

            // Build simpanan timeseries
            if ($simpananPeriod && (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE) || Schema::hasTable('simpanan_multipn'))) {
                $current = Carbon::parse($simpananPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $actualPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($p)
                        ?? (Schema::hasTable('simpanan_multipn')
                            ? DB::table('simpanan_multipn')->where('posisi', '<=', $p)->max('posisi')
                            : null);
                    if ($actualPeriod) {
                        $sum = $this->buildPeriodSummary($actualPeriod);
                        $simpananTimeline[] = round((float) ($sum['total_balance'] ?? 0) / 1e12, 3);
                        $labels[] = Carbon::parse($actualPeriod)->translatedFormat('M y');
                    } else {
                        $simpananTimeline[] = 0;
                        $labels[] = Carbon::parse($p)->translatedFormat('M y');
                    }
                }
            } else {
                $simpananTimeline = array_fill(0, $points, 0);
                $labels = array_fill(0, $points, '-');
            }

            // Build loan timeseries aligned to same labels
            if ($loanPeriod && (Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE) || Schema::hasTable('daily_loan_dinamis') || Schema::hasTable(self::LOAN_SNAPSHOT_TABLE))) {
                $current = Carbon::parse($loanPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $actualPeriod = $this->resolveHarianSnapshotPeriodOnOrBefore($p);
                    if (!$actualPeriod) {
                        $table = Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)
                            ? self::LOAN_SNAPSHOT_TABLE
                            : (Schema::hasTable('daily_loan_dinamis') ? 'daily_loan_dinamis' : null);
                        $actualPeriod = $table
                            ? DB::table($table)->where('periode', '<=', $p)->max('periode')
                            : null;
                    }
                    if ($actualPeriod) {
                        $sum = $this->buildLoanSummary($actualPeriod);
                        $loanTimeline[] = round((float) ($sum['total_balance'] ?? 0) / 1e12, 3);
                    } else {
                        $loanTimeline[] = 0;
                    }
                }
            } else {
                $loanTimeline = array_fill(0, $points, 0);
            }

            return [
                'labels' => $labels,
                'simpanan' => $simpananTimeline,
                'pinjaman' => $loanTimeline,
            ];
        });
    }

    private function buildDigitalCard(array $card): array
    {
        $series = collect(data_get($card, 'series', []))
            ->map(fn ($value) => (float) $value)
            ->values()
            ->all();

        $chart = $this->buildChartPoints($series);
        $currentSeriesValue = !empty($series) ? (float) $series[array_key_last($series)] : 0;
        $previousSeriesValue = count($series) > 1 ? (float) $series[count($series) - 2] : 0;
        $trend = $this->percentChange($currentSeriesValue, $previousSeriesValue);
        $detailPayload = $card['detail_payload'] ?? $this->buildDigitalCardDetail($card);

        return array_merge($card, [
            'trend' => $this->formatSignedPercent($trend),
            'trend_class' => $this->deltaClass($trend),
            'trend_value' => $trend,
            'chart' => $chart,
            'series' => $series,
            'detail_payload' => $detailPayload,
        ]);
    }

    private function buildDigitalCardDetail(array $card): array
    {
        $sourceTable = (string) ($card['source_table'] ?? $this->defaultDigitalSourceTable((string) ($card['key'] ?? '')));
        $rows = [
            ['label' => (string) ($card['current_label'] ?? 'Nilai utama'), 'value' => (string) ($card['current_value'] ?? '-'), 'source' => $sourceTable],
            ['label' => (string) ($card['secondary_label'] ?? 'Nilai pembanding'), 'value' => (string) ($card['secondary_value'] ?? '-'), 'source' => $sourceTable],
            ['label' => 'Trend', 'value' => (string) ($card['trend_reference'] ?? '-'), 'source' => $sourceTable],
        ];

        foreach (array_slice((array) ($card['stats'] ?? []), 0, 5) as $stat) {
            $rows[] = [
                'label' => (string) data_get($stat, 'label', '-'),
                'value' => (string) data_get($stat, 'value', '-'),
                'source' => $sourceTable,
            ];
        }

        return $this->buildLandingSourceDetail(
            (string) ($card['title'] ?? $card['badge'] ?? 'Detail'),
            (string) ($card['source_updated_at'] ?? ''),
            $sourceTable,
            $rows,
            (string) ($card['source_note'] ?? 'Angka diambil dari agregasi tabel sumber dengan filter cabang Area 6 bila tersedia.')
        );
    }

    private function defaultDigitalSourceTable(string $key): string
    {
        return match ($key) {
            'edc' => 'jumlah_merchant_detail',
            'qris' => 'jumlah_merchant_qris_detail',
            'brimo' => 'user_brimo_rpt_v2 + user_brimo_fin',
            'brilink' => 'brilink_web_laporan_summary_transaksi_brilink_web',
            'payroll' => 'performance_pis_per_produk',
            'qlola' => 'qlola_detail / qlola_report / qlola_summary',
            'casa' => 'rasio_casa_debitur / casa_debitur_summary / rekening_transaksi_debitur',
            'dormant' => 'rekening_dormant / rekening_dormant_detail / dormant_summary',
            default => 'Sumber belum dipetakan',
        };
    }

    private function buildTrendDatePeriods(string $latestPeriod, int $points = 4): array
    {
        $current = Carbon::parse($latestPeriod)->startOfDay();
        $periods = [];

        for ($offset = $points - 1; $offset >= 0; $offset--) {
            if ($offset === 0) {
                $periods[] = $current->toDateString();
                continue;
            }

            $periods[] = $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();
        }

        return array_values(array_unique($periods));
    }

    private function buildTrendMonthPeriods(string $latestPeriod, int $points = 4): array
    {
        $current = Carbon::parse($latestPeriod)->startOfMonth();
        $periods = [];

        for ($offset = $points - 1; $offset >= 0; $offset--) {
            $periods[] = $current->copy()->subMonthsNoOverflow($offset)->format('F Y');
        }

        return array_values(array_unique($periods));
    }

    private function buildChartPoints(array $series, int $width = 160, int $height = 48): array
    {
        $values = array_values(array_map(fn ($value) => max(0, (float) $value), $series));
        if (empty($values)) {
            $values = [0, 0, 0, 0];
        }

        $count = count($values);
        $paddingX = 8;
        $paddingY = 6;
        $usableWidth = max(1, $width - ($paddingX * 2));
        $usableHeight = max(1, $height - ($paddingY * 2));
        $max = max(max($values), 1);
        $min = min($values);
        $points = [];

        foreach ($values as $index => $value) {
            $x = $count > 1 ? $paddingX + ($usableWidth * ($index / ($count - 1))) : ($width / 2);
            $normalized = $max === $min ? 0.5 : (($value - $min) / ($max - $min));
            $y = $height - $paddingY - ($normalized * $usableHeight);
            $points[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'value' => $value,
            ];
        }

        $path = 'M ' . implode(' L ', array_map(fn ($point) => $point['x'] . ' ' . $point['y'], $points));
        $lastPoint = end($points);
        $firstPoint = reset($points);
        $areaPath = $path;

        if ($firstPoint && $lastPoint) {
            $areaPath .= ' L ' . $lastPoint['x'] . ' ' . ($height - $paddingY);
            $areaPath .= ' L ' . $firstPoint['x'] . ' ' . ($height - $paddingY) . ' Z';
        }

        return [
            'points' => $points,
            'path' => $path,
            'area_path' => $areaPath,
            'max' => $max,
            'min' => $min,
        ];
    }

    private function dashboardBranchNames(): array
    {
        static $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

        return $branches;
    }

    private function percentChange(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return (((float) $current - (float) $previous) / (float) $previous) * 100;
    }

    private function percentOf(float|int $value, float|int $total): float
    {
        if ((float) $total === 0.0) {
            return 0.0;
        }

        return ((float) $value / (float) $total) * 100;
    }

    private function formatInteger(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 1, ',', '.') . '%';
    }

    private function formatPercentTwo(float $value): string
    {
        return number_format($value, 2, ',', '.') . '%';
    }

    private function formatSignedPercent(float $value): string
    {
        $prefix = $value > 0 ? '+' : '';

        return $prefix . number_format($value, 2, ',', '.') . '%';
    }

    private function formatCurrencyCompact(float $value): string
    {
        $abs = abs($value);

        if ($abs >= 1000000000000) {
            return 'Rp' . number_format($value / 1000000000000, 2, ',', '.') . ' T';
        }

        if ($abs >= 1000000000) {
            return 'Rp' . number_format($value / 1000000000, 2, ',', '.') . ' M';
        }

        if ($abs >= 1000000) {
            return 'Rp' . number_format($value / 1000000, 2, ',', '.') . ' Jt';
        }

        return 'Rp' . number_format($value, 0, ',', '.');
    }

    private function formatCurrencyFull(float $value): string
    {
        return 'Rp' . number_format($value, 0, ',', '.');
    }

    private function formatRatio(float $numerator, float $denominator): string
    {
        if ($denominator == 0.0) {
            return '0,00x';
        }

        return number_format($numerator / $denominator, 2, ',', '.') . 'x';
    }

    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        return Carbon::parse($period)->translatedFormat('d M Y');
    }

    private function simplifyBranchLabel(string $branch): string
    {
        $label = preg_replace('/^\d+\s*--\s*/', '', $branch) ?? $branch;
        $label = preg_replace('/\(.+\)$/', '', $label) ?? $label;

        return trim($label);
    }

    private function deltaClass(float $value, bool $badgeCompatible = false): string
    {
        if ($value > 0) {
            return $badgeCompatible ? 'badge-success' : 'text-success';
        }

        if ($value < 0) {
            return $badgeCompatible ? 'badge-danger' : 'text-danger';
        }

        return $badgeCompatible ? 'badge-secondary' : 'text-muted';
    }

    private function reportCacheVersion(): int
    {
        return ReportCacheVersion::composite(['simpanan', 'pinjaman', 'harian']);
    }
}
