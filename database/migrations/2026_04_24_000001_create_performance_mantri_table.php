<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('performance_mantri')) {
            return;
        }

        Schema::create('performance_mantri', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('snapshot_period')->nullable();
            $table->string('pn', 50)->nullable()->index();
            $table->string('nama', 255)->nullable();
            $table->string('bc', 50)->nullable();
            $table->string('unit', 255)->nullable();
            $table->string('cabang', 150)->nullable()->index();
            $table->string('ket', 100)->nullable();
            $table->date('tmt_jabatan')->nullable()->index();
            $table->string('ket_kehadiran_mantri', 100)->nullable();
            $table->date('tanggal_mulai_bl')->nullable()->index();
            $table->unsignedInteger('disbursement_deb')->nullable();
            $table->decimal('disbursement_rp_juta', 20, 2)->nullable();
            $table->string('ket_realisasi', 100)->nullable();
            $table->string('kategori_realisasi', 50)->nullable();
            $table->decimal('tiket_size', 20, 16)->nullable();
            $table->decimal('ratas_hk', 20, 16)->nullable();
            $table->string('keterangan', 100)->nullable();
            $table->timestamps();

            $table->index(['snapshot_period', 'cabang', 'uniqueid_namareport'], 'idx_pm_delete_scope');
        });

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 23],
                [
                    'nama_report' => 'Kinerja Mantri',
                    'table_name' => 'performance_mantri',
                    'active' => 1,
                    'import_controller' => 'ImportPerformanceMantriController',
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
            DB::table('nama_report')->where('id_report', 23)->delete();
        }

        Schema::dropIfExists('performance_mantri');
    }
};
