<?php

namespace App\Services\Import\Strategies;

class Lw325PhImportStrategy implements ImportStrategyInterface
{
    /**
     * Source label aliases of the LW325 Nominatif PH export. Shared by the
     * dedicated import controller and the schema drift detector so both read
     * the same canonical column names.
     */
    public const HEADER_ALIASES = [
        'no' => 'textbox3',
        'nomor_rekening' => 'acctno',
        'nomor_rekening_1' => 'acctno',
        'nomor_rekening1' => 'acctno',
        'segmen' => 'segmen_dashboard',
        'deskripsi_segmen' => 'description',
        'produk' => 'produk_dashboard',
        'currency' => 'curtyp',
        'sisa_awal_ph_pokok' => 'saldo_pertama_ph_pokok',
        'sisa_awal_ph_bunga' => 'saldo_pertama_ph_bunga',
        'sisa_akhir_ph_pokok' => 'pokok',
        'sisa_akhir_ph_bunga' => 'bunga',
        'kumulatif_angsuran_pokok' => 'angpok',
        'kumulatif_angsuran_bunga' => 'angbung',
        'sisa_pokok' => 'sisapok',
        'sisa_bunga' => 'sisabun',
        'alih_tagih_asuransi' => 'clmamt1',
        'saldo_tagihan_alih_tagih_asuransi' => 'clmapr1',
        'total_kewajiban' => 'os_penuh_berjalan1',
        'kecamatan_tempat_tinggal' => 'kecamatan_t_tinggal',
        'kelurahan_tempat_tinggal' => 'kelurahan_t_tinggal',
        'kodepos_tempat_tinggal' => 'kodepos_t_tinggal',
        'kecamatan_tempat_usaha' => 'kecamatan_t_usaha',
        'kelurahan_tempat_usaha' => 'kelurahan_t_usaha',
        'kodepos_tempat_usaha' => 'kodepos_t_usaha',
        'pn_pengelola_2' => 'pn_pengelola2',
        'pn_crr' => 'pn_crr1',
        'pn_jumlah' => 'jumlah_pn',
        'deffered_bunga_cutoff_ph' => 'deffered_bunga_ph',
        'sai_tunggakan_cutoff_ph' => 'sai_tunggakan_ph',
        'sai_deffered_cutoff_ph' => 'sai_deffered_ph',
    ];

    public const ROW_NUMBER_HEADERS = ['textbox3', 'no', 'row_num', 'nomor_baris', 'rownumber'];

    public function key(): string
    {
        return 'lw325_ph';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));
        return $table === 'lw325_ph';
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        $normalizedHeaders = [];
        $cifOccurrence = 0;

        foreach (array_values($headers) as $index => $header) {
            $label = trim((string) $header);
            if ($label === '') {
                $normalizedHeaders[] = 'col_' . $index;
                continue;
            }

            $normalized = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label)), '_');

            if (in_array($normalized, ['cif', 'cif_1', 'cif1'], true)) {
                $cifOccurrence++;
                $normalizedHeaders[] = $cifOccurrence === 1 ? 'cif1' : 'cif';
                continue;
            }

            $normalizedHeaders[] = self::HEADER_ALIASES[$normalized] ?? $normalized;
        }

        return $normalizedHeaders;
    }

    public function importMode(array $context = []): string
    {
        return empty($context['active_filters']) ? 'bulk_csv_direct' : 'bulk_csv_filtered';
    }
}
