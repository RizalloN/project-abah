<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering indexes for report filter optimization.
     *
     * These indexes allow MySQL to satisfy filter queries entirely from the index
     * without needing to access the table (Index-Only Scan).
     *
     * Expected performance improvement: 15-25% faster DISTINCT lookups on filters.
     */

    private const DAILY_LOAN_TABLE = 'daily_loan_dinamis';
    private const DAILY_LOAN_FILTER_INDEX = 'idx_daily_loan_report_filter_covering';
    private const DAILY_LOAN_FILTER_COLUMNS = ['periode', 'cabang1', 'unit1', 'baki_debet1'];

    private const LW325_PH_TABLE = 'lw325_ph';
    private const LW325_PH_FILTER_INDEX = 'idx_lw325ph_report_filter_covering';
    private const LW325_PH_FILTER_COLUMNS = ['periode', 'kanca', 'unit', 'segmen_dashboard', 'pokok'];

    public function up(): void
    {
        // Covering index for daily_loan_dinamis - supports:
        // - WHERE periode = ? AND cabang1 = ? AND unit1 = ?
        // - SELECT baki_debet1 (from index only, no table access needed)
        if (Schema::hasTable(self::DAILY_LOAN_TABLE) && !$this->indexExists(self::DAILY_LOAN_TABLE, self::DAILY_LOAN_FILTER_INDEX)) {
            Schema::table(self::DAILY_LOAN_TABLE, function ($table) {
                $table->index(self::DAILY_LOAN_FILTER_COLUMNS, self::DAILY_LOAN_FILTER_INDEX);
            });
        }

        // Covering index for lw325_ph - supports:
        // - WHERE periode = ? AND kanca = ? AND unit = ?
        // - SELECT pokok (from index only, no table access needed)
        if (Schema::hasTable(self::LW325_PH_TABLE) && !$this->indexExists(self::LW325_PH_TABLE, self::LW325_PH_FILTER_INDEX)) {
            Schema::table(self::LW325_PH_TABLE, function ($table) {
                $table->index(self::LW325_PH_FILTER_COLUMNS, self::LW325_PH_FILTER_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::DAILY_LOAN_TABLE) && $this->indexExists(self::DAILY_LOAN_TABLE, self::DAILY_LOAN_FILTER_INDEX)) {
            Schema::table(self::DAILY_LOAN_TABLE, function ($table) {
                $table->dropIndex(self::DAILY_LOAN_FILTER_INDEX);
            });
        }

        if (Schema::hasTable(self::LW325_PH_TABLE) && $this->indexExists(self::LW325_PH_TABLE, self::LW325_PH_FILTER_INDEX)) {
            Schema::table(self::LW325_PH_TABLE, function ($table) {
                $table->dropIndex(self::LW325_PH_FILTER_INDEX);
            });
        }
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
