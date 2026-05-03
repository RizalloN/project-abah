<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPORT_ID = 26;

    public function up(): void
    {
        if (!Schema::hasTable('dly_kap_resegmentasi')) {
            Schema::create('dly_kap_resegmentasi', function (Blueprint $table) {
                $table->string('uniqueid_dly_kap', 64)->primary();
                $table->date('periode')->nullable();
                $table->string('kanwil', 20)->nullable();
                $table->string('kode_cabang', 20)->nullable();
                $table->string('kode_unit', 20)->nullable();
                $table->string('source_section', 30)->nullable();
                $table->unsignedInteger('source_row_number')->nullable();
                $table->string('segmen', 30)->nullable();
                $table->string('keterangan', 255)->nullable();
                $table->decimal('l_rp', 22, 2)->nullable();
                $table->unsignedInteger('l_deb')->nullable();
                $table->decimal('dpk_rp', 22, 2)->nullable();
                $table->unsignedInteger('dpk_deb')->nullable();
                $table->decimal('kl_rp', 22, 2)->nullable();
                $table->unsignedInteger('kl_deb')->nullable();
                $table->decimal('d_rp', 22, 2)->nullable();
                $table->unsignedInteger('d_deb')->nullable();
                $table->decimal('m_rp', 22, 2)->nullable();
                $table->unsignedInteger('m_deb')->nullable();
                $table->decimal('npl_rp', 22, 2)->nullable();
                $table->unsignedInteger('npl_deb')->nullable();
                $table->decimal('tl_rp', 22, 2)->nullable();
                $table->unsignedInteger('tl_deb')->nullable();
                $table->timestamps();

                $table->index(['periode', 'kode_cabang', 'kode_unit'], 'idx_dlykap_period_cab_unit');
                $table->index(['segmen'], 'idx_dlykap_segmen');
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => self::REPORT_ID],
                [
                    'nama_report' => 'DLY KAP RESEGMENTASI',
                    'table_name' => 'dly_kap_resegmentasi',
                    'active' => 1,
                    'import_controller' => 'ImportDlyKapResegmentasiCommand',
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

        Schema::dropIfExists('dly_kap_resegmentasi');
    }
};
