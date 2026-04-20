<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Simpanan MultiPN
        if (!Schema::hasTable('simpanan_multipn')) {
            Schema::create('simpanan_multipn', function (Blueprint $table) {
            $table->string('uniqueid_SMPN', 50)->primary();
            $table->date('posisi')->nullable()->index();
            $table->string('regional_office', 100)->nullable();
            $table->string('kantor_cabang', 100)->nullable()->index();
            $table->string('unit_kerja', 100)->nullable()->index();
            $table->string('CIFNO', 50)->nullable()->index();
            $table->string('no_rekening', 50)->nullable();
            $table->string('jenis_simpanan', 50)->nullable();
            $table->string('status', 50)->nullable()->index();
            $table->decimal('saldo_idr', 18, 2)->nullable();
            $table->timestamps();

            $table->index(['posisi', 'kantor_cabang', 'unit_kerja'], 'idx_smp_posisi_cab_unit');
            $table->index(['posisi', 'status', 'kantor_cabang', 'unit_kerja'], 'idx_smp_posisi_status_cab_unit');
            $table->index(['posisi', 'updated_at'], 'idx_smp_posisi_updated');
                $table->index(['posisi', 'CIFNO'], 'idx_smp_posisi_cif');
            });
        }

        // 2. Daily Loan Dinamis
        if (!Schema::hasTable('daily_loan_dinamis')) {
            Schema::create('daily_loan_dinamis', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('periode')->nullable()->index();
            $table->string('kode_kanwil1', 100)->nullable();
            $table->string('kanwil1', 150)->nullable();
            $table->string('kode_cabang1', 100)->nullable();
            $table->string('cabang1', 150)->nullable()->index();
            $table->string('branch1', 100)->nullable();
            $table->string('unit1', 150)->nullable()->index();
            $table->string('curtyp', 100)->nullable();
            $table->string('ao_name', 150)->nullable();
            $table->string('cifno', 50)->nullable()->index();
            $table->string('nomor_rekening1', 100)->nullable();
            $table->string('status_rekening1', 100)->nullable();
            $table->string('ln_type', 100)->nullable();
            $table->string('nama_debitur1', 150)->nullable();
            $table->decimal('rate', 20, 2)->nullable();
            $table->string('jangka_waktu1', 50)->nullable();
            $table->decimal('plafon', 20, 2)->nullable();
            $table->decimal('baki_debet1', 20, 2)->nullable();
            $table->decimal('ckpn', 20, 2)->nullable();
            $table->decimal('nilai_tercatat1', 20, 2)->nullable();
            $table->string('kol_adk1', 100)->nullable();
            $table->string('kolek_detail', 100)->nullable();
            $table->string('kolek', 100)->nullable();
            $table->decimal('kolektabilitas_lancar', 20, 2)->nullable();
            $table->decimal('kolektabilitas_dpk', 20, 2)->nullable();
            $table->decimal('kolektabilitas_kuranglancar', 20, 2)->nullable();
            $table->decimal('kolektabilitas_diragukan', 20, 2)->nullable();
            $table->decimal('kolektabilitas_macet', 20, 2)->nullable();
            $table->decimal('total_kewajiban', 20, 2)->nullable();
            $table->decimal('tunggakan_pokok', 20, 2)->nullable();
            $table->decimal('tunggakan_bunga', 20, 2)->nullable();
            $table->decimal('tunggakan_penalti', 20, 2)->nullable();
            $table->integer('umur_tunggakan')->nullable();
            $table->date('tgl_realisasi')->nullable();
            $table->date('tgl_jatuh_tempo')->nullable();
            $table->date('tanggal_menunggak')->nullable();
            $table->date('tgl_bayar_terakhir')->nullable();
            $table->date('tgl_terminate')->nullable();
            $table->date('last_date_maintenance_billing')->nullable();
            $table->date('next_pmt_date')->nullable();
            $table->date('next_pmt_int_date')->nullable();
            $table->decimal('advance_payment', 20, 2)->nullable();
            $table->decimal('bap', 20, 2)->nullable();
            $table->decimal('payment_amount', 20, 2)->nullable();
            $table->decimal('final_payment_amount', 20, 2)->nullable();
            $table->decimal('npb_pokok_la', 20, 2)->nullable();
            $table->decimal('npb_pokok_lf', 20, 2)->nullable();
            $table->decimal('npb_bunga_la', 20, 2)->nullable();
            $table->decimal('npb_bunga_lf', 20, 2)->nullable();
            $table->decimal('jml_angsuran1', 20, 2)->nullable();
            $table->decimal('jumlah_bayar', 20, 2)->nullable();
            $table->decimal('deffered_bunga', 20, 2)->nullable();
            $table->decimal('sai_tunggakan', 20, 2)->nullable();
            $table->decimal('sai_deffered', 20, 2)->nullable();
            $table->decimal('sai1', 20, 2)->nullable();
            $table->integer('freq_payment')->nullable();
            $table->integer('freq_int_payment')->nullable();
            $table->text('jadwal_gp_pokok')->nullable();
            $table->text('pn_pengelola1')->nullable();
            $table->string('pn_name1', 150)->nullable();
            $table->text('pn_pemrakarsa1')->nullable();
            $table->text('pn_referral1')->nullable();
            $table->text('pn_restruk1')->nullable();
            $table->text('pn_pengelola2')->nullable();
            $table->text('pn_pemutus1')->nullable();
            $table->text('pn_crm1')->nullable();
            $table->text('pn_crr')->nullable();
            $table->text('pn_referral_naik_kelas1')->nullable();
            $table->integer('jumlah_pn1')->nullable();
            $table->integer('jumlah_pn_all1')->nullable();
            $table->string('code', 100)->nullable();
            $table->string('description', 150)->nullable();
            $table->string('kecamatan_t_tinggal', 150)->nullable();
            $table->string('kelurahan_t_tinggal', 150)->nullable();
            $table->string('kodepos_t_tinggal', 100)->nullable();
            $table->string('kecamatan_t_usaha', 150)->nullable();
            $table->string('kelurahan_t_usaha', 150)->nullable();
            $table->string('kodepos_t_usaha', 100)->nullable();
            $table->string('segmen_dashboard', 100)->nullable()->index();
            $table->string('produk_dashboard', 100)->nullable()->index();
            $table->string('divisi_segmen_dashboard', 100)->nullable();
            $table->string('npl_method', 100)->nullable();
            $table->integer('restruk_ke1')->nullable();
            $table->string('jenis_restruk1', 100)->nullable();
            $table->date('tgl_akad_restruk')->nullable();
            $table->string('flag_restruk', 100)->nullable();
            $table->string('flag_restruk_covid1', 100)->nullable();
            $table->string('flag_commodity_chain1', 100)->nullable();
            $table->string('flag_briguna_digital1', 100)->nullable();
            $table->string('flag_agf', 100)->nullable();
            $table->string('flag_aft', 100)->nullable();
            $table->decimal('pmtamt', 20, 2)->nullable();
            $table->decimal('pmtamt_base', 20, 2)->nullable();
            $table->string('offcr', 100)->nullable();
            $table->string('lbdotu', 100)->nullable();
            $table->string('keterangan_pn_pengelola', 150)->nullable();
            $table->decimal('os_idr', 20, 2)->nullable();
            $table->string('flag_klaim', 100)->nullable();
            $table->decimal('os_sebelum_klaim', 20, 2)->nullable();
            $table->decimal('os_penuh_berjalan', 20, 2)->nullable();
            $table->decimal('bilprn', 20, 2)->nullable();
            $table->decimal('bilint', 20, 2)->nullable();
            $table->decimal('billc', 20, 2)->nullable();
            $table->timestamps();

            $table->index(['periode', 'cifno'], 'idx_loan_periode_cif');
            $table->index(['periode', 'nomor_rekening1'], 'idx_loan_periode_rek');
            $table->index(['periode', 'cabang1', 'unit1'], 'idx_loan_periode_cab_unit');
            $table->index(['periode', 'segmen_dashboard', 'produk_dashboard'], 'idx_loan_periode_segmen');
                $table->index(['periode', 'produk_dashboard'], 'idx_loan_periode_produk');
            });
        }

        // 3. Nominatif PH
        if (!Schema::hasTable('lw325_ph')) {
            Schema::create('lw325_ph', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
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
            $table->decimal('os_penuh_berjalan1', 20, 2)->nullable();
            $table->timestamps();

                $table->index(['periode', 'kanca', 'uniqueid_namareport'], 'idx_lw325ph_delete_scope');
            });
        }

        // 4. Performance PIS
        if (!Schema::hasTable('performance_pis_per_produk')) {
            Schema::create('performance_pis_per_produk', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('posisi')->nullable()->index();
            $table->string('kode_kanwil', 20)->nullable();
            $table->string('kanwil', 150)->nullable();
            $table->string('kode_kanca', 20)->nullable();
            $table->string('kanca', 150)->nullable()->index();
            $table->string('kode_uker', 20)->nullable();
            $table->string('uker', 150)->nullable();
            $table->string('corporate_code', 30)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('jenis_mitra', 100)->nullable();
            $table->string('jenis_perusahaan', 100)->nullable();
            $table->string('tipe_produk', 50)->nullable();
            $table->string('nomor_rekening', 50)->nullable()->index();
            $table->string('nama_rekening', 255)->nullable();
            $table->decimal('saldo_britama_kerjasama', 20, 2)->nullable();
            $table->date('tanggal_pembuatan_rekening')->nullable();
            $table->timestamps();

            $table->index(['posisi', 'kanca'], 'idx_pis_posisi_kanca');
                $table->index(['posisi', 'nomor_rekening'], 'idx_pis_posisi_rek');
            });
        }

        // 5. Brilink Web Summary
        if (!Schema::hasTable('brilink_web_laporan_summary_transaksi_brilink_web')) {
            Schema::create('brilink_web_laporan_summary_transaksi_brilink_web', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 50)->primary();
            $table->string('periode', 20)->nullable()->index();
            $table->string('kanwil', 100)->nullable();
            $table->string('cabang', 100)->nullable()->index();
            $table->string('uker', 100)->nullable();
            $table->string('merchant_name', 150)->nullable();
            $table->string('merchant_code', 50)->nullable();
            $table->string('outlet_name', 150)->nullable();
            $table->string('outlet_code', 50)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
            $table->decimal('total_nominal', 18, 2)->nullable();
            $table->decimal('total_fee', 18, 2)->nullable();
            $table->decimal('total_fee_bri', 18, 2)->nullable();
            $table->timestamps();

                $table->index(['periode', 'cabang', 'uker'], 'idx_brilink_period_cab_uker');
            });
        }

        // 6. EDC Detail (Merchant Detail)
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            Schema::create('jumlah_merchant_detail', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('PERIODE', 20)->nullable()->index();
            $table->date('POSISI')->nullable()->index();
            $table->string('MID', 150)->nullable();
            $table->string('TID', 150)->nullable();
            $table->string('NAMA_KANCA', 150)->nullable()->index();
            $table->string('NAMA_UKER', 150)->nullable()->index();
            $table->decimal('SALES_VOLUME', 25, 2)->nullable();
            $table->string('TIERING_SALES_VOLUME', 100)->nullable();
            $table->timestamps();

                $table->index(['POSISI', 'NAMA_KANCA', 'NAMA_UKER'], 'idx_edc_posisi_cab_unit');
            });
        }

        // 7. CASA Brilink
        if (!Schema::hasTable('casa_brilink_web')) {
            Schema::create('casa_brilink_web', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->date('periode')->index();
            $table->string('mbdesc', 150)->nullable()->index();
            $table->string('brdesc', 150)->nullable()->index();
            $table->string('account', 50)->nullable()->index();
            $table->decimal('jml_nominal_casa', 20, 2)->nullable();
            $table->string('cifno', 50)->nullable();
            $table->timestamps();

                $table->index(['periode', 'mbdesc', 'brdesc'], 'idx_casaweb_period_cab_uker');
            });
        }

        if (!Schema::hasTable('casa_brilink_edc')) {
            Schema::create('casa_brilink_edc', function (Blueprint $table) {
                $table->string('uniqueid_namareport', 255)->primary();
                $table->date('periode')->index();
                $table->string('mbdesc', 150)->nullable()->index();
                $table->string('brdesc', 150)->nullable()->index();
                $table->string('account', 50)->nullable()->index();
                $table->decimal('jml_nominal_casa', 20, 2)->nullable();
                $table->string('cifno', 50)->nullable();
                $table->timestamps();

                $table->index(['periode', 'mbdesc', 'brdesc'], 'idx_casaedc_period_cab_uker');
            });
        }

        if (!Schema::hasTable('merchant_qris')) {
            Schema::create('merchant_qris', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('PERIODE', 20)->nullable()->index();
            $table->date('POSISI')->nullable()->index();
            $table->string('NAMA_KCI', 150)->nullable()->index();
            $table->string('NAMA_BRANCH', 150)->nullable()->index();
            $table->decimal('NILAI', 20, 2)->nullable();
            $table->timestamps();

                $table->index(['POSISI', 'NAMA_KCI', 'NAMA_BRANCH'], 'idx_qris_posisi_kanwil_cab');
            });
        }

        if (!Schema::hasTable('merchant_qris_volume')) {
            Schema::create('merchant_qris_volume', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('PERIODE', 20)->nullable()->index();
            $table->date('POSISI')->nullable()->index();
            $table->string('NAMA_KCI', 150)->nullable()->index();
            $table->string('NAMA_BRANCH', 150)->nullable()->index();
            $table->string('JENIS', 50)->nullable();
            $table->decimal('MERCHANT_QRIS_VOLUME', 20, 2)->nullable();
            $table->timestamps();

                $table->index(['POSISI', 'NAMA_KCI', 'NAMA_BRANCH'], 'idx_qrisvol_posisi_kanwil_cab');
            });
        }

        if (!Schema::hasTable('sv_merchant')) {
            Schema::create('sv_merchant', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('PERIODE', 20)->nullable()->index();
            $table->date('POSISI')->nullable()->index();
            $table->string('NAMA_KCI', 150)->nullable()->index();
            $table->string('NAMA_BRANCH', 150)->nullable()->index();
            $table->decimal('SV_MERCHANT', 20, 2)->nullable();
            $table->timestamps();

                $table->index(['POSISI', 'NAMA_KCI', 'NAMA_BRANCH'], 'idx_sv_posisi_kanwil_cab');
            });
        }

        // 8. Brimo Reports
        if (!Schema::hasTable('user_brimo_fin')) {
            Schema::create('user_brimo_fin', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('periode', 20)->nullable()->index();
            $table->date('posisi')->nullable()->index();
            $table->string('tahun', 10)->nullable()->index();
            $table->string('region', 50)->nullable()->index();
            $table->string('rgdesc', 100)->nullable()->index();
            $table->string('segmentasi', 100)->nullable()->index();
            $table->string('mainbr', 20)->nullable()->index();
            $table->string('mbdesc', 100)->nullable()->index();
            $table->string('branch', 20)->nullable()->index();
            $table->string('brdesc', 100)->nullable()->index();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('jenis', 100)->nullable()->index();
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_brimo_rpt_v2')) {
            Schema::create('user_brimo_rpt_v2', function (Blueprint $table) {
            $table->string('uniqueid_namareport', 255)->primary();
            $table->string('periode', 20)->nullable()->index();
            $table->date('posisi')->nullable()->index();
            $table->string('tahun', 10)->nullable()->index();
            $table->string('region', 50)->nullable()->index();
            $table->string('rgdesc', 100)->nullable()->index();
            $table->string('segmentasi', 100)->nullable()->index();
            $table->string('mainbr', 20)->nullable()->index();
            $table->string('mbdesc', 100)->nullable()->index();
            $table->string('branch', 20)->nullable()->index();
            $table->string('brdesc', 100)->nullable()->index();
            $table->string('kategori', 100)->nullable()->index();
            $table->string('jenis', 100)->nullable()->index();
                $table->decimal('jumlah', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_brimo_rpt_v2');
        Schema::dropIfExists('user_brimo_fin');
        Schema::dropIfExists('sv_merchant');
        Schema::dropIfExists('merchant_qris_volume');
        Schema::dropIfExists('merchant_qris');
        Schema::dropIfExists('casa_brilink_edc');
        Schema::dropIfExists('casa_brilink_web');
        Schema::dropIfExists('jumlah_merchant_detail');
        Schema::dropIfExists('brilink_web_laporan_summary_transaksi_brilink_web');
        Schema::dropIfExists('performance_pis_per_produk');
        Schema::dropIfExists('lw325_ph');
        Schema::dropIfExists('daily_loan_dinamis');
        Schema::dropIfExists('simpanan_multipn');
    }
};
