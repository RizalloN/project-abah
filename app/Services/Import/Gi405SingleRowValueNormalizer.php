<?php

namespace App\Services\Import;

use App\Support\StrictDateParser;

class Gi405SingleRowValueNormalizer
{
    public const SOURCE_HEADERS = [
        'PERIODE',
        'BRANCH',
        'CURRENCY',
        'POSTING CONTROL',
        'ACCOUNT NUMBER',
        'C/C',
        'P/C',
        'F/C',
        'DESCRIPTION',
        'BEGINING BALANCE',
        'EQUIVALENTS IDR',
        'EQUIVALENTS USD',
        'TODAY DEBIT',
        'TODAY CREDIT',
        'ENDING BALANCE',
    ];

    public const DECIMAL_COLUMNS = [
        'BEGINING_BALANCE',
        'EQUIVALENTS_IDR',
        'EQUIVALENTS_USD',
        'TODAY_DEBIT',
        'TODAY_CREDIT',
        'ENDING_BALANCE',
    ];

    private const IDENTIFIER_COLUMNS = [
        'BRANCH',
        'ACCOUNT_NUMBER',
        'C_C',
        'P_C',
    ];

    public function normalizeForStaging(string $header, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $header = $this->normalizeHeader($header);
        $raw = (string) $value;
        if ($raw === '') {
            return null;
        }
        if ($raw === '\\N') {
            throw new \RuntimeException('Nilai literal `\\N` pada GI405 Single Row tidak dapat dibedakan dari penanda NULL saat bulk load. Import dibatalkan agar data sumber tidak berubah.');
        }

        if ($header === 'PERIODE') {
            if (is_numeric($raw)) {
                $serial = (float) $raw;
                if ($serial > 20000 && $serial < 60000) {
                    return $raw;
                }
            }

            $normalized = StrictDateParser::normalize(trim($raw));
            if ($normalized === null) {
                throw new \RuntimeException("Tanggal GI405 Single Row tidak valid: `{$raw}`.");
            }

            return $raw;
        }

        if (in_array($header, self::DECIMAL_COLUMNS, true)) {
            return $this->normalizeDecimal($raw);
        }

        return $raw;
    }

    public function normalizeDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = (string) $value;
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/', $raw) !== 1) {
            throw new \RuntimeException("Nilai angka GI405 Single Row tidak valid: `{$value}`.");
        }

        return $raw;
    }

    public function normalizeHeader(string $header): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header))), '_');
    }
}
