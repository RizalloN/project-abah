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
use Illuminate\Http\Request;
use Throwable;

class DashboardSimpananController extends Controller
{
    private const PAYLOAD_CACHE_MINUTES = 2;
    private const SUMMARY_CACHE_MINUTES = 5;
    private const SUMMARY_LATEST_CACHE_MINUTES = 30;
    private const TOP_BRANCH_CACHE_MINUTES = 5;
    private const DIGITAL_PERFORMANCE_CACHE_MINUTES = 10;
    private const LOAN_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const LANDING_SOURCE_CACHE_VERSION = 'harian_snapshot_v4';
    private const CACHE_LOCK_SECONDS = 20;
    private const SNAPSHOT_SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const SNAPSHOT_BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const AREA_6_BRANCH_LABELS = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
    private array $snapshotExistsMemo = [];

    public function index(): View
    {
        $dashboard = $this->buildDashboardPayload();

        return view('dashboard', [
            'dashboard' => $dashboard,
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

    private function buildDashboardPayload(): array
    {
        $cacheVersion = $this->reportCacheVersion();
        $payloadCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $cacheVersion;
        $latestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest:v' . $cacheVersion;
        $stableLatestCacheKey = 'dashboard_simpanan:payload:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest';
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

    private function buildDashboardPayloadFresh(): array
    {
        if (!Schema::hasTable('simpanan_multipn') && !Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return $this->emptyDashboard();
        }

        [$currentPeriod, $previousPeriod, $yoyPeriod] = $this->resolveDashboardPeriods();
        [$loanCurrentPeriod, $loanPreviousPeriod, $loanYoyPeriod] = $this->resolveLoanDashboardPeriods();

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
                    'label' => 'Growth Simpanan MoM',
                    'value' => $this->formatSignedPercent($savingsMoM),
                    'delta' => 'vs ' . ($previousPeriod ? $this->formatPeriodLabel($previousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($savingsMoM),
                    'icon' => 'fas fa-wallet',
                    'icon_class' => 'text-warning',
                    'icon_bg' => 'rgba(255, 193, 7, 0.16)',
                ],
                [
                    'label' => 'Growth Pinjaman MoM',
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
        $stableLatestCacheKey = 'dashboard_simpanan:area6_portfolio:' . self::LANDING_SOURCE_CACHE_VERSION . ':latest';
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
            $locked = $lock->get();

            if ($locked) {
                $freshPayload = $this->buildArea6PortfolioLandingFresh($loanPeriod, $dailyLoanPeriod);
                Cache::put($cacheKey, $freshPayload, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES));
                Cache::put($latestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));
                Cache::put($stableLatestCacheKey, $freshPayload, now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES));

                return $freshPayload;
            }
        } catch (Throwable $e) {
            Log::warning('Dashboard simpanan Area 6 fallback digunakan.', [
                'period' => $loanPeriod,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($locked) {
                $lock->release();
            }
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
        $ktsUnit = $this->buildArea6KtsRanking($dailyLoanPeriod, 'unit');
        $ktsBranch = $this->buildArea6KtsRanking($dailyLoanPeriod, 'retail');
        $smallArrearsUnit = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'unit');
        $smallArrearsBranch = $this->buildArea6SmallArrearsRanking($dailyLoanPeriod, 'retail');
        $microRankings = $this->buildArea6RankingGroups($harian['unit_rows'], $ktsUnit, $smallArrearsUnit, 'unit');
        $retailRankings = $this->buildArea6RankingGroups($harian['branch_rows'], $ktsBranch, $smallArrearsBranch, 'retail');
        $periodLabel = $this->formatSourcePeriodLabel($harian['period']);
        $loanPeriodLabel = $this->formatSourcePeriodLabel($loanPeriod);
        $dailyLoanPeriodLabel = $this->formatSourcePeriodLabel($dailyLoanPeriod);

        return [
            'title' => 'Kinerja Area 6',
            'subtitle' => 'Ringkasan cepat dari snapshot Dashboard Harian dan Pinjaman. Pilih Ritel untuk KC/KCP atau Mikro untuk unit.',
            'period_label' => $periodLabel,
            'loan_period_label' => $loanPeriodLabel,
            'loan_detail_period_label' => $dailyLoanPeriodLabel,
            'default_scope' => 'ritel',
            'cards' => [
                    [
                        'key' => 'os',
                        'label' => 'Pinjaman (Outstanding)',
                        'value' => $this->formatCurrencyCompact($harian['totals']['total_os']),
                        'meta' => 'OS Non Commercial ' . $this->formatCurrencyCompact($harian['totals']['total_os_non_commercial']),
                        'icon' => 'fas fa-hand-holding-usd',
                        'tone' => 'blue',
                        'detail_payload' => $this->buildLandingSourceDetail('Pinjaman Outstanding Area 6', $harian['period'], self::HARIAN_SNAPSHOT_TABLE, [
                            ['label' => 'Total OS', 'value' => $this->formatCurrencyFull($harian['totals']['total_os']), 'source' => 'SUM total_os, baris summary kanca'],
                            ['label' => 'OS Non Commercial', 'value' => $this->formatCurrencyFull($harian['totals']['total_os_non_commercial']), 'source' => 'SUM total_os_non_commercial'],
                            ['label' => 'Unit terbaca', 'value' => $this->formatInteger(count($harian['unit_rows'])), 'source' => self::HARIAN_SNAPSHOT_TABLE],
                        ], 'Sumber mengikuti snapshot Dashboard Harian terbaru untuk summary kanca Area 6.'),
                    ],
                    [
                        'key' => 'quality',
                        'label' => 'Kualitas SML / NPL',
                        'value' => $this->formatPercentTwo($harian['totals']['sml_pct']) . ' / ' . $this->formatPercentTwo($harian['totals']['npl_pct']),
                        'meta' => 'SML ' . $this->formatCurrencyCompact($harian['totals']['sml_abs']) . ' | NPL ' . $this->formatCurrencyCompact($harian['totals']['npl_abs']),
                        'icon' => 'fas fa-shield-alt',
                        'tone' => 'red',
                        'detail_payload' => $this->buildLandingSourceDetail('Kualitas Pinjaman Area 6', $harian['period'], self::HARIAN_SNAPSHOT_TABLE, [
                            ['label' => 'SML (%)', 'value' => $this->formatPercentTwo($harian['totals']['sml_pct']), 'source' => 'total_sml_abs_non_commercial / total_os_non_commercial'],
                            ['label' => 'SML (ABS)', 'value' => $this->formatCurrencyFull($harian['totals']['sml_abs']), 'source' => 'SUM total_sml_abs_non_commercial'],
                            ['label' => 'NPL (%)', 'value' => $this->formatPercentTwo($harian['totals']['npl_pct']), 'source' => 'total_npl_abs_non_commercial / total_os_non_commercial'],
                            ['label' => 'NPL (ABS)', 'value' => $this->formatCurrencyFull($harian['totals']['npl_abs']), 'source' => 'SUM total_npl_abs_non_commercial'],
                        ], 'Persentase dihitung ulang dari agregat Area 6 agar tidak menjumlahkan persen per unit.'),
                    ],
                    [
                        'key' => 'ldr',
                        'label' => 'LDR',
                        'value' => $this->formatPercentTwo($harian['totals']['ldr_pct']),
                        'meta' => 'OS Non Commercial / Simpanan',
                        'icon' => 'fas fa-layer-group',
                        'tone' => 'green',
                        'detail_payload' => $this->buildLandingSourceDetail('LDR Area 6', $harian['period'], self::HARIAN_SNAPSHOT_TABLE, [
                            ['label' => 'LDR', 'value' => $this->formatPercentTwo($harian['totals']['ldr_pct']), 'source' => 'total_os_non_commercial / total_simpanan'],
                            ['label' => 'Total Simpanan', 'value' => $this->formatCurrencyFull($harian['totals']['total_simpanan']), 'source' => 'SUM total_simpanan'],
                        ], 'LDR Area 6 mengikuti denominator simpanan pada Dashboard Harian.'),
                    ],
                    [
                        'key' => 'casa',
                        'label' => 'CASA',
                        'value' => $this->formatPercentTwo($harian['totals']['casa_pct']),
                        'meta' => 'Total CASA ' . $this->formatCurrencyCompact($harian['totals']['total_casa']),
                        'icon' => 'fas fa-percentage',
                        'tone' => 'amber',
                        'detail_payload' => $this->buildLandingSourceDetail('CASA Area 6', $harian['period'], self::HARIAN_SNAPSHOT_TABLE, [
                            ['label' => 'CASA (%)', 'value' => $this->formatPercentTwo($harian['totals']['casa_pct']), 'source' => 'total_casa / total_simpanan'],
                            ['label' => 'Total CASA', 'value' => $this->formatCurrencyFull($harian['totals']['total_casa']), 'source' => 'SUM total_casa'],
                        ], 'CASA diambil dari snapshot Dashboard Harian, bukan dari angka pengganti.'),
                    ],
                    [
                        'key' => 'rec_dh',
                        'label' => 'Rec. DH',
                        'value' => $this->formatCurrencyCompact($harian['totals']['rec_dh_total']),
                        'meta' => 'Recovery ekstra komtable',
                        'icon' => 'fas fa-undo-alt',
                        'tone' => 'purple',
                        'detail_payload' => $this->buildLandingSourceDetail('Rec. DH Area 6', $harian['period'], self::HARIAN_SNAPSHOT_TABLE, [
                            ['label' => 'Total Rec. DH', 'value' => $this->formatCurrencyFull($harian['totals']['rec_dh_total']), 'source' => 'SUM rec_dh_total'],
                        ], 'Mengikuti nilai Rec. DH yang sudah diringkas oleh Dashboard Harian.'),
                    ],
                    [
                        'key' => 'kts',
                        'label' => 'Kolek Tidak Sesuai (KTS)',
                        'value' => $this->formatInteger($ktsUnit['total_count']) . ' rek',
                        'meta' => $this->formatCurrencyCompact($ktsUnit['total_os']) . ' OS terdampak',
                        'icon' => 'fas fa-exclamation-triangle',
                        'tone' => 'orange',
                        'detail_payload' => $this->buildLandingSourceDetail('KTS Area 6', $dailyLoanPeriod, 'daily_loan_dinamis', [
                            ['label' => 'Total rekening KTS', 'value' => $this->formatInteger($ktsUnit['total_count']), 'source' => 'kolek_vs_umur_tunggakan_v3'],
                            ['label' => 'OS terdampak', 'value' => $this->formatCurrencyFull($ktsUnit['total_os']), 'source' => 'SUM baki_debet1 pada rekening mismatch'],
                        ], 'Rule KTS mengikuti Dashboard Pinjaman: status rekening 1/3, baki debet positif, kolek dibanding umur tunggakan.'),
                    ],
                    [
                        'key' => 'small_arrears',
                        'label' => 'Tunggakan Kecil',
                        'value' => $this->formatInteger($smallArrearsUnit['total_count']) . ' rek',
                        'meta' => $this->formatCurrencyCompact($smallArrearsUnit['total_amount']) . ' total tunggakan',
                        'icon' => 'fas fa-coins',
                        'tone' => 'teal',
                        'detail_payload' => $this->buildLandingSourceDetail('Tunggakan Kecil Area 6', $dailyLoanPeriod, 'daily_loan_dinamis', [
                            ['label' => 'Total rekening', 'value' => $this->formatInteger($smallArrearsUnit['total_count']), 'source' => 'Total tunggakan > 0 dan <= 100.000'],
                            ['label' => 'Total tunggakan', 'value' => $this->formatCurrencyFull($smallArrearsUnit['total_amount']), 'source' => 'tunggakan pokok + bunga + penalti'],
                        ], 'Rule mengikuti menu Tunggakan Kecil pada Dashboard Pinjaman.'),
                    ],
                ],
                'ranking_modes' => [
                    'ritel' => [
                        'label' => 'Ritel (KC/KCP)',
                        'description' => 'Ranking level kantor cabang/kantor cabang pembantu.',
                        'rankings' => $retailRankings,
                    ],
                    'mikro' => [
                        'label' => 'Mikro (Unit)',
                        'description' => 'Ranking level unit kerja mikro.',
                        'rankings' => $microRankings,
                    ],
                ],
                'rankings' => $retailRankings,
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
        ];
    }

    private function resolveArea6DailyLoanPeriod(?string $requestedPeriod): ?string
    {
        if (!Schema::hasTable('daily_loan_dinamis')) {
            return null;
        }

        $cacheKey = 'dashboard_simpanan:area6_daily_loan_period:' . self::LANDING_SOURCE_CACHE_VERSION . ':v' . $this->reportCacheVersion() . ':' . ($requestedPeriod ?? 'latest');

        return Cache::remember($cacheKey, now()->addMinutes(2), function () use ($requestedPeriod) {
            $query = DB::table('daily_loan_dinamis')
                ->whereIn('cabang1', self::AREA_6_BRANCH_LABELS);

            if ($requestedPeriod) {
                $period = (clone $query)
                    ->where('periode', '<=', $requestedPeriod)
                    ->max('periode');

                if ($period) {
                    return Carbon::parse($period)->toDateString();
                }
            }

            $period = $query->max('periode');

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

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $summaryRows
                ? $query->whereColumn('kanca_key', 'unit_key')
                : $query->whereColumn('kanca_key', '<>', 'unit_key');
        }

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', $summaryRows ? 'branch' : 'unit');
        }

        return $query;
    }

    private function area6HarianSnapshotSummaryQuery()
    {
        $query = DB::table(self::HARIAN_SNAPSHOT_TABLE);

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), $this->dashboardBranchNames());
        } elseif (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $this->dashboardBranchNames());
        }

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'kanca_key') && Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'unit_key')) {
            return $query->whereColumn('kanca_key', 'unit_key');
        }

        if (Schema::hasColumn(self::HARIAN_SNAPSHOT_TABLE, 'scope')) {
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

        $rows = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as kantor_cabang")
            ->selectRaw('COALESCE(SUM(COALESCE(total_simpanan, 0)), 0) as total_balance')
            ->groupBy(DB::raw("COALESCE({$branchLabelExpression}, '')"))
            ->orderByDesc('total_balance')
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

        $rows = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', $period)
            ->selectRaw("COALESCE({$branchLabelExpression}, '') as cabang1")
            ->selectRaw('COALESCE(SUM(COALESCE(total_os, 0)), 0) as total_balance')
            ->groupBy(DB::raw("COALESCE({$branchLabelExpression}, '')"))
            ->orderByDesc('total_balance')
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
                'hint' => 'Outstanding terbesar per ' . $targetLabel,
                'tone' => 'blue',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'desc', 5, 'currency', false, null, $scope),
            ],
            [
                'title' => '5 OS Terkecil',
                'hint' => 'OS positif paling kecil per ' . $targetLabel,
                'tone' => 'slate',
                'rows' => $this->rankHarianUnits($rows, 'total_os', 'asc', 5, 'currency', true, null, $scope),
            ],
            [
                'title' => '5 SML Nominal',
                'hint' => 'Nominal SML terbesar per ' . $targetLabel,
                'tone' => 'amber',
                'rows' => $this->rankHarianUnits($rows, 'sml_abs', 'desc', 5, 'currency', false, 'sml_pct', $scope),
            ],
            [
                'title' => '5 SML Rasio',
                'hint' => 'Rasio SML paling tinggi per ' . $targetLabel,
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'sml_pct', 'desc', 5, 'percent', false, 'sml_abs', $scope),
            ],
            [
                'title' => '5 NPL Nominal',
                'hint' => 'Nominal NPL terbesar per ' . $targetLabel,
                'tone' => 'orange',
                'rows' => $this->rankHarianUnits($rows, 'npl_abs', 'desc', 5, 'currency', false, 'npl_pct', $scope),
            ],
            [
                'title' => '5 NPL Rasio',
                'hint' => 'Rasio NPL paling tinggi per ' . $targetLabel,
                'tone' => 'red',
                'rows' => $this->rankHarianUnits($rows, 'npl_pct', 'desc', 5, 'percent', false, 'npl_abs', $scope),
            ],
            [
                'title' => '3 KTS Terbanyak',
                'hint' => 'Kolek tidak sesuai per ' . $targetLabel,
                'tone' => 'orange',
                'rows' => $kts['rows'],
            ],
            [
                'title' => '3 Tunggakan Kecil',
                'hint' => 'Jumlah rekening tunggakan kecil per ' . $targetLabel,
                'tone' => 'teal',
                'rows' => $smallArrears['rows'],
            ],
        ];
    }

    private function rankHarianUnits(array $rows, string $field, string $direction, int $limit, string $format, bool $positiveOnly = false, ?string $secondaryField = null, string $scope = 'unit'): array
    {
        $labelField = $scope === 'unit' ? 'unit' : 'branch';
        $metaField = $scope === 'unit' ? 'branch' : 'unit';

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

        $cacheKey = 'dashboard_simpanan:area6_kts:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

        return Cache::remember($cacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () use ($period, $scope) {
            $actualKolekExpression = "CAST(REGEXP_REPLACE(TRIM(COALESCE(kolek, '')), '[^0-9]', '') AS UNSIGNED)";
            $umurTunggakanExpression = "CAST(REGEXP_REPLACE(REPLACE(TRIM(COALESCE(umur_tunggakan, '')), ',', ''), '[^0-9-]', '') AS SIGNED)";
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
                ->whereIn(DB::raw('TRIM(status_rekening1)'), ['1', '3'])
                ->where('baki_debet1', '>', 0)
                ->whereRaw("TRIM(COALESCE(kolek, '')) REGEXP '^[^0-9]*[1-5][^0-9]*$'")
                ->whereRaw("REPLACE(TRIM(COALESCE(umur_tunggakan, '')), ',', '') REGEXP '-?[0-9]+'")
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
                ->limit(3)
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
                        'meta' => $scope === 'unit' ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
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

        $cacheKey = 'dashboard_simpanan:area6_small_arrears:v' . $this->reportCacheVersion() . ':' . $period . ':' . $scope;

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
                ->limit(3)
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
                        'meta' => $scope === 'unit' ? (string) ($row->cabang1 ?? 'Area 6') : 'Ritel Area 6',
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
                ['label' => 'Growth Simpanan MoM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-wallet', 'icon_class' => 'text-warning', 'icon_bg' => 'rgba(255, 193, 7, 0.16)'],
                ['label' => 'Growth Pinjaman MoM', 'value' => '0,0%', 'delta' => 'vs periode sebelumnya', 'delta_class' => 'text-muted', 'icon' => 'fas fa-database', 'icon_class' => 'text-success', 'icon_bg' => 'rgba(40, 167, 69, 0.14)'],
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

    private function resolveLoanDashboardPeriods(): array
    {
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

        $summary = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->selectRaw('COALESCE(SUM(COALESCE(baki_debet1, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT nomor_rekening1) as account_count')
            ->selectRaw('COUNT(DISTINCT cabang1) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit1) as unit_count')
            ->selectRaw('MAX(updated_at) as source_updated_at')
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

            if (Schema::hasTable(self::LOAN_SNAPSHOT_TABLE)) {
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

        try {
            if ($lock->get()) {
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

    private function resolveDashboardPeriods(): array
    {
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
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
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
        if (!Schema::hasTable(self::HARIAN_SNAPSHOT_TABLE)) {
            return null;
        }

        $actualPeriod = $this->area6HarianSnapshotSummaryQuery()
            ->where('snapshot_period', '<=', $period)
            ->max('snapshot_period');

        return $actualPeriod ? Carbon::parse($actualPeriod)->toDateString() : null;
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
            // Coba beberapa nama tabel QLola yang mungkin ada
            $tableName = null;
            foreach (['qlola_detail', 'qlola_report', 'qlola_summary'] as $tbl) {
                if (Schema::hasTable($tbl)) {
                    $tableName = $tbl;
                    break;
                }
            }

            if (!$tableName) {
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
                    'detail_payload' => $this->buildLandingSourceDetail('Performance QLola', null, 'qlola_detail / qlola_report / qlola_summary', [['label' => 'Status', 'value' => 'Tabel sumber belum tersedia', 'source' => 'Schema check']], 'Landing page tidak membuat angka pengganti saat tabel QLola belum ada.'),
                ];
            }

            $latestPeriod = DB::table($tableName)->max('posisi') ?? DB::table($tableName)->max('periode');
            if (!$latestPeriod) {
                return null;
            }

            $periods = $this->buildTrendDatePeriods($latestPeriod);
            $branches = $this->dashboardBranchNames();
            $timeline = [];

            foreach ($periods as $period) {
                $row = DB::table($tableName)
                    ->whereDate(Schema::hasColumn($tableName, 'posisi') ? 'posisi' : 'periode', $period)
                    ->whereIn(DB::raw('UPPER(TRIM(' . (Schema::hasColumn($tableName, 'kanca') ? 'kanca' : 'cabang') . '))'), $branches)
                    ->selectRaw('COUNT(DISTINCT ' . (Schema::hasColumn($tableName, 'cif') ? 'cif' : 'id') . ') as nasabah_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(' . (Schema::hasColumn($tableName, 'nominal') ? 'nominal' : '0') . ', 0)), 0) as volume')
                    ->first();

                $timeline[] = [
                    'label' => Carbon::parse($period)->translatedFormat('d M'),
                    'nasabah_count' => (int) ($row->nasabah_count ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
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
                'source_table' => $tableName,
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

        return $prefix . number_format($value, 1, ',', '.') . '%';
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
