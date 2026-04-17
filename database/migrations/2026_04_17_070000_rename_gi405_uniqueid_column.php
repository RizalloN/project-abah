<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gi405_rec_dh')) {
            return;
        }

        if (Schema::hasColumn('gi405_rec_dh', 'uniqueid_405RDH') && !Schema::hasColumn('gi405_rec_dh', 'uniqueid_namareport')) {
            DB::statement('ALTER TABLE `gi405_rec_dh` CHANGE `uniqueid_405RDH` `uniqueid_namareport` VARCHAR(255) NOT NULL');
        }

        if (!Schema::hasColumn('gi405_rec_dh', 'uniqueid_namareport')) {
            Schema::table('gi405_rec_dh', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->nullable()->first();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('gi405_rec_dh')) {
            return;
        }

        if (Schema::hasColumn('gi405_rec_dh', 'uniqueid_namareport') && !Schema::hasColumn('gi405_rec_dh', 'uniqueid_405RDH')) {
            DB::statement('ALTER TABLE `gi405_rec_dh` CHANGE `uniqueid_namareport` `uniqueid_405RDH` VARCHAR(255) NOT NULL');
        }
    }
};
