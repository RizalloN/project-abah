<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SSA Simpanan
        Schema::create('ssa_simpanan', function (Blueprint $table) {
            $table->id();
            $table->date('Month_Day_Year_of_Posisi')->nullable()->index();
            $table->string('regional_office', 100)->nullable();
            $table->string('id_cabang', 20)->nullable();
            $table->string('nama_cabang', 150)->nullable()->index();
            $table->string('id_uker', 20)->nullable();
            $table->string('nama_uker', 150)->nullable()->index();
            $table->string('cif', 50)->nullable()->index();
            $table->string('no_rekening', 50)->nullable();
            $table->string('segmentasi', 50)->nullable();
            $table->string('produk', 100)->nullable();
            $table->decimal('saldo', 20, 2)->nullable();
            $table->timestamps();

            $table->index(['Month_Day_Year_of_Posisi', 'nama_cabang', 'nama_uker'], 'idx_ssa_simp_period_cab_unit');
        });

        // 2. SSA Pinjaman
        Schema::create('ssa_pinjaman', function (Blueprint $table) {
            $table->id();
            $table->date('month_day_year_of_periode')->nullable()->index();
            $table->string('regional_office', 100)->nullable();
            $table->string('id_cabang', 20)->nullable();
            $table->string('nama_cabang', 150)->nullable()->index();
            $table->string('id_uker', 20)->nullable();
            $table->string('nama_uker', 150)->nullable()->index();
            $table->string('cif', 50)->nullable()->index();
            $table->string('nominatif', 50)->nullable();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('produk_dashboard', 100)->nullable();
            $table->string('produk', 100)->nullable();
            $table->decimal('baki_debet', 20, 2)->nullable();
            $table->unsignedTinyInteger('kolektabilitas_one_obligor')->nullable();
            $table->timestamps();

            $table->index(['month_day_year_of_periode', 'nama_cabang', 'nama_uker'], 'idx_ssa_loan_period_cab_unit');
        });

        // 3. GI405 Rec DH
        Schema::create('gi405_rec_dh', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('tanggal')->nullable()->index();
            $table->string('kode', 50)->nullable();
            $table->string('kc_konsol', 150)->nullable();
            $table->string('nama_uker', 180)->nullable()->index();
            $table->string('segmen', 100)->nullable();
            $table->decimal('pendapatan_koreksi_ppap_dr_angsuran_ph', 20, 2)->default(0);
            $table->decimal('recovery_non_klaim', 20, 2)->default(0);
            $table->timestamps();
        });

        // 4. Cognos Recovery
        Schema::create('cognos_recovery', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('periode')->nullable()->index();
            $table->string('keterangan', 255)->nullable();
            $table->string('cifno', 50)->nullable()->index();
            $table->string('bc', 50)->nullable();
            $table->string('sub_bc', 50)->nullable();
            $table->string('kanwil', 150)->nullable();
            $table->string('ro_fix', 150)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('cabang', 150)->nullable()->index();
            $table->string('unit_kerja', 150)->nullable()->index();
            $table->string('gl_account', 50)->nullable();
            $table->string('produk_code', 50)->nullable();
            $table->string('segmen_fpsl', 50)->nullable();
            $table->string('rekening', 50)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('stsdt_dt_raw', 50)->nullable();
            $table->string('sname', 255)->nullable();
            $table->string('segmen', 100)->nullable();
            $table->string('segmen_bisnis', 100)->nullable();
            $table->string('segmen_bisnis_2025', 100)->nullable();
            $table->string('produk', 150)->nullable();
            $table->string('segmen_kur', 100)->nullable();
            $table->string('segmen_repeat', 100)->nullable();
            $table->string('segmen_2', 100)->nullable();
            $table->string('compliance', 100)->nullable();
            $table->decimal('recovery', 20, 2)->default(0);
            $table->decimal('recovery_klaim', 20, 2)->default(0);
            $table->decimal('recovery_olsib', 20, 2)->default(0);
            $table->decimal('total_recovery', 20, 2)->default(0);
            $table->decimal('recovery_non_klaim', 20, 2)->default(0);
            $table->timestamps();
        });

        // 5. Cognos PH
        Schema::create('cognos_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('periode')->nullable()->index();
            $table->string('kanwil', 150)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('ro_fix', 150)->nullable();
            $table->string('bc', 50)->nullable();
            $table->string('sub_bc', 50)->nullable();
            $table->string('kanca', 150)->nullable()->index();
            $table->string('unit_kerja', 150)->nullable()->index();
            $table->string('acctno', 50)->nullable();
            $table->string('cifno', 50)->nullable()->index();
            $table->string('sname', 255)->nullable();
            $table->string('segmen', 100)->nullable();
            $table->string('segmen_bisnis_2025', 100)->nullable();
            $table->string('produk', 150)->nullable();
            $table->string('segmen_kur', 100)->nullable();
            $table->string('segmen_repeat', 100)->nullable();
            $table->string('segmen_2', 100)->nullable();
            $table->string('compliance', 100)->nullable();
            $table->decimal('saldo_ph', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cognos_ph');
        Schema::dropIfExists('cognos_recovery');
        Schema::dropIfExists('gi405_rec_dh');
        Schema::dropIfExists('ssa_pinjaman');
        Schema::dropIfExists('ssa_simpanan');
    }
};
