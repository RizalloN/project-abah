<?php

namespace App\Services\Import\Strategies;

class SimpananMultiPnImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'simpanan_multipn';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));
        return $table === 'simpanan_multipn';
    }

    public function prepareContext(array $context): array
    {
        $context['ignore_row_number_headers'] = true;

        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        return array_values(array_filter($headers, static function ($header): bool {
            return !preg_match('/^(?:no|row|nomor)$/i', trim((string) $header));
        }));
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }
}
