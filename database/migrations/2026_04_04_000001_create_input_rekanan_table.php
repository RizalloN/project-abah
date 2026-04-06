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
        Schema::create('input_rekanan', function (Blueprint $table) {
            $table->id();
            $table->string('perusahaan_anak')->nullable();
            $table->string('rekanan_level_1')->nullable();
            $table->string('rekanan_level_2')->nullable();
            $table->string('status_nasabah')->nullable();
            $table->string('cif')->nullable();
            $table->string('produk_1')->nullable();
            $table->string('produk_2')->nullable();
            $table->string('produk_3')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input_rekanan');
    }
};
