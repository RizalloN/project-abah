<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('nama_report')
            ->where('table_name', 'jumlah_merchant_qris_detail')
            ->update([
                'nama_report' => 'Jumlah Merchant Qris Detail',
                'table_name' => 'jumlah_merchant_qris_detail',
                'active' => 1,
                'import_controller' => 'ImportFileController',
                'requires_manual_periode' => 0,
                'updated_at' => $now,
            ]);

        $exists = DB::table('nama_report')
            ->where('table_name', 'jumlah_merchant_qris_detail')
            ->exists();

        if (!$exists) {
            $nextId = (int) DB::table('nama_report')->max('id_report') + 1;

            DB::table('nama_report')->insert([
                'id_report' => $nextId,
                'nama_report' => 'Jumlah Merchant Qris Detail',
                'table_name' => 'jumlah_merchant_qris_detail',
                'active' => 1,
                'import_controller' => 'ImportFileController',
                'requires_manual_periode' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('table_name', 'jumlah_merchant_qris_detail')
            ->update([
                'nama_report' => 'Jumlah Merchat Qris Detail',
            ]);
    }
};
