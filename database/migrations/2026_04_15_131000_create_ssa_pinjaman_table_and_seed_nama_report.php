<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_pinjaman')) {
            Schema::create('ssa_pinjaman', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('month_day_year_of_periode', 50)->nullable();
                $table->string('nama_cabang', 150)->nullable();
                $table->string('nama_uker', 150)->nullable();
                $table->string('produk', 100)->nullable();
                $table->string('produk_dashboard', 100)->nullable();
                $table->string('segmen', 50)->nullable();
                $table->string('segmen_lama', 50)->nullable();
                $table->string('segmen_2025', 50)->nullable();
                $table->string('segmen_dashboard', 50)->nullable();
                $table->string('kolektabilitas_one_obligor', 20)->nullable();
                $table->string('flag_restruk', 10)->nullable();
                $table->decimal('baki_debet', 20, 2)->nullable();
                $table->unsignedInteger('jumlah_debitur_aktif')->nullable();
                $table->unsignedInteger('jumlah_rekening_aktif')->nullable();
                $table->string('keterangan_uker', 50)->nullable();
                $table->string('kualitas', 20)->nullable();
                $table->timestamps();

                $table->index(['month_day_year_of_periode', 'nama_cabang'], 'idx_ssa_pinjaman_periode_cabang');
                $table->index(['nama_uker', 'produk_dashboard'], 'idx_ssa_pinjaman_uker_produk_dash');
                $table->index(['segmen_dashboard', 'kualitas'], 'idx_ssa_pinjaman_segmen_kualitas');
            });
        }

        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 18],
                [
                    'nama_report' => 'SSA Pinjaman',
                    'table_name' => 'ssa_pinjaman',
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
                ->where('id_report', 18)
                ->where('table_name', 'ssa_pinjaman')
                ->delete();
        }

        Schema::dropIfExists('ssa_pinjaman');
    }
};
