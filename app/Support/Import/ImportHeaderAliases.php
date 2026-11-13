<?php

namespace App\Support\Import;

/**
 * Deterministic source-header → table-column aliases shared by the import
 * pipeline and the schema drift detector. Keeping one copy guarantees the
 * drift panel reports the same mapping the import actually writes.
 */
final class ImportHeaderAliases
{
    public static function dailyLoanSourceHeaderAliasMap(): array
    {
        return [
            'PERIODE' => 'PERIODE',
            'KODE_KANWIL' => 'KODE_KANWIL1',
            'KODE_KANWIL1' => 'KODE_KANWIL1',
            'KANWIL' => 'KANWIL1',
            'KANWIL1' => 'KANWIL1',
            'KODE_CABANG' => 'KODE_CABANG1',
            'KODE_CABANG1' => 'KODE_CABANG1',
            'CABANG' => 'CABANG1',
            'CABANG1' => 'CABANG1',
            'BRANCH' => 'BRANCH1',
            'BRANCH1' => 'BRANCH1',
            'UNIT' => 'UNIT1',
            'UNIT1' => 'UNIT1',
            'CURTYP' => 'CURTYP',
            'AO_NAME' => 'AO_NAME',
            'AO' => 'AO_NAME',
            'CIFNO' => 'CIFNO',
            'NOMOR_REKENING' => 'NOMOR_REKENING1',
            'NOMOR_REKENING1' => 'NOMOR_REKENING1',
            'STATUS_REKENING' => 'STATUS_REKENING1',
            'STATUS_REKENING1' => 'STATUS_REKENING1',
            'LN_TYPE' => 'LN_TYPE',
            'NAMA_DEBITUR' => 'NAMA_DEBITUR1',
            'NAMA_DEBITUR1' => 'NAMA_DEBITUR1',
            'RATE' => 'RATE',
            'JANGKA_WAKTU' => 'JANGKA_WAKTU1',
            'JANGKA_WAKTU1' => 'JANGKA_WAKTU1',
            'PLAFON' => 'PLAFON',
            'BAKI_DEBET' => 'BAKI_DEBET1',
            'BAKI_DEBET1' => 'BAKI_DEBET1',
            'CKPN' => 'CKPN',
            'NILAI_TERCATAT' => 'NILAI_TERCATAT1',
            'NILAI_TERCATAT1' => 'NILAI_TERCATAT1',
            'KOL_ADK' => 'KOL_ADK1',
            'KOL_ADK1' => 'KOL_ADK1',
            'JML_ANGSURAN' => 'JML_ANGSURAN1',
            'JML_ANGSURAN1' => 'JML_ANGSURAN1',
            'PN_NAME' => 'PN_NAME1',
            'PN_NAME1' => 'PN_NAME1',
            'PN_PEMRAKARSA' => 'PN_PEMRAKARSA1',
            'PN_PEMRAKARSA1' => 'PN_PEMRAKARSA1',
            'PN_REFERRAL' => 'PN_REFERRAL1',
            'PN_REFERRAL1' => 'PN_REFERRAL1',
            'PN_RESTRUK' => 'PN_RESTRUK1',
            'PN_RESTRUK1' => 'PN_RESTRUK1',
            'PN_PEMUTUS' => 'PN_PEMUTUS1',
            'PN_PEMUTUS1' => 'PN_PEMUTUS1',
            'PN_CRM' => 'PN_CRM1',
            'PN_CRM1' => 'PN_CRM1',
            'PN_REFERRAL_NAIK_KELAS' => 'PN_REFERRAL_NAIK_KELAS1',
            'PN_REFERRAL_NAIK_KELAS1' => 'PN_REFERRAL_NAIK_KELAS1',
            'JUMLAH_PN' => 'JUMLAH_PN1',
            'JUMLAH_PN1' => 'JUMLAH_PN1',
            'JUMLAH_PN_ALL' => 'JUMLAH_PN_ALL1',
            'JUMLAH_PN_ALL1' => 'JUMLAH_PN_ALL1',
            'RESTRUK_KE' => 'RESTRUK_KE1',
            'RESTRUK_KE1' => 'RESTRUK_KE1',
            'JENIS_RESTRUK' => 'JENIS_RESTRUK1',
            'JENIS_RESTRUK1' => 'JENIS_RESTRUK1',
            'FLAG_RESTRUK_COVID' => 'FLAG_RESTRUK_COVID1',
            'FLAG_RESTRUK_COVID1' => 'FLAG_RESTRUK_COVID1',
            'FLAG_COMMODITY_CHAIN' => 'FLAG_COMMODITY_CHAIN1',
            'FLAG_COMMODITY_CHAIN1' => 'FLAG_COMMODITY_CHAIN1',
            'FLAG_BRIGUNA_DIGITAL' => 'FLAG_BRIGUNA_DIGITAL1',
            'FLAG_BRIGUNA_DIGITAL1' => 'FLAG_BRIGUNA_DIGITAL1',
            'PMTAMT_BASE' => 'PMTAMT_Base',
            'LAST_DATE_MAINTENANCE_BILLING' => 'LAST_DATE_MAINTENANCE_BILLING',
            'TGL_LAST_MAINTENANCE_BILLING' => 'LAST_DATE_MAINTENANCE_BILLING',
            'NEXT_PMT_DATE' => 'NEXT_PMT_DATE',
            'TGL_NEXT_PMT' => 'NEXT_PMT_DATE',
            'NEXT_PMT_INT_DATE' => 'NEXT_PMT_INT_DATE',
            'TGL_NEXT_PMT_INT' => 'NEXT_PMT_INT_DATE',
            'LBDOTU' => 'LBDOTU',
            'TOTAL_OS_BUNGA_YANG_DITUNDA_DAN_BELUM_DIJADWALKAN' => 'LBDOTU',
            'TEXTBOX20' => 'Textbox20',
            'TEXTBOX21' => 'Textbox21',
            'TAGIHAN_POKOK' => 'BILPRN',
            'TAGIHAN_BUNGA' => 'BILINT',
            'TAGIHAN_DENDA' => 'BILLC',
            'TOTAL_DEFERRED_INTEREST_DITUNDA_DAN_BELUM_DIJADWALKAN' => 'LBDOTU',
        ];
    }

    public static function dailyLoanSourceHeaders(array $headers): array
    {
        $aliasMap = self::dailyLoanSourceHeaderAliasMap();
        $canonicalHeaders = [];
        $matched = 0;
        $normalize = static fn (string $value): string => trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($value))), '_');
        $presentHeaders = array_fill_keys(array_map(static fn ($header): string => $normalize((string) $header), $headers), true);

        foreach (array_values($headers) as $index => $header) {
            $header = trim((string) $header);
            if ($header === '') {
                $canonicalHeaders[$index] = 'COL_' . $index;
                continue;
            }

            $normalizedHeader = $normalize($header);

            // A legacy name (PN_PEMUTUS) only stands in for a column the file
            // lacks. When the file carries both, they are different fields and
            // aliasing would let the legacy column overwrite the real one.
            if (isset($aliasMap[$normalizedHeader])
                && $normalize($aliasMap[$normalizedHeader]) !== $normalizedHeader
                && isset($presentHeaders[$normalize($aliasMap[$normalizedHeader])])) {
                $canonicalHeaders[$index] = $header;
                continue;
            }

            if (isset($aliasMap[$normalizedHeader])) {
                $canonicalHeaders[$index] = $aliasMap[$normalizedHeader];
                $matched++;
                continue;
            }

            $canonicalHeaders[$index] = $header;
        }

        $hasCoreSignature = isset($canonicalHeaders[0], $canonicalHeaders[1], $canonicalHeaders[9], $canonicalHeaders[17])
            && $canonicalHeaders[0] === 'PERIODE'
            && $canonicalHeaders[1] === 'KODE_KANWIL1'
            && $canonicalHeaders[9] === 'CIFNO'
            && $canonicalHeaders[17] === 'BAKI_DEBET1';

        if ($matched >= 12 || $hasCoreSignature) {
            return $canonicalHeaders;
        }

        return array_values($headers);
    }

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
     * SSA Analisis uses a fixed six-column layout even when export labels change.
     * Call only after the source header has been located, never on data rows.
     */
    public static function ssaAlmafactsHeaders(array $headers): array
    {
        if (count($headers) < 6) {
            return $headers;
        }

        foreach (['month_day_year_of_posisi', 'kanca_konsolidasi', 'kode_unit_kerja', 'unit_kerja', 'keterangan', 'saldo'] as $index => $column) {
            $headers[$index] = $column;
        }

        return $headers;
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
