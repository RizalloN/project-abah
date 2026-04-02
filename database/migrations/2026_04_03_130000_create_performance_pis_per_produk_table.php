<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_pis_per_produk', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            $table->date('posisi')->nullable()->index();
            $table->unsignedInteger('no')->nullable();
            $table->string('kode_kanwil', 20)->nullable();
            $table->string('kanwil', 150)->nullable();
            $table->string('kode_kanca', 20)->nullable();
            $table->string('kanca', 150)->nullable();
            $table->string('kode_uker', 20)->nullable();
            $table->string('uker', 150)->nullable();
            $table->string('corporate_code', 30)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('jenis_mitra', 100)->nullable();
            $table->string('jenis_perusahaan', 100)->nullable();
            $table->string('tipe_produk', 50)->nullable();
            $table->string('nomor_rekening', 50)->nullable()->index();
            $table->string('nama_rekening', 255)->nullable();
            $table->decimal('saldo_britama_kerjasama', 20, 2)->nullable();
            $table->date('tanggal_pembuatan_rekening')->nullable();
            $table->string('pn_rm_dana_brinets', 50)->nullable();
            $table->string('pn_rm_dana_pis2', 50)->nullable();
            $table->string('nomor_hp', 50)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('flag_briguna', 10)->nullable();
            $table->string('flag_cc', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_pis_per_produk');
    }
};
