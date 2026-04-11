<?php

namespace App\Services\Import\Strategies;

class GenericCsvImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'generic_csv';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        return true;
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
