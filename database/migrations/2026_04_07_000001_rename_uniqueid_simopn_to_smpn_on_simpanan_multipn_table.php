<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        if (
            Schema::hasColumn('simpanan_multipn', 'uniqueid_SimoPN')
            && !Schema::hasColumn('simpanan_multipn', 'uniqueid_SMPN')
        ) {
            DB::statement('ALTER TABLE simpanan_multipn CHANGE COLUMN uniqueid_SimoPN uniqueid_SMPN VARCHAR(50) NULL');
        }

        $uniqueIndexExists = collect(DB::select("SHOW INDEX FROM simpanan_multipn WHERE Key_name = 'uniq_simpanan_multipn_uniqueid_smpn'"))
            ->isNotEmpty();

        if (Schema::hasColumn('simpanan_multipn', 'uniqueid_SMPN') && !$uniqueIndexExists) {
            DB::statement('ALTER TABLE simpanan_multipn ADD UNIQUE KEY uniq_simpanan_multipn_uniqueid_smpn (uniqueid_SMPN)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        $uniqueIndexExists = collect(DB::select("SHOW INDEX FROM simpanan_multipn WHERE Key_name = 'uniq_simpanan_multipn_uniqueid_smpn'"))
            ->isNotEmpty();

        if ($uniqueIndexExists) {
            DB::statement('ALTER TABLE simpanan_multipn DROP INDEX uniq_simpanan_multipn_uniqueid_smpn');
        }

        if (
            Schema::hasColumn('simpanan_multipn', 'uniqueid_SMPN')
            && !Schema::hasColumn('simpanan_multipn', 'uniqueid_SimoPN')
        ) {
            DB::statement('ALTER TABLE simpanan_multipn CHANGE COLUMN uniqueid_SMPN uniqueid_SimoPN VARCHAR(50) NULL');
        }
    }
};
