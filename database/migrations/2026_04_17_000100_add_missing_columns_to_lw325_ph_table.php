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
        Schema::table('lw325_ph', function (Blueprint $table) {
            // Amounts / Decimals
            $table->decimal('clmamt1', 20, 2)->nullable()->after('sisabun');
            $table->decimal('clmapr1', 20, 2)->nullable()->after('clmamt1');
            
            // Address & PN Information
            $table->string('kecamatan_t_tinggal', 255)->nullable()->after('os_penuh_berjalan1');
            $table->string('kelurahan_t_tinggal', 255)->nullable()->after('kecamatan_t_tinggal');
            $table->string('kodepos_t_tinggal', 50)->nullable()->after('kelurahan_t_tinggal');
            $table->string('kecamatan_t_usaha', 255)->nullable()->after('kodepos_t_tinggal');
            $table->string('kelurahan_t_usaha', 255)->nullable()->after('kecamatan_t_usaha');
            $table->string('kodepos_t_usaha', 50)->nullable()->after('kelurahan_t_usaha');
            
            $table->string('pn_pengelola', 255)->nullable()->after('kodepos_t_usaha');
            $table->string('pn_pemrakarsa', 255)->nullable()->after('pn_pengelola');
            $table->string('pn_referral', 255)->nullable()->after('pn_pemrakarsa');
            $table->string('pn_restruk', 255)->nullable()->after('pn_referral');
            $table->string('pn_pengelola2', 255)->nullable()->after('pn_restruk');
            $table->string('pn_pemutus', 255)->nullable()->after('pn_pengelola2');
            $table->string('pn_crm', 255)->nullable()->after('pn_pemutus');
            $table->string('pn_crr1', 255)->nullable()->after('pn_crm');
            $table->string('pn_referral_naik_kelas', 255)->nullable()->after('pn_crr1');
            
            $table->unsignedInteger('jumlah_pn')->nullable()->after('pn_referral_naik_kelas');
            $table->unsignedInteger('jumlah_pn_all')->nullable()->after('jumlah_pn');
            
            // Financial Status Columns
            $table->decimal('saldo_pertama_kali_charge_off', 20, 2)->nullable()->after('jumlah_pn_all');
            $table->decimal('deffered_bunga', 20, 2)->nullable()->after('saldo_pertama_kali_charge_off');
            $table->decimal('sai_deffered', 20, 2)->nullable()->after('deffered_bunga');
            $table->decimal('sai_tunggakan', 20, 2)->nullable()->after('sai_deffered');
            $table->decimal('deffered_bunga_ph', 20, 2)->nullable()->after('sai_tunggakan');
            $table->decimal('sai_tunggakan_ph', 20, 2)->nullable()->after('deffered_bunga_ph');
            $table->decimal('sai_deffered_ph', 20, 2)->nullable()->after('sai_tunggakan_ph');
            
            $table->decimal('wcbal', 20, 2)->nullable()->after('sai_deffered_ph');
            $table->decimal('waccint', 20, 2)->nullable()->after('wcbal');
            $table->decimal('wadvpmt', 20, 2)->nullable()->after('waccint');
            $table->decimal('wpenint', 20, 2)->nullable()->after('wadvpmt');
            $table->decimal('wmisc', 20, 2)->nullable()->after('wpenint');
            $table->decimal('wothchg', 20, 2)->nullable()->after('wmisc');
            $table->decimal('wpmtamt', 20, 2)->nullable()->after('wothchg');
            
            $table->date('wpstdt')->nullable()->after('wpmtamt');
            $table->date('wpstdt6')->nullable()->after('wpstdt');
            $table->decimal('wamount', 20, 2)->nullable()->after('wpstdt6');
            
            $table->string('flag_klaim', 20)->nullable()->after('wamount');
            $table->decimal('clmamt', 20, 2)->nullable()->after('flag_klaim');
            $table->decimal('clmapr', 20, 2)->nullable()->after('clmamt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lw325_ph', function (Blueprint $table) {
            $table->dropColumn([
                'clmamt1', 'clmapr1', 'kecamatan_t_tinggal', 'kelurahan_t_tinggal', 'kodepos_t_tinggal',
                'kecamatan_t_usaha', 'kelurahan_t_usaha', 'kodepos_t_usaha', 'pn_pengelola', 'pn_pemrakarsa',
                'pn_referral', 'pn_restruk', 'pn_pengelola2', 'pn_pemutus', 'pn_crm', 'pn_crr1',
                'pn_referral_naik_kelas', 'jumlah_pn', 'jumlah_pn_all', 'saldo_pertama_kali_charge_off',
                'deffered_bunga', 'sai_deffered', 'sai_tunggakan', 'deffered_bunga_ph', 'sai_tunggakan_ph',
                'sai_deffered_ph', 'wcbal', 'waccint', 'wadvpmt', 'wpenint', 'wmisc', 'wothchg', 'wpmtamt',
                'wpstdt', 'wpstdt6', 'wamount', 'flag_klaim', 'clmamt', 'clmapr'
            ]);
        });
    }
};
