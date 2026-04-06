<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['nama_report' => 'Report Nominatif Rekening Pinjaman PH'],
            [
                'table_name' => 'lw325_ph',
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('nama_report', 'Report Nominatif Rekening Pinjaman PH')
            ->where('table_name', 'lw325_ph')
            ->delete();
    }
};
