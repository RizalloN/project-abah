<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'lw325_ph';
    private const INDEX = 'idx_lw325ph_period_acct_kanca_unit';
    private const INDEX_COLUMNS = ['periode', 'acctno', 'kanca', 'unit'];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!$this->hasLeftPrefixCoverage(self::TABLE, self::INDEX_COLUMNS)) {
            Schema::table(self::TABLE, static function ($table): void {
                $table->index(self::INDEX_COLUMNS, self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !$this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, static function ($table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function hasLeftPrefixCoverage(string $table, array $columns): bool
    {
        foreach ($this->indexColumnMap($table) as $indexedColumns) {
            if (array_slice($indexedColumns, 0, count($columns)) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return array_key_exists($indexName, $this->indexColumnMap($table));
    }

    private function indexColumnMap(string $table): array
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
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
