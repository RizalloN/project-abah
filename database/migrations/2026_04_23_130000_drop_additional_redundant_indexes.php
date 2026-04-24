<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These indexes are redundant because a wider composite or unique index
     * already covers the same left-most column sequence.
     */
    private const REDUNDANT_INDEXES = [
        ['dashboard_pinjaman_chart_periodik_snapshots', 'dashboard_pinjaman_chart_periodik_snapshots_periode_index'],
        ['input_rekanan', 'input_rekanan_periode_index'],
        ['performance_kurkecil_mikro', 'performance_kurkecil_mikro_tanggal_bl_index'],
        ['performance_pis_per_produk', 'idx_pis_posisi_kanca'],
        ['rasio_casa_debitur_snapshots', 'rasio_casa_debitur_snapshots_loan_period_index'],
        ['rasio_casa_debitur_uker_snapshots', 'rasio_casa_debitur_uker_snapshots_loan_period_index'],
        ['rekening_dormant_snapshots', 'rekening_dormant_snapshots_posisi_index'],
        ['sv_merchant', 'sv_merchant_posisi_index'],
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
