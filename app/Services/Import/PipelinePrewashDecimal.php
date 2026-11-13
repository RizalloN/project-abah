<?php

namespace App\Services\Import;

use RuntimeException;

/** Exact decimal arithmetic: source amounts and identifiers never pass through float. */
class PipelinePrewashDecimal
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $expanded = self::expand(trim($value));
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $expanded, $parts) !== 1 || strlen($parts[3] ?? '') > 2) {
            throw new RuntimeException('Angka Pipeline Prewash tidak valid atau melebihi dua desimal; angka tidak dibulatkan.');
        }
        $integer = ltrim($parts[2], '0') ?: '0';
        $fraction = str_pad($parts[3] ?? '', 2, '0');
        $sign = $integer === '0' && $fraction === '00' ? '' : $parts[1];

        return $sign.$integer.'.'.$fraction;
    }

    public static function integer(string $value): string
    {
        $expanded = self::expand(trim($value));
        if (preg_match('/^\+?(\d+)(?:\.(0+))?$/D', $expanded, $parts) !== 1) {
            throw new RuntimeException('Identitas numerik Pipeline Prewash wajib berupa bilangan bulat positif tanpa pembulatan.');
        }

        return ltrim($parts[1], '0') ?: '0';
    }

    public static function add(string $left, string $right): string
    {
        $left = self::normalize($left);
        $right = self::normalize($right);
        if ($left === null || $right === null) {
            throw new RuntimeException('Penjumlahan plafon memerlukan dua angka yang tersedia.');
        }
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        $a = ltrim(str_replace(['-', '.'], '', $left), '0') ?: '0';
        $b = ltrim(str_replace(['-', '.'], '', $right), '0') ?: '0';
        if ($leftNegative === $rightNegative) {
            $digits = self::addUnsigned($a, $b);
            $negative = $leftNegative;
        } else {
            $comparison = strlen($a) <=> strlen($b);
            $comparison = $comparison === 0 ? strcmp($a, $b) : $comparison;
            $digits = $comparison >= 0 ? self::subtractUnsigned($a, $b) : self::subtractUnsigned($b, $a);
            $negative = $comparison >= 0 ? $leftNegative : $rightNegative;
        }
        $digits = str_pad($digits, 3, '0', STR_PAD_LEFT);

        return ($negative && $digits !== '000' ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);
    }

    private static function expand(string $value): string
    {
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/D', $value, $parts) !== 1) {
            throw new RuntimeException('Angka Pipeline Prewash tidak valid; pemisah ribuan ambigu tidak diterima.');
        }
        $exponentText = $parts[4] ?? '0';
        if (strlen(ltrim($exponentText, '+-0')) > 3 || abs((int) $exponentText) > 512) {
            throw new RuntimeException('Eksponen atau panjang angka Pipeline Prewash melebihi kapasitas.');
        }
        $digits = $parts[2].($parts[3] ?? '');
        $position = strlen($parts[2]) + (int) $exponentText;
        if ($position <= 0) {
            $expanded = '0.'.str_repeat('0', -$position).$digits;
        } elseif ($position >= strlen($digits)) {
            $expanded = $digits.str_repeat('0', $position - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $position).'.'.substr($digits, $position);
        }

        return ($parts[1] === '-' ? '-' : '').$expanded;
    }

    private static function addUnsigned(string $left, string $right): string
    {
        $result = '';
        $carry = 0;
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0 || $j >= 0 || $carry; $i--, $j--) {
            $sum = ($i >= 0 ? (int) $left[$i] : 0) + ($j >= 0 ? (int) $right[$j] : 0) + $carry;
            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return $result;
    }

    /** $left is at least $right. */
    private static function subtractUnsigned(string $left, string $right): string
    {
        $result = '';
        $borrow = 0;
        for ($i = strlen($left) - 1, $j = strlen($right) - 1; $i >= 0; $i--, $j--) {
            $digit = (int) $left[$i] - ($j >= 0 ? (int) $right[$j] : 0) - $borrow;
            $borrow = $digit < 0 ? 1 : 0;
            $result = (string) ($digit < 0 ? $digit + 10 : $digit).$result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
