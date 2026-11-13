<?php

namespace App\Services\Import\Strategies;

use App\Services\Import\EvaluasiKunjunganSourceService;

class EvaluasiKunjunganImportStrategy implements ImportStrategyInterface
{
    public function key(): string
    {
        return 'evaluasi_kunjungan_exact';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        return strtolower(trim((string) ($tableName ?? $report->table_name ?? ''))) === EvaluasiKunjunganSourceService::TABLE;
    }

    public function validateSchema(array $availableColumns): array
    {
        $missing = array_diff([...EvaluasiKunjunganSourceService::IMPORT_COLUMNS, 'uniqueid_namareport', 'created_at', 'updated_at'], array_map('strtolower', $availableColumns));

        return $missing === [] ? ['ok' => true] : ['ok' => false, 'message' => 'Schema Kunjungan Pemasar tidak lengkap: '.implode(', ', $missing)];
    }

    public function prepareContext(array $context): array
    {
        foreach ($context['header_rules'] as &$rule) {
            $rule['preserve_source_text'] = true;
            $rule['preserve_source_text_exact'] = true;
            $rule['is_date'] = $rule['is_decimal'] = $rule['is_integer'] = false;
        }
        unset($rule);

        return $context;
    }

    public function transformHeaders(array $headers): array
    {
        return $headers;
    }

    public function importMode(array $context = []): string
    {
        return 'evaluasi_kunjungan_exact';
    }
}
