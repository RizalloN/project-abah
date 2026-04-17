<?php

/**
 * Adds dedicated composite "delete scope" indexes for the three report tables that
 * participate in the managed-delete system via ImportIndexController::DELETE_INDEX_HINTS.
 *
 * Strategy
 * --------
 * Each index covers (period_col, kanca_col, identity_col) so that the FORCE INDEX
 * branch of deleteRowsByIndexedSubqueryBatch() can issue:
 *
 *   DELETE FROM `table` FORCE INDEX (`idx_*_delete_scope`)
 *   WHERE (period_col = ?) AND (kanca_col = ?)
 *   LIMIT n
 *
 * without a full-table scan, even when either filter is NULL/empty (IS NULL lookups
 * are resolved via the leading index prefix in InnoDB).
 *
 * Note: In InnoDB every secondary index already includes the primary key columns
 * implicitly.  The explicit inclusion of uniqueid_namareport/uniqueid_SMPN here
 * is intentional — it documents the intent and makes covering-index scans visible
 * in EXPLAIN output.
 *
 * Tables covered
 * --------------
 * - performance_pis_per_produk   idx_pis_delete_scope           (posisi, kanca, uniqueid_namareport)
 * - cognos_recovery              idx_cognos_recovery_delete_scope (periode, cabang, uniqueid_namareport)
 * - cognos_ph                    idx_cognos_ph_delete_scope       (periode, kanca, uniqueid_namareport)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $rows = DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$indexName]
        );

        return !empty($rows);
    }

    public function up(): void
    {
        // 1. performance_pis_per_produk — posisi (DATE) + kanca (VARCHAR) + identity PK
        if (!$this->indexExists('performance_pis_per_produk', 'idx_pis_delete_scope')) {
            Schema::table('performance_pis_per_produk', function (Blueprint $table) {
                $table->index(
                    ['posisi', 'kanca', 'uniqueid_namareport'],
                    'idx_pis_delete_scope'
                );
            });
        }

        // 2. cognos_recovery — periode (DATE) + cabang (VARCHAR) + identity PK
        if (!$this->indexExists('cognos_recovery', 'idx_cognos_recovery_delete_scope')) {
            Schema::table('cognos_recovery', function (Blueprint $table) {
                $table->index(
                    ['periode', 'cabang', 'uniqueid_namareport'],
                    'idx_cognos_recovery_delete_scope'
                );
            });
        }

        // 3. cognos_ph — periode (DATE) + kanca (VARCHAR) + identity PK
        if (!$this->indexExists('cognos_ph', 'idx_cognos_ph_delete_scope')) {
            Schema::table('cognos_ph', function (Blueprint $table) {
                $table->index(
                    ['periode', 'kanca', 'uniqueid_namareport'],
                    'idx_cognos_ph_delete_scope'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('performance_pis_per_produk', 'idx_pis_delete_scope')) {
            Schema::table('performance_pis_per_produk', function (Blueprint $table) {
                $table->dropIndex('idx_pis_delete_scope');
            });
        }

        if ($this->indexExists('cognos_recovery', 'idx_cognos_recovery_delete_scope')) {
            Schema::table('cognos_recovery', function (Blueprint $table) {
                $table->dropIndex('idx_cognos_recovery_delete_scope');
            });
        }

        if ($this->indexExists('cognos_ph', 'idx_cognos_ph_delete_scope')) {
            Schema::table('cognos_ph', function (Blueprint $table) {
                $table->dropIndex('idx_cognos_ph_delete_scope');
            });
        }
    }
};
