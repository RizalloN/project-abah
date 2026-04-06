<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->id();
            $table->string('uniqueid_namareport', 50)->unique();

            $table->string('PERIODE', 20)->nullable();
            $table->string('KODE_KANWIL1', 20)->nullable();
            $table->string('KANWIL1', 100)->nullable();
            $table->string('KODE_CABANG1', 20)->nullable();
            $table->string('CABANG1', 100)->nullable();
            $table->string('BRANCH1', 100)->nullable();
            $table->string('UNIT1', 100)->nullable();
            $table->string('CURTYP', 10)->nullable();
            $table->string('AO_NAME', 100)->nullable();
            $table->string('CIFNO', 50)->nullable();
            $table->string('NOMOR_REKENING1', 50)->nullable();
            $table->string('STATUS_REKENING1', 50)->nullable();
            $table->string('LN_TYPE', 50)->nullable();
            $table->string('NAMA_DEBITUR1', 150)->nullable();
            $table->decimal('RATE', 10, 4)->nullable();
            $table->integer('JANGKA_WAKTU1')->nullable();
            $table->decimal('PLAFON', 18, 2)->nullable();
            $table->decimal('BAKI_DEBET1', 18, 2)->nullable();
            $table->decimal('CKPN', 18, 2)->nullable();
            $table->decimal('NILAI_TERCATAT1', 18, 2)->nullable();
            $table->string('KOL_ADK1', 10)->nullable();
            $table->string('KOLEK_DETAIL', 50)->nullable();
            $table->string('KOLEK', 10)->nullable();
            $table->decimal('KOLEKTABILITAS_LANCAR', 18, 2)->nullable();
            $table->decimal('KOLEKTABILITAS_DPK', 18, 2)->nullable();
            $table->decimal('KOLEKTABILITAS_KURANGLANCAR', 18, 2)->nullable();
            $table->decimal('KOLEKTABILITAS_DIRAGUKAN', 18, 2)->nullable();
            $table->decimal('KOLEKTABILITAS_MACET', 18, 2)->nullable();
            $table->string('TOTAL_KEWAJIBAN', 100)->nullable();
            $table->decimal('TUNGGAKAN_POKOK', 18, 2)->nullable();
            $table->decimal('TUNGGAKAN_BUNGA', 18, 2)->nullable();
            $table->decimal('TUNGGAKAN_PENALTI', 18, 2)->nullable();
            $table->integer('UMUR_TUNGGAKAN')->nullable();
            $table->date('TGL_REALISASI')->nullable();
            $table->date('TGL_JATUH_TEMPO')->nullable();
            $table->date('TANGGAL_MENUNGGAK')->nullable();
            $table->date('TGL_BAYAR_TERAKHIR')->nullable();
            $table->date('TGL_TERMINATE')->nullable();
            $table->date('LAST_DATE_MAINTENANCE_BILLING')->nullable();
            $table->date('NEXT_PMT_DATE')->nullable();
            $table->date('NEXT_PMT_INT_DATE')->nullable();
            $table->decimal('ADVANCE_PAYMENT', 18, 2)->nullable();
            $table->decimal('BAP', 18, 2)->nullable();
            $table->decimal('PAYMENT_AMOUNT', 18, 2)->nullable();
            $table->decimal('FINAL_PAYMENT_AMOUNT', 18, 2)->nullable();
            $table->decimal('NPB_POKOK_LA', 18, 2)->nullable();
            $table->decimal('NPB_POKOK_LF', 18, 2)->nullable();
            $table->decimal('NPB_BUNGA_LA', 18, 2)->nullable();
            $table->decimal('NPB_BUNGA_LF', 18, 2)->nullable();
            $table->decimal('JML_ANGSURAN1', 18, 2)->nullable();
            $table->decimal('JUMLAH_BAYAR', 18, 2)->nullable();
            $table->decimal('DEFFERED_BUNGA', 18, 2)->nullable();
            $table->decimal('SAI_TUNGGAKAN', 18, 2)->nullable();
            $table->decimal('SAI_DEFFERED', 18, 2)->nullable();
            $table->decimal('SAI1', 18, 2)->nullable();
            $table->integer('FREQ_PAYMENT')->nullable();
            $table->integer('FREQ_INT_PAYMENT')->nullable();
            $table->string('JADWAL_GP_POKOK', 50)->nullable();
            $table->string('PN_PENGELOLA1', 100)->nullable();
            $table->string('PN_NAME1', 100)->nullable();
            $table->string('PN_PEMRAKARSA1', 100)->nullable();
            $table->string('PN_REFERRAL1', 100)->nullable();
            $table->string('PN_RESTRUK1', 100)->nullable();
            $table->string('PN_PENGELOLA2', 100)->nullable();
            $table->string('PN_PEMUTUS1', 100)->nullable();
            $table->string('PN_CRM1', 100)->nullable();
            $table->string('PN_CRR', 100)->nullable();
            $table->string('PN_REFERRAL_NAIK_KELAS1', 100)->nullable();
            $table->integer('JUMLAH_PN1')->nullable();
            $table->integer('JUMLAH_PN_ALL1')->nullable();
            $table->string('CODE', 50)->nullable();
            $table->string('DESCRIPTION', 255)->nullable();
            $table->string('KECAMATAN_T_TINGGAL', 100)->nullable();
            $table->string('KELURAHAN_T_TINGGAL', 100)->nullable();
            $table->string('KODEPOS_T_TINGGAL', 10)->nullable();
            $table->string('KECAMATAN_T_USAHA', 100)->nullable();
            $table->string('KELURAHAN_T_USAHA', 100)->nullable();
            $table->string('KODEPOS_T_USAHA', 10)->nullable();
            $table->string('SEGMEN_DASHBOARD', 100)->nullable();
            $table->string('PRODUK_DASHBOARD', 100)->nullable();
            $table->string('DIVISI_SEGMEN_DASHBOARD', 100)->nullable();
            $table->string('NPL_METHOD', 50)->nullable();
            $table->integer('RESTRUK_KE1')->nullable();
            $table->string('JENIS_RESTRUK1', 100)->nullable();
            $table->date('TGL_AKAD_RESTRUK')->nullable();
            $table->string('FLAG_RESTRUK', 10)->nullable();
            $table->string('FLAG_RESTRUK_COVID1', 10)->nullable();
            $table->string('FLAG_COMMODITY_CHAIN1', 10)->nullable();
            $table->string('FLAG_BRIGUNA_DIGITAL1', 10)->nullable();
            $table->string('FLAG_AGF', 10)->nullable();
            $table->string('FLAG_AFT', 10)->nullable();
            $table->decimal('PMTAMT', 18, 2)->nullable();
            $table->decimal('PMTAMT_Base', 18, 2)->nullable();
            $table->string('OFFCR', 50)->nullable();
            $table->string('LBDOTU', 50)->nullable();
            $table->string('KETERANGAN_PN_PENGELOLA', 255)->nullable();
            $table->string('OS_IDR', 100)->nullable();
            $table->string('FLAG_KLAIM', 10)->nullable();
            $table->decimal('OS_SEBELUM_KLAIM', 18, 2)->nullable();
            $table->decimal('OS_PENUH_BERJALAN', 18, 2)->nullable();
            $table->decimal('BILPRN', 18, 2)->nullable();
            $table->decimal('BILINT', 18, 2)->nullable();
            $table->decimal('BILLC', 18, 2)->nullable();

            // Kolom kompatibilitas untuk fitur lama/report internal
            $table->date('periode')->nullable();
            $table->string('kode_kanwil', 50)->nullable();
            $table->string('kanwil', 100)->nullable();
            $table->string('kode_cabang', 50)->nullable();
            $table->string('cabang', 100)->nullable();
            $table->string('branch', 100)->nullable();
            $table->string('unit', 100)->nullable();
            $table->string('ao_name', 150)->nullable();
            $table->string('cifno', 50)->nullable();
            $table->string('nomor_rekening', 100)->nullable();
            $table->decimal('baki_debet', 18, 2)->nullable();
            $table->string('segmen_dashboard', 100)->nullable();
            $table->string('produk_dashboard', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');
    }
};
