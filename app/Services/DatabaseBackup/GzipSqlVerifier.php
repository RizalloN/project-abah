<?php

namespace App\Services\DatabaseBackup;

use RuntimeException;

/**
 * Re-reads a written gzip SQL part and proves it matches the stream that
 * mysqldump produced, so a backup part is never published unverified.
 */
class GzipSqlVerifier
{
    private const READ_BYTES = 8 * 1024 * 1024;

    private const PREFIX_BYTES = 65536;

    /**
     * @return array{uncompressed_bytes: int, uncompressed_sha256: string, compressed_sha256: string}
     */
    public function verify(string $path, int $expectedBytes, string $expectedHash): array
    {
        if (! is_file($path) || (int) filesize($path) <= 0) {
            throw new RuntimeException('File gzip backup kosong atau tidak ditemukan: '.basename($path));
        }

        $handle = @gzopen($path, 'rb');
        if (! is_resource($handle)) {
            throw new RuntimeException('File gzip backup tidak dapat dibuka: '.basename($path));
        }

        $hashContext = hash_init('sha256');
        $bytes = 0;
        $prefix = '';

        try {
            while (! gzeof($handle)) {
                $chunk = @gzread($handle, self::READ_BYTES);
                if ($chunk === false) {
                    throw new RuntimeException('Integritas gzip gagal diverifikasi: '.basename($path));
                }
                if ($chunk === '') {
                    continue;
                }

                $bytes += strlen($chunk);
                hash_update($hashContext, $chunk);
                if (strlen($prefix) < self::PREFIX_BYTES) {
                    $prefix .= substr($chunk, 0, self::PREFIX_BYTES - strlen($prefix));
                }
            }
        } finally {
            gzclose($handle);
        }

        $hash = hash_final($hashContext);
        if ($bytes <= 0 || $bytes !== $expectedBytes || ! hash_equals($expectedHash, $hash)) {
            throw new RuntimeException('Ukuran atau checksum part backup tidak cocok: '.basename($path));
        }

        $this->assertSqlPrefixIsValid($prefix);

        $compressedHash = hash_file('sha256', $path);
        if (! is_string($compressedHash) || $compressedHash === '') {
            throw new RuntimeException('Hash file terkompresi gagal dihitung: '.basename($path));
        }

        return [
            'uncompressed_bytes' => $bytes,
            'uncompressed_sha256' => $hash,
            'compressed_sha256' => $compressedHash,
        ];
    }

    public function assertSqlPrefixIsValid(string $prefix): void
    {
        $prefix = ltrim(str_starts_with($prefix, "\xEF\xBB\xBF") ? substr($prefix, 3) : $prefix);
        if ($prefix === '' || preg_match('/^(<!DOCTYPE\s+html|<html\b)/i', $prefix) === 1) {
            throw new RuntimeException('Output mysqldump bukan SQL yang valid.');
        }

        $looksLikeSql = preg_match(
            '/(\/\*(?:M)?![0-9]{5,6}|CREATE\s+(?:DATABASE|TABLE|VIEW|TRIGGER)|'
            .'DROP\s+TABLE|INSERT\s+INTO|SET\s+NAMES|USE\s+`)/i',
            $prefix
        ) === 1;

        if (! $looksLikeSql) {
            throw new RuntimeException('Output mysqldump tidak memuat struktur SQL yang dikenali.');
        }
    }
}
