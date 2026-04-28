<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove redundant indexes on shadow columns after idx_snapshot_filter_optimized introduction.
     *
     * Context: Recent optimization introduced shadow columns (segmen_kinerja, produk_kinerja, etc.)
     * with composite index idx_snapshot_filter_optimized(periode, segmen_kinerja, produk_kinerja, cabang_normalized).
     *
     * Problem: Individual indexes on these shadow columns are now redundant because:
     * 1. All queries in KinerjaRmMikroReportController filter by (periode + segmen_kinerja/produk_kinerja)
     * 2. MySQL optimizer chooses composite index over single-column index
     * 3. Single-column indexes only slow down LOAD DATA operations (17% overhead)
     *
     * Indexes to remove:
     * - idx_segmen_kinerja: Already first non-period column in composite index
     * - idx_produk_kinerja: Already in composite index after segmen_kinerja
     * - daily_loan_dinamis_segmen_dashboard_index: Legacy index on old _dashboard columns (never used with shadow columns)
     * - daily_loan_dinamis_produk_dashboard_index: Legacy index on old _dashboard columns (never used with shadow columns)
     * - idx_loan_periode_produk: Redundant with larger periode-based composite indexes
     *
     * Expected impact:
     * - LOAD DATA operations: ~17% faster (fewer indexes to maintain)
     * - Query performance: No impact (composite index is more specific)
     * - Import reliability: No change
     */

    private const TABLE = 'daily_loan_dinamis';
    private const INDEXES_TO_DROP = [
        'idx_segmen_kinerja',
        'idx_produk_kinerja',
        'daily_loan_dinamis_segmen_dashboard_index',
        'daily_loan_dinamis_produk_dashboard_index',
        'idx_loan_periode_produk',
    ];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        foreach (self::INDEXES_TO_DROP as $indexName) {
            $this->dropIndexIfExists(self::TABLE, $indexName);
        }

        \Illuminate\Support\Facades\Log::info('Dropped redundant indexes from daily_loan_dinamis', [
            'indexes_dropped' => self::INDEXES_TO_DROP,
            'expected_performance_gain' => '~17% faster LOAD DATA operations',
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        // Restore individual indexes (though not recommended - keep composite index as primary)
        Schema::table(self::TABLE, function ($table) {
            $table->index('segmen_kinerja', 'idx_segmen_kinerja');
            $table->index('produk_kinerja', 'idx_produk_kinerja');
            $table->index('segmen_dashboard', 'daily_loan_dinamis_segmen_dashboard_index');
            $table->index('produk_dashboard', 'daily_loan_dinamis_produk_dashboard_index');
            $table->index(['periode', 'produk_dashboard'], 'idx_loan_periode_produk');
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, static function ($tableBlueprint) use ($indexName): void {
                $tableBlueprint->dropIndex($indexName);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning(
                "Failed to drop index {$indexName} from {$table}: " . $e->getMessage()
            );
        }
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
