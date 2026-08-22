<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Jobs\SyncImportedReportJob;
use App\Support\LoanQualityBucketMapper;
use App\Support\RkaLookupService;
use App\Support\ReportCacheVersion;
use App\Support\StrictDateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KinerjaRmReportController extends Controller
{
    private const DEFAULT_TITLE = 'Performance Per RM';
    private const SEGMENT_LABEL = 'KPR';
    private const SOURCE_TABLE = 'daily_loan_dinamis';
    private const SNAPSHOT_TABLE = 'performance_rm_snapshots';
    
    // Mapping segmen ke product options
    private const SEGMENT_PRODUCT_MAP = [
        'CONSUMER' => ['CONSUMER'],
        'SMALL' => ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'SMALL'],
        'MICRO' => ['BRIGUNA-MIKRO', 'KUPEDES', 'KUR-MIKRO', 'CASHCOLLATERAL', 'KPR', 'KUR-SMALL'],
    ];
    
    private const AVAILABLE_SEGMENTS = ['CONSUMER', 'SMALL'];
    private const DEFAULT_SEGMENT = 'CONSUMER';
    private const SMALL_RM_CATEGORIES = [
        'KC' => 'RM KC',
        'KCP' => 'RM KCP',
    ];
    private const CONSUMER_MONTHLY_TARGETS = [
        'ARISSULISTYAWAN' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
        'ZULFAENDYCRISMANA' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
        'RATNADWISISWIYANTORO' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
        'RIDHOARDIANTO' => ['target_jg_deb' => 15, 'target_jg_os' => 3700000000.0],
        'DIMASPERDANAHADIWIJAYA' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
        'RONSROHANATALIBATA' => ['target_jg_deb' => 20, 'target_jg_os' => 3750000000.0],
        'RONAROHANATALIBATA' => ['target_jg_deb' => 20, 'target_jg_os' => 3750000000.0],
        'ARDINI' => ['target_jg_deb' => 20, 'target_jg_os' => 3850000000.0],
        'NAVANYOGAPRATAMA' => ['target_jg_deb' => 16, 'target_jg_os' => 1900000000.0],
        'MUHAMADSYAMSUDINHIMAWIJAYA' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
        'BAGUSPRASETYO' => ['target_jg_deb' => 20, 'target_jg_os' => 3750000000.0],
        'ARIANISETYOPALUPI' => ['target_jg_deb' => 20, 'target_jg_os' => 3750000000.0],
        'TITINOKTAVIA' => ['target_jg_deb' => 20, 'target_jg_os' => 3850000000.0],
        'FARIDRAMOLDONI' => ['target_jg_deb' => 19, 'target_jg_os' => 3700000000.0],
    ];

    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    public function index(Request $request): View
    {
        $availablePeriods = $this->fetchAvailablePeriods();
        $selectedSegmen = $this->resolveSelectedSegmen($request->input('segmen'));
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'))
            ?? $availablePeriods->first()
            ?? Carbon::now()->toDateString();
        $this->queueDailyLoanSnapshotSyncIfNeeded($selectedPeriod, static::class . '::index');

        $availableCabangs = $this->fetchAvailableCabangsBySegmen($selectedSegmen);
        $selectedCabang = $this->resolveSelectedCabang($availableCabangs, $request->input('cabang1'));

        $selectedProduct = $this->resolveSelectedProduct($request->input('produk'), $selectedSegmen);
        $selectedRmCategory = $this->resolveSelectedRmCategory($selectedSegmen, $request->input('kategori_rm'));

        $currentDate = Carbon::parse($selectedPeriod);
        $comparisonPeriods = $this->resolveKinerjaComparisonPeriods($this->fetchComparisonPeriods(), $selectedPeriod);
        $realisasiPeriod = $this->resolveKinerjaRealisasiPeriod($selectedPeriod, $comparisonPeriods);

        $retailPerformance = $this->fetchRetailRealizationPerformance(
            $selectedSegmen,
            $selectedPeriod,
            $selectedCabang,
            $selectedProduct,
            $selectedRmCategory
        );
        $osRows = $this->fetchBranchRows($selectedSegmen, $selectedPeriod, $comparisonPeriods, $realisasiPeriod, $selectedCabang, $selectedProduct, null, $selectedRmCategory, null, true);
        $detailedQualityRows = $this->fetchDetailedQualityRows(
            $selectedSegmen,
            $selectedPeriod,
            $comparisonPeriods,
            $selectedCabang,
            $selectedProduct,
            $selectedRmCategory
        );
        $qualitySeries = ['os' => $osRows];
        foreach (['lancar', 'lr', 'lnr', 'account_restruk', 'sml_1', 'sml_2', 'sml_3', 'kl', 'd1', 'd2', 'm'] as $qualityType) {
            $qualitySeries[$qualityType] = $this->fetchBranchRows(
                $selectedSegmen,
                $selectedPeriod,
                $comparisonPeriods,
                $realisasiPeriod,
                $selectedCabang,
                $selectedProduct,
                $qualityType,
                $selectedRmCategory,
                $detailedQualityRows,
                true
            );
        }
        $nextMonth = $currentDate->copy()->addMonthNoOverflow();

        $productOptions = self::SEGMENT_PRODUCT_MAP[$selectedSegmen] ?? [];

        $viewData = [
            'title' => self::DEFAULT_TITLE,
            'availablePeriods' => $availablePeriods,
            'availableSegmens' => self::AVAILABLE_SEGMENTS,
            'selectedSegmen' => $selectedSegmen,
            'latestPeriodLabel' => $availablePeriods->first()
                ? Carbon::parse($availablePeriods->first())->translatedFormat('d M Y')
                : '-',
            'availableCabangs' => $availableCabangs,
            'availableProducts' => $productOptions,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $currentDate->translatedFormat('d M Y'),
            'selectedPeriodShortLabel' => $currentDate->translatedFormat('d M y'),
            'selectedCabang' => $selectedCabang,
            'selectedCabangLabel' => $selectedCabang !== null ? $selectedCabang : 'Semua Cabang',
            'selectedProduct' => $selectedProduct,
            'selectedProductLabel' => $selectedProduct ?? 'Semua Produk',
            'availableRmCategories' => self::SMALL_RM_CATEGORIES,
            'selectedRmCategory' => $selectedRmCategory,
            'comparisonColumns' => array_values($comparisonPeriods),
            'comparisonPeriods' => $comparisonPeriods,
            'realisasiPeriod' => $realisasiPeriod,
            'realisasiPeriodLabel' => Carbon::parse($realisasiPeriod)->translatedFormat('d M Y'),
            'currentMonthLabel' => $currentDate->format('M-y'),
            'nextMonthLabel' => $nextMonth->format('M-y'),
            'rows' => $osRows['rows'],
            'total' => $osRows['total'],
            'performanceMonths' => $retailPerformance['months'],
            'performanceRows' => $retailPerformance['rows'],
            'performanceTotal' => $retailPerformance['total'],
            'performanceMeta' => $retailPerformance['meta'],
            'qualitySeries' => $qualitySeries,
            'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmountInJuta($value, 0),
            'formatSignedAmount' => fn ($value, bool $showArrow = true, int $decimals = 0) => $this->formatSignedAmountInJuta($value, $showArrow, 0),
            'formatCount' => fn ($value) => $this->formatCount($value),
            'formatPlainAmount' => fn ($value) => $this->formatPlainAmountInJuta($value),
            'formatPlainCount' => fn ($value) => $this->formatPlainCount($value),
            'formatPlainDelta' => fn ($value) => $this->formatPlainDeltaInJuta($value),
            'formatPercent' => fn ($value, int $decimals = 2) => $this->formatPercent($value, 2),
            'quadrantLabel' => fn ($quadrant) => $this->formatQuadrantLabel($quadrant),
            'quadrantClass' => fn ($quadrant) => $this->formatQuadrantClass($quadrant),
        ];

        if ($request->ajax()) {
            $this->releaseSessionLockIfNeeded();
            return view('report.kinerjarm-table', $viewData);
        }

        return view('report.kinerjarm', $viewData);
    }

    public function historyDetails(Request $request): View
    {
        $rm = $request->input('rm');
        $segmen = $request->input('segmen');
        $selectedPeriod = $request->input('periode');
        [$historyStart, $historyEnd] = $this->resolveHistoryDateRange((string) $selectedPeriod);
        $historyRangeLabel = Carbon::parse($historyStart)->translatedFormat('M Y')
            . ' - '
            . Carbon::parse($historyEnd)->translatedFormat('M Y');
        $selectedHistoryYear = Carbon::parse($selectedPeriod)->year;

        if (strtoupper(trim((string) $segmen)) === 'CONSUMER') {
            return view('report.kinerjarm-detail-modal', [
                'rm' => $rm,
                'segmen' => $segmen,
                'details' => $this->fetchConsumerNetDisbursementHistoryDetails((string) $rm, (string) $selectedPeriod),
                'detailMode' => 'consumer_surplus',
                'historyRangeLabel' => $historyRangeLabel,
                'selectedHistoryYear' => $selectedHistoryYear,
                'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmountInJuta($value, $decimals),
                'formatPercent' => fn ($value, int $decimals = 2) => $this->formatPercent($value, 2),
            ]);
        }

        if (strtoupper(trim((string) $segmen)) === 'SMALL') {
            $smallDetails = $this->fetchSmallHistoryDetails((string) $rm, (string) $selectedPeriod);

            if ($smallDetails->isNotEmpty()) {
                return view('report.kinerjarm-detail-modal', [
                    'rm' => $rm,
                    'segmen' => $segmen,
                    'details' => $smallDetails,
                    'smallSummariesByYear' => $this->buildSmallHistorySummaries($smallDetails, (string) $selectedPeriod),
                    'historyRangeLabel' => $historyRangeLabel,
                    'selectedHistoryYear' => $selectedHistoryYear,
                    'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmountInJuta($value, $decimals),
                    'formatPercent' => fn ($value, int $decimals = 2) => $this->formatPercent($value, 2),
                ]);
            }
        }

        $history = DB::table('performance_rm_snapshots')
            ->where('rm', $rm)
            ->where('segmen', $segmen)
            ->whereBetween('periode', [$historyStart, $historyEnd])
            ->orderByDesc('periode')
            ->get();
            
        // Group by Month and Branch
        $groups = $history->groupBy(function ($row) {
            return Carbon::parse($row->periode)->format('Y-m') . '|' . $row->cabang;
        });

        $details = $groups->map(function ($group) {
            // Pick the latest date in this month-branch group
            $latestDate = $group->first()->periode;
            $latestDateRows = $group->where('periode', $latestDate);

            $loanOs = $latestDateRows->sum('loan_os');
            $smlOs = $latestDateRows->sum('sml_os');
            $nplOs = $latestDateRows->sum('npl_os');
            $restrukOs = $latestDateRows->sum('restruk_os');
            $realisasiOs = $latestDateRows->sum('realisasi_os');

            $lar = (float)$restrukOs + (float)$smlOs + (float)$nplOs;
            $pctLar = $loanOs > 0 ? ($lar / $loanOs) * 100 : 0;
            
            // Re-calculate A/B (Target 1600M)
            $isRealizA = ($realisasiOs / 1000000) >= 1600;
            $isLarA = $pctLar < 17.5;
            
            return [
                'periode' => Carbon::parse($latestDate)->translatedFormat('M Y'),
                'periode_raw' => $latestDate,
                'year' => Carbon::parse($latestDate)->year,
                'cabang' => $group->first()->cabang,
                'loan_os' => $loanOs,
                'lar_value' => $lar,
                'realisasi_os' => $realisasiOs,
                'penc_realisasi' => $isRealizA ? 'A' : 'B',
                'pct_lar' => $pctLar,
                'penc_lar' => $isLarA ? 'A' : 'B',
                'sort_date' => $latestDate
            ];
        })->filter(function (array $detail) {
            return abs((float) $detail['lar_value']) > 0
                || abs((float) $detail['realisasi_os']) > 0
                || abs((float) $detail['pct_lar']) > 0;
        })->sortBy([
            ['sort_date', 'asc'],
            ['cabang', 'asc'],
        ])->values();
        
        return view('report.kinerjarm-detail-modal', [
            'rm' => $rm,
            'segmen' => $segmen,
            'details' => $details,
            'historyRangeLabel' => $historyRangeLabel,
            'selectedHistoryYear' => $selectedHistoryYear,
            'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmountInJuta($value, $decimals),
            'formatPercent' => fn ($value, int $decimals = 2) => $this->formatPercent($value, 2),
        ]);
    }

    private function resolveHistoryDateRange(string $selectedPeriod): array
    {
        $selectedDate = Carbon::parse($selectedPeriod);

        return [
            $selectedDate->copy()->subYearNoOverflow()->startOfYear()->toDateString(),
            $selectedDate->toDateString(),
        ];
    }

    private function fetchSmallHistoryDetails(string $rm, string $selectedPeriod): Collection
    {
        if (!Schema::hasTable(self::SOURCE_TABLE)) {
            return collect();
        }

        [$historyStart, $periodEnd] = $this->resolveHistoryDateRange($selectedPeriod);
        $rmKeys = $this->smallRmLookupKeys($rm);

        $periods = DB::table(self::SOURCE_TABLE)
            ->whereBetween('periode', [$historyStart, $periodEnd])
            ->where('segmen_kinerja', 'SMALL')
            ->whereIn('produk_kinerja', ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL', 'SMALL'])
            ->whereIn('rm_normalized', $rmKeys)
            ->select('periode')
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($period) => (string) $period)
            ->all();

        $latestByMonth = [];
        foreach ($periods as $period) {
            $latestByMonth[substr($period, 0, 7)] = $period;
        }

        $targetPeriods = array_values($latestByMonth);
        if (empty($targetPeriods)) {
            return collect();
        }

        $realisasiDateColumn = Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
        $realisasiDateExpression = $this->performanceRmEffectiveRealisasiDateSql($realisasiDateColumn, 'periode');

        $dbRows = DB::table(self::SOURCE_TABLE)
            ->whereIn('periode', $targetPeriods)
            ->where('segmen_kinerja', 'SMALL')
            ->whereIn('produk_kinerja', ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL', 'SMALL'])
            ->whereIn('rm_normalized', $rmKeys)
            ->selectRaw('periode')
            ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as loan_os')
            ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
            ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
            ->selectRaw("SUM(CASE WHEN kolek = 1 AND UPPER(TRIM(COALESCE(flag_restruk, ''))) = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw(
                "SUM(CASE WHEN {$realisasiDateExpression} BETWEEN DATE_FORMAT(periode, \"%Y-%m-01\") AND periode THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os"
            )
            ->groupBy('periode', 'cabang')
            ->get();

        return $dbRows->map(function ($row) {
            $loanOs = (float) $row->loan_os;
            $smlOs = (float) $row->sml_os;
            $nplOs = (float) $row->npl_os;
            $restrukOs = (float) $row->restruk_os;
            $realisasiOs = (float) $row->realisasi_os;

            $lar = $restrukOs + $smlOs + $nplOs;
            $pctLar = $loanOs > 0 ? ($lar / $loanOs) * 100 : 0;

            $isRealizA = ($realisasiOs / 1000000) >= 1600;
            $isLarA = $pctLar < 17.5;

            return [
                'periode' => Carbon::parse($row->periode)->translatedFormat('M Y'),
                'periode_raw' => $row->periode,
                'year' => Carbon::parse($row->periode)->year,
                'cabang' => $row->cabang,
                'loan_os' => $loanOs,
                'lar_value' => $lar,
                'realisasi_os' => $realisasiOs,
                'penc_realisasi' => $isRealizA ? 'A' : 'B',
                'pct_lar' => $pctLar,
                'penc_lar' => $isLarA ? 'A' : 'B',
                'sort_date' => $row->periode
            ];
        })->filter(function (array $detail) {
            return abs((float) $detail['lar_value']) > 0
                || abs((float) $detail['realisasi_os']) > 0
                || abs((float) $detail['pct_lar']) > 0;
        })->sortBy([
            ['sort_date', 'asc'],
            ['cabang', 'asc'],
        ])->values();
    }

    private function buildSmallHistorySummaries(Collection $details, string $selectedPeriod): array
    {
        $closedThrough = $this->smallClosedThroughDate($selectedPeriod);

        return $details
            ->filter(function (array $detail) use ($closedThrough): bool {
                $period = Carbon::parse((string) ($detail['periode_raw'] ?? ''));

                return $period->lte($closedThrough) && $period->isLastOfMonth();
            })
            ->groupBy(fn (array $detail): string => (string) ($detail['year'] ?? Carbon::parse($detail['periode_raw'])->year))
            ->map(function (Collection $yearDetails): array {
                $monthlyDetails = $yearDetails
                    ->groupBy(fn (array $detail): string => (string) $detail['periode_raw'])
                    ->sortKeys();
                $monthCount = $monthlyDetails->count();
                $ratasRealisasiOs = $monthCount > 0
                    ? ((float) $monthlyDetails->sum(fn (Collection $month): float => (float) $month->sum('realisasi_os'))) / $monthCount
                    : 0.0;
                $lastClosedPeriod = (string) $monthlyDetails->keys()->last();
                $lastClosedDetails = $monthlyDetails->get($lastClosedPeriod, collect());
                $loanOs = (float) $lastClosedDetails->sum('loan_os');
                $larValue = (float) $lastClosedDetails->sum('lar_value');
                $larPct = $loanOs > 0 ? ($larValue / $loanOs) * 100 : 0.0;

                return [
                    'month_count' => $monthCount,
                    'closed_period' => $lastClosedPeriod,
                    'closed_period_label' => $lastClosedPeriod !== ''
                        ? Carbon::parse($lastClosedPeriod)->translatedFormat('M Y')
                        : '-',
                    'realisasi_os' => $ratasRealisasiOs,
                    'penc_realisasi' => ($ratasRealisasiOs / 1000000) >= 1600 ? 'A' : 'B',
                    'pct_lar' => $larPct,
                    'penc_lar' => $larPct < 17.5 ? 'A' : 'B',
                ];
            })
            ->all();
    }

    private function smallRmLookupKeys(string $rm): array
    {
        $normalized = strtoupper(trim($rm));
        $keys = [$normalized];

        if (str_starts_with($normalized, '00385844 - GLAGAH')) {
            $keys[] = '00385844 -';
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private function performanceRmEffectiveRealisasiDateSql(string $dateColumn, string $periodColumn): string
    {
        return $dateColumn;
    }

    private function fetchConsumerNetDisbursementHistoryDetails(string $rm, string $selectedPeriod): Collection
    {
        if (!Schema::hasTable(self::SOURCE_TABLE)) {
            return collect();
        }

        [$historyStart, $periodEnd] = $this->resolveHistoryDateRange($selectedPeriod);
        $rmKeys = $this->consumerRmLookupKeys($rm);
        $target = $this->resolveConsumerMonthlyTargetForRm($rm);

        $periods = DB::table(self::SOURCE_TABLE)
            ->whereBetween('periode', [$historyStart, $periodEnd])
            ->whereIn('segmen_kinerja', ['CONSUMER'])
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereIn('rm_normalized', $rmKeys)
            ->select('periode')
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($period) => (string) $period)
            ->all();

        $latestByMonth = [];
        foreach ($periods as $period) {
            $latestByMonth[substr($period, 0, 7)] = $period;
        }

        $details = collect();
        foreach (array_values($latestByMonth) as $period) {
            $previousPeriod = $this->resolvePreviousMonthSourcePeriod($period);
            if ($previousPeriod === null) {
                continue;
            }

            $previousByProduct = DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $previousPeriod)
                ->where('segmen', 'CONSUMER')
                ->where('rm', $rm)
                ->get()
                ->keyBy(fn ($row): string => strtoupper(trim((string) ($row->produk ?? ''))));

            $monthlySnapshots = DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->where('segmen', 'CONSUMER')
                ->where('rm', $rm)
                ->orderBy('produk')
                ->get();

            foreach ($monthlySnapshots as $snapshot) {
                $product = $this->normalizeProductLabel((string) ($snapshot->produk ?? ''), 'CONSUMER')
                    ?? strtoupper(trim((string) ($snapshot->produk ?? '')));
                $previous = $previousByProduct->get(strtoupper(trim((string) ($snapshot->produk ?? ''))));

                $details->push([
                    'periode' => Carbon::parse($period)->translatedFormat('d M Y'),
                    'periode_raw' => $period,
                    'year' => Carbon::parse($period)->year,
                    'previous_period' => Carbon::parse($previousPeriod)->translatedFormat('d M Y'),
                    'account' => '',
                    'nama_debitur' => '',
                    'movement' => 'Rekap Bulanan',
                    'is_summary' => true,
                    'debitur' => (int) ($snapshot->realisasi_deb ?? 0),
                    'current_debitur' => (int) ($snapshot->total_deb ?? 0),
                    'previous_debitur' => (int) ($previous->total_deb ?? 0),
                    'cabang' => trim((string) ($snapshot->cabang ?? '')),
                    'unit' => trim((string) ($snapshot->unit ?? '')),
                    'produk' => $product,
                    'previous_plafon' => (float) ($previous->loan_os ?? 0),
                    'current_plafon' => (float) ($snapshot->loan_os ?? 0),
                    'previous_os' => (float) ($previous->loan_os ?? 0),
                    'current_os' => (float) ($snapshot->loan_os ?? 0),
                    'delta_os' => (float) ($snapshot->realisasi_os ?? 0),
                    'surplus_plafon' => (float) ($snapshot->realisasi_os ?? 0),
                    'target_jg_deb' => (int) ($target['target_jg_deb'] ?? 0),
                    'target_jg_os' => (float) ($target['target_jg_os'] ?? 0.0),
                ]);
            }
        }

        return $details->sortBy([
            ['periode_raw', 'asc'],
            ['is_summary', 'desc'],
            ['surplus_plafon', 'desc']
        ])->values();
    }

    private function resolvePreviousMonthSourcePeriod(string $period): ?string
    {
        $periodDate = Carbon::parse($period);
        $previousEnd = $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $exists = DB::table(self::SOURCE_TABLE)
            ->where('periode', $previousEnd)
            ->exists();

        return $exists ? $previousEnd : null;
    }

    /**
     * @param array{target_jg_deb:int, target_jg_os:float} $target
     */
    private function fetchConsumerSurplusAccountDetails(array $rmKeys, string $period, string $previousPeriod, array $target): Collection
    {
        $productSql = "CASE WHEN produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' ELSE produk_kinerja END";
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $realisasiDateColumn = Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
        $realisasiDateExpression = $this->performanceRmEffectiveRealisasiDateSql($realisasiDateColumn, 'periode');

        $currentRows = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereIn('segmen_kinerja', ['CONSUMER'])
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereIn('rm_normalized', $rmKeys)
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), '') as cabang")
            ->selectRaw("COALESCE(unit_normalized, UPPER(TRIM(unit1)), '') as unit")
            ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
            ->selectRaw("COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), '') as rm")
            ->selectRaw("{$productSql} as produk")
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->selectRaw('nomor_rekening1')
            ->selectRaw('MAX(nama_debitur1) as nama_debitur1')
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as current_os')
            ->selectRaw("MAX(CASE WHEN {$realisasiDateExpression} BETWEEN ? AND ? THEN 1 ELSE 0 END) as is_current_month_realization", [$periodStart, $period])
            ->groupByRaw("COALESCE(cabang_normalized, UPPER(TRIM(cabang1)), ''), COALESCE(unit_normalized, UPPER(TRIM(unit1)), ''), COALESCE(branch_normalized, ''), COALESCE(rm_normalized, UPPER(TRIM(pn_pengelola1)), ''), {$productSql}, UPPER(TRIM(nomor_rekening1)), nomor_rekening1")
            ->get();

        $accountKeys = $currentRows
            ->pluck('account_key')
            ->map(fn ($value): string => (string) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($accountKeys === []) {
            return collect();
        }

        $previousOsByAccount = DB::table(self::SOURCE_TABLE)
            ->where('periode', $previousPeriod)
            ->whereIn('segmen_kinerja', ['CONSUMER'])
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereIn(DB::raw('UPPER(TRIM(nomor_rekening1))'), $accountKeys)
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as previous_os')
            ->groupBy('account_key')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) ($row->account_key ?? '') => (float) $row->previous_os]);

        return $currentRows
            ->map(function ($row) use ($previousOsByAccount, $period, $previousPeriod, $target) {
                $accountKey = (string) ($row->account_key ?? '');
                $hasPreviousAccount = $previousOsByAccount->has($accountKey);
                $currentOs = (float) ($row->current_os ?? 0);
                $previousOs = $hasPreviousAccount ? (float) $previousOsByAccount[$accountKey] : 0.0;
                $isCurrentMonthRealization = (int) ($row->is_current_month_realization ?? 0) === 1;
                $netDisbursement = $hasPreviousAccount
                    ? ($currentOs - $previousOs)
                    : ($isCurrentMonthRealization ? $currentOs : 0.0);

                if (abs($netDisbursement) <= 0.0001) {
                    return null;
                }

                return [
                    'periode' => Carbon::parse($period)->translatedFormat('d M Y'),
                    'periode_raw' => $period,
                    'year' => Carbon::parse($period)->year,
                    'previous_period' => Carbon::parse($previousPeriod)->translatedFormat('d M Y'),
                    'account' => (string) ($row->nomor_rekening1 ?? ''),
                    'debitur' => $netDisbursement > 0 ? 1 : 0,
                    'nama_debitur' => (string) ($row->nama_debitur1 ?? ''),
                    'movement' => $hasPreviousAccount
                        ? ($netDisbursement > 0 ? 'Suplesi / OS Naik' : 'Turun Pokok / OS Turun')
                        : 'Realisasi Baru',
                    'is_summary' => false,
                    'current_debitur' => 1,
                    'previous_debitur' => $hasPreviousAccount ? 1 : 0,
                    'cabang' => trim((string) ($row->cabang ?? '')),
                    'unit' => trim((string) ($row->unit ?? '')),
                    'produk' => $this->normalizeProductLabel((string) ($row->produk ?? ''), 'CONSUMER')
                        ?? strtoupper(trim((string) ($row->produk ?? ''))),
                    'previous_plafon' => $previousOs,
                    'current_plafon' => $currentOs,
                    'previous_os' => $previousOs,
                    'current_os' => $currentOs,
                    'delta_os' => $netDisbursement,
                    'surplus_plafon' => $netDisbursement,
                    'target_jg_deb' => (int) ($target['target_jg_deb'] ?? 0),
                    'target_jg_os' => (float) ($target['target_jg_os'] ?? 0.0),
                ];
            })
            ->filter()
            ->sortByDesc('surplus_plafon')
            ->values();
    }

    /**
     * @return array{target_jg_deb:int, target_jg_os:float}
     */
    private function resolveConsumerMonthlyTargetForRm(string $rm): array
    {
        $manualTargets = Schema::hasTable('performance_targets')
            ? DB::table('performance_targets')
                ->get()
                ->groupBy('category')
                ->map(fn ($items) => $items->keyBy('rm_name'))
            : collect();
        $rmName = trim(explode('-', $rm, 2)[1] ?? $rm);

        return $this->resolveManualTargetForProduct($manualTargets, 'CONSUMER', $rmName);
    }

    private function consumerRmLookupKeys(string $rm): array
    {
        $normalized = strtoupper(trim($rm));
        $keys = [$normalized];

        if (str_starts_with($normalized, '00385844 - GLAGAH')) {
            $keys[] = '00385844 -';
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private function fetchAvailablePeriods(): Collection
    {
        $cacheKey = 'kinerja_rm_periods_v4:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, 600, function () {
            $periods = $this->fetchPeriodList(self::SNAPSHOT_TABLE, 'periode')
                ->merge($this->fetchPeriodList(self::SOURCE_TABLE, 'periode'))
                ->unique()
                ->values();

            return $this->latestPeriodPerMonth($periods)
                ->sortDesc()
                ->values();
        });
    }

    private function fetchComparisonPeriods(): Collection
    {
        $cacheKey = 'kinerja_rm_comparison_periods_v1:' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, 600, function () {
            return $this->fetchPeriodList(self::SNAPSHOT_TABLE, 'periode')
                ->merge($this->fetchPeriodList(self::SOURCE_TABLE, 'periode'))
                ->unique()
                ->sortDesc()
                ->values();
        });
    }

    private function fetchAvailableCabangsBySegmen(string $segmen): Collection
    {
        $cacheKey = 'kinerja_rm_cabangs_v3:' . $this->reportCacheVersion() . ':' . $segmen;
        
        return Cache::remember($cacheKey, 1800, function () use ($segmen) {
            return DB::table(self::SNAPSHOT_TABLE)
                ->where('segmen', $segmen)
                ->whereNotNull('cabang')
                ->where('cabang', '<>', '')
                ->select('cabang')
                ->distinct()
                ->orderBy('cabang')
                ->pluck('cabang')
                ->values();
        });
    }

    private function fetchPeriodList(string $table, string $column): Collection
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return collect();
        }

        return DB::table($table)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->distinct()
            ->orderByDesc($column)
            ->pluck($column)
            ->map(fn ($value) => $this->normalizeDate((string) $value))
            ->filter()
            ->values();
    }

    private function resolveSelectedPeriod(Collection $periods, ?string $requestedPeriod): ?string
    {
        $target = $this->normalizeDate($requestedPeriod);

        if ($target !== null) {
            $targetDate = Carbon::parse($target);
            $match = $this->resolveClosestPeriodInMonth($periods, $targetDate)
                ?? $this->resolveClosestPeriod($periods, $targetDate);
            if ($match !== null) {
                return $match;
            }
        }

        return $periods->first();
    }

    private function latestPeriodPerMonth(Collection $periods): Collection
    {
        $latestByMonth = $periods
            ->map(fn ($period) => $this->normalizeDate((string) $period))
            ->filter()
            ->sort()
            ->values()
            ->reduce(function (array $latestByMonth, string $period): array {
                $latestByMonth[substr($period, 0, 7)] = $period;

                return $latestByMonth;
            }, []);

        return collect($latestByMonth)->values();
    }

    private function resolveSelectedSegmen(?string $requestedSegmen): string
    {
        $normalized = strtoupper(trim((string) $requestedSegmen));
        
        if (in_array($normalized, self::AVAILABLE_SEGMENTS, true)) {
            return $normalized;
        }
        
        return self::DEFAULT_SEGMENT;
    }

    private function resolveSelectedCabang(Collection $cabangs, ?string $requestedCabang): ?string
    {
        $value = $this->normalizeCabangKey($requestedCabang);

        if ($value === '' || in_array($value, ['SEMUA CABANG', 'ALL', 'ALL CABANG'], true)) {
            return null;
        }

        return $cabangs->first(fn ($cabang) => $this->normalizeCabangKey($cabang) === $value);
    }

    private function resolveSelectedProduct(?string $requestedProduct, string $segmen = 'CONSUMER'): ?string
    {
        $normalized = $this->normalizeProductLabel($requestedProduct);
        $productOptions = self::SEGMENT_PRODUCT_MAP[$segmen] ?? [];
        
        if ($normalized !== null && in_array($normalized, $productOptions, true)) {
            return $normalized;
        }
        
        return null;
    }

    private function resolveSelectedRmCategory(string $segmen, ?string $requestedCategory): ?string
    {
        if ($segmen !== 'SMALL') {
            return null;
        }

        $normalized = strtoupper(trim((string) $requestedCategory));

        return array_key_exists($normalized, self::SMALL_RM_CATEGORIES)
            ? $normalized
            : null;
    }

    private function resolveSmallRmCategory(?string $unit): string
    {
        return str_starts_with(strtoupper(trim((string) $unit)), 'KCP') ? 'KCP' : 'KC';
    }

    private function kinerjaRmGroupKey(string $rmName, ?string $rmCategory, ?string $rmUnit = null): string
    {
        if ($rmUnit !== null && trim($rmUnit) !== '') {
            return $rmName . '|' . strtoupper(trim($rmUnit));
        }

        return $rmCategory !== null ? $rmName . '|' . $rmCategory : $rmName;
    }

    private function resolveClosestPeriod(Collection $periods, Carbon $target): ?string
    {
        $targetDate = $target->toDateString();

        return $periods
            ->first(function (string $period) use ($targetDate) {
                return $period <= $targetDate;
            });
    }

    private function resolveClosestPeriodInMonth(Collection $periods, Carbon $target): ?string
    {
        $targetDate = $target->toDateString();
        $targetMonth = $target->format('Y-m');

        return $periods
            ->first(function (string $period) use ($targetDate, $targetMonth) {
                return str_starts_with($period, $targetMonth)
                    && $period <= $targetDate;
            })
            ?? $periods->first(fn (string $period) => str_starts_with($period, $targetMonth));
    }

    /**
     * @return array<string, array{key:string,label:string,period:?string,period_label:string,short_label:string}>
     */
    private function resolveKinerjaComparisonPeriods(Collection $periods, string $selectedPeriod): array
    {
        $currentDate = Carbon::parse($selectedPeriod);
        $definitions = [
            'yoy' => ['target' => $currentDate->copy()->subYearNoOverflow(), 'same_month' => true],
            'ytd' => ['label' => 'YTD', 'target' => $currentDate->copy()->subYear()->endOfYear(), 'same_month' => false],
            'm2' => ['label' => 'M-2', 'target' => $currentDate->copy()->subMonthsNoOverflow(2)->endOfMonth(), 'same_month' => true],
            'm1' => ['label' => 'M-1', 'target' => $currentDate->copy()->subMonthNoOverflow()->endOfMonth(), 'same_month' => true],
        ];

        $resolved = [];
        foreach ($definitions as $key => $definition) {
            /** @var Carbon $target */
            $target = $definition['target'];
            $period = $definition['same_month']
                ? $this->resolveClosestPeriodInMonth($periods, $target)
                : $this->resolveClosestPeriod($periods, $target);

            $resolved[$key] = [
                'key' => $key,
                'label' => $period !== null ? Carbon::parse($period)->translatedFormat('d M y') : '-',
                'period' => $period,
                'period_label' => $period !== null ? Carbon::parse($period)->translatedFormat('d M Y') : '-',
                'short_label' => $period !== null ? Carbon::parse($period)->translatedFormat('d M y') : '-',
            ];
        }

        return $resolved;
    }

    private function resolveKinerjaRealisasiPeriod(string $selectedPeriod, array $comparisonPeriods): string
    {
        return $selectedPeriod;
    }

    /**
     * Build one performance row per RM and unit. Monthly figures are MTD
     * realization snapshots from the latest Daily Loan report in each month,
     * so multiple imports in the same month are never accumulated twice.
     *
     * @return array{
     *     months:array<int, array{key:string,label:string,short_label:string,period:?string,period_label:string}>,
     *     rows:array<int, array<string, mixed>>,
     *     total:array<string, mixed>,
     *     meta:array<string, mixed>
     * }
     */
    private function fetchRetailRealizationPerformance(
        string $segmen,
        string $selectedPeriod,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null,
        ?string $selectedRmCategory = null
    ): array {
        $cacheKey = 'kinerja_rm_retail_performance_v3:' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'segmen' => $segmen,
            'selected' => $selectedPeriod,
            'cabang' => $selectedCabang,
            'produk' => $selectedProduct,
            'rm_category' => $selectedRmCategory,
        ]));

        return Cache::remember($cacheKey, 300, function () use ($segmen, $selectedPeriod, $selectedCabang, $selectedProduct, $selectedRmCategory) {
            $selectedDate = Carbon::parse($selectedPeriod)->startOfDay();
            $yearStart = $selectedDate->copy()->startOfYear()->toDateString();
            $sourcePeriods = $this->fetchPeriodList(self::SOURCE_TABLE, 'periode')
                ->filter(fn (string $period): bool => $period >= $yearStart && $period <= $selectedPeriod)
                ->values();

            if ($sourcePeriods->isEmpty()) {
                $sourcePeriods = $this->fetchPeriodList(self::SNAPSHOT_TABLE, 'periode')
                    ->filter(fn (string $period): bool => $period >= $yearStart && $period <= $selectedPeriod)
                    ->values();
            }

            $latestPeriodByMonth = $this->latestPeriodPerMonth($sourcePeriods)
                ->mapWithKeys(fn (string $period): array => [substr($period, 0, 7) => $period]);
            $months = [];

            for ($monthNumber = 1; $monthNumber <= $selectedDate->month; $monthNumber++) {
                $monthDate = Carbon::create($selectedDate->year, $monthNumber, 1);
                $monthKey = $monthDate->format('Y-m');
                $period = $latestPeriodByMonth->get($monthKey);
                $months[] = [
                    'key' => $monthKey,
                    'label' => $monthDate->translatedFormat('F Y'),
                    'short_label' => $monthDate->translatedFormat('M y'),
                    'period' => $period,
                    'is_closed' => $period !== null && Carbon::parse($period)->isLastOfMonth(),
                    'period_label' => $period !== null
                        ? Carbon::parse($period)->translatedFormat('d M Y')
                        : '-',
                ];
            }

            $latestMonthKey = $selectedDate->format('Y-m');
            $latestPeriod = $latestPeriodByMonth->get($latestMonthKey, $selectedPeriod);
            $previousReportPeriod = $sourcePeriods
                ->filter(fn (string $period): bool => str_starts_with($period, $latestMonthKey) && $period < $latestPeriod)
                ->sortDesc()
                ->first();
            $monthlyPeriods = collect($months)->pluck('period')->filter()->values();
            $queryPeriods = $monthlyPeriods
                ->when($previousReportPeriod !== null, fn (Collection $periods) => $periods->push($previousReportPeriod))
                ->unique()
                ->values()
                ->all();

            $manualTargets = Schema::hasTable('performance_targets')
                ? DB::table('performance_targets')
                    ->get()
                    ->groupBy(fn ($item) => strtoupper(trim((string) ($item->category ?? ''))))
                    ->map(fn ($items) => $items->keyBy(fn ($item) => strtoupper(trim((string) ($item->rm_name ?? '')))))
                : collect();

            $snapshotRows = collect();
            if ($queryPeriods !== [] && Schema::hasTable(self::SNAPSHOT_TABLE)) {
                $productValues = $this->snapshotProductFilterValues($selectedProduct, $segmen);
                $snapshotRows = DB::table(self::SNAPSHOT_TABLE)
                    ->whereIn('periode', $queryPeriods)
                    ->where('segmen', $segmen)
                    ->when($productValues !== [], function ($query) use ($productValues) {
                        $query->whereIn('produk', $productValues);
                    })
                    ->when($selectedCabang !== null, function ($query) use ($selectedCabang) {
                        $query->where('cabang', $selectedCabang);
                    })
                    ->when($segmen === 'SMALL' && $selectedRmCategory === 'KCP', function ($query) {
                        $query->whereRaw("UPPER(TRIM(COALESCE(unit, ''))) LIKE 'KCP%'");
                    })
                    ->when($segmen === 'SMALL' && $selectedRmCategory === 'KC', function ($query) {
                        $query->whereRaw("UPPER(TRIM(COALESCE(unit, ''))) NOT LIKE 'KCP%'");
                    })
                    ->get();
            }

            $emptyMetric = static fn (bool $hasData = false): array => [
                'deb' => 0,
                'rp' => 0.0,
                'lar_loan_os' => 0.0,
                'lar_value' => 0.0,
                'lar_pct' => null,
                'has_data' => $hasData,
            ];
            $pivoted = [];

            foreach ($snapshotRows as $snapshot) {
                $rmName = trim($this->mapRmName((string) ($snapshot->rm ?? '')));
                if ($rmName === '') {
                    continue;
                }

                $cabang = trim((string) ($snapshot->cabang ?? ''));
                $unit = trim((string) ($snapshot->unit ?? ''));
                $branchCode = trim((string) ($snapshot->branch_code ?? ''));
                $period = $this->normalizeDate((string) ($snapshot->periode ?? ''));
                if ($period === null) {
                    continue;
                }

                $groupKey = implode('|', [
                    $this->normalizeCabangKey($cabang),
                    strtoupper($unit),
                    strtoupper($branchCode),
                    strtoupper($rmName),
                ]);
                $pivoted[$groupKey] ??= [
                    'cabang' => $cabang,
                    'unit' => $unit !== '' ? $unit : $cabang,
                    'unit_code' => $branchCode !== '' ? $branchCode : ($unit !== '' ? $unit : $cabang),
                    'rm' => $rmName,
                    'periods' => [],
                    'snapshot_quadrant' => null,
                ];
                $pivoted[$groupKey]['periods'][$period] ??= $emptyMetric(true);
                $pivoted[$groupKey]['periods'][$period]['deb'] += (int) round((float) ($snapshot->realisasi_deb ?? 0));
                $pivoted[$groupKey]['periods'][$period]['rp'] += (float) ($snapshot->realisasi_os ?? 0);
                $pivoted[$groupKey]['periods'][$period]['lar_loan_os'] += (float) ($snapshot->loan_os ?? 0);
                $pivoted[$groupKey]['periods'][$period]['lar_value'] += (float) ($snapshot->restruk_os ?? 0)
                    + (float) ($snapshot->sml_os ?? 0)
                    + (float) ($snapshot->npl_os ?? 0);

                if ($period === $latestPeriod) {
                    $pivoted[$groupKey]['snapshot_quadrant'] ??= $this->normalizeQuadrant($snapshot->quadrant ?? null);
                }
            }

            $monthsWithReports = collect($months)->filter(fn (array $month): bool => $month['period'] !== null)->values();
            $activeMonthKeys = $monthsWithReports->take(-2)->pluck('key')->all();
            $closedMonths = collect($months)
                ->filter(fn (array $month): bool => (bool) ($month['is_closed'] ?? false))
                ->values();
            $closedMonthKeys = $closedMonths->pluck('key')->all();
            $lastClosedMonth = $closedMonths->last();
            $lastClosedMonthKey = is_array($lastClosedMonth) ? ($lastClosedMonth['key'] ?? null) : null;
            $closedMonthCount = count($closedMonthKeys);
            $previousMonthKey = $selectedDate->copy()->subMonthNoOverflow()->format('Y-m');
            $previousMonthPeriod = $latestPeriodByMonth->get($previousMonthKey);
            $targetMonthCount = $selectedDate->month;
            $rows = [];
            $hiddenInactiveCount = 0;

            foreach ($pivoted as $data) {
                $monthMetrics = [];
                $accumulatedDeb = 0;
                $accumulatedRp = 0.0;

                foreach ($months as $month) {
                    $metric = $month['period'] !== null
                        ? ($data['periods'][$month['period']] ?? $emptyMetric(true))
                        : $emptyMetric(false);
                    $monthMetrics[$month['key']] = $metric;

                    if ($metric['has_data']) {
                        $accumulatedDeb += (int) $metric['deb'];
                        $accumulatedRp += (float) $metric['rp'];
                    }
                }

                foreach ($monthMetrics as &$monthMetric) {
                    $monthMetric['lar_pct'] = (float) ($monthMetric['lar_loan_os'] ?? 0) > 0
                        ? (((float) ($monthMetric['lar_value'] ?? 0) / (float) $monthMetric['lar_loan_os']) * 100)
                        : null;
                }
                unset($monthMetric);

                $hasRecentRealization = collect($activeMonthKeys)->contains(function (string $monthKey) use ($monthMetrics): bool {
                    $metric = $monthMetrics[$monthKey] ?? ['deb' => 0, 'rp' => 0.0];

                    return (int) $metric['deb'] !== 0 || abs((float) $metric['rp']) > 0.001;
                });
                if ($activeMonthKeys !== [] && ! $hasRecentRealization) {
                    $hiddenInactiveCount++;
                    continue;
                }

                $nameOnly = strtoupper(trim(explode('-', $data['rm'], 2)[1] ?? $data['rm']));
                $targetLabel = $selectedProduct ?? $segmen;
                $target = $this->resolveManualTargetForProduct($manualTargets, $targetLabel, $nameOnly);
                $targetDeb = (int) ($target['target_jg_deb'] ?? 0);
                $targetRp = (float) ($target['target_jg_os'] ?? 0.0);
                $currentMetric = $monthMetrics[$latestMonthKey] ?? $emptyMetric(false);
                $previousMonthMetric = $previousMonthPeriod !== null
                    ? ($data['periods'][$previousMonthPeriod] ?? $emptyMetric(true))
                    : null;
                $previousReportMetric = $previousReportPeriod !== null
                    ? ($data['periods'][$previousReportPeriod] ?? $emptyMetric(true))
                    : null;
                $averageMonthlyRp = $targetMonthCount > 0 ? $accumulatedRp / $targetMonthCount : 0.0;
                $closedRpTotal = collect($closedMonthKeys)->sum(
                    fn (string $monthKey): float => (float) ($monthMetrics[$monthKey]['rp'] ?? 0)
                );
                $smallRatasRp = $closedMonthCount > 0 ? $closedRpTotal / $closedMonthCount : null;
                $smallLarPct = $lastClosedMonthKey !== null
                    ? ($monthMetrics[$lastClosedMonthKey]['lar_pct'] ?? null)
                    : null;
                $quadrant = match ($segmen) {
                    'CONSUMER' => $this->calculateConsumerQuadrant($averageMonthlyRp, $targetRp),
                    'SMALL' => $smallRatasRp !== null && $smallLarPct !== null
                        ? $this->calculateSmallQuadrant($smallRatasRp, (float) $smallLarPct)
                        : $data['snapshot_quadrant'],
                    default => $data['snapshot_quadrant'],
                };

                $rows[] = [
                    'unit_code' => $data['unit_code'],
                    'unit' => $data['unit'],
                    'cabang' => $data['cabang'],
                    'rm' => $data['rm'],
                    'rm_display' => trim(explode('-', $data['rm'], 2)[1] ?? $data['rm']),
                    'target' => ['deb' => $targetDeb, 'rp' => $targetRp],
                    'months' => $monthMetrics,
                    'delta' => [
                        'ytd' => $accumulatedRp - ($targetRp * $targetMonthCount),
                        'mtd' => (float) $currentMetric['rp'] - $targetRp,
                        'mom' => $previousMonthMetric !== null
                            ? (float) $currentMetric['rp'] - (float) $previousMonthMetric['rp']
                            : null,
                        'dtd' => $previousReportMetric !== null
                            ? (float) $currentMetric['rp'] - (float) $previousReportMetric['rp']
                            : null,
                    ],
                    'accumulated' => [
                        'deb' => $accumulatedDeb,
                        'rp' => $accumulatedRp,
                        'ratas_rp' => $smallRatasRp,
                        'lar_pct' => $smallLarPct,
                        'closed_month_count' => $closedMonthCount,
                    ],
                    'quadrant' => $quadrant,
                ];
            }

            usort($rows, function (array $left, array $right): int {
                $codeOrder = strnatcasecmp((string) $left['unit_code'], (string) $right['unit_code']);
                if ($codeOrder !== 0) {
                    return $codeOrder;
                }

                $unitOrder = strnatcasecmp((string) $left['unit'], (string) $right['unit']);

                return $unitOrder !== 0
                    ? $unitOrder
                    : strnatcasecmp((string) $left['rm'], (string) $right['rm']);
            });

            $total = [
                'target' => ['deb' => 0, 'rp' => 0.0],
                'months' => collect($months)->mapWithKeys(fn (array $month): array => [$month['key'] => $emptyMetric($month['period'] !== null)])->all(),
                'delta' => ['ytd' => 0.0, 'mtd' => 0.0, 'mom' => null, 'dtd' => null],
                'accumulated' => [
                    'deb' => 0,
                    'rp' => 0.0,
                    'ratas_rp' => null,
                    'lar_pct' => null,
                    'closed_month_count' => $closedMonthCount,
                ],
            ];

            foreach ($rows as $row) {
                $total['target']['deb'] += (int) $row['target']['deb'];
                $total['target']['rp'] += (float) $row['target']['rp'];
                $total['accumulated']['deb'] += (int) $row['accumulated']['deb'];
                $total['accumulated']['rp'] += (float) $row['accumulated']['rp'];

                foreach ($months as $month) {
                    $total['months'][$month['key']]['deb'] += (int) $row['months'][$month['key']]['deb'];
                    $total['months'][$month['key']]['rp'] += (float) $row['months'][$month['key']]['rp'];
                    $total['months'][$month['key']]['lar_loan_os'] += (float) ($row['months'][$month['key']]['lar_loan_os'] ?? 0);
                    $total['months'][$month['key']]['lar_value'] += (float) ($row['months'][$month['key']]['lar_value'] ?? 0);
                }

                foreach (['ytd', 'mtd'] as $deltaKey) {
                    $total['delta'][$deltaKey] += (float) $row['delta'][$deltaKey];
                }
                foreach (['mom', 'dtd'] as $deltaKey) {
                    if ($row['delta'][$deltaKey] !== null) {
                        $total['delta'][$deltaKey] = (float) ($total['delta'][$deltaKey] ?? 0.0) + (float) $row['delta'][$deltaKey];
                    }
                }
            }

            foreach ($months as $month) {
                $monthKey = $month['key'];
                $loanOs = (float) ($total['months'][$monthKey]['lar_loan_os'] ?? 0);
                $total['months'][$monthKey]['lar_pct'] = $loanOs > 0
                    ? (((float) ($total['months'][$monthKey]['lar_value'] ?? 0) / $loanOs) * 100)
                    : null;
            }

            if ($segmen === 'SMALL' && $closedMonthCount > 0) {
                $totalClosedRp = collect($closedMonthKeys)->sum(
                    fn (string $monthKey): float => (float) ($total['months'][$monthKey]['rp'] ?? 0)
                );
                $total['accumulated']['ratas_rp'] = $totalClosedRp / $closedMonthCount;
                $total['accumulated']['lar_pct'] = $lastClosedMonthKey !== null
                    ? ($total['months'][$lastClosedMonthKey]['lar_pct'] ?? null)
                    : null;
            }

            return [
                'months' => $months,
                'rows' => array_values($rows),
                'total' => $total,
                'meta' => [
                    'latest_period' => $latestPeriod,
                    'latest_period_label' => Carbon::parse($latestPeriod)->translatedFormat('d M Y'),
                    'previous_report_period' => $previousReportPeriod,
                    'previous_report_period_label' => $previousReportPeriod !== null
                        ? Carbon::parse($previousReportPeriod)->translatedFormat('d M Y')
                        : null,
                    'closed_month_count' => $closedMonthCount,
                    'closed_through_period' => is_array($lastClosedMonth) ? ($lastClosedMonth['period'] ?? null) : null,
                    'closed_through_period_label' => is_array($lastClosedMonth) ? ($lastClosedMonth['period_label'] ?? null) : null,
                    'closed_range_label' => $closedMonths->isNotEmpty()
                        ? $closedMonths->first()['short_label'].' - '.$closedMonths->last()['short_label']
                        : null,
                    'hidden_inactive_count' => $hiddenInactiveCount,
                    'visible_count' => count($rows),
                ],
            ];
        });
    }

    private function fetchBranchRows(
        string $segmen,
        string $selectedPeriod,
        array $comparisonPeriods,
        string $realisasiPeriod,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null,
        ?string $qualityType = null,
        ?string $selectedRmCategory = null,
        ?Collection $detailedQualityRows = null,
        bool $sortByUnitCode = false
    ): array
    {
        $comparisonPeriodValues = collect($comparisonPeriods)
            ->mapWithKeys(fn (array $period, string $key): array => [$key => $period['period'] ?? null])
            ->all();
        $comparisonKeys = array_keys($comparisonPeriodValues);
        $emptyComparisonValues = array_fill_keys($comparisonKeys, 0.0);

        $cacheKey = 'kinerja_rm_rows_v25-quality-restruk-split:' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'segmen' => $segmen,
            'selected' => $selectedPeriod,
            'comparisons' => $comparisonPeriodValues,
            'realisasi' => $realisasiPeriod,
            'cabang' => $selectedCabang,
            'produk' => $selectedProduct,
            'quality' => $qualityType,
            'rm_category' => $selectedRmCategory,
            'sort_by_unit_code' => $sortByUnitCode,
            'quality_source' => $detailedQualityRows !== null ? 'daily-loan-bucket-v2-restruk-split' : 'snapshot',
        ]));

        return Cache::remember($cacheKey, 300, function () use ($segmen, $selectedPeriod, $comparisonPeriodValues, $comparisonKeys, $emptyComparisonValues, $realisasiPeriod, $selectedCabang, $selectedProduct, $qualityType, $selectedRmCategory, $detailedQualityRows, $sortByUnitCode) {
            if ($segmen === 'CONSUMER'
                && Schema::hasTable(self::SOURCE_TABLE)
                && Schema::hasColumn(self::SOURCE_TABLE, 'segmen_kinerja')
                && $this->resolvePreviousMonthSourcePeriod($selectedPeriod) === null
            ) {
                return [
                    'rows' => [],
                    'total' => [
                        'segmen' => $segmen,
                        'cabang' => $selectedCabang ?? 'SEMUA CABANG',
                        'rm' => 'TOTAL',
                        'curr' => 0.0,
                        'curr_deb' => 0,
                        'loan_os_reference' => 0.0,
                        'yoy' => 0.0,
                        'ytd' => 0.0,
                        'mtd' => 0.0,
                        'comparison_values' => $emptyComparisonValues,
                        'comparison_deltas' => $emptyComparisonValues,
                        'delta_yoy' => 0.0,
                        'delta_ytd' => 0.0,
                        'delta_mtd' => 0.0,
                        'target_jg_deb' => 0,
                        'target_jg_os' => 0.0,
                        'ach_deb' => null,
                        'ach_os' => null,
                        'ach_has_data' => false,
                        'lar_pct' => null,
                        'lar_has_data' => false,
                    ],
                ];
            }

            $averagePeriods = match ($segmen) {
                'SMALL' => $this->resolveSmallClosedPeriodsInScope($realisasiPeriod, $segmen, $selectedCabang, $selectedProduct),
                'CONSUMER' => $this->resolveAveragePeriodsInScope($realisasiPeriod, $segmen, $selectedCabang, $selectedProduct),
                default => [$realisasiPeriod],
            };
            if ($segmen === 'CONSUMER'
                && Schema::hasTable(self::SOURCE_TABLE)
                && Schema::hasColumn(self::SOURCE_TABLE, 'segmen_kinerja')
            ) {
                $averagePeriods = array_values(array_filter(
                    $averagePeriods,
                    fn (string $period): bool => $this->resolvePreviousMonthSourcePeriod($period) !== null
                ));
            }
            $lastAveragePeriod = $averagePeriods !== [] ? $averagePeriods[array_key_last($averagePeriods)] : null;
            $larPeriod = $segmen === 'SMALL'
                ? $lastAveragePeriod
                : ($this->resolveLatestPeriodInScope($selectedPeriod, $segmen, $selectedCabang, $selectedProduct) ?? $selectedPeriod);
            $periods = array_values(array_unique(array_filter([
                $selectedPeriod,
                $realisasiPeriod,
                $larPeriod,
                ...array_values($comparisonPeriodValues),
                ...$averagePeriods,
            ])));

            $selectedProductValues = $this->snapshotProductFilterValues($selectedProduct, $segmen);

            $dbRows = $detailedQualityRows ?? DB::table(self::SNAPSHOT_TABLE)
                ->whereIn('periode', $periods)
                ->where('segmen', $segmen)
                ->when($selectedProductValues !== [], function ($query) use ($selectedProductValues) {
                    $query->whereIn('produk', $selectedProductValues);
                })
                ->when($selectedCabang !== null, function ($query) use ($selectedCabang) {
                    $query->where('cabang', $selectedCabang);
                })
                ->when($segmen === 'SMALL' && $selectedRmCategory === 'KCP', function ($query) {
                    $query->whereRaw("UPPER(TRIM(COALESCE(unit, ''))) LIKE 'KCP%'");
                })
                ->when($segmen === 'SMALL' && $selectedRmCategory === 'KC', function ($query) {
                    $query->whereRaw("UPPER(TRIM(COALESCE(unit, ''))) NOT LIKE 'KCP%'");
                })
                ->get();

            // Always fetch from snapshot (Zero Fallback Policy)
            $manualTargets = Schema::hasTable('performance_targets')
                ? DB::table('performance_targets')
                    ->get()
                    ->groupBy('category')
                    ->map(fn ($items) => $items->keyBy('rm_name'))
                : collect();

            $branches = [];
            $grandTotals = [
                'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                'loan_os_reference' => 0.0,
                'comparison_values' => $emptyComparisonValues,
                'target_jg_deb' => 0, 'target_jg_os' => 0.0,
                'ach_deb' => 0, 'ach_os' => 0.0,
                'lar_loan_os' => 0.0, 'lar_value' => 0.0, 'lar_pct' => 0.0,
                'ach_count' => 0, 'lar_count' => 0,
            ];

            // Pivot data by RM and Product
            $pivoted = [];
            foreach ($dbRows as $row) {
                $cabKey = $this->normalizeCabangKey($row->cabang);
                $rmKey = trim(strtoupper((string)$row->rm));
                $prodKey = $this->normalizeProductLabel((string) $row->produk, $segmen) ?? strtoupper(trim((string) $row->produk));
                $rmUnit = $segmen === 'SMALL' ? strtoupper(trim((string) ($row->unit ?? ''))) : null;
                $rmUnitCode = strtoupper(trim((string) ($row->branch_code ?? '')));
                $rmCategory = $segmen === 'SMALL' ? $this->resolveSmallRmCategory($rmUnit) : null;
                $key = "{$cabKey}|{$rmUnit}|{$rmKey}|{$prodKey}";

                $val = (float) match ($qualityType) {
                    'lancar' => $row->lancar_os ?? 0,
                    'sml_1' => $row->sml_1_os ?? 0,
                    'sml_2' => $row->sml_2_os ?? 0,
                    'sml_3' => $row->sml_3_os ?? 0,
                    'kl' => $row->kl_os ?? 0,
                    'd1' => $row->d1_os ?? 0,
                    'd2' => $row->d2_os ?? 0,
                    'm' => $row->m_os ?? 0,
                    'lancar_non_restruk', 'lnr' => $row->lancar_non_restruk_os ?? 0,
                    'lr' => $row->restruk_os,
                    'account_restruk' => $row->account_restruk_os ?? 0,
                    'sml' => $row->sml_os,
                    'npl' => $row->npl_os,
                    'lar' => (float) $row->restruk_os + (float) $row->sml_os + (float) $row->npl_os,
                    default => $row->loan_os,
                };

                $pivoted[$key] ??= [
                    'cabang' => $row->cabang,
                    'rm' => $row->rm,
                    'rm_category' => $rmCategory,
                    'rm_unit' => $rmUnit,
                    'rm_unit_code' => $rmUnitCode,
                    'produk' => $row->produk,
                    'quadrant' => null,
                    'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                    'loan_os_reference' => 0.0,
                    'comparison_values' => $emptyComparisonValues,
                    'realisasi_deb' => 0, 'realisasi_os' => 0.0,
                    'realisasi_deb_sum' => 0.0, 'realisasi_os_sum' => 0.0,
                    'realisasi_period_count' => 0,
                    'quadrant_realisasi_os_sum' => 0.0,
                    'lar_loan_os' => 0.0, 'lar_value' => 0.0, 'lar_pct' => 0.0,
                    'lar_has_data' => false,
                ];

                if ($row->periode === $selectedPeriod) {
                    $pivoted[$key]['curr'] += $val;
                    $pivoted[$key]['curr_deb'] += (int)$row->total_deb;
                    $pivoted[$key]['loan_os_reference'] += (float) $row->loan_os;
                    $quadrant = $row->quadrant ?? null;
                    $pivoted[$key]['quadrant'] ??= $quadrant;
                }

                // For SMALL and CONSUMER segments, collect realisasi data from ALL average periods to compute average
                // For other segments, use only selectedPeriod
                $useForRealisasiAverage = in_array($segmen, ['SMALL', 'CONSUMER'], true)
                    ? in_array($row->periode, $averagePeriods, true)
                    : ($row->periode === $realisasiPeriod);

                $realisasiDeb = (float) ($row->realisasi_deb ?? 0);
                $realisasiOs = (float) ($row->realisasi_os ?? 0);
                $hasRealisasiValue = abs($realisasiDeb) > 0 || abs($realisasiOs) > 0;

                if ($useForRealisasiAverage && $hasRealisasiValue) {
                    $pivoted[$key]['realisasi_deb_sum'] += $realisasiDeb;
                    $pivoted[$key]['realisasi_os_sum'] += $realisasiOs;
                    $pivoted[$key]['realisasi_period_count']++;
                }

                if ($segmen === 'SMALL' && in_array($row->periode, $averagePeriods, true)) {
                    $pivoted[$key]['quadrant_realisasi_os_sum'] += $realisasiOs;
                    $quadrant = $row->quadrant ?? null;

                    if ($quadrant !== null && $row->periode === $lastAveragePeriod) {
                        $pivoted[$key]['quadrant'] = $quadrant;
                    }
                }

                if ($row->periode === $larPeriod) {
                    $larLoanOs = (float) ($row->loan_os ?? 0);
                    $larValue = (float) ($row->sml_os ?? 0)
                        + (float) ($row->npl_os ?? 0)
                        + (float) ($row->restruk_os ?? 0);

                    $pivoted[$key]['lar_loan_os'] += $larLoanOs;
                    $pivoted[$key]['lar_value'] += $larValue;
                    $pivoted[$key]['lar_pct'] = $pivoted[$key]['lar_loan_os'] > 0
                        ? (($pivoted[$key]['lar_value'] / $pivoted[$key]['lar_loan_os']) * 100)
                        : 0;
                    $pivoted[$key]['lar_has_data'] = true;
                }
                
                foreach ($comparisonPeriodValues as $periodKey => $periodValue) {
                    if ($periodValue !== null && $row->periode === $periodValue) {
                        $pivoted[$key]['comparison_values'][$periodKey] += $val;
                    }
                }
            }

            $smallQuadrantsByRm = $segmen === 'SMALL'
                ? $this->calculateSmallQuadrantsByRm($pivoted, count($averagePeriods))
                : [];

            foreach ($pivoted as $data) {
                $cabangName = $data['cabang'];
                $rmName = $this->mapRmName($data['rm']);
                $rmCategory = $data['rm_category'] ?? null;
                $rmUnit = $data['rm_unit'] ?? null;
                $rmUnitCode = $data['rm_unit_code'] ?? null;
                $rmGroupKey = $this->kinerjaRmGroupKey($rmName, $rmCategory, $rmUnit);
                $productLabel = $this->normalizeProductLabel($data['produk'], $segmen);

                if ($rmName === '' || $productLabel === null) continue;

                if ($qualityType === null
                    && $segmen === 'SMALL'
                    && abs((float) ($data['realisasi_os_sum'] ?? 0)) <= 0.001
                    && abs((float) ($data['realisasi_deb_sum'] ?? 0)) <= 0.001) {
                    continue;
                }

                // Check if all performance OS values are strictly zero
                $hasPerformanceValue = abs((float) $data['curr']) > 0.001;
                if (!$hasPerformanceValue) {
                    foreach ($data['comparison_values'] as $val) {
                        if (abs((float) $val) > 0.001) {
                            $hasPerformanceValue = true;
                            break;
                        }
                    }
                }

                if (!$hasPerformanceValue) {
                    continue;
                }

                $quadrant = $segmen === 'SMALL'
                    ? ($smallQuadrantsByRm[$rmGroupKey] ?? $data['quadrant'])
                    : $data['quadrant'];

                $cabangKey = $this->normalizeCabangKey($cabangName);
                if (!isset($branches[$cabangKey])) {
                    $branches[$cabangKey] = [
                        'cabang' => $cabangName,
                        'rms' => [],
                        'subtotal' => [
                            'curr' => 0.0, 'curr_deb' => 0, 'yoy' => 0.0, 'mtd' => 0.0, 'ytd' => 0.0,
                            'loan_os_reference' => 0.0,
                            'comparison_values' => $emptyComparisonValues,
                            'target_jg_deb' => 0, 'target_jg_os' => 0.0,
                            'ach_deb' => 0, 'ach_os' => 0.0,
                            'lar_loan_os' => 0.0, 'lar_value' => 0.0, 'lar_pct' => 0.0,
                            'ach_count' => 0, 'lar_count' => 0,
                        ],
                        'branch_rowspan' => 0,
                    ];
                }

                if (!isset($branches[$cabangKey]['rms'][$rmGroupKey])) {
                    $branches[$cabangKey]['rms'][$rmGroupKey] = [
                        'rm' => $rmName,
                        'rm_category' => $rmCategory,
                        'rm_unit' => $rmUnit,
                        'rm_unit_code' => $rmUnitCode,
                        'items' => [],
                        'rm_rowspan' => 0,
                        'quadrant' => $quadrant,
                    ];
                }

                // Manual Targets from database
                $nameOnly = strtoupper(trim(explode('-', $rmName)[1] ?? $rmName));
                $target = $this->resolveManualTargetForProduct($manualTargets, $productLabel, $nameOnly);
                $tDeb = (int) ($target['target_jg_deb'] ?? 0);
                $tOs = (float) ($target['target_jg_os'] ?? 0.0);
                $realisasiDivisor = match ($segmen) {
                    'SMALL' => count($averagePeriods),
                    'CONSUMER' => max(1, Carbon::parse($realisasiPeriod)->month),
                    default => $data['realisasi_period_count'],
                };
                $hasAchievementData = match ($segmen) {
                    'SMALL' => $realisasiDivisor > 0,
                    'CONSUMER' => true,
                    default => $data['realisasi_period_count'] > 0,
                };
                $achDeb = $hasAchievementData
                    ? (int) round($data['realisasi_deb_sum'] / max(1, $realisasiDivisor))
                    : null;
                $achOs = $hasAchievementData
                    ? ($data['realisasi_os_sum'] / max(1, $realisasiDivisor))
                    : null;
                if ($segmen === 'CONSUMER') {
                    $quadrant = $this->calculateConsumerQuadrant($achOs, $tOs);
                    $branches[$cabangKey]['rms'][$rmGroupKey]['quadrant'] = $quadrant;
                }
                $comparisonDeltas = [];
                foreach ($comparisonKeys as $periodKey) {
                    $comparisonDeltas[$periodKey] = $data['curr'] - ($data['comparison_values'][$periodKey] ?? 0.0);
                }

                $item = [
                    'segmen' => $segmen,
                    'product' => $productLabel,
                    'curr' => $data['curr'],
                    'curr_deb' => $data['curr_deb'],
                    'loan_os_reference' => $data['loan_os_reference'],
                    'comparison_values' => $data['comparison_values'],
                    'comparison_deltas' => $comparisonDeltas,
                    'yoy' => $data['comparison_values']['yoy'] ?? 0.0,
                    'ytd' => $data['comparison_values']['ytd'] ?? 0.0,
                    'mtd' => $data['comparison_values']['m1'] ?? 0.0,
                    'delta_yoy' => $comparisonDeltas['yoy'] ?? $data['curr'],
                    'delta_ytd' => $comparisonDeltas['ytd'] ?? $data['curr'],
                    'delta_mtd' => $comparisonDeltas['m1'] ?? $data['curr'],
                    'target_jg_deb' => $tDeb,
                    'target_jg_os' => $tOs,
                    'ach_deb' => $achDeb,
                    'ach_os' => $achOs,
                    'ach_has_data' => $hasAchievementData,
                    'lar_pct' => $data['lar_has_data'] ? $data['lar_pct'] : null,
                    'lar_has_data' => $data['lar_has_data'],
                ];

                $branches[$cabangKey]['rms'][$rmGroupKey]['items'][] = $item;
                $branches[$cabangKey]['rms'][$rmGroupKey]['rm_rowspan']++;
                $branches[$cabangKey]['branch_rowspan']++;

                // Update Branch Subtotal
                $branches[$cabangKey]['subtotal']['curr'] += $data['curr'];
                $branches[$cabangKey]['subtotal']['curr_deb'] += $data['curr_deb'];
                $branches[$cabangKey]['subtotal']['loan_os_reference'] += $data['loan_os_reference'];
                foreach ($comparisonKeys as $periodKey) {
                    $branches[$cabangKey]['subtotal']['comparison_values'][$periodKey] += $data['comparison_values'][$periodKey] ?? 0.0;
                }
                $branches[$cabangKey]['subtotal']['yoy'] = $branches[$cabangKey]['subtotal']['comparison_values']['yoy'] ?? 0.0;
                $branches[$cabangKey]['subtotal']['ytd'] = $branches[$cabangKey]['subtotal']['comparison_values']['ytd'] ?? 0.0;
                $branches[$cabangKey]['subtotal']['mtd'] = $branches[$cabangKey]['subtotal']['comparison_values']['m1'] ?? 0.0;
                $branches[$cabangKey]['subtotal']['target_jg_deb'] += $tDeb;
                $branches[$cabangKey]['subtotal']['target_jg_os'] += $tOs;
                if ($hasAchievementData) {
                    $branches[$cabangKey]['subtotal']['ach_count']++;
                    $branches[$cabangKey]['subtotal']['ach_deb'] = ($branches[$cabangKey]['subtotal']['ach_deb'] ?? 0) + (int) $achDeb;
                    $branches[$cabangKey]['subtotal']['ach_os'] = ($branches[$cabangKey]['subtotal']['ach_os'] ?? 0.0) + (float) $achOs;
                }
                $branches[$cabangKey]['subtotal']['lar_loan_os'] = ($branches[$cabangKey]['subtotal']['lar_loan_os'] ?? 0.0) + $data['lar_loan_os'];
                $branches[$cabangKey]['subtotal']['lar_value'] = ($branches[$cabangKey]['subtotal']['lar_value'] ?? 0.0) + $data['lar_value'];
                if ($data['lar_has_data']) {
                    $branches[$cabangKey]['subtotal']['lar_count']++;
                }

                // Grand Totals
                $grandTotals['curr'] += $data['curr'];
                $grandTotals['curr_deb'] += $data['curr_deb'];
                $grandTotals['loan_os_reference'] += $data['loan_os_reference'];
                foreach ($comparisonKeys as $periodKey) {
                    $grandTotals['comparison_values'][$periodKey] += $data['comparison_values'][$periodKey] ?? 0.0;
                }
                $grandTotals['yoy'] = $grandTotals['comparison_values']['yoy'] ?? 0.0;
                $grandTotals['ytd'] = $grandTotals['comparison_values']['ytd'] ?? 0.0;
                $grandTotals['mtd'] = $grandTotals['comparison_values']['m1'] ?? 0.0;
                $grandTotals['target_jg_deb'] += $tDeb;
                $grandTotals['target_jg_os'] += $tOs;
                if ($hasAchievementData) {
                    $grandTotals['ach_count']++;
                    $grandTotals['ach_deb'] = ($grandTotals['ach_deb'] ?? 0) + (int) $achDeb;
                    $grandTotals['ach_os'] = ($grandTotals['ach_os'] ?? 0.0) + (float) $achOs;
                }
                $grandTotals['lar_loan_os'] = ($grandTotals['lar_loan_os'] ?? 0.0) + $data['lar_loan_os'];
                $grandTotals['lar_value'] = ($grandTotals['lar_value'] ?? 0.0) + $data['lar_value'];
                if ($data['lar_has_data']) {
                    $grandTotals['lar_count'] = ($grandTotals['lar_count'] ?? 0) + 1;
                }
            }

            foreach ($branches as $key => $branch) {
                $branches[$key]['branch_rowspan'] += 1; // For subtotal row
                $b_curr = $branches[$key]['subtotal']['curr'];
                $branches[$key]['subtotal']['comparison_deltas'] = [];
                foreach ($comparisonKeys as $periodKey) {
                    $branches[$key]['subtotal']['comparison_deltas'][$periodKey] = $b_curr - ($branches[$key]['subtotal']['comparison_values'][$periodKey] ?? 0.0);
                }
                $branches[$key]['subtotal']['delta_yoy'] = $branches[$key]['subtotal']['comparison_deltas']['yoy'] ?? $b_curr;
                $branches[$key]['subtotal']['delta_ytd'] = $branches[$key]['subtotal']['comparison_deltas']['ytd'] ?? $b_curr;
                $branches[$key]['subtotal']['delta_mtd'] = $branches[$key]['subtotal']['comparison_deltas']['m1'] ?? $b_curr;
                $branches[$key]['subtotal']['ach_deb'] = ($branches[$key]['subtotal']['ach_count'] ?? 0) > 0
                    ? (int) ($branches[$key]['subtotal']['ach_deb'] ?? 0)
                    : null;
                $branches[$key]['subtotal']['ach_os'] = ($branches[$key]['subtotal']['ach_count'] ?? 0) > 0
                    ? ($branches[$key]['subtotal']['ach_os'] ?? 0)
                    : null;
                $branches[$key]['subtotal']['lar_pct'] = ($branches[$key]['subtotal']['lar_count'] ?? 0) > 0
                    && (float) ($branches[$key]['subtotal']['lar_loan_os'] ?? 0) > 0
                    ? ((($branches[$key]['subtotal']['lar_value'] ?? 0) / $branches[$key]['subtotal']['lar_loan_os']) * 100)
                    : null;
            }

            $grandTotals['ach_deb'] = ($grandTotals['ach_count'] ?? 0) > 0
                ? (int) ($grandTotals['ach_deb'] ?? 0)
                : null;
            $grandTotals['ach_os'] = ($grandTotals['ach_count'] ?? 0) > 0
                ? ($grandTotals['ach_os'] ?? 0)
                : null;
            $grandTotals['lar_pct'] = ($grandTotals['lar_count'] ?? 0) > 0
                && (float) ($grandTotals['lar_loan_os'] ?? 0) > 0
                ? ((($grandTotals['lar_value'] ?? 0) / $grandTotals['lar_loan_os']) * 100)
                : null;
            $grandTotals['comparison_deltas'] = [];
            foreach ($comparisonKeys as $periodKey) {
                $grandTotals['comparison_deltas'][$periodKey] = $grandTotals['curr'] - ($grandTotals['comparison_values'][$periodKey] ?? 0.0);
            }

            $branches = $this->sortKinerjaRmBranches($branches, $segmen, $sortByUnitCode);

            $totalRecord = [
                'segmen' => $segmen,
                'cabang' => $selectedCabang ?? 'SEMUA CABANG',
                'rm' => 'TOTAL',
                'curr' => $grandTotals['curr'],
                'curr_deb' => $grandTotals['curr_deb'],
                'loan_os_reference' => $grandTotals['loan_os_reference'],
                'yoy' => $grandTotals['yoy'],
                'ytd' => $grandTotals['ytd'],
                'mtd' => $grandTotals['mtd'],
                'comparison_values' => $grandTotals['comparison_values'],
                'comparison_deltas' => $grandTotals['comparison_deltas'],
                'delta_yoy' => $grandTotals['comparison_deltas']['yoy'] ?? $grandTotals['curr'],
                'delta_ytd' => $grandTotals['comparison_deltas']['ytd'] ?? $grandTotals['curr'],
                'delta_mtd' => $grandTotals['comparison_deltas']['m1'] ?? $grandTotals['curr'],
                'target_jg_deb' => $grandTotals['target_jg_deb'],
                'target_jg_os' => $grandTotals['target_jg_os'],
                'ach_deb' => $grandTotals['ach_deb'],
                'ach_os' => $grandTotals['ach_os'],
                'ach_has_data' => ($grandTotals['ach_count'] ?? 0) > 0,
                'lar_pct' => $grandTotals['lar_pct'],
                'lar_has_data' => ($grandTotals['lar_count'] ?? 0) > 0,
            ];

            return [
                'rows' => array_values($branches),
                'total' => $totalRecord,
            ];
        });
    }

    private function fetchDetailedQualityRows(
        string $segmen,
        string $selectedPeriod,
        array $comparisonPeriods,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null,
        ?string $selectedRmCategory = null
    ): Collection {
        $requiredColumns = [
            'periode',
            'nomor_rekening1',
            'baki_debet1',
            'kolek_detail',
            'kolek',
            'umur_tunggakan',
            'flag_restruk',
            'next_pmt_date',
            'next_pmt_int_date',
            'segmen_kinerja',
            'produk_kinerja',
            'cabang_normalized',
            'unit_normalized',
            'branch_normalized',
            'rm_normalized',
        ];

        if (!Schema::hasTable(self::SOURCE_TABLE)) {
            return collect();
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn(self::SOURCE_TABLE, $column)) {
                return collect();
            }
        }

        $periods = collect($comparisonPeriods)
            ->pluck('period')
            ->push($selectedPeriod)
            ->filter()
            ->map(fn ($period): ?string => $this->normalizeDate((string) $period))
            ->filter()
            ->unique()
            ->values();

        if ($periods->isEmpty()) {
            return collect();
        }

        $sourceProducts = $this->sourceQualityProductValues($segmen, $selectedProduct);
        if ($sourceProducts === []) {
            return collect();
        }

        $cacheKey = 'kinerja_rm_quality_detail_v2-restruk-split:' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'segmen' => $segmen,
            'periods' => $periods->all(),
            'cabang' => $selectedCabang,
            'produk' => $selectedProduct,
            'rm_category' => $selectedRmCategory,
        ]));

        return Cache::remember($cacheKey, 300, function () use ($segmen, $periods, $sourceProducts, $selectedCabang, $selectedRmCategory): Collection {
            $bucketExpression = LoanQualityBucketMapper::buildSqlExpression('d');
            $bucketRows = DB::table(self::SOURCE_TABLE . ' as d')
                ->whereIn('d.periode', $periods->all())
                ->where('d.segmen_kinerja', $segmen)
                ->whereIn('d.produk_kinerja', $sourceProducts)
                ->whereNotNull('d.rm_normalized')
                ->where('d.rm_normalized', '<>', '')
                ->when($selectedCabang !== null, function ($query) use ($selectedCabang) {
                    $query->where('d.cabang_normalized', $this->normalizeCabangKey($selectedCabang));
                })
                ->when($segmen === 'SMALL' && $selectedRmCategory === 'KCP', function ($query) {
                    $query->whereRaw("UPPER(TRIM(COALESCE(d.unit_normalized, ''))) LIKE 'KCP%'");
                })
                ->when($segmen === 'SMALL' && $selectedRmCategory === 'KC', function ($query) {
                    $query->whereRaw("UPPER(TRIM(COALESCE(d.unit_normalized, ''))) NOT LIKE 'KCP%'");
                })
                ->selectRaw('d.periode')
                ->selectRaw("COALESCE(d.cabang_normalized, '') as cabang")
                ->selectRaw("COALESCE(d.unit_normalized, '') as unit")
                ->selectRaw("COALESCE(d.branch_normalized, '') as branch_code")
                ->selectRaw("COALESCE(d.rm_normalized, '') as rm")
                ->selectRaw("COALESCE(d.produk_kinerja, '') as produk")
                ->selectRaw("{$bucketExpression} as quality_bucket")
                ->selectRaw("UPPER(TRIM(COALESCE(d.flag_restruk, ''))) as flag_restruk_normalized")
                ->selectRaw('SUM(COALESCE(d.baki_debet1, 0)) as quality_os')
                ->selectRaw('COUNT(DISTINCT d.nomor_rekening1) as quality_deb')
                ->groupBy(
                    'd.periode',
                    'd.cabang_normalized',
                    'd.unit_normalized',
                    'd.branch_normalized',
                    'd.rm_normalized',
                    'd.produk_kinerja',
                    'quality_bucket',
                    'flag_restruk_normalized'
                )
                ->get();

            $rows = [];
            foreach ($bucketRows as $bucketRow) {
                $key = implode('|', [
                    (string) $bucketRow->periode,
                    (string) $bucketRow->cabang,
                    (string) $bucketRow->unit,
                    (string) $bucketRow->branch_code,
                    (string) $bucketRow->rm,
                    (string) $bucketRow->produk,
                ]);

                $rows[$key] ??= (object) [
                    'periode' => (string) $bucketRow->periode,
                    'cabang' => (string) $bucketRow->cabang,
                    'unit' => (string) $bucketRow->unit,
                    'branch_code' => (string) $bucketRow->branch_code,
                    'rm' => (string) $bucketRow->rm,
                    'segmen' => $segmen,
                    'produk' => (string) $bucketRow->produk,
                    'loan_os' => 0.0,
                    'lancar_os' => 0.0,
                    'lancar_non_restruk_os' => 0.0,
                    'sml_os' => 0.0,
                    'npl_os' => 0.0,
                    'restruk_os' => 0.0,
                    'account_restruk_os' => 0.0,
                    'sml_1_os' => 0.0,
                    'sml_2_os' => 0.0,
                    'sml_3_os' => 0.0,
                    'kl_os' => 0.0,
                    'd1_os' => 0.0,
                    'd2_os' => 0.0,
                    'm_os' => 0.0,
                    'total_deb' => 0,
                    'realisasi_deb' => 0,
                    'realisasi_os' => 0.0,
                    'quadrant' => null,
                ];

                $amount = (float) $bucketRow->quality_os;
                $rows[$key]->loan_os += $amount;
                $rows[$key]->total_deb += (int) $bucketRow->quality_deb;

                $bucketColumn = match (strtoupper(trim((string) $bucketRow->quality_bucket))) {
                    'DPK 1' => 'sml_1_os',
                    'DPK 2' => 'sml_2_os',
                    'DPK 3' => 'sml_3_os',
                    'KL' => 'kl_os',
                    'D1' => 'd1_os',
                    'D2' => 'd2_os',
                    'M' => 'm_os',
                    default => null,
                };

                if ($bucketColumn !== null) {
                    $rows[$key]->{$bucketColumn} += $amount;
                }

                $normalizedBucket = strtoupper(trim((string) $bucketRow->quality_bucket));
                $normalizedFlagRestruk = strtoupper(trim((string) ($bucketRow->flag_restruk_normalized ?? '')));
                if (in_array($normalizedBucket, ['DPK 1', 'DPK 2', 'DPK 3'], true)) {
                    $rows[$key]->sml_os += $amount;
                } elseif (in_array($normalizedBucket, ['KL', 'D1', 'D2', 'M'], true)) {
                    $rows[$key]->npl_os += $amount;
                } elseif (in_array($normalizedBucket, ['L', 'LR'], true)) {
                    $rows[$key]->lancar_os += $amount;
                    if ($normalizedBucket === 'LR') {
                        $rows[$key]->restruk_os += $amount;
                    }
                }

                if ($normalizedBucket === 'L' && $normalizedFlagRestruk === 'N') {
                    $rows[$key]->lancar_non_restruk_os += $amount;
                }

                if ($normalizedFlagRestruk === 'Y') {
                    $rows[$key]->account_restruk_os += $amount;
                }
            }

            return collect(array_values($rows));
        });
    }

    private function sourceQualityProductValues(string $segmen, ?string $selectedProduct): array
    {
        $normalizedProduct = $selectedProduct !== null
            ? $this->normalizeProductLabel($selectedProduct, $segmen)
            : null;

        return match ($segmen) {
            'CONSUMER' => ['BRIGUNAKONSUMER', 'KPR'],
            'SMALL' => match ($normalizedProduct) {
                'COMMERCIAL' => ['COMMERCIAL'],
                'CASHCALL' => ['CASHCALL'],
                'CASHCOLLATERAL' => ['CASHCOLLATERAL'],
                default => ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL'],
            },
            default => [],
        };
    }

    private function sortKinerjaRmBranches(array $branches, string $segmen, bool $sortByUnitCode = false): array
    {
        $codeSortKey = static fn ($value): string => trim((string) $value) === '' ? "\xFF" : strtoupper(trim((string) $value));
        $branchFirstCode = static function (array $branch) use ($codeSortKey): string {
            $codes = collect((array) ($branch['rms'] ?? []))
                ->map(fn (array $rmData): string => $codeSortKey($rmData['rm_unit_code'] ?? null))
                ->sort(fn (string $left, string $right): int => strnatcasecmp($left, $right))
                ->values();

            return (string) ($codes->first() ?? "\xFF");
        };

        if ($sortByUnitCode) {
            uksort($branches, function (string $left, string $right) use ($branches, $branchFirstCode): int {
                $codeComparison = strnatcasecmp($branchFirstCode($branches[$left]), $branchFirstCode($branches[$right]));

                return $codeComparison !== 0 ? $codeComparison : strnatcasecmp($left, $right);
            });
        } else {
            uksort($branches, fn (string $left, string $right): int => strnatcasecmp($left, $right));
        }

        foreach ($branches as $branchKey => $branch) {
            $rms = (array) ($branch['rms'] ?? []);
            if ($sortByUnitCode) {
                uasort($rms, function (array $left, array $right) use ($codeSortKey): int {
                    $codeComparison = strnatcasecmp(
                        $codeSortKey($left['rm_unit_code'] ?? null),
                        $codeSortKey($right['rm_unit_code'] ?? null)
                    );

                    return $codeComparison !== 0
                        ? $codeComparison
                        : strnatcasecmp((string) ($left['rm'] ?? ''), (string) ($right['rm'] ?? ''));
                });
            } else {
                uksort($rms, fn (string $left, string $right): int => strnatcasecmp($left, $right));
            }

            foreach ($rms as $rmKey => $rmData) {
                $items = array_values((array) ($rmData['items'] ?? []));
                usort($items, function (array $left, array $right) use ($segmen): int {
                    $leftOrder = $this->productSortOrder((string) ($left['product'] ?? ''), $segmen);
                    $rightOrder = $this->productSortOrder((string) ($right['product'] ?? ''), $segmen);

                    return $leftOrder === $rightOrder
                        ? strnatcasecmp((string) ($left['product'] ?? ''), (string) ($right['product'] ?? ''))
                        : $leftOrder <=> $rightOrder;
                });

                $rms[$rmKey]['items'] = $items;
                $rms[$rmKey]['rm_rowspan'] = count($items);
            }

            $branches[$branchKey]['rms'] = $rms;
            $branches[$branchKey]['branch_rowspan'] = 1 + array_sum(array_map(
                static fn (array $rmData): int => max(0, (int) ($rmData['rm_rowspan'] ?? 0)),
                $rms
            ));
        }

        return $branches;
    }

    private function productSortOrder(string $product, string $segmen): int
    {
        $orderedProducts = self::SEGMENT_PRODUCT_MAP[$segmen] ?? [];
        $normalized = $this->normalizeProductLabel($product, $segmen) ?? strtoupper(trim($product));
        $position = array_search($normalized, $orderedProducts, true);

        return $position === false ? 999 : (int) $position;
    }

    private function reportCacheVersion(): int
    {
        return ReportCacheVersion::composite(['pinjaman', 'simpanan']);
    }

    private function fetchSourceBranchRows(
        string $segmen,
        array $periods,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null
    ): Collection
    {
        if (!Schema::hasTable(self::SOURCE_TABLE) || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return collect();
        }

        $periods = collect($periods)
            ->map(fn ($period) => $this->normalizeDate((string) $period))
            ->filter()
            ->unique()
            ->values();

        if ($periods->isEmpty()) {
            return collect();
        }

        $cabangExpr = 'UPPER(TRIM(cabang1))';
        $unitExpr = 'UPPER(TRIM(unit1))';
        $rmExpr = 'UPPER(TRIM(pn_pengelola1))';
        $productExpr = 'UPPER(TRIM(produk_dashboard))';
        $realisasiDateColumn = Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
        $realisasiDateExpression = $this->performanceRmEffectiveRealisasiDateSql($realisasiDateColumn, 'periode');
        $sourceProducts = $selectedProduct !== null
            ? $this->sourceProductValues($selectedProduct)
            : collect(self::SEGMENT_PRODUCT_MAP[$segmen] ?? [])
                ->flatMap(fn (string $product) => $this->sourceProductValues($product))
                ->unique()
                ->values()
                ->all();

        $realisasiAmountSql = 'COALESCE(plafon, 0)';

        return DB::table(self::SOURCE_TABLE)
            ->whereIn('periode', $periods->all())
            ->whereIn('segmen_dashboard', $this->sourceSegmentValues($segmen))
            ->when($sourceProducts !== [], function ($query) use ($sourceProducts) {
                $query->whereIn('produk_dashboard', $sourceProducts);
            })
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->when($selectedCabang !== null, function ($query) use ($cabangExpr, $selectedCabang) {
                $query->whereRaw("{$cabangExpr} = ?", [$this->normalizeCabangKey($selectedCabang)]);
            })
            ->selectRaw('periode')
            ->selectRaw("{$cabangExpr} as cabang")
            ->selectRaw("{$unitExpr} as unit")
            ->selectRaw("{$rmExpr} as rm")
            ->selectRaw("{$productExpr} as produk")
            ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
            ->selectRaw('SUM(COALESCE(baki_debet1, 0)) as loan_os')
            ->selectRaw('SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os')
            ->selectRaw('SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os')
            ->selectRaw('SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os')
            ->selectRaw("SUM(CASE WHEN kolek = 1 AND UPPER(TRIM(COALESCE(flag_restruk, ''))) = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as total_deb')
            ->selectRaw(
                "COUNT(DISTINCT CASE WHEN {$realisasiDateExpression} BETWEEN DATE_FORMAT(periode, \"%Y-%m-01\") AND periode THEN nomor_rekening1 END) as realisasi_deb"
            )
            ->selectRaw(
                "SUM(CASE WHEN {$realisasiDateExpression} BETWEEN DATE_FORMAT(periode, \"%Y-%m-01\") AND periode THEN {$realisasiAmountSql} ELSE 0 END) as realisasi_os"
            )
            ->selectRaw('0 as total_deposit')
            ->selectRaw('NULL as quadrant')
            ->groupBy('periode', DB::raw($cabangExpr), DB::raw($unitExpr), DB::raw($rmExpr), DB::raw($productExpr))
            ->get();
    }

    private function sourceSegmentValues(string $segmen): array
    {
        return match ($segmen) {
            'CONSUMER' => ['Consumer', 'CONSUMER'],
            'SMALL' => ['Small', 'SMALL'],
            'MICRO' => ['Micro', 'MICRO', 'Mikro', 'MIKRO'],
            default => [$segmen],
        };
    }

    private function sourceProductValues(string $product): array
    {
        return match ($product) {
            'CONSUMER' => ['Briguna-Konsumer', 'BRIGUNA-KONSUMER', 'KPR'],
            'BRIGUNA-KONSUMER' => ['Briguna-Konsumer', 'BRIGUNA-KONSUMER'],
            'KPR' => ['KPR'],
            'SMALL' => ['Commercial', 'COMMERCIAL', 'Cashcall', 'CASHCALL', 'Cash Collateral', 'CashCollateral', 'CASHCOLLATERAL'],
            'COMMERCIAL' => ['Commercial', 'COMMERCIAL'],
            'CASHCALL' => ['Cashcall', 'CASHCALL'],
            'CASHCOLLATERAL' => ['Cash Collateral', 'CashCollateral', 'CASHCOLLATERAL', 'Cashcoll', 'CASHCOLL'],
            'BRIGUNA-MIKRO' => ['Briguna-Mikro', 'BRIGUNA-MIKRO'],
            'KUPEDES' => ['Kupedes', 'KUPEDES'],
            'KUR-MIKRO' => ['KUR-Mikro', 'KUR-MIKRO'],
            'CASHCOLLATERAL' => ['Cash Collateral', 'CashCollateral', 'CASHCOLLATERAL', 'Cashcoll', 'CASHCOLL'],
            'KUR-SMALL' => ['KUR-Small', 'KUR-SMALL'],
            default => [$product],
        };
    }

    private function snapshotProductFilterValues(?string $selectedProduct, string $segmen): array
    {
        if ($selectedProduct === null) {
            return [];
        }

        $normalized = $this->normalizeProductLabel($selectedProduct, $segmen);

        if ($normalized === null) {
            return [];
        }

        return match ($normalized) {
            'CONSUMER' => ['BRIGUNA-KONSUMER', 'KPR'],
            'SMALL' => ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL'],
            'CASHCOLLATERAL' => ['CASHCOLLATERAL', 'CASHCOLL'],
            default => [$normalized],
        };
    }

    /**
     * Resolve manual target JG values for a product label.
     * SMALL is resolved from the legacy product categories so the merged label
     * does not drop target data when the backing table still stores legacy rows.
     *
     * @param Collection<string, Collection<string, object>> $manualTargets
     * @return array{target_jg_deb:int, target_jg_os:float}
     */
    private function resolveManualTargetForProduct(Collection $manualTargets, string $productLabel, string $rmName): array
    {
        if ($productLabel === 'CONSUMER') {
            $consumerTargetKey = preg_replace('/[^A-Z0-9]/', '', strtoupper($rmName)) ?? '';
            $consumerTarget = self::CONSUMER_MONTHLY_TARGETS[$consumerTargetKey] ?? null;

            if ($consumerTarget !== null) {
                return $consumerTarget;
            }
        }

        $lookupCategories = match ($productLabel) {
            'CONSUMER' => ['BRIGUNA-KONSUMER', 'KPR', 'CONSUMER'],
            'SMALL' => ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL'],
            default => [$productLabel],
        };

        $targetDeb = 0;
        $targetOs = 0.0;

        foreach ($lookupCategories as $category) {
            $target = $manualTargets[$category][$rmName] ?? null;
            if ($target === null) {
                continue;
            }

            $targetDeb += (int) ($target->target_deb ?? 0);
            $targetOs += (float) ($target->target_os ?? 0.0);
        }

        return [
            'target_jg_deb' => $targetDeb,
            'target_jg_os' => $targetOs,
        ];
    }

    private function resolveLatestPeriodInScope(
        string $selectedPeriod,
        string $segmen,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null
    ): ?string {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return null;
        }

        $periodStart = Carbon::parse($selectedPeriod)->startOfMonth()->toDateString();
        $periodEnd = Carbon::parse($selectedPeriod)->endOfMonth()->toDateString();
        $productValues = $this->snapshotProductFilterValues($selectedProduct, $segmen);

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->where('segmen', $segmen)
            ->whereBetween('periode', [$periodStart, $periodEnd]);

        if ($selectedCabang !== null) {
            $query->where('cabang', $selectedCabang);
        }

        if ($productValues !== []) {
            $query->whereIn('produk', $productValues);
        }

        $latest = $query->max('periode');

        return $latest !== null ? (string) $latest : null;
    }

    /**
     * Return every snapshot period from the start of the selected year up to the selected period.
     * SMALL uses this to compute an average achievement value across the available monthly snapshots.
     *
     * @return array<int, string>
     */
    private function resolveAveragePeriodsInScope(
        string $selectedPeriod,
        string $segmen,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null
    ): array {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return [];
        }

        $periodStart = Carbon::parse($selectedPeriod)->startOfYear()->toDateString();
        $periodEnd = Carbon::parse($selectedPeriod)->toDateString();
        $productValues = $this->snapshotProductFilterValues($selectedProduct, $segmen);

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->where('segmen', $segmen)
            ->whereBetween('periode', [$periodStart, $periodEnd]);

        if ($selectedCabang !== null) {
            $query->where('cabang', $selectedCabang);
        }

        if ($productValues !== []) {
            $query->whereIn('produk', $productValues);
        }

        $allPeriods = $query
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($period) => (string) $period)
            ->unique()
            ->all();

        $periodsByMonth = [];
        foreach ($allPeriods as $period) {
            $monthKey = substr($period, 0, 7);
            $periodsByMonth[$monthKey] = $period;
        }

        return array_values($periodsByMonth);
    }

    private function resolveSmallClosedPeriodsInScope(
        string $selectedPeriod,
        string $segmen,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null
    ): array {
        $closedThrough = $this->smallClosedThroughDate($selectedPeriod);

        return array_values(array_filter(
            $this->resolveAveragePeriodsInScope($selectedPeriod, $segmen, $selectedCabang, $selectedProduct),
            function (string $period) use ($closedThrough): bool {
                $periodDate = Carbon::parse($period);

                return $periodDate->lte($closedThrough) && $periodDate->isLastOfMonth();
            }
        ));
    }

    private function smallClosedThroughDate(string $selectedPeriod): Carbon
    {
        $selectedDate = Carbon::parse($selectedPeriod)->startOfDay();

        return $selectedDate->isLastOfMonth()
            ? $selectedDate
            : $selectedDate->copy()->startOfMonth()->subDay();
    }

    private function resolveLatestPeriodInYearScope(
        string $selectedPeriod,
        string $segmen,
        ?string $selectedCabang = null,
        ?string $selectedProduct = null
    ): ?string {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return null;
        }

        $periodStart = Carbon::parse($selectedPeriod)->startOfYear()->toDateString();
        $periodEnd = Carbon::parse($selectedPeriod)->toDateString();
        $productValues = $this->snapshotProductFilterValues($selectedProduct, $segmen);

        $query = DB::table(self::SNAPSHOT_TABLE)
            ->where('segmen', $segmen)
            ->whereBetween('periode', [$periodStart, $periodEnd]);

        if ($selectedCabang !== null) {
            $query->where('cabang', $selectedCabang);
        }

        if ($productValues !== []) {
            $query->whereIn('produk', $productValues);
        }

        $latest = $query->max('periode');

        return $latest !== null ? (string) $latest : null;
    }

    private function snapshotRealisasiLooksStale(string $period): bool
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE) || !Schema::hasTable(self::SOURCE_TABLE)) {
            return false;
        }

        $snapshot = DB::table(self::SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->selectRaw('COUNT(*) as cnt, MAX(updated_at) as last_updated')
            ->first();

        if ($snapshot?->cnt <= 0) {
            return false;
        }

        $snapshotUpdatedAt = $snapshot ? Carbon::parse($snapshot->last_updated) : Carbon::now()->subDay();
        $sourceUpdatedAfterSnapshot = $this->dailyLoanSourceUpdatedAfter($period, $snapshotUpdatedAt);

        if ($sourceUpdatedAfterSnapshot) {
            return true;
        }

        if (!Schema::hasColumn(self::SNAPSHOT_TABLE, 'realisasi_deb')
            || (!Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi') && !Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi1'))) {
            return false;
        }

        $realisasiDateColumn = Schema::hasColumn(self::SOURCE_TABLE, 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
        $realisasiDateExpression = $this->performanceRmEffectiveRealisasiDateSql($realisasiDateColumn, 'periode');

        $snapshotHasRealisasi = DB::table(self::SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->where(function ($query) {
                $query->where('realisasi_deb', '>', 0)
                    ->orWhere('realisasi_os', '>', 0);
            })
            ->exists();

        if ($snapshotHasRealisasi) {
            return false;
        }

        $date = Carbon::parse($period);
        return DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereRaw("{$realisasiDateExpression} BETWEEN ? AND ?", [
                $date->copy()->startOfMonth()->toDateString(),
                $period,
            ])
            ->exists();
    }

    private function queueDailyLoanSnapshotSyncIfNeeded(?string $period, string $source): void
    {
        $period = $this->normalizeDate($period);
        if ($period === null
            || !Schema::hasTable(self::SOURCE_TABLE)
            || !Schema::hasTable(self::SNAPSHOT_TABLE)
            || !DB::table(self::SOURCE_TABLE)->where('periode', $period)->exists()) {
            return;
        }

        $snapshot = DB::table(self::SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw(Schema::hasColumn(self::SNAPSHOT_TABLE, 'updated_at') ? 'MAX(updated_at) as last_updated' : 'NULL as last_updated')
            ->first();

        $snapshotCount = (int) ($snapshot->cnt ?? 0);
        $lastUpdated = $snapshot?->last_updated ? Carbon::parse($snapshot->last_updated) : null;
        $needsSync = $snapshotCount <= 0
            || ($lastUpdated !== null && $this->dailyLoanSourceUpdatedAfter($period, $lastUpdated))
            || $this->snapshotRealisasiLooksStale($period);

        if (!$needsSync) {
            return;
        }

        $pendingKey = 'snapshot:daily_loan:auto-sync:view:performance_rm:' . $period;
        if (!Cache::add($pendingKey, true, now()->addMinutes(10))) {
            return;
        }

        SyncImportedReportJob::dispatch(
            null,
            self::SOURCE_TABLE,
            $period,
            $source
        )->onQueue((string) config('queue.report_queue', 'default'));
    }

    private function dailyLoanSourceUpdatedAfter(string $period, Carbon $snapshotUpdatedAt): bool
    {
        $hasUpdatedAt = Schema::hasColumn(self::SOURCE_TABLE, 'updated_at');
        $hasCreatedAt = Schema::hasColumn(self::SOURCE_TABLE, 'created_at');

        if (!$hasUpdatedAt && !$hasCreatedAt) {
            return false;
        }

        return DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->where(function ($query) use ($snapshotUpdatedAt, $hasUpdatedAt, $hasCreatedAt) {
                if ($hasUpdatedAt) {
                    $query->orWhere('updated_at', '>', $snapshotUpdatedAt);
                }

                if ($hasCreatedAt) {
                    $query->orWhere('created_at', '>', $snapshotUpdatedAt);
                }
            })
            ->exists();
    }


    private function mapRmName(string $rmName): string
    {
        if (str_contains(strtoupper($rmName), '00385844 -')) {
            return '00385844 - Glagah Mahestya Yahya';
        }
        return $rmName;
    }

    private function resolveExistingColumn(string $table, array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return $fallback;
    }

    private function resolveCabangColumn(): string
    {
        return $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['cabang1', 'cabang'],
            'cabang1'
        );
    }

    private function resolveProductColumn(): string
    {
        return $this->resolveExistingColumn(
            'daily_loan_dinamis',
            ['produk_dashboard', 'produk'],
            'produk_dashboard'
        );
    }

    private function sumRkaValuesByProducts(array $values, ?string $selectedCabang = null, ?string $selectedProduct = null): float
    {
        $productKeys = $this->resolveRkaProductKeys($selectedProduct);

        if ($selectedCabang !== null) {
            $cabangKey = strtoupper($selectedCabang);
            $total = 0.0;

            foreach ($productKeys as $productKey) {
                $total += (float) ($values[$productKey][$cabangKey] ?? 0);
            }

            return $total;
        }

        $total = 0.0;
        foreach ($productKeys as $productKey) {
            $total += array_reduce(
                $values[$productKey] ?? [],
                fn (float $carry, $value) => $carry + (float) $value,
                0.0
            );
        }

        return $total;
    }

    private function normalizeProductLabel(?string $value, string $segmen = 'CONSUMER'): ?string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';

        // Normalize based on segmen
        $productMap = match($segmen) {
            'CONSUMER' => [
                'CONSUMER' => 'CONSUMER',
                'BRIGUNAKONSUMER' => 'CONSUMER',
                'KPR' => 'CONSUMER',
            ],
            'SMALL' => [
                'COMMERCIAL' => 'COMMERCIAL',
                'CASHCALL' => 'CASHCALL',
                'CASHCOLLATERAL' => 'CASHCOLLATERAL',
                'SMALL' => 'SMALL',
            ],
            'MICRO' => [
                'BRIGUNAMIKRO' => 'BRIGUNA-MIKRO',
                'KUPEDES' => 'KUPEDES',
                'KURMIKRO' => 'KUR-MIKRO',
                'CASHCOLLATERAL' => 'CASHCOLLATERAL',
                'CASHCOLL' => 'CASHCOLLATERAL',
                'KPR' => 'KPR',
                'KURSMALL' => 'KUR-SMALL',
            ],
            default => []
        };

        return $productMap[$normalized] ?? null;
    }

    private function normalizedColumnExpression(string $column): string
    {
        return "UPPER(TRIM(REPLACE(REPLACE(CAST({$column} AS CHAR), '_', '-'), ' ', '-')))";
    }

    private function normalizeCabangKey(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function sanitizeCabangLabel(?string $value): string
    {
        return trim((string) $value);
    }

    private function resolveRkaProductKeys(?string $productLabel): array
    {
        return match ($productLabel) {
            'BRIGUNA-KONSUMER' => ['briguna_konsumer'],
            'KPR' => ['kpr'],
            default => ['briguna_konsumer', 'kpr'],
        };
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $strict = StrictDateParser::normalize($value);
        if ($strict !== null) {
            return $strict;
        }

        $clamped = $this->normalizeInvalidMonthEndDate($value);
        if ($clamped !== null) {
            return $clamped;
        }

        return null;
    }

    private function normalizeInvalidMonthEndDate(string $value): ?string
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) !== 1) {
            return null;
        }

        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];

        if ($year < 1900 || $year > 2100 || $month < 1 || $month > 12 || $day < 1) {
            return null;
        }

        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        return $day > $endOfMonth->day
            ? $endOfMonth->toDateString()
            : null;
    }

    private function normalizeNumericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatAmountInJuta(mixed $value, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format($normalized / 1000000, $decimals, ',', '.');
    }

    private function formatPlainAmountInJuta(mixed $value): string
    {
        $normalized = $this->normalizeNumericValue($value);

        return $normalized === null
            ? '-'
            : (string) (int) round($normalized / 1000000);
    }

    private function formatPlainCount(mixed $value): string
    {
        $normalized = $this->normalizeNumericValue($value);

        return $normalized === null ? '-' : (string) (int) round($normalized);
    }

    private function formatPlainDeltaInJuta(mixed $value): string
    {
        $normalized = $this->normalizeNumericValue($value);
        if ($normalized === null) {
            return '-';
        }

        $amount = (int) round($normalized / 1000000);

        return $amount > 0 ? '+' . $amount : (string) $amount;
    }

    private function formatSignedAmountInJuta(mixed $value, bool $showArrow = true, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return "<span class='delta-indicator'>-</span>";
        }

        $amount = $normalized / 1000000;
        $cls = $amount > 0 ? 'pos' : ($amount < 0 ? 'neg' : '');
        $icon = '';

        if ($showArrow) {
            if ($amount > 0) {
                $icon = '<i class="fas fa-caret-up me-1"></i>';
            } elseif ($amount < 0) {
                $icon = '<i class="fas fa-caret-down me-1"></i>';
            }
        }

        $prefix = ($amount > 0 && ! $showArrow) ? '+' : '';
        $display = number_format(abs($amount), $decimals, ',', '.');

        if ($amount < 0 && ! $showArrow) {
            $display = '-' . $display;
        }

        return "<span class='delta-indicator {$cls}'>{$icon}{$prefix}{$display}</span>";
    }

    private function formatCount(mixed $value): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format((int) round($normalized), 0, ',', '.');
    }

    private function formatPercent(mixed $value, int $decimals = 1): string
    {
        $normalized = $this->normalizeNumericValue($value);

        if ($normalized === null) {
            return '-';
        }

        return number_format($normalized, $decimals, ',', '.');
    }

    private function normalizeQuadrant(mixed $quadrant): ?int
    {
        $normalized = $this->normalizeNumericValue($quadrant);

        if ($normalized === null) {
            return null;
        }

        $quadrantValue = (int) $normalized;

        return in_array($quadrantValue, [1, 2, 3, 4], true) ? $quadrantValue : null;
    }

    private function calculateSmallQuadrantsByRm(array $pivoted, int $periodCount): array
    {
        $inputs = [];

        foreach ($pivoted as $data) {
            $rmName = $this->mapRmName((string) ($data['rm'] ?? ''));
            $rmCategory = $data['rm_category'] ?? null;
            $rmUnit = $data['rm_unit'] ?? null;
            $rmGroupKey = $this->kinerjaRmGroupKey($rmName, $rmCategory, $rmUnit);
            $productLabel = $this->normalizeProductLabel((string) ($data['produk'] ?? ''), 'SMALL');

            if ($rmName === '' || $productLabel === null) {
                continue;
            }

            $inputs[$rmGroupKey] ??= [
                'snapshot_quadrant' => null,
                'realisasi_os_sum' => 0.0,
                'lar_loan_os' => 0.0,
                'lar_value' => 0.0,
                'lar_has_data' => false,
            ];

            $inputs[$rmGroupKey]['snapshot_quadrant'] ??= $this->normalizeQuadrant($data['quadrant'] ?? null);
            $inputs[$rmGroupKey]['realisasi_os_sum'] += (float) ($data['quadrant_realisasi_os_sum'] ?? 0);
            $inputs[$rmGroupKey]['lar_loan_os'] += (float) ($data['lar_loan_os'] ?? 0);
            $inputs[$rmGroupKey]['lar_value'] += (float) ($data['lar_value'] ?? 0);
            $inputs[$rmGroupKey]['lar_has_data'] = $inputs[$rmGroupKey]['lar_has_data'] || (bool) ($data['lar_has_data'] ?? false);
        }

        $quadrants = [];
        foreach ($inputs as $rmName => $input) {
            if ($periodCount <= 0 || !$input['lar_has_data'] || (float) $input['lar_loan_os'] <= 0) {
                if ($input['snapshot_quadrant'] !== null) {
                    $quadrants[$rmName] = $input['snapshot_quadrant'];
                }

                continue;
            }

            $ratasOs = (float) $input['realisasi_os_sum'] / $periodCount;
            $larPct = ((float) $input['lar_value'] / (float) $input['lar_loan_os']) * 100;
            $quadrants[$rmName] = $this->calculateSmallQuadrant($ratasOs, $larPct);
        }

        return $quadrants;
    }

    private function calculateSmallQuadrant(float $ratasOs, float $larPct): int
    {
        $isRatasA = ($ratasOs / 1000000) >= 1600;
        $isLarA = $larPct < 17.5;

        return match (true) {
            $isRatasA && $isLarA => 1,
            $isRatasA => 2,
            $isLarA => 3,
            default => 4,
        };
    }

    private function calculateConsumerQuadrant(mixed $achievementOs, float $targetOs): ?int
    {
        $achievement = $this->normalizeNumericValue($achievementOs);

        if ($achievement === null || $targetOs <= 0.0) {
            return null;
        }

        $achievementPct = ($achievement / $targetOs) * 100;

        return match (true) {
            $achievementPct >= 105.0 => 1,
            $achievementPct >= 100.0 => 2,
            $achievementPct >= 50.0 => 3,
            default => 4,
        };
    }

    private function formatQuadrantLabel(mixed $quadrant): string
    {
        $normalized = $this->normalizeQuadrant($quadrant);

        return $normalized !== null ? 'Kuadran ' . $normalized : '-';
    }

    private function formatQuadrantClass(mixed $quadrant): string
    {
        $normalized = $this->normalizeQuadrant($quadrant);

        return $normalized !== null ? 'q' . $normalized : '';
    }
}
