<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SnapshotIntegrityGuard
{
    /**
     * @var array<string, array{period:string, identity?:array<int, string>, optional_identity?:array<int, string>, max_rows_per_period?:int|null}>
     */
    private const SNAPSHOTS = [
        'dashboard_harian_snapshots' => [
            'period' => 'snapshot_period',
            'identity' => ['kanca_key', 'unit_key'],
        ],
        'dashboard_pinjaman_snapshots' => [
            'period' => 'periode',
            'identity' => ['account_number', 'segmen_dashboard', 'produk_dashboard', 'cabang1', 'unit1'],
        ],
        'dashboard_pinjaman_chart_periodik_snapshots' => [
            'period' => 'periode',
            'identity' => ['source_uniqueid_namareport', 'account_number'],
        ],
        'dashboard_simpanan_snapshots' => [
            'period' => 'snapshot_period',
            'identity' => [],
            'max_rows_per_period' => 1,
        ],
        'dashboard_simpanan_branch_snapshots' => [
            'period' => 'snapshot_period',
            'identity' => ['kantor_cabang'],
        ],
        'ssa_simpanan_snapshots' => [
            'period' => 'periode',
            'identity' => ['Month_Day_Year_of_Posisi', 'nama_cabang', 'produk', 'segmentasi'],
        ],
        'rasio_casa_debitur_snapshots' => [
            'period' => 'loan_period',
            'identity' => ['branch_key', 'segment_key'],
        ],
        'rasio_casa_debitur_uker_snapshots' => [
            'period' => 'loan_period',
            'identity' => ['source_branch_key', 'uker_key', 'segment_key'],
        ],
        'rekening_dormant_snapshots' => [
            'period' => 'posisi',
            'identity' => ['branch_label', 'raw_branch', 'unit_kerja'],
            'optional_identity' => ['snapshot_version'],
        ],
        'performance_new_payroll_snapshots' => [
            'period' => 'snapshot_posisi',
            'identity' => ['branch'],
        ],
        'performance_rm_snapshots' => [
            'period' => 'periode',
            'identity' => ['cabang', 'unit', 'rm', 'segmen', 'produk'],
            'optional_identity' => ['branch_code'],
        ],
        'performance_rm_cabang_snapshots' => [
            'period' => 'periode',
            'identity' => ['cabang', 'segmen', 'produk'],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function snapshotTables(): array
    {
        return array_keys(self::SNAPSHOTS);
    }

    public function periodColumn(string $snapshotTable): ?string
    {
        $definition = $this->definition($snapshotTable);

        return $definition['period'] ?? null;
    }

    public function periodHasAnomaly(string $snapshotTable, string $period): bool
    {
        $result = $this->inspectPeriod($snapshotTable, $period);

        return ($result['status'] ?? 'skipped') === 'anomaly';
    }

    public function periodHasDuplicateKeys(string $snapshotTable, string $period): bool
    {
        $result = $this->inspectPeriod($snapshotTable, $period);

        return (int) ($result['duplicate_group_count'] ?? 0) > 0;
    }

    public function logIfAnomalous(string $snapshotTable, string $period, array $context = []): bool
    {
        $result = $this->inspectPeriod($snapshotTable, $period);
        if (($result['status'] ?? 'skipped') !== 'anomaly') {
            return false;
        }

        Log::warning('Snapshot integrity anomaly detected.', $context + [
            'snapshot_table' => $snapshotTable,
            'period' => $period,
            'row_count' => (int) ($result['row_count'] ?? 0),
            'duplicate_group_count' => (int) ($result['duplicate_group_count'] ?? 0),
            'reason' => $result['reason'] ?? null,
        ]);

        return true;
    }

    public function purgePeriodIfAnomalous(string $snapshotTable, string $period, array $context = []): bool
    {
        if (!$this->logIfAnomalous($snapshotTable, $period, $context)) {
            return false;
        }

        $periodColumn = $this->periodColumn($snapshotTable);
        if ($periodColumn === null
            || !Schema::hasTable($snapshotTable)
            || !Schema::hasColumn($snapshotTable, $periodColumn)) {
            return false;
        }

        DB::table($snapshotTable)->where($periodColumn, $period)->delete();

        Log::warning('Snapshot integrity guard purged anomalous snapshot period before rebuild.', $context + [
            'snapshot_table' => $snapshotTable,
            'period_column' => $periodColumn,
            'period' => $period,
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectPeriod(string $snapshotTable, string $period): array
    {
        $snapshotTable = strtolower(trim($snapshotTable));
        $period = trim($period);
        $definition = $this->definition($snapshotTable);

        if ($definition === null) {
            return $this->skipped($snapshotTable, $period, 'unregistered_snapshot');
        }

        $periodColumn = $definition['period'];
        if (!Schema::hasTable($snapshotTable)) {
            return $this->skipped($snapshotTable, $period, 'missing_table');
        }

        if (!Schema::hasColumn($snapshotTable, $periodColumn)) {
            return $this->skipped($snapshotTable, $period, 'missing_period_column');
        }

        $identityColumns = $this->availableIdentityColumns($snapshotTable, $definition);
        if ($identityColumns === null) {
            return $this->skipped($snapshotTable, $period, 'missing_identity_column');
        }

        try {
            $rowCount = (int) DB::table($snapshotTable)->where($periodColumn, $period)->count();
            $duplicateGroups = $this->duplicateGroups($snapshotTable, $periodColumn, $identityColumns, $period);
            $duplicateGroupCount = count($duplicateGroups);
            $maxRows = $definition['max_rows_per_period'] ?? null;
            $tooManyRows = is_int($maxRows) && $rowCount > $maxRows;

            return [
                'status' => ($duplicateGroupCount > 0 || $tooManyRows) ? 'anomaly' : 'ok',
                'snapshot_table' => $snapshotTable,
                'period_column' => $periodColumn,
                'period' => $period,
                'row_count' => $rowCount,
                'identity_columns' => $identityColumns,
                'duplicate_group_count' => $duplicateGroupCount,
                'duplicate_groups' => $duplicateGroups,
                'max_rows_per_period' => $maxRows,
                'reason' => $duplicateGroupCount > 0 ? 'duplicate_identity' : ($tooManyRows ? 'row_count_exceeds_limit' : null),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'snapshot_table' => $snapshotTable,
                'period_column' => $periodColumn,
                'period' => $period,
                'row_count' => 0,
                'identity_columns' => $identityColumns,
                'duplicate_group_count' => 0,
                'duplicate_groups' => [],
                'reason' => 'query_failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inspectTable(string $snapshotTable, ?string $period = null, int $limit = 5): array
    {
        $snapshotTable = strtolower(trim($snapshotTable));
        $periodColumn = $this->periodColumn($snapshotTable);

        if ($period !== null && trim($period) !== '') {
            return [$this->inspectPeriod($snapshotTable, trim($period))];
        }

        if ($periodColumn === null
            || !Schema::hasTable($snapshotTable)
            || !Schema::hasColumn($snapshotTable, $periodColumn)) {
            return [$this->inspectPeriod($snapshotTable, '')];
        }

        $periods = DB::table($snapshotTable)
            ->whereNotNull($periodColumn)
            ->select($periodColumn)
            ->distinct()
            ->orderByDesc($periodColumn)
            ->limit(max(1, min(50, $limit)))
            ->pluck($periodColumn)
            ->map(fn ($value): string => (string) $value)
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->values()
            ->all();

        if ($periods === []) {
            return [$this->inspectPeriod($snapshotTable, '')];
        }

        return array_map(
            fn (string $candidatePeriod): array => $this->inspectPeriod($snapshotTable, $candidatePeriod),
            $periods
        );
    }

    /**
     * @return array<string, array{period:string, identity?:array<int, string>, optional_identity?:array<int, string>, max_rows_per_period?:int|null}>|array{period:string, identity?:array<int, string>, optional_identity?:array<int, string>, max_rows_per_period?:int|null}|null
     */
    public function definition(?string $snapshotTable = null): ?array
    {
        if ($snapshotTable === null) {
            return self::SNAPSHOTS;
        }

        return self::SNAPSHOTS[strtolower(trim($snapshotTable))] ?? null;
    }

    /**
     * @return array<int, string>|null
     */
    private function availableIdentityColumns(string $snapshotTable, array $definition): ?array
    {
        $required = $definition['identity'] ?? [];
        foreach ($required as $column) {
            if (!Schema::hasColumn($snapshotTable, $column)) {
                return null;
            }
        }

        $optional = array_values(array_filter(
            $definition['optional_identity'] ?? [],
            fn (string $column): bool => Schema::hasColumn($snapshotTable, $column)
        ));

        return array_values(array_unique(array_merge($required, $optional)));
    }

    /**
     * @param array<int, string> $identityColumns
     * @return array<int, array<string, mixed>>
     */
    private function duplicateGroups(string $snapshotTable, string $periodColumn, array $identityColumns, string $period): array
    {
        $groupColumns = array_values(array_unique(array_merge([$periodColumn], $identityColumns)));

        $query = DB::table($snapshotTable)
            ->where($periodColumn, $period)
            ->select($groupColumns)
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy($groupColumns)
            ->havingRaw('COUNT(*) > 1')
            ->limit(10);

        return $query
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function skipped(string $snapshotTable, string $period, string $reason): array
    {
        return [
            'status' => 'skipped',
            'snapshot_table' => $snapshotTable,
            'period_column' => $this->periodColumn($snapshotTable),
            'period' => $period,
            'row_count' => 0,
            'identity_columns' => [],
            'duplicate_group_count' => 0,
            'duplicate_groups' => [],
            'reason' => $reason,
        ];
    }
}
