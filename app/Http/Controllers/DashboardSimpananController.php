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
    private const CACHE_LOCK_SECONDS = 20;
    private const SNAPSHOT_SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const SNAPSHOT_BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';
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
        $payloadCacheKey = 'dashboard_simpanan:payload:v' . $this->reportCacheVersion();

        return Cache::remember($payloadCacheKey, now()->addMinutes(self::PAYLOAD_CACHE_MINUTES), function () {
            return $this->buildDashboardPayloadFresh();
        });
    }

    private function buildDashboardPayloadFresh(): array
    {
        if (!Schema::hasTable('simpanan_multipn')) {
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
        $cacheKey = 'dashboard_simpanan:summary:v' . $this->reportCacheVersion() . ':' . $period;
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
        $cacheKey = 'dashboard_simpanan:top_branches:v' . $this->reportCacheVersion() . ':' . $period;

        $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), function () use ($period) {
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

    private function emptyDashboard(): array
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
            'digital_performance' => $this->buildDigitalPerformance(),
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
        $cacheKey = 'dashboard_pinjaman:periods:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
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
        $cacheKey = 'dashboard_pinjaman:top_branches:v' . $this->reportCacheVersion() . ':' . $period;

        $rows = Cache::remember($cacheKey, now()->addMinutes(self::TOP_BRANCH_CACHE_MINUTES), function () use ($period) {
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
        $cacheKey = 'dashboard_simpanan:periods:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
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

    private function buildDigitalPerformance(): array
    {
        $cacheKey = 'dashboard_simpanan:digital_performance:v' . $this->reportCacheVersion();

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

    private function buildRekeningDormantKpiCard(): ?array
    {
        try {
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

    private function buildTimeseriesPayload(?string $simpananPeriod, ?string $loanPeriod): array
    {
        $cacheKey = 'dashboard_simpanan:timeseries:v' . $this->reportCacheVersion() . ':' . ($simpananPeriod ?? 'null') . ':' . ($loanPeriod ?? 'null');

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($simpananPeriod, $loanPeriod) {
            $points = 6;
            $simpananTimeline = [];
            $loanTimeline = [];
            $labels = [];

            // Build simpanan timeseries
            if ($simpananPeriod && Schema::hasTable('simpanan_multipn')) {
                $current = Carbon::parse($simpananPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $actualPeriod = DB::table('simpanan_multipn')->where('posisi', '<=', $p)->max('posisi');
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
            if ($loanPeriod && (Schema::hasTable('daily_loan_dinamis') || Schema::hasTable(self::LOAN_SNAPSHOT_TABLE))) {
                $current = Carbon::parse($loanPeriod)->startOfDay();
                for ($offset = $points - 1; $offset >= 0; $offset--) {
                    $p = $offset === 0
                        ? $current->toDateString()
                        : $current->copy()->subMonthsNoOverflow($offset)->endOfMonth()->toDateString();

                    $table = Schema::hasTable(self::LOAN_SNAPSHOT_TABLE) ? self::LOAN_SNAPSHOT_TABLE : 'daily_loan_dinamis';
                    $periodeCol = Schema::hasTable(self::LOAN_SNAPSHOT_TABLE) ? 'periode' : 'periode';
                    $actualPeriod = DB::table($table)->where($periodeCol, '<=', $p)->max($periodeCol);
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
        return ReportCacheVersion::composite(['simpanan', 'pinjaman']);
    }
}
