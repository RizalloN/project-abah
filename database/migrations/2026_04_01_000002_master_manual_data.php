<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Input Rekanan
        Schema::create('input_rekanan', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable()->index();
            $table->string('perusahaan_anak')->nullable();
            $table->string('rekanan_level_1')->nullable();
            $table->string('rekanan_level_2')->nullable();
            $table->string('status_nasabah', 100)->nullable();
            $table->string('cif', 100)->nullable()->index();
            $table->string('produk_1')->nullable();
            $table->string('produk_2')->nullable();
            $table->string('produk_3')->nullable();
            $table->timestamps();

            $table->index(['periode', 'cif'], 'idx_rekanan_periode_cif');
        });

        // 2. Bod Boc
        Schema::create('bod_boc', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->nullable()->index();
            $table->string('instansi')->nullable();
            $table->string('bod_boc')->nullable();
            $table->string('nama_nasabah')->nullable();
            $table->string('ket_nasabah')->nullable();
            $table->string('cif', 100)->nullable()->index();
            $table->string('fasilitas_1')->nullable();
            $table->string('fasilitas_2')->nullable();
            $table->string('fasilitas_3')->nullable();
            $table->timestamps();

            $table->index(['periode', 'cif'], 'idx_bod_boc_periode_cif');
        });

        // 3. Rencana Kerja Anggaran (RKA)
        Schema::create('rka', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->unsignedInteger('tahun')->nullable()->index();
            $table->string('kanca', 150)->nullable()->index();
            $table->string('desc_uker', 180)->nullable()->index();
            $table->string('mata_anggaran', 100)->nullable()->index();
            $table->decimal('jan', 20, 2)->default(0);
            $table->decimal('feb', 20, 2)->default(0);
            $table->decimal('mar', 20, 2)->default(0);
            $table->decimal('apr', 20, 2)->default(0);
            $table->decimal('may', 20, 2)->default(0);
            $table->decimal('jun', 20, 2)->default(0);
            $table->decimal('jul', 20, 2)->default(0);
            $table->decimal('aug', 20, 2)->default(0);
            $table->decimal('sep', 20, 2)->default(0);
            $table->decimal('oct', 20, 2)->default(0);
            $table->decimal('nov', 20, 2)->default(0);
            $table->decimal('dec', 20, 2)->default(0);
            $table->timestamps();

            $table->index(['kanca', 'mata_anggaran'], 'idx_rka_kanca_ma');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rka');
        Schema::dropIfExists('bod_boc');
        Schema::dropIfExists('input_rekanan');
    }
};
