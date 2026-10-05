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
        if (trim($raw) === '') {
            return null;
        }

        if ($header === 'PERIODE') {
            if (is_numeric($raw)) {
                $serial = (float) $raw;
                if ($serial > 20000 && $serial < 60000) {
                    return gmdate('Y-m-d', (int) round(($serial - 25569) * 86400));
                }
            }

            $normalized = StrictDateParser::normalize(trim($raw));
            if ($normalized === null) {
                throw new \RuntimeException("Tanggal GI405 Single Row tidak valid: `{$raw}`.");
            }

            return $normalized;
        }

        if (in_array($header, self::DECIMAL_COLUMNS, true)) {
            return $this->normalizeDecimal($raw, 2);
        }

        if (in_array($header, self::IDENTIFIER_COLUMNS, true) && is_numeric(trim($raw))) {
            return $this->normalizeDecimal($raw, 0);
        }

        return $raw;
    }

    public function normalizeDecimal(mixed $value, int $scale = 2): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '\\N') {
            return null;
        }

        $negative = false;
        if (preg_match('/^\((.*)\)$/', $raw, $matches) === 1) {
            $raw = trim((string) ($matches[1] ?? ''));
            $negative = true;
        }
        if (str_ends_with($raw, '-')) {
            $raw = rtrim(substr($raw, 0, -1));
            $negative = true;
        }

        $raw = preg_replace('/\s+/u', '', $raw) ?? '';
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            $parts = explode(',', $raw);
            $last = (string) end($parts);
            $raw = count($parts) > 2 || strlen($last) === 3
                ? str_replace(',', '', $raw)
                : str_replace(',', '.', $raw);
        }

        if (preg_match('/^([+-]?)(\d+)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $raw, $matches) !== 1) {
            throw new \RuntimeException("Nilai angka GI405 Single Row tidak valid: `{$value}`.");
        }

        $negative = $negative || ($matches[1] ?? '') === '-';
        $integer = ltrim((string) ($matches[2] ?? '0'), '0');
        $fraction = (string) ($matches[3] ?? '');
        $exponent = (int) ($matches[4] ?? 0);
        $digits = ($integer === '' ? '0' : $integer) . $fraction;
        $decimalPosition = strlen($integer === '' ? '0' : $integer) + $exponent;

        if ($decimalPosition <= 0) {
            $digits = str_repeat('0', -$decimalPosition) . $digits;
            $decimalPosition = 0;
        } elseif ($decimalPosition >= strlen($digits)) {
            $digits .= str_repeat('0', $decimalPosition - strlen($digits));
        }

        $whole = $decimalPosition === 0 ? '0' : substr($digits, 0, $decimalPosition);
        $decimal = substr($digits, $decimalPosition);
        $discarded = substr($decimal, $scale);
        if ($discarded !== '' && trim($discarded, '0') !== '') {
            throw new \RuntimeException(
                "Nilai angka GI405 Single Row `{$value}` memiliki presisi melebihi {$scale} desimal. Import dibatalkan agar data tidak dibulatkan."
            );
        }

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $decimal = str_pad(substr($decimal, 0, $scale), $scale, '0');
        $isZero = $whole === '0' && trim($decimal, '0') === '';
        $prefix = $negative && !$isZero ? '-' : '';

        return $scale > 0
            ? $prefix . $whole . '.' . $decimal
            : $prefix . $whole;
    }

    public function normalizeHeader(string $header): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($header))), '_');
    }
}
