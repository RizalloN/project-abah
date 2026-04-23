<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('performance_kurkecil_mikro')) {
            Schema::create('performance_kurkecil_mikro', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('kanca', 150)->nullable()->index();
                $table->string('pn', 50)->nullable()->index();
                $table->string('nama', 255)->nullable();
                $table->string('bc_uker', 150)->nullable();
                $table->string('uker', 255)->nullable();
                $table->date('tanggal_bl')->nullable()->index();
                $table->string('ket', 100)->nullable();
                $table->unsignedInteger('lt_250_juta_deb')->nullable();
                $table->decimal('lt_250_juta_pct', 20, 16)->nullable();
                $table->decimal('lt_250_juta_rp_juta', 20, 2)->nullable();
                $table->unsignedInteger('gt_250_juta_deb')->nullable();
                $table->decimal('gt_250_juta_pct', 20, 16)->nullable();
                $table->decimal('gt_250_juta_rp_juta', 20, 2)->nullable();
                $table->unsignedInteger('total_deb')->nullable();
                $table->decimal('total_rp_juta', 20, 2)->nullable();
                $table->timestamps();

                $table->index(['tanggal_bl', 'kanca', 'uniqueid_namareport'], 'idx_pkm_delete_scope');
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => 22],
                [
                    'nama_report' => 'Kinerja per RM Kur Mikro',
                    'table_name' => 'performance_kurkecil_mikro',
                    'active' => 1,
                    'import_controller' => 'ImportKurMikroController',
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
            DB::table('nama_report')->where('id_report', 22)->delete();
        }

        Schema::dropIfExists('performance_kurkecil_mikro');
    }
};
