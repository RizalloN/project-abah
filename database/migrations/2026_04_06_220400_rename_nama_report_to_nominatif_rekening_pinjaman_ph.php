<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nama_report')
            ->where('nama_report', 'Report Pinjaman - Nomintaif Per Rekening')
            ->where('table_name', 'lw325_ph')
            ->update([
                'nama_report' => 'Report Nominatif Rekening Pinjaman PH',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('nama_report', 'Report Nominatif Rekening Pinjaman PH')
            ->where('table_name', 'lw325_ph')
            ->update([
                'nama_report' => 'Report Pinjaman - Nomintaif Per Rekening',
                'updated_at' => now(),
            ]);
    }
};
