<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->forceDrop('ssa_simpanan', [
            'idx_ssa_simpanan_bulan_tahun_segmentasi',
        ], [
            'tgl',
            'bulan',
            'tahun',
            'bulan_tahun',
        ]);

        $this->forceDrop('ssa_pinjaman', [
            'idx_ssa_pinjaman_bulan_segmen',
        ], [
            'tgl',
            'bulan',
            'tahun',
            'bulan_tahun',
        ]);
    }

    public function down(): void
    {
        // No-op: previous migrations already define these helper columns for rollback scenarios.
    }

    private function forceDrop(string $table, array $indexes, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($indexes as $indexName) {
            $exists = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
            }
        }

        foreach ($columns as $column) {
            $quotedColumn = str_replace("'", "''", $column);
            $exists = DB::select("SHOW COLUMNS FROM `{$table}` LIKE '{$quotedColumn}'");
            if (!empty($exists)) {
                DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
            }
        }
    }
};
