<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lw325_ph', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_namareport')->unique();
            $table->date('periode')->nullable()->index();
            $table->string('acctno', 50)->nullable()->index();
            $table->string('kanwil', 150)->nullable();
            $table->string('kanca', 150)->nullable()->index();
            $table->string('unit', 180)->nullable();
            $table->string('nama_debitur', 255)->nullable();
            $table->string('cif1', 50)->nullable()->index();
            $table->string('fksegmen', 30)->nullable();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('description', 150)->nullable();
            $table->string('produk_dashboard', 150)->nullable();
            $table->date('tgl_ph')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->string('curtyp', 10)->nullable();
            $table->decimal('saldo_pertama_ph_pokok', 20, 2)->nullable();
            $table->decimal('saldo_pertama_ph_bunga', 20, 2)->nullable();
            $table->decimal('besar_realisasi', 20, 2)->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->unsignedInteger('jw')->nullable();
            $table->unsignedInteger('at')->nullable();
            $table->string('cif', 50)->nullable()->index();
            $table->decimal('pokok', 20, 2)->nullable();
            $table->decimal('bunga', 20, 2)->nullable();
            $table->decimal('angpok', 20, 2)->nullable();
            $table->decimal('angbung', 20, 2)->nullable();
            $table->decimal('sisapok', 20, 2)->nullable();
            $table->decimal('sisabun', 20, 2)->nullable();
            $table->decimal('clmamt1', 20, 2)->nullable();
            $table->decimal('clmapr1', 20, 2)->nullable();
            $table->decimal('os_penuh_berjalan1', 20, 2)->nullable();
            $table->string('kecamatan_t_tinggal', 150)->nullable();
            $table->string('kelurahan_t_tinggal', 150)->nullable();
            $table->string('kodepos_t_tinggal', 20)->nullable();
            $table->string('kecamatan_t_usaha', 150)->nullable();
            $table->string('kelurahan_t_usaha', 150)->nullable();
            $table->string('kodepos_t_usaha', 20)->nullable();
            $table->string('pn_pengelola', 255)->nullable();
            $table->string('pn_pemrakarsa', 255)->nullable();
            $table->string('pn_referral', 255)->nullable();
            $table->string('pn_restruk', 255)->nullable();
            $table->string('pn_pengelola2', 255)->nullable();
            $table->string('pn_pemutus', 255)->nullable();
            $table->string('pn_crm', 255)->nullable();
            $table->string('pn_crr1', 255)->nullable();
            $table->string('pn_referral_naik_kelas', 255)->nullable();
            $table->unsignedInteger('jumlah_pn')->nullable();
            $table->unsignedInteger('jumlah_pn_all')->nullable();
            $table->decimal('saldo_pertama_kali_charge_off', 20, 2)->nullable();
            $table->decimal('deffered_bunga', 20, 2)->nullable();
            $table->decimal('sai_deffered', 20, 2)->nullable();
            $table->decimal('sai_tunggakan', 20, 2)->nullable();
            $table->decimal('deffered_bunga_ph', 20, 2)->nullable();
            $table->decimal('sai_tunggakan_ph', 20, 2)->nullable();
            $table->decimal('sai_deffered_ph', 20, 2)->nullable();
            $table->decimal('wcbal', 20, 2)->nullable();
            $table->decimal('waccint', 20, 2)->nullable();
            $table->decimal('wadvpmt', 20, 2)->nullable();
            $table->decimal('wpenint', 20, 2)->nullable();
            $table->decimal('wmisc', 20, 2)->nullable();
            $table->decimal('wothchg', 20, 2)->nullable();
            $table->decimal('wpmtamt', 20, 2)->nullable();
            $table->date('wpstdt')->nullable();
            $table->date('wpstdt6')->nullable();
            $table->decimal('wamount', 20, 2)->nullable();
            $table->string('flag_klaim', 10)->nullable();
            $table->decimal('clmamt', 20, 2)->nullable();
            $table->decimal('clmapr', 20, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lw325_ph');
    }
};
