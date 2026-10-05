<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gi405_singlerow')) {
            Schema::create('gi405_singlerow', function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode');
                $table->string('branch', 8);
                $table->string('currency', 4);
                $table->string('posting_control', 16);
                $table->string('account_number', 32);
                $table->string('c_c', 8)->nullable();
                $table->string('p_c', 8)->nullable();
                $table->string('f_c', 8)->nullable();
                $table->string('description', 255);
                $table->decimal('begining_balance', 24, 2);
                $table->decimal('equivalents_idr', 24, 2)->nullable();
                $table->decimal('equivalents_usd', 24, 2)->nullable();
                $table->decimal('today_debit', 24, 2);
                $table->decimal('today_credit', 24, 2);
                $table->decimal('ending_balance', 24, 2);
                $table->timestamps();

                $table->index(
                    ['periode', 'branch', 'posting_control', 'account_number'],
                    'idx_gi405_singlerow_scope'
                );
            });
        }

        if (!DB::table('nama_report')->where('table_name', 'gi405_singlerow')->exists()) {
            DB::table('nama_report')->insert([
                'id_report' => ((int) DB::table('nama_report')->max('id_report')) + 1,
                'nama_report' => 'GI405 Single Row',
                'table_name' => 'gi405_singlerow',
                'active' => 1,
                'import_controller' => 'ImportExcelController',
                'requires_manual_periode' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')->where('table_name', 'gi405_singlerow')->delete();
        Schema::dropIfExists('gi405_singlerow');
    }
};
