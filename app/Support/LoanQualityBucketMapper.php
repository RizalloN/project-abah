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

        $effectiveAge = "
            CASE
                WHEN {$umurTunggakanColumn} IS NOT NULL THEN {$umurTunggakanColumn}
                ELSE ({$derivedAge})
            END
        ";

        $kolekValueExpression = "
            CASE
                WHEN {$normalizedKolek} IN ('1', '1.0', '1.00') THEN 1
                WHEN {$normalizedKolek} IN ('2', '2.0', '2.00') THEN 2
                WHEN {$normalizedKolek} IN ('3', '3.0', '3.00') THEN 3
                WHEN {$normalizedKolek} IN ('4', '4.0', '4.00') THEN 4
                WHEN {$normalizedKolek} IN ('5', '5.0', '5.00') THEN 5
                ELSE NULL
            END
        ";

        $detailBucketExpression = "
            CASE
                WHEN {$normalizedDetail} IN ('', '0', '-') THEN NULL
                WHEN {$normalizedDetail} IN ('DPK1', 'DPK 1', 'SML1', 'SML 1') THEN 'DPK 1'
                WHEN {$normalizedDetail} IN ('DPK2', 'DPK 2', 'SML2', 'SML 2') THEN 'DPK 2'
                WHEN {$normalizedDetail} IN ('DPK3', 'DPK 3', 'SML3', 'SML 3') THEN 'DPK 3'
                WHEN {$normalizedDetail} = 'NPL' THEN 'M'
                WHEN {$normalizedDetail} IN ('PAY', 'LUNAS') THEN 'Pay'
                ELSE {$normalizedDetail}
            END
        ";

        // Cross-check kolek dengan umur (umur_tunggakan, fallback ke
        // periode - LEAST(next_pmt_date, next_pmt_int_date) bila NULL).
        // Bila umur tetap tidak diketahui, kolek=2 jatuh ke DPK 3 dan kolek=4 ke D2
        // mengikuti SOP "worst-case dalam kelas kolek".
        $kolekBucketExpression = "
            CASE
                WHEN ({$kolekValueExpression}) = 1 AND {$normalizedFlagRestruk} = 'Y' THEN 'LR'
                WHEN ({$kolekValueExpression}) = 1 THEN 'L'
                WHEN ({$kolekValueExpression}) = 2 AND ({$effectiveAge}) IS NOT NULL AND ({$effectiveAge}) < 31 THEN 'DPK 1'
                WHEN ({$kolekValueExpression}) = 2 AND ({$effectiveAge}) IS NOT NULL AND ({$effectiveAge}) < 61 THEN 'DPK 2'
                WHEN ({$kolekValueExpression}) = 2 AND ({$effectiveAge}) IS NOT NULL AND ({$effectiveAge}) < 91 THEN 'DPK 3'
                WHEN ({$kolekValueExpression}) = 2 THEN 'DPK 3'
                WHEN ({$kolekValueExpression}) = 3 THEN 'KL'
                WHEN ({$kolekValueExpression}) = 4 AND ({$effectiveAge}) IS NOT NULL AND ({$effectiveAge}) < 150 THEN 'D1'
                WHEN ({$kolekValueExpression}) = 4 AND ({$effectiveAge}) IS NOT NULL AND ({$effectiveAge}) < 180 THEN 'D2'
                WHEN ({$kolekValueExpression}) = 4 THEN 'D2'
                WHEN ({$kolekValueExpression}) = 5 THEN 'M'
                ELSE NULL
            END
        ";

        $bucketExpression = "
            CASE
                WHEN ({$kolekBucketExpression}) IS NOT NULL THEN ({$kolekBucketExpression})
                WHEN ({$effectiveAge}) IS NOT NULL THEN CASE
                    WHEN ({$effectiveAge}) <= 0 THEN 'L'
                    WHEN ({$effectiveAge}) <= 30 THEN 'DPK 1'
                    WHEN ({$effectiveAge}) <= 60 THEN 'DPK 2'
                    WHEN ({$effectiveAge}) <= 90 THEN 'DPK 3'
                    WHEN ({$effectiveAge}) <= 120 THEN 'KL'
                    WHEN ({$effectiveAge}) <= 150 THEN 'D1'
                    WHEN ({$effectiveAge}) <= 180 THEN 'D2'
                    ELSE 'M'
                END
                WHEN ({$detailBucketExpression}) IS NOT NULL THEN ({$detailBucketExpression})
                ELSE 'Unknown'
            END
        ";

        return "
            CASE
                WHEN ({$bucketExpression}) = 'L'
                    AND ({$detailBucketExpression}) IN ('L', 'LR')
                    AND {$normalizedFlagRestruk} = 'Y' THEN 'LR'
                ELSE ({$bucketExpression})
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
                $days !== null && $days < 150 => 'D1',
                $days !== null && $days < 180 => 'D2',
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
