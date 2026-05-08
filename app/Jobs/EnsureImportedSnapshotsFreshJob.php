<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\SimpananMultiPnSnapshotGate;
use App\Support\SsaSimpananSnapshotBuilder;
use App\Support\StrictDateParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EnsureImportedSnapshotsFreshJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 2400;
    public $backoff = [60, 300];

    public function __construct(
        private readonly string $tableName,
        private readonly ?string $periodHint = null,
        private readonly ?string $source = null
    ) {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        $periodScope = StrictDateParser::normalize($this->periodHint) ?? trim((string) $this->periodHint);
        $scope = strtolower(trim($this->tableName)) . ':' . ($periodScope !== '' ? $periodScope : 'latest');

        return [
            new DeferSnapshotJobsDuringImport(),
            (new WithoutOverlapping('snapshot:freshness:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(ReportSnapshotBuilder $builder, DashboardHarianSnapshotService $dashboardHarian): void
    {
        $table = strtolower(trim($this->tableName));

        match ($table) {
            'daily_loan_dinamis' => $this->ensureDailyLoanSnapshots($builder, $dashboardHarian),
            'simpanan_multipn' => $this->ensureSimpananSnapshots($builder, $dashboardHarian),
            'ssa_simpanan' => $this->ensureSsaSnapshots($dashboardHarian, true),
            'ssa_pinjaman' => $this->ensureSsaSnapshots($dashboardHarian, false),
            'lw325_ph' => $this->ensureReportPhSnapshots($dashboardHarian),
            default => null,
        };
    }

    private function ensureDailyLoanSnapshots(ReportSnapshotBuilder $builder, DashboardHarianSnapshotService $dashboardHarian): void
    {
        $period = $this->resolvePeriod('daily_loan_dinamis', 'periode');
        if ($period === null || !$this->sourceHasRows('daily_loan_dinamis', 'periode', $period)) {
            return;
        }

        $checks = [
            'dashboard_pinjaman_snapshots' => fn (): int => $builder->rebuildDashboard($period, true)[$period] ?? 0,
            'dashboard_pinjaman_chart_periodik_snapshots' => fn (): int => $builder->rebuildChartPeriodik($period, true)[$period] ?? 0,
            'performance_rm_snapshots' => fn (): int => $builder->rebuildPerformanceRm($period, true)[$period] ?? 0,
            'rasio_casa_debitur_snapshots' => fn (): int => $builder->rebuildRasioCasa($period, true)[$period] ?? 0,
        ];

        if ($this->dashboardHarianSourcesAreAvailable($period)) {
            $checks['dashboard_harian_snapshots'] = fn (): int => $dashboardHarian->rebuild($period, true)[$period] ?? 0;
        }

        foreach ($checks as $snapshotTable => $rebuild) {
            $periodColumn = $snapshotTable === 'dashboard_harian_snapshots' ? 'snapshot_period'
                : ($snapshotTable === 'rasio_casa_debitur_snapshots' ? 'loan_period' : 'periode');

            $before = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
            if ($before > 0) {
                continue;
            }

            $after = 0;
            $startedAt = microtime(true);

            try {
                $after = (int) $rebuild();
                if ($after <= 0) {
                    $after = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
                }

                ReportDataSyncService::analyzeTable($snapshotTable);

                Log::warning('Auto-recovered missing Daily Loan snapshot.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'source' => $this->source,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto-recovery Daily Loan snapshot failed.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'source' => $this->source,
                ]);

                throw $e;
            }
        }
    }

    private function ensureSimpananSnapshots(ReportSnapshotBuilder $builder, DashboardHarianSnapshotService $dashboardHarian): void
    {
        $period = $this->resolvePeriod('simpanan_multipn', 'posisi');
        if ($period === null || !$this->sourceHasRows('simpanan_multipn', 'posisi', $period)) {
            return;
        }

        $gate = app(SimpananMultiPnSnapshotGate::class);
        if (!$gate->isReady($period)) {
            Log::info('Auto-refresh Simpanan snapshot ditunda karena periode belum lengkap.', [
                'period' => $period,
                'missing_branches' => $gate->getMissingBranches($period),
                'source' => $this->source,
            ]);

            return;
        }

        $checks = [
            'dashboard_simpanan_snapshots' => [
                'period_column' => 'snapshot_period',
                'rebuild' => fn (): int => $builder->rebuildDashboardSimpanan($period, true)[$period] ?? 0,
            ],
            'rekening_dormant_snapshots' => [
                'period_column' => 'posisi',
                'rebuild' => fn (): int => $builder->rebuildRekeningDormant($period, true)[$period] ?? 0,
            ],
            'performance_rm_snapshots' => [
                'period_column' => 'periode',
                'rebuild' => fn (): int => $builder->rebuildPerformanceRm($period, true)[$period] ?? 0,
            ],
            'rasio_casa_debitur_snapshots' => [
                'period_column' => 'casa_period',
                'rebuild' => fn (): int => $builder->rebuildRasioCasa($period, true)[$period] ?? 0,
            ],
        ];

        if ($this->dashboardHarianSourcesAreAvailable($period)) {
            $checks['dashboard_harian_snapshots'] = [
                'period_column' => 'snapshot_period',
                'rebuild' => fn (): int => $dashboardHarian->rebuild($period, true)[$period] ?? 0,
            ];
        }

        foreach ($checks as $snapshotTable => $definition) {
            $periodColumn = (string) $definition['period_column'];
            $before = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
            if ($before > 0) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $after = (int) $definition['rebuild']();
                if ($after <= 0) {
                    $after = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
                }

                ReportDataSyncService::analyzeTable($snapshotTable);

                Log::warning('Auto-recovered missing Simpanan snapshot.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'source' => $this->source,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto-recovery Simpanan snapshot failed.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'source' => $this->source,
                ]);

                throw $e;
            }
        }
    }

    private function ensureSsaSnapshots(DashboardHarianSnapshotService $dashboardHarian, bool $includeSimpananSnapshot): void
    {
        $table = $includeSimpananSnapshot ? 'ssa_simpanan' : 'ssa_pinjaman';
        $periodColumn = $includeSimpananSnapshot ? 'Month_Day_Year_of_Posisi' : 'month_day_year_of_periode';
        $period = $this->resolvePeriod($table, $periodColumn);
        if ($period === null || !$this->sourceHasRows($table, $periodColumn, $period)) {
            return;
        }

        if ($includeSimpananSnapshot) {
            $before = $this->snapshotRowCount('ssa_simpanan_snapshots', 'periode', $period);
            if ($before <= 0) {
                app(SsaSimpananSnapshotBuilder::class)->rebuild($period, true);
                ReportDataSyncService::analyzeTable('ssa_simpanan_snapshots');
            }
        }

        if (!$this->dashboardHarianSourcesAreAvailable($period)) {
            Log::info('Dashboard Harian snapshot ditunda karena SSA periode belum lengkap.', [
                'period' => $period,
                'source_table' => $table,
                'has_ssa_simpanan' => $this->sourceHasRows('ssa_simpanan', 'Month_Day_Year_of_Posisi', $period),
                'has_ssa_pinjaman' => $this->sourceHasRows('ssa_pinjaman', 'month_day_year_of_periode', $period),
                'source' => $this->source,
            ]);

            return;
        }

        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        if ($before > 0) {
            return;
        }

        $after = (int) ($dashboardHarian->rebuild($period, true)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

        Log::warning('Auto-recovered missing Dashboard Harian snapshot after SSA import.', [
            'period' => $period,
            'source_table' => $table,
            'rows_before' => $before,
            'rows_after' => $after,
            'source' => $this->source,
        ]);
    }

    private function ensureReportPhSnapshots(DashboardHarianSnapshotService $dashboardHarian): void
    {
        $period = $this->resolvePeriod('lw325_ph', 'periode');
        if ($period === null || !$this->sourceHasRows('lw325_ph', 'periode', $period)) {
            return;
        }

        $affectedPeriods = $dashboardHarian->resolveAffectedSnapshotPeriodsForPh($period);

        foreach ($affectedPeriods as $snapshotPeriod) {
            $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $snapshotPeriod);
            if ($before > 0) {
                continue;
            }

            $startedAt = microtime(true);
            $after = (int) ($dashboardHarian->rebuild($snapshotPeriod, true)[$snapshotPeriod] ?? 0);
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

            Log::warning('Auto-recovered missing Dashboard Harian snapshot after LW325-PH import.', [
                'ph_period' => $period,
                'snapshot_period' => $snapshotPeriod,
                'rows_before' => $before,
                'rows_after' => $after,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'source' => $this->source,
            ]);
        }
    }

    private function resolvePeriod(string $table, string $column): ?string
    {
        $hint = StrictDateParser::normalize($this->periodHint) ?? trim((string) $this->periodHint);
        if ($hint !== '') {
            return $hint;
        }

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        $latest = DB::table($table)->max($column);

        return StrictDateParser::normalize((string) $latest) ?? (trim((string) $latest) ?: null);
    }

    private function sourceHasRows(string $table, string $periodColumn, string $period): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, $periodColumn)
            && DB::table($table)->where($periodColumn, $period)->exists();
    }

    private function snapshotRowCount(string $table, string $periodColumn, string $period): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $periodColumn)) {
            return 0;
        }

        return (int) DB::table($table)->where($periodColumn, $period)->count();
    }

    private function dashboardHarianSourcesAreAvailable(string $period): bool
    {
        return $this->sourceHasRows('ssa_simpanan', 'Month_Day_Year_of_Posisi', $period)
            && (
                $this->sourceHasRows('ssa_pinjaman', 'month_day_year_of_periode', $period)
                || $this->sourceHasRows('dly_kap_resegmentasi', 'periode', $period)
                || $this->sourceHasRows('l1133', 'periode', $period)
            );
    }
}
