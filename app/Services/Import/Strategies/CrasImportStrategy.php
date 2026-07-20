<?php

namespace App\Services\Import\Strategies;

class CrasImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'cras';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        return strtolower(trim((string) ($tableName ?? $report->table_name ?? ''))) === 'cras';
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = ['cras_uuid', 'cras_periode', 'month_day_year_of_posisi', 'ket_kanca'];
        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = array_values(array_filter(
            $required,
            static fn (string $column): bool => !isset($lookup[$column])
        ));

        return $missing === []
            ? ['ok' => true]
            : ['ok' => false, 'message' => 'Schema CRAS tidak lengkap: ' . implode(', ', $missing)];
    }

    public function transformHeaders(array $headers): array
    {
        return $headers;
    }

    public function importMode(array $context = []): string
    {
        return 'cras_exact_bulk';
    }
}
