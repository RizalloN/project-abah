<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SnapshotAuditService
{
    private const AUDIT_PRECISION = 2;

    public function auditSnapshot(string $tableName, ?string $periodHint = null): array
    {
        $normalizedTable = strtolower(trim($tableName));

        try {
            return match ($normalizedTable) {
                'daily_loan_dinamis' => $this->auditDailyLoan($periodHint),
                'simpanan_multipn' => $this->auditSimpanan($periodHint),
                'ssa_simpanan' => $this->auditSsaSimpanan($periodHint),
                default => $this->auditGenericSnapshot($normalizedTable, $periodHint),
            };
        } catch (Throwable $e) {
            Log::error('Snapshot audit failed: ' . $e->getMessage(), [
                'table' => $normalizedTable,
                'period_hint' => $periodHint,
                'exception' => $e::class,
            ]);

            return [
                'status' => 'error',
                'table_name' => $normalizedTable,
                'period_hint' => $periodHint,
                'error' => $e->getMessage(),
                'discrepancies' => [],
                'summary' => [],
            ];
        }
    }

    private function auditDailyLoan(?string $periodHint): array
    {
        $sourceTable = 'daily_loan_dinamis';
        $snapshotTable = 'dashboard_pinjaman_snapshots';

        if (!$this->tablesAndColumnsAvailable([
            $sourceTable => ['periode', 'baki_debet1', 'nomor_rekening1'],
            $snapshotTable => ['periode', 'loan_balance', 'account_number'],
        ])) {
            return $this->auditUnavailable($sourceTable, $snapshotTable, $periodHint);
        }

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_balance' => 'SUM(CAST(COALESCE(baki_debet1, 0) AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
            'distinct_accounts' => 'COUNT(DISTINCT nomor_rekening1)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'periode', $periodHint, [
            'total_balance' => 'SUM(CAST(COALESCE(loan_balance, 0) AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
            'distinct_accounts' => 'COUNT(DISTINCT account_number)',
        ]);

        return $this->compareMetrics(
            $sourceTable,
            $snapshotTable,
            'periode',
            'periode',
            $sourceMetrics,
            $snapshotMetrics,
            $periodHint
        );
    }

    private function auditSimpanan(?string $periodHint): array
    {
        $sourceTable = 'simpanan_multipn';
        $snapshotTable = 'dashboard_simpanan_snapshots';

        if (!$this->tablesAndColumnsAvailable([
            $sourceTable => ['posisi', 'saldo_idr', 'CIFNO'],
            $snapshotTable => ['snapshot_period', 'total_balance', 'source_row_count', 'cif_count'],
        ])) {
            return $this->auditUnavailable($sourceTable, $snapshotTable, $periodHint);
        }

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'posisi', $periodHint, [
            'total_balance' => 'SUM(CAST(COALESCE(saldo_idr, 0) AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
            'distinct_cif' => 'COUNT(DISTINCT CIFNO)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'snapshot_period', $periodHint, [
            'total_balance' => 'SUM(CAST(COALESCE(total_balance, 0) AS DECIMAL(20,2)))',
            'record_count' => 'SUM(source_row_count)',
            'distinct_cif' => 'SUM(cif_count)',
        ]);

        return $this->compareMetrics(
            $sourceTable,
            $snapshotTable,
            'posisi',
            'snapshot_period',
            $sourceMetrics,
            $snapshotMetrics,
            $periodHint
        );
    }

    private function auditSsaSimpanan(?string $periodHint): array
    {
        $sourceTable = 'ssa_simpanan';
        $snapshotTable = 'ssa_simpanan_snapshots';

        if (!$this->tablesAndColumnsAvailable([
            $sourceTable => ['Month_Day_Year_of_Posisi', 'saldo'],
            $snapshotTable => ['periode', 'total_saldo', 'record_count'],
        ])) {
            return $this->auditUnavailable($sourceTable, $snapshotTable, $periodHint);
        }

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'Month_Day_Year_of_Posisi', $periodHint, [
            'total_saldo' => 'SUM(CAST(COALESCE(saldo, 0) AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'periode', $periodHint, [
            'total_saldo' => 'SUM(CAST(COALESCE(total_saldo, 0) AS DECIMAL(20,2)))',
            'record_count' => 'SUM(record_count)',
        ]);

        return $this->compareMetrics(
            $sourceTable,
            $snapshotTable,
            'Month_Day_Year_of_Posisi',
            'periode',
            $sourceMetrics,
            $snapshotMetrics,
            $periodHint
        );
    }

    private function auditGenericSnapshot(string $tableName, ?string $periodHint): array
    {
        return [
            'status' => 'unsupported',
            'table_name' => $tableName,
            'period_hint' => $periodHint,
            'message' => "Audit not configured for table: {$tableName}",
            'discrepancies' => [],
            'summary' => [],
        ];
    }

    private function auditUnavailable(string $sourceTable, string $snapshotTable, ?string $periodHint): array
    {
        return [
            'status' => 'unsupported',
            'table_name' => $sourceTable,
            'snapshot_table' => $snapshotTable,
            'period_hint' => $periodHint,
            'message' => 'Audit skipped because the required table or columns are not available in the current schema.',
            'discrepancies' => [],
            'summary' => [],
            'total_periods_checked' => 0,
            'periods_with_issues' => 0,
        ];
    }

    /**
     * @param array<string, array<int, string>> $requirements
     */
    private function tablesAndColumnsAvailable(array $requirements): bool
    {
        foreach ($requirements as $table => $columns) {
            if (!Schema::hasTable($table)) {
                return false;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function getSourceMetrics(string $table, string $periodColumn, ?string $periodHint, array $metrics): array
    {
        $query = DB::table($table);

        if ($periodHint !== null && $periodHint !== '') {
            $query->where($periodColumn, $periodHint);
        }

        $selectParts = ["{$periodColumn} AS period"];
        foreach ($metrics as $alias => $expression) {
            $selectParts[] = "{$expression} AS {$alias}";
        }

        return $query
            ->selectRaw(implode(', ', $selectParts))
            ->groupBy($periodColumn)
            ->get()
            ->map(fn($row) => (array) $row)
            ->keyBy('period')
            ->all();
    }

    private function getSnapshotMetrics(string $table, string $periodColumn, ?string $periodHint, array $metrics): array
    {
        $query = DB::table($table);

        if ($periodHint !== null && $periodHint !== '') {
            $query->where($periodColumn, $periodHint);
        }

        $selectParts = ["{$periodColumn} AS period"];
        foreach ($metrics as $alias => $expression) {
            $selectParts[] = "{$expression} AS {$alias}";
        }

        return $query
            ->selectRaw(implode(', ', $selectParts))
            ->groupBy($periodColumn)
            ->get()
            ->map(fn($row) => (array) $row)
            ->keyBy('period')
            ->all();
    }

    private function compareMetrics(
        string $sourceTable,
        string $snapshotTable,
        string $sourcePeriodCol,
        string $snapshotPeriodCol,
        array $sourceMetrics,
        array $snapshotMetrics,
        ?string $periodHint
    ): array {
        $discrepancies = [];
        $allPeriods = array_unique(array_merge(
            array_keys($sourceMetrics),
            array_keys($snapshotMetrics)
        ));

        foreach ($allPeriods as $period) {
            $sourceMets = $sourceMetrics[$period] ?? [];
            $snapshotMets = $snapshotMetrics[$period] ?? [];

            $periodDiscrepancy = $this->comparePeriodMetrics($period, $sourceMets, $snapshotMets);
            if (!empty($periodDiscrepancy['differences'])) {
                $discrepancies[] = $periodDiscrepancy;
            }
        }

        $summary = $this->generateAuditSummary($sourceTable, $discrepancies);

        return [
            'status' => empty($discrepancies) ? 'clean' : 'has_discrepancies',
            'table_name' => $sourceTable,
            'snapshot_table' => $snapshotTable,
            'period_hint' => $periodHint,
            'audit_timestamp' => now()->toIso8601String(),
            'discrepancies' => $discrepancies,
            'summary' => $summary,
            'total_periods_checked' => count($allPeriods),
            'periods_with_issues' => count($discrepancies),
        ];
    }

    private function comparePeriodMetrics(string $period, array $sourceMetrics, array $snapshotMetrics): array
    {
        $differences = [];

        if (empty($sourceMetrics) && empty($snapshotMetrics)) {
            return ['period' => $period, 'differences' => []];
        }

        if (empty($sourceMetrics)) {
            return [
                'period' => $period,
                'differences' => [
                    [
                        'metric' => 'all',
                        'severity' => 'critical',
                        'message' => 'Period exists in snapshot but not in source (should be deleted)',
                        'source_value' => 0,
                        'snapshot_value' => 'EXISTS',
                    ],
                ],
            ];
        }

        if (empty($snapshotMetrics)) {
            return [
                'period' => $period,
                'differences' => [
                    [
                        'metric' => 'all',
                        'severity' => 'critical',
                        'message' => 'Period missing from snapshot (should be created)',
                        'source_value' => 'EXISTS',
                        'snapshot_value' => 0,
                    ],
                ],
            ];
        }

        foreach ($sourceMetrics as $metric => $sourceValue) {
            if ($metric === 'period') {
                continue;
            }

            $snapshotValue = $snapshotMetrics[$metric] ?? null;
            if ($snapshotValue === null) {
                $differences[] = [
                    'metric' => $metric,
                    'severity' => 'warning',
                    'message' => "Metric '{$metric}' missing from snapshot",
                    'source_value' => $sourceValue,
                    'snapshot_value' => null,
                ];
                continue;
            }

            $diff = $this->compareValues($sourceValue, $snapshotValue);
            if ($diff != 0.0) {
                $severity = abs($diff) > 0.01 ? 'critical' : 'warning';
                $differences[] = [
                    'metric' => $metric,
                    'severity' => $severity,
                    'message' => "Mismatch in {$metric}: source={$sourceValue}, snapshot={$snapshotValue}, variance={$diff}",
                    'source_value' => $sourceValue,
                    'snapshot_value' => $snapshotValue,
                    'variance' => round($diff, self::AUDIT_PRECISION),
                    'variance_percent' => $sourceValue != 0 ? round(($diff / abs($sourceValue)) * 100, 2) : 0,
                ];
            }
        }

        return [
            'period' => $period,
            'differences' => $differences,
            'is_critical' => collect($differences)->where('severity', 'critical')->isNotEmpty(),
        ];
    }

    private function compareValues($source, $snapshot): float
    {
        $src = (float) ($source ?? 0);
        $snap = (float) ($snapshot ?? 0);

        return round($src - $snap, self::AUDIT_PRECISION);
    }

    private function generateAuditSummary(string $tableNam, array $discrepancies): array
    {
        $critical = 0;
        $warnings = 0;
        $affectedPeriods = [];

        foreach ($discrepancies as $disc) {
            $affectedPeriods[] = $disc['period'];
            foreach ($disc['differences'] as $diff) {
                if ($diff['severity'] === 'critical') {
                    $critical++;
                } else {
                    $warnings++;
                }
            }
        }

        return [
            'total_issues' => $critical + $warnings,
            'critical_issues' => $critical,
            'warnings' => $warnings,
            'affected_periods' => array_unique($affectedPeriods),
            'affected_period_count' => count(array_unique($affectedPeriods)),
            'action_required' => $critical > 0,
            'recommended_action' => $critical > 0
                ? 'Rebuild snapshots for affected periods'
                : ($warnings > 0 ? 'Review snapshot accuracy' : 'No action needed'),
        ];
    }
}
