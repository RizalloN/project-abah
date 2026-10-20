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

    public const ORDER_DAY_FIRST = 'dmy';
    public const ORDER_MONTH_FIRST = 'mdy';
    public const ORDER_YEAR_FIRST = 'ymd';

    /**
     * Normalize a date using a column-level order hint learned from the source
     * file (for example PERIODE exported as MM/DD/YYYY hh:mm:ss AM). The hint is
     * only used for genuinely ambiguous numeric values such as 04/07/2026; any
     * value that proves its own order (day > 12) always wins over the hint.
     */
    public static function normalizeWithOrder(
        ?string $value,
        ?string $order,
        array $extraFormats = [],
        bool $allowLocaleMonthNames = true
    ): ?string {
        $order = strtolower(trim((string) $order));
        if (!in_array($order, [self::ORDER_DAY_FIRST, self::ORDER_MONTH_FIRST], true)) {
            return self::normalize($value, $extraFormats, $allowLocaleMonthNames);
        }

        $canonical = self::canonicalizeDateTimeText((string) ($value ?? ''));
        $parts = self::splitNumericDayMonth($canonical);
        if ($parts === null) {
            return self::normalize($value, $extraFormats, $allowLocaleMonthNames);
        }

        [$first, $second, $year] = $parts;
        if ($first > 12) {
            return self::composeDate($year, $second, $first);
        }

        if ($second > 12) {
            return self::composeDate($year, $first, $second);
        }

        return $order === self::ORDER_MONTH_FIRST
            ? self::composeDate($year, $first, $second)
            : self::composeDate($year, $second, $first);
    }

    /**
     * Returns [first, second, year] for numeric day/month dates such as
     * 4/7/2026, 04-07-2026 08:30, 16.10.2026 or 10/18/2026 12:00:00 AM.
     */
    public static function splitNumericDayMonth(string $value): ?array
    {
        $pattern = '/^(\d{1,2})([\/.\-])(\d{1,2})\2(\d{4}|\d{2})'
            . '(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*([AP]M))?)?$/i';

        if (preg_match($pattern, trim($value), $matches) !== 1) {
            return null;
        }

        if (isset($matches[5]) && $matches[5] !== '') {
            $hour = (int) $matches[5];
            $hasMeridiem = isset($matches[8]) && $matches[8] !== '';
            if (($hasMeridiem && ($hour < 1 || $hour > 12)) || $hour > 23
                || (int) $matches[6] > 59 || (int) ($matches[7] ?? 0) > 59) {
                return null;
            }
        }

        $year = (int) $matches[4];
        if (strlen($matches[4]) === 2) {
            $year += $year >= 70 ? 1900 : 2000;
        }

        return [(int) $matches[1], (int) $matches[3], $year];
    }

    /**
     * Make modern export variants look like the classic formats: ISO 8601 "T"
     * separators, fractional seconds, trailing time zones and "a.m."/"p.m.".
     */
    public static function canonicalizeDateTimeText(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^(\d{4}-\d{1,2}-\d{1,2})T(\d{1,2}:\d{2})/i', '$1 $2', $value) ?? $value;
        $value = preg_replace('/(\d{1,2}:\d{2}:\d{2})[.,]\d{1,9}/', '$1', $value) ?? $value;
        $value = preg_replace('/(\d{1,2}:\d{2}(?::\d{2})?)\s*(?:Z|UTC|GMT|[+\-]\d{2}:?\d{2})$/i', '$1', $value) ?? $value;
        $value = preg_replace('/\b([ap])\.\s?m\.?(?=\s|$)/i', '$1M', $value) ?? $value;

        return trim($value);
    }

    private static function composeDate(int $year, int $month, int $day): ?string
    {
        if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Parse common import date inputs into Y-m-d without relying on Carbon::parse
     * for numeric-only or slash-delimited ambiguous values.
     */
    public static function normalize(?string $value, array $extraFormats = [], bool $allowLocaleMonthNames = true): ?string
    {
        $value = self::canonicalizeDateTimeText((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if ($allowLocaleMonthNames) {
            $value = self::normalizeLocaleDateText($value);
        }

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

        // Core banking exports use month/day/year with a 12-hour AM/PM clock.
        // Handle this before numeric date branches, which only accept 24-hour time.
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/\d{4}\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s+[AP]M$/i', $value, $matches) === 1) {
            if ((int) $matches[3] < 1 || (int) $matches[3] > 12 || (int) $matches[4] > 59 || (int) ($matches[5] ?? 0) > 59) {
                return null;
            }

            $dateFormat = (int) $matches[1] > 12 ? 'j/n/Y' : 'n/j/Y';

            return self::tryFormat(strtoupper($value), $dateFormat . ' g:i:s A')
                ?? self::tryFormat(strtoupper($value), $dateFormat . ' g:i A');
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

        foreach (['/', '-'] as $separator) {
            $quoted = preg_quote($separator, '/');
            if (preg_match('/^(\d{2})' . $quoted . '(\d{2})' . $quoted . '(\d{4})(?:\s+.*)?$/', $value, $matches) !== 1) {
                continue;
            }

            $order = ((int) $matches[1] <= 12 && (int) $matches[2] > 12) ? 'm' . $separator . 'd' : 'd' . $separator . 'm';
            $hasTime = str_contains($value, ':');
            $resolved = self::tryFormat($value, $order . $separator . ($hasTime ? 'Y H:i:s' : 'Y'))
                ?? self::tryFormat($value, $order . $separator . ($hasTime ? 'Y H:i' : 'Y'));

            // Fall through to the generic handlers (12-hour clock, etc.) when the
            // strict two-digit formats cannot represent the time component.
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Single-digit day/month (4/7/2026), dotted dates (16.10.2026) and the
        // same with a clock. Day-first stays the default for Indonesian exports;
        // a 12-hour clock follows the core-banking month-first convention.
        $numericParts = self::splitNumericDayMonth($value);
        if ($numericParts !== null) {
            [$first, $second, $year] = $numericParts;
            $monthFirst = $first > 12
                ? false
                : ($second > 12 || preg_match('/\s[AP]M$/i', $value) === 1);

            return $monthFirst
                ? self::composeDate($year, $first, $second)
                : self::composeDate($year, $second, $first);
        }

        if (preg_match('/^(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?$/i', $value, $matches) === 1) {
            return self::composeDate((int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        $textualFormats = array_merge([
            'd M Y h:i:s A',
            'd M Y g:i:s A',
            'd M Y g:i A',
            'j M Y g:i:s A',
            'd F Y g:i:s A',
            'd-M-Y H:i:s',
            'd-M-Y H:i',
            'd-M-Y g:i:s A',
            'd-M-y g:i:s A',
            'M d, Y g:i:s A',
            'M j, Y g:i A',
            'F j, Y g:i:s A',
            'd/M/Y',
            'd-M-Y',
            'd-M-y',
            'd-M-Y',
            'j-M-y',
            'j-M-Y',
            'd M y',
            'j M y',
            'd F y',
            'j F y',
            'M d y',
            'M j y',
            'F d y',
            'F j y',
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

        // Indonesian short month names that differ from English (Agt, Okt, Des).
        foreach (['agt' => 'Aug', 'ags' => 'Aug', 'agu' => 'Aug', 'okt' => 'Oct', 'des' => 'Dec'] as $source => $target) {
            $value = preg_replace('/(?<=^|[\s\-\/.])' . $source . '(?=$|[\s\-\/.,])/i', $target, $value) ?? $value;
        }

        return $value;
    }

    public static function buildMySqlCaseExpression(string $textExpression, ?string $order = null): string
    {
        $order = strtolower(trim((string) $order));
        $order = in_array($order, [self::ORDER_DAY_FIRST, self::ORDER_MONTH_FIRST], true) ? $order : null;

        $firstPart = "CAST(SUBSTRING_INDEX({$textExpression}, '/', 1) AS UNSIGNED)";
        $secondPart = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$textExpression}, '/', 2), '/', -1) AS UNSIGNED)";
        $ambiguousMeridiemFormat = $order === self::ORDER_DAY_FIRST ? "'%e/%c/%Y'" : "'%c/%e/%Y'";
        $meridiemDateFormat = "CASE WHEN {$firstPart} > 12 THEN '%e/%c/%Y' "
            . "WHEN {$secondPart} > 12 THEN '%c/%e/%Y' "
            . "ELSE {$ambiguousMeridiemFormat} END";
        $meridiemDate = "DATE(STR_TO_DATE(SUBSTRING_INDEX({$textExpression}, ' ', 1), {$meridiemDateFormat}))";

        $monthFirstHintClauses = '';
        if ($order === self::ORDER_MONTH_FIRST) {
            foreach (['/', '-'] as $separator) {
                $first = "CAST(SUBSTRING_INDEX({$textExpression}, '{$separator}', 1) AS UNSIGNED)";
                $format = "%m{$separator}%d{$separator}%Y";
                $monthFirstHintClauses .= "WHEN {$textExpression} REGEXP '^[0-9]{2}[{$separator}][0-9]{2}[{$separator}][0-9]{4}$' "
                    . "AND {$first} <= 12 "
                    . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '{$format}'), '{$format}') = {$textExpression} "
                    . "THEN STR_TO_DATE({$textExpression}, '{$format}') ";
            }
        }

        // DATE_ADD(..., INTERVAL 0 DAY) rejects impossible dates (31/02) that
        // STR_TO_DATE would otherwise pass through as 2026-02-31.
        return "DATE_ADD(CASE "
            . "WHEN {$textExpression} IS NULL THEN NULL "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4} +(0?[1-9]|1[0-2]):[0-5][0-9](:[0-5][0-9])? +[AaPp][Mm]$' "
                . "AND YEAR({$meridiemDate}) BETWEEN 1900 AND 2100 "
                . "AND DAY({$meridiemDate}) <= DAY(LAST_DAY({$meridiemDate})) THEN {$meridiemDate} "
            . "WHEN {$textExpression} REGEXP '^[0-9]{8}$' THEN CASE "
                . "WHEN DATE_FORMAT(STR_TO_DATE({$textExpression}, '%Y%m%d'), '%Y%m%d') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%Y%m%d') "
                . "WHEN DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d%m%Y'), '%d%m%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d%m%Y') "
                . "WHEN CAST(LEFT({$textExpression}, 2) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m%d%Y'), '%m%d%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m%d%Y') "
                . "ELSE NULL END "
            . "WHEN {$textExpression} REGEXP '^[0-9]+(\\\\.[0-9]+)?$' THEN DATE_ADD('1899-12-30', INTERVAL CAST({$textExpression} AS DECIMAL(18,5)) DAY) "
            . "WHEN {$textExpression} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%Y-%m-%d'), '%Y-%m-%d') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%Y-%m-%d') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}[T ]+[0-9]{1,2}:[0-9]{2}' "
                . "AND DATE_FORMAT(STR_TO_DATE(LEFT({$textExpression}, 10), '%Y-%m-%d'), '%Y-%m-%d') = LEFT({$textExpression}, 10) "
                . "THEN STR_TO_DATE(LEFT({$textExpression}, 10), '%Y-%m-%d') "
            . $monthFirstHintClauses
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d/%m/%Y'), '%d/%m/%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d/%m/%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%d-%m-%Y'), '%d-%m-%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%d-%m-%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3,}\\s+[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %M %Y'), '%e %M %Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %M %Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3}\\s+[0-9]{4}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %b %Y'), '%e %b %Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %b %Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3,}\\s+[0-9]{2}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %M %y'), '%e %M %y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %M %y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{1,2}\\s+[A-Za-z]{3}\\s+[0-9]{2}$' "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%e %b %y'), '%e %b %y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%e %b %y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$' "
                . "AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$textExpression}, '/', 2), '/', -1) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m/%d/%Y'), '%m/%d/%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m/%d/%Y') "
            . "WHEN {$textExpression} REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$' "
                . "AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$textExpression}, '-', 2), '-', -1) AS UNSIGNED) > 12 "
                . "AND DATE_FORMAT(STR_TO_DATE({$textExpression}, '%m-%d-%Y'), '%m-%d-%Y') = {$textExpression} THEN STR_TO_DATE({$textExpression}, '%m-%d-%Y') "
            . self::buildMySqlGenericDayMonthClause($textExpression, $order)
            . self::buildMySqlTextualMonthClause($textExpression)
            . "ELSE NULL END, INTERVAL 0 DAY)";
    }

    /**
     * "20 Oct 2026 08:39:16 AM", "16-Okt-2026", "18 Oktober 2026".
     */
    private static function buildMySqlTextualMonthClause(string $textExpression): string
    {
        $text = "LOWER(REPLACE({$textExpression}, '-', ' '))";
        foreach ([
            'januari' => 'january', 'februari' => 'february', 'maret' => 'march', 'mei' => 'may',
            'juni' => 'june', 'juli' => 'july', 'agustus' => 'august', 'oktober' => 'october',
            'desember' => 'december', 'agt' => 'aug', 'ags' => 'aug', 'agu' => 'aug',
            'okt' => 'oct', 'des' => 'dec',
        ] as $source => $target) {
            $text = "REPLACE({$text}, '{$source}', '{$target}')";
        }

        return "WHEN {$textExpression} REGEXP '^[0-9]{1,2}[ -][A-Za-z]{3,9}[ -][0-9]{4}( .*)?$' "
            . "THEN STR_TO_DATE(SUBSTRING_INDEX({$text}, ' ', 3), '%e %M %Y') ";
    }

    /**
     * Single-digit, dotted, and clock-suffixed day/month dates
     * (4/7/2026, 16.10.2026, 16/10/2026 08:30:00, 10-18-2026 12:00:00 AM).
     * The date is composed from numeric parts and validated by round trip so an
     * impossible value (31/02) becomes NULL instead of a shifted date.
     */
    private static function buildMySqlGenericDayMonthClause(string $textExpression, ?string $order): string
    {
        $datePart = "REPLACE(REPLACE(SUBSTRING_INDEX({$textExpression}, ' ', 1), '.', '/'), '-', '/')";
        $first = "CAST(SUBSTRING_INDEX({$datePart}, '/', 1) AS UNSIGNED)";
        $second = "CAST(SUBSTRING_INDEX(SUBSTRING_INDEX({$datePart}, '/', 2), '/', -1) AS UNSIGNED)";
        $year = "CAST(SUBSTRING_INDEX({$datePart}, '/', -1) AS UNSIGNED)";
        $isMeridiem = "{$textExpression} REGEXP '[[:space:]][AaPp][Mm]$'";
        $ambiguousMonthFirst = match ($order) {
            self::ORDER_MONTH_FIRST => 'TRUE',
            self::ORDER_DAY_FIRST => 'FALSE',
            default => "({$isMeridiem})",
        };
        $monthFirst = "({$first} <= 12 AND ({$second} > 12 OR {$ambiguousMonthFirst}))";
        $month = "CASE WHEN {$monthFirst} THEN {$first} ELSE {$second} END";
        $day = "CASE WHEN {$monthFirst} THEN {$second} ELSE {$first} END";
        $canonical = "CONCAT({$year}, '-', {$month}, '-', {$day})";
        $composed = "STR_TO_DATE({$canonical}, '%Y-%c-%e')";

        return "WHEN {$textExpression} REGEXP '^[0-9]{1,2}[/.-][0-9]{1,2}[/.-][0-9]{4}( +[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?( *[AaPp][Mm])?)?$' "
            . "AND {$year} BETWEEN 1900 AND 2100 "
            . "AND DATE_FORMAT({$composed}, '%Y-%c-%e') = {$canonical} "
            . "THEN {$composed} ";
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
