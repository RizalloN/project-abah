<?php

namespace App\Services\Import\Strategies;

use App\Services\Import\DlyKapResegmentasiCsvImporter;

class DlyKapResegmentasiImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'dly_kap_resegmentasi';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === DlyKapResegmentasiCsvImporter::TABLE;
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = array_merge(['created_at', 'updated_at'], DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS);
        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = [];

        foreach ($required as $column) {
            if (!isset($lookup[strtolower($column)])) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Schema DLY KAP Resegmentasi tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        return DlyKapResegmentasiCsvImporter::NORMALIZED_HEADERS;
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }
}
