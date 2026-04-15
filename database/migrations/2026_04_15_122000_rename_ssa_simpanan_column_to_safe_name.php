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

        $hasCommaColumn = in_array('Month, Day, Year of Posisi', $columns, true);
        $hasSafeColumn = in_array('Month_Day_Year_of_Posisi', $columns, true);

        if ($hasCommaColumn && !$hasSafeColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `Month, Day, Year of Posisi` `Month_Day_Year_of_Posisi` VARCHAR(50) NULL'
            );
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns === ['Month, Day, Year of Posisi', 'nama_cabang']) {
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

        $hasCommaColumn = in_array('Month, Day, Year of Posisi', $columns, true);
        $hasSafeColumn = in_array('Month_Day_Year_of_Posisi', $columns, true);

        if (!$hasCommaColumn && $hasSafeColumn) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` CHANGE COLUMN `Month_Day_Year_of_Posisi` `Month, Day, Year of Posisi` VARCHAR(50) NULL'
            );
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns === ['Month_Day_Year_of_Posisi', 'nama_cabang']) {
            DB::statement('ALTER TABLE `ssa_simpanan` DROP INDEX `idx_ssa_simpanan_posisi_cabang`');
        }

        $indexColumns = collect(DB::select("SHOW INDEX FROM `ssa_simpanan` WHERE Key_name = 'idx_ssa_simpanan_posisi_cabang'"))
            ->pluck('Column_name')
            ->filter()
            ->values()
            ->all();

        if ($indexColumns !== ['Month, Day, Year of Posisi', 'nama_cabang']) {
            DB::statement(
                'ALTER TABLE `ssa_simpanan` ADD INDEX `idx_ssa_simpanan_posisi_cabang` (`Month, Day, Year of Posisi`, `nama_cabang`)'
            );
        }
    }
};
