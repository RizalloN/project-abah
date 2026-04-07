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

        $indexes = collect(DB::select('SHOW INDEX FROM simpanan_multipn'))
            ->pluck('Key_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!in_array('simpanan_multipn_dormant_lookup_idx', $indexes, true)) {
            DB::statement('CREATE INDEX simpanan_multipn_dormant_lookup_idx ON simpanan_multipn (posisi, status, kantor_cabang, unit_kerja)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('simpanan_multipn')) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM simpanan_multipn'))
            ->pluck('Key_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (in_array('simpanan_multipn_dormant_lookup_idx', $indexes, true)) {
            DB::statement('DROP INDEX simpanan_multipn_dormant_lookup_idx ON simpanan_multipn');
        }
    }
};
