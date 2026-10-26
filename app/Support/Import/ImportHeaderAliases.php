<?php

namespace App\Support\Import;

/**
 * Deterministic source-header → table-column aliases shared by the import
 * pipeline and the schema drift detector. Keeping one copy guarantees the
 * drift panel reports the same mapping the import actually writes.
 */
final class ImportHeaderAliases
{
    /**
     * Upper snake header => column (ImportExcelController::normalizeImportColumnName).
     */
    public const IMPORT_COLUMN = [
        'TEXTBOX20' => 'total_kewajiban',
        'TOTAL_KEWAJIBAN' => 'total_kewajiban',
        'TEXTBOX21' => 'os_idr',
        'OS_IDR' => 'os_idr',
        'MONTH_DAY_YEAR_OF_POSISI' => 'posisi',
        'MONTH_DAY_YEAR_OF_PERIODE' => 'month_day_year_of_periode',
        'NOREKENING' => 'no_rekening',
        'NOMORREKENING' => 'no_rekening',
        'NOMOR_REKENING' => 'no_rekening',
        'NO_REKENING' => 'no_rekening',
        'CIF_NO' => 'cifno',
        'CIF_NUMBER' => 'cifno',
    ];

    /**
     * Lower snake header => column (ImportExcelController::getHeaderDatabaseCandidates).
     */
    public const CANDIDATE = [
        'textbox20' => 'total_kewajiban',
        'textbox21' => 'os_idr',
        'c_c' => 'c_c',
        'c' => 'c_c',
        'p_c' => 'p_c',
        'p' => 'p_c',
        'f_c' => 'f_c',
        'f' => 'f_c',
        'month_day_year_of_posisi' => 'month_day_year_of_posisi',
        'periode' => 'periode',
        'kode' => 'kode_uker',
        'segmen_dashboard' => 'segmen_dashboard',
        'produk_dashboard' => 'produk_dashboard',
        'cbal_base' => 'balance_dalam_idr',
        'cbal' => 'balance_dalam_idr',
        'orgamt_base' => 'plafon_dalam_idr',
        'orgamt' => 'plafon_dalam_idr',
        'kolek_lancar' => 'kolektibilitas_lancar',
        'kolek_dpk' => 'kolektibilitas_dpk',
        'kolek_kurang_lancar' => 'kolektibilitas_kurang_lancar',
        'kolek_diragukan' => 'kolektibilitas_diragukan',
        'kolek_macet' => 'kolektibilitas_macet',
        'pn_referal' => 'pn_referral',
    ];

    public static function importColumnName(string $header): string
    {
        $normalized = trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header))), '_');

        return self::IMPORT_COLUMN[$normalized] ?? strtolower($normalized);
    }

    /**
     * The deterministic candidates the import tries, in its order; fuzzy
     * family aliases are deliberately not part of this list.
     *
     * @return array<int, string>
     */
    public static function candidates(string $header): array
    {
        $exact = strtolower(trim($header));
        $raw = trim((string) preg_replace('/[^a-z0-9]+/', '_', $exact), '_');
        $candidates = [];

        if ($exact !== '') {
            $candidates[] = $exact;
        }
        if ($raw !== '') {
            $candidates[] = $raw;
        }

        $candidates[] = self::importColumnName($header);

        $rawWithoutNumericSuffix = trim((string) preg_replace('/(?:_)?\d+$/', '', $raw), '_');
        if ($rawWithoutNumericSuffix !== '' && $rawWithoutNumericSuffix !== $raw) {
            $candidates[] = $rawWithoutNumericSuffix;
        }

        if (isset(self::CANDIDATE[$raw])) {
            $candidates[] = self::CANDIDATE[$raw];
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $value): bool => $value !== '')));
    }
}
