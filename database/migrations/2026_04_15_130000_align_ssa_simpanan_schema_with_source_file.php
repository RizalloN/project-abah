<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_simpanan')) {
            return;
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM `ssa_simpanan`'))
            ->pluck('Field')
            ->map(fn ($column) => (string) $column)
            ->all();

        $columnsToDrop = array_values(array_filter([
            in_array('tgl', $columns, true) ? '`tgl`' : null,
            in_array('bulan', $columns, true) ? '`bulan`' : null,
            in_array('tahun', $columns, true) ? '`tahun`' : null,
            in_array('bulan_tahun', $columns, true) ? '`bulan_tahun`' : null,
        ]));

        if ($columnsToDrop !== []) {
            DB::statement('ALTER TABLE `ssa_simpanan` DROP COLUMN ' . implode(', DROP COLUMN ', $columnsToDrop));
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ssa_simpanan')) {
            return;
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM `ssa_simpanan`'))
            ->pluck('Field')
            ->map(fn ($column) => (string) $column)
            ->all();

        $clauses = [];

        if (!in_array('tgl', $columns, true)) {
            $clauses[] = 'ADD COLUMN `tgl` TINYINT UNSIGNED NULL AFTER `saldo`';
        }

        if (!in_array('bulan', $columns, true)) {
            $clauses[] = 'ADD COLUMN `bulan` VARCHAR(20) NULL AFTER `tgl`';
        }

        if (!in_array('tahun', $columns, true)) {
            $clauses[] = 'ADD COLUMN `tahun` SMALLINT UNSIGNED NULL AFTER `bulan`';
        }

        if (!in_array('bulan_tahun', $columns, true)) {
            $clauses[] = 'ADD COLUMN `bulan_tahun` VARCHAR(30) NULL AFTER `tahun`';
        }

        if ($clauses !== []) {
            DB::statement('ALTER TABLE `ssa_simpanan` ' . implode(', ', $clauses));
        }
    }
};
