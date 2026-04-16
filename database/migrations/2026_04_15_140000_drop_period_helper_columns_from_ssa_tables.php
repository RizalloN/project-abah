<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropColumnsIfPresent('ssa_simpanan', [
            'tgl',
            'bulan',
            'tahun',
            'bulan_tahun',
        ], [
            'idx_ssa_simpanan_bulan_tahun_segmentasi',
        ]);

        $this->dropColumnsIfPresent('ssa_pinjaman', [
            'tgl',
            'bulan',
            'tahun',
            'bulan_tahun',
        ], [
            'idx_ssa_pinjaman_bulan_segmen',
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('ssa_simpanan')) {
            $columns = $this->columnNames('ssa_simpanan');
            if (!in_array('tgl', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_simpanan` ADD COLUMN `tgl` TINYINT UNSIGNED NULL AFTER `saldo`');
            }
            $columns = $this->columnNames('ssa_simpanan');
            if (!in_array('bulan', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_simpanan` ADD COLUMN `bulan` VARCHAR(20) NULL AFTER `tgl`');
            }
            $columns = $this->columnNames('ssa_simpanan');
            if (!in_array('tahun', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_simpanan` ADD COLUMN `tahun` SMALLINT UNSIGNED NULL AFTER `bulan`');
            }
            $columns = $this->columnNames('ssa_simpanan');
            if (!in_array('bulan_tahun', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_simpanan` ADD COLUMN `bulan_tahun` VARCHAR(30) NULL AFTER `tahun`');
            }
            $this->ensureIndex('ssa_simpanan', 'idx_ssa_simpanan_bulan_tahun_segmentasi', '`bulan_tahun`, `segmentasi`');
        }

        if (Schema::hasTable('ssa_pinjaman')) {
            $columns = $this->columnNames('ssa_pinjaman');
            if (!in_array('tgl', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_pinjaman` ADD COLUMN `tgl` TINYINT UNSIGNED NULL AFTER `kualitas`');
            }
            $columns = $this->columnNames('ssa_pinjaman');
            if (!in_array('bulan', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_pinjaman` ADD COLUMN `bulan` VARCHAR(20) NULL AFTER `tgl`');
            }
            $columns = $this->columnNames('ssa_pinjaman');
            if (!in_array('tahun', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_pinjaman` ADD COLUMN `tahun` SMALLINT UNSIGNED NULL AFTER `bulan`');
            }
            $columns = $this->columnNames('ssa_pinjaman');
            if (!in_array('bulan_tahun', $columns, true)) {
                DB::statement('ALTER TABLE `ssa_pinjaman` ADD COLUMN `bulan_tahun` VARCHAR(30) NULL AFTER `tahun`');
            }
            $this->ensureIndex('ssa_pinjaman', 'idx_ssa_pinjaman_bulan_segmen', '`bulan_tahun`, `segmen_dashboard`');
        }
    }

    private function dropColumnsIfPresent(string $table, array $columns, array $indexes): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($indexes as $indexName) {
            $this->dropIndexIfExists($table, $indexName);
        }

        $existing = array_intersect($columns, $this->columnNames($table));
        if ($existing === []) {
            return;
        }

        $dropClauses = implode(', ', array_map(static fn (string $column): string => "DROP COLUMN `{$column}`", $existing));
        DB::statement("ALTER TABLE `{$table}` {$dropClauses}");
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $matches = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if (!empty($matches)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
        }
    }

    private function ensureIndex(string $table, string $indexName, string $columnsSql): void
    {
        $matches = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        if (empty($matches)) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$columnsSql})");
        }
    }

    private function columnNames(string $table): array
    {
        return array_map(
            static fn ($row): string => (string) ($row->Field ?? ''),
            DB::select("SHOW COLUMNS FROM `{$table}`")
        );
    }
};
