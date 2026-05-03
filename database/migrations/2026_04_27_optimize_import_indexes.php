<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Optimize Import Table Indexes
 *
 * Menambah covering indexes untuk mempercepat:
 * 1. Duplicate detection (staging table join)
 * 2. Snapshot artifact queries
 * 3. Report filtering dan aggregation
 *
 * Tanpa menambah redundant indexes yang tumpang tindih.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ───────────────────────────────────────────────────────────────
        // Merchant QRIS Detail already has PRIMARY and POSISI-prefixed covering indexes.
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('jumlah_merchant_qris_detail')) {
            $this->dropIndexIfExists('jumlah_merchant_qris_detail', 'idx_unique_id');
            $this->dropIndexIfExists('jumlah_merchant_qris_detail', 'idx_posisi_uid');
        }

        // ───────────────────────────────────────────────────────────────
        // SV Merchant - Duplicate Key + Covering Indexes
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('sv_merchant')) {
            // Composite unique key untuk SV Merchant (PERIODE + uniqueid)
            $this->ensureIndexExists('sv_merchant', 'idx_periode_uid', [
                'PERIODE',
                'uniqueid_namareport',
            ], isUnique: false);

            // Covering index untuk POSISI queries
            $this->ensureIndexExists('sv_merchant', 'idx_posisi_periode_uid', [
                'POSISI',
                'PERIODE',
                'uniqueid_namareport',
            ], isUnique: false);

            // Covering index untuk branch filtering
            $this->ensureIndexExists('sv_merchant', 'idx_nama_branch_uid', [
                'NAMA_BRANCH',
                'uniqueid_namareport',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // Merchant QRIS (Jumlah) - Duplicate Key + Covering
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('jumlah_merchant_qris')) {
            $this->ensureIndexExists('jumlah_merchant_qris', 'idx_periode_uid', [
                'periode',
                'uniqueid_namareport',
            ], isUnique: false);

            $this->ensureIndexExists('jumlah_merchant_qris', 'idx_posisi_uid', [
                'posisi',
                'uniqueid_namareport',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // Merchant QRIS Volume - Duplicate Key + Covering
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('merchant_qris_volume')) {
            $this->ensureIndexExists('merchant_qris_volume', 'idx_periode_uid', [
                'periode',
                'uniqueid_namareport',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // User Brimo RPT v2 - Duplicate Key
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('user_brimo_rpt_v2')) {
            $this->ensureIndexExists('user_brimo_rpt_v2', 'idx_unique_id', [
                'uniqueid_namareport',
            ], isUnique: false);

            $this->ensureIndexExists('user_brimo_rpt_v2', 'idx_periode_uid', [
                'periode',
                'uniqueid_namareport',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // Brimo Fin - Duplicate Key
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('brimo_fin')) {
            $this->ensureIndexExists('brimo_fin', 'idx_periode_uid', [
                'periode',
                'uniqueid_namareport',
            ], isUnique: false);

            $this->ensureIndexExists('brimo_fin', 'idx_posisi_uid', [
                'posisi',
                'uniqueid_namareport',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // Simpanan Multi PN - Duplicate Key
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('simpanan_multipn')) {
            $this->ensureIndexExists('simpanan_multipn', 'idx_unique_id', [
                'uniqueid_SMPN',
            ], isUnique: false);

            // Covering index untuk posisi + uniqueid queries
            $this->ensureIndexExists('simpanan_multipn', 'idx_posisi_uid', [
                'posisi',
                'uniqueid_SMPN',
            ], isUnique: false);
        }

        // ───────────────────────────────────────────────────────────────
        // Pinjaman - Duplicate Key
        // ───────────────────────────────────────────────────────────────
        if (Schema::hasTable('pinjaman')) {
            $this->ensureIndexExists('pinjaman', 'idx_periode_uid', [
                'periode',
                'uniqueid_namareport',
            ], isUnique: false);

            $this->ensureIndexExists('pinjaman', 'idx_posisi_uid', [
                'posisi',
                'uniqueid_namareport',
            ], isUnique: false);
        }
    }

    public function down(): void
    {
        // Drop indexes (reverse operation) using raw SQL with IF EXISTS
        $tables = [
            'jumlah_merchant_qris_detail' => ['idx_unique_id', 'idx_posisi_uid'],
            'sv_merchant' => ['idx_periode_uid', 'idx_posisi_periode_uid', 'idx_nama_branch_uid'],
            'jumlah_merchant_qris' => ['idx_periode_uid', 'idx_posisi_uid'],
            'merchant_qris_volume' => ['idx_periode_uid'],
            'user_brimo_rpt_v2' => ['idx_unique_id', 'idx_periode_uid'],
            'brimo_fin' => ['idx_periode_uid', 'idx_posisi_uid'],
            'simpanan_multipn' => ['idx_unique_id', 'idx_posisi_uid'],
            'pinjaman' => ['idx_periode_uid', 'idx_posisi_uid'],
        ];

        foreach ($tables as $tableName => $indexes) {
            if (Schema::hasTable($tableName)) {
                foreach ($indexes as $indexName) {
                    try {
                        DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
                    } catch (\Exception $e) {
                        // Index might not exist, ignore error
                    }
                }
            }
        }
    }

    /**
     * Helper: Ensure index exists, skip if already there
     * (avoid "Duplicate key name" errors)
     */
    private function ensureIndexExists(string $tableName, string $indexName, array $columns, bool $isUnique = false): void
    {
        try {
            $indexes = DB::select(
                "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 AND INDEX_NAME = ?",
                [$tableName, $indexName]
            );

            if (!empty($indexes)) {
                // Index sudah ada, skip
                return;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName, $isUnique) {
                if ($isUnique) {
                    $table->unique($columns, $indexName);
                } else {
                    $table->index($columns, $indexName);
                }
            });

            echo "✓ Index {$indexName} pada tabel {$tableName} berhasil dibuat.\n";
        } catch (\Exception $e) {
            echo "⚠ Gagal membuat index {$indexName}: " . $e->getMessage() . "\n";
        }
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        $indexes = DB::select(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ?
             AND INDEX_NAME = ?
             LIMIT 1",
            [$tableName, $indexName]
        );

        if (empty($indexes)) {
            return;
        }

        DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
    }
};
