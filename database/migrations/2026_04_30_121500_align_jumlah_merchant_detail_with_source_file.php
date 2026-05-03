<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align jumlah_merchant_detail with the source JUMLAH_MERCHANT_DETAIL_R_ALL file.
     *
     * The source columns are kept in their file order after uniqueid_namareport.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jumlah_merchant_detail')) {
            return;
        }

        $columns = [
            'TAHUN' => ['varchar(255)', 'uniqueid_namareport'],
            'PERIODE' => ['varchar(255)', 'TAHUN'],
            'POSISI' => ['datetime', 'PERIODE'],
            'KODE_KANWIL' => ['varchar(255)', 'POSISI'],
            'NAMA_KANWIL' => ['varchar(255)', 'KODE_KANWIL'],
            'KODE_KANCA' => ['varchar(255)', 'NAMA_KANWIL'],
            'NAMA_KANCA' => ['varchar(255)', 'KODE_KANCA'],
            'KODE_UKER' => ['varchar(255)', 'NAMA_KANCA'],
            'NAMA_UKER' => ['varchar(255)', 'KODE_UKER'],
            'TID' => ['varchar(255)', 'NAMA_UKER'],
            'MID' => ['varchar(255)', 'TID'],
            'NAMA_MERCHANT' => ['varchar(255)', 'MID'],
            'JENIS' => ['varchar(255)', 'NAMA_MERCHANT'],
            'KANWIL_PEMRAKARSA' => ['varchar(255)', 'JENIS'],
            'KANWIL_NAMA_PEMRAKARSA' => ['varchar(255)', 'KANWIL_PEMRAKARSA'],
            'UKER_PEMRAKARSA' => ['varchar(255)', 'KANWIL_NAMA_PEMRAKARSA'],
            'UKER_NAMA_PEMRAKARSA' => ['varchar(255)', 'UKER_PEMRAKARSA'],
            'KANWIL_IMPLEMENTOR' => ['varchar(255)', 'UKER_NAMA_PEMRAKARSA'],
            'KANWIL_NAMA_IMPLEMENTOR' => ['varchar(255)', 'KANWIL_IMPLEMENTOR'],
            'UKER_IMPLEMENTOR' => ['varchar(255)', 'KANWIL_NAMA_IMPLEMENTOR'],
            'UKER_NAMA_IMPLEMENTOR' => ['varchar(255)', 'UKER_IMPLEMENTOR'],
            'PN_USER_PEMRAKARSA' => ['varchar(255)', 'UKER_NAMA_IMPLEMENTOR'],
            'NAMA_USER_PEMRAKARSA' => ['varchar(255)', 'PN_USER_PEMRAKARSA'],
            'LAST_AVAILABLE' => ['datetime', 'NAMA_USER_PEMRAKARSA'],
            'STATUS_AVAILABLE' => ['varchar(255)', 'LAST_AVAILABLE'],
            'LAST_UTILITY' => ['datetime', 'STATUS_AVAILABLE'],
            'STATUS_UTILITY' => ['varchar(255)', 'LAST_UTILITY'],
            'LAST_TRANSACTIONAL' => ['datetime', 'STATUS_UTILITY'],
            'STATUS_TRANSACTIONAL' => ['varchar(255)', 'LAST_TRANSACTIONAL'],
            'ALAMAT_MERCHANT' => ['text', 'STATUS_TRANSACTIONAL'],
            'KELURAHAN' => ['varchar(255)', 'ALAMAT_MERCHANT'],
            'KECAMATAN' => ['varchar(255)', 'KELURAHAN'],
            'KABUPATEN' => ['varchar(255)', 'KECAMATAN'],
            'PROVINSI' => ['varchar(255)', 'KABUPATEN'],
            'AKTIF_OR_STAGING' => ['varchar(255)', 'PROVINSI'],
            'JML_TRANSAKSI' => ['bigint', 'AKTIF_OR_STAGING'],
            'SALES_VOLUME' => ['decimal(25,2)', 'JML_TRANSAKSI'],
            'AKUMULASI_TRANSAKSI' => ['bigint', 'SALES_VOLUME'],
            'AKUMULASI_SALES_VOLUME' => ['decimal(25,2)', 'AKUMULASI_TRANSAKSI'],
            'KARTU_JML_TRANSAKSI_ON_US' => ['bigint', 'AKUMULASI_SALES_VOLUME'],
            'KARTU_JML_TRANSAKSI_OFF_US' => ['bigint', 'KARTU_JML_TRANSAKSI_ON_US'],
            'JML_TRANSAKSI_LAINNYA' => ['bigint', 'KARTU_JML_TRANSAKSI_OFF_US'],
            'KARTU_SALES_VOLUME_ON_US' => ['decimal(25,2)', 'JML_TRANSAKSI_LAINNYA'],
            'KARTU_SALES_VOLUME_OFF_US' => ['decimal(25,2)', 'KARTU_SALES_VOLUME_ON_US'],
            'SALES_VOLUME_LAINNYA' => ['decimal(25,2)', 'KARTU_SALES_VOLUME_OFF_US'],
            'KET_MCC' => ['varchar(255)', 'SALES_VOLUME_LAINNYA'],
            'KODE_MCC' => ['varchar(255)', 'KET_MCC'],
            'NOREK' => ['varchar(255)', 'KODE_MCC'],
            'CIFNO' => ['varchar(255)', 'NOREK'],
            'SALDO_POSISI' => ['decimal(25,2)', 'CIFNO'],
            'RATAS_SALDO' => ['decimal(25,2)', 'SALDO_POSISI'],
            'SALDO_POSISI_BY_CIF' => ['decimal(25,2)', 'RATAS_SALDO'],
            'RATAS_SALDO_BY_CIF' => ['decimal(25,2)', 'SALDO_POSISI_BY_CIF'],
            'TGL_APPROVAL' => ['datetime', 'RATAS_SALDO_BY_CIF'],
            'NILAI' => ['varchar(255)', 'TGL_APPROVAL'],
            'SOURCE' => ['varchar(255)', 'NILAI'],
            'SALES_VOLUME_MID' => ['decimal(25,2)', 'SOURCE'],
            'FLAGGING' => ['varchar(255)', 'SALES_VOLUME_MID'],
            'FLAGGING_BRI_MERCHANT' => ['varchar(255)', 'FLAGGING'],
            'TIERING_SALES_VOLUME' => ['varchar(255)', 'FLAGGING_BRI_MERCHANT'],
        ];

        foreach ($columns as $column => [$type, $after]) {
            $columnSql = '`' . str_replace('`', '``', $column) . '`';
            $afterSql = '`' . str_replace('`', '``', $after) . '`';
            $definition = "{$columnSql} {$type} NULL AFTER {$afterSql}";

            if (Schema::hasColumn('jumlah_merchant_detail', $column)) {
                DB::statement("ALTER TABLE `jumlah_merchant_detail` MODIFY {$definition}");
                continue;
            }

            DB::statement("ALTER TABLE `jumlah_merchant_detail` ADD {$definition}");
        }
    }

    public function down(): void
    {
        // Intentionally no-op: this migration preserves imported source columns.
    }
};
