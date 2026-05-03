<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPORT_ID = 27;

    public function up(): void
    {
        if (!Schema::hasTable('l1133')) {
            Schema::create('l1133', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->nullable();
                $table->string('kode_kanwil', 20)->nullable();
                $table->string('nama_kanwil', 150)->nullable();
                $table->string('kode_kanca', 20)->nullable();
                $table->string('nama_kanca', 150)->nullable();
                $table->string('kode_uker', 20)->nullable();
                $table->string('nama_uker', 150)->nullable();
                $table->string('jenis', 150)->nullable();
                $table->unsignedInteger('jumlah_debitur')->nullable();
                $table->unsignedInteger('jumlah_rekening')->nullable();
                $table->decimal('outstanding', 22, 2)->nullable();
                $table->unsignedInteger('jumlah_debitur_npl')->nullable();
                $table->decimal('npl', 22, 2)->nullable();
                $table->unsignedInteger('jumlah_debitur_dpk')->nullable();
                $table->decimal('dpk', 22, 2)->nullable();
                $table->timestamps();

                $table->index(['periode', 'kode_kanca', 'kode_uker'], 'idx_l1133_period_kanca_uker');
                $table->index(['periode', 'jenis'], 'idx_l1133_period_jenis');
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => self::REPORT_ID],
                [
                    'nama_report' => 'L1133 - Laporan Harian Pinjaman Kanwil',
                    'table_name' => 'l1133',
                    'active' => 1,
                    'import_controller' => 'ImportExcelController',
                    'requires_manual_periode' => 0,
                    'manual_periode_type' => null,
                    'manual_periode_label' => null,
                    'manual_periode_help' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('nama_report')) {
            DB::table('nama_report')->where('id_report', self::REPORT_ID)->delete();
        }

        Schema::dropIfExists('l1133');
    }
};
