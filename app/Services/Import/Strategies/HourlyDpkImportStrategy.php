<?php

namespace App\Services\Import\Strategies;

class HourlyDpkImportStrategy implements ImportStrategyInterface
{
    /**
     * Alias yang memang pernah/layak muncul pada keluaran BRI. Pemetaan tetap
     * berbasis nama header; urutan kolom tidak pernah dipakai untuk menebak.
     *
     * @var array<string, string>
     */
    private const HEADER_ALIASES = [
        'POSISI' => 'posisi',
        'POSITION' => 'posisi',
        'MINUTE_OF_POSISI' => 'posisi',
        'MINUTE_OF_POSITION' => 'posisi',
        'MONTH_DAY_YEAR_OF_POSISI' => 'posisi',
        'MONTH_DAY_YEAR_OF_POSITION' => 'posisi',
        'TANGGAL_POSISI' => 'posisi',
        'WAKTU_POSISI' => 'posisi',
        'DATETIME_POSISI' => 'posisi',
        'MBNAME' => 'mbname',
        'MB_NAME' => 'mbname',
        'NAMA_MB' => 'mbname',
        'MAIN_BRANCH_NAME' => 'mbname',
        'BRNAME' => 'brname',
        'BR_NAME' => 'brname',
        'NAMA_BR' => 'brname',
        'BRANCH_NAME' => 'brname',
        'SEGMEN' => 'segmen',
        'SEGMENT' => 'segmen',
        'SEGMENTASI' => 'segmen',
        'SEGMEN2' => 'segmen2',
        'SEGMEN_2' => 'segmen2',
        'SEGMENT2' => 'segmen2',
        'SEGMENT_2' => 'segmen2',
        'SEGMENTASI_2' => 'segmen2',
        'PRODUK' => 'produk',
        'PRODUCT' => 'produk',
        'NAMA_PRODUK' => 'produk',
        'PRODUCT_NAME' => 'produk',
        'SALDO' => 'saldo',
        'BALANCE' => 'saldo',
        'SALDO_IDR' => 'saldo',
        'JUMLAH_SALDO' => 'saldo',
        'NOMINAL_SALDO' => 'saldo',
    ];

    public function key(): string
    {
        return 'hourly_dpk';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === 'hourly_dpk';
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = [
            'uniqueid_namareport',
            'posisi',
            'mbname',
            'brname',
            'segmen2',
            'produk',
            'saldo',
        ];

        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = array_values(array_filter($required, static fn (string $column): bool => !isset($lookup[$column])));

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Schema Hourly DPK tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        $mappedHeaders = array_map(function ($header): string {
            $rawHeader = $this->cleanHeaderLabel($header);
            $normalized = $this->normalizeHeaderKey($rawHeader);

            if (isset(self::HEADER_ALIASES[$normalized])) {
                return self::HEADER_ALIASES[$normalized];
            }

            $fuzzyMatch = $this->resolveUniqueMinorTypo($normalized);

            return $fuzzyMatch ?? $rawHeader;
        }, $headers);

        // Some BRI exports label the only required segment column as SEGMEN/
        // SEGMENTASI. Promote it only when no explicit SEGMEN2 exists, so a
        // seven-column file containing both fields keeps their distinct meaning.
        if (!in_array('segmen2', $mappedHeaders, true) && count(array_keys($mappedHeaders, 'segmen', true)) === 1) {
            $segmentIndex = (int) array_search('segmen', $mappedHeaders, true);
            $mappedHeaders[$segmentIndex] = 'segmen2';
        }

        return $mappedHeaders;
    }

    private function cleanHeaderLabel($header): string
    {
        $label = trim((string) $header);

        return ltrim($label, "\xEF\xBB\xBF\x{FEFF}");
    }

    private function normalizeHeaderKey(string $header): string
    {
        $normalized = preg_replace('/[^A-Z0-9]+/', '_', strtoupper($header));

        return trim((string) $normalized, '_');
    }

    private function resolveUniqueMinorTypo(string $normalized): ?string
    {
        $compact = str_replace('_', '', $normalized);
        if (strlen($compact) < 4) {
            return null;
        }

        $matches = [];
        foreach (self::HEADER_ALIASES as $alias => $canonical) {
            $aliasCompact = str_replace('_', '', $alias);
            if ($this->isSingleEditOrTransposition($compact, $aliasCompact)) {
                $matches[$canonical] = true;
            }
        }

        return count($matches) === 1 ? (string) array_key_first($matches) : null;
    }

    private function isSingleEditOrTransposition(string $value, string $expected): bool
    {
        if (levenshtein($value, $expected) <= 1) {
            return true;
        }

        if (strlen($value) !== strlen($expected)) {
            return false;
        }

        $differences = [];
        for ($index = 0, $length = strlen($value); $index < $length; $index++) {
            if ($value[$index] !== $expected[$index]) {
                $differences[] = $index;
            }
        }

        return count($differences) === 2
            && $differences[1] === $differences[0] + 1
            && $value[$differences[0]] === $expected[$differences[1]]
            && $value[$differences[1]] === $expected[$differences[0]];
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
