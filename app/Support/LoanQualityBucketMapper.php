<?php

namespace App\Support;

final class LoanQualityBucketMapper
{
    public static function map(
        ?string $kolekDetail,
        $umurTunggakan,
        ?string $flagRestruk,
        ?string $kolAdk1 = null,
        ?string $kolek = null,
        ?string $periode = null,
        ?string $nextPmtDate = null,
        ?string $nextPmtIntDate = null
    ): string {
        $effectiveAge = self::resolveEffectiveAge($umurTunggakan, $periode, $nextPmtDate, $nextPmtIntDate);
        $kolekBucket = self::resolveKolekBucket(self::normalizeKolekValue($kolek), $effectiveAge, $flagRestruk);

        if ($kolekBucket !== null) {
            return $kolekBucket;
        }

        $normalizedDetail = self::normalizeDetailBucket($kolekDetail);
        $bucket = self::resolveBaseBucket($normalizedDetail, $effectiveAge);

        return self::normalizeBucket($bucket, $flagRestruk, $normalizedDetail);
    }

    private static function resolveEffectiveAge(
        $umurTunggakan,
        ?string $periode,
        ?string $nextPmtDate,
        ?string $nextPmtIntDate
    ): ?int {
        $direct = self::normalizeAge($umurTunggakan);
        if ($direct !== null) {
            return $direct;
        }

        if (!$periode) {
            return null;
        }

        try {
            $periodeTs = strtotime($periode);
            if ($periodeTs === false) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $candidates = array_filter(
            [$nextPmtDate, $nextPmtIntDate],
            static fn ($v) => is_string($v) && trim($v) !== ''
        );

        if (!$candidates) {
            return null;
        }

        $earliest = null;
        foreach ($candidates as $candidate) {
            $ts = strtotime($candidate);
            if ($ts === false) {
                continue;
            }
            if ($earliest === null || $ts < $earliest) {
                $earliest = $ts;
            }
        }

        if ($earliest === null) {
            return null;
        }

        return (int) floor(($periodeTs - $earliest) / 86400);
    }

    public static function buildSqlExpression(?string $alias = null): string
    {
        $kolekDetailColumn = self::qualifyColumn($alias, 'kolek_detail');
        $umurTunggakanColumn = self::qualifyColumn($alias, 'umur_tunggakan');
        $flagRestrukColumn = self::qualifyColumn($alias, 'flag_restruk');
        $kolekColumn = self::qualifyColumn($alias, 'kolek');
        $periodeColumn = self::qualifyColumn($alias, 'periode');
        $nextPmtDateColumn = self::qualifyColumn($alias, 'next_pmt_date');
        $nextPmtIntDateColumn = self::qualifyColumn($alias, 'next_pmt_int_date');

        $normalizedDetail = self::buildNormalizedSqlValue($kolekDetailColumn);
        $normalizedFlagRestruk = self::buildNormalizedSqlValue($flagRestrukColumn);
        $normalizedKolek = self::buildNormalizedSqlValue($kolekColumn);

        // Effective umur: pakai umur_tunggakan apa adanya, kalau NULL hitung dari
        // periode - LEAST(next_pmt_date, next_pmt_int_date). Hasilnya bisa tetap NULL
        // bila keduanya juga NULL.
        $derivedAge = "
            CASE
                WHEN {$nextPmtDateColumn} IS NOT NULL AND {$nextPmtIntDateColumn} IS NOT NULL
                    THEN DATEDIFF({$periodeColumn}, LEAST({$nextPmtDateColumn}, {$nextPmtIntDateColumn}))
                WHEN {$nextPmtDateColumn} IS NOT NULL
                    THEN DATEDIFF({$periodeColumn}, {$nextPmtDateColumn})
                WHEN {$nextPmtIntDateColumn} IS NOT NULL
                    THEN DATEDIFF({$periodeColumn}, {$nextPmtIntDateColumn})
                ELSE NULL
            END
        ";

        $effectiveAge = "COALESCE({$umurTunggakanColumn}, {$derivedAge})";

        // Simple CASE mengevaluasi operandnya sekali per baris. Versi lama menyalin
        // kolek, umur dan detail ke setiap cabang (~67 KB SQL) sehingga dihitung
        // berulang; hasilnya identik, hanya jauh lebih murah dievaluasi.
        $kolekValueExpression = "
            CASE {$normalizedKolek}
                WHEN '1' THEN 1 WHEN '1.0' THEN 1 WHEN '1.00' THEN 1
                WHEN '2' THEN 2 WHEN '2.0' THEN 2 WHEN '2.00' THEN 2
                WHEN '3' THEN 3 WHEN '3.0' THEN 3 WHEN '3.00' THEN 3
                WHEN '4' THEN 4 WHEN '4.0' THEN 4 WHEN '4.00' THEN 4
                WHEN '5' THEN 5 WHEN '5.0' THEN 5 WHEN '5.00' THEN 5
                ELSE 0
            END
        ";
        $currentOrRestructured = "CASE WHEN {$normalizedFlagRestruk} = 'Y' THEN 'LR' ELSE 'L' END";

        // Tanpa kolek: umur menentukan bucket; bila umur juga kosong, kolek_detail.
        // L dari umur/detail menjadi LR bila restruk dan detailnya L/LR.
        $ageBucketExpression = "
            CASE
                WHEN {$effectiveAge} <= 0 THEN CASE
                    WHEN {$normalizedFlagRestruk} = 'Y' AND {$normalizedDetail} IN ('L', 'LR') THEN 'LR'
                    ELSE 'L'
                END
                WHEN {$effectiveAge} <= 30 THEN 'DPK 1'
                WHEN {$effectiveAge} <= 60 THEN 'DPK 2'
                WHEN {$effectiveAge} <= 90 THEN 'DPK 3'
                WHEN {$effectiveAge} <= 120 THEN 'KL'
                WHEN {$effectiveAge} <= 150 THEN 'D1'
                WHEN {$effectiveAge} <= 180 THEN 'D2'
                ELSE 'M'
            END
        ";
        $detailBucketExpression = "
            CASE {$normalizedDetail}
                WHEN '' THEN 'Unknown' WHEN '0' THEN 'Unknown' WHEN '-' THEN 'Unknown'
                WHEN 'DPK1' THEN 'DPK 1' WHEN 'DPK 1' THEN 'DPK 1' WHEN 'SML1' THEN 'DPK 1' WHEN 'SML 1' THEN 'DPK 1'
                WHEN 'DPK2' THEN 'DPK 2' WHEN 'DPK 2' THEN 'DPK 2' WHEN 'SML2' THEN 'DPK 2' WHEN 'SML 2' THEN 'DPK 2'
                WHEN 'DPK3' THEN 'DPK 3' WHEN 'DPK 3' THEN 'DPK 3' WHEN 'SML3' THEN 'DPK 3' WHEN 'SML 3' THEN 'DPK 3'
                WHEN 'NPL' THEN 'M'
                WHEN 'PAY' THEN 'Pay' WHEN 'LUNAS' THEN 'Pay'
                WHEN 'L' THEN {$currentOrRestructured}
                ELSE {$normalizedDetail}
            END
        ";

        // Cross-check kolek dengan umur (umur_tunggakan, fallback ke
        // periode - LEAST(next_pmt_date, next_pmt_int_date) bila NULL).
        // Bila umur tetap tidak diketahui, kolek=2 jatuh ke DPK 3 dan kolek=4 ke D2
        // mengikuti SOP "worst-case dalam kelas kolek".
        return "
            CASE {$kolekValueExpression}
                WHEN 1 THEN {$currentOrRestructured}
                WHEN 2 THEN CASE
                    WHEN {$effectiveAge} < 31 THEN 'DPK 1'
                    WHEN {$effectiveAge} < 61 THEN 'DPK 2'
                    ELSE 'DPK 3'
                END
                WHEN 3 THEN 'KL'
                WHEN 4 THEN CASE WHEN {$effectiveAge} <= 150 THEN 'D1' ELSE 'D2' END
                WHEN 5 THEN 'M'
                ELSE CASE
                    WHEN {$effectiveAge} IS NOT NULL THEN {$ageBucketExpression}
                    ELSE {$detailBucketExpression}
                END
            END
        ";
    }

    private static function resolveKolekBucket(?int $kolek, $umurTunggakan, ?string $flagRestruk): ?string
    {
        $days = self::normalizeAge($umurTunggakan);

        return match ($kolek) {
            1 => self::normalizeValue($flagRestruk) === 'Y' ? 'LR' : 'L',
            2 => match (true) {
                $days !== null && $days < 31 => 'DPK 1',
                $days !== null && $days < 61 => 'DPK 2',
                $days !== null && $days < 91 => 'DPK 3',
                default => 'DPK 3',
            },
            3 => 'KL',
            4 => match (true) {
                $days !== null && $days <= 150 => 'D1',
                $days !== null && $days <= 180 => 'D2',
                default => 'D2',
            },
            5 => 'M',
            default => null,
        };
    }

    private static function resolveBaseBucket(?string $normalizedDetail, $umurTunggakan): string
    {
        $ageBucket = self::resolveAgeBucket($umurTunggakan);

        if ($ageBucket !== null) {
            return $ageBucket;
        }

        return $normalizedDetail ?? 'Unknown';
    }

    private static function resolveAgeBucket($umurTunggakan): ?string
    {
        $days = self::normalizeAge($umurTunggakan);

        if ($days === null) {
            return null;
        }

        return match (true) {
            $days <= 0 => 'L',
            $days <= 30 => 'DPK 1',
            $days <= 60 => 'DPK 2',
            $days <= 90 => 'DPK 3',
            $days <= 120 => 'KL',
            $days <= 150 => 'D1',
            $days <= 180 => 'D2',
            default => 'M',
        };
    }

    private static function normalizeDetailBucket(?string $kolekDetail): ?string
    {
        $normalizedDetail = self::normalizeValue($kolekDetail);

        return match ($normalizedDetail) {
            '', '0', '-' => null,
            'DPK1', 'DPK 1', 'SML1', 'SML 1' => 'DPK 1',
            'DPK2', 'DPK 2', 'SML2', 'SML 2' => 'DPK 2',
            'DPK3', 'DPK 3', 'SML3', 'SML 3' => 'DPK 3',
            'NPL' => 'M',
            'PAY', 'LUNAS' => 'Pay',
            'L', 'LR', 'DPK 1', 'DPK 2', 'DPK 3', 'KL', 'D1', 'D2', 'M', 'PAY' => $normalizedDetail,
            default => 'Unknown',
        };
    }

    private static function normalizeBucket(string $bucket, ?string $flagRestruk, ?string $normalizedDetail): string
    {
        $normalizedBucket = self::normalizeValue($bucket);

        if (
            $normalizedBucket === 'L'
            && in_array($normalizedDetail, ['L', 'LR'], true)
            && self::normalizeValue($flagRestruk) === 'Y'
        ) {
            return 'LR';
        }

        return match ($normalizedBucket) {
            'PAY', 'LUNAS' => 'Pay',
            'UNKNOWN' => 'Unknown',
            default => $normalizedBucket,
        };
    }

    private static function normalizeAge($umurTunggakan): ?int
    {
        if ($umurTunggakan === null) {
            return null;
        }

        $normalized = trim((string) $umurTunggakan);

        return $normalized === '' ? null : (int) $normalized;
    }

    private static function normalizeValue(?string $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    private static function normalizeKolekValue(?string $value): ?int
    {
        $normalized = self::normalizeValue($value);

        if ($normalized === '') {
            return null;
        }

        return is_numeric($normalized) ? (int) $normalized : null;
    }

    private static function qualifyColumn(?string $alias, string $column): string
    {
        return $alias ? "{$alias}.{$column}" : $column;
    }

    private static function buildNormalizedSqlValue(string $column): string
    {
        return "UPPER(TRIM(COALESCE({$column}, '')))";
    }
}
