<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rka') || !Schema::hasColumn('rka', 'kanca') || !Schema::hasColumn('rka', 'desc_kanwil')) {
            return;
        }

        DB::statement("ALTER TABLE `rka` CHANGE COLUMN `kanca` `kanca` VARCHAR(100) NULL AFTER `desc_kanwil`");
    }

    public function down(): void
    {
        if (!Schema::hasTable('rka') || !Schema::hasColumn('rka', 'kanca') || !Schema::hasColumn('rka', 'uniqueid_namareport')) {
            return;
        }

        DB::statement("ALTER TABLE `rka` CHANGE COLUMN `kanca` `kanca` VARCHAR(100) NULL AFTER `uniqueid_namareport`");
    }
};
