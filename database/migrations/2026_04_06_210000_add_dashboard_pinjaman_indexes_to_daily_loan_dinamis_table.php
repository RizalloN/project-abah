<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $table->index(['periode', 'nomor_rekening1'], 'idx_dld_periode_rekening');
            $table->index(['periode', 'segmen_dashboard', 'produk_dashboard'], 'idx_dld_periode_segmen_produk');
            $table->index(['periode', 'cabang1', 'unit1'], 'idx_dld_periode_cabang_unit');
        });
    }

    public function down(): void
    {
        Schema::table('daily_loan_dinamis', function (Blueprint $table) {
            $table->dropIndex('idx_dld_periode_rekening');
            $table->dropIndex('idx_dld_periode_segmen_produk');
            $table->dropIndex('idx_dld_periode_cabang_unit');
        });
    }
};
