<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        DB::table('nama_report')
            ->where('table_name', 'usak_ibbiz_uker')
            ->update([
                'requires_manual_periode' => 1,
                'manual_periode_type' => 'date',
                'manual_periode_label' => 'Tanggal Periode',
                'manual_periode_help' => 'Wajib isi tanggal periode manual (YYYY-MM-DD) untuk IB Biz User Aktif.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('nama_report')) {
            return;
        }

        DB::table('nama_report')
            ->where('table_name', 'usak_ibbiz_uker')
            ->update([
                'requires_manual_periode' => 0,
                'manual_periode_type' => null,
                'manual_periode_label' => null,
                'manual_periode_help' => null,
                'updated_at' => now(),
            ]);
    }
};
