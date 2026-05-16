<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportCacheVersion;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\SnapshotSourceSignatureService;
use App\Support\SimpananMultiPnSnapshotGate;
use App\Support\SsaSimpananSnapshotBuilder;
use App\Support\StrictDateParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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
            new DeferSnapshotJobsDuringImport(sourceTable: $this->tableName),
            (new WithoutOverlapping('snapshot:freshness:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function handle(
        ReportSnapshotBuilder $builder,
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $table = strtolower(trim($this->tableName));

        match ($table) {
            'daily_loan_dinamis' => $this->ensureDailyLoanSnapshots($builder, $dashboardHarian, $sourceSignatures),
            'simpanan_multipn' => $this->ensureSimpananSnapshots($builder, $dashboardHarian, $sourceSignatures),
            'ssa_simpanan' => $this->ensureSsaSnapshots($dashboardHarian, $sourceSignatures, true),
            'ssa_pinjaman' => $this->ensureSsaSnapshots($dashboardHarian, $sourceSignatures, false),
            'hourly_dpk' => $this->ensureHourlyDpkSnapshots($dashboardHarian, $sourceSignatures),
            'lw325_ph' => $this->ensureReportPhSnapshots($dashboardHarian, $sourceSignatures),
            default => null,
        };
    }

    private function ensureDailyLoanSnapshots(
        ReportSnapshotBuilder $builder,
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $period = $this->resolvePeriod('daily_loan_dinamis', 'periode');
        if ($period === null || !$this->sourceHasRows('daily_loan_dinamis', 'periode', $period)) {
            return;
        }

        $this->ensureDailyLoanShadowColumnsReady($period);

        $sourceMetadata = $sourceSignatures->capture('daily_loan_dinamis', 'periode', $period);

        $checks = [
            'dashboard_pinjaman_snapshots' => [
                'period_column' => 'periode',
                'rebuild' => fn (): int => $builder->rebuildDashboard($period, false)[$period] ?? 0,
            ],
            'dashboard_pinjaman_chart_periodik_snapshots' => [
                'period_column' => 'periode',
                'rebuild' => fn (): int => $builder->rebuildChartPeriodik($period, false)[$period] ?? 0,
            ],
            'performance_rm_snapshots' => [
                'period_column' => 'periode',
                'rebuild' => fn (): int => $builder->rebuildPerformanceRm($period, false)[$period] ?? 0,
            ],
            'rasio_casa_debitur_snapshots' => [
                'period_column' => 'loan_period',
                'rebuild' => fn (): int => $builder->rebuildRasioCasa($period, false)[$period] ?? 0,
            ],
        ];

        if ($this->dashboardHarianSourcesAreAvailable($period)) {
            $checks['dashboard_harian_snapshots'] = [
                'period_column' => 'snapshot_period',
                'rebuild' => fn (): int => $dashboardHarian->rebuild($period, false)[$period] ?? 0,
            ];
        }

        $rebuiltAny = false;
        foreach ($checks as $snapshotTable => $definition) {
            $periodColumn = (string) $definition['period_column'];
            $before = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
            $isStale = $before > 0 && !$sourceSignatures->isFresh('daily_loan_dinamis', $snapshotTable, $period, $sourceMetadata);

            if ($before > 0 && !$isStale) {
                continue;
            }

            $after = 0;
            $startedAt = microtime(true);

            try {
                $after = (int) $definition['rebuild']();
                if ($after <= 0) {
                    $after = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
                }

                ReportDataSyncService::analyzeTable($snapshotTable);
                $rebuiltAny = $rebuiltAny || $after > 0;

                if ($after > 0 && $sourceMetadata !== null) {
                    $sourceSignatures->markBuilt('daily_loan_dinamis', $snapshotTable, $period, $sourceMetadata, [
                        'period_column' => $periodColumn,
                        'rows_before' => $before,
                        'rows_after' => $after,
                        'source' => $this->source,
                    ]);
                }

                Log::warning($isStale ? 'Auto-refreshed stale Daily Loan snapshot.' : 'Auto-recovered missing Daily Loan snapshot.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'stale' => $isStale,
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

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion('pinjaman');
        }
    }

    private function ensureDailyLoanShadowColumnsReady(string $period): void
    {
        $missingBefore = $this->countDailyLoanRowsMissingShadowColumns($period);
        if ($missingBefore <= 0) {
            return;
        }

        $exitCode = Artisan::call('shadow:backfill', [
            '--periods' => $period,
            '--chunk-size' => 10000,
            '--retry-count' => 5,
            '--skip-snapshot' => true,
            '--no-interaction' => true,
        ]);

        $missingAfter = $this->countDailyLoanRowsMissingShadowColumns($period);
        if ($exitCode === 0 && $missingAfter <= 0) {
            Log::info('Daily Loan shadow columns completed before freshness rebuild.', [
                'period' => $period,
                'missing_before' => $missingBefore,
                'source' => $this->source,
            ]);

            return;
        }

        $totalRows = (int) DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->count();
        $completion = $totalRows > 0 ? round((100 * ($totalRows - $missingAfter)) / $totalRows, 2) : 100.0;

        throw new \RuntimeException(sprintf(
            'Shadow column Daily Loan periode %s belum siap untuk snapshot Kinerja RM format baru (%.2f%% lengkap, sisa %s row).',
            $period,
            $completion,
            number_format($missingAfter)
        ));
    }

    private function countDailyLoanRowsMissingShadowColumns(string $period): int
    {
        $requiredColumns = [
            'segmen_kinerja',
            'produk_kinerja',
            'cabang_normalized',
            'unit_normalized',
            'branch_normalized',
            'rm_normalized',
            'cifno_clean',
        ];

        if (!Schema::hasTable('daily_loan_dinamis')) {
            return 0;
        }

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return 0;
            }
        }

        return (int) DB::table('daily_loan_dinamis')
            ->where('periode', $period)
            ->where(function ($query) use ($requiredColumns): void {
                foreach ($requiredColumns as $column) {
                    $query->orWhereNull($column);
                }

                if (Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')
                    && Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus1')) {
                    $query->orWhere(function ($pnQuery): void {
                        $pnQuery->whereNull('pn_pemutus_normalized')
                            ->whereRaw("LENGTH(TRIM(COALESCE(pn_pemutus1, ''))) > 0");
                    });
                }

                if (Schema::hasColumn('daily_loan_dinamis', 'shadow_built_at')
                    && Schema::hasColumn('daily_loan_dinamis', 'updated_at')) {
                    $query->orWhereNull('shadow_built_at')
                        ->orWhereColumn('shadow_built_at', '<', 'updated_at');
                }
            })
            ->count();
    }

    private function ensureSimpananSnapshots(
        ReportSnapshotBuilder $builder,
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $period = $this->resolvePeriod('simpanan_multipn', 'posisi');
        if ($period === null || !$this->sourceHasRows('simpanan_multipn', 'posisi', $period)) {
            return;
        }

        $sourceMetadata = $sourceSignatures->capture('simpanan_multipn', 'posisi', $period);

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
                'rebuild' => fn (): int => $builder->rebuildDashboardSimpanan($period, false)[$period] ?? 0,
            ],
            'rekening_dormant_snapshots' => [
                'period_column' => 'posisi',
                'rebuild' => fn (): int => $builder->rebuildRekeningDormant($period, false)[$period] ?? 0,
            ],
            'performance_rm_snapshots' => [
                'period_column' => 'periode',
                'rebuild' => fn (): int => $builder->rebuildPerformanceRm($period, false)[$period] ?? 0,
            ],
            'rasio_casa_debitur_snapshots' => [
                'period_column' => 'casa_period',
                'rebuild' => fn (): int => $builder->rebuildRasioCasa($period, false)[$period] ?? 0,
            ],
        ];

        if ($this->dashboardHarianSourcesAreAvailable($period)) {
            $checks['dashboard_harian_snapshots'] = [
                'period_column' => 'snapshot_period',
                'rebuild' => fn (): int => $dashboardHarian->rebuild($period, false)[$period] ?? 0,
            ];
        }

        $rebuiltAny = false;
        foreach ($checks as $snapshotTable => $definition) {
            $periodColumn = (string) $definition['period_column'];
            $before = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
            $isStale = $before > 0 && !$sourceSignatures->isFresh('simpanan_multipn', $snapshotTable, $period, $sourceMetadata);

            if ($before > 0 && !$isStale) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $after = (int) $definition['rebuild']();
                if ($after <= 0) {
                    $after = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
                }

                ReportDataSyncService::analyzeTable($snapshotTable);
                $rebuiltAny = $rebuiltAny || $after > 0;

                if ($after > 0 && $sourceMetadata !== null) {
                    $sourceSignatures->markBuilt('simpanan_multipn', $snapshotTable, $period, $sourceMetadata, [
                        'period_column' => $periodColumn,
                        'rows_before' => $before,
                        'rows_after' => $after,
                        'source' => $this->source,
                    ]);
                }

                Log::warning($isStale ? 'Auto-refreshed stale Simpanan snapshot.' : 'Auto-recovered missing Simpanan snapshot.', [
                    'snapshot_table' => $snapshotTable,
                    'period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'stale' => $isStale,
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

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion('simpanan');
        }
    }

    private function ensureSsaSnapshots(
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures,
        bool $includeSimpananSnapshot
    ): void
    {
        $table = $includeSimpananSnapshot ? 'ssa_simpanan' : 'ssa_pinjaman';
        $periodColumn = $includeSimpananSnapshot ? 'Month_Day_Year_of_Posisi' : 'month_day_year_of_periode';
        $period = $this->resolvePeriod($table, $periodColumn);
        if ($period === null || !$this->sourceHasRows($table, $periodColumn, $period)) {
            return;
        }

        $sourceMetadata = $sourceSignatures->capture($table, $periodColumn, $period);
        $rebuiltAny = false;

        if ($includeSimpananSnapshot) {
            $before = $this->snapshotRowCount('ssa_simpanan_snapshots', 'periode', $period);
            $isStale = $before > 0 && !$sourceSignatures->isFresh('ssa_simpanan', 'ssa_simpanan_snapshots', $period, $sourceMetadata);
            if ($before <= 0 || $isStale) {
                app(SsaSimpananSnapshotBuilder::class)->rebuild($period, false);
                $after = $this->snapshotRowCount('ssa_simpanan_snapshots', 'periode', $period);
                ReportDataSyncService::analyzeTable('ssa_simpanan_snapshots');
                $rebuiltAny = $rebuiltAny || $after > 0;

                if ($after > 0 && $sourceMetadata !== null) {
                    $sourceSignatures->markBuilt('ssa_simpanan', 'ssa_simpanan_snapshots', $period, $sourceMetadata, [
                        'period_column' => 'periode',
                        'rows_before' => $before,
                        'rows_after' => $after,
                        'source' => $this->source,
                    ]);
                }
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

            if ($rebuiltAny) {
                $this->bumpReportCacheVersion($includeSimpananSnapshot ? 'simpanan' : 'harian');
            }

            return;
        }

        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        $isStale = $before > 0 && !$sourceSignatures->isFresh($table, 'dashboard_harian_snapshots', $period, $sourceMetadata);
        if ($before > 0 && !$isStale) {
            if ($rebuiltAny) {
                $this->bumpReportCacheVersion($includeSimpananSnapshot ? 'simpanan' : 'harian');
            }

            return;
        }

        $after = (int) ($dashboardHarian->rebuild($period, false)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
        $rebuiltAny = $rebuiltAny || $after > 0;

        if ($after > 0 && $sourceMetadata !== null) {
            $sourceSignatures->markBuilt($table, 'dashboard_harian_snapshots', $period, $sourceMetadata, [
                'period_column' => 'snapshot_period',
                'rows_before' => $before,
                'rows_after' => $after,
                'source' => $this->source,
            ]);
        }

        Log::warning($isStale ? 'Auto-refreshed stale Dashboard Harian snapshot after SSA import.' : 'Auto-recovered missing Dashboard Harian snapshot after SSA import.', [
            'period' => $period,
            'source_table' => $table,
            'rows_before' => $before,
            'rows_after' => $after,
            'stale' => $isStale,
            'source' => $this->source,
        ]);

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion($includeSimpananSnapshot ? 'simpanan' : 'harian');
        }
    }

    private function ensureReportPhSnapshots(
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $period = $this->resolvePeriod('lw325_ph', 'periode');
        if ($period === null || !$this->sourceHasRows('lw325_ph', 'periode', $period)) {
            return;
        }

        $sourceMetadata = $sourceSignatures->capture('lw325_ph', 'periode', $period);
        $affectedPeriods = $dashboardHarian->resolveAffectedSnapshotPeriodsForPh($period);
        $rebuiltAny = false;

        foreach ($affectedPeriods as $snapshotPeriod) {
            $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $snapshotPeriod);
            $isStale = $before > 0 && !$sourceSignatures->isFresh('lw325_ph', 'dashboard_harian_snapshots', $snapshotPeriod, $sourceMetadata);
            if ($before > 0 && !$isStale) {
                continue;
            }

            $startedAt = microtime(true);
            $after = (int) ($dashboardHarian->rebuild($snapshotPeriod, false)[$snapshotPeriod] ?? 0);
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
            $rebuiltAny = $rebuiltAny || $after > 0;

            if ($after > 0 && $sourceMetadata !== null) {
                $sourceSignatures->markBuilt('lw325_ph', 'dashboard_harian_snapshots', $snapshotPeriod, $sourceMetadata, [
                    'period_column' => 'snapshot_period',
                    'source_period' => $period,
                    'rows_before' => $before,
                    'rows_after' => $after,
                    'source' => $this->source,
                ]);
            }

            Log::warning($isStale ? 'Auto-refreshed stale Dashboard Harian snapshot after LW325-PH import.' : 'Auto-recovered missing Dashboard Harian snapshot after LW325-PH import.', [
                'ph_period' => $period,
                'snapshot_period' => $snapshotPeriod,
                'rows_before' => $before,
                'rows_after' => $after,
                'stale' => $isStale,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'source' => $this->source,
            ]);
        }

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion('pinjaman');
        }
    }

    private function ensureHourlyDpkSnapshots(
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $period = $this->resolvePeriod('hourly_dpk', 'posisi');
        if ($period === null || !$this->sourceHasRows('hourly_dpk', 'posisi', $period)) {
            return;
        }

        if (!$this->dashboardHarianSourcesAreAvailable($period)) {
            Log::info('Dashboard Harian snapshot ditunda karena sumber Hourly DPK/loan periode belum lengkap.', [
                'period' => $period,
                'source_table' => 'hourly_dpk',
                'has_hourly_dpk' => $this->sourceHasRows('hourly_dpk', 'posisi', $period),
                'has_dly_kap' => $this->sourceHasRows('dly_kap_resegmentasi', 'periode', $period),
                'has_l1133' => $this->sourceHasRows('l1133', 'periode', $period),
                'source' => $this->source,
            ]);

            return;
        }

        $sourceMetadata = $sourceSignatures->capture('hourly_dpk', 'posisi', $period);
        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        $isStale = $before > 0 && !$sourceSignatures->isFresh('hourly_dpk', 'dashboard_harian_snapshots', $period, $sourceMetadata);
        if ($before > 0 && !$isStale) {
            return;
        }

        $after = (int) ($dashboardHarian->rebuild($period, false)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

        if ($after > 0 && $sourceMetadata !== null) {
            $sourceSignatures->markBuilt('hourly_dpk', 'dashboard_harian_snapshots', $period, $sourceMetadata, [
                'period_column' => 'snapshot_period',
                'rows_before' => $before,
                'rows_after' => $after,
                'source' => $this->source,
            ]);

            $this->bumpReportCacheVersion('simpanan');
        }

        Log::warning($isStale ? 'Auto-refreshed stale Dashboard Harian snapshot after Hourly DPK import.' : 'Auto-recovered missing Dashboard Harian snapshot after Hourly DPK import.', [
            'period' => $period,
            'rows_before' => $before,
            'rows_after' => $after,
            'stale' => $isStale,
            'source' => $this->source,
        ]);
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

    private function snapshotIsOlderThanDailyLoanSource(string $snapshotTable, string $periodColumn, string $period): bool
    {
        if (!Schema::hasTable($snapshotTable)
            || !Schema::hasColumn($snapshotTable, $periodColumn)
            || !Schema::hasTable('daily_loan_dinamis')) {
            return false;
        }

        $snapshotUpdatedAt = null;
        if (Schema::hasColumn($snapshotTable, 'updated_at')) {
            $snapshotUpdatedAt = DB::table($snapshotTable)
                ->where($periodColumn, $period)
                ->max('updated_at');
        }

        if ($snapshotUpdatedAt === null) {
            return false;
        }

        $hasUpdatedAt = Schema::hasColumn('daily_loan_dinamis', 'updated_at');
        $hasCreatedAt = Schema::hasColumn('daily_loan_dinamis', 'created_at');

        if (!$hasUpdatedAt && !$hasCreatedAt) {
            return false;
        }

        return DB::table('daily_loan_dinamis')
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

    private function bumpReportCacheVersion(string $scope = 'global'): void
    {
        ReportCacheVersion::bump($scope);
    }

    private function dashboardHarianSourcesAreAvailable(string $period): bool
    {
        $hasSsaSavings = $this->sourceHasRows('ssa_simpanan', 'Month_Day_Year_of_Posisi', $period);
        $hasHourlySavings = $this->sourceHasRows('hourly_dpk', 'posisi', $period);
        $hasSsaLoan = $this->sourceHasRows('ssa_pinjaman', 'month_day_year_of_periode', $period);
        $hasFallbackLoan = $this->sourceHasRows('dly_kap_resegmentasi', 'periode', $period)
            || $this->sourceHasRows('l1133', 'periode', $period);

        return ($hasSsaSavings && ($hasSsaLoan || $hasFallbackLoan))
            || (!$hasSsaSavings && $hasHourlySavings && $hasFallbackLoan);
    }
}
