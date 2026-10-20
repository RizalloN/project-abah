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
        'cognos_ph',
        'cognos_recovery',
        'performance_pis_per_produk',
        'cras',
        'casa_brilink_web',
        'casa_brilink_edc',
        'daily_loan_dinamis',
        'simpanan_multipn',
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
}
