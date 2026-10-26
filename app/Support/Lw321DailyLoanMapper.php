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
            // Daily Loan Dinamis membukukan RITKOM 5-25 M sebagai Small/Commercial
            // dengan segmen kinerja MEDIUM (terverifikasi pada periode overlap).
            'segment' => 'Small',
            'product' => 'Commercial',
            'performance_segment' => 'MEDIUM',
            'descriptions' => [
                '10. RITKOM -> Rp. 5 M S/D 15 M',
                '11. RITKOM -> Rp. 15 M S/D 25 M',
            ],
        ],
        [
            'segment' => 'Medium',
            'product' => 'Medium',
            'descriptions' => [
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
     * DESCRIPTION LW321 yang ditulis berbeda oleh Daily Loan Dinamis untuk
     * rekening yang sama pada tanggal yang sama. DESCRIPTION lain identik.
     *
     * @var array<string, string>
     */
    private const DAILY_DESCRIPTIONS = [
        '01. RITKOM - S/D Rp 50 JUTA' => '01. S/D Rp 50 JUTA',
        '02. RITKOM - > Rp.50 JUTA S/D Rp 100 JUTA' => '02. > Rp.50 JUTA S/D Rp 100 JUTA',
        '03. RITKOM - > Rp 100 JUTA S/D Rp 350 JUTA' => '03. > Rp 100 JUTA S/D Rp 350 JUTA',
        '04. RITKOM - > Rp 350 JUTA S/D Rp 500 JUTA' => '04. > Rp 350 JUTA S/D Rp 500 JUTA',
        '05. RITKOM - > Rp 500 JUTA S/D Rp 1 M' => '05. > Rp 500 JUTA S/D Rp 1 M',
        '06. RITKOM - > Rp 1 M S/D Rp 2 M' => '06. > Rp 1 M S/D Rp 2 M',
        '07. RITKOM - > Rp 2 M S/D Rp 3 M' => '07. > Rp 2 M S/D Rp 3 M',
        '08. RITKOM - > Rp 3 M S/D Rp 4 M' => '08. > Rp 3 M S/D Rp 4 M',
        '09. RITKOM - > Rp 4 M S/D Rp 5 M' => '09. > Rp 4 M S/D Rp 5 M',
        'KREDIT MIKRO - KUPEDES' => 'Kupedes',
        'KREDIT MIKRO - KUPEDES RAKYAT' => 'Kupedes Rakyat',
        'KREDIT MIKRO - KUR MIKRO BARU' => 'KUR Mikro Baru',
        'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA KARYA' => 'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRETAP',
        'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA PURNA' => 'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRESUN',
    ];

    /**
     * @return array{segment:?string, product:?string, performance_segment:?string, performance_product:?string, matched:bool}
     */
    public static function classifyDescription(?string $description): array
    {
        $token = self::normalizeToken($description);
        $rule = self::rulesByToken()[$token] ?? null;

        if ($rule === null) {
            return [
                'segment' => null,
                'product' => null,
                'performance_segment' => null,
                'performance_product' => null,
                'matched' => false,
            ];
        }

        return [
            'segment' => $rule['segment'],
            'product' => $rule['product'],
            'performance_segment' => $rule['performance_segment'],
            'performance_product' => $rule['performance_product'],
            'matched' => true,
        ];
    }

    /** DESCRIPTION dalam kosakata Daily Loan Dinamis; teks lain dipertahankan apa adanya. */
    public static function dailyDescription(?string $description): ?string
    {
        $normalized = trim((string) $description);
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return self::dailyDescriptionsByToken()[self::normalizeToken($normalized)] ?? $normalized;
    }

    public static function dailyDescriptionSql(string $descriptionExpression): string
    {
        $tokenSql = self::normalizedTokenSql($descriptionExpression);
        $cases = [];
        foreach (self::dailyDescriptionsByToken() as $token => $dailyDescription) {
            $cases[] = 'WHEN '.self::quoteSqlLiteral($token).' THEN '.self::quoteSqlLiteral($dailyDescription);
        }

        return "(CASE {$tokenSql} ".implode(' ', $cases)
            ." ELSE NULLIF(NULLIF(TRIM({$descriptionExpression}), ''), '-') END)";
    }

    public static function performanceSegmentSql(string $descriptionExpression): string
    {
        return self::classificationSql($descriptionExpression, 'performance_segment');
    }

    public static function performanceProductSql(string $descriptionExpression): string
    {
        return self::classificationSql($descriptionExpression, 'performance_product');
    }

    /** LW321 menulis suku bunga dalam persen (6.000000), Daily Loan dalam pecahan (0.060000). */
    public static function dailyRate(mixed $rate): ?float
    {
        if ($rate === null || trim((string) $rate) === '' || ! is_numeric($rate)) {
            return null;
        }

        return round(((float) $rate) / 100, 6);
    }

    public static function dailyRateSql(string $rateExpression): string
    {
        return "ROUND(({$rateExpression}) / 100, 6)";
    }

    /** LW321 menulis jangka waktu "36M", Daily Loan "36". */
    public static function dailyTenor(?string $tenor): ?string
    {
        $normalized = trim((string) $tenor);
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        return preg_match('/^(\d+)\s*M$/i', $normalized, $matches) === 1 ? $matches[1] : $normalized;
    }

    public static function dailyTenorSql(string $tenorExpression): string
    {
        $tenor = "NULLIF(NULLIF(TRIM(COALESCE({$tenorExpression}, '')), ''), '-')";

        return "(CASE WHEN {$tenor} REGEXP '^[0-9]+[[:space:]]*[Mm]$'"
            ." THEN TRIM(SUBSTRING({$tenor}, 1, CHAR_LENGTH({$tenor}) - 1)) ELSE {$tenor} END)";
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

    /**
     * Kolom saldo per kolektibilitas pada LW321, urut dari kualitas terburuk.
     * Kolom ini identik dengan kolektibilitas Daily Loan Dinamis pada tanggal yang sama.
     *
     * @var array<string, string>
     */
    public const KOLEK_BALANCE_COLUMNS = [
        '5' => 'kolektibilitas_macet',
        '4' => 'kolektibilitas_diragukan',
        '3' => 'kolektibilitas_kurang_lancar',
        '2' => 'kolektibilitas_dpk',
        '1' => 'kolektibilitas_lancar',
    ];

    /**
     * @param  array<string, string>  $columnExpressions  kolek => ekspresi kolom saldo yang tersedia
     */
    public static function balanceKolekSql(array $columnExpressions): string
    {
        $cases = [];
        foreach (array_keys(self::KOLEK_BALANCE_COLUMNS) as $kolek) {
            if (isset($columnExpressions[$kolek])) {
                $cases[] = "WHEN COALESCE({$columnExpressions[$kolek]}, 0) <> 0 THEN '{$kolek}'";
            }
        }

        return $cases === [] ? 'NULL' : '(CASE '.implode(' ', $cases).' ELSE NULL END)';
    }

    /** @param array<string, mixed> $row baris mentah LW321 */
    public static function kolekFromBalances(array $row): ?string
    {
        foreach (self::KOLEK_BALANCE_COLUMNS as $kolek => $column) {
            $value = $row[$column] ?? null;
            if (is_numeric($value) && (float) $value != 0.0) {
                return (string) $kolek;
            }
        }

        return null;
    }

    public static function earliestDueDateSql(string $nextPaymentDateExpression, string $nextInterestPaymentDateExpression): string
    {
        return 'CASE'
            ." WHEN {$nextPaymentDateExpression} IS NOT NULL AND {$nextInterestPaymentDateExpression} IS NOT NULL"
            ." THEN LEAST({$nextPaymentDateExpression}, {$nextInterestPaymentDateExpression})"
            ." WHEN {$nextPaymentDateExpression} IS NOT NULL THEN {$nextPaymentDateExpression}"
            ." WHEN {$nextInterestPaymentDateExpression} IS NOT NULL THEN {$nextInterestPaymentDateExpression}"
            .' ELSE NULL END';
    }

    /**
     * Umur tunggakan versi Daily Loan Dinamis: hari sejak (jatuh tempo terdekat - 1 hari),
     * dibatasi nol untuk kolektibilitas lancar.
     */
    public static function dailyArrearsAgeSql(string $rawAgeExpression, string $kolekExpression): string
    {
        return "CASE WHEN ({$rawAgeExpression}) IS NULL THEN NULL"
            ." WHEN ({$kolekExpression}) = '1' THEN GREATEST(0, ({$rawAgeExpression}) + 1)"
            ." ELSE ({$rawAgeExpression}) + 1 END";
    }

    public static function dailyArrearsAge(?int $rawAge, ?string $kolek): ?int
    {
        if ($rawAge === null) {
            return null;
        }

        return $kolek === '1' ? max(0, $rawAge + 1) : $rawAge + 1;
    }

    /** Tanggal menunggak versi Daily Loan Dinamis: jatuh tempo - 1 hari, maksimal posisi untuk kredit lancar. */
    public static function dailyArrearsStartSql(string $periodExpression, string $dueDateExpression, string $kolekExpression): string
    {
        return "CASE WHEN ({$dueDateExpression}) IS NULL THEN NULL"
            ." WHEN ({$kolekExpression}) = '1' THEN LEAST({$periodExpression}, DATE_SUB({$dueDateExpression}, INTERVAL 1 DAY))"
            ." ELSE DATE_SUB({$dueDateExpression}, INTERVAL 1 DAY) END";
    }

    public static function dailyArrearsStart(
        string|DateTimeInterface|null $period,
        string|DateTimeInterface|null $nextPaymentDate,
        string|DateTimeInterface|null $nextInterestPaymentDate,
        ?string $kolek
    ): ?string {
        $periodTimestamp = self::dateTimestamp($period);
        $dueTimestamps = array_values(array_filter([
            self::dateTimestamp($nextPaymentDate),
            self::dateTimestamp($nextInterestPaymentDate),
        ], static fn (?int $timestamp): bool => $timestamp !== null));
        if ($dueTimestamps === []) {
            return null;
        }

        $start = strtotime('-1 day', min($dueTimestamps));
        if ($kolek === '1' && $periodTimestamp !== null) {
            $start = min($start, $periodTimestamp);
        }

        return date('Y-m-d', $start);
    }

    /** Detail kolektibilitas versi Daily Loan Dinamis (lancar selalu "L"; LR diturunkan laporan dari flag restruk). */
    public static function dailyKolekDetailSql(string $kolekExpression, string $ageExpression): string
    {
        return "CASE ({$kolekExpression})"
            ." WHEN '1' THEN 'L'"
            ." WHEN '2' THEN CASE WHEN ({$ageExpression}) <= 30 THEN 'DPK 1' WHEN ({$ageExpression}) <= 60 THEN 'DPK 2' ELSE 'DPK 3' END"
            ." WHEN '3' THEN 'KL'"
            ." WHEN '4' THEN CASE WHEN ({$ageExpression}) <= 150 THEN 'D1' ELSE 'D2' END"
            ." WHEN '5' THEN 'M' ELSE NULL END";
    }

    public static function dailyKolekDetail(?string $kolek, ?int $age): ?string
    {
        return match ($kolek) {
            '1' => 'L',
            '2' => ($age ?? 0) <= 30 ? 'DPK 1' : (($age ?? 0) <= 60 ? 'DPK 2' : 'DPK 3'),
            '3' => 'KL',
            '4' => ($age ?? 0) <= 150 ? 'D1' : 'D2',
            '5' => 'M',
            default => null,
        };
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
     * @return array<string, array{segment:string, product:string, performance_segment:string, performance_product:string}>
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
                    'performance_segment' => $rule['performance_segment'] ?? strtoupper($rule['segment']),
                    'performance_product' => $rule['performance_product'] ?? self::normalizeToken($rule['product']),
                ];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private static function dailyDescriptionsByToken(): array
    {
        static $descriptions;

        if ($descriptions !== null) {
            return $descriptions;
        }

        $descriptions = [];
        foreach (self::DAILY_DESCRIPTIONS as $lwDescription => $dailyDescription) {
            $descriptions[self::normalizeToken($lwDescription)] = $dailyDescription;
        }

        return $descriptions;
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
