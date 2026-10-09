<?php

namespace App\Services\Import\Strategies;

use App\Services\Import\Gi405SingleRowValueNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Gi405SingleRowImportStrategy implements ImportStrategyInterface
{
    private const COLUMN_MAP = [
        'PERIODE' => 'periode',
        'BRANCH' => 'branch',
        'CURRENCY' => 'currency',
        'POSTING_CONTROL' => 'posting_control',
        'ACCOUNT_NUMBER' => 'account_number',
        'C_C' => 'c_c',
        'P_C' => 'p_c',
        'F_C' => 'f_c',
        'DESCRIPTION' => 'description',
        'BEGINING_BALANCE' => 'begining_balance',
        'EQUIVALENTS_IDR' => 'equivalents_idr',
        'EQUIVALENTS_USD' => 'equivalents_usd',
        'TODAY_DEBIT' => 'today_debit',
        'TODAY_CREDIT' => 'today_credit',
        'ENDING_BALANCE' => 'ending_balance',
    ];

    public function key(): string
    {
        return 'gi405_singlerow';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        return strtolower(trim((string) ($tableName ?? $report->table_name ?? ''))) === 'gi405_singlerow';
    }

    public function prepareContext(array $context): array
    {
        if (! Schema::hasTable('referensi_uker')) {
            throw new \RuntimeException('Import GI405 Single Row memerlukan tabel referensi_uker untuk mengisi nama cabang dan unit kerja.');
        }

        $context['gi405_uker_lookup'] = [];
        foreach (DB::table('referensi_uker')->get(['kode_uker', 'nama_cabang', 'nama_uker']) as $reference) {
            $context['gi405_uker_lookup'][(string) $reference->kode_uker] = [
                'nama_cabang' => $reference->nama_cabang,
                'nama_uker' => $reference->nama_uker,
            ];
        }

        $textColumns = array_fill_keys([
            'BRANCH', 'CURRENCY', 'POSTING_CONTROL', 'ACCOUNT_NUMBER', 'C_C', 'P_C', 'F_C', 'DESCRIPTION',
            ...Gi405SingleRowValueNormalizer::DECIMAL_COLUMNS,
        ], true);

        foreach ((array) ($context['header_rules'] ?? []) as $index => $rule) {
            $candidates = array_map(
                static fn ($column): string => strtoupper((string) $column),
                (array) ($rule['db_candidates'] ?? [])
            );

            if (array_intersect_key($textColumns, array_fill_keys($candidates, true)) !== []) {
                $context['header_rules'][$index]['preserve_source_text'] = true;
                $context['header_rules'][$index]['preserve_source_text_exact'] = true;
            }
        }

        $context['unique_id_prefix'] = 'uuid_gi405_singlerow_' . str_replace('.', '', uniqid('', true));
        $context['suffix'] = '';

        return $context;
    }

    public function validateSchema(array $availableColumns): array
    {
        $required = array_merge(
            ['uniqueid_namareport', 'source_periode', 'nama_cabang', 'nama_uker'],
            array_values(self::COLUMN_MAP),
            ['created_at', 'updated_at']
        );
        $lookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $missing = array_values(array_filter(
            $required,
            static fn (string $column): bool => !isset($lookup[strtolower($column)])
        ));

        return $missing === []
            ? ['ok' => true]
            : ['ok' => false, 'message' => 'Schema GI405 Single Row tidak lengkap: ' . implode(', ', $missing)];
    }

    public function transformHeaders(array $headers): array
    {
        $normalizer = app(Gi405SingleRowValueNormalizer::class);

        return array_map(static function ($header) use ($normalizer): string {
            $normalized = $normalizer->normalizeHeader((string) $header);

            return self::COLUMN_MAP[$normalized] ?? strtolower($normalized);
        }, $headers);
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
