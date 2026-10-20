<?php

namespace App\Support\Import;

use App\Support\StrictDateParser;

/**
 * Profiles the raw values of one source column so the importer can recognise
 * what the column really contains, independent of header names:
 * - kind (date, datetime, decimal, integer, identifier, text, empty)
 * - date order (day/month vs month/day) with the evidence that proves it
 * - whether a time component exists and whether it carries information
 * - numeric separator style, scale, and text width
 *
 * The profile is deterministic and cheap; it only needs a few hundred samples.
 */
final class ColumnValueProfiler
{
    public const KIND_EMPTY = 'empty';
    public const KIND_DATE = 'date';
    public const KIND_DATETIME = 'datetime';
    public const KIND_DECIMAL = 'decimal';
    public const KIND_INTEGER = 'integer';
    public const KIND_IDENTIFIER = 'identifier';
    public const KIND_TEXT = 'text';

    public const ORDER_EVIDENCE = 'evidence';
    public const ORDER_CONVENTION = 'convention';
    public const ORDER_CONFLICT = 'conflict';

    private const DOMINANCE_RATIO = 0.9;
    private const MAX_SAMPLES = 6;

    private const BLANK_TOKENS = ['', '-', '--', 'null', 'nan', 'n/a', '\\n', '#n/a'];

    private const IDENTIFIER_HEADER_PATTERN = '/(^|_)(acct|acctno|rek|rekening|norek|no|nomor|cif|cifno|nik|npwp|kode|code|id|pn|hp|phone|telp|kodepos|zip|nip|account)(_|$|\d)/i';

    private const DATE_HEADER_PATTERN = '/(^|_)(tgl|tanggal|date|periode|posisi|tmt|jatuh|realisasi|wpstdt)/i';

    /**
     * Amount-like headers (SALDO_POSISI, JUMLAH_TRX_PERIODE) mention a date word
     * but hold numbers; an 8-digit balance must never be read as YYYYMMDD.
     */
    private const AMOUNT_HEADER_PATTERN = '/(^|_)(saldo|nominal|jumlah|jml|amount|amt|total|ratas|rata|volume|sv|trx|os|baki|plafon|bunga|pokok|nilai|fee|count|qty|sales|akumulasi|tunggakan|denda)(_|$|\d)/i';

    /**
     * @param iterable<mixed> $values
     */
    public function profile(iterable $values, ?string $header = null): array
    {
        $headerKey = $this->normalizeHeader((string) $header);
        $headerLooksLikeDate = $headerKey !== ''
            && preg_match(self::DATE_HEADER_PATTERN, $headerKey) === 1
            && preg_match(self::AMOUNT_HEADER_PATTERN, $headerKey) !== 1;
        $headerLooksLikeIdentifier = $headerKey !== ''
            && preg_match(self::IDENTIFIER_HEADER_PATTERN, $headerKey) === 1
            && preg_match('/^(jumlah|jml|total|count|qty|banyak|nilai|saldo|sum|rata|avg|persen|pct)(_|$)/', $headerKey) !== 1;

        $total = 0;
        $blank = 0;
        $counts = [
            self::KIND_DATE => 0,
            self::KIND_DECIMAL => 0,
            self::KIND_INTEGER => 0,
            self::KIND_TEXT => 0,
        ];
        $date = [
            'first_gt_12' => 0,
            'second_gt_12' => 0,
            'iso' => 0,
            'textual' => 0,
            'compact' => 0,
            'serial' => 0,
            'numeric' => 0,
            'with_time' => 0,
            'meridiem' => 0,
            'meaningful_time' => 0,
            'separators' => [],
            'two_digit_parts' => 0,
            'single_digit_parts' => 0,
        ];
        $numeric = [
            'thousands_comma' => 0,
            'thousands_dot' => 0,
            'decimal_dot' => 0,
            'decimal_comma' => 0,
            'max_scale' => 0,
            'max_integer_digits' => 0,
            'negative' => 0,
            'leading_zero' => 0,
            'fraction' => 0,
            'max_digits' => 0,
        ];
        $maxLength = 0;
        $samples = [];
        $failedDateSamples = [];

        foreach ($values as $value) {
            $total++;
            $text = $this->cleanValue($value);
            if ($text === null) {
                $blank++;
                continue;
            }

            $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
            $maxLength = max($maxLength, $length);
            if (count($samples) < self::MAX_SAMPLES && !in_array($text, $samples, true)) {
                $samples[] = $text;
            }

            $dateSignal = $this->classifyDate($text, $headerLooksLikeDate);
            if ($dateSignal !== null) {
                $counts[self::KIND_DATE]++;
                $this->accumulateDate($date, $dateSignal);
                continue;
            }

            $numericSignal = $this->classifyNumber($text);
            if ($numericSignal !== null) {
                $counts[$numericSignal['is_integer'] ? self::KIND_INTEGER : self::KIND_DECIMAL]++;
                $this->accumulateNumber($numeric, $numericSignal);
                continue;
            }

            if ($headerLooksLikeDate && count($failedDateSamples) < 3) {
                $failedDateSamples[] = $text;
            }
            $counts[self::KIND_TEXT]++;
        }

        $nonEmpty = $total - $blank;
        $kind = $this->resolveKind($counts, $nonEmpty, $numeric, $headerLooksLikeIdentifier, $maxLength);
        $dateProfile = $counts[self::KIND_DATE] > 0 ? $this->summarizeDate($date) : null;
        if ($kind === self::KIND_DATE && $dateProfile !== null && $dateProfile['meaningful_time']) {
            $kind = self::KIND_DATETIME;
        }

        $dominantCount = match ($kind) {
            self::KIND_DATE, self::KIND_DATETIME => $counts[self::KIND_DATE],
            self::KIND_DECIMAL => $counts[self::KIND_DECIMAL] + $counts[self::KIND_INTEGER],
            self::KIND_INTEGER => $counts[self::KIND_INTEGER],
            self::KIND_IDENTIFIER, self::KIND_TEXT => $nonEmpty,
            default => 0,
        };

        return [
            'header' => $header,
            'kind' => $kind,
            'total' => $total,
            'non_empty' => $nonEmpty,
            'blank' => $blank,
            'counts' => $counts,
            'confidence' => $nonEmpty > 0 ? round(min(1, $dominantCount / $nonEmpty), 4) : 0.0,
            'nonconforming' => max(0, $nonEmpty - $dominantCount),
            'max_length' => $maxLength,
            'samples' => $samples,
            'example' => $this->representativeExample($samples),
            'failed_date_samples' => $failedDateSamples,
            'date' => in_array($kind, [self::KIND_DATE, self::KIND_DATETIME], true) ? $dateProfile : null,
            'number' => in_array($kind, [self::KIND_DECIMAL, self::KIND_INTEGER, self::KIND_IDENTIFIER], true)
                ? $this->summarizeNumber($numeric)
                : null,
            'signature' => $this->signature($kind, $dateProfile, $numeric),
            'label' => $this->label($kind, $dateProfile, $numeric),
        ];
    }

    /**
     * Profiles every column of a sample grid at once.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows positional rows aligned with $headers
     * @return array<int, array<string, mixed>>
     */
    public function profileColumns(array $headers, array $rows): array
    {
        $profiles = [];
        foreach ($headers as $index => $header) {
            $profiles[$index] = $this->profile(
                (function () use ($rows, $index) {
                    foreach ($rows as $row) {
                        yield $row[$index] ?? null;
                    }
                })(),
                (string) $header
            );
        }

        return $profiles;
    }

    private function cleanValue(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $text = trim((string) $value);
        if (strlen($text) >= 2 && $text[0] === '"' && str_ends_with($text, '"')) {
            $text = trim(str_replace('""', '"', substr($text, 1, -1)));
        }

        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;

        return in_array(strtolower($text), self::BLANK_TOKENS, true) ? null : $text;
    }

    private function classifyDate(string $text, bool $headerLooksLikeDate): ?array
    {
        $canonical = StrictDateParser::canonicalizeDateTimeText($text);
        $time = $this->extractTime($canonical);

        $parts = StrictDateParser::splitNumericDayMonth($canonical);
        if ($parts !== null) {
            [$first, $second, $year] = $parts;
            if ($year < 1900 || $year > 2100 || ($first > 12 && $second > 12) || $first < 1 || $second < 1 || $first > 31 || $second > 31) {
                return null;
            }

            preg_match('/^\d{1,2}([\/.\-])/', $canonical, $separatorMatch);

            return [
                'family' => 'numeric',
                'first' => $first,
                'second' => $second,
                'separator' => $separatorMatch[1] ?? '/',
                'two_digit' => preg_match('/^\d{2}[\/.\-]\d{2}[\/.\-]/', $canonical) === 1,
                'time' => $time,
            ];
        }

        if (preg_match('/^(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})(?:\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AP]M)?)?$/i', $canonical, $matches) === 1) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                return null;
            }

            return ['family' => 'iso', 'time' => $time];
        }

        if (preg_match('/^\d{8}$/', $canonical) === 1) {
            if (!$headerLooksLikeDate || StrictDateParser::normalize($canonical) === null) {
                return null;
            }

            return ['family' => 'compact', 'time' => null];
        }

        if (preg_match('/^\d{5}(?:\.\d+)?$/', $canonical) === 1) {
            $serial = (float) $canonical;
            if (!$headerLooksLikeDate || $serial < 20000 || $serial > 80000) {
                return null;
            }

            $fraction = $serial - floor($serial);

            return ['family' => 'serial', 'time' => $fraction > 0 ? ['meaningful' => true, 'meridiem' => false] : null];
        }

        if (preg_match('/[a-z]/i', $canonical) === 1
            && preg_match('/\d{1,4}/', $canonical) === 1
            && preg_match('/\b(jan|feb|mar|apr|mei|may|jun|jul|agu|agt|ags|aug|sep|okt|oct|nov|des|dec)[a-z]*\b/i', $canonical) === 1
            && StrictDateParser::normalize($canonical) !== null) {
            return ['family' => 'textual', 'time' => $time];
        }

        return null;
    }

    private function extractTime(string $canonical): ?array
    {
        if (preg_match('/\s(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*([AP]M))?$/i', $canonical, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $second = (int) ($matches[3] ?? 0);
        $meridiem = strtoupper((string) ($matches[4] ?? ''));
        $isMidnight = $minute === 0 && $second === 0
            && ($meridiem === 'AM' ? $hour === 12 : ($meridiem === '' && $hour === 0));

        return [
            'meaningful' => !$isMidnight,
            'meridiem' => $meridiem !== '',
        ];
    }

    private function accumulateDate(array &$date, array $signal): void
    {
        $family = $signal['family'];
        $date[$family === 'numeric' ? 'numeric' : $family]++;

        if ($family === 'numeric') {
            if ($signal['first'] > 12) {
                $date['first_gt_12']++;
            }
            if ($signal['second'] > 12) {
                $date['second_gt_12']++;
            }
            $date['separators'][$signal['separator']] = true;
            $signal['two_digit'] ? $date['two_digit_parts']++ : $date['single_digit_parts']++;
        }

        if (is_array($signal['time'] ?? null)) {
            $date['with_time']++;
            if (!empty($signal['time']['meridiem'])) {
                $date['meridiem']++;
            }
            if (!empty($signal['time']['meaningful'])) {
                $date['meaningful_time']++;
            }
        }
    }

    private function summarizeDate(array $date): array
    {
        $order = null;
        $orderBasis = null;
        $numeric = $date['numeric'];

        if ($numeric > 0) {
            if ($date['first_gt_12'] > 0 && $date['second_gt_12'] > 0) {
                $order = StrictDateParser::ORDER_DAY_FIRST;
                $orderBasis = self::ORDER_CONFLICT;
            } elseif ($date['first_gt_12'] > 0) {
                $order = StrictDateParser::ORDER_DAY_FIRST;
                $orderBasis = self::ORDER_EVIDENCE;
            } elseif ($date['second_gt_12'] > 0) {
                $order = StrictDateParser::ORDER_MONTH_FIRST;
                $orderBasis = self::ORDER_EVIDENCE;
            } else {
                // No value proves its order. A 12-hour clock is the core-banking
                // month-first convention; otherwise Indonesian day-first applies.
                $order = $date['meridiem'] > 0 ? StrictDateParser::ORDER_MONTH_FIRST : StrictDateParser::ORDER_DAY_FIRST;
                $orderBasis = self::ORDER_CONVENTION;
            }
        } elseif ($date['iso'] > 0 || $date['compact'] > 0) {
            $order = StrictDateParser::ORDER_YEAR_FIRST;
            $orderBasis = self::ORDER_EVIDENCE;
        }

        $families = array_keys(array_filter([
            'numeric' => $numeric,
            'iso' => $date['iso'],
            'textual' => $date['textual'],
            'compact' => $date['compact'],
            'serial' => $date['serial'],
        ]));

        return [
            'order' => $order,
            'order_basis' => $orderBasis,
            'families' => $families,
            'separator' => array_key_first($date['separators']),
            'has_time' => $date['with_time'] > 0,
            'meridiem' => $date['meridiem'] > 0,
            'meaningful_time' => $date['meaningful_time'] > 0,
            'evidence' => [
                'day_first_values' => $date['first_gt_12'],
                'month_first_values' => $date['second_gt_12'],
            ],
            'pattern' => $this->datePattern($order, $families, $date),
        ];
    }

    private function datePattern(?string $order, array $families, array $date): string
    {
        $family = $families[0] ?? 'numeric';
        $separator = array_key_first($date['separators']) ?? '/';
        $width = $date['single_digit_parts'] > $date['two_digit_parts'] ? 1 : 2;
        $dd = str_repeat('D', $width);
        $mm = str_repeat('M', $width);

        $base = match ($family) {
            'iso' => 'YYYY-MM-DD',
            'compact' => 'YYYYMMDD',
            'serial' => 'Serial Excel',
            'textual' => 'DD Mon YYYY',
            default => $order === StrictDateParser::ORDER_MONTH_FIRST
                ? "{$mm}{$separator}{$dd}{$separator}YYYY"
                : "{$dd}{$separator}{$mm}{$separator}YYYY",
        };

        if ($date['with_time'] > 0) {
            $base .= $date['meridiem'] > 0 ? ' hh:mm:ss AM/PM' : ' HH:mm:ss';
        }

        return $base;
    }

    private function classifyNumber(string $text): ?array
    {
        $value = str_replace(["\u{00A0}", ' ', 'Rp', 'rp', 'IDR'], '', $text);
        $negative = false;
        if (preg_match('/^\((.+)\)$/', $value, $matches) === 1) {
            $value = $matches[1];
            $negative = true;
        }
        if (str_ends_with($value, '-') && strlen($value) > 1) {
            $value = substr($value, 0, -1);
            $negative = true;
        }
        if (str_starts_with($value, '-') || str_starts_with($value, '+')) {
            $negative = $negative || $value[0] === '-';
            $value = substr($value, 1);
        }

        if ($value === '' || preg_match('/^[\d.,]+$/', $value) !== 1 || preg_match('/\d/', $value) !== 1) {
            return null;
        }

        $signal = [
            'negative' => $negative,
            'thousands' => null,
            'decimal' => null,
            'scale' => 0,
            'integer_digits' => 0,
            'leading_zero' => false,
            'is_integer' => true,
            'digits' => strlen(preg_replace('/\D/', '', $value) ?? ''),
        ];

        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $value, $matches) === 1) {
            $signal['thousands'] = ',';
            $fraction = isset($matches[2]) ? substr($matches[2], 1) : '';
            $signal['decimal'] = $fraction !== '' ? '.' : null;
            $integerPart = str_replace(',', '', explode('.', $value)[0]);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $value, $matches) === 1) {
            $signal['thousands'] = '.';
            $fraction = isset($matches[2]) ? substr($matches[2], 1) : '';
            $signal['decimal'] = $fraction !== '' ? ',' : null;
            $integerPart = str_replace('.', '', explode(',', $value)[0]);
        } elseif (preg_match('/^(\d*)(?:([.,])(\d+))?$/', $value, $matches) === 1 && ($matches[1] !== '' || ($matches[3] ?? '') !== '')) {
            // ".00" and ",5" (no leading zero) are common in SSRS/Cognos exports.
            $integerPart = $matches[1] !== '' ? $matches[1] : '0';
            $fraction = $matches[3] ?? '';
            $signal['decimal'] = $fraction !== '' ? $matches[2] : null;
        } else {
            return null;
        }

        $signal['scale'] = strlen($fraction);
        $signal['integer_digits'] = strlen(ltrim($integerPart, '0')) ?: 1;
        $signal['leading_zero'] = strlen($integerPart) > 1 && $integerPart[0] === '0' && $signal['thousands'] === null;
        $signal['is_integer'] = $signal['scale'] === 0 || trim($fraction, '0') === '';
        $signal['has_fraction_digits'] = $signal['scale'] > 0;

        return $signal;
    }

    private function accumulateNumber(array &$numeric, array $signal): void
    {
        if ($signal['thousands'] === ',') {
            $numeric['thousands_comma']++;
        } elseif ($signal['thousands'] === '.') {
            $numeric['thousands_dot']++;
        }
        if ($signal['decimal'] === '.') {
            $numeric['decimal_dot']++;
        } elseif ($signal['decimal'] === ',') {
            $numeric['decimal_comma']++;
        }
        if (!empty($signal['has_fraction_digits'])) {
            $numeric['fraction']++;
        }
        $numeric['max_scale'] = max($numeric['max_scale'], $signal['scale']);
        $numeric['max_integer_digits'] = max($numeric['max_integer_digits'], $signal['integer_digits']);
        $numeric['max_digits'] = max($numeric['max_digits'], $signal['digits']);
        if ($signal['negative']) {
            $numeric['negative']++;
        }
        if ($signal['leading_zero']) {
            $numeric['leading_zero']++;
        }
    }

    private function summarizeNumber(array $numeric): array
    {
        $style = 'plain';
        if ($numeric['thousands_comma'] > 0 || ($numeric['decimal_dot'] > 0 && $numeric['decimal_comma'] === 0)) {
            $style = 'us';
        }
        if ($numeric['thousands_dot'] > 0 || ($numeric['decimal_comma'] > 0 && $numeric['decimal_dot'] === 0)) {
            $style = $style === 'us' ? 'mixed' : 'id';
        }

        return [
            'style' => $style,
            'max_scale' => $numeric['max_scale'],
            'max_integer_digits' => $numeric['max_integer_digits'],
            'max_digits' => $numeric['max_digits'],
            'has_negative' => $numeric['negative'] > 0,
            'leading_zero_values' => $numeric['leading_zero'],
            'has_fraction' => $numeric['fraction'] > 0,
        ];
    }

    private function resolveKind(array $counts, int $nonEmpty, array $numeric, bool $headerLooksLikeIdentifier, int $maxLength): string
    {
        if ($nonEmpty === 0) {
            return self::KIND_EMPTY;
        }

        $threshold = (int) ceil($nonEmpty * self::DOMINANCE_RATIO);
        $numericCount = $counts[self::KIND_INTEGER] + $counts[self::KIND_DECIMAL];

        if ($counts[self::KIND_DATE] >= $threshold) {
            return self::KIND_DATE;
        }

        if ($numericCount >= $threshold) {
            $plainDigitsOnly = $numeric['thousands_comma'] === 0 && $numeric['thousands_dot'] === 0 && $numeric['fraction'] === 0;
            if ($plainDigitsOnly && ($numeric['leading_zero'] > 0 || $headerLooksLikeIdentifier || $numeric['max_digits'] >= 16)) {
                return self::KIND_IDENTIFIER;
            }

            return $numeric['fraction'] > 0 || $counts[self::KIND_DECIMAL] > 0 || $numeric['thousands_comma'] > 0 || $numeric['thousands_dot'] > 0
                ? self::KIND_DECIMAL
                : self::KIND_INTEGER;
        }

        if ($headerLooksLikeIdentifier && $maxLength <= 64 && $numericCount > 0) {
            return self::KIND_IDENTIFIER;
        }

        return self::KIND_TEXT;
    }

    private function signature(string $kind, ?array $dateProfile, array $numeric): string
    {
        if (in_array($kind, [self::KIND_DATE, self::KIND_DATETIME], true) && $dateProfile !== null) {
            return implode('|', [
                'date',
                implode('+', $dateProfile['families']),
                $dateProfile['order'] ?? '-',
                $dateProfile['has_time'] ? ($dateProfile['meridiem'] ? 'time12' : 'time24') : 'notime',
            ]);
        }

        if ($kind === self::KIND_DECIMAL || $kind === self::KIND_INTEGER) {
            return 'number|' . $this->summarizeNumber($numeric)['style'];
        }

        return $kind;
    }

    private function label(string $kind, ?array $dateProfile, array $numeric): string
    {
        return match ($kind) {
            self::KIND_DATE, self::KIND_DATETIME => $dateProfile['pattern'] ?? 'Tanggal',
            self::KIND_DECIMAL => match ($this->summarizeNumber($numeric)['style']) {
                'us' => 'Angka 1,234,567.89',
                'id' => 'Angka 1.234.567,89',
                'mixed' => 'Angka (pemisah campuran)',
                default => 'Angka desimal',
            },
            self::KIND_INTEGER => 'Bilangan bulat',
            self::KIND_IDENTIFIER => 'Kode/nomor identitas',
            self::KIND_EMPTY => 'Kosong',
            default => 'Teks',
        };
    }

    /**
     * The sample that best shows the format: a value with separators or a
     * clock beats a bare "0".
     */
    private function representativeExample(array $samples): ?string
    {
        $best = null;
        $bestScore = -1;
        foreach ($samples as $sample) {
            $sample = (string) $sample;
            $score = preg_match_all('/[.,\/:\-]/', $sample) + min(3, strlen($sample) / 4);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $sample;
            }
        }

        return $best;
    }

    private function normalizeHeader(string $header): string
    {
        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $header)), '_');
    }
}
