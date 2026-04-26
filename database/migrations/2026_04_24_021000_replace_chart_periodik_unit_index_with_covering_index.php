<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'dashboard_pinjaman_chart_periodik_snapshots';
    private const OLD_INDEX = 'idx_dpcp_period_cabang_branch_unit';
    private const NEW_INDEX = 'idx_dpcp_period_cabang_branch_unit_pola';
    private const OLD_COLUMNS = ['periode', 'cabang1', 'branch1', 'unit1'];
    private const NEW_COLUMNS = ['periode', 'cabang1', 'branch1', 'unit1', 'pola_pembayaran'];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!$this->hasLeftPrefixCoverage(self::TABLE, self::NEW_COLUMNS)) {
            Schema::table(self::TABLE, static function ($table): void {
                $table->index(self::NEW_COLUMNS, self::NEW_INDEX);
            });
        }

        $this->dropIndexIfExists(self::TABLE, self::OLD_INDEX);
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!$this->hasLeftPrefixCoverage(self::TABLE, self::OLD_COLUMNS)) {
            Schema::table(self::TABLE, static function ($table): void {
                $table->index(self::OLD_COLUMNS, self::OLD_INDEX);
            });
        }

        $this->dropIndexIfExists(self::TABLE, self::NEW_INDEX);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, static function ($tableBlueprint) use ($indexName): void {
            $tableBlueprint->dropIndex($indexName);
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
