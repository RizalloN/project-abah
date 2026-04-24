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
        return array_values(array_map(static function ($header, int $index): string {
            $headerName = trim((string) $header);

            if (preg_match('/^(?:no|row|nomor)$/i', $headerName) === 1) {
                return 'COL_' . $index;
            }

            return $headerName !== '' ? $headerName : 'COL_' . $index;
        }, $headers, array_keys($headers)));
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }
}
