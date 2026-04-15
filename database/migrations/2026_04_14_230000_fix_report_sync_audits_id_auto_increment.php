<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('report_sync_audits') || !Schema::hasColumn('report_sync_audits', 'id')) {
            return;
        }

        $column = DB::selectOne("
            SELECT COLUMN_TYPE, IS_NULLABLE, EXTRA
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'report_sync_audits'
              AND COLUMN_NAME = 'id'
            LIMIT 1
        ");

        if (!$column) {
            return;
        }

        $extra = strtolower((string) ($column->EXTRA ?? ''));
        if (str_contains($extra, 'auto_increment')) {
            return;
        }

        DB::statement('ALTER TABLE `report_sync_audits` ADD PRIMARY KEY (`id`)');
        DB::statement('ALTER TABLE `report_sync_audits` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    public function down(): void
    {
        if (!Schema::hasTable('report_sync_audits') || !Schema::hasColumn('report_sync_audits', 'id')) {
            return;
        }

        DB::statement('ALTER TABLE `report_sync_audits` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL');
    }
};
