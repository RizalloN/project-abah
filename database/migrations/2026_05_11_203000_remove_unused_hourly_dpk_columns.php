<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'hourly_dpk';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->dropForeignKeysForColumn('id_report');

        foreach (['id_report', 'created_at', 'updated_at'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                DB::statement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', self::TABLE, $column));
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!Schema::hasColumn(self::TABLE, 'id_report')) {
            DB::statement(sprintf('ALTER TABLE `%s` ADD COLUMN `id_report` BIGINT UNSIGNED NULL AFTER `uniqueid_namareport`', self::TABLE));
        }

        if (!Schema::hasColumn(self::TABLE, 'created_at')) {
            DB::statement(sprintf('ALTER TABLE `%s` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL', self::TABLE));
        }

        if (!Schema::hasColumn(self::TABLE, 'updated_at')) {
            DB::statement(sprintf('ALTER TABLE `%s` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL', self::TABLE));
        }
    }

    private function dropForeignKeysForColumn(string $column): void
    {
        if (!Schema::hasColumn(self::TABLE, $column)) {
            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', self::TABLE)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', self::TABLE, str_replace('`', '``', (string) $constraint)));
        }
    }
};
