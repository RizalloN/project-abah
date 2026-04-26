<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Memperluas index Dormant agar menjadi Covering Index.
     * Dengan menyertakan no_rekening, query COUNT(DISTINCT no_rekening) 
     * bisa diselesaikan 100% di level index (Index-Only Scan).
     */
    public function up(): void
    {
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            // Drop existing index if it exists to avoid redundancy
            // idx_smp_posisi_status_cab_unit: [posisi, status, kantor_cabang, unit_kerja]
            
            // Create new covering index for Dormant report
            $table->index(
                ['posisi', 'status', 'kantor_cabang', 'unit_kerja', 'no_rekening'], 
                'idx_smp_dormant_covering'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->dropIndex('idx_smp_dormant_covering');
        });
    }
};
