<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('loan_type')) {
            Schema::create('loan_type', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->string('loan_type', 100);
                $table->string('loan_type_desc', 255)->nullable();
                $table->string('segmentasi', 150)->nullable();
                $table->string('suku_bunga', 50)->nullable();
                $table->string('pola_pembayaran', 150)->nullable();
                $table->text('keterangan1')->nullable();
                $table->text('keterangan2')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_type');
    }
};
