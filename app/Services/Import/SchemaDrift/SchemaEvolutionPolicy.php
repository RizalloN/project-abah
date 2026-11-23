<?php

namespace App\Services\Import\SchemaDrift;

/**
 * Decides, per target table, whether an accepted new source column can really
 * be imported ("evolve") or whether the report has a fixed import layout, in
 * which case drift is only detected and notified ("monitor"). Adding a column
 * that the import pipeline would never fill would be misleading, so a table is
 * only "evolve" once its pipeline was verified to carry new columns end to end.
 */
final class SchemaEvolutionPolicy
{
    public const MODE_EVOLVE = 'evolve';
    public const MODE_MONITOR = 'monitor';

    /**
     * Pipelines with a dedicated controller or processor that still selects a
     * fixed column layout. Everything else goes through the generic importer,
     * which maps source headers to table columns by name.
     */
    private const FIXED_LAYOUT_TABLES = [
        'monitoring_realisasi_ksbk',
        'evaluasi_aktivitas_kunjungan_pemasar',
        'pipeline_prewash',
        'pipeline_prewash_boh',
        'monitoring_tl_pipeline_mbm',
        'cognos_ph',
        'cognos_recovery',
        'mau_brimo_r_all',
        'input_rekanan',
        'bod_boc',
        'brilink_web_laporan_summary_transaksi_brilink_web',
        'cras',
        'casa_brilink_web',
        'casa_brilink_edc',
        'simpanan_multipn',
        'gi405_recovery',
        'gi405_rec_dh',
        'gi405_singlerow',
        'hourly_dpk',
        'dly_kap_resegmentasi',
        'l1133',
    ];

    public function modeFor(string $table): string
    {
        return in_array(strtolower(trim($table)), self::FIXED_LAYOUT_TABLES, true)
            ? self::MODE_MONITOR
            : self::MODE_EVOLVE;
    }

    public function canEvolve(string $table): bool
    {
        return $this->modeFor($table) === self::MODE_EVOLVE;
    }

    public function canStoreNewColumnType(string $table, string $type): bool
    {
        if ($table === 'performance_pis_per_produk') {
            return $type === 'TEXT';
        }

        // Both dedicated date parsers retain the date only. PH also normalizes
        // decimals to two places before the value reaches the database.
        if (in_array($table, ['daily_loan_dinamis', 'lw325_ph'], true) && $type === 'DATETIME') {
            return false;
        }

        return $table !== 'lw325_ph' || preg_match('/^DECIMAL\(\d+,([3-9]|\d{2,})\)$/', $type) !== 1;
    }
}
