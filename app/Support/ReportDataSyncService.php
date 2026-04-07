<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReportDataSyncService
{
    private const AUDIT_TABLE = 'report_sync_audits';
    private const DASHBOARD_SNAPSHOT_TABLE = 'dashboard_pinjaman_snapshots';
    private const RASIO_SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const DORMANT_SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const CACHE_VERSION_KEY = 'report_cache_version:global';

    public function __construct(
        private readonly ReportSnapshotBuilder $snapshotBuilder
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
                default => null,
            };
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
        $this->refreshTableStatistics(self::DASHBOARD_SNAPSHOT_TABLE, $periodHint, $jobId, $source);

        $this->runSnapshotAudit('daily_loan_dinamis', $periodHint, $jobId, $source, 'snapshot_rasio_casa', function () use ($periodHint) {
            return $this->snapshotBuilder->rebuildRasioCasa($periodHint, true);
        });
        $this->refreshTableStatistics(self::RASIO_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
    }

    private function syncSimpanan(?string $periodHint, ?int $jobId, ?string $source): void
    {
        $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_rekening_dormant', function () use ($periodHint) {
            return $this->snapshotBuilder->rebuildRekeningDormant($periodHint, true);
        });
        $this->refreshTableStatistics(self::DORMANT_SNAPSHOT_TABLE, $periodHint, $jobId, $source);

        $this->runSnapshotAudit('simpanan_multipn', $periodHint, $jobId, $source, 'snapshot_rasio_casa', function () {
            return $this->snapshotBuilder->rebuildRasioCasa(null, true);
        });
        $this->refreshTableStatistics(self::RASIO_SNAPSHOT_TABLE, $periodHint, $jobId, $source);
    }

    private function syncReportPh(?string $periodHint, ?int $jobId, ?string $source): void
    {
        // Dashboard pinjaman membaca PH langsung dari tabel sumber,
        // jadi cukup refresh statistik optimizer + flush cache.
        $this->writeAudit('lw325_ph', $periodHint, $jobId, $source, 'snapshot_sync', 'success', [
            'message' => 'PH uses source table directly; only optimizer stats and cache refresh required.',
        ]);
    }

    public function syncAfterDelete(string $tableName, ?string $periodHint = null, ?string $source = null): void
    {
        $this->syncImportedTable($tableName, $periodHint, null, $source ?? static::class . '::syncAfterDelete');
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

    private function writeAudit(string $tableName, ?string $periodHint, ?int $jobId, ?string $source, string $action, string $status, array $payload = []): void
    {
        if (!Schema::hasTable(self::AUDIT_TABLE)) {
            return;
        }

        try {
            DB::table(self::AUDIT_TABLE)->insert([
                'import_job_id' => $jobId,
                'source' => $source,
                'table_name' => $tableName,
                'period_hint' => $periodHint,
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
}
