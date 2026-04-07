<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['table_name' => 'bod_boc'],
            [
                'nama_report' => 'Nasabah Prioritas BOD/BOC',
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('nama_report')
            ->where('table_name', 'bod_boc')
            ->where('nama_report', 'Nasabah Prioritas BOD/BOC')
            ->delete();
    }
};
