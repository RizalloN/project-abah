<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rka')) {
            return;
        }

        Schema::table('rka', function (Blueprint $table) {
            if (!Schema::hasColumn('rka', 'kanca')) {
                $table->string('kanca', 100)->nullable()->after('uniqueid_namareport');
            }
        });

        Schema::table('rka', function (Blueprint $table) {
            if (Schema::hasColumn('rka', 'no_urut')) {
                $table->dropColumn('no_urut');
            }

            if (Schema::hasColumn('rka', 'prognosa_realisasi')) {
                $table->dropColumn('prognosa_realisasi');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rka')) {
            return;
        }

        Schema::table('rka', function (Blueprint $table) {
            if (!Schema::hasColumn('rka', 'no_urut')) {
                $table->integer('no_urut')->nullable()->after('desc_uker');
            }

            if (!Schema::hasColumn('rka', 'prognosa_realisasi')) {
                $table->decimal('prognosa_realisasi', 20, 2)->nullable()->after('mata_anggaran');
            }

            if (Schema::hasColumn('rka', 'kanca')) {
                $table->dropColumn('kanca');
            }
        });
    }
};
