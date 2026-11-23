<?php

namespace App\Services\Import\Strategies;

class Gi405RecDhImportStrategy implements ImportStrategyInterface
{
    public const SOURCE_TARGETS = [
        'periode' => 'periode',
        'kode' => 'kode_uker',
        'pendapatan koreksi ppap-dr angsuran ph' => 'pendapatan_koreksi_ppap_dr_angsuran_ph',
    ];

    public function key(): string
    {
        return 'gi405_recovery';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === 'gi405_recovery';
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = [
            'uniqueid_namareport',
            'periode',
            'kode_uker',
            'pendapatan_koreksi_ppap_dr_angsuran_ph',
            'nama_uker',
        ];

        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = [];

        foreach ($required as $column) {
            if (!isset($lookup[$column])) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Schema GI405 Recovery tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        return $headers;
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }
}
