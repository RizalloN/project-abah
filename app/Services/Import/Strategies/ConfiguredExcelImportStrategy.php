<?php

namespace App\Services\Import\Strategies;

class ConfiguredExcelImportStrategy implements ImportStrategyInterface
{
    private const TABLES = [
        'rka',
        'brihc',
        'wilayah_mbm',
    ];

    public function key(): string
    {
        return 'configured_excel';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return in_array($table, self::TABLES, true);
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
        return $headers;
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
