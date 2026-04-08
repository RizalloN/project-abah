<?php

namespace App\Http\Controllers;

use App\Support\ReportDataSyncService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardSimpananController extends Controller
{
    private const SUMMARY_CACHE_MINUTES = 5;
    private const SUMMARY_LATEST_CACHE_MINUTES = 30;
    private const TOP_BRANCH_CACHE_MINUTES = 5;
    private const CACHE_LOCK_SECONDS = 20;
    private const SNAPSHOT_SUMMARY_TABLE = 'dashboard_simpanan_snapshots';
    private const SNAPSHOT_BRANCH_TABLE = 'dashboard_simpanan_branch_snapshots';

    public function index(): View
    {
        $dashboard = $this->buildDashboardPayload();

        return view('dashboard', [
            'dashboard' => $dashboard,
        ]);
    }

    private function buildDashboardPayload(): array
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return $this->emptyDashboard();
        }

        $latestPeriod = DB::table('simpanan_multipn')->max('posisi');

        if (!$latestPeriod) {
            return $this->emptyDashboard();
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

        $currentSummary = $this->buildPeriodSummary($currentPeriod);
        $previousSummary = $previousPeriod ? $this->buildPeriodSummary($previousPeriod) : $this->emptySummary();
        $yoySummary = $yoyPeriod ? $this->buildPeriodSummary($yoyPeriod) : $this->emptySummary();

        $topBranches = $this->fetchTopBranches($currentPeriod);
        $composition = $this->buildComposition($currentSummary);
        $latestUpdatedAt = $currentSummary['source_updated_at'] ?? null;
        $topBranchLabel = data_get($topBranches->first(), 'label', 'Cabang belum tersedia');
        $topBranchDisplay = data_get($topBranches->first(), 'display', '-');

        return [
            'period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'yoy_period' => $yoyPeriod,
            'hero' => [
                'title' => 'Dashboard Simpanan Area 6',
                'subtitle' => 'Ringkasan saldo, rekening, CIF, dan konsentrasi cabang sekarang ditarik langsung dari data simpanan terbaru.',
                'badge' => 'Area 6 Overview',
                'updated_label' => $this->formatPeriodLabel($currentPeriod),
                'stats' => [
                    [
                        'label' => 'Total Saldo',
                        'value' => $this->formatCurrencyCompact($currentSummary['total_balance']),
                    ],
                    [
                        'label' => 'Jumlah Rekening',
                        'value' => $this->formatInteger($currentSummary['account_count']),
                    ],
                    [
                        'label' => 'Total CIF',
                        'value' => $this->formatInteger($currentSummary['cif_count']),
                    ],
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
            'metrics' => [
                [
                    'label' => 'Total Cabang Aktif',
                    'value' => $this->formatInteger($currentSummary['branch_count']),
                    'delta' => $this->formatSignedPercent($this->percentChange($currentSummary['branch_count'], $previousSummary['branch_count'])),
                    'delta_class' => $this->deltaClass($this->percentChange($currentSummary['branch_count'], $previousSummary['branch_count'])),
                    'icon' => 'fas fa-building',
                    'icon_class' => 'text-primary',
                    'icon_bg' => 'rgba(13, 110, 253, 0.12)',
                ],
                [
                    'label' => 'Growth Saldo MoM',
                    'value' => $this->formatSignedPercent($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance'])),
                    'delta' => 'vs ' . ($previousPeriod ? $this->formatPeriodLabel($previousPeriod) : 'periode sebelumnya'),
                    'delta_class' => $this->deltaClass($this->percentChange($currentSummary['total_balance'], $previousSummary['total_balance'])),
                    'icon' => 'fas fa-chart-line',
                    'icon_class' => 'text-info',
                    'icon_bg' => 'rgba(23, 162, 184, 0.13)',
                ],
                [
                    'label' => 'Rata-rata Saldo / CIF',
                    'value' => $this->formatCurrencyCompact($currentSummary['avg_balance_per_cif']),
                    'delta' => $this->formatSignedPercent($this->percentChange($currentSummary['avg_balance_per_cif'], $previousSummary['avg_balance_per_cif'])),
                    'delta_class' => $this->deltaClass($this->percentChange($currentSummary['avg_balance_per_cif'], $previousSummary['avg_balance_per_cif'])),
                    'icon' => 'fas fa-wallet',
                    'icon_class' => 'text-warning',
                    'icon_bg' => 'rgba(255, 193, 7, 0.16)',
                ],
                [
                    'label' => 'Coverage YoY',
                    'value' => $this->formatSignedPercent($this->percentChange($currentSummary['total_balance'], $yoySummary['total_balance'])),
                    'delta' => 'dibanding ' . ($yoyPeriod ? $this->formatPeriodLabel($yoyPeriod) : 'tahun lalu'),
                    'delta_class' => $this->deltaClass($this->percentChange($currentSummary['total_balance'], $yoySummary['total_balance'])),
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
                    'title' => 'Pantau Pergerakan Saldo',
                    'text' => 'Total saldo posisi ' . $this->formatPeriodLabel($currentPeriod) . ' tercatat ' . $this->formatCurrencyFull($currentSummary['total_balance']) . '.',
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
                    'text' => $topBranchLabel . ' menjadi kontributor saldo terbesar periode ini.',
                ],
            ],
            'activities' => $this->buildActivities($currentSummary, $previousSummary, $composition, $currentPeriod, $topBranchLabel, $topBranchDisplay),
            'agenda' => [
                [
                    'title' => 'Review Posisi Simpanan',
                    'time' => $this->formatPeriodLabel($currentPeriod),
                    'tag' => 'Data',
                ],
                [
                    'title' => 'Bandingkan Growth MoM',
                    'time' => $previousPeriod ? $this->formatPeriodLabel($previousPeriod) : 'Belum ada pembanding',
                    'tag' => 'MoM',
                ],
                [
                    'title' => 'Pantau Top Branch',
                    'time' => $topBranchLabel,
                    'tag' => 'Area 6',
                ],
            ],
            'top_branches' => $topBranches->all(),
        ];
    }

    private function buildPeriodSummary(string $period): array
    {
        $cacheKey = 'dashboard_simpanan:summary:v' . $this->reportCacheVersion() . ':' . $period;
        $latestKey = $cacheKey . ':latest';
        $ttl = now()->addMinutes(self::SUMMARY_CACHE_MINUTES);
        $latestTtl = now()->addMinutes(self::SUMMARY_LATEST_CACHE_MINUTES);
        $lock = Cache::lock($cacheKey . ':lock', self::CACHE_LOCK_SECONDS);

        try {
            return $lock->block(5, function () use ($cacheKey, $latestKey, $ttl, $latestTtl, $period) {
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
            ->from(DB::raw('simpanan_multipn FORCE INDEX (idx_smp_posisi_cif_jenis)'))
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
                ->from(DB::raw('simpanan_multipn FORCE INDEX (idx_smp_posisi_status_cabang_unit)'))
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

    private function buildActivities(
        array $currentSummary,
        array $previousSummary,
        array $composition,
        string $period,
        string $topBranchLabel,
        string $topBranchDisplay
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
                'title' => 'Kontributor terbesar saat ini: ' . $topBranchLabel,
                'time' => $topBranchDisplay,
            ],
            [
                'class' => $composition['badge_class'],
                'title' => 'Saldo yang sudah terklasifikasi sebagai Giro/Tabungan',
                'time' => $this->formatPercent($composition['known_ratio']),
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
                'title' => 'Dashboard Simpanan Area 6',
                'subtitle' => 'Data simpanan belum tersedia untuk ditampilkan.',
                'badge' => 'Area 6 Overview',
                'updated_label' => 'Belum ada data',
                'stats' => [
                    ['label' => 'Total Saldo', 'value' => 'Rp0'],
                    ['label' => 'Jumlah Rekening', 'value' => '0'],
                    ['label' => 'Total CIF', 'value' => '0'],
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
                ['label' => 'Total Cabang Aktif', 'value' => '0', 'delta' => '-', 'delta_class' => 'text-muted', 'icon' => 'fas fa-building', 'icon_class' => 'text-primary', 'icon_bg' => 'rgba(13, 110, 253, 0.12)'],
                ['label' => 'Growth Saldo MoM', 'value' => '0,0%', 'delta' => '-', 'delta_class' => 'text-muted', 'icon' => 'fas fa-chart-line', 'icon_class' => 'text-info', 'icon_bg' => 'rgba(23, 162, 184, 0.13)'],
                ['label' => 'Rata-rata Saldo / CIF', 'value' => 'Rp0', 'delta' => '-', 'delta_class' => 'text-muted', 'icon' => 'fas fa-wallet', 'icon_class' => 'text-warning', 'icon_bg' => 'rgba(255, 193, 7, 0.16)'],
                ['label' => 'Coverage YoY', 'value' => '0,0%', 'delta' => '-', 'delta_class' => 'text-muted', 'icon' => 'fas fa-database', 'icon_class' => 'text-success', 'icon_bg' => 'rgba(40, 167, 69, 0.14)'],
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
        ];
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
        ];
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

        $cacheKey = 'dashboard_simpanan:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $period;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($period) {
            $exists = DB::table(self::SNAPSHOT_SUMMARY_TABLE)
                ->where('snapshot_period', $period)
                ->exists();

            if ($exists) {
                return true;
            }

            $hasSourceRows = DB::table('simpanan_multipn')
                ->where('posisi', $period)
                ->exists();

            if (!$hasSourceRows) {
                return false;
            }

            $lock = Cache::lock('snapshot:dashboard_simpanan:auto-rebuild:' . $period, 60);

            try {
                $lock->block(5, function () use ($period) {
                    app(ReportDataSyncService::class)->syncImportedTable(
                        'simpanan_multipn',
                        $period,
                        source: static::class . '::hasSimpananSnapshot'
                    );
                });
            } catch (LockTimeoutException) {
                return false;
            } catch (Throwable) {
                return false;
            } finally {
                optional($lock)->release();
            }

            return DB::table(self::SNAPSHOT_SUMMARY_TABLE)
                ->where('snapshot_period', $period)
                ->exists();
        });
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
        return (int) Cache::get('report_cache_version:global', 1);
    }
}
