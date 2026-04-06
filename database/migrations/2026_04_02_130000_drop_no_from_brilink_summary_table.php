<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')) {
            return;
        }

        if (!Schema::hasColumn('brilink_web_laporan_summary_transaksi_brilink_web', 'no')) {
            return;
        }

        try {
            Schema::table('brilink_web_laporan_summary_transaksi_brilink_web', function (Blueprint $table) {
                $table->dropColumn('no');
            });
        } catch (\Throwable $e) {
            DB::statement('ALTER TABLE brilink_web_laporan_summary_transaksi_brilink_web DROP COLUMN `no`');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')) {
            return;
        }

        if (Schema::hasColumn('brilink_web_laporan_summary_transaksi_brilink_web', 'no')) {
            return;
        }

        Schema::table('brilink_web_laporan_summary_transaksi_brilink_web', function (Blueprint $table) {
            $table->unsignedInteger('no')->nullable()->after('periode');
        });
    }
};
