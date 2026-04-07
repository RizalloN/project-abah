<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addColumnIfMissing(string $table, string $column, callable $callback): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($callback) {
            $callback($tableBlueprint);
        });
    }

    public function up(): void
    {
        $table = 'daily_loan_dinamis';

        if (!Schema::hasTable($table)) {
            return;
        }

        $string100 = [
            'kode_kanwil1', 'kode_cabang1', 'branch1', 'curtyp', 'cifno', 'nomor_rekening1',
            'status_rekening1', 'ln_type', 'kol_adk1', 'kolek_detail', 'kolek', 'code',
            'kodepos_t_tinggal', 'kodepos_t_usaha', 'segmen_dashboard', 'produk_dashboard',
            'divisi_segmen_dashboard', 'npl_method', 'jenis_restruk1', 'flag_restruk',
            'flag_restruk_covid1', 'flag_commodity_chain1', 'flag_briguna_digital1',
            'flag_agf', 'flag_aft', 'offcr', 'lbdotu', 'flag_klaim',
        ];

        foreach ($string100 as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->string($column, 100)->nullable();
            });
        }

        $string150 = [
            'kanwil1', 'cabang1', 'unit1', 'ao_name', 'nama_debitur1', 'description',
            'kecamatan_t_tinggal', 'kelurahan_t_tinggal', 'kecamatan_t_usaha', 'kelurahan_t_usaha',
            'pn_name1', 'keterangan_pn_pengelola',
        ];

        foreach ($string150 as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->string($column, 150)->nullable();
            });
        }

        $textColumns = [
            'pn_pengelola1', 'pn_pemrakarsa1', 'pn_referral1', 'pn_restruk1', 'pn_pengelola2',
            'pn_pemutus1', 'pn_crm1', 'pn_crr', 'pn_referral_naik_kelas1', 'jadwal_gp_pokok',
        ];

        foreach ($textColumns as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->text($column)->nullable();
            });
        }

        $integerColumns = ['umur_tunggakan', 'freq_payment', 'freq_int_payment', 'jumlah_pn1', 'jumlah_pn_all1', 'restruk_ke1'];

        foreach ($integerColumns as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->integer($column)->nullable();
            });
        }

        $dateColumns = [
            'tgl_realisasi', 'tgl_jatuh_tempo', 'tanggal_menunggak', 'tgl_bayar_terakhir',
            'tgl_terminate', 'last_date_maintenance_billing', 'next_pmt_date',
            'next_pmt_int_date', 'tgl_akad_restruk',
        ];

        foreach ($dateColumns as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->date($column)->nullable();
            });
        }

        $decimalColumns = [
            'rate', 'plafon', 'baki_debet1', 'ckpn', 'nilai_tercatat1',
            'kolektabilitas_lancar', 'kolektabilitas_dpk', 'kolektabilitas_kuranglancar',
            'kolektabilitas_diragukan', 'kolektabilitas_macet', 'total_kewajiban', 'tunggakan_pokok',
            'tunggakan_bunga', 'tunggakan_penalti', 'advance_payment', 'bap', 'payment_amount',
            'final_payment_amount', 'npb_pokok_la', 'npb_pokok_lf', 'npb_bunga_la',
            'npb_bunga_lf', 'jml_angsuran1', 'jumlah_bayar', 'deffered_bunga',
            'sai_tunggakan', 'sai_deffered', 'sai1', 'pmtamt', 'pmtamt_base', 'os_idr',
            'os_sebelum_klaim', 'os_penuh_berjalan', 'bilprn', 'bilint', 'billc',
        ];

        foreach ($decimalColumns as $column) {
            $this->addColumnIfMissing($table, $column, function (Blueprint $tableBlueprint) use ($column) {
                $tableBlueprint->decimal($column, 20, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        $table = 'daily_loan_dinamis';

        if (!Schema::hasTable($table)) {
            return;
        }

        $columns = [
            'kode_kanwil1', 'kanwil1', 'kode_cabang1', 'cabang1', 'branch1', 'unit1', 'curtyp',
            'ao_name', 'nomor_rekening1', 'status_rekening1', 'ln_type', 'nama_debitur1',
            'rate', 'plafon', 'baki_debet1', 'ckpn', 'nilai_tercatat1', 'kol_adk1',
            'kolek_detail', 'kolek', 'kolektabilitas_lancar', 'kolektabilitas_dpk',
            'kolektabilitas_kuranglancar', 'kolektabilitas_diragukan', 'kolektabilitas_macet',
            'total_kewajiban', 'tunggakan_pokok', 'tunggakan_bunga', 'tunggakan_penalti',
            'umur_tunggakan', 'tgl_realisasi', 'tgl_jatuh_tempo', 'tanggal_menunggak',
            'tgl_bayar_terakhir', 'tgl_terminate', 'last_date_maintenance_billing',
            'next_pmt_date', 'next_pmt_int_date', 'advance_payment', 'bap', 'payment_amount',
            'final_payment_amount', 'npb_pokok_la', 'npb_pokok_lf', 'npb_bunga_la',
            'npb_bunga_lf', 'jml_angsuran1', 'jumlah_bayar', 'deffered_bunga', 'sai_tunggakan',
            'sai_deffered', 'sai1', 'freq_payment', 'freq_int_payment', 'jadwal_gp_pokok',
            'pn_pengelola1', 'pn_name1', 'pn_pemrakarsa1', 'pn_referral1', 'pn_restruk1',
            'pn_pengelola2', 'pn_pemutus1', 'pn_crm1', 'pn_crr', 'pn_referral_naik_kelas1',
            'jumlah_pn1', 'jumlah_pn_all1', 'code', 'description', 'kecamatan_t_tinggal',
            'kelurahan_t_tinggal', 'kodepos_t_tinggal', 'kecamatan_t_usaha',
            'kelurahan_t_usaha', 'kodepos_t_usaha', 'divisi_segmen_dashboard', 'npl_method',
            'restruk_ke1', 'jenis_restruk1', 'tgl_akad_restruk', 'flag_restruk',
            'flag_restruk_covid1', 'flag_commodity_chain1', 'flag_briguna_digital1',
            'flag_agf', 'flag_aft', 'pmtamt', 'pmtamt_base', 'offcr', 'lbdotu',
            'keterangan_pn_pengelola', 'os_idr', 'flag_klaim', 'os_sebelum_klaim',
            'os_penuh_berjalan', 'bilprn', 'bilint', 'billc',
        ];

        $existingColumns = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($table, $column)));
        if (!empty($existingColumns)) {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($existingColumns) {
                $tableBlueprint->dropColumn($existingColumns);
            });
        }
    }
};
