<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SnapshotSourceSignatureService
{
    private const TABLE = 'snapshot_source_signatures';
    private const SIGNATURE_VERSION = 'snapshot-source-v1';
    private const BUCKET_SIGNATURE_VERSION = 'snapshot-source-v2-buckets';
    private const SNAPSHOT_FORMULA_VERSIONS = [
        'performance_rm_snapshots' => 'performance-rm-v5-valid-consumer-plafon-basis',
    ];

    private const NUMERIC_COLUMNS = [
        'daily_loan_dinamis' => [
            'baki_debet1',
            'baki_debet',
            'plafon',
            'tunggakan_pokok',
            'tunggakan_bunga',
            'tunggakan_penalti',
            'tunggakan_pinalti',
        ],
        'simpanan_multipn' => [
            'saldo_idr',
            'saldo',
        ],
        'ssa_simpanan' => [
            'saldo',
        ],
        'hourly_dpk' => [
            'saldo',
        ],
        'ssa_pinjaman' => [
            'baki_debet',
            'plafon',
            'plafond',
        ],
        'lw325_ph' => [
            'pokok',
        ],
    ];

    /**
     * @return array{source_signature: string, source_row_count: int, source_max_updated_at: mixed, payload: array<string, mixed>}|null
     */
    public function capture(string $sourceTable, string $periodColumn, string $period): ?array
    {
        $sourceTable = strtolower(trim($sourceTable));
        $periodColumn = trim($periodColumn);
        $period = trim($period);

        if ($sourceTable === ''
            || $periodColumn === ''
            || $period === ''
            || !Schema::hasTable($sourceTable)
            || !Schema::hasColumn($sourceTable, $periodColumn)) {
            return null;
        }

        $query = DB::table($sourceTable)
            ->where($periodColumn, $period)
            ->selectRaw('COUNT(*) as source_row_count');

        $grammar = DB::connection()->getQueryGrammar();
        $selectedAliases = [];

        foreach (['updated_at', 'created_at', 'id', 'uniqueid_namareport'] as $column) {
            if (!Schema::hasColumn($sourceTable, $column)) {
                continue;
            }

            $alias = 'max_' . $column;
            $query->selectRaw('MAX(' . $grammar->wrap($column) . ') as ' . $alias);
            $selectedAliases[] = $alias;
        }

        foreach (self::NUMERIC_COLUMNS[$sourceTable] ?? [] as $column) {
            if (!Schema::hasColumn($sourceTable, $column)) {
                continue;
            }

            $alias = 'sum_' . preg_replace('/[^A-Za-z0-9_]/', '_', $column);
            $query->selectRaw('COALESCE(SUM(COALESCE(' . $grammar->wrap($column) . ', 0)), 0) as ' . $alias);
            $selectedAliases[] = $alias;
        }

        $row = (array) $query->first();
        $rowCount = (int) ($row['source_row_count'] ?? 0);
        if ($rowCount <= 0) {
            return null;
        }

        $payload = [
            'version' => self::SIGNATURE_VERSION,
            'source_table' => $sourceTable,
            'period_column' => $periodColumn,
            'period' => $period,
            'source_row_count' => $rowCount,
        ];

        foreach ($selectedAliases as $alias) {
            $payload[$alias] = $this->normalizeAggregateValue($row[$alias] ?? null);
        }

        $bucketSignatures = $this->captureBucketSignatures($sourceTable, $periodColumn, $period);
        if ($bucketSignatures !== []) {
            $payload['bucket_signature_version'] = self::BUCKET_SIGNATURE_VERSION;
            $payload['bucket_signatures'] = $bucketSignatures;
        }

        ksort($payload);

        return [
            'source_signature' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE)),
            'source_row_count' => $rowCount,
            'source_max_updated_at' => $this->normalizeTimestamp($row['max_updated_at'] ?? $row['max_created_at'] ?? null),
            'payload' => $payload,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function captureBucketSignatures(string $sourceTable, string $periodColumn, string $period): array
    {
        $bucketColumn = $this->resolveBucketColumn($sourceTable);
        if ($bucketColumn === null) {
            return [];
        }

        $grammar = DB::connection()->getQueryGrammar();
        $query = DB::table($sourceTable)
            ->where($periodColumn, $period)
            ->selectRaw("UPPER(TRIM(COALESCE(" . $grammar->wrap($bucketColumn) . ", ''))) as bucket_key")
            ->selectRaw('COUNT(*) as source_row_count');

        foreach (self::NUMERIC_COLUMNS[$sourceTable] ?? [] as $column) {
            if (!Schema::hasColumn($sourceTable, $column)) {
                continue;
            }

            $query->selectRaw('COALESCE(SUM(COALESCE(' . $grammar->wrap($column) . ', 0)), 0) as sum_' . preg_replace('/[^A-Za-z0-9_]/', '_', $column));
        }

        return $query
            ->groupBy('bucket_key')
            ->limit(200)
            ->get()
            ->mapWithKeys(function ($row): array {
                $payload = (array) $row;
                $bucket = (string) ($payload['bucket_key'] ?? '');
                unset($payload['bucket_key']);
                ksort($payload);

                return [$bucket !== '' ? $bucket : '*' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE))];
            })
            ->all();
    }

    private function resolveBucketColumn(string $sourceTable): ?string
    {
        $candidates = match ($sourceTable) {
            'daily_loan_dinamis' => ['cabang_normalized', 'cabang_normalized_gc', 'cabang1', 'branch1'],
            'simpanan_multipn' => ['kantor_cabang', 'branch_normalized', 'branch_normalized_gc'],
            'ssa_simpanan' => ['kanca', 'kantor_cabang'],
            'hourly_dpk' => ['kanca', 'kantor_cabang'],
            'ssa_pinjaman' => ['kanca', 'kantor_cabang'],
            'lw325_ph' => ['kanca', 'cabang'],
            default => [],
        };

        foreach ($candidates as $column) {
            if (Schema::hasColumn($sourceTable, $column)) {
                return $column;
            }
        }

        return null;
    }

    public function isFresh(string $sourceTable, string $snapshotTable, string $periodKey, ?array $sourceMetadata): bool
    {
        if ($sourceMetadata === null || !Schema::hasTable(self::TABLE)) {
            return false;
        }

        $existing = DB::table(self::TABLE)
            ->where('source_table', strtolower(trim($sourceTable)))
            ->where('snapshot_table', strtolower(trim($snapshotTable)))
            ->where('period_key', trim($periodKey))
            ->first(['source_signature', 'context']);

        if ($existing === null) {
            return false;
        }

        $formulaVersion = $this->snapshotFormulaVersion($snapshotTable);
        if ($formulaVersion !== null) {
            $context = json_decode((string) ($existing->context ?? ''), true);
            if (!is_array($context) || (string) ($context['snapshot_formula_version'] ?? '') !== $formulaVersion) {
                return false;
            }
        }

        return hash_equals(
                (string) ($existing->source_signature ?? ''),
                (string) ($sourceMetadata['source_signature'] ?? '')
            );
    }

    public function markBuilt(
        string $sourceTable,
        string $snapshotTable,
        string $periodKey,
        array $sourceMetadata,
        array $context = []
    ): void {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $now = now();

        $formulaVersion = $this->snapshotFormulaVersion($snapshotTable);
        if ($formulaVersion !== null) {
            $context['snapshot_formula_version'] = $formulaVersion;
        }

        DB::table(self::TABLE)->updateOrInsert(
            [
                'source_table' => strtolower(trim($sourceTable)),
                'snapshot_table' => strtolower(trim($snapshotTable)),
                'period_key' => trim($periodKey),
            ],
            [
                'source_signature' => (string) ($sourceMetadata['source_signature'] ?? ''),
                'source_row_count' => (int) ($sourceMetadata['source_row_count'] ?? 0),
                'source_max_updated_at' => $this->normalizeTimestamp($sourceMetadata['source_max_updated_at'] ?? null),
                'built_at' => $now,
                'context' => $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function snapshotFormulaVersion(string $snapshotTable): ?string
    {
        return self::SNAPSHOT_FORMULA_VERSIONS[strtolower(trim($snapshotTable))] ?? null;
    }

    /**
     * Capture current source state and mark snapshot built for every candidate
     * source that actually has rows for the given period. Sources without rows
     * are silently skipped (they did not contribute to this snapshot).
     *
     * @param string $snapshotTable
     * @param string $period Canonical period key (must match what
     *                       EnsureImportedSnapshotsFreshJob will look up).
     * @param array<int, array{source_table: string, period_column: string}> $candidates
     * @param array<string, mixed> $context
     * @return array<string, bool> source_table => marked
     */
    public function markBuiltForApplicableSources(
        string $snapshotTable,
        string $period,
        array $candidates,
        array $context = []
    ): array {
        $period = trim($period);
        if ($period === '' || !Schema::hasTable(self::TABLE)) {
            return [];
        }

        $results = [];

        foreach ($candidates as $candidate) {
            $sourceTable = strtolower(trim((string) ($candidate['source_table'] ?? '')));
            $periodColumn = trim((string) ($candidate['period_column'] ?? ''));
            if ($sourceTable === '' || $periodColumn === '') {
                continue;
            }

            try {
                $metadata = $this->capture($sourceTable, $periodColumn, $period);
            } catch (Throwable) {
                $metadata = null;
            }

            if ($metadata === null) {
                $results[$sourceTable] = false;
                continue;
            }

            $this->markBuilt($sourceTable, $snapshotTable, $period, $metadata, $context);
            $results[$sourceTable] = true;
        }

        return $results;
    }

    private function normalizeAggregateValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return trim((string) $value);
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return $value;
        }
    }
}
