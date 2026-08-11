<?php

namespace App\Services\Import\Strategies;

class SsaSimpananImportStrategy implements ImportStrategyInterface
{
    private const REQUIRED_COLUMNS = [
        'month_day_year_of_posisi',
        'nama_cabang',
        'nama_uker',
        'produk',
        'segmentasi',
        'segmen_kategorisasi_bisnis',
        'saldo',
    ];

    public function key(): string
    {
        return 'ssa_simpanan';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === 'ssa_simpanan';
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $available = array_map(static fn ($column): string => strtolower(trim((string) $column)), $availableColumns);
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $available));

        return $missing === []
            ? ['ok' => true]
            : [
                'ok' => false,
                'message' => 'Kolom SSA Simpanan belum lengkap: ' . implode(', ', $missing) . '.',
            ];
    }

    public function transformHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $header = trim((string) $header);
            if (preg_match('/^COL_\\d+$/i', $header)) {
                return strtoupper($header);
            }

            $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $header));
            $normalized = trim($normalized, '_');

            return match ($normalized) {
                'month_day_year_of_position', 'tanggal_posisi', 'tgl_posisi' => 'month_day_year_of_posisi',
                'nama_kantor_cabang', 'kantor_cabang', 'kanca' => 'nama_cabang',
                'nama_unit_kerja', 'unit_kerja', 'uker' => 'nama_uker',
                'segmen_bisnis', 'segmentasi_bisnis', 'segmen_kategorisasi' => 'segmen_kategorisasi_bisnis',
                default => $normalized,
            };
        }, $headers);
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
