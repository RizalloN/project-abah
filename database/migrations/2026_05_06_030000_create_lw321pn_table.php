<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPORT_ID = 28;
    private const TABLE = 'lw321pn';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->nullable();
                $table->string('kode_kanwil', 20)->nullable();
                $table->string('kanwil', 150)->nullable();
                $table->string('kode_kanca', 20)->nullable();
                $table->string('kanca', 150)->nullable();
                $table->string('kode_uker', 20)->nullable();
                $table->string('uker', 150)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('ln_type', 20)->nullable();
                $table->string('no_rekening', 50)->nullable();
                $table->string('nama_debitur', 255)->nullable();
                $table->decimal('plafon', 22, 2)->nullable();
                $table->date('next_pmt_date')->nullable();
                $table->date('next_int_pmt_date')->nullable();
                $table->decimal('rate', 10, 6)->nullable();
                $table->date('tgl_menunggak')->nullable();
                $table->date('tgl_realisasi')->nullable();
                $table->date('tgl_jatuh_tempo')->nullable();
                $table->string('jangka_waktu', 30)->nullable();
                $table->string('flag_restruk', 10)->nullable();
                $table->string('cifno', 50)->nullable();
                $table->decimal('kolektibilitas_lancar', 22, 2)->nullable();
                $table->decimal('kolektibilitas_dpk', 22, 2)->nullable();
                $table->decimal('kolektibilitas_kurang_lancar', 22, 2)->nullable();
                $table->decimal('kolektibilitas_diragukan', 22, 2)->nullable();
                $table->decimal('kolektibilitas_macet', 22, 2)->nullable();
                $table->decimal('tunggakan_pokok', 22, 2)->nullable();
                $table->decimal('tunggakan_bunga', 22, 2)->nullable();
                $table->decimal('tunggakan_pinalti', 22, 2)->nullable();
                $table->unsignedInteger('freq_payment')->nullable();
                $table->unsignedInteger('freq_int_payment')->nullable();
                $table->string('code', 50)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('segmen_lv1', 50)->nullable();
                $table->string('desc_segmen_lv1', 150)->nullable();
                $table->string('kol_adk', 20)->nullable();
                $table->string('pn_pengelola_singlepn', 150)->nullable();
                $table->string('pn_pengelola_1', 150)->nullable();
                $table->string('pn_pemrakarsa', 150)->nullable();
                $table->string('pn_referral', 150)->nullable();
                $table->string('pn_restruk', 150)->nullable();
                $table->string('pn_pengelola_2', 150)->nullable();
                $table->string('pn_pemutus', 150)->nullable();
                $table->string('pn_crm', 150)->nullable();
                $table->string('pn_rm_referral_naik_segmentasi', 150)->nullable();
                $table->string('pn_rm_crr', 150)->nullable();
                $table->decimal('plafon_dalam_idr', 22, 2)->nullable();
                $table->decimal('balance_dalam_idr', 22, 2)->nullable();
                $table->timestamps();

                $table->index(['periode', 'kode_kanca', 'kode_uker'], 'idx_lw321pn_period_kanca_uker');
                $table->index(['periode', 'no_rekening'], 'idx_lw321pn_period_rekening');
            });
        }

        if (Schema::hasTable('nama_report')) {
            $now = now();
            DB::table('nama_report')->updateOrInsert(
                ['id_report' => self::REPORT_ID],
                [
                    'nama_report' => 'LW321PN - Kolektibilitas dan Tunggakan Per AO',
                    'table_name' => self::TABLE,
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

        Schema::dropIfExists(self::TABLE);
    }
};
