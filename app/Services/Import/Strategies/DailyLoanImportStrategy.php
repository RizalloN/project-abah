<?php

namespace App\Services\Import\Strategies;

class DailyLoanImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'daily_loan_dinamis';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));
        $name = strtolower(trim((string) ($report->nama_report ?? '')));

        return $table === 'daily_loan_dinamis' || str_contains($name, 'daily loan');
    }

    public function prepareContext(array $context): array
    {
        $context['required_columns'] = ['baki_debet', 'baki_debet1'];

        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $available = array_map('strtolower', $availableColumns);
        $hasAnyRequired = in_array('baki_debet', $available, true)
            || in_array('baki_debet1', $available, true);

        return $hasAnyRequired
            ? ['ok' => true]
            : [
                'ok' => false,
                'message' => 'Kolom Daily Loan untuk baki debet belum tersedia di tabel `daily_loan_dinamis`.',
            ];
    }

    public function transformHeaders(array $headers): array
    {
        return $headers;
    }

    public function importMode(array $context = []): string
    {
        // Daily Loan is locked to full-import mode in this project.
        // Force direct LOAD DATA path to avoid slow staging + INSERT SELECT fallback.
        return 'bulk_csv_direct';
    }
}
