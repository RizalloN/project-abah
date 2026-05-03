<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referensi_uker')) {
            return;
        }

        Schema::create('referensi_uker', function (Blueprint $table) {
            $table->id();
            $table->string('kode_uker', 5)->unique();
            $table->string('nama_uker', 180);
            $table->string('keterangan', 50)->nullable();
            $table->string('kode_cabang', 5)->nullable();
            $table->string('nama_cabang', 180)->nullable();
            $table->string('nama_uker_sumber', 180)->nullable();
            $table->string('kode_uker_sumber', 20)->nullable();
            $table->string('sheet_name', 100)->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referensi_uker');
    }
};
