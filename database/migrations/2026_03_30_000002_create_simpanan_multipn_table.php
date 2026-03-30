<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_SimoPN')->unique();
            $table->date('posisi')->nullable();
            $table->string('regional_office', 150)->nullable();
            $table->string('kantor_cabang', 150)->nullable();
            $table->string('unit_kerja', 150)->nullable();
            $table->string('CIFNO', 50)->nullable();
            $table->string('no_rekening', 100)->nullable();
            $table->string('jenis_simpanan', 100)->nullable();
            $table->decimal('saldo_idr', 20, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simpanan_multipn');
    }
};
