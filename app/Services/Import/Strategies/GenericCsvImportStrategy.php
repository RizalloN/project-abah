<?php

namespace App\Services\Import\Strategies;

class GenericCsvImportStrategy implements ImportStrategyInterface
{
    private const SPECIALIZED_TABLES = [
        'daily_loan_dinamis',
        'simpanan_multipn',
        'gi405_rec_dh',
        'ssa_pinjaman',
        'ssa_simpanan',
        'lw325_ph',
        'cognos_ph',
        'cognos_recovery',
        'performance_pis_per_produk',
        'rka',
        'brihc',
        'wilayah_mbm',
    ];

    public function key(): string
    {
        return 'generic_csv';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));

        return $table === '' || !in_array($table, self::SPECIALIZED_TABLES, true);
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
