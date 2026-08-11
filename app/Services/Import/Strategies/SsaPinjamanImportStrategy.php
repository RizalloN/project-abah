<?php

namespace App\Services\Import\Strategies;

class SsaPinjamanImportStrategy implements ImportStrategyInterface
{
    private const REQUIRED_COLUMNS = [
        'month_day_year_of_periode',
        'nama_cabang',
        'nama_uker',
        'produk',
        'produk_dashboard',
        'segmen',
        'segmen_lama',
        'segmen_2025',
        'segmen_dashboard',
        'kolektabilitas_one_obligor',
        'flag_restruk',
        'baki_debet',
        'jumlah_debitur_aktif',
        'jumlah_rekening_aktif',
    ];

    public function key(): string
    {
        return 'ssa_pinjaman';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === 'ssa_pinjaman';
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
                'message' => 'Kolom SSA Pinjaman belum lengkap: ' . implode(', ', $missing) . '.',
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
                'month_day_year_of_period', 'tanggal_periode', 'tgl_periode' => 'month_day_year_of_periode',
                'nama_kantor_cabang', 'kantor_cabang', 'kanca' => 'nama_cabang',
                'nama_unit_kerja', 'unit_kerja', 'uker' => 'nama_uker',
                'kolektabilitas', 'kolektibilitas_one_obligor' => 'kolektabilitas_one_obligor',
                'jumlah_debitur', 'jumlah_debitur_aktif_akun' => 'jumlah_debitur_aktif',
                'jumlah_rekening', 'jumlah_rekening_aktif_akun' => 'jumlah_rekening_aktif',
                default => $normalized,
            };
        }, $headers);
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
