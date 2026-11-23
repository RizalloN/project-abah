<?php

namespace App\Services\Import\Strategies;

class PerformancePisImportStrategy implements ImportStrategyInterface
{
    public const TARGET_COLUMNS = [
        'posisi', 'kode_kanwil', 'kanwil', 'kode_kanca', 'kanca', 'kode_uker', 'uker',
        'corporate_code', 'nama_perusahaan', 'jenis_mitra', 'jenis_perusahaan', 'tipe_produk',
        'nomor_rekening', 'nama_rekening', 'saldo_britama_kerjasama', 'tanggal_pembuatan_rekening',
        'pn_rm_dana_brinets', 'pn_rm_dana_pis2', 'nomor_hp', 'email', 'flag_briguna', 'flag_cc',
    ];

    public const HEADER_ALIASES = [
        'product_type' => 'tipe_produk',
        'alamat_email' => 'email',
        'relasi_briguna_yes_no' => 'flag_briguna',
        'relasi_kartu_kredit_yes_no' => 'flag_cc',
    ];

    public static function normalizeHeader(mixed $header): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $header));
        $key = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $value)), '_');

        return self::HEADER_ALIASES[$key] ?? $key;
    }

    public static function sourceTargets(array $headers, array $writableColumns, array $aliases): array
    {
        $writable = array_fill_keys($writableColumns, true);
        $targets = [];
        foreach ($headers as $index => $header) {
            $key = self::normalizeHeader($header);
            $target = $aliases[$key] ?? $key;
            if ($target !== 'posisi' && isset($writable[$target])) {
                $targets[$index] = $target;
            }
        }

        return $targets;
    }

    public function key(): string
    {
        return 'performance_pis_per_produk';
    }

    public function supports(?object $report, ?string $tableName = null): bool
    {
        $table = strtolower(trim((string) ($tableName ?? $report->table_name ?? '')));
        return $table === 'performance_pis_per_produk';
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
        return array_map(static function ($header): string {
            $raw = trim((string) $header);
            $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            if ($raw === '' || preg_match('/^col_\d+$/i', $raw) === 1) {
                return $raw;
            }

            $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $raw));
            $normalized = trim($normalized, '_');

            return self::HEADER_ALIASES[$normalized] ?? $raw;
        }, $headers);
    }

    public function importMode(array $context = []): string
    {
        return 'bulk_csv_staging';
    }
}
