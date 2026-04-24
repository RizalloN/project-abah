<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redundant indexes removed here are single-column indexes whose leading
     * column is already covered by a wider composite or unique index.
     *
     * This keeps read plans intact while reducing write overhead and storage.
     */
    private const REDUNDANT_INDEXES = [
        ['simpanan_multipn', 'simpanan_multipn_posisi_index'],
        ['lw325_ph', 'lw325_ph_periode_index'],
        ['daily_loan_dinamis', 'daily_loan_dinamis_periode_index'],
        ['cognos_recovery', 'cognos_recovery_periode_index'],
        ['cognos_ph', 'cognos_ph_periode_index'],
        ['dashboard_harian_snapshots', 'dashboard_harian_snapshots_snapshot_period_index'],
        ['dashboard_pinjaman_snapshots', 'dashboard_pinjaman_snapshots_periode_index'],
        ['ssa_pinjaman', 'ssa_pinjaman_month_day_year_of_periode_index'],
        ['ssa_simpanan', 'ssa_simpanan_month_day_year_of_posisi_index'],
        ['performance_pis_per_produk', 'performance_pis_per_produk_posisi_index'],
        ['jumlah_merchant_detail', 'jumlah_merchant_detail_posisi_index'],
        ['jumlah_merchant_qris_detail', 'jumlah_merchant_qris_detail_posisi_index'],
        ['casa_brilink_web', 'casa_brilink_web_periode_index'],
        ['casa_brilink_edc', 'casa_brilink_edc_periode_index'],
        ['brilink_web_laporan_summary_transaksi_brilink_web', 'brilink_web_laporan_summary_transaksi_brilink_web_periode_index'],
        ['rka', 'rka_kanca_index'],
        ['bod_boc', 'bod_boc_periode_index'],
        ['merchant_qris', 'merchant_qris_posisi_index'],
        ['merchant_qris_volume', 'merchant_qris_volume_posisi_index'],
    ];

    public function up(): void
    {
        foreach (self::REDUNDANT_INDEXES as [$table, $indexName]) {
            $this->dropIndexIfExists($table, $indexName);
        }
    }

    public function down(): void
    {
        // Intentionally left empty.
        // Recreating these indexes would reintroduce the storage/write overhead
        // that this cleanup migration removes.
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, static function ($tableBlueprint) use ($indexName): void {
            $tableBlueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
