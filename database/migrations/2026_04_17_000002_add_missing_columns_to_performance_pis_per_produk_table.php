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
        Schema::table('performance_pis_per_produk', function (Blueprint $table) {
            $table->string('pn_rm_dana_brinets', 50)->nullable()->after('tanggal_pembuatan_rekening');
            $table->string('pn_rm_dana_pis2', 50)->nullable()->after('pn_rm_dana_brinets');
            $table->string('nomor_hp', 50)->nullable()->after('pn_rm_dana_pis2');
            $table->string('email', 150)->nullable()->after('nomor_hp');
            $table->string('flag_briguna', 20)->nullable()->after('email');
            $table->string('flag_cc', 20)->nullable()->after('flag_briguna');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_pis_per_produk', function (Blueprint $table) {
            $table->dropColumn([
                'pn_rm_dana_brinets',
                'pn_rm_dana_pis2',
                'nomor_hp',
                'email',
                'flag_briguna',
                'flag_cc'
            ]);
        });
    }
};
