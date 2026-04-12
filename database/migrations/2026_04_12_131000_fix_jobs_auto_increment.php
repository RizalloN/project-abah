<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!Schema::hasTable('jobs')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT EXTRA
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?',
            ['jobs', 'id']
        );

        $hasAutoIncrement = str_contains(strtolower((string) ($column->EXTRA ?? $column->extra ?? '')), 'auto_increment');

        $hasPrimaryKey = DB::selectOne(
            'SELECT 1
             FROM information_schema.table_constraints
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND constraint_type = ?
             LIMIT 1',
            ['jobs', 'PRIMARY KEY']
        ) !== null;

        if (!$hasPrimaryKey) {
            DB::statement('ALTER TABLE `jobs` ADD PRIMARY KEY (`id`)');
        }

        if (!$hasAutoIncrement) {
            DB::statement('ALTER TABLE `jobs` MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // Hotfix schema; no rollback needed.
    }
};
