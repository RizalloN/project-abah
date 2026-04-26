<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optimize daily_loan_dinamis covering index and cleanup redundancies.
     *
     * This migration:
     * 1. Expands the covering index to include 'plafon' (5th column)
     *    - Current: idx_daily_loan_report_filter_covering (periode, cabang1, unit1, baki_debet1)
     *    - New:     idx_daily_loan_report_filter_covering (periode, cabang1, unit1, baki_debet1, plafon)
     *    - Benefit: Both SUM(baki_debet1) and SUM(plafon) queries now run at Index-Only Scan speed
     *    - ReportSnapshotBuilder requires plafon for Grand Total dashboard calculations
     *
     * 2. Drops redundant indexes:
     *    - idx_loan_periode_cab_unit: Left-prefix duplicate of expanded covering index
     *    - daily_loan_dinamis_cabang1_index: Redundant (all queries filter by periode first)
     *    - daily_loan_dinamis_unit1_index: Redundant (all queries filter by periode first)
     *
     * Expected impact:
     * - Dashboard queries for SUM(plafon): Cache-less table access → Index-Only Scan (massive speedup)
     * - LOAD DATA operations: Fewer indexes to maintain (15-25% faster inserts)
     * - Disk space: Redundant indexes removed
     */

    private const TABLE = 'daily_loan_dinamis';
    private const OLD_COVERING_INDEX = 'idx_daily_loan_report_filter_covering';
    private const REDUNDANT_INDEXES = [
        'idx_loan_periode_cab_unit',
        'daily_loan_dinamis_cabang1_index',
        'daily_loan_dinamis_unit1_index',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        // 1. Drop redundant indexes first (before rebuilding covering index)
        foreach (self::REDUNDANT_INDEXES as $indexName) {
            $this->dropIndexIfExists(self::TABLE, $indexName);
        }

        // 2. Expand covering index to include plafon
        // Drop old version first
        $this->dropIndexIfExists(self::TABLE, self::OLD_COVERING_INDEX);

        // Create new expanded version with plafon included
        Schema::table(self::TABLE, function ($table) {
            $table->index(
                ['periode', 'cabang1', 'unit1', 'baki_debet1', 'plafon'],
                self::OLD_COVERING_INDEX
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        // Restore old covering index version (without plafon)
        $this->dropIndexIfExists(self::TABLE, self::OLD_COVERING_INDEX);

        Schema::table(self::TABLE, function ($table) {
            $table->index(
                ['periode', 'cabang1', 'unit1', 'baki_debet1'],
                self::OLD_COVERING_INDEX
            );
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, static function ($tableBlueprint) use ($indexName): void {
            $tableBlueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
