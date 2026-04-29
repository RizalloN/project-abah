<?php

namespace App\Support;

use App\Jobs\WarmReportCacheJob;
use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportProgressService;
use App\Support\ParallelSnapshotBatchCoordinator;
use App\Support\SimpananMultiPnSnapshotGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportDataSyncService
{
    public static function analyzeTable(string $tableName): bool
    {
        if (!Schema::hasTable($tableName)) {
            return false;
        }

        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        try {
            DB::statement('ANALYZE TABLE `' . str_replace('`', '``', $tableName) . '`');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private const AUDIT_TABLE = 'report_sync_audits';
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const CHART_PERIODIK_SNAPSHOT_TABLE = 'dashboard_pinjaman_chart_periodik_snapshots';
    private const DASHBOARD_SIMPANAN_SNAPSHOT_TABLE = 'dashboard_simpanan_snapshots';
    private const DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const DASHBOARD_HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const RASIO_UKER_SNAPSHOT_TABLE = 'rasio_casa_debitur_uker_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const NEW_PAYROLL_SNAPSHOT_TABLE = 'performance_new_payroll_snapshots';
    private const PERFORMANCE_RM_SNAPSHOT_TABLE = 'performance_rm_snapshots';
    private const SSA_SIMPANAN_SNAPSHOT_TABLE = 'ssa_simpanan_snapshots';
    private const CACHE_VERSION_KEY = 'report_cache_version:global';
    private const RASIO_REBUILD_LOCK_PREFIX = 'snapshot:rasio:rebuild:';
    private const SIMPANAN_REBUILD_LOCK_PREFIX = 'snapshot:simpanan:rebuild:';
    private const ANALYZE_THROTTLE_SECONDS = 600;
    private const POST_DELETE_SNAPSHOT_REPORTS = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'ssa_simpanan',
        'ssa_pinjaman',
        'lw325_ph',
        'performance_pis_per_produk',
    ];

    public function __construct(
        private readonly ReportSnapshotBuilder $snapshotBuilder,
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService,
        private readonly PartitionMaintenanceService $partitionMaintenanceService,
        private readonly DashboardHarianSnapshotDirtyPeriodQueue $dashboardHarianDirtyPeriods
    ) {
    }

    public function syncImportedJob(int $jobId, ?string $fallbackTableName = null, ?string $periodHint = null, ?string $source = null, ?string $rebuildId = null): void
    {
        if ($jobId <= 0) {
            if ($fallbackTableName) {
                $this->syncImportedTable(
                    tableName: $fallbackTableName,
                    periodHint: $periodHint,
                    jobId: null,
                    source: $source,
                    deleteId: null,
                    rebuildId: $rebuildId
                );
            }

            return;
        }

        $tableName = $fallbackTableName;

        try {
            $job = DB::table('import_jobs as ij')
                ->leftJoin('nama_report as nr', 'nr.id_report', '=', 'ij.id_report')
                ->where('ij.id', $jobId)
                ->select('nr.table_name')
                ->first();

            $resolvedTable = trim((string) ($job->table_name ?? ''));
            if ($resolvedTable !== '') {
                $tableName = $resolvedTable;
            }
        } catch (Throwable $e) {
            Log::warning('Gagal membaca metadata import job untuk sinkronisasi report: ' . $e->getMessage(), [
                'job_id' => $jobId,
                'fallback_table' => $fallbackTableName,
            ]);
        }

        if ($tableName) {
            $this->syncImportedTable(
                tableName: $tableName,
                periodHint: $periodHint,
                jobId: $jobId,
                source: $source,
                deleteId: null,
                rebuildId: $rebuildId
            );
        }
    }

    public function syncImportedTable(string $tableName, ?string $periodHint = null, ?int $jobId = null, ?string $source = null, ?string $deleteId = null, ?string $rebuildId = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
        }

        $periodHint = $this->normalizeAuditPeriodHint($periodHint);

        if ($this->shouldDeferSnapshotSync($jobId, $deleteId, $rebuildId)) {
            $this->dispatchDeferredSnapshotSync($jobId, $normalizedTable, $periodHint, $source, $deleteId, $rebuildId);
            return;
        }

        if ($deleteId) {
            $this->heartbeat($deleteId, 'Starting report synchronization...');
        }

        if ($rebuildId) {
            $this->heartbeat($rebuildId, 'Starting report synchronization into snapshot...');
        }

        $this->refreshTableStatistics($normalizedTable, $periodHint, $jobId, $source);

        try {
            $newVersion = $this->bumpReportCacheVersion();
            $this->writeAudit($normalizedTable, $periodHint, $jobId, $source, 'cache_invalidate', 'success', [
                'context' => ['cache_version' => $newVersion],
            ]);
        } catch (Throwable $e) {
            $this->writeAudit($normalizedTable, $periodHint, $jobId, $source, 'cache_invalidate', 'failed', [
                'message' => $e->getMessage(),
            ]);
            Log::warning('Gagal invalidasi cache report setelah import: ' . $e->getMessage(), [
                'table' => $normalizedTable,
            ]);
        }

        try {
            match ($normalizedTable) {
                'daily_loan_dinamis' => $this->syncDailyLoan($periodHint, $jobId, $source, $deleteId),
                'loan_type' => $this->syncLoanType($periodHint, $jobId, $source, $deleteId),
                'simpanan_multipn' => $this->syncSimpanan($periodHint, $jobId, $source, $deleteId),
                'ssa_simpanan' => $this->syncSsaSimpanan($periodHint, $jobId, $source, $deleteId),
                'ssa_pinjaman' => $this->syncSsaPinjaman($periodHint, $jobId, $source, $deleteId),
                'lw325_ph' => $this->syncReportPh($periodHint, $jobId, $source, $deleteId),
                'performance_pis_per_produk' => $this->syncPerformanceNewPayroll($periodHint, $jobId, $source, $deleteId),
                'rka' => app(\App\Support\OptimizedRkaLookupService::class)->invalidateCache(),
                default => null,
            };

            if (!in_array($normalizedTable, ['daily_loan_dinamis', 'simpanan_multipn'], true)) {
                WarmReportCacheJob::dispatch();
            }

        } catch (Throwable $e) {
            $this->writeAudit($normalizedTable, $periodHint, $jobId, $source, 'snapshot_sync', 'failed', [
                'message' => $e->getMessage(),
            ]);
            Log::error('Sinkronisasi snapshot report gagal: ' . $e->getMessage(), [
                'table' => $normalizedTable,
                'period_hint' => $periodHint,
            ]);
        }
    }

    private function shouldDeferSnapshotSync(?int $jobId, ?string $deleteId = null, ?string $rebuildId = null): bool
    {
        try {
            return app(ImportProgressService::class)->hasActiveProcessingJobs();
        } catch (Throwable $e) {
            Log::debug('Gagal mengecek status import aktif saat sinkronisasi snapshot.', [
                'job_id' => $jobId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function dispatchDeferredSnapshotSync(
        ?int $jobId,
        string $tableName,
        ?string $periodHint,
        ?string $source,
        ?string $deleteId,
        ?string $rebuildId
    ): void {
        try {
            SyncImportedReportJob::dispatch(
                $jobId > 0 ? $jobId : null,
                $tableName,
                $periodHint,
                $source,
                $rebuildId
            )->onQueue((string) config('queue.report_queue', 'default'));

            Log::info('Snapshot sync ditunda karena import masih aktif.', [
                'table' => $tableName,
                'period_hint' => $periodHint,
                'job_id' => $jobId,
                'delete_id' => $deleteId,
                'rebuild_id' => $rebuildId,
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menjadwalkan snapshot sync yang ditunda: ' . $e->getMessage(), [
                'table' => $tableName,
                'period_hint' => $periodHint,
                'job_id' => $jobId,
            ]);
        }
    }

    private function syncDailyLoan(?string $periodHint, ?int $jobId, ?string $source, ?string $deleteId = null): void
    {
        $normalizedPeriod = trim((string) $periodHint);
        $periodForDispatch = $normalizedPeriod !== '' ? $normalizedPeriod : null;
        $startedAt = microtime(true);

        try {
            $batchId = ParallelSnapshotBatchCoordinator::dispatchDailyLoanParallelRebuild(
                $periodForDispatch,
                $deleteId,
                $source
            );

            $this->writeAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_parallel_dispatch', 'success', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'context' => [
                    'batch_id' => $batchId,
                    'period_scope' => $periodForDispatch ?? 'all',
                    'jobs' => ['dashboard_pinjaman', 'dashboard_harian', 'rasio_casa', 'performance_rm', 'chart_periodik'],
                ],
            ]);
        } catch (Throwable $e) {
            $this->writeAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_parallel_dispatch', 'failed', [
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function syncSimpanan(?string $periodHint, ?int $jobId, ?string $source, ?string $deleteId = null): void
    {
        if ($this->shouldDeferSimpananSnapshotStart($periodHint)) {
            Log::info('Snapshot simpanan multipn ditunda karena Area 6 belum lengkap.', [
                'period' => $periodHint,
                'job_id' => $jobId,
                'source' => $source,
                'missing_branches' => app(SimpananMultiPnSnapshotGate::class)->getMissingBranches($periodHint),
            ]);

            return;
        }

        $this->runWithSimpananSnapshotLock($periodHint, function () use ($periodHint, $jobId, $source, $deleteId) {
            try {
                // Dispatch 4 snapshot rebuild jobs to run in PARALLEL
                $batchId = ParallelSnapshotBatchCoordinator::dispatchParallelRebuild(
                    $periodHint,
                    $deleteId,
                    $source
                );

                Log::info('Dispatched parallel snapshot rebuild batch for Simpanan MultiPN', [
                    'period' => $periodHint,
                    'batch_id' => $batchId,
                    'jobs' => ['Dashboard Simpanan', 'Dashboard Harian', 'Rekening Dormant', 'Rasio CASA', 'Performance RM'],
                ]);

            } catch (Throwable $e) {
                Log::error('Gagal mendispatch parallel snapshot rebuild batch', [
                    'period' => $periodHint,
                    'error' => $e->getMessage(),
                    'exception_class' => $e::class,
                ]);

                throw $e;
            }
        });
    }

    private function shouldDeferSimpananSnapshotStart(?string $periodHint): bool
    {
        $normalizedPeriod = trim((string) $periodHint);
        if ($normalizedPeriod === '') {
            return false;
        }

        return !app(SimpananMultiPnSnapshotGate::class)->isReady($normalizedPeriod);
    }

    private function syncReportPh(?string $periodHint, ?int $jobId, ?string $source, ?string $deleteId = null): void
    {
        $this->runSnapshotAudit('lw325_ph', $periodHint, $jobId, $source, 'snapshot_dashboard_harian', function () use ($periodHint, $deleteId) {
            if ($deleteId) {
                $this->heartbeat($deleteId, 'Rebuilding Daily Dashboard snapshots after PH import...');
            }

            if ($periodHint !== null && trim($periodHint) !== '') {
                return $this->dashboardHarianSnapshotService->rebuildAffectedByPhPeriod($periodHint, true);
            }

            return $this->dashboardHarianSnapshotService->syncDuePeriods();
        });

        if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
            $this->refreshTableStatistics(self::DASHBOARD_HARIAN_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
        }
    }

    private function syncPerformanceNewPayroll(?string $periodHint, ?int $jobId, ?string $source, ?string $deleteId = null): void
    {
        $this->runSnapshotAudit('performance_pis_per_produk', $periodHint, $jobId, $source, 'snapshot_new_payroll', function () use ($periodHint, $deleteId) {
            return $this->snapshotBuilder->rebuildPerformanceNewPayroll($periodHint, true, $this->makeHeartbeatCallback($deleteId, 'Rebuilding New Payroll snapshots...'));
        });
        $this->refreshTableStatistics(self::NEW_PAYROLL_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
    }

    private function syncLoanType(?string $periodHint, ?int $jobId, ?string $source, ?string $deleteId = null): void
    {
        $this->runSnapshotAudit('loan_type', $periodHint, $jobId, $source, 'snapshot_chart_periodik', function () {
            return $this->snapshotBuilder->rebuildChartPeriodik(null, true);
        });

        if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
            $this->refreshTableStatistics(self::CHART_PERIODIK_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
        }
    }

    private function makeHeartbeatCallback(?string $deleteId, string $message): ?callable
    {
        if (!$deleteId) {
            return null;
        }

        return function (array $progress) use ($deleteId, $message) {
            $formattedMessage = sprintf(
                '%s (%d/%d periods)',
                $message,
                $progress['completed_units'] ?? 0,
                $progress['total_units'] ?? 0
            );
            $this->heartbeat($deleteId, $formattedMessage);
        };
    }

    private function syncSsaSimpanan(?string $periodHint, ?int $jobId, ?string $source): void
    {
        // Rebuild the new SSA Simpanan Snapshot (Phase 2)
        $this->runSnapshotAudit('ssa_simpanan', $periodHint, $jobId, $source, 'snapshot_ssa_simpanan', function () use ($periodHint) {
            return app(\App\Support\SsaSimpananSnapshotBuilder::class)->rebuild($periodHint, true);
        });

        // Dispatch background job for dashboard harian snapshot rebuild
        $this->dispatchDashboardHarianSnapshotRebuildJob($periodHint);

        Log::info('Triggered SSA Simpanan snapshot rebuild and background Dashboard Harian sync', [
            'period' => $periodHint,
            'job_id' => $jobId,
        ]);
    }

    private function syncSsaPinjaman(?string $periodHint, ?int $jobId, ?string $source): void
    {
        // Dispatch background job for snapshot rebuild instead of blocking
        // This allows the import to complete immediately instead of waiting 0.4-60+ seconds
        $this->dispatchDashboardHarianSnapshotRebuildJob($periodHint);

        // Skip audit and stats for now - background job will handle them
        Log::info('Dispatched background Dashboard Harian snapshot rebuild for SSA Pinjaman', [
            'period' => $periodHint,
            'job_id' => $jobId,
        ]);
    }

    /**
     * OPTIMIZED: Dispatch background job for snapshot rebuild instead of blocking
     * 
     * This dramatically speeds up the import response by offloading the rebuild to the queue.
     * The user sees the import complete almost instantly, while the snapshot rebuilds in the background.
     */
    private function dispatchDashboardHarianSnapshotRebuildJob(string|array|null $period): void
    {
        try {
            $jobClass = class_exists('App\Jobs\RebuildDashboardHarianSnapshotJob')
                ? 'App\Jobs\RebuildDashboardHarianSnapshotJob'
                : null;

            if (!$jobClass) {
                Log::warning('RebuildDashboardHarianSnapshotJob not found, falling back to sync rebuild');
                $this->syncDashboardHarianDuePeriodsNow($period);
                return;
            }

            $periods = is_array($period) ? $period : [$period];
            $shouldDispatch = $this->dashboardHarianDirtyPeriods->register($periods);
            if (!$shouldDispatch) {
                Log::info('Coalesced Dashboard Harian snapshot rebuild into pending dirty-period job', [
                    'periods' => $periods,
                ]);

                return;
            }

            $job = new $jobClass(null, true);
            dispatch($job)
                ->delay(now()->addSeconds($this->dashboardHarianDirtyPeriods->debounceSeconds()))
                ->onQueue('imports-high');

            Log::info('Dispatched RebuildDashboardHarianSnapshotJob', [
                'periods' => $periods,
                'queue' => 'imports-high',
                'debounce_seconds' => $this->dashboardHarianDirtyPeriods->debounceSeconds(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch snapshot rebuild job, falling back to sync', [
                'error' => $e->getMessage(),
                'period' => $period,
            ]);
            
            $this->syncDashboardHarianDuePeriodsNow($period);
        }
    }

    private function syncDashboardHarianDuePeriodsNow(string|array|null $period): void
    {
        $periods = is_array($period) ? $period : [$period];
        $normalized = array_values(array_filter(
            array_map(fn ($value) => trim((string) $value), $periods),
            fn (string $value) => $value !== ''
        ));

        $normalized !== []
            ? $this->dashboardHarianSnapshotService->syncDuePeriods($normalized)
            : $this->dashboardHarianSnapshotService->syncDuePeriods();
    }

    public function syncAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null, ?string $deleteId = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
        }

        $periodHint = $this->normalizeAuditPeriodHint($periodHint);

        $source = $source ?? static::class . '::syncAfterDelete';

        if (in_array($normalizedTable, self::POST_DELETE_SNAPSHOT_REPORTS, true)) {
            $this->refreshTableStatistics($normalizedTable, $periodHint, null, $source);
            $this->cleanupDerivedArtifactsAfterDelete($normalizedTable, $periodHint, $source, $deleteId);
            $this->rebuildSnapshotsAfterDelete($normalizedTable, $periodHint, $source, $deleteId);

            return;
        }

        $this->syncAfterDeleteLightweight($normalizedTable, $periodHint, $source, $deleteId);
    }

    public function syncAfterDeleteLightweight(string $tableName, ?string $periodHint = null, ?string $source = null, ?string $deleteId = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
        }

        if ($deleteId) {
            $this->heartbeat($deleteId, 'Refreshing table statistics...');
        }

        $this->refreshTableStatistics($normalizedTable, $periodHint, null, $source ?? static::class . '::syncAfterDeleteLightweight');

        try {
            $newVersion = $this->bumpReportCacheVersion();
            $this->writeAudit($normalizedTable, $periodHint, null, $source, 'cache_invalidate_lightweight', 'success', [
                'context' => ['cache_version' => $newVersion],
            ]);
        } catch (Throwable $e) {
            $this->writeAudit($normalizedTable, $periodHint, null, $source, 'cache_invalidate_lightweight', 'failed', [
                'message' => $e->getMessage(),
            ]);
            Log::warning('Gagal invalidasi cache lightweight setelah delete: ' . $e->getMessage(), [
                'table' => $normalizedTable,
            ]);
        }
    }

    public function resolvePostDeleteMaintenanceMode(string $tableName): string
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return 'lightweight';
        }

        return in_array($normalizedTable, self::POST_DELETE_SNAPSHOT_REPORTS, true)
            ? 'snapshot'
            : 'lightweight';
    }

    public function cleanupDerivedArtifactsAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null, ?string $deleteId = null): array
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return [];
        }

        if ($deleteId) {
            $this->heartbeat($deleteId, 'Cleaning up derived snapshot artifacts...');
        }

        $cleanupMap = match ($normalizedTable) {
            'daily_loan_dinamis' => [
                self::DASHBOARD_SNAPSHOT_TABLE => 'periode',
                self::CHART_PERIODIK_SNAPSHOT_TABLE => 'periode',
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::RASIO_SNAPSHOT_TABLE => 'loan_period',
                self::RASIO_UKER_SNAPSHOT_TABLE => 'loan_period',
                self::PERFORMANCE_RM_SNAPSHOT_TABLE => 'periode',
            ],
            'simpanan_multipn' => [
                self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE => 'snapshot_period',
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::DORMANT_SNAPSHOT_TABLE => 'posisi',
                self::RASIO_SNAPSHOT_TABLE => 'casa_period',
                self::RASIO_UKER_SNAPSHOT_TABLE => 'casa_period',
                self::PERFORMANCE_RM_SNAPSHOT_TABLE => 'periode',
            ],
            'ssa_simpanan' => [
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::SSA_SIMPANAN_SNAPSHOT_TABLE => 'Month_Day_Year_of_Posisi',
            ],
            'ssa_pinjaman' => [
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
            ],
            'lw325_ph' => [
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
            ],
            'performance_pis_per_produk' => [
                self::NEW_PAYROLL_SNAPSHOT_TABLE => 'snapshot_posisi',
            ],
            default => [],
        };

        $deleted = [];

        foreach ($cleanupMap as $snapshotTable => $periodColumn) {
            if (!Schema::hasTable($snapshotTable)) {
                continue;
            }

            $startedAt = microtime(true);

            try {
                $query = DB::table($snapshotTable);
                if ($periodHint !== null && $periodHint !== '' && Schema::hasColumn($snapshotTable, $periodColumn)) {
                    if ($normalizedTable === 'lw325_ph' && $snapshotTable === self::DASHBOARD_HARIAN_SNAPSHOT_TABLE) {
                        $affectedPeriods = $this->dashboardHarianSnapshotService->resolveAffectedSnapshotPeriodsForPh($periodHint);
                        if ($affectedPeriods === []) {
                            $deleted[$snapshotTable] = 0;
                            continue;
                        }

                        $query->whereIn($periodColumn, $affectedPeriods);
                    } else {
                    if ($periodHint !== null && $periodHint !== '') {
                        $partitionName = $this->partitionMaintenanceService->resolveSinglePartitionForValue(
                            $snapshotTable,
                            $periodColumn,
                            $periodHint
                        );
                    } else {
                        $partitionName = null;
                    }

                    if ($partitionName !== null) {
                        $affected = (int) DB::table($snapshotTable)
                            ->where($periodColumn, $periodHint)
                            ->count();

                        if ($affected > 0) {
                            $this->partitionMaintenanceService->truncatePartition($snapshotTable, $partitionName);
                        }

                        $deleted[$snapshotTable] = $affected;

                        $this->writeAudit($normalizedTable, $periodHint, null, $source, 'cleanup_snapshot_rows', 'success', [
                            'duration_ms' => $this->elapsedMs($startedAt),
                            'affected_rows' => $affected,
                            'context' => [
                                'snapshot_table' => $snapshotTable,
                                'cleanup_strategy' => 'partition_truncate',
                                'partition_name' => $partitionName,
                            ],
                        ]);

                        continue;
                    }

                    $query->where($periodColumn, $periodHint);
                    }
                }

                $affected = (int) $query->delete();
                $deleted[$snapshotTable] = $affected;

                $this->writeAudit($normalizedTable, $periodHint, null, $source, 'cleanup_snapshot_rows', 'success', [
                    'duration_ms' => $this->elapsedMs($startedAt),
                    'affected_rows' => $affected,
                    'context' => [
                        'snapshot_table' => $snapshotTable,
                        'cleanup_strategy' => 'delete',
                    ],
                ]);
            } catch (Throwable $e) {
                $this->writeAudit($normalizedTable, $periodHint, null, $source, 'cleanup_snapshot_rows', 'failed', [
                    'duration_ms' => $this->elapsedMs($startedAt),
                    'message' => $e->getMessage(),
                    'context' => ['snapshot_table' => $snapshotTable],
                ]);

                throw $e;
            }
        }

        $this->bumpReportCacheVersion();

        return $deleted;
    }

    private function rebuildSnapshotsAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null, ?string $deleteId = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        $normalizedPeriodHint = trim((string) $periodHint);

        if ($normalizedPeriodHint === '') {
            if ($normalizedTable === 'lw325_ph') {
                $this->syncReportPh(null, null, $source, $deleteId);
                return;
            }

            $this->writeAudit($normalizedTable, $periodHint, null, $source, 'snapshot_rebuild_after_delete', 'skipped', [
                'message' => 'Snapshot rebuild after delete dilewati karena period hint tidak tersedia.',
            ]);

            return;
        }

        match ($normalizedTable) {
            'daily_loan_dinamis' => $this->syncDailyLoan($normalizedPeriodHint, null, $source, $deleteId),
            'loan_type' => $this->syncLoanType(null, null, $source, $deleteId),
            'simpanan_multipn' => $this->syncSimpanan($normalizedPeriodHint, null, $source, $deleteId),
            'ssa_simpanan' => $this->syncSsaSimpanan($normalizedPeriodHint, null, $source, $deleteId),
            'ssa_pinjaman' => $this->syncSsaPinjaman($normalizedPeriodHint, null, $source, $deleteId),
            'lw325_ph' => $this->syncReportPh($normalizedPeriodHint, null, $source, $deleteId),
            'performance_pis_per_produk' => $this->syncPerformanceNewPayroll($normalizedPeriodHint, null, $source, $deleteId),
            default => null,
        };
    }

    private function refreshTableStatistics(string $tableName, ?string $periodHint, ?int $jobId, ?string $source): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        $cacheKey = 'report:analyze:last:' . $tableName . ':' . $this->normalizeSnapshotLockScope($periodHint);
        if (!Cache::add($cacheKey, now()->toIso8601String(), now()->addSeconds(self::ANALYZE_THROTTLE_SECONDS))) {
            $this->writeAudit($tableName, $periodHint, $jobId, $source, 'analyze_table', 'skipped', [
                'context' => ['reason' => 'throttled'],
            ]);

            return;
        }

        $startedAt = microtime(true);

        try {
            DB::statement('ANALYZE TABLE `' . str_replace('`', '``', $tableName) . '`');
            $this->writeAudit($tableName, $periodHint, $jobId, $source, 'analyze_table', 'success', [
                'duration_ms' => $this->elapsedMs($startedAt),
            ]);
        } catch (Throwable $e) {
            $this->writeAudit($tableName, $periodHint, $jobId, $source, 'analyze_table', 'failed', [
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => $e->getMessage(),
            ]);
            Log::warning('ANALYZE TABLE gagal dijalankan setelah import: ' . $e->getMessage(), [
                'table' => $tableName,
            ]);
        }
    }

    private function runWithRasioSnapshotLock(?string $periodHint, callable $callback): mixed
    {
        $scope = $this->normalizeSnapshotLockScope($periodHint);
        $lock = Cache::lock(self::RASIO_REBUILD_LOCK_PREFIX . $scope, 120);

        try {
            return $lock->block(60, $callback);
        } finally {
            try {
                optional($lock)->release();
            } catch (Throwable $e) {
                Log::warning('Gagal melepas lock rebuild rasio snapshot: ' . $e->getMessage(), [
                    'period_hint' => $periodHint,
                ]);
            }
        }
    }

    private function runWithSimpananSnapshotLock(?string $periodHint, callable $callback): mixed
    {
        $scope = $this->normalizeSnapshotLockScope($periodHint);
        $lock = Cache::lock(self::SIMPANAN_REBUILD_LOCK_PREFIX . $scope, 180);

        try {
            return $lock->block(60, $callback);
        } finally {
            try {
                optional($lock)->release();
            } catch (Throwable $e) {
                Log::warning('Gagal melepas lock rebuild snapshot simpanan: ' . $e->getMessage(), [
                    'period_hint' => $periodHint,
                ]);
            }
        }
    }

    private function shouldRefreshDerivedSnapshotStatistics(?string $periodHint): bool
    {
        return true;
    }

    private function normalizeSnapshotLockScope(?string $periodHint): string
    {
        $normalized = trim((string) $periodHint);

        return $normalized !== '' ? $normalized : '__all__';
    }

    private function runSnapshotAudit(string $tableName, ?string $periodHint, ?int $jobId, ?string $source, string $action, callable $callback): void
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
            $affectedRows = $this->sumAffectedRows($result);

            $this->writeAudit($tableName, $periodHint, $jobId, $source, $action, 'success', [
                'duration_ms' => $this->elapsedMs($startedAt),
                'affected_rows' => $affectedRows,
                'context' => ['result' => $result],
            ]);
        } catch (Throwable $e) {
            $this->writeAudit($tableName, $periodHint, $jobId, $source, $action, 'failed', [
                'duration_ms' => $this->elapsedMs($startedAt),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function sumAffectedRows(mixed $result): ?int
    {
        if (is_int($result)) {
            return $result;
        }

        if (!is_array($result)) {
            return null;
        }

        $total = 0;
        foreach ($result as $value) {
            if (is_array($value)) {
                $nested = $this->sumAffectedRows($value);
                if ($nested !== null) {
                    $total += $nested;
                }
                continue;
            }

            if (is_int($value)) {
                $total += $value;
            }
        }

        return $total;
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function bumpReportCacheVersion(): int
    {
        Cache::add(self::CACHE_VERSION_KEY, 1, now()->addDays(30));

        return (int) Cache::increment(self::CACHE_VERSION_KEY);
    }

    public function invalidateReportCaches(?string $source = null): int
    {
        $newVersion = $this->bumpReportCacheVersion();

        $this->writeAudit('report_snapshot_rebuild', null, null, $source, 'cache_invalidate', 'success', [
            'context' => ['cache_version' => $newVersion],
        ]);

        return $newVersion;
    }

    private function writeAudit(string $tableName, ?string $periodHint, ?int $jobId, ?string $source, string $action, string $status, array $payload = []): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            return;
        }

        try {
            $normalizedPeriodHint = $this->normalizeAuditPeriodHint($periodHint);

            DB::table(self::AUDIT_TABLE)->insert([
                'import_job_id' => $jobId,
                'source' => $source,
                'table_name' => $tableName,
                'period_hint' => $normalizedPeriodHint,
                'action' => $action,
                'status' => $status,
                'duration_ms' => $payload['duration_ms'] ?? null,
                'affected_rows' => $payload['affected_rows'] ?? null,
                'message' => $payload['message'] ?? null,
                'context' => isset($payload['context']) ? json_encode($payload['context'], JSON_UNESCAPED_UNICODE) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Gagal menulis audit sinkronisasi report: ' . $e->getMessage(), [
                'table' => $tableName,
                'action' => $action,
                'status' => $status,
            ]);
        }
    }

    private function heartbeat(string $trackingId, ?string $message = null): void
    {
        try {
            // Managed Delete Heartbeat
            if (str_contains($trackingId, 'managed_delete:')) {
                app(\App\Http\Controllers\Import\ImportIndexController::class)->heartbeatManagedDeleteState($trackingId, $message);
                return;
            }

            // Snapshot Rebuild Heartbeat (UUID)
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $trackingId)) {
                $state = ManagedReportSnapshotRebuildStore::getState($trackingId);
                if ($state) {
                    $state['message'] = $message ?? 'Sedang mensinkronisasi data snapshot...';
                    $state['status'] = 'running';
                    $state['updated_at'] = now()->toIso8601String();
                    ManagedReportSnapshotRebuildStore::putState($state);
                }
            }
        } catch (Throwable $e) {
            Log::debug('Heartbeat failed: ' . $e->getMessage());
        }
    }

    private function normalizeAuditPeriodHint(?string $periodHint): ?string
    {
        $value = trim((string) $periodHint);
        if ($value === '') {
            return null;
        }

        $strictNormalized = StrictDateParser::normalize($value);
        if ($strictNormalized !== null) {
            return $strictNormalized;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return $value . '-01';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
