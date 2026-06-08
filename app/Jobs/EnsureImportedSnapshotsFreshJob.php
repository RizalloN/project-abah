<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Support\DashboardHarianSnapshotService;
use App\Support\ReportCacheVersion;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use App\Support\SnapshotIntegrityGuard;
use App\Support\SnapshotSourceSignatureService;
use App\Support\SimpananMultiPnSnapshotGate;
use App\Support\SsaSimpananSnapshotBuilder;
use App\Support\StrictDateParser;
use Carbon\Carbon;
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

    public $tries = 35;
    public $timeout = 2400;
    public $backoff = [60, 300];

    private ?string $resolvedPeriodScope = null;

    public function __construct(
        private readonly string $tableName,
        private readonly ?string $periodHint = null,
        private readonly ?string $source = null
    ) {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        $periodScope = $this->resolveLockPeriodScope();
        $scope = strtolower(trim($this->tableName)) . ':' . ($periodScope !== '' ? $periodScope : 'latest');

        return [
            new DeferSnapshotJobsDuringImport(sourceTable: $this->tableName),
            (new WithoutOverlapping('snapshot:freshness:' . $scope))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
            (new WithoutOverlapping('snapshot:freshness:period:' . ($periodScope !== '' ? $periodScope : 'latest')))
                ->releaseAfter(60)
                ->expireAfter($this->timeout + 300),
        ];
    }

    private function resolveLockPeriodScope(): string
    {
        if ($this->resolvedPeriodScope !== null) {
            return $this->resolvedPeriodScope;
        }

        $periodScope = StrictDateParser::normalize($this->periodHint) ?? trim((string) $this->periodHint);
        if ($periodScope !== '') {
            return $this->resolvedPeriodScope = $periodScope;
        }

        $table = strtolower(trim($this->tableName));
        $sourceDefinition = $this->sourceDefinition($table);
        if ($sourceDefinition === null) {
            return $this->resolvedPeriodScope = '';
        }

        return $this->resolvedPeriodScope = ($this->resolvePeriod($table, $sourceDefinition['period_column']) ?? '');
    }

    public function handle(
        ReportSnapshotBuilder $builder,
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $table = strtolower(trim($this->tableName));
        $sourceDefinition = $this->sourceDefinition($table);
        $periodScope = $sourceDefinition !== null
            ? ($this->resolvePeriod($table, $sourceDefinition['period_column']) ?? '')
            : '';

        if ($periodScope !== ''
            && $sourceDefinition !== null
            && !$this->sourceHasRows($table, $sourceDefinition['period_column'], $periodScope)) {
            $this->handleDeletedSourcePeriod($dashboardHarian, $table, $periodScope);

            return;
        }

        match ($table) {
            'daily_loan_dinamis' => $this->ensureDailyLoanSnapshots($builder, $dashboardHarian, $sourceSignatures),
            'simpanan_multipn' => $this->ensureSimpananSnapshots($builder, $dashboardHarian, $sourceSignatures),
            'ssa_simpanan' => $this->ensureSsaSnapshots($dashboardHarian, $sourceSignatures, true),
            'ssa_pinjaman' => $this->ensureSsaSnapshots($dashboardHarian, $sourceSignatures, false),
            'hourly_dpk' => $this->ensureHourlyDpkSnapshots($dashboardHarian, $sourceSignatures),
            'lw325_ph' => $this->ensureReportPhSnapshots($dashboardHarian, $sourceSignatures),
            'gi405_recovery' => $this->ensureGi405RecoverySnapshots($dashboardHarian, $sourceSignatures),
            'dly_kap_resegmentasi' => $this->ensureFallbackLoanSnapshots($dashboardHarian, 'dly_kap_resegmentasi'),
            'l1133' => $this->ensureFallbackLoanSnapshots($dashboardHarian, 'l1133'),
            default => null,
        };
    }

    private function handleDeletedSourcePeriod(
        DashboardHarianSnapshotService $dashboardHarian,
        string $table,
        string $period
    ): void {
        $deletedRows = 0;

        $cleanupMap = match ($table) {
            'daily_loan_dinamis' => [
                'dashboard_pinjaman_snapshots' => 'periode',
                'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
                'performance_rm_snapshots' => 'periode',
                'rasio_casa_debitur_snapshots' => 'loan_period',
            ],
            'simpanan_multipn' => [
                'dashboard_simpanan_snapshots' => 'snapshot_period',
                'rekening_dormant_snapshots' => 'posisi',
                'performance_rm_snapshots' => 'periode',
                'rasio_casa_debitur_snapshots' => 'casa_period',
            ],
            'ssa_simpanan' => [
                'ssa_simpanan_snapshots' => 'periode',
            ],
            default => [],
        };

        foreach ($cleanupMap as $snapshotTable => $periodColumn) {
            $deletedRows += $this->deleteSnapshotPeriod($snapshotTable, $periodColumn, $period);
        }

        $affectedDashboardPeriods = $this->affectedDashboardHarianPeriodsForDeletedSource($dashboardHarian, $table, $period);
        foreach ($affectedDashboardPeriods as $snapshotPeriod) {
            $dashboardHarian->rebuild($snapshotPeriod, true);
        }

        $this->forgetSourceSignatures($table, $period);

        if ($deletedRows > 0 || $affectedDashboardPeriods !== []) {
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
            $this->bumpReportCacheVersion($this->cacheScopeForTable($table));
        }

        Log::warning('Auto-maintained snapshots after source period was deleted.', [
            'source_table' => $table,
            'period' => $period,
            'deleted_snapshot_rows' => $deletedRows,
            'dashboard_harian_periods' => $affectedDashboardPeriods,
            'source' => $this->source,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function affectedDashboardHarianPeriodsForDeletedSource(
        DashboardHarianSnapshotService $dashboardHarian,
        string $table,
        string $period
    ): array {
        $periods = match ($table) {
            'lw325_ph' => $dashboardHarian->resolveAffectedSnapshotPeriodsForPh($period),
            'dly_kap_resegmentasi', 'l1133' => $dashboardHarian->resolveAffectedSnapshotPeriodsForLoanFallback($table, $period),
            default => [$period],
        };

        if ($table === 'lw325_ph' && $periods === []) {
            $end = Carbon::parse($period)->addMonthNoOverflow()->endOfMonth()->toDateString();
            $periods = DB::table('dashboard_harian_snapshots')
                ->whereBetween('snapshot_period', [$period, $end])
                ->select('snapshot_period')
                ->distinct()
                ->pluck('snapshot_period')
                ->map(fn ($value): string => Carbon::parse($value)->toDateString())
                ->all();
        }

        if ($periods === []) {
            $periods = [$period];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($value): string => trim((string) $value), $periods),
            fn (string $value): bool => $value !== ''
        )));
    }

    private function deleteSnapshotPeriod(string $snapshotTable, string $periodColumn, string $period): int
    {
        if (!Schema::hasTable($snapshotTable) || !Schema::hasColumn($snapshotTable, $periodColumn)) {
            return 0;
        }

        $deleted = (int) DB::table($snapshotTable)
            ->where($periodColumn, $period)
            ->delete();

        if ($deleted > 0) {
            ReportDataSyncService::analyzeTable($snapshotTable);
        }

        return $deleted;
    }

    private function forgetSourceSignatures(string $sourceTable, string $period): void
    {
        if (!Schema::hasTable('snapshot_source_signatures')) {
            return;
        }

        DB::table('snapshot_source_signatures')
            ->where('source_table', $sourceTable)
            ->where('period_key', $period)
            ->delete();
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
            $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly($snapshotTable, $period);
            $isFresh = !$hasAnomaly
                && $sourceSignatures->isFresh('daily_loan_dinamis', $snapshotTable, $period, $sourceMetadata, $before);
            $isStale = $hasAnomaly || !$isFresh;

            if ($isFresh) {
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
                $rebuiltAny = true;

                if ($sourceMetadata !== null) {
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
                    'snapshot_anomaly' => $hasAnomaly,
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
            $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly($snapshotTable, $period);
            $isFresh = !$hasAnomaly
                && $sourceSignatures->isFresh('simpanan_multipn', $snapshotTable, $period, $sourceMetadata, $before);
            $isStale = $hasAnomaly || !$isFresh;

            if ($isFresh) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $after = (int) $definition['rebuild']();
                if ($after <= 0) {
                    $after = $this->snapshotRowCount($snapshotTable, $periodColumn, $period);
                }

                ReportDataSyncService::analyzeTable($snapshotTable);
                $rebuiltAny = true;

                if ($sourceMetadata !== null) {
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
                    'snapshot_anomaly' => $hasAnomaly,
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
            $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly('ssa_simpanan_snapshots', $period);
            $isFresh = !$hasAnomaly
                && $sourceSignatures->isFresh('ssa_simpanan', 'ssa_simpanan_snapshots', $period, $sourceMetadata, $before);
            if (!$isFresh) {
                app(SsaSimpananSnapshotBuilder::class)->rebuild($period, false);
                $after = $this->snapshotRowCount('ssa_simpanan_snapshots', 'periode', $period);
                ReportDataSyncService::analyzeTable('ssa_simpanan_snapshots');
                $rebuiltAny = true;

                if ($sourceMetadata !== null) {
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
                'has_l1133' => $this->sourceHasRows('l1133', 'periode', $period),
                'has_dly_kap' => $this->sourceHasRows('dly_kap_resegmentasi', 'periode', $period),
                'has_gi405_recovery' => $this->sourceHasRows('gi405_recovery', 'periode', $period),
                'source' => $this->source,
            ]);

            if ($rebuiltAny) {
                $this->bumpReportCacheVersion($includeSimpananSnapshot ? 'simpanan' : 'harian');
            }

            return;
        }

        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly('dashboard_harian_snapshots', $period);
        $isFresh = !$hasAnomaly
            && $sourceSignatures->isFresh($table, 'dashboard_harian_snapshots', $period, $sourceMetadata, $before);
        $isStale = $hasAnomaly || !$isFresh;
        if ($isFresh) {
            if ($rebuiltAny) {
                $this->bumpReportCacheVersion($includeSimpananSnapshot ? 'simpanan' : 'harian');
            }

            return;
        }

        $after = (int) ($dashboardHarian->rebuild($period, false)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
        $rebuiltAny = true;

        if ($sourceMetadata !== null) {
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
            'snapshot_anomaly' => $hasAnomaly,
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
            $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly('dashboard_harian_snapshots', $snapshotPeriod);
            $isFresh = !$hasAnomaly
                && $sourceSignatures->isFresh('lw325_ph', 'dashboard_harian_snapshots', $snapshotPeriod, $sourceMetadata, $before);
            $isStale = $hasAnomaly || !$isFresh;
            if ($isFresh) {
                continue;
            }

            $startedAt = microtime(true);
            $after = (int) ($dashboardHarian->rebuild($snapshotPeriod, false)[$snapshotPeriod] ?? 0);
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
            $rebuiltAny = true;

            if ($sourceMetadata !== null) {
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
                'snapshot_anomaly' => $hasAnomaly,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'source' => $this->source,
            ]);
        }

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion('harian');
        }
    }

    private function ensureGi405RecoverySnapshots(
        DashboardHarianSnapshotService $dashboardHarian,
        SnapshotSourceSignatureService $sourceSignatures
    ): void
    {
        $period = $this->resolvePeriod('gi405_recovery', 'periode');
        if ($period === null || !$this->sourceHasRows('gi405_recovery', 'periode', $period)) {
            return;
        }

        $sourceMetadata = $sourceSignatures->capture('gi405_recovery', 'periode', $period);
        $rebuiltAny = false;

        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly('dashboard_harian_snapshots', $period);
        $isFresh = !$hasAnomaly
            && $sourceSignatures->isFresh('gi405_recovery', 'dashboard_harian_snapshots', $period, $sourceMetadata, $before);
        $isStale = $hasAnomaly || !$isFresh;

        if ($isFresh) {
            return;
        }

        $startedAt = microtime(true);
        $after = (int) ($dashboardHarian->rebuild($period, false)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
        $rebuiltAny = true;

        if ($sourceMetadata !== null) {
            $sourceSignatures->markBuilt('gi405_recovery', 'dashboard_harian_snapshots', $period, $sourceMetadata, [
                'period_column' => 'snapshot_period',
                'source_period' => $period,
                'rows_before' => $before,
                'rows_after' => $after,
                'source' => $this->source,
            ]);
        }

        Log::warning($isStale ? 'Auto-refreshed stale Dashboard Harian snapshot after GI405 Recovery import.' : 'Auto-recovered missing Dashboard Harian snapshot after GI405 Recovery import.', [
            'recovery_period' => $period,
            'snapshot_period' => $period,
            'rows_before' => $before,
            'rows_after' => $after,
            'stale' => $isStale,
            'snapshot_anomaly' => $hasAnomaly,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => $this->source,
        ]);

        if ($rebuiltAny) {
            $this->bumpReportCacheVersion('harian');
        }
    }

    private function ensureFallbackLoanSnapshots(DashboardHarianSnapshotService $dashboardHarian, string $sourceTable): void
    {
        $period = $this->resolvePeriod($sourceTable, 'periode');
        if ($period === null || !$this->sourceHasRows($sourceTable, 'periode', $period)) {
            return;
        }

        $affectedPeriods = $dashboardHarian->resolveAffectedSnapshotPeriodsForLoanFallback($sourceTable, $period);
        if ($affectedPeriods === []) {
            return;
        }

        $startedAt = microtime(true);
        $result = $dashboardHarian->syncDuePeriods($affectedPeriods);
        $rebuilt = (int) ($result['built'] ?? 0);

        if ($rebuilt > 0) {
            ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');
            $this->bumpReportCacheVersion('harian');
        }

        Log::warning('Auto-checked Dashboard Harian snapshots after fallback loan import.', [
            'source_table' => $sourceTable,
            'source_period' => $period,
            'affected_periods' => $affectedPeriods,
            'built' => $rebuilt,
            'failed' => (int) ($result['failed'] ?? 0),
            'stale' => $result['stale'] ?? [],
            'missing' => $result['missing'] ?? [],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'source' => $this->source,
        ]);
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
                'has_gi405_recovery' => $this->sourceHasRows('gi405_recovery', 'periode', $period),
                'source' => $this->source,
            ]);

            return;
        }

        $sourceMetadata = $sourceSignatures->capture('hourly_dpk', 'posisi', $period);
        $before = $this->snapshotRowCount('dashboard_harian_snapshots', 'snapshot_period', $period);
        $hasAnomaly = $before > 0 && $this->snapshotHasAnomaly('dashboard_harian_snapshots', $period);
        $isFresh = !$hasAnomaly
            && $sourceSignatures->isFresh('hourly_dpk', 'dashboard_harian_snapshots', $period, $sourceMetadata, $before);
        $isStale = $hasAnomaly || !$isFresh;
        if ($isFresh) {
            return;
        }

        $after = (int) ($dashboardHarian->rebuild($period, false)[$period] ?? 0);
        ReportDataSyncService::analyzeTable('dashboard_harian_snapshots');

        if ($sourceMetadata !== null) {
            $sourceSignatures->markBuilt('hourly_dpk', 'dashboard_harian_snapshots', $period, $sourceMetadata, [
                'period_column' => 'snapshot_period',
                'rows_before' => $before,
                'rows_after' => $after,
                'source' => $this->source,
            ]);
        }

        $this->bumpReportCacheVersion('simpanan');

        Log::warning($isStale ? 'Auto-refreshed stale Dashboard Harian snapshot after Hourly DPK import.' : 'Auto-recovered missing Dashboard Harian snapshot after Hourly DPK import.', [
            'period' => $period,
            'rows_before' => $before,
            'rows_after' => $after,
            'stale' => $isStale,
            'snapshot_anomaly' => $hasAnomaly,
            'source' => $this->source,
        ]);
    }

    private function resolvePeriod(string $table, string $column): ?string
    {
        $hint = StrictDateParser::normalize($this->periodHint) ?? trim((string) $this->periodHint);
        if ($hint !== '') {
            return $this->resolvedPeriodScope = $hint;
        }

        if ($this->resolvedPeriodScope !== null) {
            return $this->resolvedPeriodScope !== '' ? $this->resolvedPeriodScope : null;
        }

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            $this->resolvedPeriodScope = '';

            return null;
        }

        $latest = DB::table($table)->max($column);

        $resolved = StrictDateParser::normalize((string) $latest) ?? (trim((string) $latest) ?: null);
        $this->resolvedPeriodScope = $resolved ?? '';

        return $resolved;
    }

    private function sourceHasRows(string $table, string $periodColumn, string $period): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, $periodColumn)
            && DB::table($table)->where($periodColumn, $period)->exists();
    }

    /**
     * @return array{period_column:string}|null
     */
    private function sourceDefinition(string $table): ?array
    {
        return match (strtolower(trim($table))) {
            'daily_loan_dinamis' => ['period_column' => 'periode'],
            'simpanan_multipn' => ['period_column' => 'posisi'],
            'ssa_simpanan' => ['period_column' => 'Month_Day_Year_of_Posisi'],
            'ssa_pinjaman' => ['period_column' => 'month_day_year_of_periode'],
            'hourly_dpk' => ['period_column' => 'posisi'],
            'lw325_ph', 'gi405_recovery', 'dly_kap_resegmentasi', 'l1133' => ['period_column' => 'periode'],
            default => null,
        };
    }

    private function snapshotRowCount(string $table, string $periodColumn, string $period): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $periodColumn)) {
            return 0;
        }

        return (int) DB::table($table)->where($periodColumn, $period)->count();
    }

    private function snapshotHasAnomaly(string $snapshotTable, string $period): bool
    {
        try {
            return app(SnapshotIntegrityGuard::class)->logIfAnomalous($snapshotTable, $period, [
                'job' => static::class,
                'source_table' => $this->tableName,
                'source' => $this->source,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
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

    private function cacheScopeForTable(string $tableName): string
    {
        return match (strtolower(trim($tableName))) {
            'daily_loan_dinamis', 'ssa_pinjaman', 'lw325_ph', 'gi405_recovery' => 'pinjaman',
            'simpanan_multipn', 'ssa_simpanan', 'hourly_dpk' => 'simpanan',
            'dly_kap_resegmentasi', 'l1133' => 'harian',
            default => 'global',
        };
    }

    private function dashboardHarianSourcesAreAvailable(string $period): bool
    {
        $hasSsaSavings = $this->sourceHasRows('ssa_simpanan', 'Month_Day_Year_of_Posisi', $period);
        $hasSsaLoan = $this->sourceHasRows('ssa_pinjaman', 'month_day_year_of_periode', $period);
        $hasDlyKap = $this->sourceHasRows('dly_kap_resegmentasi', 'periode', $period);
        $hasL1133 = $this->sourceHasRows('l1133', 'periode', $period);
        $hasGi405Recovery = !Schema::hasTable('gi405_recovery')
            || $this->sourceHasRows('gi405_recovery', 'periode', $period);

        return $hasSsaSavings
            && $hasGi405Recovery
            && ($hasSsaLoan || ($hasDlyKap && $hasL1133));
    }
}
