<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_ph') && !Schema::hasTable('lw325_ph')) {
            Schema::rename('report_ph', 'lw325_ph');
        }

        DB::table('nama_report')
            ->where('nama_report', 'Report Nominatif Rekening Pinjaman PH')
            ->whereIn('table_name', ['report_ph', 'lw325_PH', 'lw321_ph'])
            ->update([
                'table_name' => 'lw325_ph',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('lw325_ph') && !Schema::hasTable('report_ph')) {
            Schema::rename('lw325_ph', 'report_ph');
        }

        DB::table('nama_report')
            ->where('nama_report', 'Report Nominatif Rekening Pinjaman PH')
            ->where('table_name', 'lw325_ph')
            ->update([
                'table_name' => 'report_ph',
                'updated_at' => now(),
            ]);
    }
};
