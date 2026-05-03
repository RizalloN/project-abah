<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gi405_rec_dh');

        if (!Schema::hasTable('gi405_singlerow')) {
            Schema::create('gi405_singlerow', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->nullable()->index();
                $table->string('branch', 20)->nullable()->index();
                $table->string('currency', 10)->nullable();
                $table->string('posting_control', 30)->nullable()->index();
                $table->string('account_number', 50)->nullable()->index();
                $table->string('c_c', 20)->nullable();
                $table->string('p_c', 20)->nullable();
                $table->string('f_c', 20)->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('begining_balance', 24, 2)->default(0);
                $table->decimal('equivalents_idr', 24, 2)->default(0);
                $table->decimal('equivalents_usd', 24, 2)->default(0);
                $table->decimal('today_debit', 24, 2)->default(0);
                $table->decimal('today_credit', 24, 2)->default(0);
                $table->decimal('ending_balance', 24, 2)->default(0);
                $table->timestamps();

                $table->index(['periode', 'branch', 'posting_control'], 'idx_gi405_sr_period_branch_posting');
            });
        }

        DB::table('nama_report')
            ->where('id_report', 19)
            ->update([
                'nama_report' => 'GI405 Single Row',
                'table_name' => 'gi405_singlerow',
                'import_controller' => 'Gi405RecDhImportExcelController',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gi405_singlerow');

        if (!Schema::hasTable('gi405_rec_dh')) {
            Schema::create('gi405_rec_dh', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('tanggal')->nullable()->index();
                $table->string('kode', 50)->nullable();
                $table->string('kc_konsol', 150)->nullable();
                $table->string('nama_uker', 180)->nullable()->index();
                $table->string('segmen', 100)->nullable();
                $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 20, 2)->default(0);
                $table->decimal('recovery_non_klaim', 20, 2)->default(0);
                $table->timestamps();
            });
        }

        DB::table('nama_report')
            ->where('id_report', 19)
            ->update([
                'nama_report' => 'GI405 Rec DH',
                'table_name' => 'gi405_rec_dh',
                'import_controller' => 'ImportRecDhController',
            ]);
    }
};
