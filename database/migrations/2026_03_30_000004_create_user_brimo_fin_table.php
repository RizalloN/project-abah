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
        if (Schema::hasTable('user_brimo_fin')) {
            return;
        }

        Schema::create('user_brimo_fin', function (Blueprint $table) {
            $table->id();

            // 🔥 UNIQUE IMPORT ID
            $table->string('uniqueid_namareport')->unique();

            // 🔥 DATA CSV (lowercase sesuai ImportFileBrimoController)
            // Struktur sama dengan user_brimo_rpt_v2,
            // perbedaan hanya pada id dan suffix uniqueid_namareport (_UBFin)
            $table->string('tahun')->nullable();
            $table->string('periode')->nullable();
            $table->date('posisi')->nullable();
            $table->string('region')->nullable();
            $table->string('rgdesc')->nullable();
            $table->string('mainbr')->nullable();
            $table->string('mbdesc')->nullable();
            $table->string('branch')->nullable();
            $table->string('brdesc')->nullable();
            $table->string('kategori')->nullable();
            $table->string('jenis')->nullable();
            $table->decimal('jumlah', 20, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_brimo_fin');
    }
};
