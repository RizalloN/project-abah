<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['table_name' => 'input_rekanan'],
            [
                'nama_report' => 'Input Rekanan',
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('table_name', 'input_rekanan')
            ->delete();
    }
};
