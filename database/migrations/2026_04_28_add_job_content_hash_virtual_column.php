<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration: Add Virtual Generated Column for Content Hash Indexing
 *
 * Menambahkan kolom virtual yang mengekstrak content_hash dari job_context JSON,
 * kemudian di-index untuk optimasi pencarian.
 *
 * Alasan Menggunakan VIRTUAL:
 * - Tidak menyimpan data duplikat di disk (zero storage overhead)
 * - Nilai dikomputasi otomatis dari JSON saat dibaca
 * - Tetap bisa di-index untuk pencarian kilat O(log N)
 * - Sinkronisasi otomatis dengan source JSON
 *
 * Impact pada validateFileUniqueness():
 * - Mengubah kompleksitas dari O(N) → O(log N)
 * - Menghilangkan kebutuhan pull semua rows ke PHP untuk parsing
 * - Pencarian langsung ke database dengan index
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        Schema::table('import_jobs', function (Blueprint $blueprint) {
            // Cek apakah kolom sudah ada (untuk safety)
            $columns = DB::select(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'import_jobs'
                 AND COLUMN_NAME = 'job_content_hash'"
            );

            if (empty($columns)) {
                // Tambahkan Virtual Generated Column
                // Mengekstrak nilai content_hash dari JSON path '$.content_hash'
                DB::statement(
                    "ALTER TABLE `import_jobs`
                     ADD COLUMN `job_content_hash` VARCHAR(64)
                     GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(job_context, '$.content_hash')))
                     VIRTUAL
                     AFTER `job_context`"
                );
            }

            // Tambahkan index pada virtual column
            $this->ensureIndexExists('import_jobs', 'idx_import_jobs_content_hash', ['job_content_hash']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        try {
            // Drop index dulu
            DB::statement("ALTER TABLE `import_jobs` DROP INDEX `idx_import_jobs_content_hash`");
        } catch (\Exception) {
            // Index mungkin tidak ada
        }

        try {
            // Drop virtual column
            DB::statement("ALTER TABLE `import_jobs` DROP COLUMN `job_content_hash`");
        } catch (\Exception) {
            // Column mungkin tidak ada
        }
    }

    /**
     * Helper: Ensure index exists without throwing duplicate key error
     */
    private function ensureIndexExists(string $tableName, string $indexName, array $columns): void
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
                return; // Index sudah ada
            }

            DB::statement(
                "ALTER TABLE `{$tableName}`
                 ADD INDEX `{$indexName}` (" . implode(',', array_map(fn ($col) => "`{$col}`", $columns)) . ")"
            );
        } catch (\Exception $e) {
            // Log warning tapi jangan fail migration
            \Illuminate\Support\Facades\Log::warning("Failed to create index {$indexName}: " . $e->getMessage());
        }
    }
};
