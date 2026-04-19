<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class StrictDateParser
{
    private const INDONESIAN_MONTHS = [
        'januari' => 'January',
        'februari' => 'February',
        'maret' => 'March',
        'april' => 'April',
        'mei' => 'May',
        'juni' => 'June',
        'juli' => 'July',
        'agustus' => 'August',
        'september' => 'September',
        'oktober' => 'October',
        'november' => 'November',
        'desember' => 'December',
    ];

    /**
     * Parse common import date inputs into Y-m-d without relying on Carbon::parse
     * for numeric-only or slash-delimited ambiguous values.
     */
    public static function normalize(?string $value, array $extraFormats = []): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $value = self::normalizeLocaleDateText($value);

        if (preg_match('/^\d{8}$/', $value) === 1) {
            foreach (['Ymd', 'dmY', 'mdY'] as $format) {
                $normalized = self::tryFormat($value, $format);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 20000 && $serial <= 80000) {
                try {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject($serial))->format('Y-m-d');
                } catch (\Throwable) {
                    // fall through
                }
            }
        }

        $unambiguousPatterns = [
            '/^\d{4}-\d{2}-\d{2}$/' => 'Y-m-d',
            '/^\d{4}\/\d{2}\/\d{2}$/' => 'Y/m/d',
            '/^\d{2}-\d{2}-\d{4}$/' => 'd-m-Y',
            '/^\d{2}\/\d{2}\/\d{4}$/' => 'd/m/Y',
            '/^\d{2}-\d{2}-\d{2}$/' => 'd-m-y',
            '/^\d{2}\/\d{2}\/\d{2}$/' => 'd/m/y',
            '/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/' => 'Y-m-d H:i:s',
            '/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/' => 'Y-m-d H:i',
            '/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2}$/' => 'd/m/Y H:i:s',
            '/^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}$/' => 'd/m/Y H:i',
            '/^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2}$/' => 'd-m-Y H:i:s',
            '/^\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}$/' => 'd-m-Y H:i',
        ];

        foreach ($unambiguousPatterns as $pattern => $format) {
            if (preg_match($pattern, $value) !== 1) {
                continue;
            }

            $normalized = self::tryFormat($value, $format);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+.*)?$/', $value, $matches) === 1) {
            if ((int) $matches[1] > 12) {
                return self::tryFormat($value, str_contains($value, ':') ? 'd/m/Y H:i:s' : 'd/m/Y')
                    ?? self::tryFormat($value, str_contains($value, ':') ? 'd/m/Y H:i' : 'd/m/Y');
            }

            if ((int) $matches[2] > 12) {
                return self::tryFormat($value, str_contains($value, ':') ? 'm/d/Y H:i:s' : 'm/d/Y')
                    ?? self::tryFormat($value, str_contains($value, ':') ? 'm/d/Y H:i' : 'm/d/Y');
            }

            return self::tryFormat($value, str_contains($value, ':') ? 'd/m/Y H:i:s' : 'd/m/Y')
                ?? self::tryFormat($value, str_contains($value, ':') ? 'd/m/Y H:i' : 'd/m/Y');
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})(?:\s+.*)?$/', $value, $matches) === 1) {
            if ((int) $matches[1] > 12) {
                return self::tryFormat($value, str_contains($value, ':') ? 'd-m-Y H:i:s' : 'd-m-Y')
                    ?? self::tryFormat($value, str_contains($value, ':') ? 'd-m-Y H:i' : 'd-m-Y');
            }

            if ((int) $matches[2] > 12) {
                return self::tryFormat($value, str_contains($value, ':') ? 'm-d-Y H:i:s' : 'm-d-Y')
                    ?? self::tryFormat($value, str_contains($value, ':') ? 'm-d-Y H:i' : 'm-d-Y');
            }

            return self::tryFormat($value, str_contains($value, ':') ? 'd-m-Y H:i:s' : 'd-m-Y')
                ?? self::tryFormat($value, str_contains($value, ':') ? 'd-m-Y H:i' : 'd-m-Y');
        }

        $textualFormats = array_merge([
            'd-M-y',
            'd-M-Y',
            'j-M-y',
            'j-M-Y',
            'M d Y',
            'M j Y',
            'F d Y',
            'F j Y',
            'd F Y',
            'j F Y',
            'd M Y',
            'j M Y',
            'M Y',
            'F Y',
            'M d, Y',
            'F d, Y',
            'd M Y H:i:s',
            'd F Y H:i:s',
            'n/j/Y g:i:s A',
            'm/d/Y g:i:s A',
        ], $extraFormats);

        foreach ($textualFormats as $format) {
            $normalized = self::tryFormat($value, $format);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        if (preg_match('/[a-zA-Z]/', $value) === 1) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function normalizeLocaleDateText(string $value): string
    {
        foreach (self::INDONESIAN_MONTHS as $source => $target) {
            $value = preg_replace('/\b' . preg_quote($source, '/') . '\b/i', $target, $value) ?? $value;
        }

        return $value;
    }

    public static function buildMySqlCaseExpression(string $textExpression): string
    {
        return "CASE "
            . "WHEN {$textExpression} IS NULL THEN NULL "
            . "WHEN {$textExpression} REGEXP '^[0-9]{8}$' THEN CASE "
                . "WHEN DATE_FORMAT(STR_TO_DATE({$textExpression}, '%Y%m%d'), '%Y%m%d') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%Y%m%d') "
                . "WHEN DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d%m%Y'), '%d%m%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d%m%Y') "
                . "WHEN CAST(LEFT({$textExpression}, 2) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m%d%Y'), '%m%d%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m%d%Y') "
                . "ELSE NULL END "
            . "WHEN {$textExpression} REGEXP '^[0-9]+(\\\\.[0-9]+)?$' THEN DATE_ADD('1899-12-30', INTERVAL CAST({$textExpression} AS DECIMAL(18,5)) DAY) "
            . "WHEN {$textExpression} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%Y-%m-%d'), '%Y-%m-%d') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%Y-%m-%d') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d/%m/%Y'), '%d/%m/%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d/%m/%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d-%m-%Y'), '%d-%m-%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d-%m-%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3,}\\s+[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %M %Y'), '%e %M %Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %M %Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3}\\s+[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %b %Y'), '%e %b %Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %b %Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' "
                . "AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$textExpression}, '/', 2), '/', -1) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m/%d/%Y'), '%m/%d/%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m/%d/%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' "
                . "AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$textExpression}, '-', 2), '-', -1) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m-%d-%Y'), '%m-%d-%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m-%d-%Y') "
            . "ELSE NULL END";
    }

    private static function tryFormat(string $value, string $format): ?string
    {
        try {
            $date = Carbon::createFromFormat($format, $value);
            if ($date === false) {
                return null;
            }

            $lastErrors = Carbon::getLastErrors();
            if (($lastErrors['warning_count'] ?? 0) > 0 || ($lastErrors['error_count'] ?? 0) > 0) {
                return null;
            }

            $year = (int) $date->format('Y');
            if ($year < 1900 || $year > 2100) {
                return null;
            }

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
