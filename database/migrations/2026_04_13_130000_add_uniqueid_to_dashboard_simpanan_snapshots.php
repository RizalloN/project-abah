<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!Schema::hasTable('dashboard_simpanan_snapshots') || Schema::hasColumn('dashboard_simpanan_snapshots', 'uniqueid_dss')) {
            return;
        }

        $table = 'dashboard_simpanan_snapshots';
        $temporaryTable = $table . '__rebuild';
        $backupTable = $table . '__backup';

        DB::statement("DROP TABLE IF EXISTS `{$temporaryTable}`");
        DB::statement("DROP TABLE IF EXISTS `{$backupTable}`");
        DB::statement("CREATE TABLE `{$temporaryTable}` LIKE `{$table}`");
        DB::statement("ALTER TABLE `{$temporaryTable}` ADD COLUMN `uniqueid_dss` varchar(191) NOT NULL FIRST");
        DB::statement("ALTER TABLE `{$temporaryTable}` DROP PRIMARY KEY");
        DB::statement("ALTER TABLE `{$temporaryTable}` ADD PRIMARY KEY (`uniqueid_dss`)");
        DB::statement("ALTER TABLE `{$temporaryTable}` ADD UNIQUE KEY `uq_dss_snapshot_period` (`snapshot_period`)");
        DB::statement(<<<'SQL'
INSERT INTO `dashboard_simpanan_snapshots__rebuild` (
    `uniqueid_dss`, `snapshot_period`, `total_balance`, `account_count`, `cif_count`, `branch_count`, `unit_count`,
    `tabungan_balance`, `giro_balance`, `other_balance`, `top_branch_label`, `top_branch_balance`,
    `source_row_count`, `source_updated_at`, `created_at`, `updated_at`
)
SELECT
    MD5(CONCAT_WS('|', 'dss', `snapshot_period`)) as `uniqueid_dss`,
    `snapshot_period`,
    MAX(`total_balance`) as `total_balance`,
    MAX(`account_count`) as `account_count`,
    MAX(`cif_count`) as `cif_count`,
    MAX(`branch_count`) as `branch_count`,
    MAX(`unit_count`) as `unit_count`,
    MAX(`tabungan_balance`) as `tabungan_balance`,
    MAX(`giro_balance`) as `giro_balance`,
    MAX(`other_balance`) as `other_balance`,
    MAX(`top_branch_label`) as `top_branch_label`,
    MAX(`top_branch_balance`) as `top_branch_balance`,
    MAX(`source_row_count`) as `source_row_count`,
    MAX(`source_updated_at`) as `source_updated_at`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `dashboard_simpanan_snapshots`
GROUP BY `snapshot_period`
SQL);
        DB::statement("RENAME TABLE `{$table}` TO `{$backupTable}`, `{$temporaryTable}` TO `{$table}`");
        DB::statement("DROP TABLE `{$backupTable}`");
    }

    public function down(): void
    {
        // Rebuild migration is one-way; the snapshot table is intentionally normalized to unique id + unique period.
    }
};
