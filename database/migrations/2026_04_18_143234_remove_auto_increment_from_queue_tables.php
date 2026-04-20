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
        // 1. jobs table: Remove AUTO_INCREMENT
        if ($this->shouldRemoveAutoIncrement('jobs', 'id')) {
            DB::statement('ALTER TABLE jobs MODIFY id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE jobs AUTO_INCREMENT = 1');
        }

        // 2. failed_jobs table: Remove AUTO_INCREMENT
        if ($this->shouldRemoveAutoIncrement('failed_jobs', 'id')) {
            DB::statement('ALTER TABLE failed_jobs MODIFY id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE failed_jobs AUTO_INCREMENT = 1');
        }

        // 3. job_batches table: Convert from string to BIGINT and remove any default
        // Standard Laravel uses string(255) IDs for batches.
        if ($this->shouldConvertJobBatchId()) {
            DB::statement('ALTER TABLE job_batches MODIFY id BIGINT UNSIGNED NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. restore jobs
        if (Schema::hasTable('jobs')) {
            DB::statement('ALTER TABLE jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        // 2. restore failed_jobs
        if (Schema::hasTable('failed_jobs')) {
            DB::statement('ALTER TABLE failed_jobs MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        // 3. restore job_batches (back to string)
        if (Schema::hasTable('job_batches')) {
            DB::statement('ALTER TABLE job_batches MODIFY id VARCHAR(255) NOT NULL');
        }
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

    private function shouldConvertJobBatchId(): bool
    {
        if (DB::getDriverName() !== 'mysql' || !Schema::hasTable('job_batches')) {
            return false;
        }

        $columnInfo = DB::selectOne(
            'SELECT DATA_TYPE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['job_batches', 'id']
        );

        $dataType = strtolower((string) ($columnInfo->DATA_TYPE ?? ''));

        return in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)
            && str_contains(strtolower((string) ($columnInfo->EXTRA ?? '')), 'auto_increment');
    }
};
