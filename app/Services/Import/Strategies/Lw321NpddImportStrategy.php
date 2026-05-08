<?php

namespace App\Services\Import\Strategies;

class Lw321NpddImportStrategy implements ImportStrategyInterface
{
    public const TABLE = 'lw321_npdd';

    private const REQUIRED_COLUMNS = [
        'uniqueid_namareport',
        'periode',
        'billing',
        'kanca',
        'bc',
        'uker',
        'no_rekening',
        'nama_debitur',
        'npdd',
        'npdd_update',
        'os',
        'now_t_total',
    ];

    public function key(): string
    {
        return self::TABLE;
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === self::TABLE;
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!isset($lookup[$column])) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Schema LW321 NPDD tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        $normalized = array_map(
            static fn ($header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/u', '', (string) $header)),
            $headers
        );

        $tailHeaders = array_slice($normalized, 21, 7);
        $tailHeaderKeys = array_map(static fn (string $header): string => strtoupper(trim($header)), $tailHeaders);
        $tailHasParentPositionHeader = isset($tailHeaders[0])
            && $this->looksLikePositionGroupHeader((string) $tailHeaders[0]);
        $tailHasPlaceholderChildren = array_slice($tailHeaderKeys, 1) === ['COL_22', 'COL_23', 'COL_24', 'COL_25', 'COL_26', 'COL_27'];

        if (count($normalized) >= 28 || ($tailHasParentPositionHeader && $tailHasPlaceholderChildren)) {
            foreach (['now_kol', 'now_detail', 'now_os', 'now_t_pokok', 'now_t_bunga', 'now_t_total', 'ptp'] as $offset => $header) {
                $normalized[21 + $offset] = $header;
            }
        }

        $aliases = [
            'ref_kol' => 'now_kol',
            'ref_detail' => 'now_detail',
            'ref_detai' => 'now_detail',
            'ref_os' => 'now_os',
            't_pokok' => 'now_t_pokok',
            't_bunga' => 'now_t_bunga',
            't_total' => 'now_t_total',
        ];

        return array_map(
            static fn (string $header): string => $aliases[strtolower($header)] ?? $header,
            $normalized
        );
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }

    private function looksLikePositionGroupHeader(string $header): bool
    {
        $value = strtoupper(trim($header));
        if ($value === '' || $value === '#REF!') {
            return $value === '#REF!';
        }

        if (str_starts_with($value, 'POSISI') || str_starts_with($value, 'POSITION')) {
            return true;
        }

        $monthPattern = 'JAN|JANUARI|JANUARY|FEB|FEBRUARI|FEBRUARY|MAR|MARET|MARCH|APR|APRIL|MEI|MAY|JUN|JUNI|JUNE|JUL|JULI|JULY|AGU|AGUSTUS|AUG|AUGUST|SEP|SEPT|SEPTEMBER|OKT|OKTOBER|OCT|OCTOBER|NOV|NOVEMBER|DES|DESEMBER|DEC|DECEMBER';

        return preg_match('/^\d{1,2}\s+(' . $monthPattern . ')\s+\d{2,4}$/', $value) === 1
            || preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}$/', $value) === 1;
    }
}
