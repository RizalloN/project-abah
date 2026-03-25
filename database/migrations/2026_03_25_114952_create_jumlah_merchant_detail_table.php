<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jumlah_merchant_detail', function (Blueprint $table) {

            $table->id(); // PK utama

            // 🔥 UNIQUE IMPORT ID
            $table->string('uniqueid_namareport')->unique();

            // 🔥 DATA CSV
            $table->string('TAHUN')->nullable();
            $table->string('PERIODE')->nullable();
            $table->dateTime('POSISI')->nullable();
            $table->string('KODE_KANWIL')->nullable();
            $table->string('NAMA_KANWIL')->nullable();
            $table->string('KODE_KANCA')->nullable();
            $table->string('NAMA_KANCA')->nullable();
            $table->string('KODE_UKER')->nullable();
            $table->string('NAMA_UKER')->nullable();
            $table->string('TID')->nullable();
            $table->string('MID')->nullable();
            $table->string('NAMA_MERCHANT')->nullable();
            $table->string('JENIS')->nullable();
            $table->string('KANWIL_PEMRAKARSA')->nullable();
            $table->string('KANWIL_NAMA_PEMRAKARSA')->nullable();
            $table->string('UKER_PEMRAKARSA')->nullable();
            $table->string('UKER_NAMA_PEMRAKARSA')->nullable();
            $table->string('KANWIL_IMPLEMENTOR')->nullable();
            $table->string('KANWIL_NAMA_IMPLEMENTOR')->nullable();
            $table->string('UKER_IMPLEMENTOR')->nullable();
            $table->string('UKER_NAMA_IMPLEMENTOR')->nullable();
            $table->string('PN_USER_PEMRAKARSA')->nullable();
            $table->string('NAMA_USER_PEMRAKARSA')->nullable();

            $table->dateTime('LAST_AVAILABLE')->nullable();
            $table->string('STATUS_AVAILABLE')->nullable();
            $table->dateTime('LAST_UTILITY')->nullable();
            $table->string('STATUS_UTILITY')->nullable();
            $table->dateTime('LAST_TRANSACTIONAL')->nullable();
            $table->string('STATUS_TRANSACTIONAL')->nullable();

            $table->text('ALAMAT_MERCHANT')->nullable();
            $table->string('KELURAHAN')->nullable();
            $table->string('KECAMATAN')->nullable();
            $table->string('KABUPATEN')->nullable();
            $table->string('PROVINSI')->nullable();

            $table->string('AKTIF_OR_STAGING')->nullable();

            $table->bigInteger('JML_TRANSAKSI')->nullable();
            $table->bigInteger('SALES_VOLUME')->nullable();
            $table->bigInteger('AKUMULASI_TRANSAKSI')->nullable();
            $table->bigInteger('AKUMULASI_SALES_VOLUME')->nullable();

            $table->bigInteger('KARTU_JML_TRANSAKSI_ON_US')->nullable();
            $table->bigInteger('KARTU_JML_TRANSAKSI_OFF_US')->nullable();
            $table->bigInteger('JML_TRANSAKSI_LAINNYA')->nullable();

            $table->bigInteger('KARTU_SALES_VOLUME_ON_US')->nullable();
            $table->bigInteger('KARTU_SALES_VOLUME_OFF_US')->nullable();
            $table->bigInteger('SALES_VOLUME_LAINNYA')->nullable();

            $table->string('KET_MCC')->nullable();
            $table->string('KODE_MCC')->nullable();
            $table->string('NOREK')->nullable();
            $table->string('CIFNO')->nullable();

            $table->bigInteger('SALDO_POSISI')->nullable();
            $table->bigInteger('RATAS_SALDO')->nullable();
            $table->bigInteger('SALDO_POSISI_BY_CIF')->nullable();
            $table->bigInteger('RATAS_SALDO_BY_CIF')->nullable();

            $table->dateTime('TGL_APPROVAL')->nullable();
            $table->string('NILAI')->nullable();
            $table->string('SOURCE')->nullable();

            $table->bigInteger('SALES_VOLUME_MID')->nullable();

            $table->string('FLAGGING')->nullable();
            $table->string('FLAGGING_BRI_MERCHANT')->nullable();
            $table->string('TIERING_SALES_VOLUME')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jumlah_merchant_detail');
    }
};
