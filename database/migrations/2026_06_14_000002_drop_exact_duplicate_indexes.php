<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DUPLICATE_INDEXES = [
        ['simpanan_multipn', 'idx_unique_id'],
        ['user_brimo_rpt_v2', 'idx_unique_id'],
        ['shadow_backfill_checkpoints', 'shadow_backfill_checkpoints_period_index'],
    ];

    public function up(): void
    {
        foreach (self::DUPLICATE_INDEXES as [$tableName, $indexName]) {
            $this->dropIndexIfExists($tableName, $indexName);
        }
    }

    public function down(): void
    {
        // No-op: these indexes duplicate PRIMARY/UNIQUE coverage and increase storage/import cost.
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

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
