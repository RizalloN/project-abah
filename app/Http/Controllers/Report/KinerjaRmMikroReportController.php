<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Jobs\SyncImportedReportJob;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KinerjaRmMikroReportController extends Controller
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';
    private const SNAPSHOT_TABLE = 'performance_rm_snapshots';
    private const TARGET_MONTHLY_JUTA = 4000.0;
    private const WEEKLY_TARGET_JUTA = 1000.0;
    private const KUR_RITEL_DESCRIPTION = 'Kredit Mikro - KUR Ritel 2015';

    private const REPORT_CATEGORIES = [
        'per_uker' => 'Per UKER',
        'per_rm' => 'Per RM',
        'series_bulanan' => 'Series Bulanan',
        'series_harian' => 'Series Harian',
        'rekap' => 'Rekap',
        'per_tiering' => 'Per Tiering',
    ];

    private const MANTRI_REPORT_CATEGORIES = [
        'unit_pemutus' => 'Unit per Pemutus',
        'kuadran' => 'Kuadran',
        'produktivitas_mantri' => 'Produktivitas per Mantri',
        'pdwk_override' => 'PDWK - Override',
        'rekap_mantri' => 'Rekap Mantri',
    ];

    private const RM_CATEGORIES = [
        'rm_mikro_kur' => 'RM Mikro KUR',
        'mantri' => 'Mantri',
    ];

    private const PIC_MBM = [
        'KC NGAWI' => 'Puguh Harianto (MBM)',
        'KC PONOROGO' => 'Dian Febriantari (MBM)',
        'KC MAGETAN' => 'Suprijono Edi (MBM)',
        'KC MADIUN' => 'Anggono Handoko Mukti (MBM)',
    ];

    private const BOH_BY_BRANCH = [
        'KC PONOROGO' => 'Agus Adi Hermanto',
        'KC MAGETAN' => 'Aditya Ivan Buana Putra',
        'KC MADIUN' => 'Rizky Akbar Trilaksono',
        'KC NGAWI' => 'Israhadi Aprihanto',
    ];

    private const AREA_BRANCH_ORDER = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
    private const MANTRI_PRODUCTS = ['BRIGUNAMIKRO', 'CASHCOLLATERAL', 'KUPEDES', 'KPR', 'KURMIKRO'];

    public function index(Request $request): View
    {
        $availablePeriods = $this->fetchAvailablePeriods();
        $selectedPeriod = $this->resolveSelectedPeriod($availablePeriods, $request->input('periode'));
        $this->queueDailyLoanSnapshotSyncIfNeeded($selectedPeriod, static::class . '::index');
        $selectedRmCategory = array_key_exists((string) $request->input('kategori_rm'), self::RM_CATEGORIES)
            ? (string) $request->input('kategori_rm')
            : 'rm_mikro_kur';
        $reportCategories = $selectedRmCategory === 'mantri' ? self::MANTRI_REPORT_CATEGORIES : self::REPORT_CATEGORIES;
        $selectedReportCategory = array_key_exists((string) $request->input('kategori_report'), $reportCategories)
            ? (string) $request->input('kategori_report')
            : array_key_first($reportCategories);

        $periodDate = $selectedPeriod ? Carbon::parse($selectedPeriod) : null;
        $payload = $selectedRmCategory === 'mantri'
            ? $this->buildMantriPayload($selectedReportCategory, $selectedPeriod)
            : $this->buildReportPayload($selectedReportCategory, $selectedPeriod);

        // Pre-calculate max values for gradients to offload from Blade
        $rows = collect($payload['rows'] ?? []);
        $payload['max_values'] = [
            'ratas_mantri_hk' => max(1, (float) $rows->max('ratas_mantri_hk')),
            'ratio' => 100,
            'tiket_size' => max(1, (float) $rows->max('tiket_size')),
        ];

        return view('report.dashboard-pinjaman.kinerjarmmikro', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $periodDate?->locale('id')->translatedFormat('d F y') ?? '-',
            'selectedPeriodShortLabel' => $periodDate?->locale('id')->translatedFormat('d M y') ?? '-',
            'selectedMonthLabel' => $periodDate?->locale('id')->translatedFormat('M y') ?? '-',
            'selectedRmCategory' => $selectedRmCategory,
            'selectedReportCategory' => $selectedReportCategory,
            'rmCategories' => self::RM_CATEGORIES,
            'reportCategories' => $reportCategories,
            'targetMonthlyJuta' => self::TARGET_MONTHLY_JUTA,
            'weeklyTargetJuta' => self::WEEKLY_TARGET_JUTA,
            'payload' => $payload,
            'formatAmount' => fn ($value, int $decimals = 0) => $this->formatAmount($value, $decimals),
            'formatJuta' => fn ($value, int $decimals = 0) => $this->formatJuta($value, $decimals),
            'formatPercent' => fn ($value, int $decimals = 1) => $this->formatPercent($value, $decimals),
            'achievementClass' => fn ($value, float $target) => $this->achievementClass((float) $value, $target),
            'gradientClass' => fn ($value, float $min, float $max, bool $higherIsBetter = true) => $this->gradientClass((float) $value, $min, $max, $higherIsBetter),
        ]);
    }

    private function buildReportPayload(string $category, ?string $period): array
    {
        if ($period === null) {
            return ['rows' => [], 'total' => [], 'months' => []];
        }

        return match ($category) {
            'per_rm' => $this->perRmPayload($period),
            'series_bulanan' => $this->seriesBulananPayload($period),
            'series_harian' => $this->seriesHarianPayload($period),
            'rekap' => $this->rekapPayload($period),
            'per_tiering' => $this->tieringPayload($period),
            default => $this->perUkerPayload($period),
        };
    }

    private function perUkerPayload(string $period): array
    {
        $rows = $this->snapshotAggregates($period, ['unit'])->values()
            ->map(function (array $row) {
            $cabang = (string) ($row['cabang'] ?? '');

            return array_merge($this->blankPositionMetrics(), [
                'bc' => (string) ($row['branch_code'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
                'cabang' => $cabang !== '' ? $cabang : '-',
                'pic_mbm' => self::PIC_MBM[$this->normalizeKey($cabang)] ?? '-',
            ], $row);
        })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['unit'])->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->sumPositionRows($rows),
        ];
    }

    private function perRmPayload(string $period): array
    {
        $rows = $this->snapshotAggregates($period, ['rm'])->values()
            ->map(function (array $row) {
            [$pn, $name] = $this->splitRm((string) ($row['rm'] ?? ''));

            return array_merge($this->blankPositionMetrics(), [
                'cabang' => (string) ($row['cabang'] ?? '-'),
                'pn' => $pn,
                'nama' => $name,
                'branch_code' => (string) ($row['branch_code'] ?? '-'),
                'unit' => (string) ($row['unit'] ?? '-'),
            ], $row);
        })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['nama'])->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->sumPositionRows($rows),
        ];
    }

    private function seriesBulananPayload(string $period): array
    {
        $periodDate = Carbon::parse($period);
        $monthPeriods = $this->fetchMonthEndPeriods($periodDate);
        $rowsByRm = collect();

        foreach ($monthPeriods as $monthKey => $monthPeriod) {
            foreach ($this->snapshotAggregates($monthPeriod, ['rm'])->values() as $row) {
                $rm = (string) $row['rm'];
                $existing = $rowsByRm->get($rm, [
                    'cabang' => $row['cabang'],
                    'rm' => $rm,
                    'branch_code' => $row['branch_code'],
                    'unit' => $row['unit'],
                    'months' => [],
                ]);
                $existing['months'][$monthKey] = [
                    'deb' => (int) $row['realisasi_deb'],
                    'os' => (float) $row['realisasi_os'],
                ];
                $rowsByRm->put($rm, $existing);
            }
        }

        $rows = $rowsByRm->map(function (array $row) use ($monthPeriods) {
            [$pn, $name] = $this->splitRm((string) $row['rm']);
            $totalDeb = 0;
            $totalOs = 0.0;

            foreach ($monthPeriods as $monthKey => $_) {
                $totalDeb += (int) ($row['months'][$monthKey]['deb'] ?? 0);
                $totalOs += (float) ($row['months'][$monthKey]['os'] ?? 0);
            }

            return [
                'cabang' => $row['cabang'],
                'pn' => $pn,
                'nama' => $name,
                'branch_code' => $row['branch_code'],
                'unit' => $row['unit'],
                'tmt_jabatan' => str_contains($this->normalizeKey($row['unit']), 'KCP') ? '01/03/2026' : '01/01/2026',
                'bulan_masuk' => str_contains($this->normalizeKey($row['unit']), 'KCP') ? 'Mar' : 'Jan',
                'months' => $row['months'],
                'total_deb' => $totalDeb,
                'total_os' => $totalOs,
            ];
        })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['nama'])->values();

        return [
            'rows' => $rows->all(),
            'months' => collect($monthPeriods)->map(fn ($date, $key) => [
                'key' => $key,
                'label' => Carbon::parse($date)->locale('id')->translatedFormat('M'),
            ])->values()->all(),
        ];
    }

    private function seriesHarianPayload(string $period): array
    {
        $rows = $this->snapshotAggregates($period, ['rm'])->values()->map(function (array $row) {
            [$pn, $name] = $this->splitRm((string) $row['rm']);
            $totalOs = array_sum(array_map(fn ($week) => (float) ($row[$week . '_realisasi_os'] ?? 0), ['w1', 'w2', 'w3', 'w4']));
            $totalDeb = array_sum(array_map(fn ($week) => (int) ($row[$week . '_realisasi_deb'] ?? 0), ['w1', 'w2', 'w3', 'w4']));

            return $row + [
                'pn' => $pn,
                'nama' => $name,
                'total_deb' => $totalDeb,
                'total_os' => $totalOs,
                'target_os' => self::TARGET_MONTHLY_JUTA * 1000000,
                'pct_target' => self::TARGET_MONTHLY_JUTA > 0 ? ($totalOs / 1000000 / self::TARGET_MONTHLY_JUTA) * 100 : 0,
            ];
        })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['nama'])->values();

        return ['rows' => $rows->all()];
    }

    private function rekapPayload(string $period): array
    {
        $perRm = collect($this->perRmPayload($period)['rows'] ?? []);
        $rows = $perRm->groupBy(fn ($row) => $this->normalizeKey($row['cabang']))
            ->map(function (Collection $items) {
                $first = $items->first();
                $totalRm = $items->count();
                $sudahReal = $items->where('realisasi_os', '>', 0)->count();
                $realisasiOs = (float) $items->sum('realisasi_os');
                $target = $totalRm * self::TARGET_MONTHLY_JUTA * 1000000;

                return [
                    'bc' => $first['branch_code'] ?? '-',
                    'cabang' => $first['cabang'] ?? '-',
                    'pembina' => self::PIC_MBM[$this->normalizeKey($first['cabang'] ?? '')] ?? '-',
                    'total_rm' => $totalRm,
                    'sudah_real' => $sudahReal,
                    'belum_real' => max(0, $totalRm - $sudahReal),
                    'realisasi_deb' => (int) $items->sum('realisasi_deb'),
                    'realisasi_os' => $realisasiOs,
                    'target_os' => $target,
                    'pct_target' => $target > 0 ? ($realisasiOs / $target) * 100 : 0,
                ];
            })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']))->values();

        return [
            'rows' => $rows->all(),
            'total' => [
                'total_rm' => $rows->sum('total_rm'),
                'sudah_real' => $rows->sum('sudah_real'),
                'belum_real' => $rows->sum('belum_real'),
                'realisasi_deb' => $rows->sum('realisasi_deb'),
                'realisasi_os' => $rows->sum('realisasi_os'),
                'target_os' => $rows->sum('target_os'),
            ],
        ];
    }

    private function tieringPayload(string $period): array
    {
        $rows = $this->snapshotAggregates($period, ['rm'])->values()->map(function (array $row) {
            [$pn, $name] = $this->splitRm((string) $row['rm']);
            $totalOs = (float) $row['lt_250_realisasi_os'] + (float) $row['gt_250_realisasi_os'];

            return $row + [
                'pn' => $pn,
                'nama' => $name,
                'ket' => $totalOs > 0 ? 'Sudah Real' : 'Belum Real',
                'lt_250_pct' => $totalOs > 0 ? ((float) $row['lt_250_realisasi_os'] / $totalOs) * 100 : 0,
                'gt_250_pct' => $totalOs > 0 ? ((float) $row['gt_250_realisasi_os'] / $totalOs) * 100 : 0,
                'total_deb' => (int) $row['lt_250_realisasi_deb'] + (int) $row['gt_250_realisasi_deb'],
                'total_os' => $totalOs,
            ];
        })->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['nama'])->values();

        return ['rows' => $rows->all()];
    }

    private function snapshotAggregates(string $period, array $group): Collection
    {
        return Cache::remember($this->cacheKey('snapshot', $period, $group), 300, function () use ($period, $group) {
            $query = DB::table(self::SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->where('segmen', 'MICRO')
                ->where('produk', 'KUR-MIKRO')
                ->selectRaw('cabang')
                ->selectRaw('unit')
                ->selectRaw($this->snapshotSelectColumn('branch_code', "''") . ' as branch_code');

            if (in_array('rm', $group, true)) {
                $query->selectRaw('rm');
            }

            return $query
                ->selectRaw($this->sumSnapshotColumn('lancar_deb'))
                ->selectRaw('SUM(COALESCE(lancar_os, 0)) as lancar_os')
                ->selectRaw($this->sumSnapshotColumn('sml_deb'))
                ->selectRaw('SUM(COALESCE(sml_os, 0)) as sml_os')
                ->selectRaw($this->sumSnapshotColumn('npl_deb'))
                ->selectRaw('SUM(COALESCE(npl_os, 0)) as npl_os')
                ->selectRaw('SUM(COALESCE(total_deb, 0)) as total_deb')
                ->selectRaw('SUM(COALESCE(loan_os, 0)) as total_os')
                ->selectRaw('SUM(COALESCE(realisasi_deb, 0)) as realisasi_deb')
                ->selectRaw('SUM(COALESCE(realisasi_os, 0)) as realisasi_os')
                ->selectRaw($this->sumSnapshotColumn('w1_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('w1_realisasi_os'))
                ->selectRaw($this->sumSnapshotColumn('w2_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('w2_realisasi_os'))
                ->selectRaw($this->sumSnapshotColumn('w3_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('w3_realisasi_os'))
                ->selectRaw($this->sumSnapshotColumn('w4_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('w4_realisasi_os'))
                ->selectRaw($this->sumSnapshotColumn('lt_250_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('lt_250_realisasi_os'))
                ->selectRaw($this->sumSnapshotColumn('gt_250_realisasi_deb'))
                ->selectRaw($this->sumSnapshotColumn('gt_250_realisasi_os'))
                ->groupBy(...$this->snapshotGroupExpressions($group))
                ->get()
                ->mapWithKeys(fn ($row) => [$this->rowKey((array) $row, $group) => (array) $row]);
        });
    }

    private function fetchAvailablePeriods(): Collection
    {
        return Cache::remember('kinerja_rm_mikro_periods_v1:' . $this->reportCacheVersion(), 600, function () {
            return $this->fetchPeriodList(self::SNAPSHOT_TABLE, 'periode', function ($query): void {
                    $query->where('segmen', 'MICRO')->where('produk', 'KUR-MIKRO');
                })
                ->merge($this->fetchPeriodList(self::SOURCE_TABLE, 'periode', function ($query): void {
                    $query->where('segmen_kinerja', 'MICRO')->where('produk_kinerja', 'KURMIKRO');
                }))
                ->unique()
                ->sortDesc()
                ->values();
        });
    }

    private function fetchPeriodList(string $table, string $column, ?callable $scope = null): Collection
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return collect();
        }

        $query = DB::table($table)
            ->whereNotNull($column)
            ->select($column)
            ->distinct()
            ->orderByDesc($column);

        if ($scope !== null) {
            $scope($query);
        }

        return $query
            ->pluck($column)
            ->map(function ($value) {
                try {
                    return Carbon::parse($value)->toDateString();
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values();
    }

    private function fetchMonthEndPeriods(Carbon $selectedPeriod): Collection
    {
        return $this->fetchAvailablePeriods()
            ->filter(fn ($period) => Carbon::parse($period)->year === $selectedPeriod->year && $period <= $selectedPeriod->toDateString())
            ->groupBy(fn ($period) => Carbon::parse($period)->format('Y-m'))
            ->map(fn (Collection $periods) => $periods->sortDesc()->first())
            ->sortKeys();
    }

    private function resolveSelectedPeriod(Collection $periods, mixed $requested): ?string
    {
        try {
            $requestedDate = $requested ? Carbon::parse((string) $requested)->toDateString() : null;
        } catch (\Throwable) {
            $requestedDate = null;
        }

        if ($requestedDate !== null) {
            $match = $periods->first(fn ($period) => $period <= $requestedDate);
            if ($match !== null) {
                return $match;
            }
        }

        return $periods->first();
    }

    private function snapshotGroupExpressions(array $group): array
    {
        $expressions = [
            'cabang',
            'unit',
            DB::raw($this->snapshotSelectColumn('branch_code', "''")),
        ];

        if (in_array('rm', $group, true)) {
            $expressions[] = 'rm';
        }

        return $expressions;
    }

    private function rowKey(array $row, array $group): string
    {
        $parts = [$row['cabang'] ?? '', $row['unit'] ?? '', $row['branch_code'] ?? ''];

        if (in_array('rm', $group, true)) {
            $parts[] = $row['rm'] ?? '';
        }

        return implode('|', array_map(fn ($value) => $this->normalizeKey((string) $value), $parts));
    }

    private function blankPositionMetrics(): array
    {
        return [
            'lancar_deb' => 0,
            'lancar_os' => 0.0,
            'sml_deb' => 0,
            'sml_os' => 0.0,
            'npl_deb' => 0,
            'npl_os' => 0.0,
            'total_deb' => 0,
            'total_os' => 0.0,
        ];
    }

    private function sumPositionRows(Collection $rows): array
    {
        return [
            'lancar_deb' => $rows->sum('lancar_deb'),
            'lancar_os' => $rows->sum('lancar_os'),
            'sml_deb' => $rows->sum('sml_deb'),
            'sml_os' => $rows->sum('sml_os'),
            'npl_deb' => $rows->sum('npl_deb'),
            'npl_os' => $rows->sum('npl_os'),
            'total_deb' => $rows->sum('total_deb'),
            'total_os' => $rows->sum('total_os'),
            'realisasi_deb' => $rows->sum('realisasi_deb'),
            'realisasi_os' => $rows->sum('realisasi_os'),
        ];
    }

    private function splitRm(string $rm): array
    {
        $parts = array_map('trim', explode('-', $rm, 2));

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['-', $rm !== '' ? $rm : '-'];
    }

    private function buildMantriPayload(string $category, ?string $period): array
    {
        if ($period === null) {
            return ['rows' => [], 'total' => [], 'working_days' => 0];
        }

        return Cache::remember('kinerja_rm_mikro_mantri_v2:' . $this->reportCacheVersion() . ':' . $period . ':' . $category, 600, function () use ($category, $period): array {
            return match ($category) {
                'kuadran' => $this->mantriKuadranPayload($period),
                'produktivitas_mantri' => $this->mantriProductivityPayload($period),
                'pdwk_override' => $this->mantriPdwkOverridePayload($period),
                'rekap_mantri' => $this->mantriRekapPayload($period),
                default => $this->mantriUnitPemutusPayload($period),
            };
        });
    }

    private function mantriUnitPemutusPayload(string $period): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $roles = ['kaunit', 'mbm', 'pinca', 'rmbh'];
        $query = DB::query()
            ->fromSub($this->mantriSourceQuery($period), 'x')
            ->selectRaw('bc, unit, cabang');

        foreach ($roles as $role) {
            $level = strtoupper($role);
            $query
                ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi = ? AND actual_level = ? THEN rekening END) as {$role}_period_deb", [$period, $level])
                ->selectRaw("SUM(CASE WHEN tgl_realisasi = ? AND actual_level = ? THEN COALESCE(plafon, 0) ELSE 0 END) as {$role}_period_os", [$period, $level])
                ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? THEN rekening END) as {$role}_mtd_deb", [$periodStart, $period, $level])
                ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? THEN COALESCE(plafon, 0) ELSE 0 END) as {$role}_mtd_os", [$periodStart, $period, $level]);
        }

        $rows = $query
            ->groupBy('bc', 'unit', 'cabang')
            ->get()
            ->map(fn ($row) => $this->decorateMantriUnitRow((array) $row))
            ->map(function (array $row): array {
                $row['period_total_deb'] = (int) $row['kaunit_period_deb'] + (int) $row['mbm_period_deb'] + (int) $row['pinca_period_deb'] + (int) $row['rmbh_period_deb'];
                $row['period_total_os'] = (float) $row['kaunit_period_os'] + (float) $row['mbm_period_os'] + (float) $row['pinca_period_os'] + (float) $row['rmbh_period_os'];
                $row['mtd_total_deb'] = (int) $row['kaunit_mtd_deb'] + (int) $row['mbm_mtd_deb'] + (int) $row['pinca_mtd_deb'] + (int) $row['rmbh_mtd_deb'];
                $row['mtd_total_os'] = (float) $row['kaunit_mtd_os'] + (float) $row['mbm_mtd_os'] + (float) $row['pinca_mtd_os'] + (float) $row['rmbh_mtd_os'];

                return $row;
            })
            ->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['unit'])
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->sumMantriRows($rows, [
                'kaunit_period_deb', 'kaunit_period_os', 'mbm_period_deb', 'mbm_period_os', 'pinca_period_deb', 'pinca_period_os', 'rmbh_period_deb', 'rmbh_period_os', 'period_total_deb', 'period_total_os',
                'kaunit_mtd_deb', 'kaunit_mtd_os', 'mbm_mtd_deb', 'mbm_mtd_os', 'pinca_mtd_deb', 'pinca_mtd_os', 'rmbh_mtd_deb', 'rmbh_mtd_os', 'mtd_total_deb', 'mtd_total_os',
            ]),
            'working_days' => $this->networkDays($periodStart, $period),
            'message' => 'Data Mantri dari daily_loan_dinamis segmen Micro untuk Briguna-Mikro, Cash Collateral, Kupedes, KPR, dan KUR-Mikro.',
        ];
    }

    private function mantriKuadranPayload(string $period): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $workingDays = $this->networkDays($periodStart, $period);
        $rows = DB::query()
            ->fromSub($this->mantriSourceQuery($period), 'x')
            ->selectRaw('bc, unit, cabang')
            ->selectRaw("COUNT(DISTINCT CASE WHEN pn_pengelola <> '' THEN pn_pengelola END) as jumlah_mantri")
            ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN rekening END) as realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os", [$periodStart, $period])
            ->groupBy('bc', 'unit', 'cabang')
            ->get()
            ->map(function ($row) use ($workingDays): array {
                $data = $this->decorateMantriUnitRow((array) $row);
                $realisasiJuta = ((float) $data['realisasi_os']) / 1000000;
                $deb = (int) $data['realisasi_deb'];
                $mantri = max(1, (int) $data['jumlah_mantri']);
                $data['tiket_size'] = $deb > 0 ? $realisasiJuta / $deb : 0;
                $data['ratas_mantri_hk'] = $workingDays > 0 ? ($realisasiJuta / $mantri) / $workingDays : 0;
                $data['ket'] = $this->kuadranLabel($data['ratas_mantri_hk'], $data['tiket_size']);

                return $data;
            })
            ->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['unit'])
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => [
                'jumlah_mantri' => $rows->sum('jumlah_mantri'),
                'realisasi_deb' => $rows->sum('realisasi_deb'),
                'realisasi_os' => $rows->sum('realisasi_os'),
            ],
            'working_days' => $workingDays,
            'message' => 'Kuadran dihitung dari realisasi bulan berjalan, tiket size, dan ratas Mantri per hari kerja.',
        ];
    }

    private function mantriProductivityPayload(string $period): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $workingDays = $this->networkDays($periodStart, $period);
        $rows = DB::query()
            ->fromSub($this->mantriSourceQuery($period), 'x')
            ->selectRaw('bc, unit, cabang, pn_pengelola')
            ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN rekening END) as realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as realisasi_os", [$periodStart, $period])
            ->where('pn_pengelola', '<>', '')
            ->groupBy('bc', 'unit', 'cabang', 'pn_pengelola')
            ->get()
            ->map(function ($row) use ($workingDays): array {
                $data = $this->decorateMantriUnitRow((array) $row);
                $realisasiJuta = ((float) $data['realisasi_os']) / 1000000;
                $deb = (int) $data['realisasi_deb'];
                $data['jumlah_mantri'] = 1;
                $data['nama_mantri'] = (string) ($data['pn_pengelola'] ?? '-');
                $data['tiket_size'] = $deb > 0 ? $realisasiJuta / $deb : 0;
                $data['ratas_mantri_hk'] = $workingDays > 0 ? $realisasiJuta / $workingDays : 0;
                $data['ket'] = $this->kuadranLabel($data['ratas_mantri_hk'], $data['tiket_size']);

                return $data;
            })
            ->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['unit'] . '|' . $row['nama_mantri'])
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => [
                'jumlah_mantri' => $rows->sum('jumlah_mantri'),
                'realisasi_deb' => $rows->sum('realisasi_deb'),
                'realisasi_os' => $rows->sum('realisasi_os'),
            ],
            'working_days' => $workingDays,
            'message' => 'Produktivitas per Mantri dihitung dari realisasi bulan berjalan per pn_pengelola1.',
        ];
    }

    private function mantriPdwkOverridePayload(string $period): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $rows = $this->mantriPdwkAggregateQuery($period, ['bc', 'unit', 'cabang'], $periodStart)
            ->get()
            ->map(fn ($row) => $this->decorateMantriUnitRow((array) $row))
            ->sortBy(fn ($row) => $this->branchSortKey($row['cabang']) . '|' . $row['unit'])
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->sumMantriRows($rows, $this->pdwkMetricColumns()),
            'working_days' => $this->networkDays($periodStart, $period),
            'message' => 'PDWK dibandingkan antara jabatan pemutus aktual dari BRIHC dan limit plafon keputusan.',
        ];
    }

    private function mantriRekapPayload(string $period): array
    {
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $workingDays = $this->networkDays($periodStart, $period);
        $rows = $this->mantriPdwkAggregateQuery($period, ['cabang'], $periodStart)
            ->selectRaw("COUNT(DISTINCT bc) as jumlah_unit")
            ->selectRaw("COUNT(DISTINCT CASE WHEN pn_pengelola <> '' THEN pn_pengelola END) as jumlah_mantri")
            ->get()
            ->map(function ($row) use ($workingDays): array {
                $data = (array) $row;
                $totalOs = (float) ($data['total_realisasi_os'] ?? 0);
                $totalDeb = (int) ($data['total_realisasi_deb'] ?? 0);

                foreach (['kaunit_pdwk', 'mbm_total', 'pinca_total', 'rmbh_override'] as $prefix) {
                    $data[$prefix . '_ratio'] = $totalOs > 0 ? ((float) ($data[$prefix . '_os'] ?? 0) / $totalOs) * 100 : 0;
                }

                $data['bc'] = '-';
                $data['boh'] = self::BOH_BY_BRANCH[$this->normalizeKey($data['cabang'] ?? '')] ?? '-';
                $data['tiket_size'] = $totalDeb > 0 ? ($totalOs / 1000000) / $totalDeb : 0;
                $mantri = max(1, (int) ($data['jumlah_mantri'] ?? 0));
                $data['ratas_mantri_hk'] = $workingDays > 0 ? (($totalOs / 1000000) / $mantri) / $workingDays : 0;

                return $data;
            })
            ->sortBy(fn ($row) => $this->branchSortKey($row['cabang']))
            ->values();

        return [
            'rows' => $rows->all(),
            'total' => $this->sumMantriRows($rows, array_merge($this->pdwkMetricColumns(), ['jumlah_unit', 'jumlah_mantri'])),
            'working_days' => $workingDays,
            'message' => 'Rekap Mantri dikonsolidasikan per cabang dari realisasi bulan berjalan.',
        ];
    }

    private function mantriPdwkAggregateQuery(string $period, array $groupColumns, string $periodStart)
    {
        $query = DB::query()->fromSub($this->mantriSourceQuery($period), 'x');

        foreach ($groupColumns as $column) {
            $query->selectRaw($column);
        }

        foreach (['KAUNIT', 'MBM', 'PINCA'] as $role) {
            $prefix = strtolower($role);
            foreach (['pdwk' => true, 'override' => false] as $status => $isPdwk) {
                $operator = $isPdwk ? '=' : '<>';
                $query
                    ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? AND actual_level {$operator} expected_level THEN rekening END) as {$prefix}_{$status}_deb", [$periodStart, $period, $role])
                    ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? AND actual_level {$operator} expected_level THEN COALESCE(plafon, 0) ELSE 0 END) as {$prefix}_{$status}_os", [$periodStart, $period, $role]);
            }

            $query
                ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? THEN rekening END) as {$prefix}_total_deb", [$periodStart, $period, $role])
                ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = ? THEN COALESCE(plafon, 0) ELSE 0 END) as {$prefix}_total_os", [$periodStart, $period, $role]);
        }

        $query
            ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = 'RMBH' THEN rekening END) as rmbh_override_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? AND actual_level = 'RMBH' THEN COALESCE(plafon, 0) ELSE 0 END) as rmbh_override_os", [$periodStart, $period])
            ->selectRaw("COUNT(DISTINCT CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN rekening END) as total_realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN tgl_realisasi BETWEEN ? AND ? THEN COALESCE(plafon, 0) ELSE 0 END) as total_realisasi_os", [$periodStart, $period])
            ->groupBy(...$groupColumns);

        return $query;
    }

    private function mantriSourceQuery(string $period)
    {
        // Optimization: Use already cleaned/normalized columns if they exist
        // and avoid complex string manipulations in JOIN conditions
        $pnPemutusSql = "NULLIF(d.pn_pemutus_normalized, '')";
        $jabatanSql = "UPPER(TRIM(COALESCE(b.jabatan, '')))";
        $expectedSql = "CASE WHEN COALESCE(d.plafon, 0) <= 100000000 THEN 'KAUNIT' WHEN COALESCE(d.plafon, 0) <= 250000000 THEN 'MBM' ELSE 'PINCA' END";
        $actualSql = "CASE WHEN {$jabatanSql} LIKE '%RMBH%' THEN 'RMBH' WHEN {$jabatanSql} LIKE '%PINCA%' OR {$jabatanSql} LIKE '%PIMPINAN CABANG%' THEN 'PINCA' WHEN {$jabatanSql} LIKE '%MBM%' THEN 'MBM' WHEN {$jabatanSql} LIKE '%KAUNIT%' OR {$jabatanSql} LIKE '%KEPALA UNIT%' THEN 'KAUNIT' ELSE {$expectedSql} END";

        // Fallback for pnPemutus if normalized column doesn't exist
        if (!Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')) {
            $pnPemutusSql = "NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE(d.pn_pemutus1, ''), '-', 1))), '')";
        }

        return DB::table('daily_loan_dinamis as d')
            ->leftJoin('brihc as b', function ($join) use ($pnPemutusSql): void {
                $join->on('b.pn', '=', DB::raw($pnPemutusSql));
            })
            ->where('d.periode', $period)
            ->where('d.segmen_kinerja', 'MICRO')
            ->whereIn('d.produk_kinerja', self::MANTRI_PRODUCTS)
            ->whereRaw(
                "NOT (d.produk_kinerja = ? AND {$this->normalizedSql('d.description')} = ?)",
                ['KURMIKRO', $this->normalizeToken(self::KUR_RITEL_DESCRIPTION)]
            )
            ->selectRaw("COALESCE(NULLIF(d.branch_normalized, ''), UPPER(TRIM(COALESCE(d.branch1, '')))) as bc")
            ->selectRaw("COALESCE(NULLIF(d.unit_normalized, ''), UPPER(TRIM(COALESCE(d.unit1, '')))) as unit")
            ->selectRaw("COALESCE(NULLIF(d.cabang_normalized, ''), UPPER(TRIM(COALESCE(d.cabang1, '')))) as cabang")
            ->selectRaw("COALESCE(NULLIF(d.rm_normalized, ''), UPPER(TRIM(COALESCE(d.pn_pengelola1, '')))) as pn_pengelola")
            ->selectRaw("COALESCE(NULLIF(d.nomor_rekening1, ''), CONCAT(COALESCE(d.branch1, ''), '-', COALESCE(d.pn_pengelola1, ''), '-', COALESCE(d.plafon, ''), '-', COALESCE(d.tgl_realisasi, ''))) as rekening")
            ->selectRaw('d.plafon, d.baki_debet1, d.kol_adk1, d.tgl_realisasi')
            ->selectRaw("{$actualSql} as actual_level")
            ->selectRaw("{$expectedSql} as expected_level");
    }

    private function decorateMantriUnitRow(array $row): array
    {
        $wilayah = $this->wilayahMbmMap()->get($this->normalizeBc($row['bc'] ?? ''));

        return array_merge([
            'bc' => (string) ($row['bc'] ?? '-'),
            'unit' => (string) ($wilayah['nama_uker'] ?? $row['unit'] ?? '-'),
            'cabang' => (string) ($wilayah['cabang'] ?? $row['cabang'] ?? '-'),
            'mbm_name' => (string) ($wilayah['nama_mbm'] ?? '-'),
        ], $row);
    }

    private function wilayahMbmMap(): Collection
    {
        return Cache::remember('kinerja_rm_mikro_wilayah_mbm_v1:' . $this->reportCacheVersion(), 600, function () {
            if (!Schema::hasTable('wilayah_mbm')) {
                return collect();
            }

            return DB::table('wilayah_mbm')
                ->select('bc', 'nama_uker', 'cabang', 'nama_mbm')
                ->get()
                ->mapWithKeys(fn ($row) => [$this->normalizeBc($row->bc ?? '') => (array) $row]);
        });
    }

    private function pdwkMetricColumns(): array
    {
        return [
            'kaunit_pdwk_deb', 'kaunit_pdwk_os', 'kaunit_override_deb', 'kaunit_override_os', 'kaunit_total_deb', 'kaunit_total_os',
            'mbm_pdwk_deb', 'mbm_pdwk_os', 'mbm_override_deb', 'mbm_override_os', 'mbm_total_deb', 'mbm_total_os',
            'pinca_pdwk_deb', 'pinca_pdwk_os', 'pinca_override_deb', 'pinca_override_os', 'pinca_total_deb', 'pinca_total_os',
            'rmbh_override_deb', 'rmbh_override_os', 'total_realisasi_deb', 'total_realisasi_os',
        ];
    }

    private function sumMantriRows(Collection $rows, array $columns): array
    {
        $total = [];
        foreach ($columns as $column) {
            $total[$column] = $rows->sum($column);
        }

        return $total;
    }

    private function kuadranLabel(float $ratasMantriHk, float $tiketSize): string
    {
        return match (true) {
            $ratasMantriHk >= 75 && $tiketSize >= 50 => 'KUADRAN 1',
            $ratasMantriHk >= 75 && $tiketSize < 50 => 'KUADRAN 2',
            $ratasMantriHk < 75 && $tiketSize >= 50 => 'KUADRAN 3',
            default => 'KUADRAN 4',
        };
    }

    private function networkDays(string $startDate, string $endDate): int
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $holidays = [];
        $days = 0;

        while ($start->lte($end)) {
            if ($start->isWeekday() && !in_array($start->toDateString(), $holidays, true)) {
                $days++;
            }

            $start->addDay();
        }

        return $days;
    }

    private function sumSnapshotColumn(string $column): string
    {
        $expression = Schema::hasColumn(self::SNAPSHOT_TABLE, $column)
            ? "COALESCE({$column}, 0)"
            : '0';

        return "SUM({$expression}) as {$column}";
    }

    private function snapshotSelectColumn(string $column, string $fallback): string
    {
        return Schema::hasColumn(self::SNAPSHOT_TABLE, $column) ? $column : $fallback;
    }

    private function normalizeKey(?string $value): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $value))) ?? '';
    }

    private function normalizeBc(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return ltrim($digits, '0') ?: $digits;
    }

    private function normalizedSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
    }

    private function normalizeToken(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';
    }

    private function branchSortKey(string $branch): string
    {
        $key = $this->normalizeKey($branch);
        $pos = array_search($key, self::AREA_BRANCH_ORDER, true);

        return str_pad((string) ($pos === false ? 99 : $pos), 2, '0', STR_PAD_LEFT) . '|' . $key;
    }

    private function achievementClass(float $value, float $target): string
    {
        if ($target <= 0) {
            return 'heat-muted';
        }

        $pct = ($value / $target) * 100;

        return match (true) {
            $pct >= 100 => 'heat-green',
            $pct >= 75 => 'heat-lime',
            $pct >= 50 => 'heat-yellow',
            $pct >= 25 => 'heat-orange',
            default => 'heat-red',
        };
    }

    private function gradientClass(float $value, float $min, float $max, bool $higherIsBetter = true): string
    {
        if ($max <= $min) {
            return 'heat-muted';
        }

        $pct = max(0, min(100, (($value - $min) / ($max - $min)) * 100));
        if (!$higherIsBetter) {
            $pct = 100 - $pct;
        }

        return match (true) {
            $pct >= 80 => 'heat-green',
            $pct >= 60 => 'heat-lime',
            $pct >= 40 => 'heat-yellow',
            $pct >= 20 => 'heat-orange',
            default => 'heat-red',
        };
    }

    private function formatAmount(mixed $value, int $decimals = 0): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals, ',', '.') : '-';
    }

    private function formatJuta(mixed $value, int $decimals = 0): string
    {
        return is_numeric($value) ? number_format((float) $value / 1000000, $decimals, ',', '.') : '-';
    }

    private function formatPercent(mixed $value, int $decimals = 1): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals, ',', '.') . '%' : '-';
    }

    private function cacheKey(string $prefix, string $period, array $group): string
    {
        return 'kinerja_rm_mikro_' . $prefix . '_v1:' . $this->reportCacheVersion() . ':' . $period . ':' . implode(',', $group);
    }

    private function queueDailyLoanSnapshotSyncIfNeeded(?string $period, string $source): void
    {
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
            || ($lastUpdated !== null && $this->dailyLoanSourceUpdatedAfter($period, $lastUpdated));

        if (!$needsSync) {
            return;
        }

        $pendingKey = 'snapshot:daily_loan:auto-sync:view:kinerja_rm_mikro:' . $period;
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

    private function reportCacheVersion(): int
    {
        return ReportCacheVersion::composite(['pinjaman', 'simpanan']);
    }
}
