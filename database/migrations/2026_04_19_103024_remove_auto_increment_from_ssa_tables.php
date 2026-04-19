<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. ssa_simpanan table: Remove AUTO_INCREMENT from id
        if (Schema::hasTable('ssa_simpanan')) {
            DB::statement('ALTER TABLE ssa_simpanan MODIFY id BIGINT UNSIGNED NOT NULL');
            // Reset auto increment counter just in case
            DB::statement('ALTER TABLE ssa_simpanan AUTO_INCREMENT = 1');
        }

        // 2. ssa_pinjaman table: Remove AUTO_INCREMENT from id
        if (Schema::hasTable('ssa_pinjaman')) {
            DB::statement('ALTER TABLE ssa_pinjaman MODIFY id BIGINT UNSIGNED NOT NULL');
            // Reset auto increment counter just in case
            DB::statement('ALTER TABLE ssa_pinjaman AUTO_INCREMENT = 1');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ssa_simpanan')) {
            DB::statement('ALTER TABLE ssa_simpanan MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }

        if (Schema::hasTable('ssa_pinjaman')) {
            DB::statement('ALTER TABLE ssa_pinjaman MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }
};
