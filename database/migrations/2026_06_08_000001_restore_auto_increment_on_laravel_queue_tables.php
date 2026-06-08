<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->restoreAutoIncrement('jobs');
        $this->restoreAutoIncrement('failed_jobs');
    }

    public function down(): void
    {
        // Keep Laravel queue tables in their framework-compatible shape.
    }

    private function restoreAutoIncrement(string $table): void
    {
        if (!Schema::hasTable($table) || $this->hasAutoIncrement($table, 'id')) {
            return;
        }

        $duplicate = DB::selectOne(
            "SELECT `id`, COUNT(*) AS duplicate_count FROM `{$table}` GROUP BY `id` HAVING duplicate_count > 1 LIMIT 1"
        );

        if ($duplicate !== null) {
            throw new RuntimeException(sprintf(
                'Cannot restore AUTO_INCREMENT on %s.id because duplicate id %s exists.',
                $table,
                (string) ($duplicate->id ?? '')
            ));
        }

        DB::statement("ALTER TABLE `{$table}` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");

        $maxId = (int) (DB::table($table)->max('id') ?? 0);
        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = " . max(1, $maxId + 1));
    }

    private function hasAutoIncrement(string $table, string $column): bool
    {
        $columnInfo = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return str_contains(strtolower((string) ($columnInfo->EXTRA ?? '')), 'auto_increment');
    }
};
