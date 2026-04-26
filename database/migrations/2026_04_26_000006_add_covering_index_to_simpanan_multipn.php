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
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            // Covering index for Rasio CASA aggregation
            // Format: (posisi, CIFNO, jenis_simpanan, saldo_idr)
            // This allows index-only scan for computing CASA balances per CIF
            $table->index(
                ['posisi', 'CIFNO', 'jenis_simpanan', 'saldo_idr'], 
                'idx_smp_posisi_cif_covering'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->dropIndex('idx_smp_posisi_cif_covering');
        });
    }
};
