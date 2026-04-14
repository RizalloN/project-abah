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

        $this->rebuildDashboardPinjamanSnapshots();
        $this->rebuildDashboardSimpananSnapshots();
        $this->rebuildDashboardSimpananBranchSnapshots();
        $this->rebuildRasioCasaSnapshots();
        $this->rebuildRekeningDormantSnapshots();
        $this->rebuildPerformanceNewPayrollSnapshots();
    }

    public function down(): void
    {
        // Snapshot tables are rebuilt in-place to restore the constraints expected by the app.
    }

    private function rebuildDashboardPinjamanSnapshots(): void
    {
        if (!Schema::hasTable('dashboard_pinjaman_snapshots') || $this->tableHasPrimaryKey('dashboard_pinjaman_snapshots', 'uniqueid_dps')) {
            return;
        }

        $this->rebuildTable(
            'dashboard_pinjaman_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`uniqueid_dps`)',
                'ALTER TABLE `__TEMP__` ADD KEY `dashboard_pinjaman_snapshots_periode_index` (`periode`)',
                'ALTER TABLE `__TEMP__` ADD KEY `dashboard_pinjaman_snapshots_account_number_index` (`account_number`)',
                'ALTER TABLE `__TEMP__` ADD KEY `dashboard_pinjaman_snapshots_quality_bucket_index` (`quality_bucket`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `uniqueid_dps`, `periode`, `account_number`, `loan_balance`, `quality_bucket`,
    `segmen_dashboard`, `produk_dashboard`, `cabang1`, `unit1`, `created_at`, `updated_at`
)
SELECT
    `uniqueid_dps`,
    MAX(`periode`) as `periode`,
    MAX(`account_number`) as `account_number`,
    MAX(`loan_balance`) as `loan_balance`,
    MAX(`quality_bucket`) as `quality_bucket`,
    MAX(`segmen_dashboard`) as `segmen_dashboard`,
    MAX(`produk_dashboard`) as `produk_dashboard`,
    MAX(`cabang1`) as `cabang1`,
    MAX(`unit1`) as `unit1`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `dashboard_pinjaman_snapshots`
GROUP BY `uniqueid_dps`
SQL
        );
    }

    private function rebuildDashboardSimpananSnapshots(): void
    {
        if (!Schema::hasTable('dashboard_simpanan_snapshots') || $this->tableHasPrimaryKey('dashboard_simpanan_snapshots', 'uniqueid_dss')) {
            return;
        }

        $this->rebuildTable(
            'dashboard_simpanan_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`snapshot_period`)',
                'ALTER TABLE `__TEMP__` ADD KEY `idx_dss_source_updated_at` (`source_updated_at`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `snapshot_period`, `total_balance`, `account_count`, `cif_count`, `branch_count`, `unit_count`,
    `tabungan_balance`, `giro_balance`, `other_balance`, `top_branch_label`, `top_branch_balance`,
    `source_row_count`, `source_updated_at`, `created_at`, `updated_at`
)
SELECT
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
SQL
        );
    }

    private function rebuildDashboardSimpananBranchSnapshots(): void
    {
        if (!Schema::hasTable('dashboard_simpanan_branch_snapshots') || $this->tableHasPrimaryKey('dashboard_simpanan_branch_snapshots', 'uniqueid_dsbs')) {
            return;
        }

        $this->rebuildTable(
            'dashboard_simpanan_branch_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`uniqueid_dsbs`)',
                'ALTER TABLE `__TEMP__` ADD UNIQUE KEY `uq_dsbs_period_branch` (`snapshot_period`, `kantor_cabang`)',
                'ALTER TABLE `__TEMP__` ADD KEY `idx_dsbs_period_rank` (`snapshot_period`, `rank_order`)',
                'ALTER TABLE `__TEMP__` ADD KEY `idx_dsbs_period_balance` (`snapshot_period`, `total_balance`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `uniqueid_dsbs`, `snapshot_period`, `kantor_cabang`, `total_balance`, `rank_order`, `created_at`, `updated_at`
)
SELECT
    `uniqueid_dsbs`,
    MAX(`snapshot_period`) as `snapshot_period`,
    MAX(`kantor_cabang`) as `kantor_cabang`,
    MAX(`total_balance`) as `total_balance`,
    MAX(`rank_order`) as `rank_order`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `dashboard_simpanan_branch_snapshots`
GROUP BY `uniqueid_dsbs`
SQL
        );
    }

    private function rebuildRasioCasaSnapshots(): void
    {
        if (!Schema::hasTable('rasio_casa_debitur_snapshots') || $this->tableHasPrimaryKey('rasio_casa_debitur_snapshots', 'uniqueid_rcds')) {
            return;
        }

        $this->rebuildTable(
            'rasio_casa_debitur_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`uniqueid_rcds`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rasio_casa_debitur_snapshots_loan_period_index` (`loan_period`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rasio_casa_debitur_snapshots_casa_period_index` (`casa_period`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rasio_casa_debitur_snapshots_branch_key_index` (`branch_key`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rasio_casa_debitur_snapshots_segment_key_index` (`segment_key`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `uniqueid_rcds`, `loan_period`, `casa_period`, `branch_key`, `branch_label`, `segment_key`,
    `os_amount`, `casa_amount`, `source_row_count`, `created_at`, `updated_at`
)
SELECT
    `uniqueid_rcds`,
    MAX(`loan_period`) as `loan_period`,
    MAX(`casa_period`) as `casa_period`,
    MAX(`branch_key`) as `branch_key`,
    MAX(`branch_label`) as `branch_label`,
    MAX(`segment_key`) as `segment_key`,
    MAX(`os_amount`) as `os_amount`,
    MAX(`casa_amount`) as `casa_amount`,
    MAX(`source_row_count`) as `source_row_count`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `rasio_casa_debitur_snapshots`
GROUP BY `uniqueid_rcds`
SQL
        );
    }

    private function rebuildRekeningDormantSnapshots(): void
    {
        if (!Schema::hasTable('rekening_dormant_snapshots') || $this->tableHasPrimaryKey('rekening_dormant_snapshots', 'uniqueid_rds')) {
            return;
        }

        $this->rebuildTable(
            'rekening_dormant_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`uniqueid_rds`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rekening_dormant_snapshots_posisi_index` (`posisi`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rekening_dormant_snapshots_branch_label_index` (`branch_label`)',
                'ALTER TABLE `__TEMP__` ADD KEY `rekening_dormant_snapshots_raw_branch_index` (`raw_branch`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `uniqueid_rds`, `posisi`, `branch_label`, `raw_branch`, `unit_kerja`, `dormant_count`, `created_at`, `updated_at`
)
SELECT
    `uniqueid_rds`,
    MAX(`posisi`) as `posisi`,
    MAX(`branch_label`) as `branch_label`,
    MAX(`raw_branch`) as `raw_branch`,
    MAX(`unit_kerja`) as `unit_kerja`,
    MAX(`dormant_count`) as `dormant_count`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `rekening_dormant_snapshots`
GROUP BY `uniqueid_rds`
SQL
        );
    }

    private function rebuildPerformanceNewPayrollSnapshots(): void
    {
        if (!Schema::hasTable('performance_new_payroll_snapshots') || $this->tableHasPrimaryKey('performance_new_payroll_snapshots', 'uniqueid_pnps')) {
            return;
        }

        $this->rebuildTable(
            'performance_new_payroll_snapshots',
            [
                'ALTER TABLE `__TEMP__` ADD PRIMARY KEY (`uniqueid_pnps`)',
            ],
            <<<'SQL'
INSERT INTO `__TEMP__` (
    `uniqueid_pnps`, `snapshot_posisi`, `branch`, `rekening_curr`, `rekening_prev`, `rekening_yoy_prev`,
    `saldo_curr`, `saldo_prev`, `saldo_yoy_prev`, `created_at`, `updated_at`
)
SELECT
    `uniqueid_pnps`,
    MAX(`snapshot_posisi`) as `snapshot_posisi`,
    MAX(`branch`) as `branch`,
    MAX(`rekening_curr`) as `rekening_curr`,
    MAX(`rekening_prev`) as `rekening_prev`,
    MAX(`rekening_yoy_prev`) as `rekening_yoy_prev`,
    MAX(`saldo_curr`) as `saldo_curr`,
    MAX(`saldo_prev`) as `saldo_prev`,
    MAX(`saldo_yoy_prev`) as `saldo_yoy_prev`,
    MIN(`created_at`) as `created_at`,
    MAX(`updated_at`) as `updated_at`
FROM `performance_new_payroll_snapshots`
GROUP BY `uniqueid_pnps`
SQL
        );
    }

    private function rebuildTable(string $table, array $alterStatements, string $insertSql): void
    {
        $temporaryTable = $table . '__rebuild';
        $backupTable = $table . '__backup';

        DB::statement("DROP TABLE IF EXISTS `{$temporaryTable}`");
        DB::statement("DROP TABLE IF EXISTS `{$backupTable}`");
        DB::statement("CREATE TABLE `{$temporaryTable}` LIKE `{$table}`");

        foreach ($alterStatements as $statement) {
            DB::statement(str_replace('__TEMP__', $temporaryTable, $statement));
        }

        DB::statement(str_replace('__TEMP__', $temporaryTable, $insertSql));
        DB::statement("RENAME TABLE `{$table}` TO `{$backupTable}`, `{$temporaryTable}` TO `{$table}`");
        DB::statement("DROP TABLE `{$backupTable}`");
    }

    private function tableHasPrimaryKey(string $table, string $column): bool
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'");

        foreach ($rows as $row) {
            if ((string) ($row->Column_name ?? '') === $column) {
                return true;
            }
        }

        return false;
    }
};
