<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'simpanan_multipn';
    private const COLUMN = 'cifno_clean';
    private const INDEX = 'simpanan_multipn_cifno_clean_index';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || !Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        $this->dropIndexIfExists(self::TABLE, self::INDEX);
        DB::statement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', self::TABLE, self::COLUMN));
    }

    public function down(): void
    {
        // No-op: simpanan_multipn already uses CIFNO directly in imports and snapshot joins.
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

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $tableName, $indexName));
    }
};
