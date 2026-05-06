<?php

namespace App\Services\Import\Strategies;

class Lw321PnImportStrategy implements ImportStrategyInterface
{
    public const TABLE = 'lw321pn';

    private const REQUIRED_COLUMNS = [
        'uniqueid_namareport',
        'periode',
        'kode_kanwil',
        'kanwil',
        'kode_kanca',
        'kanca',
        'kode_uker',
        'uker',
        'no_rekening',
        'nama_debitur',
        'balance_dalam_idr',
    ];

    public function key(): string
    {
        return self::TABLE;
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === self::TABLE;
    }

    public function prepareContext(array $context): array
    {
        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (!isset($lookup[$column])) {
                $missing[] = $column;
            }
        }

        if ($missing !== []) {
            return [
                'ok' => false,
                'message' => 'Schema LW321PN tidak lengkap. Kolom yang hilang: ' . implode(', ', $missing),
            ];
        }

        return ['ok' => true];
    }

    public function transformHeaders(array $headers): array
    {
        return array_map(
            static fn ($header): string => trim((string) preg_replace('/^\xEF\xBB\xBF/u', '', (string) $header)),
            $headers
        );
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_filtered';
    }
}
