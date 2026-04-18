<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jumlah_merchant_qris_detail')) {
            Schema::create('jumlah_merchant_qris_detail', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('PERIODE', 20)->nullable()->index();
                $table->date('POSISI')->nullable()->index();
                $table->string('REGION', 20)->nullable();
                $table->string('RGDESC', 150)->nullable();
                $table->string('MAINBR', 20)->nullable();
                $table->string('MBDESC', 150)->nullable()->index();
                $table->string('BRANCH', 20)->nullable();
                $table->string('BRDESC', 180)->nullable()->index();
                $table->string('MERCHANT_PAN', 50)->nullable()->index();
                $table->string('STOREID', 50)->nullable()->index();
                $table->string('NAMA_MERCHANT', 255)->nullable();
                $table->string('KRITERIA', 50)->nullable();
                $table->string('JENIS_USAHA', 255)->nullable();
                $table->string('KODE_MCC', 20)->nullable();
                $table->string('MCC', 100)->nullable();
                $table->text('ALAMAT')->nullable();
                $table->string('KODE_POS', 20)->nullable();
                $table->string('KOTA', 150)->nullable();
                $table->string('PROVINSI', 150)->nullable();
                $table->string('NO_REK', 50)->nullable()->index();
                $table->string('CIF', 50)->nullable()->index();
                $table->string('PN', 50)->nullable()->index();
                $table->string('PN_PEMRAKASA', 150)->nullable();
                $table->string('JABATAN', 150)->nullable();
                $table->string('TGL_BALIKAN_PTEN', 20)->nullable();
                $table->string('STATUS', 100)->nullable()->index();
                $table->string('MERCHANT_TYPE', 50)->nullable()->index();
                $table->decimal('AKUMULASI_SV_ONUS', 20, 2)->nullable();
                $table->decimal('AKUMULASI_SV_OFFUS', 20, 2)->nullable();
                $table->decimal('AKUMULASI_SV_LINKAJA', 20, 2)->nullable();
                $table->decimal('AKUMULASI_SV_TOTAL', 20, 2)->nullable();
                $table->decimal('POSISI_SV_TOTAL', 20, 2)->nullable();
                $table->decimal('AKUMULASI_TRX_ONUS', 20, 2)->nullable();
                $table->decimal('AKUMULASI_TRX_OFFUS', 20, 2)->nullable();
                $table->decimal('AKUMULASI_TRX_LINKAJA', 20, 2)->nullable();
                $table->decimal('AKUMULASI_TRX_TOTAL', 20, 2)->nullable();
                $table->decimal('POSISI_TRX_TOTAL', 20, 2)->nullable();
                $table->decimal('SALDO_POSISI', 20, 2)->nullable();
                $table->decimal('RATAS_SALDO', 20, 2)->nullable();
                $table->string('FLAGGING_BRI_MERCHANT', 50)->nullable()->index();
                $table->timestamps();

                $table->index(['POSISI', 'MBDESC', 'uniqueid_namareport'], 'idx_jmqd_delete_scope');
                $table->index(['POSISI', 'MBDESC', 'BRDESC'], 'idx_jmqd_posisi_cab_unit');
            });
        }

        $existing = DB::table('nama_report')
            ->where('table_name', 'jumlah_merchant_qris_detail')
            ->orWhere('nama_report', 'Jumlah Merchat Qris Detail')
            ->exists();

        if (!$existing) {
            $nextId = (int) DB::table('nama_report')->max('id_report') + 1;

            DB::table('nama_report')->insert([
                'id_report' => $nextId,
                'nama_report' => 'Jumlah Merchat Qris Detail',
                'table_name' => 'jumlah_merchant_qris_detail',
                'active' => 1,
                'import_controller' => 'ImportFileController',
                'requires_manual_periode' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('nama_report')
            ->where('table_name', 'jumlah_merchant_qris_detail')
            ->delete();

        Schema::dropIfExists('jumlah_merchant_qris_detail');
    }
};
