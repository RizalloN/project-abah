<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jumlah_merchant_qris_detail')) {
            return;
        }

        $this->dropIndexIfExists('jumlah_merchant_qris_detail', 'idx_unique_id');
        $this->dropIndexIfExists('jumlah_merchant_qris_detail', 'idx_posisi_uid');
    }

    public function down(): void
    {
        // No-op: these indexes duplicate PRIMARY/left-prefix coverage and add import cost.
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
