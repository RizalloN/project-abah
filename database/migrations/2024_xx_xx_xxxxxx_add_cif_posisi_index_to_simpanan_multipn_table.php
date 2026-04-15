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
            // Index komposit ini akan mempercepat Subquery WHERE IN (CIFNO) & MAX(posisi) hingga >90%
            $table->index(['CIFNO', 'posisi'], 'idx_smp_cifno_posisi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simpanan_multipn', function (Blueprint $table) {
            $table->dropIndex('idx_smp_cifno_posisi');
        });
    }
};