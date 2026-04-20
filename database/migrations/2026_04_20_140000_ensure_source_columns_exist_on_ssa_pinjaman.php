<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ssa_pinjaman')) {
            return;
        }

        $hasSegmen = Schema::hasColumn('ssa_pinjaman', 'segmen');
        $hasSegmenLama = Schema::hasColumn('ssa_pinjaman', 'segmen_lama');
        $hasSegmen2025 = Schema::hasColumn('ssa_pinjaman', 'segmen_2025');
        $hasFlagRestruk = Schema::hasColumn('ssa_pinjaman', 'flag_restruk');
        $hasJumlahDebiturAktif = Schema::hasColumn('ssa_pinjaman', 'jumlah_debitur_aktif');
        $hasJumlahRekeningAktif = Schema::hasColumn('ssa_pinjaman', 'jumlah_rekening_aktif');

        if (
            $hasSegmen
            && $hasSegmenLama
            && $hasSegmen2025
            && $hasFlagRestruk
            && $hasJumlahDebiturAktif
            && $hasJumlahRekeningAktif
        ) {
            return;
        }

        Schema::table('ssa_pinjaman', function (Blueprint $table) use (
            $hasSegmen,
            $hasSegmenLama,
            $hasSegmen2025,
            $hasFlagRestruk,
            $hasJumlahDebiturAktif,
            $hasJumlahRekeningAktif
        ) {
            if (!$hasSegmen) {
                $table->string('segmen', 100)->nullable()->after('produk_dashboard');
            }

            if (!$hasSegmenLama) {
                $table->string('segmen_lama', 100)->nullable()->after($hasSegmen ? 'segmen' : 'produk_dashboard');
            }

            if (!$hasSegmen2025) {
                $table->string('segmen_2025', 100)->nullable()->after('segmen_lama');
            }

            if (!$hasFlagRestruk) {
                $table->string('flag_restruk', 100)->nullable()->after('kolektabilitas_one_obligor');
            }

            if (!$hasJumlahDebiturAktif) {
                $table->unsignedInteger('jumlah_debitur_aktif')->nullable()->after('baki_debet');
            }

            if (!$hasJumlahRekeningAktif) {
                $table->unsignedInteger('jumlah_rekening_aktif')->nullable()->after('jumlah_debitur_aktif');
            }
        });
    }

    public function down(): void
    {
        // No-op. These columns are now part of the expected SSA Pinjaman schema.
    }
};
