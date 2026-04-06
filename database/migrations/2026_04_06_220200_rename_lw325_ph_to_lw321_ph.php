<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lw321_ph') && !Schema::hasTable('lw325_ph')) {
            Schema::rename('lw321_ph', 'lw325_ph');
        }

        DB::table('nama_report')
            ->whereIn('table_name', ['lw325_PH', 'lw321_ph'])
            ->update([
                'table_name' => 'lw325_ph',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('lw325_ph') && !Schema::hasTable('lw321_ph')) {
            Schema::rename('lw325_ph', 'lw321_ph');
        }

        DB::table('nama_report')
            ->where('table_name', 'lw325_ph')
            ->where('nama_report', 'Report Pinjaman - Nomintaif Per Rekening')
            ->update([
                'table_name' => 'lw321_ph',
                'updated_at' => now(),
            ]);
    }
};
