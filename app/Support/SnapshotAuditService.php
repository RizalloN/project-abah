<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'ssa_pinjaman' => $this->auditSsaPinjaman($periodHint),
                'lw325_ph' => $this->auditLw325Ph($periodHint),
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

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_plafon' => 'SUM(CAST(plafon AS DECIMAL(20,2)))',
            'total_baki_debet' => 'SUM(CAST(baki_debet AS DECIMAL(20,2)))',
            'total_npl_amount' => 'SUM(CAST(CASE WHEN kol_adkval > 2 THEN baki_debet ELSE 0 END AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
            'distinct_debitur' => 'COUNT(DISTINCT CIFNO)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'snapshot_period', $periodHint, [
            'total_plafon' => 'SUM(CAST(total_plafon_amount AS DECIMAL(20,2)))',
            'total_baki_debet' => 'SUM(CAST(total_baki_debet_amount AS DECIMAL(20,2)))',
            'total_npl_amount' => 'SUM(CAST(total_npl_amount AS DECIMAL(20,2)))',
            'record_count' => 'SUM(source_row_count)',
            'distinct_debitur' => 'COUNT(DISTINCT id)',
        ]);

        return $this->compareMetrics(
            $sourceTable,
            $snapshotTable,
            'periode',
            'snapshot_period',
            $sourceMetrics,
            $snapshotMetrics,
            $periodHint
        );
    }

    private function auditSimpanan(?string $periodHint): array
    {
        $sourceTable = 'simpanan_multipn';
        $snapshotTable = 'dashboard_simpanan_snapshots';

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_saldo' => 'SUM(CAST(saldo_akhir AS DECIMAL(20,2)))',
            'total_bunga_bersih' => 'SUM(CAST(bunga_bersih AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
            'distinct_nasabah' => 'COUNT(DISTINCT cif)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'snapshot_period', $periodHint, [
            'total_saldo' => 'SUM(CAST(total_saldo_amount AS DECIMAL(20,2)))',
            'total_bunga_bersih' => 'SUM(CAST(total_bunga_bersih_amount AS DECIMAL(20,2)))',
            'record_count' => 'SUM(source_row_count)',
            'distinct_nasabah' => 'COUNT(DISTINCT id)',
        ]);

        return $this->compareMetrics(
            $sourceTable,
            $snapshotTable,
            'periode',
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

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_saldo' => 'SUM(CAST(saldo AS DECIMAL(20,2)))',
            'total_bunga' => 'SUM(CAST(bunga AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'periode', $periodHint, [
            'total_saldo' => 'SUM(CAST(total_saldo AS DECIMAL(20,2)))',
            'total_bunga' => 'SUM(CAST(total_bunga AS DECIMAL(20,2)))',
            'record_count' => 'SUM(record_count)',
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

    private function auditSsaPinjaman(?string $periodHint): array
    {
        $sourceTable = 'ssa_pinjaman';
        $snapshotTable = 'ssa_pinjaman_snapshots';

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_os_awal' => 'SUM(CAST(os_awal AS DECIMAL(20,2)))',
            'total_os_akhir' => 'SUM(CAST(os_akhir AS DECIMAL(20,2)))',
            'total_bunga' => 'SUM(CAST(bunga AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'periode', $periodHint, [
            'total_os_awal' => 'SUM(CAST(total_os_awal AS DECIMAL(20,2)))',
            'total_os_akhir' => 'SUM(CAST(total_os_akhir AS DECIMAL(20,2)))',
            'total_bunga' => 'SUM(CAST(total_bunga AS DECIMAL(20,2)))',
            'record_count' => 'SUM(record_count)',
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

    private function auditLw325Ph(?string $periodHint): array
    {
        $sourceTable = 'lw325_ph';
        $snapshotTable = 'lw325_ph_snapshots';

        $sourceMetrics = $this->getSourceMetrics($sourceTable, 'periode', $periodHint, [
            'total_outstanding' => 'SUM(CAST(outstanding AS DECIMAL(20,2)))',
            'total_interest' => 'SUM(CAST(interest_amount AS DECIMAL(20,2)))',
            'record_count' => 'COUNT(*)',
        ]);

        $snapshotMetrics = $this->getSnapshotMetrics($snapshotTable, 'periode', $periodHint, [
            'total_outstanding' => 'SUM(CAST(total_outstanding AS DECIMAL(20,2)))',
            'total_interest' => 'SUM(CAST(total_interest AS DECIMAL(20,2)))',
            'record_count' => 'SUM(record_count)',
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
            if ($diff !== 0) {
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
