<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['nama_report' => 'PERFORMANCE PIS PER PRODUK'],
            [
                'table_name' => 'performance_pis_per_produk',
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('nama_report', 'PERFORMANCE PIS PER PRODUK')
            ->where('table_name', 'performance_pis_per_produk')
            ->delete();
    }
};
