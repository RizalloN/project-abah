<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_almafacts') || !Schema::hasColumn('ssa_almafacts', 'saldo')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `ssa_almafacts` MODIFY `saldo` DECIMAL(30,12) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ssa_almafacts') || !Schema::hasColumn('ssa_almafacts', 'saldo')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `ssa_almafacts` MODIFY `saldo` DECIMAL(24,2) NULL');
        }
    }
};
