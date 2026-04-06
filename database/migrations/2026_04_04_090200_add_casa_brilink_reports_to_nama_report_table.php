<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['nama_report' => 'CASA BRILINK WEB'],
            [
                'table_name' => 'casa_brilink_web',
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('nama_report')->updateOrInsert(
            ['nama_report' => 'CASA BRILINK EDC'],
            [
                'table_name' => 'casa_brilink_edc',
                'active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->whereIn('nama_report', ['CASA BRILINK WEB', 'CASA BRILINK EDC'])
            ->whereIn('table_name', ['casa_brilink_web', 'casa_brilink_edc'])
            ->delete();
    }
};
