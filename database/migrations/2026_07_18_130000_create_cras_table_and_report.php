<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'cras';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table): void {
                $table->char('cras_uuid', 32)->primary();
                $table->date('cras_periode');
                $table->text('month_day_year_of_posisi');
                $table->string('ket_kanca', 255);
                $table->text('br_number');
                $table->text('ket_unit_kerja');
                $table->text('status_rekening');
                $table->text('segmen');
                $table->text('produk');
                $table->text('loan_type');
                $table->text('sektor_ekonomi');
                $table->text('sub_sektor_ekonomi');
                $table->text('tahun_realisasi');
                $table->text('ket_produk_tiering');
                $table->text('kualitas_bulan_lalu');
                $table->text('kualitas');
                $table->text('flag_movement_kualitas');
                $table->text('detail_movement_kualitas');
                $table->text('kol_adk');
                $table->text('flag_restruk');
                $table->text('accint');
                $table->text('baki_debet');
                $table->text('biaya_ckpn');
                $table->text('ckpn_mo');
                $table->text('denda');
                $table->text('jumlah_debitur');
                $table->text('jumlah_rekening');
                $table->text('nilai_tercatat');
                $table->text('plafond');
                $table->text('realisasi_ph');
                $table->text('recovery_total');
                $table->text('saldo_ph');
                $table->text('tunggakan_bunga');
                $table->text('tunggakan_kecil');
                $table->text('tunggakan_pokok');
                $table->timestamps();

                // Covers period listing, period+branch overlap checks, and scoped batch deletion.
                $table->index(
                    ['cras_periode', 'ket_kanca', 'cras_uuid'],
                    'idx_cras_period_branch_uuid'
                );
            });
        }

        if (Schema::hasTable('nama_report')) {
            $existingId = DB::table('nama_report')
                ->where('table_name', self::TABLE)
                ->value('id_report');
            $reportId = $existingId ?: ((int) DB::table('nama_report')->max('id_report')) + 1;
            $now = now();

            DB::table('nama_report')->updateOrInsert(
                ['table_name' => self::TABLE],
                [
                    'id_report' => $reportId,
                    'nama_report' => 'SSA CRAS',
                    'active' => 1,
                    'import_controller' => 'ImportCrasController',
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
            DB::table('nama_report')->where('table_name', self::TABLE)->delete();
        }

        Schema::dropIfExists(self::TABLE);
    }
};
