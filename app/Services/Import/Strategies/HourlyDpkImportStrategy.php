<?php

namespace App\Services\Import\Strategies;

class HourlyDpkImportStrategy implements ImportStrategyInterface
{
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
            'segmen',
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
        return array_map(function ($header): string {
            $normalized = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim((string) $header)));
            $normalized = trim((string) $normalized, '_');

            return match ($normalized) {
                'MONTH_DAY_YEAR_OF_POSISI', 'POSISI' => 'posisi',
                'MBNAME' => 'mbname',
                'BRNAME' => 'brname',
                'SEGMEN', 'SEGMENTASI', 'SEGMEN2' => 'segmen',
                'PRODUK' => 'produk',
                'SALDO' => 'saldo',
                default => (string) $header,
            };
        }, $headers);
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
