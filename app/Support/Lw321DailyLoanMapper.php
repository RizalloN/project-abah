<?php

namespace App\Support;

use DateTimeInterface;

final class Lw321DailyLoanMapper
{
    /**
     * DESCRIPTION pada file acuan LW321 adalah sumber aturan segmen. Produk
     * menggunakan nama kanonis yang sudah dipakai Daily Loan Dinamis.
     *
     * @var array<int, array{segment:string, product:string, descriptions:array<int, string>}>
     */
    private const RULES = [
        [
            'segment' => 'Consumer',
            'product' => 'Briguna-Konsumer',
            'descriptions' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA KARYA',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEGAWAI BRI',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA PURNA',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA UMUM',
            ],
        ],
        [
            'segment' => 'Consumer',
            'product' => 'KPR',
            'descriptions' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) KOMERSIAL',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) SUBSIDI',
            ],
        ],
        [
            'segment' => 'Small',
            'product' => 'Commercial',
            'descriptions' => [
                '01. RITKOM - S/D Rp 50 JUTA',
                '02. RITKOM - > Rp.50 JUTA S/D Rp 100 JUTA',
                '03. RITKOM - > Rp 100 JUTA S/D Rp 350 JUTA',
                '04. RITKOM - > Rp 350 JUTA S/D Rp 500 JUTA',
                '05. RITKOM - > Rp 500 JUTA S/D Rp 1 M',
                '06. RITKOM - > Rp 1 M S/D Rp 2 M',
                '07. RITKOM - > Rp 2 M S/D Rp 3 M',
                '08. RITKOM - > Rp 3 M S/D Rp 4 M',
                '09. RITKOM - > Rp 4 M S/D Rp 5 M',
                'KREDIT PANGAN',
            ],
        ],
        [
            'segment' => 'Small',
            'product' => 'Cashcall',
            'descriptions' => ['CASHCOLL KREDIT SMALL'],
        ],
        [
            'segment' => 'Medium',
            'product' => 'Medium',
            'descriptions' => [
                '10. RITKOM -> Rp. 5 M S/D 15 M',
                '11. RITKOM -> Rp. 15 M S/D 25 M',
                '(KWL) 1. MENENGAH > Rp 25 M S/D 50 M',
                '(KWL) 2. MENENGAH > Rp 50 M S/D 200 M',
            ],
        ],
        [
            'segment' => 'Micro',
            'product' => 'Briguna-Mikro',
            'descriptions' => ['KREDIT MIKRO - GBT'],
        ],
        [
            'segment' => 'Micro',
            'product' => 'KUR-Kecil',
            'descriptions' => ['Kredit Mikro - KUR Ritel 2015'],
        ],
        [
            'segment' => 'Micro',
            'product' => 'KUR-Mikro',
            'descriptions' => ['KREDIT MIKRO - KUR MIKRO BARU'],
        ],
        [
            'segment' => 'Micro',
            'product' => 'Kupedes',
            'descriptions' => [
                'KREDIT MIKRO - KUPEDES',
                'KREDIT MIKRO - KUPEDES RAKYAT',
            ],
        ],
        [
            'segment' => 'Micro',
            'product' => 'KPR',
            'descriptions' => ['KREDITMIKRO - KPP'],
        ],
        [
            'segment' => 'Micro',
            'product' => 'Cash Collateral',
            'descriptions' => ['Kredit Mikro - Cash Collateral'],
        ],
    ];

    /**
     * @return array{segment:?string, product:?string, matched:bool}
     */
    public static function classifyDescription(?string $description): array
    {
        $token = self::normalizeToken($description);
        $rule = self::rulesByToken()[$token] ?? null;

        if ($rule === null) {
            return ['segment' => null, 'product' => null, 'matched' => false];
        }

        return [
            'segment' => $rule['segment'],
            'product' => $rule['product'],
            'matched' => true,
        ];
    }

    public static function resolveArrearsAge(
        string|DateTimeInterface|null $period,
        string|DateTimeInterface|null $nextPaymentDate,
        string|DateTimeInterface|null $nextInterestPaymentDate
    ): ?int {
        $periodTimestamp = self::dateTimestamp($period);
        if ($periodTimestamp === null) {
            return null;
        }

        $dueTimestamps = array_values(array_filter([
            self::dateTimestamp($nextPaymentDate),
            self::dateTimestamp($nextInterestPaymentDate),
        ], static fn (?int $timestamp): bool => $timestamp !== null));

        if ($dueTimestamps === []) {
            return null;
        }

        return (int) floor(($periodTimestamp - min($dueTimestamps)) / 86400);
    }

    /**
     * @return array{kolek:?string, kolek_detail:?string}
     */
    public static function qualityFromAge(?int $age, ?string $restructureFlag): array
    {
        if ($age === null) {
            return ['kolek' => null, 'kolek_detail' => null];
        }

        if ($age <= 0) {
            return [
                'kolek' => '1',
                'kolek_detail' => strtoupper(trim((string) $restructureFlag)) === 'Y' ? 'LR' : 'L',
            ];
        }

        return match (true) {
            $age <= 30 => ['kolek' => '2', 'kolek_detail' => 'DPK 1'],
            $age <= 60 => ['kolek' => '2', 'kolek_detail' => 'DPK 2'],
            $age <= 90 => ['kolek' => '2', 'kolek_detail' => 'DPK 3'],
            $age <= 120 => ['kolek' => '3', 'kolek_detail' => 'KL'],
            $age <= 150 => ['kolek' => '4', 'kolek_detail' => 'D1'],
            $age <= 180 => ['kolek' => '4', 'kolek_detail' => 'D2'],
            default => ['kolek' => '5', 'kolek_detail' => 'M'],
        };
    }

    public static function normalizeToken(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);

        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '';
    }

    public static function normalizedTokenSql(string $expression): string
    {
        // UPPER harus dijalankan sebelum REPLACE(..., CHAR(160), ...). Pada
        // MariaDB, CHAR(160) dapat membuat hasil REPLACE bertipe binary sehingga
        // UPPER di lapisan terluar tidak lagi mengubah huruf kecil.
        $sql = "UPPER(TRIM(COALESCE({$expression}, '')))";

        foreach ([
            'CHAR(160)', "' '", "'-'", "'_'", "'/'", "'.'", "'>'", "'<'",
            "'('", "')'", "','", "':'",
        ] as $character) {
            $sql = "REPLACE({$sql}, {$character}, '')";
        }

        return $sql;
    }

    public static function segmentSql(string $descriptionExpression): string
    {
        return self::classificationSql($descriptionExpression, 'segment');
    }

    public static function productSql(string $descriptionExpression): string
    {
        return self::classificationSql($descriptionExpression, 'product');
    }

    public static function arrearsAgeSql(
        string $periodExpression,
        string $nextPaymentDateExpression,
        string $nextInterestPaymentDateExpression
    ): string {
        return "DATEDIFF({$periodExpression}, CASE"
            ." WHEN {$nextPaymentDateExpression} IS NOT NULL AND {$nextInterestPaymentDateExpression} IS NOT NULL"
            ." THEN LEAST({$nextPaymentDateExpression}, {$nextInterestPaymentDateExpression})"
            ." WHEN {$nextPaymentDateExpression} IS NOT NULL THEN {$nextPaymentDateExpression}"
            ." WHEN {$nextInterestPaymentDateExpression} IS NOT NULL THEN {$nextInterestPaymentDateExpression}"
            .' ELSE NULL END)';
    }

    public static function kolekSql(string $ageExpression): string
    {
        return "CASE WHEN ({$ageExpression}) IS NULL THEN NULL"
            ." WHEN ({$ageExpression}) <= 0 THEN '1'"
            ." WHEN ({$ageExpression}) <= 90 THEN '2'"
            ." WHEN ({$ageExpression}) <= 120 THEN '3'"
            ." WHEN ({$ageExpression}) <= 180 THEN '4' ELSE '5' END";
    }

    public static function kolekDetailSql(string $ageExpression, string $restructureFlagExpression): string
    {
        $flag = "UPPER(TRIM(COALESCE({$restructureFlagExpression}, '')))";

        return "CASE WHEN ({$ageExpression}) IS NULL THEN NULL"
            ." WHEN ({$ageExpression}) <= 0 AND {$flag} = 'Y' THEN 'LR'"
            ." WHEN ({$ageExpression}) <= 0 THEN 'L'"
            ." WHEN ({$ageExpression}) <= 30 THEN 'DPK 1'"
            ." WHEN ({$ageExpression}) <= 60 THEN 'DPK 2'"
            ." WHEN ({$ageExpression}) <= 90 THEN 'DPK 3'"
            ." WHEN ({$ageExpression}) <= 120 THEN 'KL'"
            ." WHEN ({$ageExpression}) <= 150 THEN 'D1'"
            ." WHEN ({$ageExpression}) <= 180 THEN 'D2' ELSE 'M' END";
    }

    private static function classificationSql(string $descriptionExpression, string $field): string
    {
        $tokenSql = self::normalizedTokenSql($descriptionExpression);
        $cases = [];

        foreach (self::rulesGroupedForSql($field) as $value => $tokens) {
            $tokenList = implode(', ', array_map(self::quoteSqlLiteral(...), $tokens));
            $cases[] = "WHEN {$tokenSql} IN ({$tokenList}) THEN ".self::quoteSqlLiteral($value);
        }

        return '(CASE '.implode(' ', $cases).' ELSE NULL END)';
    }

    /**
     * @return array<string, array{segment:string, product:string}>
     */
    private static function rulesByToken(): array
    {
        static $rules;

        if ($rules !== null) {
            return $rules;
        }

        $rules = [];
        foreach (self::RULES as $rule) {
            foreach ($rule['descriptions'] as $description) {
                $rules[self::normalizeToken($description)] = [
                    'segment' => $rule['segment'],
                    'product' => $rule['product'],
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function rulesGroupedForSql(string $field): array
    {
        $grouped = [];
        foreach (self::rulesByToken() as $token => $rule) {
            $grouped[$rule[$field]][] = $token;
        }

        return $grouped;
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private static function dateTimestamp(string|DateTimeInterface|null $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            $timestamp = strtotime($value->format('Y-m-d'));

            return $timestamp === false ? null : $timestamp;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $timestamp = strtotime($normalized);

        if ($timestamp === false) {
            return null;
        }

        $dateTimestamp = strtotime(date('Y-m-d', $timestamp));

        return $dateTimestamp === false ? null : $dateTimestamp;
    }
}
