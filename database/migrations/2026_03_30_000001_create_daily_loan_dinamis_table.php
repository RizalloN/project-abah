<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            $table->date('periode')->nullable();
            $table->string('kode_kanwil', 50)->nullable();
            $table->string('kanwil', 100)->nullable();
            $table->string('kode_cabang', 50)->nullable();
            $table->string('cabang', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('ao_name', 150)->nullable();
            $table->string('cifno', 50)->nullable();
            $table->string('nomor_rekening', 100)->nullable();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('produk_dashboard', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');
    }
};
