<?php

namespace App\Support;

use App\Jobs\WarmReportCacheJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportDataSyncService
{
    private const AUDIT_TABLE = 'report_sync_audits';
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const DASHBOARD_SIMPANAN_SNAPSHOT_TABLE = 'dashboard_simpanan_snapshots';
    private const DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE = 'dashboard_simpanan_branch_snapshots';
    private const DASHBOARD_HARIAN_SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const NEW_PAYROLL_SNAPSHOT_TABLE = 'performance_new_payroll_snapshots';
    private const CACHE_VERSION_KEY = 'report_cache_version:global';
    private const RASIO_REBUILD_LOCK_KEY = 'snapshot:rasio:rebuild:global';
    private const SIMPANAN_REBUILD_LOCK_PREFIX = 'snapshot:simpanan:rebuild:';
    private const POST_DELETE_SNAPSHOT_REPORTS = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'performance_pis_per_produk',
    ];

    public function __construct(
        private readonly ReportSnapshotBuilder $snapshotBuilder,
        private readonly DashboardHarianSnapshotService $dashboardHarianSnapshotService,
        private readonly PartitionMaintenanceService $partitionMaintenanceService
    ) {
    }

    public function syncImportedJob(int $jobId, ?string $fallbackTableName = null, ?string $periodHint = null, ?string $source = null): void
    {
        if ($jobId <= 0) {
            if ($fallbackTableName) {
                $this->syncImportedTable($fallbackTableName, $periodHint, null, $source);
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
            $this->syncImportedTable($tableName, $periodHint, $jobId, $source);
        }
    }

    public function syncImportedTable(string $tableName, ?string $periodHint = null, ?int $jobId = null, ?string $source = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
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
                'daily_loan_dinamis' => $this->syncDailyLoan($periodHint, $jobId, $source),
                'simpanan_multipn' => $this->syncSimpanan($periodHint, $jobId, $source),
                'lw325_ph' => $this->syncReportPh($periodHint, $jobId, $source),
                'performance_pis_per_produk' => $this->syncPerformanceNewPayroll($periodHint, $jobId, $source),
                default => null,
            };

            WarmReportCacheJob::dispatch()->onQueue('reports-low');

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

    private function syncDailyLoan(?string $periodHint, ?int $jobId, ?string $source): void
    {
        $this->runSnapshotAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_dashboard', function () use ($periodHint) {
            return $this->snapshotBuilder->rebuildDashboard($periodHint, true);
        });
        if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
            $this->refreshTableStatistics(self::DASHBOARD_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
        }

        $this->runSnapshotAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_dashboard_harian', function () use ($periodHint) {
            return $this->dashboardHarianSnapshotService->rebuild($periodHint, true);
        });
        if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
            $this->refreshTableStatistics(self::DASHBOARD_HARIAN_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
        }

        $this->runWithRasioSnapshotLock(function () use ($periodHint, $jobId, $source) {
            $this->runSnapshotAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_rasio_casa', function () use ($periodHint) {
                return $this->snapshotBuilder->rebuildRasioCasa($periodHint, true);
            });
        });
        if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
            $this->refreshTableStatistics(self::RASIO_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
        }
    }

    private function syncSimpanan(?string $periodHint, ?int $jobId, ?string $source): void
    {
        $this->runWithSimpananSnapshotLock($periodHint, function () use ($periodHint, $jobId, $source) {
            $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_dashboard_simpanan', function () use ($periodHint) {
                return $this->snapshotBuilder->rebuildDashboardSimpanan($periodHint, true);
            });

            if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
                $this->refreshTableStatistics(self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
                $this->refreshTableStatistics(self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
            }

            $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_dashboard_harian', function () use ($periodHint) {
                return $this->dashboardHarianSnapshotService->rebuild($periodHint, true);
            });

            if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
                $this->refreshTableStatistics(self::DASHBOARD_HARIAN_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
            }

            $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_rekening_dormant', function () use ($periodHint) {
                return $this->snapshotBuilder->rebuildRekeningDormant($periodHint, true);
            });

            if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
                $this->refreshTableStatistics(self::DORMANT_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
            }

            $this->runWithRasioSnapshotLock(function () use ($periodHint, $jobId, $source) {
                $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_rasio_casa', function () use ($periodHint) {
                    return $this->snapshotBuilder->rebuildRasioCasa($periodHint, true);
                });
            });

            if ($this->shouldRefreshDerivedSnapshotStatistics($periodHint)) {
                $this->refreshTableStatistics(self::RASIO_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
            }
        });
    }

    private function syncReportPh(?string $periodHint, ?int $jobId, ?string $source): void
    {
        // Dashboard pinjaman membaca PH langsung dari tabel sumber,
        // jadi cukup refresh statistik optimizer + flush cache.
        $this->writeAudit('lw325_ph', $periodHint, $jobId, $source, 'snapshot_sync', 'success', [
            'message' => 'PH uses source table directly; only optimizer stats and cache refresh required.',
        ]);
    }

    private function syncPerformanceNewPayroll(?string $periodHint, ?int $jobId, ?string $source): void
    {
        $this->runSnapshotAudit('performance_pis_per_produk', $periodHint, $jobId, $source, 'snapshot_new_payroll', function () use ($periodHint) {
            return $this->snapshotBuilder->rebuildPerformanceNewPayroll($periodHint, true);
        });
        $this->refreshTableStatistics(self::NEW_PAYROLL_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
    }

    public function syncAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
        }

        $source = $source ?? static::class . '::syncAfterDelete';

        if (in_array($normalizedTable, self::POST_DELETE_SNAPSHOT_REPORTS, true)) {
            $this->refreshTableStatistics($normalizedTable, $periodHint, null, $source);
            $this->cleanupDerivedArtifactsAfterDelete($normalizedTable, $periodHint, $source);
            $this->rebuildSnapshotsAfterDelete($normalizedTable, $periodHint, $source);

            return;
        }

        $this->syncAfterDeleteLightweight($normalizedTable, $periodHint, $source);
    }

    public function syncAfterDeleteLightweight(string $tableName, ?string $periodHint = null, ?string $source = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return;
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

    public function cleanupDerivedArtifactsAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null): array
    {
        $normalizedTable = strtolower(trim($tableName));
        if ($normalizedTable === '') {
            return [];
        }

        $cleanupMap = match ($normalizedTable) {
            'daily_loan_dinamis' => [
                self::DASHBOARD_SNAPSHOT_TABLE => 'periode',
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::RASIO_SNAPSHOT_TABLE => 'loan_period',
            ],
            'simpanan_multipn' => [
                self::DASHBOARD_SIMPANAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::DASHBOARD_SIMPANAN_BRANCH_SNAPSHOT_TABLE => 'snapshot_period',
                self::DASHBOARD_HARIAN_SNAPSHOT_TABLE => 'snapshot_period',
                self::DORMANT_SNAPSHOT_TABLE => 'posisi',
                self::RASIO_SNAPSHOT_TABLE => 'casa_period',
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
                    $partitionName = $this->partitionMaintenanceService->resolveSinglePartitionForValue(
                        $snapshotTable,
                        $periodColumn,
                        $periodHint
                    );

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

    private function rebuildSnapshotsAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null): void
    {
        $normalizedTable = strtolower(trim($tableName));
        $normalizedPeriodHint = trim((string) $periodHint);

        if ($normalizedPeriodHint === '') {
            $this->writeAudit($normalizedTable, $periodHint, null, $source, 'snapshot_rebuild_after_delete', 'skipped', [
                'message' => 'Snapshot rebuild after delete dilewati karena period hint tidak tersedia.',
            ]);

            return;
        }

        match ($normalizedTable) {
            'daily_loan_dinamis' => $this->syncDailyLoan($normalizedPeriodHint, null, $source),
            'simpanan_multipn' => $this->syncSimpanan($normalizedPeriodHint, null, $source),
            'performance_pis_per_produk' => $this->syncPerformanceNewPayroll($normalizedPeriodHint, null, $source),
            default => null,
        };
    }

    private function refreshTableStatistics(string $tableName, ?string $periodHint, ?int $jobId, ?string $source): void
    {
        if (!Schema::hasTable($tableName)) {
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

    private function runWithRasioSnapshotLock(callable $callback): mixed
    {
        $lock = Cache::lock(self::RASIO_REBUILD_LOCK_KEY, 120);

        try {
            return $lock->block(10, $callback);
        } finally {
            try {
                optional($lock)->release();
            } catch (Throwable $e) {
                Log::warning('Gagal melepas lock rebuild rasio snapshot: ' . $e->getMessage());
            }
        }
    }

    private function runWithSimpananSnapshotLock(?string $periodHint, callable $callback): mixed
    {
        $scope = $this->normalizeSnapshotLockScope($periodHint);
        $lock = Cache::lock(self::SIMPANAN_REBUILD_LOCK_PREFIX . $scope, 180);

        try {
            return $lock->block(10, $callback);
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

    private function normalizeAuditPeriodHint(?string $periodHint): ?string
    {
        $value = trim((string) $periodHint);
        if ($value === '') {
            return null;
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

