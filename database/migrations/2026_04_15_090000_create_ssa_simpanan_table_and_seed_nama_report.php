<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_simpanan')) {
            Schema::create('ssa_simpanan', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('posisi', 50)->nullable();
                $table->string('nama_cabang', 150)->nullable();
                $table->string('nama_uker', 150)->nullable();
                $table->string('produk', 50)->nullable();
                $table->string('segmentasi', 50)->nullable();
                $table->string('segmen_kategorisasi_bisnis', 100)->nullable();
                $table->decimal('saldo', 20, 2)->nullable();
                $table->unsignedTinyInteger('tgl')->nullable();
                $table->string('bulan', 20)->nullable();
                $table->unsignedSmallInteger('tahun')->nullable();
                $table->string('bulan_tahun', 30)->nullable();
                $table->timestamps();

                $table->index(['posisi', 'nama_cabang'], 'idx_ssa_simpanan_posisi_cabang');
                $table->index(['nama_uker', 'produk'], 'idx_ssa_simpanan_uker_produk');
                $table->index(['bulan_tahun', 'segmentasi'], 'idx_ssa_simpanan_bulan_tahun_segmentasi');
            });
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 17],
                [
                    'nama_report' => 'SSA Simpanan',
                    'table_name' => 'ssa_simpanan',
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
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
                ->where('id_report', 17)
                ->where('table_name', 'ssa_simpanan')
                ->delete();
        }

        Schema::dropIfExists('ssa_simpanan');
    }
};
