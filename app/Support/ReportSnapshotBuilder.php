<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportSnapshotBuilder
{
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const DASHBOARD_SIMPANAN_SNAPSHOT_TABLE = 'dashboard_simpanan_snapshots';
    private const DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const RASIO_UKER_SNAPSHOT_TABLE = 'rasio_casa_debitur_uker_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const NEW_PAYROLL_SNAPSHOT_TABLE = 'performance_new_payroll_snapshots';

    private const PRIORITY_BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
    private const SEGMENTS = ['total', 'briguna', 'kpr', 'mikro', 'smc'];
    private const NEW_PAYROLL_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const AREA_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    private const BRANCH_PATTERNS = [
        'KC Madiun' => 'KC MADIUN',
        'KC Magetan' => 'KC MAGETAN',
        'KC Ngawi' => 'KC NGAWI',
        'KC Ponorogo' => 'KC PONOROGO',
    ];
    private const LOAN_SOURCE_KEY_COLUMN = 'uniqueid_namareport';

    /** @var array<string, array<int, string>> */
    private array $columnListingCache = [];

    /** @var array<string, string|null> */
    private array $availablePeriodCache = [];

    /** @var array<string, string|null> */
    private array $availableCasaPeriodCache = [];

    /** @var array<string, bool> */
    private array $casaTypeFilterCache = [];

    public function __construct(
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService
    ) {
    }

    public function rebuild(string $report = 'all', ?string $period = null, bool $force = false): array
    {
        $report = strtolower(trim($report));

        return match ($report) {
            'dashboard', 'dashboard-pinjaman', 'pinjaman' => [
                'dashboard' => $this->rebuildDashboard($period, $force),
            ],
            'dashboard-simpanan', 'simpanan-dashboard', 'simpanan' => [
                'dashboard_simpanan' => $this->rebuildDashboardSimpanan($period, $force),
            ],
            'rasio', 'rasio-casa', 'rasio-casa-debitur' => [
                'rasio' => $this->rebuildRasioCasa($period, $force),
            ],
            'dormant', 'rekening-dormant' => [
                'dormant' => $this->rebuildRekeningDormant($period, $force),
            ],
            'new-payroll', 'performance-new-payroll', 'payroll' => [
                'new_payroll' => $this->rebuildPerformanceNewPayroll($period, $force),
            ],
            'dashboard-harian', 'harian' => [
                'dashboard_harian' => $this->dashboardHarianSnapshotService->rebuild($period, $force),
            ],
            default => [
                'dashboard' => $this->rebuildDashboard($period, $force),
                'dashboard_simpanan' => $this->rebuildDashboardSimpanan($period, $force),
                'dashboard_harian' => $this->dashboardHarianSnapshotService->rebuild($period, $force),
                'rasio' => $this->rebuildRasioCasa($period, $force),
                'dormant' => $this->rebuildRekeningDormant($period, $force),
                'new_payroll' => $this->rebuildPerformanceNewPayroll($period, $force),
            ],
        };
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
            $results[$snapshotPeriod] = $this->buildDashboardPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
        }

        return $results;
    }

    public function rebuildDashboardSimpanan(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveSimpananDashboardPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildDashboardSimpananPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
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
            $results[$snapshotPeriod] = $this->buildRasioPeriodSnapshot($snapshotPeriod, $force)
                + $this->buildRasioUkerPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
        }

        return $results;
    }

    public function rebuildRekeningDormant(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveDormantPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildDormantPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
        }

        return $results;
    }

    public function rebuildPerformanceNewPayroll(?string $period = null, bool $force = false, ?callable $progress = null): array
    {
        $results = [];
        $periods = $this->resolveNewPayrollPeriods($period);
        $totalPeriods = count($periods);

        foreach ($periods as $index => $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildNewPayrollPeriodSnapshot($snapshotPeriod, $force);

            if ($progress !== null) {
                $progress([
                    'current_period' => $snapshotPeriod,
                    'completed_units' => $index + 1,
                    'total_units' => $totalPeriods,
                    'current_result_count' => (int) ($results[$snapshotPeriod] ?? 0),
                ]);
            }
        }

        return $results;
    }

    private function buildDashboardPeriodSnapshot(string $period, bool $force): int
    {
        if (!$force) {
            $existingCount = (int) DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
        }

        $bucketExpression = $this->buildDashboardBucketExpression();
        $normalizedLoanBalanceExpression = $this->buildNormalizedLoanBalanceExpression('baki_debet1');
        $snapshotTable = self::DASHBOARD_SNAPSHOT_TABLE;
        $sourceKey = self::LOAN_SOURCE_KEY_COLUMN;

        DB::statement("
            INSERT INTO {$snapshotTable}
            (
                uniqueid_dps, periode, account_number, loan_balance, quality_bucket,
                segmen_dashboard, produk_dashboard, cabang1, unit1, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'dps', ?, TRIM(COALESCE({$sourceKey}, '')), TRIM(nomor_rekening1))) as uniqueid_dps,
                ? as periode,
                TRIM(nomor_rekening1) as account_number,
                {$normalizedLoanBalanceExpression} as loan_balance,
                {$bucketExpression} as quality_bucket,
                TRIM(COALESCE(segmen_dashboard, '')) as segmen_dashboard,
                TRIM(COALESCE(produk_dashboard, '')) as produk_dashboard,
                TRIM(COALESCE(cabang1, '')) as cabang1,
                TRIM(COALESCE(unit1, '')) as unit1,
                NOW() as created_at,
                NOW() as updated_at
            FROM daily_loan_dinamis
            WHERE periode = ?
                AND nomor_rekening1 IS NOT NULL
                AND nomor_rekening1 <> ''
            ON DUPLICATE KEY UPDATE
                loan_balance = VALUES(loan_balance),
                quality_bucket = VALUES(quality_bucket),
                segmen_dashboard = VALUES(segmen_dashboard),
                produk_dashboard = VALUES(produk_dashboard),
                cabang1 = VALUES(cabang1),
                unit1 = VALUES(unit1),
                updated_at = VALUES(updated_at)
        ", [$period, $period, $period]);

        DB::statement("
            DELETE snap
            FROM {$snapshotTable} snap
            LEFT JOIN (
                SELECT
                    MD5(CONCAT_WS('|', 'dps', ?, TRIM(COALESCE({$sourceKey}, '')), TRIM(nomor_rekening1))) as uniqueid_dps
                FROM daily_loan_dinamis
                WHERE periode = ?
                    AND nomor_rekening1 IS NOT NULL
                    AND nomor_rekening1 <> ''
            ) src ON src.uniqueid_dps = snap.uniqueid_dps
            WHERE snap.periode = ?
                AND src.uniqueid_dps IS NULL
        ", [$period, $period, $period]);

        return (int) DB::table(self::DASHBOARD_SNAPSHOT_TABLE)->where('periode', $period)->count();
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

    private function buildRasioPeriodSnapshot(string $loanPeriod, bool $force): int
    {
        if (!$force) {
            $existingCount = (int) DB::table(self::RASIO_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
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

        return count($rows);
    }

    private function buildRasioUkerPeriodSnapshot(string $loanPeriod, bool $force): int
    {
        if (!Schema::hasTable(self::RASIO_UKER_SNAPSHOT_TABLE)) {
            return 0;
        }

        if (!$force) {
            $existingCount = (int) DB::table(self::RASIO_UKER_SNAPSHOT_TABLE)->where('loan_period', $loanPeriod)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
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

        return count($rows);
    }

    private function buildDashboardSimpananPeriodSnapshot(string $period, bool $force): int
    {
        if (!Schema::hasTable(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE) || !Schema::hasTable('simpanan_multipn')) {
            return 0;
        }

        if (!$force) {
            $existingSourceRowCount = DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)
                ->where('snapshot_period', $period)
                ->value('source_row_count');

            if ($existingSourceRowCount !== null) {
                return (int) $existingSourceRowCount;
            }
        }

        $baseQuery = DB::table('simpanan_multipn')->where('posisi', $period);

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as source_row_count')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->selectRaw('COUNT(DISTINCT no_rekening) as account_count')
            ->selectRaw('COUNT(DISTINCT CIFNO) as cif_count')
            ->selectRaw('COUNT(DISTINCT kantor_cabang) as branch_count')
            ->selectRaw('COUNT(DISTINCT unit_kerja) as unit_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as tabungan_balance")
            ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(COALESCE(jenis_simpanan, '')) LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END), 0) as giro_balance")
            ->selectRaw('MAX(updated_at) as source_updated_at')
            ->first();

        $sourceRowCount = (int) ($summary->source_row_count ?? 0);
        if ($sourceRowCount <= 0) {
            DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();
            DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        $totalBalance = (float) ($summary->total_balance ?? 0);
        $tabunganBalance = (float) ($summary->tabungan_balance ?? 0);
        $giroBalance = (float) ($summary->giro_balance ?? 0);
        $otherBalance = max(0, $totalBalance - $tabunganBalance - $giroBalance);

        $topBranches = (clone $baseQuery)
            ->whereNotNull('kantor_cabang')
            ->where('kantor_cabang', '<>', '')
            ->selectRaw('TRIM(kantor_cabang) as kantor_cabang')
            ->selectRaw('COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) as total_balance')
            ->groupBy('kantor_cabang')
            ->orderByDesc('total_balance')
            ->limit(5)
            ->get();

        $topBranch = $topBranches->first();

        DB::table(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE)->upsert([
            [
                'uniqueid_dss' => md5(implode('|', ['dss', $period])),
                'snapshot_period' => $period,
                'total_balance' => $totalBalance,
                'account_count' => (int) ($summary->account_count ?? 0),
                'cif_count' => (int) ($summary->cif_count ?? 0),
                'branch_count' => (int) ($summary->branch_count ?? 0),
                'unit_count' => (int) ($summary->unit_count ?? 0),
                'tabungan_balance' => $tabunganBalance,
                'giro_balance' => $giroBalance,
                'other_balance' => $otherBalance,
                'top_branch_label' => trim((string) ($topBranch->kantor_cabang ?? '')),
                'top_branch_balance' => (float) ($topBranch->total_balance ?? 0),
                'source_row_count' => $sourceRowCount,
                'source_updated_at' => $summary->source_updated_at,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
                'total_balance' => (float) ($row->total_balance ?? 0),
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

        $branchCleanup = DB::table(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE)->where('snapshot_period', $period);
        if (!empty($branchKeys)) {
            $branchCleanup->whereNotIn('kantor_cabang', $branchKeys)->delete();
        } else {
            $branchCleanup->delete();
        }

        return $sourceRowCount;
    }

    private function buildDormantPeriodSnapshot(string $period, bool $force): int
    {
        if (!$force) {
            $existingCount = (int) DB::table(self::DORMANT_SNAPSHOT_TABLE)->where('posisi', $period)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
        }

        $snapshotTable = self::DORMANT_SNAPSHOT_TABLE;
        $branchLabelExpression = $this->buildDormantBranchLabelSqlExpression('base.raw_branch');

        DB::statement("
            INSERT INTO {$snapshotTable}
            (
                uniqueid_rds, posisi, branch_label, raw_branch, unit_kerja, dormant_count, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'rds', ?, TRIM(base.raw_branch), TRIM(base.unit_kerja))) as uniqueid_rds,
                ? as posisi,
                {$branchLabelExpression} as branch_label,
                TRIM(base.raw_branch) as raw_branch,
                TRIM(base.unit_kerja) as unit_kerja,
                base.dormant_count as dormant_count,
                NOW() as created_at,
                NOW() as updated_at
            FROM (
                SELECT
                    normalized.raw_branch as raw_branch,
                    normalized.unit_kerja as unit_kerja,
                    COUNT(*) as dormant_count
                FROM (
                    SELECT
                        TRIM(kantor_cabang) as raw_branch,
                        COALESCE(NULLIF(TRIM(unit_kerja), ''), '') as unit_kerja
                    FROM simpanan_multipn
                    WHERE posisi = ?
                        AND status = '9'
                        AND kantor_cabang IS NOT NULL
                        AND kantor_cabang <> ''
                ) normalized
                GROUP BY normalized.raw_branch, normalized.unit_kerja
            ) base
            WHERE {$branchLabelExpression} IS NOT NULL
            ON DUPLICATE KEY UPDATE
                branch_label = VALUES(branch_label),
                dormant_count = VALUES(dormant_count),
                updated_at = VALUES(updated_at)
        ", [$period, $period, $period]);

        DB::statement("
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
                    ) normalized
                    GROUP BY normalized.raw_branch, normalized.unit_kerja
                ) base
                WHERE {$branchLabelExpression} IS NOT NULL
            ) src ON src.uniqueid_rds = snap.uniqueid_rds
            WHERE snap.posisi = ?
                AND src.uniqueid_rds IS NULL
        ", [$period, $period, $period]);

        return (int) DB::table(self::DORMANT_SNAPSHOT_TABLE)->where('posisi', $period)->count();
    }

    private function buildNewPayrollPeriodSnapshot(string $snapshotPosisi, bool $force): int
    {
        if (!Schema::hasTable(self::NEW_PAYROLL_SNAPSHOT_TABLE) || !Schema::hasTable('performance_pis_per_produk')) {
            return 0;
        }

        if (!$force) {
            $existingCount = (int) DB::table(self::NEW_PAYROLL_SNAPSHOT_TABLE)->where('snapshot_posisi', $snapshotPosisi)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
        }

        $snapshotDate = Carbon::parse($snapshotPosisi);
        $currStart = $snapshotDate->copy()->startOfMonth()->toDateString();
        $currEnd = $snapshotDate->copy()->endOfMonth()->toDateString();
        $prevStart = $snapshotDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevEnd = Carbon::parse($prevStart)->endOfMonth()->toDateString();
        $yoyStart = $snapshotDate->copy()->subYearNoOverflow()->startOfMonth()->toDateString();
        $yoyEnd = Carbon::parse($yoyStart)->endOfMonth()->toDateString();

        // Use single INSERT ... SELECT ... ON DUPLICATE KEY UPDATE
        $branchList = implode("','", self::NEW_PAYROLL_BRANCHES);

        DB::statement(
            "INSERT INTO " . self::NEW_PAYROLL_SNAPSHOT_TABLE . " (
                uniqueid_pnps, snapshot_posisi, branch, rekening_curr, rekening_prev, rekening_yoy_prev,
                saldo_curr, saldo_prev, saldo_yoy_prev, created_at, updated_at
            )
            SELECT
                MD5(CONCAT_WS('|', 'pnps', ?, TRIM(UPPER(kanca)))) as uniqueid_pnps,
                ? as snapshot_posisi,
                TRIM(UPPER(kanca)) as branch,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_curr,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_prev,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 ELSE 0 END) as rekening_yoy_prev,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_curr,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_prev,
                SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_yoy_prev,
                NOW() as created_at,
                NOW() as updated_at
            FROM performance_pis_per_produk
            WHERE posisi = ?
                AND UPPER(TRIM(kanca)) IN ('{$branchList}')
            GROUP BY TRIM(UPPER(kanca))
            ON DUPLICATE KEY UPDATE
                rekening_curr = VALUES(rekening_curr),
                rekening_prev = VALUES(rekening_prev),
                rekening_yoy_prev = VALUES(rekening_yoy_prev),
                saldo_curr = VALUES(saldo_curr),
                saldo_prev = VALUES(saldo_prev),
                saldo_yoy_prev = VALUES(saldo_yoy_prev),
                updated_at = VALUES(updated_at)",
            [
                $snapshotPosisi, // for MD5
                $snapshotPosisi, // snapshot_posisi
                $currStart, $currEnd,
                $prevStart, $prevEnd,
                $yoyStart, $yoyEnd,
                $currStart, $currEnd,
                $prevStart, $prevEnd,
                $yoyStart, $yoyEnd,
                $snapshotPosisi, // WHERE posisi = ?
            ]
        );

        // Remove any orphan rows not in the fixed branch list
        DB::table(self::NEW_PAYROLL_SNAPSHOT_TABLE)
            ->where('snapshot_posisi', $snapshotPosisi)
            ->whereNotIn('branch', self::NEW_PAYROLL_BRANCHES)
            ->delete();

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
            $casaBalances = [];

            foreach (array_chunk(array_keys($identityVariants), 2000) as $chunk) {
                $casaQuery = DB::table('simpanan_multipn')
                    ->where('posisi', $casaDate)
                    ->whereIn($casaKeyColumn, $chunk);

                if ($applyCasaTypeFilter) {
                    $casaQuery->where(function ($query) {
                        $query->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                }

                foreach ($casaQuery
                    ->selectRaw("{$casaKeyColumn} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
                    ->groupBy($casaKeyColumn)
                    ->get() as $row
                ) {
                    $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

                    if ($identityKey !== '') {
                        $casaBalances[$identityKey] = ($casaBalances[$identityKey] ?? 0) + (float) ($row->casa_balance ?? 0);
                    }
                }
            }

            foreach ($casaBalances as $identityKey => $balance) {
                foreach (($identityMappings[$identityKey] ?? []) as $branchKey => $flags) {
                    foreach ($flags as $segmentKey => $enabled) {
                        if ($enabled) {
                            $snapshot['casa'][$branchKey][$segmentKey] += $balance;
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

        $loanBase = DB::table('daily_loan_dinamis as d')
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

            $casaBase = DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                ->whereNotNull($casaKeyColumn)
                ->where($casaKeyColumn, '<>', '')
                ->when($applyCasaTypeFilter, function ($query) {
                    $query->where(function ($inner) {
                        $inner->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                })
                ->selectRaw("{$casaIdentitySql} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
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
            ->orderBy('loan_per_cif.source_branch_key')
            ->orderBy('loan_per_cif.uker_key')
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

    private function resolveAvailablePeriod(string $table, string $column, ?string $targetDate): ?string
    {
        $cacheKey = $table . '|' . $column . '|' . ($targetDate ?? '__null__');
        if (array_key_exists($cacheKey, $this->availablePeriodCache)) {
            return $this->availablePeriodCache[$cacheKey];
        }

        try {
            $query = DB::table($table);

            if ($targetDate) {
                $query->where($column, '<=', Carbon::parse($targetDate)->toDateString());
            }

            return $this->availablePeriodCache[$cacheKey] = $query->max($column);
        } catch (Throwable) {
            $this->availablePeriodCache[$cacheKey] = null;
            return null;
        }
    }

    private function resolveAvailableCasaPeriod(string $targetDate): ?string
    {
        if (array_key_exists($targetDate, $this->availableCasaPeriodCache)) {
            return $this->availableCasaPeriodCache[$targetDate];
        }

        try {
            return $this->availableCasaPeriodCache[$targetDate] = DB::table('simpanan_multipn')
                ->where('posisi', '<=', $targetDate)
                ->max('posisi');
        } catch (Throwable) {
            $this->availableCasaPeriodCache[$targetDate] = null;
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
        return LoanQualityBucketMapper::buildSqlExpression();
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
}
