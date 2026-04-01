<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for Rasio Casa performance optimization
     * Critical for 2M+ row tables
     */
    public function up(): void
    {
        // Index for daily_loan_dinamis table
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            // Composite index for the main query pattern: periode + branch + segmen_dashboard
            $table->index(['periode', 'branch'], 'idx_loan_periode_branch');
            $table->index(['periode', 'cifno'], 'idx_loan_periode_cif');
            $table->index('segmen_dashboard', 'idx_loan_segmen');
            $table->index('cifno', 'idx_loan_cif');
        });

        // Index for simpanan_multipn table
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            // Composite index for the main query pattern: posisi + cifno + jenis_simpanan
            $table->index(['posisi', 'cifno'], 'idx_simp_posisi_cif');
            $table->index(['posisi', 'jenis_simpanan'], 'idx_simp_posisi_jenis');
            $table->index('cifno', 'idx_simp_cif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $table->dropIndex('idx_loan_periode_branch');
            $table->dropIndex('idx_loan_periode_cif');
            $table->dropIndex('idx_loan_segmen');
            $table->dropIndex('idx_loan_cif');
        });

        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->dropIndex('idx_simp_posisi_cif');
            $table->dropIndex('idx_simp_posisi_jenis');
            $table->dropIndex('idx_simp_cif');
        });
    }
};
