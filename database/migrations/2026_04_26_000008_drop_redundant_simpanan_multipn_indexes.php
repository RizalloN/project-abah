<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'simpanan_multipn';

    private const LEFT_PREFIX_REDUNDANT_INDEXES = [
        'idx_smp_posisi_status_cab_unit' => [
            'columns' => ['posisi', 'status', 'kantor_cabang', 'unit_kerja'],
            'covered_by' => 'idx_smp_dormant_covering',
        ],
        'idx_smp_posisi_cab_unit' => [
            'columns' => ['posisi', 'kantor_cabang', 'unit_kerja'],
            'covered_by' => 'idx_smp_period_covering_counts',
        ],
        'idx_smp_posisi_cif' => [
            'columns' => ['posisi', 'CIFNO'],
            'covered_by' => 'idx_smp_posisi_cif_covering',
        ],
    ];

    private const LOW_VALUE_SINGLE_COLUMN_INDEXES = [
        'simpanan_multipn_kantor_cabang_index',
        'simpanan_multipn_unit_kerja_index',
        'simpanan_multipn_status_index',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $indexes = $this->indexColumnMap(self::TABLE);

        foreach (self::LEFT_PREFIX_REDUNDANT_INDEXES as $indexName => $definition) {
            if (
                array_key_exists($indexName, $indexes)
                && $this->hasLeftPrefixCoverage($indexes, $definition['covered_by'], $definition['columns'])
            ) {
                Schema::table(self::TABLE, fn ($table) => $table->dropIndex($indexName));
            }
        }

        foreach (self::LOW_VALUE_SINGLE_COLUMN_INDEXES as $indexName) {
            if ($this->indexExists($indexName)) {
                Schema::table(self::TABLE, fn ($table) => $table->dropIndex($indexName));
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $definitions = [
            'idx_smp_posisi_status_cab_unit' => ['posisi', 'status', 'kantor_cabang', 'unit_kerja'],
            'idx_smp_posisi_cab_unit' => ['posisi', 'kantor_cabang', 'unit_kerja'],
            'idx_smp_posisi_cif' => ['posisi', 'CIFNO'],
            'simpanan_multipn_kantor_cabang_index' => ['kantor_cabang'],
            'simpanan_multipn_unit_kerja_index' => ['unit_kerja'],
            'simpanan_multipn_status_index' => ['status'],
        ];

        foreach ($definitions as $indexName => $columns) {
            if (!$this->indexExists($indexName)) {
                Schema::table(self::TABLE, fn ($table) => $table->index($columns, $indexName));
            }
        }
    }

    /**
     * @param array<string, array<int, string>> $indexes
     * @param array<int, string> $columns
     */
    private function hasLeftPrefixCoverage(array $indexes, string $coveringIndex, array $columns): bool
    {
        $coveringColumns = $indexes[$coveringIndex] ?? [];

        return array_slice($coveringColumns, 0, count($columns)) === $columns;
    }

    private function indexExists(string $indexName): bool
    {
        return array_key_exists($indexName, $this->indexColumnMap(self::TABLE));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function indexColumnMap(string $table): array
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return [];
        }

        $rows = DB::table('information_schema.statistics')
            ->select('index_name', 'column_name', 'seq_in_index')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->orderBy('index_name')
            ->orderBy('seq_in_index')
            ->get();

        $indexes = [];
        foreach ($rows as $row) {
            $indexes[(string) $row->index_name][] = (string) $row->column_name;
        }

        return $indexes;
    }
};
