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
            ->map(fn ($column) => (string) ($column->Field ?? ''))
            ->all();

        $hasLegacyColumn = in_array('posisi', $columns, true);
        $hasCommaColumn = in_array('Month, Day, Year of Posisi', $columns, true);
        $hasTargetColumn = in_array('Month_Day_Year_of_Posisi', $columns, true);

        if ($hasLegacyColumn && !$hasTargetColumn && !$hasCommaColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `posisi` `Month_Day_Year_of_Posisi` VARCHAR(50) NULL'
            );
        }

        if ($hasCommaColumn && !$hasTargetColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `Month, Day, Year of Posisi` `Month_Day_Year_of_Posisi` VARCHAR(50) NULL'
            );
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns === ['posisi', 'nama_cabang'] || $indexColumns === ['Month, Day, Year of Posisi', 'nama_cabang']) {
            DB::statement('ALTER TABLE `ssa_simpanan` DROP INDEX `idx_ssa_simpanan_posisi_cabang`');
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns !== ['Month_Day_Year_of_Posisi', 'nama_cabang']) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` ADD INDEX `idx_ssa_simpanan_posisi_cabang` (`Month_Day_Year_of_Posisi`, `nama_cabang`)'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ssa_simpanan')) {
            return;
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM `ssa_simpanan`'))
            ->map(fn ($column) => (string) ($column->Field ?? ''))
            ->all();

        $hasLegacyColumn = in_array('posisi', $columns, true);
        $hasCommaColumn = in_array('Month, Day, Year of Posisi', $columns, true);
        $hasTargetColumn = in_array('Month_Day_Year_of_Posisi', $columns, true);

        if (!$hasLegacyColumn && $hasTargetColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `Month_Day_Year_of_Posisi` `posisi` VARCHAR(50) NULL'
            );
        }

        if (!$hasLegacyColumn && $hasCommaColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `Month, Day, Year of Posisi` `posisi` VARCHAR(50) NULL'
            );
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns === ['Month_Day_Year_of_Posisi', 'nama_cabang'] || $indexColumns === ['Month, Day, Year of Posisi', 'nama_cabang']) {
            DB::statement('ALTER TABLE `ssa_simpanan` DROP INDEX `idx_ssa_simpanan_posisi_cabang`');
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns !== ['posisi', 'nama_cabang']) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` ADD INDEX `idx_ssa_simpanan_posisi_cabang` (`posisi`, `nama_cabang`)'
            );
        }
    }
};
