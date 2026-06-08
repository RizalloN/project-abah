<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gi405_recovery')) {
            Schema::create('gi405_recovery', function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->nullable();
                $table->string('kode_uker', 20)->nullable();
                $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 24, 2)->default(0);
                $table->string('nama_uker', 180)->nullable();
                $table->timestamps();

                $table->index(['periode', 'kode_uker', 'uniqueid_namareport'], 'idx_gi405_recovery_delete_scope');
            });
        }

        DB::table('nama_report')->updateOrInsert(
            ['id_report' => 19],
            [
                'nama_report' => 'GI405 Recovery',
                'table_name' => 'gi405_recovery',
                'active' => 1,
                'import_controller' => 'Gi405RecDhImportExcelController',
                'requires_manual_periode' => 0,
            ]
        );
    }

    public function down(): void
    {
        DB::table('nama_report')->updateOrInsert(
            ['id_report' => 19],
            [
                'nama_report' => 'GI405 Single Row',
                'table_name' => 'gi405_singlerow',
                'active' => 1,
                'import_controller' => 'Gi405RecDhImportExcelController',
                'requires_manual_periode' => 0,
            ]
        );

        Schema::dropIfExists('gi405_recovery');
    }
};
