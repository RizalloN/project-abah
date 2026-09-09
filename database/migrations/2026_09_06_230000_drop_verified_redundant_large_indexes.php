<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only indexes proven redundant by current query plans and the slow-query log
     * are included here. Indexes used by branch, unit, RM, decision-maker, import,
     * and period-scoped delete paths are deliberately retained.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const INDEXES = [
        'simpanan_multipn' => [
            'idx_smp_jenis_simpanan_filter' => ['jenis_simpanan'],
        ],
        'daily_loan_dinamis' => [
            'daily_loan_dinamis_cifno_index' => ['cifno'],
            'idx_cifno_clean' => ['cifno_clean'],
        ],
    ];

    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::INDEXES as $tableName => $expectedIndexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $existingIndexes = $this->indexColumnMap($tableName);
            $dropNames = [];

            foreach ($expectedIndexes as $indexName => $expectedColumns) {
                if (! array_key_exists($indexName, $existingIndexes)) {
                    continue;
                }

                if ($existingIndexes[$indexName] !== $expectedColumns) {
                    throw new \RuntimeException(sprintf(
                        'Index %s.%s berubah definisi; migrasi dihentikan tanpa menghapus indeks tersebut.',
                        $tableName,
                        $indexName
                    ));
                }

                $dropNames[] = $indexName;
            }

            if ($dropNames === []) {
                continue;
            }

            $clauses = implode(', ', array_map(
                static fn (string $indexName): string => 'DROP INDEX `'.str_replace('`', '``', $indexName).'`',
                $dropNames
            ));
            $escapedTable = str_replace('`', '``', $tableName);

            DB::statement("ALTER TABLE `{$escapedTable}` {$clauses}, ALGORITHM=NOCOPY, LOCK=NONE");
        }
    }

    public function down(): void
    {
        // Rebuilding multi-gigabyte low-value indexes during rollback is unsafe.
    }

    /** @return array<string, array<int, string>> */
    private function indexColumnMap(string $tableName): array
    {
        $rows = DB::table('information_schema.statistics')
            ->select('index_name', 'column_name', 'seq_in_index')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
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
