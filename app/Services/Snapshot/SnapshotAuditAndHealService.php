<?php

namespace App\Services\Snapshot;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Jobs\SmartPartialSnapshotRebuildJob;
use App\Services\Import\ImportProgressService;
use App\Support\SnapshotAuditCoordinator;
use App\Support\SnapshotAuditService;
use App\Support\SnapshotIntegrityGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SnapshotAuditAndHealService
{
    private const REBUILD_THROTTLE_SECONDS = 300;
    private const AUDIT_COOLDOWN_SECONDS = 180;

    /**
     * @var array<int, string>
     */
    private const AUDIT_CANDIDATE_TABLES = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'ssa_simpanan',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SOURCE_TO_SNAPSHOT_MAP = [
        'daily_loan_dinamis' => [
            'dashboard_pinjaman_snapshots',
            'dashboard_pinjaman_chart_periodik_snapshots',
            'performance_rm_snapshots',
        ],
        'simpanan_multipn' => [
            'dashboard_simpanan_snapshots',
            'rekening_dormant_snapshots',
        ],
        'ssa_simpanan' => [
            'ssa_simpanan_snapshots',
        ],
    ];

    public function __construct(
        private readonly ImportProgressService $importProgressService,
        private readonly SnapshotAuditCoordinator $auditCoordinator,
        private readonly SnapshotAuditService $auditService,
        private readonly SnapshotIntegrityGuard $integrityGuard
    ) {
    }

    /**
     * Periksa apakah proses audit harus mengalah (yield) demi mengamankan
     * kecepatan proses import, delete, atau update data.
     */
    public function shouldYieldToImports(?string $sourceTable = null): bool
    {
        if ($sourceTable !== null && trim($sourceTable) !== '') {
            return $this->importProgressService->hasActiveProcessingJobsForTable($sourceTable);
        }

        return $this->importProgressService->hasActiveProcessingJobs();
    }

    /**
     * Audit semua tabel snapshot utama dan perbaiki secara otomatis
     * jika ditemukan ketidaksesuaian data (mismatch/anomali).
     *
     * @return array<string, mixed>
     */
    public function auditAndHealAll(bool $force = false, int $recentLimit = 5): array
    {
        if (!$force && $this->shouldYieldToImports()) {
            Log::info('SnapshotAuditAndHealService: Audit ditunda karena ada proses import/data transfer yang aktif.');

            return [
                'status' => 'yielded',
                'message' => 'Audit ditunda karena ada aktivitas import/update/delete yang sedang berjalan.',
                'tables_checked' => 0,
                'discrepancies_found' => 0,
                'rebuilds_dispatched' => 0,
                'results' => [],
            ];
        }

        $results = [];
        $totalDiscrepancies = 0;
        $totalRebuilds = 0;

        foreach (self::AUDIT_CANDIDATE_TABLES as $table) {
            if (!$force && $this->shouldYieldToImports($table)) {
                $results[$table] = [
                    'status' => 'yielded',
                    'message' => "Audit untuk tabel {$table} ditunda karena import sedang aktif.",
                ];
                continue;
            }

            try {
                $tableResult = $this->auditAndHealTable($table, null, $force, $recentLimit);
                $results[$table] = $tableResult;
                $totalDiscrepancies += (int) ($tableResult['discrepancies_found'] ?? 0);
                $totalRebuilds += (int) ($tableResult['rebuilds_dispatched'] ?? 0);
            } catch (Throwable $e) {
                Log::error("SnapshotAuditAndHealService: Gagal mengaudit {$table}", [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                $results[$table] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return [
            'status' => $totalDiscrepancies > 0 ? 'repaired' : 'clean',
            'tables_checked' => count($results),
            'discrepancies_found' => $totalDiscrepancies,
            'rebuilds_dispatched' => $totalRebuilds,
            'results' => $results,
        ];
    }

    /**
     * Audit tabel tertentu dan picu rekonsiliasi jika terjadi miss/error.
     *
     * @return array<string, mixed>
     */
    public function auditAndHealTable(
        string $tableName,
        ?string $periodHint = null,
        bool $force = false,
        int $recentLimit = 0
    ): array {
        $normalizedTable = strtolower(trim($tableName));

        if (!$force && $this->shouldYieldToImports($normalizedTable)) {
            return [
                'status' => 'yielded',
                'message' => "Audit tabel {$normalizedTable} ditunda karena import sedang berjalan.",
                'discrepancies_found' => 0,
                'rebuilds_dispatched' => 0,
            ];
        }

        if (!Schema::hasTable($normalizedTable)) {
            return [
                'status' => 'skipped',
                'message' => "Tabel sumber {$normalizedTable} tidak ditemukan di database.",
                'discrepancies_found' => 0,
                'rebuilds_dispatched' => 0,
            ];
        }

        $periodsToAudit = [];
        if ($periodHint !== null && $periodHint !== '') {
            $periodsToAudit = [$periodHint];
        } elseif ($recentLimit > 0) {
            $periodsToAudit = $this->resolveRecentPeriods($normalizedTable, $recentLimit);
        }

        if ($periodsToAudit !== []) {
            $allAffectedPeriods = [];
            $totalDiscrepancies = 0;
            $totalRebuilds = 0;

            foreach ($periodsToAudit as $p) {
                $subResult = $this->auditAndHealSinglePeriod($normalizedTable, $p, $force);
                if (!empty($subResult['affected_periods'])) {
                    $allAffectedPeriods = array_merge($allAffectedPeriods, $subResult['affected_periods']);
                }
                $totalDiscrepancies += (int) ($subResult['discrepancies_found'] ?? 0);
                $totalRebuilds += (int) ($subResult['rebuilds_dispatched'] ?? 0);
            }

            $uniqueAffected = array_values(array_unique(array_filter($allAffectedPeriods)));

            return [
                'status' => $uniqueAffected !== [] ? 'discrepancies_detected' : 'clean',
                'table_name' => $normalizedTable,
                'period_hint' => $periodHint,
                'periods_audited' => count($periodsToAudit),
                'discrepancies_found' => count($uniqueAffected),
                'affected_periods' => $uniqueAffected,
                'rebuilds_dispatched' => $totalRebuilds,
            ];
        }

        return $this->auditAndHealSinglePeriod($normalizedTable, null, $force);
    }

    private function auditAndHealSinglePeriod(string $normalizedTable, ?string $periodHint, bool $force): array
    {
        // 1. Audit metrik agregat menggunakan SnapshotAuditCoordinator
        $auditResult = $this->auditCoordinator->runAudit($normalizedTable, $periodHint);
        $discrepancies = (array) ($auditResult['discrepancies'] ?? []);
        $affectedPeriods = [];

        foreach ($discrepancies as $disc) {
            $period = $disc['period'] ?? null;
            if ($period !== null && $period !== '') {
                $affectedPeriods[] = (string) $period;
            }
        }

        // 2. Audit integritas struktural (anomali & duplicate group)
        $snapshotTables = self::SOURCE_TO_SNAPSHOT_MAP[$normalizedTable] ?? [];
        foreach ($snapshotTables as $snapTable) {
            if (!Schema::hasTable($snapTable)) {
                continue;
            }

            $inspections = $this->integrityGuard->inspectTable($snapTable, $periodHint, 5);
            foreach ($inspections as $insp) {
                if (($insp['status'] ?? '') === 'anomaly' || (int) ($insp['duplicate_group_count'] ?? 0) > 0) {
                    $anomPeriod = (string) ($insp['period'] ?? '');
                    if ($anomPeriod !== '') {
                        $affectedPeriods[] = $anomPeriod;
                        // Purge baris anomali agar rebuild dapat menyusun data yang bersih
                        $this->integrityGuard->purgePeriodIfAnomalous($snapTable, $anomPeriod, [
                            'source' => static::class,
                            'reason' => 'auto-heal anomaly purge',
                        ]);
                    }
                }
            }
        }

        $uniqueAffectedPeriods = array_values(array_unique(array_filter($affectedPeriods)));
        $rebuildsDispatched = 0;

        // 3. Dispatch auto-heal rebuild untuk periode yang terdampak
        if ($uniqueAffectedPeriods !== []) {
            foreach ($uniqueAffectedPeriods as $period) {
                if ($this->dispatchAutoHeal($normalizedTable, $period, $force)) {
                    $rebuildsDispatched++;
                }
            }
        }

        return [
            'status' => $uniqueAffectedPeriods !== [] ? 'discrepancies_detected' : 'clean',
            'table_name' => $normalizedTable,
            'period_hint' => $periodHint,
            'discrepancies_found' => count($uniqueAffectedPeriods),
            'affected_periods' => $uniqueAffectedPeriods,
            'rebuilds_dispatched' => $rebuildsDispatched,
        ];
    }

    public function resolveRecentPeriods(string $table, int $limit = 5): array
    {
        $periodColumn = match ($table) {
            'daily_loan_dinamis' => 'periode',
            'simpanan_multipn' => 'posisi',
            'ssa_simpanan' => 'Month_Day_Year_of_Posisi',
            default => null,
        };

        if ($periodColumn === null || !Schema::hasTable($table) || !Schema::hasColumn($table, $periodColumn)) {
            return [];
        }

        return DB::table($table)
            ->whereNotNull($periodColumn)
            ->where($periodColumn, '!=', '')
            ->distinct()
            ->orderByDesc($periodColumn)
            ->limit($limit)
            ->pluck($periodColumn)
            ->map(fn ($p) => (string) $p)
            ->toArray();
    }

    /**
     * Dispatch perbaikan data secara aman dengan throttle cache agar tidak membanjiri antrean.
     */
    private function dispatchAutoHeal(string $tableName, string $period, bool $force = false): bool
    {
        $throttleKey = "snapshot:auto-heal:lock:{$tableName}:{$period}";

        if (!$force && Cache::has($throttleKey)) {
            Log::debug("Auto-heal untuk {$tableName} periode {$period} dilewati karena masih dalam cooldown.");
            return false;
        }

        try {
            // Dispatch ke antrean snapshots-parallel (dikelola oleh pool snapshots)
            EnsureImportedSnapshotsFreshJob::dispatch(
                $tableName,
                $period,
                static::class
            )->onQueue('snapshots-parallel');

            Cache::put($throttleKey, time(), now()->addSeconds(self::REBUILD_THROTTLE_SECONDS));

            Log::warning("SnapshotAutoHeal: Berhasil men-dispatch perbaikan otomatis untuk {$tableName} periode {$period}.", [
                'table' => $tableName,
                'period' => $period,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error("SnapshotAutoHeal: Gagal men-dispatch rebuild untuk {$tableName} periode {$period}: " . $e->getMessage());

            return false;
        }
    }
}
