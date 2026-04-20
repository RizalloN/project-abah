<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!$this->shouldRemoveAutoIncrement('import_jobs', 'id')) {
            return;
        }

        // Remove AUTO_INCREMENT from id column
        DB::statement('ALTER TABLE import_jobs MODIFY id BIGINT UNSIGNED NOT NULL');
        
        // Reset AUTO_INCREMENT value to 1 just in case
        DB::statement('ALTER TABLE import_jobs AUTO_INCREMENT = 1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        // Restore AUTO_INCREMENT to id column
        DB::statement('ALTER TABLE import_jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    private function shouldRemoveAutoIncrement(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable($table)) {
            return false;
        }

        $columnInfo = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return str_contains(strtolower((string) ($columnInfo->EXTRA ?? '')), 'auto_increment');
    }
};
