<?php

namespace App\Support;

final class LoanQualityBucketMapper
{
    public static function map(
        ?string $kolekDetail,
        $umurTunggakan,
        ?string $flagRestruk,
        ?string $kolAdk1 = null,
        ?string $kolek = null
    ): string {
        $normalizedDetail = self::normalizeDetailBucket($kolekDetail);
        $bucket = self::resolveBaseBucket($normalizedDetail, $umurTunggakan);

        return self::normalizeBucket($bucket, $flagRestruk, $normalizedDetail);
    }

    public static function buildSqlExpression(?string $alias = null): string
    {
        $kolekDetailColumn = self::qualifyColumn($alias, 'kolek_detail');
        $umurTunggakanColumn = self::qualifyColumn($alias, 'umur_tunggakan');
        $flagRestrukColumn = self::qualifyColumn($alias, 'flag_restruk');

        $normalizedDetail = self::buildNormalizedSqlValue($kolekDetailColumn);
        $normalizedFlagRestruk = self::buildNormalizedSqlValue($flagRestrukColumn);

        $detailBucketExpression = "
            CASE
                WHEN {$normalizedDetail} IN ('', '0', '-') THEN NULL
                WHEN {$normalizedDetail} IN ('DPK1', 'SML1') THEN 'DPK 1'
                WHEN {$normalizedDetail} IN ('DPK2', 'SML2') THEN 'DPK 2'
                WHEN {$normalizedDetail} IN ('DPK3', 'SML3') THEN 'DPK 3'
                WHEN {$normalizedDetail} = 'NPL' THEN 'M'
                WHEN {$normalizedDetail} IN ('PAY', 'LUNAS') THEN 'Pay'
                ELSE {$normalizedDetail}
            END
        ";

        $bucketExpression = "
            CASE
                WHEN {$umurTunggakanColumn} IS NOT NULL THEN CASE
                    WHEN {$umurTunggakanColumn} <= 0 THEN 'L'
                    WHEN {$umurTunggakanColumn} <= 30 THEN 'DPK 1'
                    WHEN {$umurTunggakanColumn} <= 60 THEN 'DPK 2'
                    WHEN {$umurTunggakanColumn} <= 90 THEN 'DPK 3'
                    WHEN {$umurTunggakanColumn} <= 120 THEN 'KL'
                    WHEN {$umurTunggakanColumn} <= 150 THEN 'D1'
                    WHEN {$umurTunggakanColumn} <= 180 THEN 'D2'
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
            'DPK1', 'SML1' => 'DPK 1',
            'DPK2', 'SML2' => 'DPK 2',
            'DPK3', 'SML3' => 'DPK 3',
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

    private static function qualifyColumn(?string $alias, string $column): string
    {
        return $alias ? "{$alias}.{$column}" : $column;
    }

    private static function buildNormalizedSqlValue(string $column): string
    {
        return "UPPER(TRIM(COALESCE({$column}, '')))";
    }
}
