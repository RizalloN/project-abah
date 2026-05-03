<?php

namespace App\Services\Import\Strategies;

use App\Services\Import\L1133CsvImporter;

class L1133ImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return L1133CsvImporter::TABLE;
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === L1133CsvImporter::TABLE;
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = array_merge(['uniqueid_namareport', 'created_at', 'updated_at'], L1133CsvImporter::NORMALIZED_HEADERS);
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
                'message' => 'Schema L1133 tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        return L1133CsvImporter::NORMALIZED_HEADERS;
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
