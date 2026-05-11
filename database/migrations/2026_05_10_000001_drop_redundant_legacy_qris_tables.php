<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->whereIn('table_name', ['merchant_qris', 'merchant_qris_volume'])
                ->update(['active' => 0]);
        }

        Schema::dropIfExists('merchant_qris_volume');
        Schema::dropIfExists('merchant_qris');
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->whereIn('table_name', ['merchant_qris', 'merchant_qris_volume'])
                ->update(['active' => 1]);
        }
    }
};
