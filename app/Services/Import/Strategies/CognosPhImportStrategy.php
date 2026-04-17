<?php

namespace App\Services\Import\Strategies;

class CognosPhImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'cognos_ph';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));
        return $table === 'cognos_ph';
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
