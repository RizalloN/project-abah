<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

require_once __DIR__ . '/SnapshotQueryOptimizer.php';

class ReportSnapshotBuilder
{
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const CHART_PERIODIK_SNAPSHOT_TABLE = 'dashboard_pinjaman_chart_periodik_snapshots';
    private const DASHBOARD_SIMPANAN_SNAPSHOT_TABLE = 'dashboard_simpanan_snapshots';
    private const DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const RASIO_UKER_SNAPSHOT_TABLE = 'rasio_casa_debitur_uker_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const DORMANT_SNAPSHOT_VERSION = 2;
    private const NEW_PAYROLL_SNAPSHOT_TABLE = 'performance_new_payroll_snapshots';
    private const PERFORMANCE_RM_SNAPSHOT_TABLE = 'performance_rm_snapshots';
    private const PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE = 'performance_rm_cabang_snapshots';

    private const PRIORITY_BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
    private const SEGMENTS = ['total', 'briguna', 'kpr', 'mikro', 'smc'];
    private const NEW_PAYROLL_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const AREA_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    private const KINERJA_RM_SEGMENT_RULES = [
        'CONSUMER' => [
            ['source_segment' => 'CONSUMER', 'products' => ['BRIGUNA-KONSUMER', 'KPR']],
        ],
        'SMALL' => [
            ['source_segment' => 'SMALL', 'products' => ['COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL']],
        ],
        'MICRO' => [
            ['source_segment' => 'MICRO', 'products' => ['BRIGUNA-MIKRO', 'KUPEDES', 'CASHCOLLATERAL', 'KPR']],
            ['source_segment' => 'MICRO', 'products' => ['KUR-MIKRO', 'KUR-KECIL'], 'descriptions' => ['Kredit Mikro - KUR Ritel 2015']],
        ],
    ];

    private const BRANCH_PATTERNS = [
        'KC Madiun' => 'KC MADIUN',
        'KC Magetan' => 'KC MAGETAN',
        'KC Ngawi' => 'KC NGAWI',
        'KC Ponorogo' => 'KC PONOROGO',
    ];

    /** @var array<string, array<int, string>> */
    private array $columnListingCache = [];

    /** @var array<string, string|null> */
    private array $availablePeriodCache = [];

    /** @var array<string, string|null> */
    private array $availableCasaPeriodCache = [];

    /** @var array<string, bool> */
    private array $casaTypeFilterCache = [];

    /** @var array<string, string> */
    private array $dormantBranchFilterExpressionCache = [];

    private ?string $rasioCasaTempTablePeriod = null;
    private ?bool $rasioCasaTempTableTypeFilter = null;

    private readonly SnapshotQueryOptimizer $queryOptimizer;

    public function __construct(
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService
    ) {
        $this->queryOptimizer = new SnapshotQueryOptimizer();
    }

    public function rebuild(string $report = 'all', ?string $period = null, bool $force = false): array
    {
        $report = strtolower(trim($report));
        $period = $this->normalizePeriodInput($period);

        return match ($report) {
            'dashboard', 'dashboard-pinjaman', 'pinjaman' => [
                'dashboard' => $this->rebuildDashboard($period, $force),
            ],
            'dashboard-pinjaman-chart-periodik', 'chart-periodik', 'chart_periodik' => [
                'chart_periodik' => $this->rebuildChartPeriodik($period, $force),
            ],
            'dashboard-simpanan', 'simpanan-dashboard', 'simpanan' => [
                'dashboard_simpanan' => $this->rebuildDashboardSimpanan($period, $force),
            ],
            'simpanan_multipn' => $this->rebuildSimpananMultipnSnapshots($period, $force),
            'rasio', 'rasio-casa', 'rasio-casa-debitur' => [
                'rasio' => $this->rebuildRasioCasa($period, $force),
            ],
            'dormant', 'rekening-dormant' => [
                'dormant' => $this->rebuildRekeningDormant($period, $force),
            ],
            'new-payroll', 'performance-new-payroll', 'payroll' => [
                'new_payroll' => $this->rebuildPerformanceNewPayroll($period, $force),
            ],
            'kinerja-rm', 'rm-performance', 'rm' => [
                'kinerja_rm' => $this->rebuildPerformanceRm($period, $force),
            ],
            'dashboard-harian', 'harian' => [
                'dashboard_harian' => $this->dashboardHarianSnapshotService->rebuild($period, $force),
            ],
            default => [
                'dashboard' => $this->rebuildDashboard($period, $force),
                'chart_periodik' => $this->rebuildChartPeriodik($period, $force),
                'dashboard_simpanan' => $this->rebuildDashboardSimpanan($period, $force),
                'dashboard_harian' => $this->dashboardHarianSnapshotService->rebuild($period, $force),
                'rasio' => $this->rebuildRasioCasa($period, $force),
                'dormant' => $this->rebuildRekeningDormant($period, $force),
                'new_payroll' => $this->rebuildPerformanceNewPayroll($period, $force),
                'kinerja_rm' => $this->rebuildPerformanceRm($period, $force),
            ],
        };
    }

    /**
     * Rebuild snapshots that depend on Simpanan MultiPN.
     */
    private function rebuildSimpananMultipnSnapshots(?string $period = null, bool $force = false): array
    {
        return [
            'dashboard_simpanan' => $this->rebuildDashboardSimpanan($period, $force),
            'dashboard_harian' => $this->dashboardHarianSnapshotService->rebuild($period, $force),
            'dormant' => $this->rebuildRekeningDormant($period, $force),
            'rasio' => $this->rebuildRasioCasa($period, $force),
        ];
    }

    public function describeRebuildPlan(?string $period = null): array
    {
        $reports = [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard Pinjaman',
                'periods' => $this->resolveDashboardPeriods($period),
            ],
            [
                'key' => 'chart_periodik',
                'label' => 'Chart Periodik',
                'periods' => $this->resolveChartPeriodikPeriods($period),
            ],
            [
                'key' => 'dashboard_simpanan',
                'label' => 'Dashboard Simpanan',
                'periods' => $this->resolveSimpananDashboardPeriods($period),
            ],
            [
                'key' => 'dashboard_harian',
                'label' => 'Dashboard Harian',
                'periods' => $this->dashboardHarianSnapshotService->describeRebuildPlan($period)['periods'] ?? [],
            ],
            [
                'key' => 'rasio',
                'label' => 'Rasio CASA Debitur',
                'periods' => $this->resolveRasioPeriods($period),
            ],
            [
                'key' => 'dormant',
                'label' => 'Rekening Dormant',
                'periods' => $this->resolveDormantPeriods($period),
            ],
            [
                'key' => 'new_payroll',
                'label' => 'Performance New Payroll',
                'periods' => $this->resolveNewPayrollPeriods($period),
            ],
        ];

        $buildUnits = 0;
        foreach ($reports as &$reportEntry) {
            $reportEntry['periods'] = array_values(array_unique(array_filter($reportEntry['periods'] ?? [])));
            $reportEntry['total_units'] = count($reportEntry['periods']);
            $buildUnits += (int) $reportEntry['total_units'];
        }
        unset($reportEntry);

        return [
            'reports' => $reports,
            'build_units' => $buildUnits,
            'total_units' => max(1, $buildUnits + 1),
        ];
    }

    public function rebuildDashboard(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveDashboardPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildDashboardPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    public function rebuildChartPeriodik(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveChartPeriodikPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildChartPeriodikPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    public function rebuildDashboardSimpanan(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveSimpananDashboardPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildDashboardSimpananPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        if ($period === null) {
            $this->cleanupSimpananDashboardSnapshotOrphans($periods);
        }

        return $results;
    }

    public function rebuildRasioCasa(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveRasioPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildRasioPeriodSnapshot($snapshotPeriod, $force)
                + $this->buildRasioUkerPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    public function rebuildRekeningDormant(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveDormantPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildDormantPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    public function rebuildPerformanceNewPayroll(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveNewPayrollPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildNewPayrollPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    private function reportProgress(
        ?callable $progress,
        string $snapshotPeriod,
        int $completedUnits,
        int $totalUnits,
        int $currentResultCount = 0
    ): void {
        if ($progress === null) {
            return;
        }

        $progress([
            'current_period' => $snapshotPeriod,
            'completed_units' => max(0, $completedUnits),
            'total_units' => $totalUnits,
            'current_result_count' => max(0, $currentResultCount),
        ]);
    }

    private function purgeSnapshotPeriodIfAnomalous(string $snapshotTable, string $period): bool
    {
        try {
            return app(SnapshotIntegrityGuard::class)->purgePeriodIfAnomalous($snapshotTable, $period, [
                'builder' => static::class,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function logSnapshotPeriodIfAnomalous(string $snapshotTable, string $period): void
    {
        try {
            app(SnapshotIntegrityGuard::class)->logIfAnomalous($snapshotTable, $period, [
                'builder' => static::class,
                'phase' => 'post_rebuild',
            ]);
        } catch (Throwable $e) {
            // Snapshot rebuild must not fail because the audit query failed.
        }
    }

    private function buildDashboardPeriodSnapshot(string $period, bool $force): int
    {
        $bucketExpression = $this->buildDashboardBucketExpression();
        $normalizedLoanBalanceExpression = $this->buildNormalizedLoanBalanceExpression('d.baki_debet1');
        $snapshotTable = self::DASHBOARD_SNAPSHOT_TABLE;
        $force = $force || $this->purgeSnapshotPeriodIfAnomalous($snapshotTable, $period);

        if ($force) {
            DB::table($snapshotTable)->where('periode', $period)->delete();
        }

        $this->statementWithConcurrencyRetry('dashboard period upsert', fn (): bool => DB::statement("
            INSERT INTO {$snapshotTable}
            (
                uniqueid_dps, periode, account_number, loan_balance, quality_bucket,
                segmen_dashboard, produk_dashboard, cabang1, unit1, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'dps', ?, TRIM(COALESCE(d.uniqueid_namareport, '')), TRIM(d.nomor_rekening1))) as uniqueid_dps,
                ? as periode,
                TRIM(d.nomor_rekening1) as account_number,
                {$normalizedLoanBalanceExpression} as loan_balance,
                {$bucketExpression} as quality_bucket,
                TRIM(COALESCE(d.segmen_dashboard, '')) as segmen_dashboard,
                TRIM(COALESCE(d.produk_dashboard, '')) as produk_dashboard,
                TRIM(COALESCE(d.cabang1, '')) as cabang1,
                TRIM(COALESCE(d.unit1, '')) as unit1,
                NOW() as created_at,
                NOW() as updated_at
            FROM daily_loan_dinamis d
            WHERE d.periode = ?
                AND d.nomor_rekening1 IS NOT NULL
                AND d.nomor_rekening1 <> ''
            ON DUPLICATE KEY UPDATE
                loan_balance = VALUES(loan_balance),
                quality_bucket = VALUES(quality_bucket),
                segmen_dashboard = VALUES(segmen_dashboard),
                produk_dashboard = VALUES(produk_dashboard),
                cabang1 = VALUES(cabang1),
                unit1 = VALUES(unit1),
                updated_at = VALUES(updated_at)
        ", [$period, $period, $period]));

        if (!$force) {
            $this->statementWithConcurrencyRetry('dashboard period prune', fn (): bool => DB::statement("
                DELETE snap
                FROM {$snapshotTable} snap
                LEFT JOIN (
                    SELECT
                        MD5(CONCAT_WS('|', 'dps', ?, TRIM(COALESCE(uniqueid_namareport, '')), TRIM(nomor_rekening1))) as uniqueid_dps
                    FROM daily_loan_dinamis
                    WHERE periode = ?
                        AND nomor_rekening1 IS NOT NULL
                        AND nomor_rekening1 <> ''
                ) src ON src.uniqueid_dps = snap.uniqueid_dps
                WHERE snap.periode = ?
                    AND src.uniqueid_dps IS NULL
            ", [$period, $period, $period]));
        }

        $rowCount = (int) DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->count();
        $this->logSnapshotPeriodIfAnomalous($snapshotTable, $period);

        return $rowCount;
    }

    private function buildChartPeriodikPeriodSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasTable(self::CHART_PERIODIK_SNAPSHOT_TABLE) || !Schema::hasTable('daily_loan_dinamis') || !Schema::hasTable('loan_type')) {
            return 0;
        }

        $force = $force || $this->purgeSnapshotPeriodIfAnomalous(self::CHART_PERIODIK_SNAPSHOT_TABLE, $period);

        if (DB::getDriverName() !== 'mysql') {
            return $this->buildChartPeriodikPeriodSnapshotPortable($period);
        }

        $snapshotTable = self::CHART_PERIODIK_SNAPSHOT_TABLE;

        if ($force) {
            DB::table($snapshotTable)->where('periode', $period)->delete();
        }

        $this->statementWithConcurrencyRetry('chart periodik period snapshot upsert', fn (): bool => DB::statement("
            INSERT INTO {$snapshotTable}
            (
                uniqueid_dpcs, periode, source_uniqueid_namareport, account_number, baki_debet1,
                ln_type, loan_type, pola_pembayaran, segmen_dashboard, produk_dashboard,
                cabang1, unit1, branch1, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'dpcs', ?, TRIM(COALESCE(d.uniqueid_namareport, '')), TRIM(COALESCE(d.nomor_rekening1, '')))) as uniqueid_dpcs,
                ? as periode,
                TRIM(COALESCE(d.uniqueid_namareport, '')) as source_uniqueid_namareport,
                TRIM(COALESCE(d.nomor_rekening1, '')) as account_number,
                COALESCE(d.baki_debet1, 0) as baki_debet1,
                UPPER(TRIM(COALESCE(d.ln_type, ''))) as ln_type,
                UPPER(COALESCE(NULLIF(TRIM(lt.loan_type), ''), TRIM(COALESCE(d.ln_type, '')))) as loan_type,
                COALESCE(NULLIF(UPPER(TRIM(lt.pola_pembayaran)), ''), 'TIDAK TERPETAKAN') as pola_pembayaran,
                UPPER(TRIM(COALESCE(d.segmen_dashboard, ''))) as segmen_dashboard,
                UPPER(TRIM(COALESCE(d.produk_dashboard, ''))) as produk_dashboard,
                UPPER(TRIM(COALESCE(d.cabang1, ''))) as cabang1,
                UPPER(TRIM(COALESCE(d.unit1, ''))) as unit1,
                UPPER(TRIM(COALESCE(d.branch1, ''))) as branch1,
                NOW() as created_at,
                NOW() as updated_at
            FROM daily_loan_dinamis d
            LEFT JOIN loan_type lt
                ON UPPER(TRIM(d.ln_type)) = UPPER(TRIM(lt.loan_type))
            WHERE d.periode = ?
                AND d.nomor_rekening1 IS NOT NULL
                AND d.nomor_rekening1 <> ''
            ON DUPLICATE KEY UPDATE
                source_uniqueid_namareport = VALUES(source_uniqueid_namareport),
                account_number = VALUES(account_number),
                baki_debet1 = VALUES(baki_debet1),
                ln_type = VALUES(ln_type),
                loan_type = VALUES(loan_type),
                pola_pembayaran = VALUES(pola_pembayaran),
                segmen_dashboard = VALUES(segmen_dashboard),
                produk_dashboard = VALUES(produk_dashboard),
                cabang1 = VALUES(cabang1),
                unit1 = VALUES(unit1),
                branch1 = VALUES(branch1),
                updated_at = VALUES(updated_at)
        ", [$period, $period, $period]));

        if (!$force) {
            $this->statementWithConcurrencyRetry('chart periodik period snapshot prune', fn (): bool => DB::statement("
                DELETE snap
                FROM {$snapshotTable} snap
                LEFT JOIN (
                    SELECT
                        MD5(CONCAT_WS('|', 'dpcs', ?, TRIM(COALESCE(uniqueid_namareport, '')), TRIM(COALESCE(nomor_rekening1, '')))) as uniqueid_dpcs
                    FROM daily_loan_dinamis
                    WHERE periode = ?
                        AND nomor_rekening1 IS NOT NULL
                        AND nomor_rekening1 <> ''
                ) src ON src.uniqueid_dpcs = snap.uniqueid_dpcs
                WHERE snap.periode = ?
                    AND src.uniqueid_dpcs IS NULL
            ", [$period, $period, $period]));
        }

        $rowCount = (int) DB::table(self::CHART_PERIODIK_SNAPSHOT_TABLE)->where('periode', $period)->count();
        $this->logSnapshotPeriodIfAnomalous($snapshotTable, $period);

        return $rowCount;
    }

    private function buildChartPeriodikPeriodSnapshotPortable(string $period): int
    {
        $rows = DB::table('daily_loan_dinamis as d')
            ->leftJoin('loan_type as lt', function ($join) {
                $join->on(DB::raw('UPPER(TRIM(d.ln_type))'), '=', DB::raw('UPPER(TRIM(lt.loan_type))'));
            })
            ->where('d.periode', $period)
            ->whereNotNull('d.nomor_rekening1')
            ->where('d.nomor_rekening1', '<>', '')
            ->selectRaw('TRIM(COALESCE(d.uniqueid_namareport, \'\')) as source_uniqueid_namareport')
            ->selectRaw('TRIM(COALESCE(d.nomor_rekening1, \'\')) as account_number')
            ->selectRaw('COALESCE(d.baki_debet1, 0) as baki_debet1')
            ->selectRaw('TRIM(COALESCE(d.ln_type, \'\')) as ln_type')
            ->selectRaw("COALESCE(NULLIF(TRIM(lt.loan_type), ''), TRIM(COALESCE(d.ln_type, ''))) as loan_type")
            ->selectRaw("COALESCE(NULLIF(UPPER(TRIM(lt.pola_pembayaran)), ''), 'TIDAK TERPETAKAN') as pola_pembayaran")
            ->selectRaw('TRIM(COALESCE(d.segmen_dashboard, \'\')) as segmen_dashboard')
            ->selectRaw('TRIM(COALESCE(d.produk_dashboard, \'\')) as produk_dashboard')
            ->selectRaw('TRIM(COALESCE(d.cabang1, \'\')) as cabang1')
            ->selectRaw('TRIM(COALESCE(d.unit1, \'\')) as unit1')
            ->selectRaw('TRIM(COALESCE(d.branch1, \'\')) as branch1')
            ->selectRaw('d.periode as periode')
            ->get()
            ->map(function ($row) use ($period) {
                $sourceId = (string) ($row->source_uniqueid_namareport ?? '');
                $accountNumber = (string) ($row->account_number ?? '');

                if ($accountNumber === '') {
                    return null;
                }

                return [
                    'uniqueid_dpcs' => md5(implode('|', ['dpcs', $period, $sourceId, $accountNumber])),
                    'periode' => $period,
                    'source_uniqueid_namareport' => $sourceId,
                    'account_number' => $accountNumber,
                    'baki_debet1' => (float) ($row->baki_debet1 ?? 0),
                    'ln_type' => strtoupper(trim((string) ($row->ln_type ?? ''))),
                    'loan_type' => strtoupper(trim((string) ($row->loan_type ?? ''))),
                    'pola_pembayaran' => strtoupper(trim((string) ($row->pola_pembayaran ?? 'TIDAK TERPETAKAN'))),
                    'segmen_dashboard' => strtoupper(trim((string) ($row->segmen_dashboard ?? ''))),
                    'produk_dashboard' => strtoupper(trim((string) ($row->produk_dashboard ?? ''))),
                    'cabang1' => strtoupper(trim((string) ($row->cabang1 ?? ''))),
                    'unit1' => strtoupper(trim((string) ($row->unit1 ?? ''))),
                    'branch1' => strtoupper(trim((string) ($row->branch1 ?? ''))),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        DB::table(self::CHART_PERIODIK_SNAPSHOT_TABLE)->where('periode', $period)->delete();

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::CHART_PERIODIK_SNAPSHOT_TABLE)->insert($chunk);
            }
        }

        $this->logSnapshotPeriodIfAnomalous(self::CHART_PERIODIK_SNAPSHOT_TABLE, $period);

        return count($rows);
    }

    private function buildNormalizedLoanBalanceExpression(string $column): string
    {
        $base = $this->loanBalanceRoundingBase();

        if ($base <= 1) {
            return $this->buildExcelSnapshotOsHelperExpression($column);
        }

        return "FLOOR(COALESCE({$column}, 0) / {$base}) * {$base}";
    }

    private function buildExcelSnapshotOsHelperExpression(string $column): string
    {
        $wholeRupiah = "TRUNCATE(COALESCE({$column}, 0), 0)";

        return "
            CASE
                WHEN ABS({$wholeRupiah}) >= 1000
                    AND ABS({$wholeRupiah}) < 1000000
                    AND MOD(ABS({$wholeRupiah}), 10) = 0
                    THEN SIGN({$wholeRupiah}) * CASE
                        WHEN MOD(ABS({$wholeRupiah}), 1000) = 0 THEN ABS({$wholeRupiah}) / 1000
                        WHEN MOD(ABS({$wholeRupiah}), 100) = 0 THEN ABS({$wholeRupiah}) / 100
                        ELSE ABS({$wholeRupiah}) / 10
                    END
                ELSE {$wholeRupiah}
            END
        ";
    }

    private function loanBalanceRoundingBase(): int
    {
        $configured = (int) config('reports.dashboard_pinjaman.row_rounding_base', 1);

        return $configured > 0 ? $configured : 1;
    }

    private function buildRasioPeriodSnapshot(string $loanPeriod, bool $force): int
    {
        $force = $force || $this->purgeSnapshotPeriodIfAnomalous(self::RASIO_SNAPSHOT_TABLE, $loanPeriod);

        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        if ($casaDate === null) {
            if ($force) {
                DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->delete();
            }

            return 0;
        }

        if ($force) {
            DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->delete();
        }

        if (DB::getDriverName() === 'mysql') {
            $rowCount = $this->buildRasioPeriodSnapshotSqlFirst($loanPeriod);
            $this->logSnapshotPeriodIfAnomalous(self::RASIO_SNAPSHOT_TABLE, $loanPeriod);

            return $rowCount;
        }

        $snapshot = $this->computeRasioSummary($loanPeriod);

        $rows = [];
        foreach (($snapshot['branch_labels'] ?? []) as $branchKey => $branchLabel) {
            foreach (self::SEGMENTS as $segmentKey) {
                $rows[] = [
                    'uniqueid_rcds' => $this->makeRasioSnapshotId($loanPeriod, $branchKey, $segmentKey),
                    'loan_period' => $loanPeriod,
                    'casa_period' => $snapshot['casa_date'],
                    'branch_key' => $branchKey,
                    'branch_label' => $branchLabel,
                    'segment_key' => $segmentKey,
                    'os_amount' => (float) ($snapshot['os'][$branchKey][$segmentKey] ?? 0),
                    'casa_amount' => (float) ($snapshot['casa'][$branchKey][$segmentKey] ?? 0),
                    'source_row_count' => (int) ($snapshot['row_count'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::RASIO_SNAPSHOT_TABLE)->upsert(
                    $chunk,
                    ['loan_period', 'branch_key', 'segment_key'],
                    ['casa_period', 'branch_label', 'os_amount', 'casa_amount', 'source_row_count', 'updated_at']
                );
            }
        }

        $this->logSnapshotPeriodIfAnomalous(self::RASIO_SNAPSHOT_TABLE, $loanPeriod);

        return count($rows);
    }

    private function buildRasioUkerPeriodSnapshot(string $loanPeriod, bool $force): int
    {
        if (!Schema::hasTable(self::RASIO_UKER_SNAPSHOT_TABLE)) {
            return 0;
        }

        $force = $force || $this->purgeSnapshotPeriodIfAnomalous(self::RASIO_UKER_SNAPSHOT_TABLE, $loanPeriod);

        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        if ($casaDate === null) {
            if ($force) {
                DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->delete();
            }

            return 0;
        }

        if (DB::getDriverName() === 'mysql') {
            if ($force) {
                DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->delete();
            }

            $rowCount = $this->buildRasioUkerPeriodSnapshotSqlFirst($loanPeriod);
            $this->logSnapshotPeriodIfAnomalous(self::RASIO_UKER_SNAPSHOT_TABLE, $loanPeriod);

            return $rowCount;
        }

        $rows = $this->computeRasioUkerSnapshotRows($loanPeriod);

        DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->delete();

        if (!empty($rows)) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)->upsert(
                    $chunk,
                    ['loan_period', 'source_branch_key', 'uker_key', 'segment_key'],
                    ['casa_period', 'uker_label', 'os_amount', 'casa_amount', 'source_row_count', 'updated_at']
                );
            }
        }

        $this->logSnapshotPeriodIfAnomalous(self::RASIO_UKER_SNAPSHOT_TABLE, $loanPeriod);

        return count($rows);
    }

    private function buildRasioPeriodSnapshotSqlFirst(string $loanPeriod): int
    {
        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);
        $loanKeyColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
        $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');

        $loanIdentitySql = $this->buildLoanIdentityExpression($loanKeyColumn);
        $loanBranchSql = $this->buildLoanNormalizedExpression($loanBranchColumn, 'cabang_normalized');
        $brigunaFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'smc');

        $casaSelectSql = $casaDate ? "
            SUM(COALESCE(c.casa_balance, 0)) as total_casa,
            SUM(CASE WHEN base.has_briguna = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as briguna_casa,
            SUM(CASE WHEN base.has_kpr = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as kpr_casa,
            SUM(CASE WHEN base.has_mikro = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as mikro_casa,
            SUM(CASE WHEN base.has_smc = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as smc_casa
        " : "
            0 as total_casa, 0 as briguna_casa, 0 as kpr_casa, 0 as mikro_casa, 0 as smc_casa
        ";

        $casaJoinSql = '';
        $bindings = [$loanPeriod, $loanPeriod, $casaDate, $loanPeriod];

        if ($casaDate) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $this->ensureRasioCasaTempTable($casaDate, $casaKeyColumn, $applyCasaTypeFilter);
            $casaJoinSql = "
                LEFT JOIN tmp_rasio_casa_balances c ON c.identity_key = base.identity_key
            ";
        }

        $this->statementWithConcurrencyRetry('rasio snapshot upsert', fn (): bool => DB::statement("
            INSERT INTO " . self::RASIO_SNAPSHOT_TABLE . " (
                uniqueid_rcds, loan_period, casa_period, branch_key, 
                branch_label, segment_key, os_amount, casa_amount, 
                source_row_count, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'rcds', ?, agg.branch_key, seg.segment_key)),
                ?,
                ?,
                agg.branch_key,
                agg.branch_key as branch_label,
                seg.segment_key,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_os
                    WHEN 'briguna' THEN agg.briguna_os
                    WHEN 'kpr' THEN agg.kpr_os
                    WHEN 'mikro' THEN agg.mikro_os
                    WHEN 'smc' THEN agg.smc_os
                    ELSE 0
                END as os_amount,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_casa
                    WHEN 'briguna' THEN agg.briguna_casa
                    WHEN 'kpr' THEN agg.kpr_casa
                    WHEN 'mikro' THEN agg.mikro_casa
                    WHEN 'smc' THEN agg.smc_casa
                    ELSE 0
                END as casa_amount,
                agg.source_row_count,
                NOW(),
                NOW()
            FROM (
                SELECT
                    base.branch_key,
                    SUM(base.loan_balance) as total_os,
                    SUM(CASE WHEN base.has_briguna = 1 THEN base.loan_balance ELSE 0 END) as briguna_os,
                    SUM(CASE WHEN base.has_kpr = 1 THEN base.loan_balance ELSE 0 END) as kpr_os,
                    SUM(CASE WHEN base.has_mikro = 1 THEN base.loan_balance ELSE 0 END) as mikro_os,
                    SUM(CASE WHEN base.has_smc = 1 THEN base.loan_balance ELSE 0 END) as smc_os,
                    {$casaSelectSql},
                    SUM(base.source_row_count) as source_row_count
                FROM (
                    SELECT
                        {$loanBranchSql} as branch_key,
                        {$loanIdentitySql} as identity_key,
                        SUM(COALESCE(d.{$loanBalanceColumn}, 0)) as loan_balance,
                        MAX({$brigunaFlagSql}) as has_briguna,
                        MAX({$kprFlagSql}) as has_kpr,
                        MAX({$mikroFlagSql}) as has_mikro,
                        MAX({$smcFlagSql}) as has_smc,
                        COUNT(*) as source_row_count
                    FROM daily_loan_dinamis d
                    WHERE d.periode = ?
                        AND d.{$loanKeyColumn} IS NOT NULL AND d.{$loanKeyColumn} <> ''
                        AND d.{$loanBranchColumn} IS NOT NULL AND d.{$loanBranchColumn} <> ''
                    GROUP BY branch_key, identity_key
                ) base
                {$casaJoinSql}
                GROUP BY base.branch_key
            ) agg
            CROSS JOIN (
                SELECT 'total' as segment_key UNION ALL
                SELECT 'briguna' UNION ALL
                SELECT 'kpr' UNION ALL
                SELECT 'mikro' UNION ALL
                SELECT 'smc'
            ) seg
            WHERE 1 = 1
            ON DUPLICATE KEY UPDATE
                casa_period = VALUES(casa_period),
                branch_label = VALUES(branch_label),
                os_amount = VALUES(os_amount),
                casa_amount = VALUES(casa_amount),
                source_row_count = VALUES(source_row_count),
                updated_at = VALUES(updated_at)
        ", $bindings));

        return (int) DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->count();
    }

    private function buildRasioUkerPeriodSnapshotSqlFirst(string $loanPeriod): int
    {
        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);
        $loanKeyColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
        $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanUkerColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['unit1', 'unit'], 'unit1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');

        $loanIdentitySql = $this->buildLoanIdentityExpression($loanKeyColumn);
        $loanBranchSql = $this->buildLoanNormalizedExpression($loanBranchColumn, 'cabang_normalized');
        $loanUkerSql = $this->buildLoanNormalizedExpression($loanUkerColumn, 'unit_normalized');
        $brigunaFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'smc');

        $casaSelectSql = $casaDate ? "
            SUM(COALESCE(c.casa_balance, 0)) as total_casa,
            SUM(CASE WHEN base.has_briguna = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as briguna_casa,
            SUM(CASE WHEN base.has_kpr = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as kpr_casa,
            SUM(CASE WHEN base.has_mikro = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as mikro_casa,
            SUM(CASE WHEN base.has_smc = 1 THEN COALESCE(c.casa_balance, 0) ELSE 0 END) as smc_casa
        " : "
            0 as total_casa, 0 as briguna_casa, 0 as kpr_casa, 0 as mikro_casa, 0 as smc_casa
        ";

        $casaJoinSql = '';
        $bindings = [$loanPeriod, $loanPeriod, $casaDate, $loanPeriod];

        if ($casaDate) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $this->ensureRasioCasaTempTable($casaDate, $casaKeyColumn, $applyCasaTypeFilter);
            $casaJoinSql = "
                LEFT JOIN tmp_rasio_casa_balances c ON c.identity_key = base.identity_key
            ";
        }

        $this->statementWithConcurrencyRetry('rasio uker snapshot upsert', fn (): bool => DB::statement("
            INSERT INTO " . self::RASIO_UKER_SNAPSHOT_TABLE . " (
                uniqueid_rcdus, loan_period, casa_period, source_branch_key, 
                uker_key, uker_label, segment_key, os_amount, casa_amount, 
                source_row_count, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'rcdus', ?, agg.source_branch_key, agg.uker_key, seg.segment_key)),
                ?,
                ?,
                agg.source_branch_key,
                agg.uker_key,
                agg.uker_key as uker_label,
                seg.segment_key,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_os
                    WHEN 'briguna' THEN agg.briguna_os
                    WHEN 'kpr' THEN agg.kpr_os
                    WHEN 'mikro' THEN agg.mikro_os
                    WHEN 'smc' THEN agg.smc_os
                    ELSE 0
                END as os_amount,
                CASE seg.segment_key
                    WHEN 'total' THEN agg.total_casa
                    WHEN 'briguna' THEN agg.briguna_casa
                    WHEN 'kpr' THEN agg.kpr_casa
                    WHEN 'mikro' THEN agg.mikro_casa
                    WHEN 'smc' THEN agg.smc_casa
                    ELSE 0
                END as casa_amount,
                agg.source_row_count,
                NOW(),
                NOW()
            FROM (
                SELECT
                    base.source_branch_key,
                    base.uker_key,
                    SUM(base.loan_balance) as total_os,
                    SUM(CASE WHEN base.has_briguna = 1 THEN base.loan_balance ELSE 0 END) as briguna_os,
                    SUM(CASE WHEN base.has_kpr = 1 THEN base.loan_balance ELSE 0 END) as kpr_os,
                    SUM(CASE WHEN base.has_mikro = 1 THEN base.loan_balance ELSE 0 END) as mikro_os,
                    SUM(CASE WHEN base.has_smc = 1 THEN base.loan_balance ELSE 0 END) as smc_os,
                    {$casaSelectSql},
                    SUM(base.source_row_count) as source_row_count
                FROM (
                    SELECT
                        {$loanBranchSql} as source_branch_key,
                        {$loanUkerSql} as uker_key,
                        {$loanIdentitySql} as identity_key,
                        SUM(COALESCE(d.{$loanBalanceColumn}, 0)) as loan_balance,
                        MAX({$brigunaFlagSql}) as has_briguna,
                        MAX({$kprFlagSql}) as has_kpr,
                        MAX({$mikroFlagSql}) as has_mikro,
                        MAX({$smcFlagSql}) as has_smc,
                        COUNT(*) as source_row_count
                    FROM daily_loan_dinamis d
                    WHERE d.periode = ?
                        AND d.{$loanKeyColumn} IS NOT NULL AND d.{$loanKeyColumn} <> ''
                        AND d.{$loanBranchColumn} IS NOT NULL AND d.{$loanBranchColumn} <> ''
                        AND d.{$loanUkerColumn} IS NOT NULL AND d.{$loanUkerColumn} <> ''
                    GROUP BY source_branch_key, uker_key, identity_key
                ) base
                {$casaJoinSql}
                GROUP BY base.source_branch_key, base.uker_key
            ) agg
            CROSS JOIN (
                SELECT 'total' as segment_key UNION ALL
                SELECT 'briguna' UNION ALL
                SELECT 'kpr' UNION ALL
                SELECT 'mikro' UNION ALL
                SELECT 'smc'
            ) seg
            WHERE 1 = 1
            ON DUPLICATE KEY UPDATE
                casa_period = VALUES(casa_period),
                uker_label = VALUES(uker_label),
                os_amount = VALUES(os_amount),
                casa_amount = VALUES(casa_amount),
                source_row_count = VALUES(source_row_count),
                updated_at = VALUES(updated_at)
        ", $bindings));

        return (int) DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->count();
    }

    private function buildDashboardSimpananPeriodSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasTable(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE) || !Schema::hasTable('simpanan_multipn')) {
            return 0;
        }

        $force = $force
            || $this->purgeSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, $period)
            || $this->purgeSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE, $period);

        if ($force) {
            DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            if (Schema::hasTable(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)) {
                DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
            }
        }

        $existingSnapshot = null;
        if (!$force) {
            $existingSnapshot = DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)
                ->where('snapshot_period', $period)
                ->first(['source_row_count', 'source_updated_at']);
        }

        $baseQuery = DB::table('simpanan_multipn')->where('posisi', $period);
        $sourceMetadata = null;

        if (!$force && $existingSnapshot !== null) {
            $sourceMetadata = (clone $baseQuery)
                ->selectRaw('COUNT(*) as source_row_count')
                ->selectRaw('MAX(updated_at) as source_updated_at')
                ->first();

            if ($this->dashboardSimpananSnapshotIsFresh($existingSnapshot, $sourceMetadata)) {
                $this->logSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, $period);
                $this->logSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE, $period);

                return (int) ($existingSnapshot->source_row_count ?? 0);
            }
        }

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as source_row_count')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT no_rekening) as account_count')
            ->selectRaw('COUNT(DISTINCT CIFNO) as cif_count')
            ->selectRaw('COUNT(DISTINCT unit_kerja) as unit_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as tabungan_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as giro_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) NOT LIKE 'TABUNGAN%' AND UPPER(COALESCE(jenis_simpanan, '')) NOT LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as other_balance")
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        $sourceRowCount = (int) ($summary->source_row_count ?? 0);
        if ($sourceRowCount <= 0) {
            DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
            DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        $totalBalance = $this->normalizeSnapshotDecimal($summary->total_balance ?? 0);
        $tabunganBalance = $this->normalizeSnapshotDecimal($summary->tabungan_balance ?? 0);
        $giroBalance = $this->normalizeSnapshotDecimal($summary->giro_balance ?? 0);
        $otherBalance = $this->normalizeSnapshotDecimal($summary->other_balance ?? 0);

        $branchBalances = (clone $baseQuery)
            ->whereNotNull('kantor_cabang')
            ->where('kantor_cabang', '<>', '')
            ->selectRaw('TRIM(kantor_cabang) as kantor_cabang')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->groupBy('kantor_cabang')
            ->get();
        $branchCount = $branchBalances->count();
        $topBranches = $branchBalances
            ->sortByDesc(fn ($row) => (float) ($row->total_balance ?? 0))
            ->take(5)
            ->values();

        $topBranch = $topBranches->first();
        $simpananGate = app(SimpananMultiPnSnapshotGate::class);
        $missingBranches = $simpananGate->getMissingBranches($period);
        $completenessPayload = [];
        $completenessUpdateColumns = [];
        if (Schema::hasColumn(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, 'snapshot_completeness')) {
            $completenessPayload['snapshot_completeness'] = $missingBranches === [] ? 'complete' : 'partial';
            $completenessUpdateColumns[] = 'snapshot_completeness';
        }
        if (Schema::hasColumn(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, 'partial_branches')) {
            $completenessPayload['partial_branches'] = $missingBranches === [] ? null : json_encode(array_values($missingBranches), JSON_UNESCAPED_UNICODE);
            $completenessUpdateColumns[] = 'partial_branches';
        }

        DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)->upsert([
            array_merge([
                'uniqueid_dss' => md5(implode('|', ['dss', $period])),
                'snapshot_period' => $period,
                'total_balance' => $totalBalance,
                'account_count' => (int) ($summary->account_count ?? 0),
                'cif_count' => (int) ($summary->cif_count ?? 0),
                'branch_count' => $branchCount,
                'unit_count' => (int) ($summary->unit_count ?? 0),
                'tabungan_balance' => $tabunganBalance,
                'giro_balance' => $giroBalance,
                'other_balance' => $otherBalance,
                'top_branch_label' => trim((string) ($topBranch->kantor_cabang ?? '')),
                'top_branch_balance' => $this->normalizeSnapshotDecimal($topBranch->total_balance ?? 0),
                'source_row_count' => $sourceRowCount,
                'source_updated_at' => $summary->source_updated_at,
                'created_at' => now(),
                'updated_at' => now(),
            ], $completenessPayload),
        ], ['uniqueid_dss'], [
            'total_balance',
            'account_count',
            'cif_count',
            'branch_count',
            'unit_count',
            'tabungan_balance',
            'giro_balance',
            'other_balance',
            'top_branch_label',
            'top_branch_balance',
            'source_row_count',
            'source_updated_at',
            ...$completenessUpdateColumns,
            'updated_at',
        ]);

        $branchPayload = [];
        $branchKeys = [];
        foreach ($topBranches->values() as $index => $row) {
            $branchLabel = trim((string) ($row->kantor_cabang ?? ''));
            if ($branchLabel === '') {
                continue;
            }

            $branchKeys[] = $branchLabel;
            $branchPayload[] = [
                'uniqueid_dsbs' => md5(implode('|', ['dsbs', $period, $branchLabel])),
                'snapshot_period' => $period,
                'kantor_cabang' => $branchLabel,
                'total_balance' => $this->normalizeSnapshotDecimal($row->total_balance ?? 0),
                'rank_order' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($branchPayload)) {
            DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->upsert(
                $branchPayload,
                ['snapshot_period', 'kantor_cabang'],
                ['total_balance', 'rank_order', 'updated_at']
            );
        }

        if (!$force) {
            $branchCleanup = DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->where('snapshot_period', $period);
            if (!empty($branchKeys)) {
                $branchCleanup->whereNotIn('kantor_cabang', $branchKeys)->delete();
            } else {
                $branchCleanup->delete();
            }
        }

        $this->logSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, $period);
        $this->logSnapshotPeriodIfAnomalous(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE, $period);

        return $sourceRowCount;
    }

    private function normalizeSnapshotDecimal(mixed $value): string
    {
        $normalized = trim((string) ($value ?? '0'));

        return $normalized === '' ? '0' : $normalized;
    }

    private function dashboardSimpananSnapshotIsFresh(object $existingSnapshot, ?object $sourceMetadata): bool
    {
        if ($sourceMetadata === null) {
            return false;
        }

        $snapshotRowCount = (int) ($existingSnapshot->source_row_count ?? -1);
        $sourceRowCount = (int) ($sourceMetadata->source_row_count ?? -2);
        if ($snapshotRowCount !== $sourceRowCount) {
            return false;
        }

        $snapshotUpdatedAt = $this->normalizeComparableTimestamp($existingSnapshot->source_updated_at ?? null);
        $sourceUpdatedAt = $this->normalizeComparableTimestamp($sourceMetadata->source_updated_at ?? null);

        return $snapshotUpdatedAt === $sourceUpdatedAt;
    }

    private function normalizeComparableTimestamp(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::parse($normalized)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return $normalized;
        }
    }

    private function buildDormantPeriodSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasColumn(self::DORMANT_SNAPSHOT_TABLE, 'snapshot_version')) {
            return 0;
        }

        $snapshotTable = self::DORMANT_SNAPSHOT_TABLE;
        $branchLabelExpression = $this->buildDormantBranchLabelSqlExpression('base.raw_branch');
        $force = $force || $this->purgeSnapshotPeriodIfAnomalous($snapshotTable, $period);

        if ($force) {
            DB::table($snapshotTable)
                ->where('posisi', $period)
                ->delete();
        }

        $conflictSql = $force ? '' : "
            ON DUPLICATE KEY UPDATE
                branch_label = VALUES(branch_label),
                dormant_count = VALUES(dormant_count),
                snapshot_version = VALUES(snapshot_version),
                updated_at = VALUES(updated_at)
        ";

        $dormantBranchFilterExpression = $this->buildDormantBranchFilterSqlExpression('kantor_cabang');

        $this->statementWithConcurrencyRetry('dormant snapshot upsert', fn (): bool => DB::statement("
            INSERT INTO {$snapshotTable}
            (
                uniqueid_rds, posisi, branch_label, raw_branch, unit_kerja, dormant_count, snapshot_version, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'rds', ?, TRIM(base.raw_branch), TRIM(base.unit_kerja))) as uniqueid_rds,
                ? as posisi,
                {$branchLabelExpression} as branch_label,
                TRIM(base.raw_branch) as raw_branch,
                TRIM(base.unit_kerja) as unit_kerja,
                base.dormant_count as dormant_count,
                " . self::DORMANT_SNAPSHOT_VERSION . " as snapshot_version,
                NOW() as created_at,
                NOW() as updated_at
            FROM (
                SELECT
                    normalized.raw_branch as raw_branch,
                    normalized.unit_kerja as unit_kerja,
                    COUNT(DISTINCT normalized.no_rekening) as dormant_count
                FROM (
                    SELECT
                        TRIM(kantor_cabang) as raw_branch,
                        COALESCE(NULLIF(TRIM(unit_kerja), ''), '') as unit_kerja,
                        TRIM(no_rekening) as no_rekening
                    FROM simpanan_multipn
                    WHERE posisi = ?
                        AND status = '9'
                        AND kantor_cabang IS NOT NULL
                        AND kantor_cabang <> ''
                        AND {$dormantBranchFilterExpression}
                        AND no_rekening IS NOT NULL
                        AND no_rekening <> ''
                ) normalized
                GROUP BY normalized.raw_branch, normalized.unit_kerja
            ) base
            WHERE {$branchLabelExpression} IS NOT NULL
            {$conflictSql}
        ", [$period, $period, $period]));

        if (!$force) {
            $this->statementWithConcurrencyRetry('dormant snapshot prune', fn (): bool => DB::statement("
                DELETE snap
                FROM {$snapshotTable} snap
                LEFT JOIN (
                    SELECT
                        MD5(CONCAT_WS('|', 'rds', ?, TRIM(base.raw_branch), TRIM(base.unit_kerja))) as uniqueid_rds
                    FROM (
                        SELECT
                            normalized.raw_branch as raw_branch,
                            normalized.unit_kerja as unit_kerja
                        FROM (
                            SELECT
                                TRIM(kantor_cabang) as raw_branch,
                                COALESCE(NULLIF(TRIM(unit_kerja), ''), '') as unit_kerja
                            FROM simpanan_multipn
                            WHERE posisi = ?
                                AND status = '9'
                                AND kantor_cabang IS NOT NULL
                                AND kantor_cabang <> ''
                                AND {$dormantBranchFilterExpression}
                                AND no_rekening IS NOT NULL
                                AND no_rekening <> ''
                        ) normalized
                        GROUP BY normalized.raw_branch, normalized.unit_kerja
                    ) base
                    WHERE {$branchLabelExpression} IS NOT NULL
                ) src ON src.uniqueid_rds = snap.uniqueid_rds
                WHERE snap.posisi = ?
                    AND snap.snapshot_version = ?
                    AND src.uniqueid_rds IS NULL
            ", [$period, $period, $period, self::DORMANT_SNAPSHOT_VERSION]));
        }

        $rowCount = (int) DB::table(self::DORMANT_SNAPSHOT_TABLE)
            ->where('posisi', $period)
            ->where('snapshot_version', self::DORMANT_SNAPSHOT_VERSION)
            ->count();
        $this->logSnapshotPeriodIfAnomalous($snapshotTable, $period);

        return $rowCount;
    }

    private function buildNewPayrollPeriodSnapshot(string $snapshotPosisi, bool $force): int
    {
        if (!Schema::hasTable(self::NEW_PAYROLL_SNAPSHOT_TABLE) || !Schema::hasTable('performance_pis_per_produk')) {
            return 0;
        }

        $force = $force || $this->purgeSnapshotPeriodIfAnomalous(self::NEW_PAYROLL_SNAPSHOT_TABLE, $snapshotPosisi);

        $snapshotDate = Carbon::parse($snapshotPosisi);
        $currStart = $snapshotDate->copy()->startOfYear()->toDateString();
        $currEnd = $snapshotDate->copy()->endOfMonth()->toDateString();
        $prevEndDate = $snapshotDate->copy()->subMonthNoOverflow()->endOfMonth();
        $prevStart = $prevEndDate->copy()->startOfYear()->toDateString();
        $prevEnd = $prevEndDate->toDateString();
        $yoyDate = $snapshotDate->copy()->subYearNoOverflow();
        $yoyStart = $yoyDate->copy()->startOfYear()->toDateString();
        $yoyEnd = $yoyDate->copy()->endOfMonth()->toDateString();

        $prevSnapshot = DB::table('performance_pis_per_produk')
            ->whereDate('posisi', '<=', $prevEnd)
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), self::NEW_PAYROLL_BRANCHES)
            ->max('posisi') ?? $snapshotPosisi;

        $yoySnapshot = DB::table('performance_pis_per_produk')
            ->whereDate('posisi', '<=', $yoyEnd)
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), self::NEW_PAYROLL_BRANCHES)
            ->max('posisi') ?? $snapshotPosisi;

        // Use single INSERT ... SELECT ... ON DUPLICATE KEY UPDATE
        $branchList = implode("','", self::NEW_PAYROLL_BRANCHES);

        if ($force) {
            DB::table(self::NEW_PAYROLL_SNAPSHOT_TABLE)->where('snapshot_posisi', $snapshotPosisi)->delete();
        }

        $conflictSql = $force ? '' : "
            ON DUPLICATE KEY UPDATE
                rekening_curr = VALUES(rekening_curr),
                rekening_prev = VALUES(rekening_prev),
                rekening_yoy_prev = VALUES(rekening_yoy_prev),
                saldo_curr = VALUES(saldo_curr),
                saldo_prev = VALUES(saldo_prev),
                saldo_yoy_prev = VALUES(saldo_yoy_prev),
                updated_at = VALUES(updated_at)
        ";

        $this->statementWithConcurrencyRetry('new payroll snapshot upsert', fn (): bool => DB::statement(
            "INSERT INTO " . self::NEW_PAYROLL_SNAPSHOT_TABLE . " (
                uniqueid_pnps, snapshot_posisi, branch, rekening_curr, rekening_prev, rekening_yoy_prev,
                saldo_curr, saldo_prev, saldo_yoy_prev, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'pnps', ?, base.branch)) as uniqueid_pnps,
                ? as snapshot_posisi,
                base.branch as branch,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_curr,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_prev,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_yoy_prev,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN base.saldo_britama_kerjasama ELSE 0 END) as saldo_curr,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN base.saldo_britama_kerjasama ELSE 0 END) as saldo_prev,
                SUM(CASE WHEN base.posisi = ? AND base.tanggal_pembuatan_rekening BETWEEN ? AND ? THEN base.saldo_britama_kerjasama ELSE 0 END) as saldo_yoy_prev,
                NOW() as created_at,
                NOW() as updated_at
            FROM (
                SELECT
                    TRIM(UPPER(kanca)) as branch,
                    posisi,
                    tanggal_pembuatan_rekening,
                    saldo_britama_kerjasama
                FROM performance_pis_per_produk
                WHERE posisi IN (?, ?, ?)
                    AND TRIM(UPPER(kanca)) IN ('{$branchList}')
            ) base
            GROUP BY base.branch
            {$conflictSql}",
            [
                $snapshotPosisi, // for MD5
                $snapshotPosisi, // snapshot_posisi
                $snapshotPosisi, $currStart, $currEnd,
                $prevSnapshot, $prevStart, $prevEnd,
                $yoySnapshot, $yoyStart, $yoyEnd,
                $snapshotPosisi, $currStart, $currEnd,
                $prevSnapshot, $prevStart, $prevEnd,
                $yoySnapshot, $yoyStart, $yoyEnd,
                $snapshotPosisi, $prevSnapshot, $yoySnapshot, // WHERE posisi IN (?, ?, ?)
            ]
        ));

        if (!$force) {
            DB::table(self::NEW_PAYROLL_SNAPSHOT_TABLE)
                ->where('snapshot_posisi', $snapshotPosisi)
                ->whereNotIn('branch', self::NEW_PAYROLL_BRANCHES)
                ->delete();
        }

        $this->logSnapshotPeriodIfAnomalous(self::NEW_PAYROLL_SNAPSHOT_TABLE, $snapshotPosisi);

        return count(self::NEW_PAYROLL_BRANCHES);
    }

    private function computeRasioSummary(string $loanPeriod): array
    {
        $loanKeyColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
        $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');
        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        $loanBranchSql = $this->buildLoanBranchExpression($loanBranchColumn);
        $loanIdentitySql = "TRIM({$loanKeyColumn})";
        $brigunaFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression($loanSegmentColumn, $loanProductColumn, 'smc');

        $loanBase = DB::table('daily_loan_dinamis')
            ->where('periode', $loanPeriod)
            ->whereNotNull($loanKeyColumn)
            ->where($loanKeyColumn, '<>', '')
            ->selectRaw("
                {$loanBranchSql} as branch_key,
                {$loanIdentitySql} as identity_key,
                COALESCE({$loanBalanceColumn}, 0) as loan_balance,
                1 as has_total,
                {$brigunaFlagSql} as has_briguna,
                {$kprFlagSql} as has_kpr,
                {$mikroFlagSql} as has_mikro,
                {$smcFlagSql} as has_smc
            ");

        $loanRows = DB::query()
            ->fromSub($loanBase, 'loan_base')
            ->selectRaw("
                branch_key,
                identity_key,
                SUM(loan_balance) as total_os,
                SUM(CASE WHEN has_briguna = 1 THEN loan_balance ELSE 0 END) as briguna_os,
                SUM(CASE WHEN has_kpr = 1 THEN loan_balance ELSE 0 END) as kpr_os,
                SUM(CASE WHEN has_mikro = 1 THEN loan_balance ELSE 0 END) as mikro_os,
                SUM(CASE WHEN has_smc = 1 THEN loan_balance ELSE 0 END) as smc_os,
                MAX(has_total) as has_total,
                MAX(has_briguna) as has_briguna,
                MAX(has_kpr) as has_kpr,
                MAX(has_mikro) as has_mikro,
                MAX(has_smc) as has_smc
            ")
            ->groupBy('branch_key', 'identity_key')
            ->orderByRaw($this->buildBranchSortExpression('branch_key'))
            ->get();

        $snapshot = [
            'loan_date' => $loanPeriod,
            'casa_date' => $casaDate,
            'row_count' => 0,
            'branch_labels' => [],
            'os' => [],
            'casa' => [],
        ];

        $identityMappings = [];
        $identityVariants = [];

        foreach ($loanRows as $row) {
            $branchKey = $this->normalizePriorityBranchKey($row->branch_key ?? null);
            $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

            if ($branchKey === '' || $identityKey === '') {
                continue;
            }

            $snapshot['row_count']++;
            $snapshot['branch_labels'][$branchKey] = $this->formatPriorityBranchLabel($branchKey);
            $snapshot['os'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
            $snapshot['casa'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);

            $snapshot['os'][$branchKey]['total'] += (float) ($row->total_os ?? 0);
            $snapshot['os'][$branchKey]['briguna'] += (float) ($row->briguna_os ?? 0);
            $snapshot['os'][$branchKey]['kpr'] += (float) ($row->kpr_os ?? 0);
            $snapshot['os'][$branchKey]['mikro'] += (float) ($row->mikro_os ?? 0);
            $snapshot['os'][$branchKey]['smc'] += (float) ($row->smc_os ?? 0);

            $identityMappings[$identityKey][$branchKey] = [
                'total' => ((int) ($row->has_total ?? 0)) === 1,
                'briguna' => ((int) ($row->has_briguna ?? 0)) === 1,
                'kpr' => ((int) ($row->has_kpr ?? 0)) === 1,
                'mikro' => ((int) ($row->has_mikro ?? 0)) === 1,
                'smc' => ((int) ($row->has_smc ?? 0)) === 1,
            ];

            foreach ($this->buildIdentityVariants($identityKey) as $variant) {
                $identityVariants[$variant] = true;
            }
        }

        if ($casaDate && !empty($identityVariants)) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
            
            foreach (array_chunk(array_keys($identityVariants), 10000) as $identityChunk) {
                $casaBalances = DB::table('simpanan_multipn')
                    ->where('posisi', $casaDate)
                    ->whereIn($casaKeyColumn, $identityChunk)
                    ->when($applyCasaTypeFilter, function ($query) {
                        $query->where(function ($inner) {
                            $inner->where('jenis_simpanan', 'like', 'GIRO%')
                                ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                        });
                    })
                    ->selectRaw("{$casaKeyColumn} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
                    ->groupBy($casaKeyColumn)
                    ->get()
                    ->pluck('casa_balance', 'identity_key')
                    ->all();

                foreach ($casaBalances as $identityKey => $balance) {
                    $normId = $this->normalizeIdentityKey($identityKey);
                    foreach (($identityMappings[$normId] ?? []) as $branchKey => $flags) {
                        foreach ($flags as $segmentKey => $enabled) {
                            if ($enabled) {
                                $snapshot['casa'][$branchKey][$segmentKey] += (float) $balance;
                            }
                        }
                    }
                }
            }
        }

        foreach (self::PRIORITY_BRANCHES as $branchKey) {
            $snapshot['branch_labels'][$branchKey] ??= $this->formatPriorityBranchLabel($branchKey);
            $snapshot['os'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
            $snapshot['casa'][$branchKey] ??= array_fill_keys(self::SEGMENTS, 0);
        }

        return $snapshot;
    }

    private function computeRasioUkerSnapshotRows(string $loanPeriod): array
    {
        $loanKeyColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
        $casaKeyColumn = $this->resolveExistingColumn('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanUkerColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['unit1', 'unit'], 'unit1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');
        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        $loanIdentitySql = $this->buildJoinableIdentitySql("d.{$loanKeyColumn}");
        $brigunaFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression("d.{$loanSegmentColumn}", "d.{$loanProductColumn}", 'smc');

        $dldTable = DB::raw($this->queryOptimizer->optimizeSnapshotQuery('daily_loan_dinamis', 'd', ['idx_daily_loan_periode', 'idx_daily_loan_periode_cabang']));

        $loanBase = DB::table($dldTable)
            ->where('d.periode', $loanPeriod)
            ->whereNotNull("d.{$loanKeyColumn}")
            ->where("d.{$loanKeyColumn}", '<>', '')
            ->whereNotNull("d.{$loanBranchColumn}")
            ->where("d.{$loanBranchColumn}", '<>', '')
            ->whereNotNull("d.{$loanUkerColumn}")
            ->where("d.{$loanUkerColumn}", '<>', '')
            ->selectRaw("
                UPPER(TRIM(d.{$loanBranchColumn})) as source_branch_key,
                UPPER(TRIM(d.{$loanUkerColumn})) as uker_key,
                {$loanIdentitySql} as identity_key,
                COALESCE(d.{$loanBalanceColumn}, 0) as loan_balance,
                1 as has_total,
                {$brigunaFlagSql} as has_briguna,
                {$kprFlagSql} as has_kpr,
                {$mikroFlagSql} as has_mikro,
                {$smcFlagSql} as has_smc
            ");

        $loanPerCif = DB::query()
            ->fromSub($loanBase, 'loan_base')
            ->selectRaw("
                source_branch_key,
                uker_key,
                identity_key,
                SUM(loan_balance) as loan_balance,
                MAX(has_total) as has_total,
                MAX(has_briguna) as has_briguna,
                MAX(has_kpr) as has_kpr,
                MAX(has_mikro) as has_mikro,
                MAX(has_smc) as has_smc
            ")
            ->groupBy('source_branch_key', 'uker_key', 'identity_key');

        $joined = DB::query()->fromSub($loanPerCif, 'loan_per_cif');
        $includeCasa = (bool) $casaDate;

        if ($includeCasa) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $casaIdentitySql = $this->buildJoinableIdentitySql($casaKeyColumn);

            // Apply FORCE INDEX for optimal MySQL Optimizer behavior on large simpanan_multipn tables
            $smTable = DB::raw($this->queryOptimizer->optimizeSnapshotQuery('simpanan_multipn', 'c', [
                'idx_smp_posisi_distinct_queries',
                'idx_smp_period_covering_counts',
            ]));

            $casaBase = DB::table($smTable)
                ->where('c.posisi', $casaDate)
                ->whereNotNull("c.{$casaKeyColumn}")
                ->where("c.{$casaKeyColumn}", '<>', '')
                ->when($applyCasaTypeFilter, function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('c.jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('c.jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                })
                ->selectRaw("{$casaIdentitySql} as identity_key, SUM(COALESCE(c.saldo_idr, 0)) as casa_balance")
                ->groupBy('identity_key');

            $joined->leftJoinSub($casaBase, 'casa_base', function ($join) {
                $join->on('loan_per_cif.identity_key', '=', 'casa_base.identity_key');
            });
        }

        $casaSelectSql = $includeCasa
            ? "
                SUM(COALESCE(casa_base.casa_balance, 0)) as total_casa,
                SUM(CASE WHEN loan_per_cif.has_briguna = 1 THEN COALESCE(casa_base.casa_balance, 0) ELSE 0 END) as briguna_casa,
                SUM(CASE WHEN loan_per_cif.has_kpr = 1 THEN COALESCE(casa_base.casa_balance, 0) ELSE 0 END) as kpr_casa,
                SUM(CASE WHEN loan_per_cif.has_mikro = 1 THEN COALESCE(casa_base.casa_balance, 0) ELSE 0 END) as mikro_casa,
                SUM(CASE WHEN loan_per_cif.has_smc = 1 THEN COALESCE(casa_base.casa_balance, 0) ELSE 0 END) as smc_casa,
            "
            : "
                0 as total_casa,
                0 as briguna_casa,
                0 as kpr_casa,
                0 as mikro_casa,
                0 as smc_casa,
            ";

        $summaryRows = $joined
            ->selectRaw("
                loan_per_cif.source_branch_key,
                loan_per_cif.uker_key,
                SUM(loan_per_cif.loan_balance) as total_os,
                SUM(CASE WHEN loan_per_cif.has_briguna = 1 THEN loan_per_cif.loan_balance ELSE 0 END) as briguna_os,
                SUM(CASE WHEN loan_per_cif.has_kpr = 1 THEN loan_per_cif.loan_balance ELSE 0 END) as kpr_os,
                SUM(CASE WHEN loan_per_cif.has_mikro = 1 THEN loan_per_cif.loan_balance ELSE 0 END) as mikro_os,
                SUM(CASE WHEN loan_per_cif.has_smc = 1 THEN loan_per_cif.loan_balance ELSE 0 END) as smc_os,
                {$casaSelectSql}
                COUNT(*) as source_row_count
            ")
            ->groupBy('loan_per_cif.source_branch_key', 'loan_per_cif.uker_key')
            ->get();

        $rows = [];

        foreach ($summaryRows as $row) {
            $sourceBranchKey = strtoupper(trim((string) ($row->source_branch_key ?? '')));
            $ukerKey = strtoupper(trim((string) ($row->uker_key ?? '')));

            if ($sourceBranchKey === '' || $ukerKey === '') {
                continue;
            }

            foreach (self::SEGMENTS as $segmentKey) {
                $rows[] = [
                    'uniqueid_rcdus' => $this->makeRasioUkerSnapshotId($loanPeriod, $sourceBranchKey, $ukerKey, $segmentKey),
                    'loan_period' => $loanPeriod,
                    'casa_period' => $casaDate,
                    'source_branch_key' => $sourceBranchKey,
                    'uker_key' => $ukerKey,
                    'uker_label' => $ukerKey,
                    'segment_key' => $segmentKey,
                    'os_amount' => (float) ($row->{$segmentKey . '_os'} ?? 0),
                    'casa_amount' => (float) ($row->{$segmentKey . '_casa'} ?? 0),
                    'source_row_count' => (int) ($row->source_row_count ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        return $rows;
    }

    private function resolveDashboardPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('daily_loan_dinamis', 'periode', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        $comparison = $this->resolveAvailablePeriod(
            'daily_loan_dinamis',
            'periode',
            Carbon::parse($selected)->subMonthNoOverflow()->endOfMonth()->toDateString()
        );

        return array_values(array_unique(array_filter([$selected, $comparison])));
    }

    private function resolveChartPeriodikPeriods(?string $period): array
    {
        if ($period) {
            $normalized = $this->normalizePeriodInput($period);

            return $normalized ? [$normalized] : [];
        }

        if (!Schema::hasTable('daily_loan_dinamis')) {
            return [];
        }

        return DB::table('daily_loan_dinamis')
            ->whereNotNull('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->values()
            ->all();
    }

    private function resolveRasioPeriods(?string $period): array
    {
        return $this->resolveDashboardPeriods($period);
    }

    private function resolveDormantPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('simpanan_multipn', 'posisi', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        $currentDate = Carbon::parse($selected);

        $mtd = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()
        );
        $ytd = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subYearNoOverflow()->endOfYear()->toDateString()
        );
        $yoy = $this->resolveAvailablePeriod(
            'simpanan_multipn',
            'posisi',
            $currentDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString()
        );

        return array_values(array_unique(array_filter([$selected, $mtd, $ytd, $yoy])));
    }

    private function resolveSimpananDashboardPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('simpanan_multipn', 'posisi', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        try {
            return DB::table('simpanan_multipn')
                ->select('posisi')
                ->whereNotNull('posisi')
                ->distinct()
                ->orderByDesc('posisi')
                ->pluck('posisi')
                ->map(fn ($value) => Carbon::parse($value)->toDateString())
                ->values()
                ->all();
        } catch (Throwable) {
            return [$selected];
        }
    }

    private function resolveNewPayrollPeriods(?string $period): array
    {
        $selected = $this->resolveAvailablePeriod('performance_pis_per_produk', 'posisi', $period);

        if (!$selected) {
            return [];
        }

        if ($period) {
            return [$selected];
        }

        $comparison = $this->resolveAvailablePeriod(
            'performance_pis_per_produk',
            'posisi',
            Carbon::parse($selected)->subMonthNoOverflow()->endOfMonth()->toDateString()
        );

        return array_values(array_unique(array_filter([$selected, $comparison])));
    }

    private function resolvePerformanceRmPeriods(?string $period): array
    {
        $sourcePeriods = $this->fetchSourcePeriods('daily_loan_dinamis', 'periode');
        if ($sourcePeriods === []) {
            $normalized = $this->normalizePeriodInput($period);

            return $normalized !== null ? [$normalized] : [];
        }

        if ($period !== null) {
            $requested = $this->normalizePeriodInput($period);
            if ($requested === null) {
                return [];
            }

            if (in_array($requested, $sourcePeriods, true)) {
                return [$requested];
            }

            $closest = $this->resolveClosestSourcePeriod($sourcePeriods, $requested);

            return $closest !== null ? [$closest] : [];
        }

        return $this->latestPerformanceRmPeriodPerMonth($sourcePeriods);
    }

    /**
     * @param array<int, string> $periods
     * @return array<int, string>
     */
    private function latestPerformanceRmPeriodPerMonth(array $periods): array
    {
        sort($periods);

        $latestByMonth = [];
        foreach ($periods as $period) {
            $normalized = $this->normalizePeriodInput($period);
            if ($normalized === null) {
                continue;
            }

            $latestByMonth[substr($normalized, 0, 7)] = $normalized;
        }

        return array_values($latestByMonth);
    }

    private function resolveAvailablePeriod(string $table, string $column, ?string $targetDate): ?string
    {
        $normalizedTargetDate = $this->normalizePeriodInput($targetDate);
        $cacheKey = $table . '|' . $column . '|' . ($normalizedTargetDate ?? '__null__');
        if (array_key_exists($cacheKey, $this->availablePeriodCache)) {
            return $this->availablePeriodCache[$cacheKey];
        }

        try {
            $query = DB::table($table);

            if ($normalizedTargetDate) {
                $query->where($column, '<=', $normalizedTargetDate);
            }

            return $this->availablePeriodCache[$cacheKey] = $query->max($column);
        } catch (Throwable) {
            $this->availablePeriodCache[$cacheKey] = null;
            return null;
        }
    }

    private function resolveAvailableCasaPeriod(string $targetDate): ?string
    {
        $normalizedTargetDate = $this->normalizePeriodInput($targetDate);
        if ($normalizedTargetDate === null) {
            return null;
        }

        if (array_key_exists($normalizedTargetDate, $this->availableCasaPeriodCache)) {
            return $this->availableCasaPeriodCache[$normalizedTargetDate];
        }

        try {
            $exists = DB::table('simpanan_multipn')
                ->where('posisi', $normalizedTargetDate)
                ->exists();

            return $this->availableCasaPeriodCache[$normalizedTargetDate] = $exists ? $normalizedTargetDate : null;
        } catch (Throwable) {
            $this->availableCasaPeriodCache[$normalizedTargetDate] = null;
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function fetchSourcePeriods(string $table, string $column): array
    {
        try {
            return DB::table($table)
                ->whereNotNull($column)
                ->distinct()
                ->orderBy($column)
                ->pluck($column)
                ->map(fn ($value) => $this->normalizePeriodInput((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, string> $periods
     */
    private function resolveClosestSourcePeriod(array $periods, string $targetPeriod): ?string
    {
        $closest = null;

        foreach ($periods as $period) {
            if ($period <= $targetPeriod) {
                $closest = $period;
                continue;
            }

            break;
        }

        return $closest ?? ($periods[0] ?? null);
    }

    private function normalizePeriodInput(?string $period): ?string
    {
        $trimmed = trim((string) $period);
        if ($trimmed === '') {
            return null;
        }

        $strictNormalized = StrictDateParser::normalize($trimmed);
        if ($strictNormalized !== null) {
            return $strictNormalized;
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanupSimpananDashboardSnapshotOrphans(array $validPeriods): void
    {
        if (!Schema::hasTable(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE) || !Schema::hasTable(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)) {
            return;
        }

        $summaryCleanup = DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE);
        $branchCleanup = DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE);

        if (!empty($validPeriods)) {
            $summaryCleanup->whereNotIn('snapshot_period', $validPeriods)->delete();
            $branchCleanup->whereNotIn('snapshot_period', $validPeriods)->delete();
            return;
        }

        $summaryCleanup->delete();
        $branchCleanup->delete();
    }

    private function shouldApplyCasaTypeFilter(string $casaDate): bool
    {
        if (array_key_exists($casaDate, $this->casaTypeFilterCache)) {
            return $this->casaTypeFilterCache[$casaDate];
        }

        try {
            return $this->casaTypeFilterCache[$casaDate] = DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                ->where(function ($query) {
                    $query->where('jenis_simpanan', 'like', 'GIRO%')
                        ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                })
                ->exists();
        } catch (Throwable) {
            $this->casaTypeFilterCache[$casaDate] = false;
            return false;
        }
    }

    private function buildDashboardBucketExpression(): string
    {
        return LoanQualityBucketMapper::buildSqlExpression('d');
    }

    private function buildLoanBranchExpression(string $loanBranchColumn): string
    {
        return "
            CASE
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%MADIUN%' THEN 'MADIUN'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%MAGETAN%' THEN 'MAGETAN'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%NGAWI%' THEN 'NGAWI'
                WHEN UPPER(TRIM(COALESCE({$loanBranchColumn}, ''))) LIKE '%PONOROGO%' THEN 'PONOROGO'
                ELSE ''
            END
        ";
    }

    private function buildBranchSortExpression(string $column): string
    {
        return "
            CASE {$column}
                WHEN 'MADIUN' THEN 1
                WHEN 'MAGETAN' THEN 2
                WHEN 'NGAWI' THEN 3
                WHEN 'PONOROGO' THEN 4
                ELSE 99
            END
        ";
    }

    private function buildDormantBranchLabelSqlExpression(string $column): string
    {
        return "
            CASE
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC MADIUN%' THEN 'KC Madiun'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC MAGETAN%' THEN 'KC Magetan'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC NGAWI%' THEN 'KC Ngawi'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC PONOROGO%' THEN 'KC Ponorogo'
                ELSE NULL
            END
        ";
    }

    private function buildDormantBranchFilterSqlExpression(string $column): string
    {
        if (isset($this->dormantBranchFilterExpressionCache[$column])) {
            return $this->dormantBranchFilterExpressionCache[$column];
        }

        return $this->dormantBranchFilterExpressionCache[$column] = "
            (
                UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC MADIUN%'
                OR UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC MAGETAN%'
                OR UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC NGAWI%'
                OR UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%KC PONOROGO%'
            )
        ";
    }

    private function buildSegmentFlagExpression(string $segmentColumn, string $productColumn, string $bucket): string
    {
        $segmen = "LOWER(TRIM(COALESCE({$segmentColumn}, '')))";
        $produk = "LOWER(TRIM(COALESCE({$productColumn}, '')))";

        return match ($bucket) {
            'briguna' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%briguna%' THEN 1
                    ELSE 0
                END
            ",
            'kpr' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%kpr%' THEN 1
                    ELSE 0
                END
            ",
            'mikro' => "
                CASE
                    WHEN {$segmen} LIKE '%micro%' OR {$segmen} LIKE '%mikro%' OR {$segmen} LIKE '%umkm%' THEN 1
                    ELSE 0
                END
            ",
            'smc' => "
                CASE
                    WHEN {$segmen} LIKE '%small%' OR {$segmen} LIKE '%smc%' OR {$segmen} LIKE '%menengah%' THEN 1
                    ELSE 0
                END
            ",
            default => '0',
        };
    }

    private function buildLoanIdentityExpression(string $fallbackColumn): string
    {
        return $this->buildRasioIdentityExpression("d.{$fallbackColumn}");
    }

    private function buildLoanNormalizedExpression(string $fallbackColumn, string $shadowColumn): string
    {
        $fallbackExpression = "UPPER(TRIM(d.{$fallbackColumn}))";

        if (Schema::hasColumn('daily_loan_dinamis', $shadowColumn)) {
            return "COALESCE(NULLIF(d.{$shadowColumn}, ''), {$fallbackExpression})";
        }

        return $fallbackExpression;
    }

    private function ensureRasioCasaTempTable(string $casaDate, string $casaKeyColumn, bool $applyCasaTypeFilter): void
    {
        if (
            $this->rasioCasaTempTablePeriod === $casaDate
            && $this->rasioCasaTempTableTypeFilter === $applyCasaTypeFilter
        ) {
            return;
        }

        $this->statementWithConcurrencyRetry('create rasio casa temp table', fn (): bool => DB::statement("
            CREATE TEMPORARY TABLE IF NOT EXISTS tmp_rasio_casa_balances (
                identity_key VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
                casa_balance DECIMAL(22, 2) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "));
        $this->statementWithConcurrencyRetry('truncate rasio casa temp table', fn (): bool => DB::statement('TRUNCATE TABLE tmp_rasio_casa_balances'));

        $casaFilterSql = $applyCasaTypeFilter
            ? "AND (s.jenis_simpanan LIKE 'GIRO%' OR s.jenis_simpanan LIKE 'TABUNGAN%')"
            : '';

        $this->statementWithConcurrencyRetry('populate rasio casa temp table', fn (): bool => DB::statement("
            INSERT INTO tmp_rasio_casa_balances (identity_key, casa_balance)
            SELECT
                {$this->buildRasioIdentityExpression("s.{$casaKeyColumn}")} as identity_key,
                SUM(COALESCE(s.saldo_idr, 0)) as casa_balance
            FROM simpanan_multipn s
            WHERE s.posisi = ?
                AND s.{$casaKeyColumn} IS NOT NULL
                AND s.{$casaKeyColumn} <> ''
                {$casaFilterSql}
            GROUP BY identity_key
        ", [$casaDate]));

        $this->rasioCasaTempTablePeriod = $casaDate;
        $this->rasioCasaTempTableTypeFilter = $applyCasaTypeFilter;
    }

    private function buildRasioIdentityExpression(string $column): string
    {
        return "CONVERT(UPPER(REPLACE(TRIM(COALESCE({$column}, '')), '''', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    private function resolveExistingColumn(string $table, array $candidates, string $fallback): string
    {
        $columns = $this->cachedColumnListing($table);
        $map = [];

        foreach ($columns as $column) {
            $map[strtolower($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $matched = $map[strtolower($candidate)] ?? null;
            if ($matched) {
                return $matched;
            }
        }

        return $fallback;
    }

    /**
     * @return array<int, string>
     */
    private function cachedColumnListing(string $table): array
    {
        if (!array_key_exists($table, $this->columnListingCache)) {
            $this->columnListingCache[$table] = Schema::getColumnListing($table);
        }

        return $this->columnListingCache[$table];
    }

    private function normalizePriorityBranchKey(?string $branch): string
    {
        $value = strtoupper(trim((string) $branch));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^KC[\.\s-]*/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        foreach (self::PRIORITY_BRANCHES as $branchName) {
            if (str_contains($value, $branchName)) {
                return $branchName;
            }
        }

        return '';
    }

    private function normalizeIdentityKey($value): string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = ltrim($normalized, "'");

        return trim($normalized);
    }

    private function buildIdentityVariants(string $identityKey): array
    {
        $variants = [$identityKey];
        if (!str_starts_with($identityKey, "'")) {
            $variants[] = "'" . $identityKey;
        }

        return array_values(array_unique($variants));
    }

    private function formatPriorityBranchLabel(string $branchKey): string
    {
        return 'KC ' . $this->normalizePriorityBranchKey($branchKey);
    }

    private function mapDormantBranchLabel(string $rawBranch): ?string
    {
        $upperBranch = strtoupper(trim($rawBranch));

        foreach (self::BRANCH_PATTERNS as $label => $needle) {
            if ($needle !== '' && str_contains($upperBranch, $needle)) {
                return $label;
            }
        }

        return null;
    }

    private function makeDashboardSnapshotId(string $period, string $sourceUniqueId, string $accountNumber): string
    {
        return md5(implode('|', [
            'dps',
            $period,
            trim($sourceUniqueId),
            trim($accountNumber),
        ]));
    }

    private function makeRasioSnapshotId(string $loanPeriod, string $branchKey, string $segmentKey): string
    {
        return md5(implode('|', [
            'rcds',
            $loanPeriod,
            trim($branchKey),
            trim($segmentKey),
        ]));
    }

    private function makeRasioUkerSnapshotId(string $loanPeriod, string $sourceBranchKey, string $ukerKey, string $segmentKey): string
    {
        return md5(implode('|', [
            'rcdus',
            $loanPeriod,
            trim($sourceBranchKey),
            trim($ukerKey),
            trim($segmentKey),
        ]));
    }

    private function buildJoinableIdentitySql(string $column): string
    {
        return "CONVERT(UPPER(REPLACE(TRIM(COALESCE({$column}, '')), \"'\", '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    private function makeDormantSnapshotId(string $period, string $rawBranch, string $unitKerja): string
    {
        return md5(implode('|', [
            'rds',
            $period,
            trim($rawBranch),
            trim($unitKerja),
        ]));
    }

    private function makeNewPayrollSnapshotId(string $snapshotPosisi, string $branch): string
    {
        return md5(implode('|', [
            'pnps',
            $snapshotPosisi,
            trim($branch),
        ]));
    }

    public function rebuildPerformanceRm(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolvePerformanceRmPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $this->reportProgress($progress, $snapshotPeriod, $index, $totalPeriods);
            $results[$snapshotPeriod] = $this->buildPerformanceRmPeriodSnapshot($snapshotPeriod, $force);
            $this->reportProgress($progress, $snapshotPeriod, $index + 1, $totalPeriods, (int) ($results[$snapshotPeriod] ?? 0));
        }

        return $results;
    }

    private function buildPerformanceRmPeriodSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasTable(self::PERFORMANCE_RM_SNAPSHOT_TABLE)) {
            return 0;
        }

        $force = $force
            || $this->purgeSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_SNAPSHOT_TABLE, $period)
            || $this->purgeSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE, $period);

        if (!$force) {
            $rowCount = DB::getDriverName() === 'mysql'
                ? $this->buildPerformanceRmPeriodSnapshotSqlFirstIncremental($period)
                : $this->buildPerformanceRmPeriodSnapshotPortableIncremental($period);

            $this->buildPerformanceRmCabangSnapshot($period, false);
            $this->logSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_SNAPSHOT_TABLE, $period);

            return $rowCount;
        }

        DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->delete();

        if (DB::getDriverName() === 'mysql') {
            $rowCount = $this->buildPerformanceRmPeriodSnapshotSqlFirst($period);
        } else {
            $rows = $this->computePerformanceRmRows($period);

            if (!empty($rows)) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)->insert($chunk);
                }
            }

            $rowCount = count($rows);
        }

        // Build cabang-level summary snapshots after RM data is loaded
        $this->buildPerformanceRmCabangSnapshot($period, $force);
        $this->logSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_SNAPSHOT_TABLE, $period);

        return $rowCount;
    }

    private function buildPerformanceRmPeriodSnapshotSqlFirstIncremental(string $period): int
    {
        $tempTable = 'tmp_performance_rm_snapshots_' . str_replace('.', '_', (string) microtime(true));

        DB::statement('CREATE TEMPORARY TABLE ' . $this->quoteIdentifier($tempTable) . ' LIKE ' . $this->quoteIdentifier(self::PERFORMANCE_RM_SNAPSHOT_TABLE));

        try {
            $this->buildPerformanceRmPeriodSnapshotSqlFirst($period, $tempTable);
            $this->syncPerformanceRmSnapshotRowsFromTemp($period, $tempTable);
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS ' . $this->quoteIdentifier($tempTable));
        }

        return (int) DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->count();
    }

    private function buildPerformanceRmPeriodSnapshotPortableIncremental(string $period): int
    {
        $rows = $this->computePerformanceRmRows($period);
        $validKeys = [];

        foreach ($rows as $row) {
            $key = $this->performanceRmSnapshotKey($row);
            $validKeys[$key] = true;

            DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)->updateOrInsert(
                $this->performanceRmSnapshotIdentity($row),
                array_diff_key($row, array_flip(['id', 'created_at']))
            );
        }

        $existingRows = DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->get($this->performanceRmSnapshotIdentityColumns());

        foreach ($existingRows as $existingRow) {
            $row = (array) $existingRow;
            if (isset($validKeys[$this->performanceRmSnapshotKey($row)])) {
                continue;
            }

            DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
                ->where($this->performanceRmSnapshotIdentity($row))
                ->delete();
        }

        return (int) DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->count();
    }

    private function buildPerformanceRmPeriodSnapshotSqlFirst(string $period, ?string $snapshotTable = null): int
    {
        $snapshotTable = $snapshotTable ?? self::PERFORMANCE_RM_SNAPSHOT_TABLE;
        $snapshotColumns = array_flip(Schema::getColumnListing(self::PERFORMANCE_RM_SNAPSHOT_TABLE));
        $latestSmpnPosisi = DB::table('simpanan_multipn')->max('posisi');

        foreach (self::KINERJA_RM_SEGMENT_RULES as $segment => $rules) {
            $normalizedRules = $this->normalizeKinerjaRmRules((array) $rules);
            if ($normalizedRules === []) {
                continue;
            }

            $this->insertPerformanceRmSegmentSnapshotSqlFirst(
                $period,
                $segment,
                $normalizedRules,
                $latestSmpnPosisi !== null ? (string) $latestSmpnPosisi : null,
                $snapshotColumns,
                $snapshotTable
            );
        }

        $this->updateConsumerPerformanceRmSurplusMetrics($period, $snapshotTable, $snapshotColumns);
        $this->updateSmallPerformanceRmQuadrantsSqlFirst($period, $snapshotTable);

        return (int) DB::table($snapshotTable)
            ->where('periode', $period)
            ->count();
    }

    /**
     * @param array<int, array{segment: string, products: array<int, string>, descriptions: array<int, string>}> $normalizedRules
     * @param array<string, int> $snapshotColumns
     */
    private function insertPerformanceRmSegmentSnapshotSqlFirst(
        string $period,
        string $segment,
        array $normalizedRules,
        ?string $latestSmpnPosisi,
        array $snapshotColumns,
        string $snapshotTable
    ): void {
        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $kurRitelDescriptionSql = $this->buildKinerjaRmNormalizedSql('d.description');
        $kurRitelDescriptionToken = $this->normalizeKinerjaRmToken('Kredit Mikro - KUR Ritel 2015');
        $rawRealisasiDateColumn = 'd.' . $this->resolvePerformanceRmRealisasiDateColumn();
        $rawCurrentCifRealisasiDateColumn = 'd2.' . $this->resolvePerformanceRmRealisasiDateColumn();
        $realisasiDateColumn = $this->performanceRmEffectiveRealisasiDateSql($rawRealisasiDateColumn, 'd.periode');
        $currentCifRealisasiDateColumn = $this->performanceRmEffectiveRealisasiDateSql($rawCurrentCifRealisasiDateColumn, 'd2.periode');
        $consumerPreviousPeriod = $segment === 'CONSUMER'
            ? $this->resolvePreviousMonthPerformanceRmPeriod($period)
            : null;
        $hasConsumerSurplusBase = $segment === 'CONSUMER' && $consumerPreviousPeriod !== null;
        $consumerPreviousLookupOrderSql = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'd.uniqueid_namareport'
            : 'UPPER(TRIM(d.nomor_rekening1))';
        [$ruleSql, $ruleBindings] = $this->buildKinerjaRmRuleSql($normalizedRules, 'd');
        [$currentCifRuleSql, $currentCifRuleBindings] = $this->buildKinerjaRmRuleSql($normalizedRules, 'd2');
        $canonicalProductSql = $this->buildKinerjaRmCanonicalProductSql($segment, 'd.produk_kinerja');
        $consumerSurplusJoinSql = $hasConsumerSurplusBase
            ? "
            LEFT JOIN (
                SELECT
                    current_groups.cabang,
                    current_groups.unit,
                    current_groups.branch_code,
                    current_groups.rm,
                    current_groups.produk,
                    SUM(current_groups.debitur) as surplus_deb,
                    SUM(current_groups.current_plafon - COALESCE(previous_closed.previous_os, 0)) as surplus_os
                FROM (
                    SELECT
                        current_base.cabang,
                        current_base.unit,
                        current_base.branch_code,
                        current_base.rm,
                        current_base.produk,
                        current_base.clean_cif,
                        COUNT(DISTINCT current_base.account_key) as debitur,
                        SUM(current_base.current_plafon) as current_plafon
                    FROM (
                        SELECT
                            COALESCE(d.cabang_normalized, '') as cabang,
                            COALESCE(d.unit_normalized, '') as unit,
                            COALESCE(d.branch_normalized, '') as branch_code,
                            COALESCE(d.rm_normalized, '') as rm,
                            {$canonicalProductSql} as produk,
                            UPPER(TRIM(d.nomor_rekening1)) as account_key,
                            UPPER(TRIM(d.cifno)) as clean_cif,
                            COALESCE(d.plafon, 0) as current_plafon
                        FROM daily_loan_dinamis d
                        WHERE d.periode = ?
                            AND ({$ruleSql})
                            AND d.pn_pengelola1 IS NOT NULL
                            AND d.pn_pengelola1 <> ''
                            AND d.nomor_rekening1 IS NOT NULL
                            AND d.nomor_rekening1 <> ''
                            AND d.cifno IS NOT NULL
                            AND d.cifno <> ''
                            AND {$realisasiDateColumn} BETWEEN ? AND ?
                    ) current_base
                    GROUP BY
                        current_base.cabang,
                        current_base.unit,
                        current_base.branch_code,
                        current_base.rm,
                        current_base.produk,
                        current_base.clean_cif
                ) current_groups
                LEFT JOIN (
                    SELECT
                        previous_base.clean_cif,
                        previous_base.previous_os
                    FROM (
                        SELECT
                            UPPER(TRIM(d.cifno)) as clean_cif,
                            UPPER(TRIM(d.nomor_rekening1)) as account_key,
                            COALESCE(d.baki_debet1, 0) as previous_os,
                            ROW_NUMBER() OVER (
                                PARTITION BY UPPER(TRIM(d.cifno))
                                ORDER BY {$consumerPreviousLookupOrderSql}
                            ) as row_num
                        FROM daily_loan_dinamis d
                        WHERE d.periode = ?
                            AND UPPER(TRIM(d.cifno)) IN (
                                SELECT DISTINCT UPPER(TRIM(d2.cifno))
                                FROM daily_loan_dinamis d2
                                WHERE d2.periode = ?
                                    AND ({$currentCifRuleSql})
                                    AND d2.pn_pengelola1 IS NOT NULL
                                    AND d2.pn_pengelola1 <> ''
                                    AND d2.nomor_rekening1 IS NOT NULL
                                    AND d2.nomor_rekening1 <> ''
                                    AND d2.cifno IS NOT NULL
                                    AND d2.cifno <> ''
                                    AND {$currentCifRealisasiDateColumn} BETWEEN ? AND ?
                            )
                            AND d.nomor_rekening1 IS NOT NULL
                            AND d.nomor_rekening1 <> ''
                            AND d.cifno IS NOT NULL
                            AND d.cifno <> ''
                    ) previous_base
                    LEFT JOIN (
                        SELECT DISTINCT UPPER(TRIM(nomor_rekening1)) as account_key
                        FROM daily_loan_dinamis
                        WHERE periode = ?
                            AND nomor_rekening1 IS NOT NULL
                            AND nomor_rekening1 <> ''
                    ) current_accounts ON current_accounts.account_key = previous_base.account_key
                    WHERE previous_base.row_num = 1
                        AND current_accounts.account_key IS NULL
                ) previous_closed ON previous_closed.clean_cif = current_groups.clean_cif
                GROUP BY
                    current_groups.cabang,
                    current_groups.unit,
                    current_groups.branch_code,
                    current_groups.rm,
                    current_groups.produk
            ) consumer_surplus ON consumer_surplus.cabang = COALESCE(d.cabang_normalized, '')
                AND consumer_surplus.unit = COALESCE(d.unit_normalized, '')
                AND consumer_surplus.branch_code = COALESCE(d.branch_normalized, '')
                AND consumer_surplus.rm = COALESCE(d.rm_normalized, '')
                AND consumer_surplus.produk = {$canonicalProductSql}
            "
            : '';
        $consumerSurplusJoinSql = '';
        $hasConsumerSurplusBase = false;
        $weekRanges = [
            'w1' => [$periodDate->copy()->startOfMonth(), $periodDate->copy()->startOfMonth()->addDays(6)],
            'w2' => [$periodDate->copy()->startOfMonth()->addDays(7), $periodDate->copy()->startOfMonth()->addDays(13)],
            'w3' => [$periodDate->copy()->startOfMonth()->addDays(14), $periodDate->copy()->startOfMonth()->addDays(20)],
            'w4' => [$periodDate->copy()->startOfMonth()->addDays(21), $periodDate->copy()],
        ];
        $weekRanges = array_map(
            fn (array $range): array => [
                $range[0]->toDateString(),
                $range[1]->greaterThan($periodDate) ? $periodDate->toDateString() : $range[1]->toDateString(),
            ],
            $weekRanges
        );

        $groupColumns = [
            "COALESCE(d.cabang_normalized, '')",
            "COALESCE(d.unit_normalized, '')",
            "COALESCE(d.branch_normalized, '')",
            "COALESCE(d.rm_normalized, '')",
            $canonicalProductSql,
        ];
        $groupBySql = implode(', ', $groupColumns);

        $columns = [
            'periode',
            'cabang',
            'unit',
        ];
        $selects = [
            '? as periode',
            "COALESCE(d.cabang_normalized, '') as cabang",
            "COALESCE(d.unit_normalized, '') as unit",
        ];
        $bindings = [$period];

        if (isset($snapshotColumns['branch_code'])) {
            $columns[] = 'branch_code';
            $selects[] = "COALESCE(d.branch_normalized, '') as branch_code";
        }

        array_push(
            $columns,
            'rm',
            'segmen',
            'produk',
            'plafon',
            'loan_os',
            'lancar_os',
            'sml_os',
            'npl_os',
            'restruk_os',
            'total_deb'
        );
        array_push(
            $selects,
            "COALESCE(d.rm_normalized, '') as rm",
            '? as segmen',
            "{$canonicalProductSql} as produk",
            'SUM(COALESCE(d.plafon, 0)) as plafon',
            "SUM(CASE WHEN d.segmen_kinerja = 'MICRO' AND d.produk_kinerja IN ('KURMIKRO', 'KURKECIL') AND {$kurRitelDescriptionSql} = ? THEN COALESCE(d.plafon, 0) ELSE COALESCE(d.baki_debet1, 0) END) as loan_os",
            'SUM(CASE WHEN d.kolek = 1 THEN COALESCE(d.baki_debet1, 0) ELSE 0 END) as lancar_os',
            'SUM(CASE WHEN d.kolek = 2 THEN COALESCE(d.baki_debet1, 0) ELSE 0 END) as sml_os',
            'SUM(CASE WHEN d.kolek > 2 THEN COALESCE(d.baki_debet1, 0) ELSE 0 END) as npl_os',
            "SUM(CASE WHEN d.kolek = 1 AND COALESCE(d.flag_restruk, '') = 'Y' THEN COALESCE(d.baki_debet1, 0) ELSE 0 END) as restruk_os",
            'COUNT(DISTINCT d.nomor_rekening1) as total_deb'
        );
        array_push($bindings, $segment, $kurRitelDescriptionToken);

        $realisasiAmountSql = 'COALESCE(d.plafon, 0)';

        $metricSelects = [
            'lancar_deb' => 'COUNT(DISTINCT CASE WHEN d.kolek = 1 THEN d.nomor_rekening1 END) as lancar_deb',
            'sml_deb' => 'COUNT(DISTINCT CASE WHEN d.kolek = 2 THEN d.nomor_rekening1 END) as sml_deb',
            'npl_deb' => 'COUNT(DISTINCT CASE WHEN d.kolek > 2 THEN d.nomor_rekening1 END) as npl_deb',
            'realisasi_deb' => $segment === 'CONSUMER'
                ? ($hasConsumerSurplusBase ? 'COALESCE(MAX(consumer_surplus.surplus_deb), 0) as realisasi_deb' : '0 as realisasi_deb')
                : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN d.nomor_rekening1 END) as realisasi_deb",
            'realisasi_os' => $segment === 'CONSUMER'
                ? ($hasConsumerSurplusBase ? 'COALESCE(MAX(consumer_surplus.surplus_os), 0) as realisasi_os' : '0 as realisasi_os')
                : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as realisasi_os",
            'w1_realisasi_deb' => $segment === 'CONSUMER' ? '0 as w1_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN d.nomor_rekening1 END) as w1_realisasi_deb",
            'w1_realisasi_os' => $segment === 'CONSUMER' ? '0 as w1_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w1_realisasi_os",
            'w2_realisasi_deb' => $segment === 'CONSUMER' ? '0 as w2_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN d.nomor_rekening1 END) as w2_realisasi_deb",
            'w2_realisasi_os' => $segment === 'CONSUMER' ? '0 as w2_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w2_realisasi_os",
            'w3_realisasi_deb' => $segment === 'CONSUMER' ? '0 as w3_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN d.nomor_rekening1 END) as w3_realisasi_deb",
            'w3_realisasi_os' => $segment === 'CONSUMER' ? '0 as w3_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w3_realisasi_os",
            'w4_realisasi_deb' => $segment === 'CONSUMER' ? '0 as w4_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN d.nomor_rekening1 END) as w4_realisasi_deb",
            'w4_realisasi_os' => $segment === 'CONSUMER' ? '0 as w4_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w4_realisasi_os",
            'lt_250_realisasi_deb' => $segment === 'CONSUMER' ? '0 as lt_250_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(d.plafon, 0) < 250000000 THEN d.nomor_rekening1 END) as lt_250_realisasi_deb",
            'lt_250_realisasi_os' => $segment === 'CONSUMER' ? '0 as lt_250_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(d.plafon, 0) < 250000000 THEN {$realisasiAmountSql} ELSE 0 END) as lt_250_realisasi_os",
            'gt_250_realisasi_deb' => $segment === 'CONSUMER' ? '0 as gt_250_realisasi_deb' : "COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(d.plafon, 0) > 250000000 THEN d.nomor_rekening1 END) as gt_250_realisasi_deb",
            'gt_250_realisasi_os' => $segment === 'CONSUMER' ? '0 as gt_250_realisasi_os' : "SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(d.plafon, 0) > 250000000 THEN {$realisasiAmountSql} ELSE 0 END) as gt_250_realisasi_os",
        ];
        $metricBindings = [
            'realisasi_deb' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
            'realisasi_os' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
            'w1_realisasi_deb' => $segment === 'CONSUMER' ? [] : $weekRanges['w1'],
            'w1_realisasi_os' => $segment === 'CONSUMER' ? [] : $weekRanges['w1'],
            'w2_realisasi_deb' => $segment === 'CONSUMER' ? [] : $weekRanges['w2'],
            'w2_realisasi_os' => $segment === 'CONSUMER' ? [] : $weekRanges['w2'],
            'w3_realisasi_deb' => $segment === 'CONSUMER' ? [] : $weekRanges['w3'],
            'w3_realisasi_os' => $segment === 'CONSUMER' ? [] : $weekRanges['w3'],
            'w4_realisasi_deb' => $segment === 'CONSUMER' ? [] : $weekRanges['w4'],
            'w4_realisasi_os' => $segment === 'CONSUMER' ? [] : $weekRanges['w4'],
            'lt_250_realisasi_deb' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
            'lt_250_realisasi_os' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
            'gt_250_realisasi_deb' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
            'gt_250_realisasi_os' => $segment === 'CONSUMER' ? [] : [$periodStart, $period],
        ];

        foreach ($metricSelects as $column => $selectSql) {
            if (!isset($snapshotColumns[$column])) {
                continue;
            }

            $columns[] = $column;
            $selects[] = $selectSql;
            array_push($bindings, ...($metricBindings[$column] ?? []));
        }

        array_push($columns, 'total_deposit', 'quadrant', 'created_at', 'updated_at');
        array_push(
            $selects,
            $latestSmpnPosisi !== null ? 'COALESCE(MAX(dep.total_deposit), 0) as total_deposit' : '0 as total_deposit',
            'NULL as quadrant',
            'NOW() as created_at',
            'NOW() as updated_at'
        );

        $depositJoinSql = $this->buildPerformanceRmDepositJoinSql($canonicalProductSql, $ruleSql, $latestSmpnPosisi);
        $insertColumnsSql = implode(', ', $columns);
        $selectSql = implode(",\n                ", $selects);

        $sql = "
            INSERT INTO " . $this->quoteIdentifier($snapshotTable) . " ({$insertColumnsSql})
            SELECT
                {$selectSql}
            FROM daily_loan_dinamis d
            {$consumerSurplusJoinSql}
            {$depositJoinSql}
            WHERE d.periode = ?
                AND ({$ruleSql})
                AND d.pn_pengelola1 IS NOT NULL
                AND d.pn_pengelola1 <> ''
            GROUP BY {$groupBySql}
        ";

        $depositBindings = $latestSmpnPosisi !== null
            ? [$period, ...$ruleBindings, $latestSmpnPosisi]
            : [];

        $bindings = array_merge(
            $bindings,
            $hasConsumerSurplusBase ? [
                $period,
                ...$ruleBindings,
                $periodStart,
                $period,
                $consumerPreviousPeriod,
                $period,
                ...$currentCifRuleBindings,
                $periodStart,
                $period,
                $period,
            ] : [],
            $depositBindings,
            [$period],
            $ruleBindings
        );

        $this->statementWithConcurrencyRetry(
            'performance rm segment upsert',
            fn (): bool => DB::statement($sql, $bindings)
        );
    }

    private function buildPerformanceRmDepositJoinSql(string $canonicalProductSql, string $ruleSql, ?string $latestSmpnPosisi): string
    {
        if ($latestSmpnPosisi === null) {
            return '';
        }

        return "
            LEFT JOIN (
                SELECT
                    cif_groups.cabang,
                    cif_groups.unit,
                    cif_groups.branch_code,
                    cif_groups.rm,
                    cif_groups.produk,
                    SUM(COALESCE(deposits.saldo_idr, 0)) as total_deposit
                FROM (
                    SELECT DISTINCT
                        COALESCE(d.cabang_normalized, '') as cabang,
                        COALESCE(d.unit_normalized, '') as unit,
                        COALESCE(d.branch_normalized, '') as branch_code,
                        COALESCE(d.rm_normalized, '') as rm,
                        {$canonicalProductSql} as produk,
                        UPPER(TRIM(d.cifno)) as clean_cif
                    FROM daily_loan_dinamis d
                    WHERE d.periode = ?
                        AND ({$ruleSql})
                        AND d.pn_pengelola1 IS NOT NULL
                        AND d.pn_pengelola1 <> ''
                        AND d.cifno IS NOT NULL
                        AND d.cifno <> ''
                ) cif_groups
                LEFT JOIN simpanan_multipn deposits FORCE INDEX (idx_smp_posisi_cif_covering)
                    ON deposits.posisi = ?
                    AND deposits.CIFNO = cif_groups.clean_cif
                GROUP BY cif_groups.cabang, cif_groups.unit, cif_groups.branch_code, cif_groups.rm, cif_groups.produk
            ) dep ON dep.cabang = COALESCE(d.cabang_normalized, '')
                AND dep.unit = COALESCE(d.unit_normalized, '')
                AND dep.branch_code = COALESCE(d.branch_normalized, '')
                AND dep.rm = COALESCE(d.rm_normalized, '')
                AND dep.produk = {$canonicalProductSql}
        ";
    }

    /**
     * @param array<string, int> $snapshotColumns
     */
    private function updateConsumerPerformanceRmSurplusMetrics(string $period, string $snapshotTable, array $snapshotColumns): void
    {
        if (!isset($snapshotColumns['realisasi_deb'], $snapshotColumns['realisasi_os'])) {
            return;
        }

        DB::table($snapshotTable)
            ->where('periode', $period)
            ->where('segmen', 'CONSUMER')
            ->update([
                'realisasi_deb' => 0,
                'realisasi_os' => 0,
                'updated_at' => now(),
            ]);

        $previousPeriod = $this->resolvePreviousMonthPerformanceRmPeriod($period);
        if ($previousPeriod === null) {
            return;
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $realisasiDateColumn = $this->performanceRmEffectiveRealisasiDateSql(
            $this->resolvePerformanceRmRealisasiDateColumn(),
            'periode'
        );

        $currentRows = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->whereRaw("{$realisasiDateColumn} BETWEEN ? AND ?", [$periodStart, $period])
            ->selectRaw("COALESCE(cabang_normalized, '') as cabang")
            ->selectRaw("COALESCE(unit_normalized, '') as unit")
            ->selectRaw("COALESCE(branch_normalized, '') as branch_code")
            ->selectRaw("COALESCE(rm_normalized, '') as rm")
            ->selectRaw("CASE WHEN produk_kinerja = 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' ELSE produk_kinerja END as produk")
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->selectRaw("UPPER(TRIM(cifno)) as clean_cif")
            ->selectRaw('COALESCE(plafon, 0) as current_plafon')
            ->get();

        if ($currentRows->isEmpty()) {
            return;
        }

        $currentCifs = $currentRows
            ->pluck('clean_cif')
            ->map(fn ($value): string => (string) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $currentAccountKeys = [];
        foreach (array_chunk($currentCifs, 500) as $cifChunk) {
            DB::table('daily_loan_dinamis')
                ->where('periode', $period)
                ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifChunk)
                ->whereNotNull('nomor_rekening1')
                ->where('nomor_rekening1', '<>', '')
                ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
                ->distinct()
                ->orderBy('account_key')
                ->chunk(1000, function ($rows) use (&$currentAccountKeys): void {
                    foreach ($rows as $row) {
                        $accountKey = (string) ($row->account_key ?? '');
                        if ($accountKey !== '') {
                            $currentAccountKeys[$accountKey] = true;
                        }
                    }
                });
        }

        $previousLookupOrderColumn = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'uniqueid_namareport'
            : 'nomor_rekening1';
        $previousOsByCif = [];
        foreach (array_chunk($currentCifs, 500) as $cifChunk) {
            DB::table('daily_loan_dinamis')
                ->where('periode', $previousPeriod)
                ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifChunk)
                ->where('segmen_kinerja', 'CONSUMER')
                ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
                ->whereNotNull('nomor_rekening1')
                ->where('nomor_rekening1', '<>', '')
                ->whereNotNull('cifno')
                ->where('cifno', '<>', '')
                ->selectRaw("UPPER(TRIM(cifno)) as clean_cif")
                ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
                ->selectRaw('COALESCE(baki_debet1, 0) as previous_os')
                ->orderBy($previousLookupOrderColumn)
                ->chunk(1000, function ($rows) use (&$previousOsByCif, $currentAccountKeys): void {
                    foreach ($rows as $row) {
                        $cleanCif = (string) ($row->clean_cif ?? '');
                        $accountKey = (string) ($row->account_key ?? '');
                        if ($cleanCif === '' || isset($currentAccountKeys[$accountKey]) || array_key_exists($cleanCif, $previousOsByCif)) {
                            continue;
                        }

                        $previousOsByCif[$cleanCif] = (float) ($row->previous_os ?? 0);
                    }
                });
        }

        $currentMetricsByCif = [];
        foreach ($currentRows as $row) {
            $groupKey = implode('|', [
                (string) ($row->cabang ?? ''),
                (string) ($row->unit ?? ''),
                (string) ($row->branch_code ?? ''),
                (string) ($row->rm ?? ''),
                (string) ($row->produk ?? ''),
            ]);
            $cleanCif = (string) ($row->clean_cif ?? '');
            $metricKey = $groupKey . '|' . $cleanCif;

            $currentMetricsByCif[$metricKey] ??= [
                'group_key' => $groupKey,
                'cabang' => (string) ($row->cabang ?? ''),
                'unit' => (string) ($row->unit ?? ''),
                'branch_code' => (string) ($row->branch_code ?? ''),
                'rm' => (string) ($row->rm ?? ''),
                'produk' => (string) ($row->produk ?? ''),
                'clean_cif' => $cleanCif,
                'accounts' => [],
                'current_plafon' => 0.0,
            ];

            $accountKey = (string) ($row->account_key ?? '');
            if ($accountKey !== '') {
                $currentMetricsByCif[$metricKey]['accounts'][$accountKey] = true;
            }

            $currentMetricsByCif[$metricKey]['current_plafon'] += (float) ($row->current_plafon ?? 0);
        }

        $metricsByGroup = [];
        foreach ($currentMetricsByCif as $metric) {
            $groupKey = (string) $metric['group_key'];
            $metricsByGroup[$groupKey] ??= [
                'cabang' => $metric['cabang'],
                'unit' => $metric['unit'],
                'branch_code' => $metric['branch_code'],
                'rm' => $metric['rm'],
                'produk' => $metric['produk'],
                'accounts' => [],
                'realisasi_os' => 0.0,
            ];

            foreach ($metric['accounts'] as $accountKey => $_) {
                $metricsByGroup[$groupKey]['accounts'][$accountKey] = true;
            }

            $previousOs = (float) ($previousOsByCif[(string) $metric['clean_cif']] ?? 0);
            $metricsByGroup[$groupKey]['realisasi_os'] += (float) $metric['current_plafon'] - $previousOs;
        }

        foreach ($metricsByGroup as $metric) {
            $query = DB::table($snapshotTable)
                ->where('periode', $period)
                ->where('cabang', $metric['cabang'])
                ->where('unit', $metric['unit'])
                ->where('rm', $metric['rm'])
                ->where('segmen', 'CONSUMER')
                ->where('produk', $metric['produk']);

            if (isset($snapshotColumns['branch_code'])) {
                $query->where('branch_code', $metric['branch_code']);
            }

            $query->update([
                'realisasi_deb' => count($metric['accounts']),
                'realisasi_os' => $metric['realisasi_os'],
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param array<int, array{segment: string, products: array<int, string>, descriptions: array<int, string>}> $normalizedRules
     * @return array{0:string, 1:array<int, mixed>}
     */
    private function buildKinerjaRmRuleSql(array $normalizedRules, string $alias = 'd'): array
    {
        $parts = [];
        $bindings = [];

        foreach ($normalizedRules as $rule) {
            $productPlaceholders = implode(', ', array_fill(0, count($rule['products']), '?'));
            $part = "{$alias}.segmen_kinerja = ? AND {$alias}.produk_kinerja IN ({$productPlaceholders})";
            $partBindings = [$rule['segment'], ...$rule['products']];

            if (!empty($rule['descriptions'])) {
                $descriptionSql = $this->buildKinerjaRmNormalizedSql("{$alias}.description");
                $descriptionPlaceholders = implode(', ', array_fill(0, count($rule['descriptions']), '?'));
                $part .= " AND {$descriptionSql} IN ({$descriptionPlaceholders})";
                array_push($partBindings, ...$rule['descriptions']);
            }

            $parts[] = "({$part})";
            array_push($bindings, ...$partBindings);
        }

        return [implode(' OR ', $parts), $bindings];
    }

    private function buildKinerjaRmCanonicalProductSql(string $segment, string $column): string
    {
        return match (strtoupper(trim($segment))) {
            'CONSUMER' => "CASE {$column} WHEN 'BRIGUNAKONSUMER' THEN 'BRIGUNA-KONSUMER' WHEN 'KPR' THEN 'KPR' ELSE UPPER(TRIM(COALESCE({$column}, ''))) END",
            'SMALL' => "CASE {$column} WHEN 'COMMERCIAL' THEN 'SMALL' WHEN 'CASHCALL' THEN 'SMALL' WHEN 'CASHCOLLATERAL' THEN 'SMALL' WHEN 'CASHCOLL' THEN 'SMALL' WHEN 'SMALL' THEN 'SMALL' ELSE UPPER(TRIM(COALESCE({$column}, ''))) END",
            'MICRO' => "CASE {$column} WHEN 'BRIGUNAMIKRO' THEN 'BRIGUNA-MIKRO' WHEN 'KUPEDES' THEN 'KUPEDES' WHEN 'KURMIKRO' THEN 'KUR-MIKRO' WHEN 'KURKECIL' THEN 'KUR-MIKRO' WHEN 'CASHCOLLATERAL' THEN 'CASHCOLLATERAL' WHEN 'CASHCOLL' THEN 'CASHCOLLATERAL' WHEN 'KPR' THEN 'KPR' WHEN 'KURSMALL' THEN 'KUR-SMALL' ELSE UPPER(TRIM(COALESCE({$column}, ''))) END",
            default => "UPPER(TRIM(COALESCE({$column}, '')))",
        };
    }

    private function updateSmallPerformanceRmQuadrantsSqlFirst(string $period, ?string $snapshotTable = null): void
    {
        if (!Schema::hasColumn(self::PERFORMANCE_RM_SNAPSHOT_TABLE, 'quadrant')) {
            return;
        }

        $targetTable = $snapshotTable ?? self::PERFORMANCE_RM_SNAPSHOT_TABLE;
        $rmKeys = DB::table($targetTable)
            ->where('periode', $period)
            ->where('segmen', 'SMALL')
            ->distinct()
            ->pluck('rm')
            ->map(fn ($rm): string => (string) $rm)
            ->all();

        if ($rmKeys === []) {
            return;
        }

        $closedPeriods = $this->resolveSmallClosedMonthlySnapshotPeriods($period);
        if ($closedPeriods === []) {
            DB::table($targetTable)
                ->where('periode', $period)
                ->where('segmen', 'SMALL')
                ->update(['quadrant' => null]);

            return;
        }

        $realisasiByRm = array_fill_keys($rmKeys, 0.0);
        $historicalPeriods = array_values(array_filter($closedPeriods, fn (string $closedPeriod): bool => $closedPeriod !== $period));

        if ($historicalPeriods !== []) {
            DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
                ->whereIn('rm', $rmKeys)
                ->where('segmen', 'SMALL')
                ->whereIn('produk', ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL'])
                ->whereIn('periode', $historicalPeriods)
                ->selectRaw('rm, SUM(COALESCE(realisasi_os, 0)) as total_realisasi_os')
                ->groupBy('rm')
                ->get()
                ->each(function ($row) use (&$realisasiByRm): void {
                    $realisasiByRm[(string) $row->rm] = (float) $row->total_realisasi_os;
                });
        }

        if (in_array($period, $closedPeriods, true)) {
            DB::table($targetTable)
                ->whereIn('rm', $rmKeys)
                ->where('periode', $period)
                ->where('segmen', 'SMALL')
                ->selectRaw('rm, SUM(COALESCE(realisasi_os, 0)) as total_realisasi_os')
                ->groupBy('rm')
                ->get()
                ->each(function ($row) use (&$realisasiByRm): void {
                    $rm = (string) $row->rm;
                    $realisasiByRm[$rm] = ($realisasiByRm[$rm] ?? 0.0) + (float) $row->total_realisasi_os;
                });
        }

        $lastClosedPeriod = $closedPeriods[array_key_last($closedPeriods)];
        $larTable = $lastClosedPeriod === $period ? $targetTable : self::PERFORMANCE_RM_SNAPSHOT_TABLE;
        $larByRm = DB::table($larTable)
            ->whereIn('rm', $rmKeys)
            ->where('periode', $lastClosedPeriod)
            ->where('segmen', 'SMALL')
            ->selectRaw('rm, SUM(COALESCE(loan_os, 0)) as loan_os')
            ->selectRaw('SUM(COALESCE(restruk_os, 0) + COALESCE(sml_os, 0) + COALESCE(npl_os, 0)) as lar_value')
            ->groupBy('rm')
            ->get()
            ->keyBy(fn ($row): string => (string) $row->rm);
        $periodCount = count($closedPeriods);

        foreach ($rmKeys as $rm) {
            $lar = $larByRm->get($rm);
            $loanOs = (float) ($lar->loan_os ?? 0);
            $quadrant = null;

            if ($loanOs > 0) {
                $ratasOs = (float) ($realisasiByRm[$rm] ?? 0.0) / $periodCount;
                $larPct = ((float) ($lar->lar_value ?? 0.0) / $loanOs) * 100;
                $quadrant = $this->calculateSmallPerformanceRmQuadrant($ratasOs, $larPct);
            }

            DB::table($targetTable)
                ->where('periode', $period)
                ->where('segmen', 'SMALL')
                ->where('rm', $rm)
                ->update(['quadrant' => $quadrant]);
        }
    }

    private function resolveSmallClosedMonthlySnapshotPeriods(string $period): array
    {
        $selectedDate = Carbon::parse($period)->startOfDay();
        $closedThrough = $selectedDate->isLastOfMonth()
            ? $selectedDate
            : $selectedDate->copy()->startOfMonth()->subDay();

        if ($closedThrough->year !== $selectedDate->year) {
            return [];
        }

        $periods = DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('segmen', 'SMALL')
            ->whereBetween('periode', [
                $selectedDate->copy()->startOfYear()->toDateString(),
                $closedThrough->toDateString(),
            ])
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($snapshotPeriod): string => (string) $snapshotPeriod)
            ->filter(fn (string $snapshotPeriod): bool => Carbon::parse($snapshotPeriod)->isLastOfMonth())
            ->values()
            ->all();

        if ($selectedDate->isLastOfMonth() && !in_array($period, $periods, true)) {
            $periods[] = $period;
        }

        return array_values(array_unique($periods));
    }

    private function calculateSmallPerformanceRmQuadrant(float $ratasOs, float $larPct): int
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

    private function syncPerformanceRmSnapshotRowsFromTemp(string $period, string $tempTable): void
    {
        $target = $this->quoteIdentifier(self::PERFORMANCE_RM_SNAPSHOT_TABLE);
        $source = $this->quoteIdentifier($tempTable);
        $identityColumns = $this->performanceRmSnapshotIdentityColumns();
        $joinSql = $this->performanceRmSnapshotJoinSql('target', 'source', $identityColumns);

        $columns = array_values(array_filter(
            Schema::getColumnListing(self::PERFORMANCE_RM_SNAPSHOT_TABLE),
            static fn (string $column): bool => $column !== 'id'
        ));
        $updatableColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => !in_array($column, [...$identityColumns, 'created_at'], true)
        ));

        if ($updatableColumns !== []) {
            $assignments = implode(",\n                ", array_map(
                fn (string $column): string => 'target.' . $this->quoteIdentifier($column) . ' = source.' . $this->quoteIdentifier($column),
                $updatableColumns
            ));

            $this->statementWithConcurrencyRetry('performance rm snapshot update from temp', fn (): bool => DB::statement("
                UPDATE {$target} target
                INNER JOIN {$source} source ON {$joinSql}
                SET {$assignments}
                WHERE target.periode = ?
            ", [$period]));
        }

        $insertColumnsSql = implode(', ', array_map([$this, 'quoteIdentifier'], $columns));
        $selectColumnsSql = implode(', ', array_map(fn (string $column): string => 'source.' . $this->quoteIdentifier($column), $columns));

        $this->statementWithConcurrencyRetry('performance rm snapshot insert from temp', fn (): bool => DB::statement("
            INSERT INTO {$target} ({$insertColumnsSql})
            SELECT {$selectColumnsSql}
            FROM {$source} source
            LEFT JOIN {$target} target ON {$joinSql}
            WHERE target.id IS NULL
        "));

        $this->statementWithConcurrencyRetry('performance rm snapshot delete stale from temp', fn (): bool => DB::statement("
            DELETE target
            FROM {$target} target
            LEFT JOIN {$source} source ON {$joinSql}
            WHERE target.periode = ?
                AND source.periode IS NULL
        ", [$period]));
    }

    /**
     * @return array<int, string>
     */
    private function performanceRmSnapshotIdentityColumns(): array
    {
        $columns = ['periode', 'cabang', 'unit', 'rm', 'segmen', 'produk'];

        if (Schema::hasColumn(self::PERFORMANCE_RM_SNAPSHOT_TABLE, 'branch_code')) {
            array_splice($columns, 3, 0, ['branch_code']);
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function performanceRmSnapshotIdentity(array $row): array
    {
        $identity = [];
        foreach ($this->performanceRmSnapshotIdentityColumns() as $column) {
            $identity[$column] = $row[$column] ?? null;
        }

        return $identity;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function performanceRmSnapshotKey(array $row): string
    {
        return implode('|', array_map(
            static fn (string $column): string => (string) ($row[$column] ?? ''),
            $this->performanceRmSnapshotIdentityColumns()
        ));
    }

    /**
     * @param array<int, string> $identityColumns
     */
    private function performanceRmSnapshotJoinSql(string $targetAlias, string $sourceAlias, array $identityColumns): string
    {
        return implode(' AND ', array_map(
            fn (string $column): string => "{$targetAlias}." . $this->quoteIdentifier($column) . " <=> {$sourceAlias}." . $this->quoteIdentifier($column),
            $identityColumns
        ));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function buildPerformanceRmCabangSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasTable(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)) {
            return 0;
        }

        if (!Schema::hasTable(self::PERFORMANCE_RM_SNAPSHOT_TABLE)) {
            return 0;
        }

        $force = $force || $this->purgeSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE, $period);

        if (DB::getDriverName() !== 'mysql') {
            return $this->buildPerformanceRmCabangSnapshotPortable($period, $force);
        }

        if ($force) {
            DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->delete();
        }

        $conflictSql = $force ? '' : "
            ON DUPLICATE KEY UPDATE
                loan_os = VALUES(loan_os),
                lancar_os = VALUES(lancar_os),
                sml_os = VALUES(sml_os),
                npl_os = VALUES(npl_os),
                total_deb = VALUES(total_deb),
                lancar_deb = VALUES(lancar_deb),
                sml_deb = VALUES(sml_deb),
                npl_deb = VALUES(npl_deb),
                restruk_os = VALUES(restruk_os),
                realisasi_deb = VALUES(realisasi_deb),
                realisasi_os = VALUES(realisasi_os),
                total_deposit = VALUES(total_deposit),
                plafon = VALUES(plafon),
                updated_at = VALUES(updated_at)
        ";

        $this->statementWithConcurrencyRetry('performance rm cabang snapshot upsert', fn (): bool => DB::statement(
            "
            INSERT INTO performance_rm_cabang_snapshots (
                periode, cabang, segmen, produk,
                loan_os, lancar_os, sml_os, npl_os,
                total_deb, lancar_deb, sml_deb, npl_deb,
                restruk_os, realisasi_deb, realisasi_os,
                total_deposit, plafon,
                created_at, updated_at
            )
            SELECT
                p.periode,
                p.cabang,
                p.segmen,
                p.produk,
                SUM(COALESCE(p.loan_os, 0)) as loan_os,
                SUM(COALESCE(p.lancar_os, 0)) as lancar_os,
                SUM(COALESCE(p.sml_os, 0)) as sml_os,
                SUM(COALESCE(p.npl_os, 0)) as npl_os,
                SUM(COALESCE(p.total_deb, 0)) as total_deb,
                SUM(COALESCE(p.lancar_deb, 0)) as lancar_deb,
                SUM(COALESCE(p.sml_deb, 0)) as sml_deb,
                SUM(COALESCE(p.npl_deb, 0)) as npl_deb,
                SUM(COALESCE(p.restruk_os, 0)) as restruk_os,
                SUM(COALESCE(p.realisasi_deb, 0)) as realisasi_deb,
                SUM(COALESCE(p.realisasi_os, 0)) as realisasi_os,
                SUM(COALESCE(p.total_deposit, 0)) as total_deposit,
                SUM(COALESCE(p.plafon, 0)) as plafon,
                NOW(),
                NOW()
            FROM performance_rm_snapshots p
            WHERE p.periode = ? AND p.segmen IS NOT NULL
            GROUP BY p.periode, p.cabang, p.segmen, p.produk
            {$conflictSql}
            ",
            [$period]
        ));

        if (!$force) {
            $this->statementWithConcurrencyRetry('performance rm cabang snapshot prune', fn (): bool => DB::statement("
                DELETE target
                FROM performance_rm_cabang_snapshots target
                LEFT JOIN (
                    SELECT periode, cabang, segmen, produk
                    FROM performance_rm_snapshots
                    WHERE periode = ? AND segmen IS NOT NULL
                    GROUP BY periode, cabang, segmen, produk
                ) source
                    ON source.periode = target.periode
                    AND source.cabang <=> target.cabang
                    AND source.segmen <=> target.segmen
                    AND source.produk <=> target.produk
                WHERE target.periode = ?
                    AND source.periode IS NULL
            ", [$period, $period]));
        }

        $rowCount = (int) DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->count();
        $this->logSnapshotPeriodIfAnomalous(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE, $period);

        return $rowCount;
    }

    private function buildPerformanceRmCabangSnapshotPortable(string $period, bool $force): int
    {
        if ($force) {
            DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)
                ->where('periode', $period)
                ->delete();
        }

        $rows = DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->whereNotNull('segmen')
            ->groupBy('periode', 'cabang', 'segmen', 'produk')
            ->select([
                'periode',
                'cabang',
                'segmen',
                'produk',
            ])
            ->selectRaw('SUM(COALESCE(loan_os, 0)) as loan_os')
            ->selectRaw('SUM(COALESCE(lancar_os, 0)) as lancar_os')
            ->selectRaw('SUM(COALESCE(sml_os, 0)) as sml_os')
            ->selectRaw('SUM(COALESCE(npl_os, 0)) as npl_os')
            ->selectRaw('SUM(COALESCE(total_deb, 0)) as total_deb')
            ->selectRaw('SUM(COALESCE(lancar_deb, 0)) as lancar_deb')
            ->selectRaw('SUM(COALESCE(sml_deb, 0)) as sml_deb')
            ->selectRaw('SUM(COALESCE(npl_deb, 0)) as npl_deb')
            ->selectRaw('SUM(COALESCE(restruk_os, 0)) as restruk_os')
            ->selectRaw('SUM(COALESCE(realisasi_deb, 0)) as realisasi_deb')
            ->selectRaw('SUM(COALESCE(realisasi_os, 0)) as realisasi_os')
            ->selectRaw('SUM(COALESCE(total_deposit, 0)) as total_deposit')
            ->selectRaw('SUM(COALESCE(plafon, 0)) as plafon')
            ->get();

        $validKeys = [];
        foreach ($rows as $row) {
            $payload = [
                'periode' => (string) $row->periode,
                'cabang' => (string) $row->cabang,
                'segmen' => (string) $row->segmen,
                'produk' => (string) $row->produk,
                'loan_os' => (float) $row->loan_os,
                'lancar_os' => (float) $row->lancar_os,
                'sml_os' => (float) $row->sml_os,
                'npl_os' => (float) $row->npl_os,
                'total_deb' => (int) $row->total_deb,
                'lancar_deb' => (int) $row->lancar_deb,
                'sml_deb' => (int) $row->sml_deb,
                'npl_deb' => (int) $row->npl_deb,
                'restruk_os' => (float) $row->restruk_os,
                'realisasi_deb' => (int) $row->realisasi_deb,
                'realisasi_os' => (float) $row->realisasi_os,
                'total_deposit' => (float) $row->total_deposit,
                'plafon' => (float) $row->plafon,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $identity = array_intersect_key($payload, array_flip(['periode', 'cabang', 'segmen', 'produk']));
            $validKeys[implode('|', $identity)] = true;

            DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)->updateOrInsert(
                $identity,
                array_diff_key($payload, array_flip(['created_at']))
            );
        }

        $existingRows = DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->get(['periode', 'cabang', 'segmen', 'produk']);

        foreach ($existingRows as $existingRow) {
            $identity = [
                'periode' => (string) $existingRow->periode,
                'cabang' => (string) $existingRow->cabang,
                'segmen' => (string) $existingRow->segmen,
                'produk' => (string) $existingRow->produk,
            ];

            if (isset($validKeys[implode('|', $identity)])) {
                continue;
            }

            DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)->where($identity)->delete();
        }

        return (int) DB::table(self::PERFORMANCE_RM_CABANG_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->count();
    }

    private function computePerformanceRmRows(string $period): array
    {
        $rows = [];
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $latestSmpnPosisi = DB::table('simpanan_multipn')->max('posisi');
        $snapshotColumns = array_flip(Schema::getColumnListing(self::PERFORMANCE_RM_SNAPSHOT_TABLE));

        foreach (self::KINERJA_RM_SEGMENT_RULES as $segment => $rules) {
            $normalizedRules = $this->normalizeKinerjaRmRules((array) $rules);
            if ($normalizedRules === []) {
                continue;
            }

            $isSmall = ($segment === 'SMALL');
            $segmentRows = $this->fetchSegmentRmAggregates($period, $segment, $normalizedRules, $isSmall);

            if (empty($segmentRows)) {
                continue;
            }

            $uniqueCifs = array_reduce($segmentRows, function ($cifs, $row) {
                if (!empty($row['cifno_list'])) {
                    $parsed = array_filter(array_map('trim', explode(',', (string)$row['cifno_list'])));
                    return array_merge($cifs, $parsed);
                }
                return $cifs;
            }, []);

            $deposits = [];
            if (!empty($uniqueCifs)) {
                $deposits = $this->fetchDepositsByNormalizedCifs(array_unique($uniqueCifs), $latestSmpnPosisi);
            }

            $rmTotals = [];
            foreach ($segmentRows as $row) {
                $rm = (string) $row['rm'];
                $cifList = array_filter(array_map('trim', explode(',', (string)($row['cifno_list'] ?? ''))));
                $depSum = array_reduce($cifList, fn ($sum, $cif) => $sum + ((float) ($deposits[$cif] ?? 0)), 0.0);

                if (!isset($rmTotals[$rm])) {
                    $rmTotals[$rm] = [
                    'loan' => 0, 'lancar' => 0, 'npl' => 0,
                        'sml' => 0, 'restruk' => 0, 'deposit' => 0,
                        'realisasi_os' => 0, 'total_deb' => 0, 'realisasi_deb' => 0
                    ];
                }

                $rmTotals[$rm]['loan'] += (float) $row['loan_os'];
                $rmTotals[$rm]['lancar'] += (float) $row['lancar_os'];
                $rmTotals[$rm]['npl'] += (float) $row['npl_os'];
                $rmTotals[$rm]['sml'] += (float) $row['sml_os'];
                $rmTotals[$rm]['restruk'] += (float) $row['restruk_os'];
                $rmTotals[$rm]['deposit'] += $depSum;
                $rmTotals[$rm]['realisasi_os'] += (float) $row['realisasi_os'];
                $rmTotals[$rm]['total_deb'] += (int) $row['total_deb'];
                $rmTotals[$rm]['realisasi_deb'] += (int) $row['realisasi_deb'];
            }

            $rmGrades = $isSmall
                ? $this->computeSmallSegmentGrades($period, $rmTotals)
                : array_fill_keys(array_keys($rmTotals), null);

            foreach ($segmentRows as $row) {
                $rm = (string) $row['rm'];
                $cifList = array_filter(array_map('trim', explode(',', (string)($row['cifno_list'] ?? ''))));
                $depSum = array_reduce($cifList, fn ($sum, $cif) => $sum + ((float) ($deposits[$cif] ?? 0)), 0.0);
                $canonicalProduct = $this->canonicalizeKinerjaRmProduct($segment, (string) $row['produk']);

                $snapshotRow = [
                    'periode' => $period,
                    'cabang' => (string) $row['cabang'],
                    'unit' => (string) $row['unit'],
                    'rm' => $rm,
                    'segmen' => $segment,
                    'produk' => $canonicalProduct,
                    'plafon' => (float) $row['plafon'],
                    'loan_os' => (float) $row['loan_os'],
                    'lancar_os' => (float) $row['lancar_os'],
                    'sml_os' => (float) $row['sml_os'],
                    'npl_os' => (float) $row['npl_os'],
                    'restruk_os' => (float) $row['restruk_os'],
                    'total_deb' => (int) $row['total_deb'],
                    'realisasi_deb' => (int) $row['realisasi_deb'],
                    'realisasi_os' => (float) $row['realisasi_os'],
                    'total_deposit' => $depSum,
                    'quadrant' => $rmGrades[$rm] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $metricColumns = [
                    'lancar_deb',
                    'sml_deb',
                    'npl_deb',
                    'w1_realisasi_deb',
                    'w1_realisasi_os',
                    'w2_realisasi_deb',
                    'w2_realisasi_os',
                    'w3_realisasi_deb',
                    'w3_realisasi_os',
                    'w4_realisasi_deb',
                    'w4_realisasi_os',
                    'lt_250_realisasi_deb',
                    'lt_250_realisasi_os',
                    'gt_250_realisasi_deb',
                    'gt_250_realisasi_os',
                ];

                if (isset($snapshotColumns['branch_code'])) {
                    $snapshotRow['branch_code'] = (string) ($row['branch_code'] ?? '');
                }

                foreach ($metricColumns as $metricColumn) {
                    if (isset($snapshotColumns[$metricColumn])) {
                        $snapshotRow[$metricColumn] = str_ends_with($metricColumn, '_deb')
                            ? (int) ($row[$metricColumn] ?? 0)
                            : (float) ($row[$metricColumn] ?? 0);
                    }
                }

                $rows[] = $snapshotRow;
            }
        }

        return $rows;
    }

    private function fetchSegmentRmAggregates(string $period, string $segment, array $normalizedRules, bool $isSmall): array
    {
        // OPTIMIZATION: Use shadow columns (segmen_kinerja, produk_kinerja) instead of function-based WHERE
        // This enables index-only scans on (periode, segmen_kinerja, produk_kinerja, cabang_normalized)
        // BENEFIT: 10-50x faster queries (no UPPER/TRIM/REPLACE overhead)
        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $periodDate = Carbon::parse($period);
        $kurRitelDescriptionSql = $this->buildKinerjaRmNormalizedSql('description');
        $kurRitelDescriptionToken = $this->normalizeKinerjaRmToken('Kredit Mikro - KUR Ritel 2015');
        $realisasiDateColumn = $this->performanceRmEffectiveRealisasiDateSql(
            $this->resolvePerformanceRmRealisasiDateColumn(),
            'periode'
        );
        $weekRanges = [
            'w1' => [$periodDate->copy()->startOfMonth(), $periodDate->copy()->startOfMonth()->addDays(6)],
            'w2' => [$periodDate->copy()->startOfMonth()->addDays(7), $periodDate->copy()->startOfMonth()->addDays(13)],
            'w3' => [$periodDate->copy()->startOfMonth()->addDays(14), $periodDate->copy()->startOfMonth()->addDays(20)],
            'w4' => [$periodDate->copy()->startOfMonth()->addDays(21), $periodDate->copy()],
        ];
        $weekRanges = array_map(
            fn (array $range): array => [
                $range[0]->toDateString(),
                $range[1]->greaterThan($periodDate) ? $periodDate->toDateString() : $range[1]->toDateString(),
            ],
            $weekRanges
        );
        $realisasiAmountSql = 'COALESCE(plafon, 0)';

        $query = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($scope) use ($normalizedRules) {
                foreach ($normalizedRules as $rule) {
                    $scope->orWhere(function ($ruleScope) use ($rule) {
                        // Use pre-computed shadow columns instead of functions
                        $ruleScope->where('segmen_kinerja', $rule['segment'])
                            ->whereIn('produk_kinerja', $rule['products']);

                        if (!empty($rule['descriptions'])) {
                            $descriptionSql = $this->buildKinerjaRmNormalizedSql('description');
                            $ruleScope->whereIn(DB::raw($descriptionSql), $rule['descriptions']);
                        }
                    });
                }
            })
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            // Use shadow columns in SELECT to avoid function overhead
            ->select([
                'cabang_normalized as cabang',
                'unit_normalized as unit',
                'branch_normalized as branch_code',
                'rm_normalized as rm',
                'produk_kinerja as produk',
            ])
            ->selectRaw("SUM(COALESCE(plafon, 0)) as plafon")
            ->selectRaw(
                "SUM(CASE WHEN segmen_kinerja = ? AND produk_kinerja IN (?, ?) AND {$kurRitelDescriptionSql} = ? THEN COALESCE(plafon, 0) ELSE COALESCE(baki_debet1, 0) END) as loan_os",
                ['MICRO', 'KURMIKRO', 'KURKECIL', $kurRitelDescriptionToken]
            )
            ->selectRaw("SUM(CASE WHEN kolek = 1 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as lancar_os")
            ->selectRaw("COUNT(DISTINCT CASE WHEN kolek = 1 THEN nomor_rekening1 END) as lancar_deb")
            ->selectRaw("SUM(CASE WHEN kolek = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as sml_os")
            ->selectRaw("COUNT(DISTINCT CASE WHEN kolek = 2 THEN nomor_rekening1 END) as sml_deb")
            ->selectRaw("SUM(CASE WHEN kolek > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) as npl_os")
            ->selectRaw("COUNT(DISTINCT CASE WHEN kolek > 2 THEN nomor_rekening1 END) as npl_deb")
            ->selectRaw("SUM(CASE WHEN kolek = 1 AND COALESCE(flag_restruk, '') = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) as restruk_os")
            ->selectRaw("COUNT(DISTINCT nomor_rekening1) as total_deb")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as realisasi_os", [$periodStart, $period])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as w1_realisasi_deb", $weekRanges['w1'])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w1_realisasi_os", $weekRanges['w1'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as w2_realisasi_deb", $weekRanges['w2'])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w2_realisasi_os", $weekRanges['w2'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as w3_realisasi_deb", $weekRanges['w3'])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w3_realisasi_os", $weekRanges['w3'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN nomor_rekening1 END) as w4_realisasi_deb", $weekRanges['w4'])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? THEN {$realisasiAmountSql} ELSE 0 END) as w4_realisasi_os", $weekRanges['w4'])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(plafon, 0) < 250000000 THEN nomor_rekening1 END) as lt_250_realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(plafon, 0) < 250000000 THEN {$realisasiAmountSql} ELSE 0 END) as lt_250_realisasi_os", [$periodStart, $period])
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(plafon, 0) > 250000000 THEN nomor_rekening1 END) as gt_250_realisasi_deb", [$periodStart, $period])
            ->selectRaw("SUM(CASE WHEN {$realisasiDateColumn} BETWEEN ? AND ? AND COALESCE(plafon, 0) > 250000000 THEN {$realisasiAmountSql} ELSE 0 END) as gt_250_realisasi_os", [$periodStart, $period])
            // Keep full alphanumeric CIF intact for downstream snapshot matching.
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'GROUP_CONCAT(DISTINCT UPPER(TRIM(cifno))) as cifno_list'
                : "GROUP_CONCAT(DISTINCT UPPER(TRIM(cifno)) SEPARATOR ',') as cifno_list")
            // Use shadow columns in GROUP BY to avoid function overhead
            ->groupBy('cabang_normalized', 'unit_normalized', 'branch_normalized', 'rm_normalized', 'produk_kinerja')
            ->get();

        $rows = $query->map(fn($row) => (array)$row)->toArray();

        return $segment === 'CONSUMER'
            ? $this->applyConsumerPlafonSurplusMetrics($period, $rows)
            : $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyConsumerPlafonSurplusMetrics(string $period, array $rows): array
    {
        $zeroColumns = [
            'realisasi_deb',
            'realisasi_os',
            'w1_realisasi_deb',
            'w1_realisasi_os',
            'w2_realisasi_deb',
            'w2_realisasi_os',
            'w3_realisasi_deb',
            'w3_realisasi_os',
            'w4_realisasi_deb',
            'w4_realisasi_os',
            'lt_250_realisasi_deb',
            'lt_250_realisasi_os',
            'gt_250_realisasi_deb',
            'gt_250_realisasi_os',
        ];

        foreach ($rows as &$row) {
            foreach ($zeroColumns as $column) {
                $row[$column] = str_ends_with($column, '_deb') ? 0 : 0.0;
            }
        }
        unset($row);

        if ($rows === []) {
            return $rows;
        }

        $previousPeriod = $this->resolvePreviousMonthPerformanceRmPeriod($period);
        if ($previousPeriod === null) {
            return $rows;
        }

        $periodStart = Carbon::parse($period)->startOfMonth()->toDateString();
        $realisasiDateColumn = $this->performanceRmEffectiveRealisasiDateSql(
            $this->resolvePerformanceRmRealisasiDateColumn(),
            'periode'
        );

        $currentAccountKeys = DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->distinct()
            ->pluck('account_key')
            ->map(fn ($accountKey): string => (string) $accountKey)
            ->filter()
            ->flip();

        $previousLookupOrderColumn = Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')
            ? 'uniqueid_namareport'
            : 'nomor_rekening1';

        $previousClosedOsByCif = [];
        DB::table('daily_loan_dinamis')
            ->where('periode', $previousPeriod)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->selectRaw('UPPER(TRIM(cifno)) as clean_cif')
            ->selectRaw('UPPER(TRIM(nomor_rekening1)) as account_key')
            ->selectRaw('COALESCE(baki_debet1, 0) as previous_os')
            ->orderBy($previousLookupOrderColumn)
            ->chunk(1000, function ($sourceRows) use (&$previousClosedOsByCif, $currentAccountKeys): void {
                foreach ($sourceRows as $sourceRow) {
                    $cleanCif = (string) ($sourceRow->clean_cif ?? '');
                    $accountKey = (string) ($sourceRow->account_key ?? '');
                    if ($cleanCif === '' || isset($currentAccountKeys[$accountKey]) || array_key_exists($cleanCif, $previousClosedOsByCif)) {
                        continue;
                    }

                    $previousClosedOsByCif[$cleanCif] = (float) ($sourceRow->previous_os ?? 0);
                }
            });

        $currentRealizationByCif = [];
        DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where('segmen_kinerja', 'CONSUMER')
            ->whereIn('produk_kinerja', ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull('pn_pengelola1')
            ->where('pn_pengelola1', '<>', '')
            ->whereNotNull('nomor_rekening1')
            ->where('nomor_rekening1', '<>', '')
            ->whereNotNull('cifno')
            ->where('cifno', '<>', '')
            ->whereRaw("{$realisasiDateColumn} BETWEEN ? AND ?", [$periodStart, $period])
            ->select([
                'cabang_normalized',
                'unit_normalized',
                'branch_normalized',
                'rm_normalized',
                'produk_kinerja',
                'nomor_rekening1',
                'plafon',
                'cifno',
            ])
            ->selectRaw("UPPER(TRIM(nomor_rekening1)) as account_key")
            ->orderBy('nomor_rekening1')
            ->chunk(1000, function ($sourceRows) use (&$currentRealizationByCif): void {
                foreach ($sourceRows as $sourceRow) {
                    $groupKey = $this->consumerSurplusGroupKey([
                        'cabang' => $sourceRow->cabang_normalized,
                        'unit' => $sourceRow->unit_normalized,
                        'branch_code' => $sourceRow->branch_normalized,
                        'rm' => $sourceRow->rm_normalized,
                        'produk' => $sourceRow->produk_kinerja,
                    ]);
                    $cleanCif = strtoupper(trim((string) ($sourceRow->cifno ?? '')));
                    $accountKey = (string) ($sourceRow->account_key ?? '');
                    $metricKey = $groupKey . '|' . $cleanCif;

                    if (!isset($currentRealizationByCif[$metricKey])) {
                        $currentRealizationByCif[$metricKey] = [
                            'group_key' => $groupKey,
                            'clean_cif' => $cleanCif,
                            'accounts' => [],
                            'current_plafon' => 0.0,
                        ];
                    }

                    $currentRealizationByCif[$metricKey]['accounts'][$accountKey] = true;
                    $currentRealizationByCif[$metricKey]['current_plafon'] += (float) ($sourceRow->plafon ?? 0);
                }
            });

        $surplusByGroup = [];
        foreach ($currentRealizationByCif as $metric) {
            $groupKey = (string) ($metric['group_key'] ?? '');
            $cleanCif = (string) ($metric['clean_cif'] ?? '');
            $netDisbursement = (float) ($metric['current_plafon'] ?? 0)
                - (float) ($previousClosedOsByCif[$cleanCif] ?? 0);

            $surplusByGroup[$groupKey] ??= ['debitur' => 0, 'os' => 0.0];
            $surplusByGroup[$groupKey]['debitur'] += count($metric['accounts'] ?? []);
            $surplusByGroup[$groupKey]['os'] += $netDisbursement;
        }

        foreach ($rows as &$row) {
            $metric = $surplusByGroup[$this->consumerSurplusGroupKey($row)] ?? null;
            if ($metric === null) {
                continue;
            }

            $row['realisasi_deb'] = (int) ($metric['debitur'] ?? 0);
            $row['realisasi_os'] = (float) ($metric['os'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function consumerSurplusGroupKey(array $row): string
    {
        return implode('|', [
            (string) ($row['cabang'] ?? ''),
            (string) ($row['unit'] ?? ''),
            (string) ($row['branch_code'] ?? ''),
            (string) ($row['rm'] ?? ''),
            $this->canonicalizeKinerjaRmProduct('CONSUMER', (string) ($row['produk'] ?? '')),
        ]);
    }

    private function resolvePreviousMonthPerformanceRmPeriod(string $period): ?string
    {
        $periodDate = Carbon::parse($period);
        $previousEnd = $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $exists = DB::table('daily_loan_dinamis')
            ->where('periode', $previousEnd)
            ->exists();

        return $exists ? $previousEnd : null;
    }

    private function fetchDepositsByNormalizedCifs(array $normalizedCifs, ?string $latestPosisi): array
    {
        // CIF is an alphanumeric identifier; keep its full value intact.
        if (empty($normalizedCifs)) {
            return [];
        }

        $deposits = DB::table('simpanan_multipn')
            ->where('posisi', $latestPosisi ?? DB::table('simpanan_multipn')->max('posisi'))
            ->selectRaw('CIFNO as clean_cif')
            ->selectRaw("SUM(COALESCE(saldo_idr, 0)) as total_deposit");

        $deposits->whereIn('CIFNO', array_unique($normalizedCifs));

        return $deposits
            ->groupBy('clean_cif')
            ->pluck('total_deposit', 'clean_cif')
            ->all();
    }

    private function computeSmallSegmentGrades(string $period, array $rmTotals): array
    {
        $grades = [];
        $rmKeys = array_keys($rmTotals);
        if (empty($rmKeys)) {
            return $grades;
        }

        $closedPeriods = $this->resolveSmallClosedMonthlySnapshotPeriods($period);
        if ($closedPeriods === []) {
            return $grades;
        }

        $historicalPeriods = array_values(array_filter($closedPeriods, fn (string $closedPeriod): bool => $closedPeriod !== $period));
        $historySums = $historicalPeriods !== []
            ? DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
                ->whereIn('rm', $rmKeys)
                ->where('segmen', 'SMALL')
                ->whereIn('produk', ['SMALL', 'COMMERCIAL', 'CASHCALL', 'CASHCOLLATERAL', 'CASHCOLL'])
                ->whereIn('periode', $historicalPeriods)
                ->selectRaw('rm, SUM(realisasi_os) as total')
                ->groupBy('rm')
                ->pluck('total', 'rm')
                ->all()
            : [];
        $lastClosedPeriod = $closedPeriods[array_key_last($closedPeriods)];
        $larByRm = $lastClosedPeriod !== $period
            ? DB::table(self::PERFORMANCE_RM_SNAPSHOT_TABLE)
                ->whereIn('rm', $rmKeys)
                ->where('segmen', 'SMALL')
                ->where('periode', $lastClosedPeriod)
                ->selectRaw('rm, SUM(loan_os) as loan_os')
                ->selectRaw('SUM(restruk_os + sml_os + npl_os) as lar_value')
                ->groupBy('rm')
                ->get()
                ->keyBy(fn ($row): string => (string) $row->rm)
            : collect();
        $periodCount = count($closedPeriods);

        foreach ($rmKeys as $rm) {
            $historySum = (float) ($historySums[$rm] ?? 0);
            if (in_array($period, $closedPeriods, true)) {
                $historySum += (float) $rmTotals[$rm]['realisasi_os'];
            }

            $lar = $lastClosedPeriod === $period ? null : $larByRm->get($rm);
            $loanOs = $lastClosedPeriod === $period
                ? (float) $rmTotals[$rm]['loan']
                : (float) ($lar->loan_os ?? 0);
            $larValue = $lastClosedPeriod === $period
                ? (float) $rmTotals[$rm]['restruk'] + (float) $rmTotals[$rm]['sml'] + (float) $rmTotals[$rm]['npl']
                : (float) ($lar->lar_value ?? 0);

            if ($loanOs <= 0) {
                continue;
            }

            $ratasOs = $historySum / $periodCount;
            $larPct = ($larValue / $loanOs) * 100;
            $grades[$rm] = $this->calculateSmallPerformanceRmQuadrant($ratasOs, $larPct);
        }

        return $grades;
    }

    private function resolvePerformanceRmRealisasiDateColumn(): string
    {
        return Schema::hasColumn('daily_loan_dinamis', 'tgl_realisasi1')
            ? 'tgl_realisasi1'
            : 'tgl_realisasi';
    }

    private function performanceRmEffectiveRealisasiDateSql(string $dateColumn, string $periodColumn): string
    {
        return $dateColumn;
    }

    private function buildKinerjaRmNormalizedSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
    }

    private function normalizeKinerjaRmToken(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';
    }

    /**
     * @param array<int, array{source_segment?: string, products?: array<int, string>}> $rules
     * @return array<int, array{segment: string, products: array<int, string>}>
     */
    private function normalizeKinerjaRmRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            $segmentToken = $this->normalizeKinerjaRmToken((string) ($rule['source_segment'] ?? ''));
            $productTokens = collect((array) ($rule['products'] ?? []))
                ->map(fn ($product) => $this->normalizeKinerjaRmToken((string) $product))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $descriptionTokens = collect((array) ($rule['descriptions'] ?? []))
                ->map(fn ($description) => $this->normalizeKinerjaRmToken((string) $description))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($segmentToken === '' || $productTokens === []) {
                continue;
            }

            $normalized[] = [
                'segment' => $segmentToken,
                'products' => $productTokens,
                'descriptions' => $descriptionTokens,
            ];
        }

        return $normalized;
    }

    private function canonicalizeKinerjaRmProduct(string $segment, string $product): string
    {
        $token = $this->normalizeKinerjaRmToken($product);
        $segment = strtoupper(trim($segment));

        $map = match ($segment) {
            'CONSUMER' => [
                'BRIGUNAKONSUMER' => 'BRIGUNA-KONSUMER',
                'KPR' => 'KPR',
            ],
            'SMALL' => [
                'COMMERCIAL' => 'SMALL',
                'CASHCALL' => 'SMALL',
                'CASHCOLLATERAL' => 'SMALL',
                'CASHCOLL' => 'SMALL',
                'SMALL' => 'SMALL',
            ],
            'MICRO' => [
                'BRIGUNAMIKRO' => 'BRIGUNA-MIKRO',
                'KUPEDES' => 'KUPEDES',
                'KURMIKRO' => 'KUR-MIKRO',
                'KURKECIL' => 'KUR-MIKRO',
                'CASHCOLLATERAL' => 'CASHCOLLATERAL',
                'CASHCOLL' => 'CASHCOLLATERAL',
                'KPR' => 'KPR',
                'KURSMALL' => 'KUR-SMALL',
            ],
            default => [],
        };

        return $map[$token] ?? strtoupper(trim($product));
    }

    /**
     * @param callable(): bool $callback
     */
    private function statementWithConcurrencyRetry(string $context, callable $callback): bool
    {
        $attempts = 0;
        $maxAttempts = 3;

        do {
            $attempts++;

            try {
                return $callback();
            } catch (Throwable $e) {
                if ($attempts >= $maxAttempts || !$this->isRetryableConcurrencyError($e)) {
                    throw $e;
                }

                usleep(random_int(200_000, 800_000) * $attempts);
                logger()->warning('Retrying snapshot SQL after transient lock conflict.', [
                    'context' => $context,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
            }
        } while ($attempts < $maxAttempts);

        return false;
    }

    private function isRetryableConcurrencyError(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'SQLSTATE[40001]')
            || str_contains($message, '1213 Deadlock')
            || str_contains($message, '1205 Lock wait timeout');
    }
}
