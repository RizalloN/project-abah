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
        Schema::create('bod_boc', function (Blueprint $table) {
            $table->id();
            $table->string('instansi')->nullable();
            $table->string('bod_boc')->nullable();
            $table->string('nama_nasabah')->nullable();
            $table->string('ket_nasabah')->nullable();
            $table->string('cif')->nullable();
            $table->string('fasilitas_1')->nullable();
            $table->string('fasilitas_2')->nullable();
            $table->string('fasilitas_3')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bod_boc');
    }
};
