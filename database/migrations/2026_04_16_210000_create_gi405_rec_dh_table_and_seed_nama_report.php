<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gi405_rec_dh')) {
            Schema::create('gi405_rec_dh', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('kode', 20);
                $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 20, 2)->nullable();
                $table->decimal('recovery_non_klaim', 20, 2)->nullable();
                $table->string('kc_konsol', 150)->nullable();
                $table->string('nama_uker', 150)->nullable();
                $table->string('segmen', 50)->nullable();
                $table->date('tanggal')->nullable();
                $table->timestamps();

                $table->unique(['tanggal', 'kode'], 'uq_gi405_rec_dh_tanggal_kode');
                $table->index(['tanggal', 'kc_konsol'], 'idx_gi405_rec_dh_tanggal_kc');
                $table->index(['kode'], 'idx_gi405_rec_dh_kode');
            });
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 19],
                [
                    'nama_report' => 'GI405 - Rec. DH',
                    'table_name' => 'gi405_rec_dh',
                    'active' => 1,
                    'import_controller' => 'Gi405RecDhImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')
                ->where('id_report', 19)
                ->where('table_name', 'gi405_rec_dh')
                ->delete();
        }

        Schema::dropIfExists('gi405_rec_dh');
    }
};
