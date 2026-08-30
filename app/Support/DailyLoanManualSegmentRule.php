<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class DailyLoanManualSegmentRule
{
    public const VERSION = 'daily-loan-manual-segment-v1';

    /**
     * Description is the business source of truth for the manual segment only.
     * Product remains sourced from the imported file.
     *
     * @var array<int, array{segment:string, descriptions:array<int, string>}>
     */
    private const RULES = [
        [
            'segment' => 'CONSUMER',
            'descriptions' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRETAP',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - KRETAP',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEGAWAI BRI',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - KREDIT PEGAWAI BRI',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KRESUN',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - KRESUN',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - BRIGUNA UMUM',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - BRIGUNA UMUM',
            ],
        ],
        [
            'segment' => 'CONSUMER',
            'descriptions' => [
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) KOMERSIAL',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - KREDIT PEMILIKAN RUMAH (KPR) KOMERSIAL',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLATERAL - KREDIT PEMILIKAN RUMAH (KPR) SUBSIDI',
                'KREDIT RITEL - KONSUMTIF - NON CASH COLLATERAL - KREDIT PEMILIKAN RUMAH (KPR) SUBSIDI',
            ],
        ],
        [
            'segment' => 'SMALL',
            'descriptions' => [
                '1. S/D Rp 50 JUTA',
                '01. S/D Rp 50 JUTA',
                '2. > Rp.50 JUTA S/D Rp 100 JUTA',
                '02. > Rp.50 JUTA S/D Rp 100 JUTA',
                '3. > Rp 100 JUTA S/D Rp 350 JUTA',
                '03. > Rp 100 JUTA S/D Rp 350 JUTA',
                '4. > Rp 350 JUTA S/D Rp 500 JUTA',
                '04. > Rp 350 JUTA S/D Rp 500 JUTA',
                '11. > Rp 350 JUTA S/D Rp 500 JUTA',
                '5. > Rp 500 JUTA S/D Rp 1 M',
                '05. > Rp 500 JUTA S/D Rp 1 M',
                '6. > Rp 1 M S/D Rp 2 M',
                '06. > Rp 1 M S/D Rp 2 M',
                '7. > Rp 2 M S/D Rp 3 M',
                '07. > Rp 2 M S/D Rp 3 M',
                '14. > Rp 2 M S/D Rp 3 M',
                '8. > Rp 3 M S/D Rp 4 M',
                '08. > Rp 3 M S/D Rp 4 M',
                '12. > Rp 3 M S/D Rp 4 M',
                '9. > Rp 4 M S/D Rp 5 M',
                '09. > Rp 4 M S/D Rp 5 M',
                '13. > Rp 4 M S/D Rp 5 M',
                'KREDIT PANGAN',
            ],
        ],
        [
            'segment' => 'SMALL',
            'descriptions' => [
                'CASHCOLL KREDIT SMALL',
            ],
        ],
        [
            'segment' => 'MEDIUM',
            'descriptions' => [
                '10. RITKOM -> Rp. 5 M S/D 15 M',
                '(KWL) 1. MENENGAH > Rp 25 M S/D 50 M',
                '(KWL) 2. MENENGAH > Rp 50 M S/D 200 M',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'KREDIT MIKRO - GBT',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'Kredit Mikro - KUR Ritel 2015',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'KUR Mikro Baru',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'Kupedes',
                'Kupedes Rakyat',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'KREDITMIKRO - KPP',
                'KREDIT MIKRO - KPP',
            ],
        ],
        [
            'segment' => 'MICRO',
            'descriptions' => [
                'Kredit Mikro - Cash Collateral',
            ],
        ],
    ];

    /**
     * @return array{segment:string, product:string, matched:bool}
     */
    public static function classify(
        ?string $description,
        ?string $fallbackSegment = null,
        ?string $fallbackProduct = null
    ): array {
        $descriptionToken = self::normalizeToken($description);

        foreach (self::normalizedRules() as $rule) {
            if (isset($rule['description_tokens'][$descriptionToken])) {
                return [
                    'segment' => $rule['segment'],
                    'product' => self::normalizeToken($fallbackProduct),
                    'matched' => true,
                ];
            }
        }

        return [
            'segment' => self::normalizeSegment($fallbackSegment),
            'product' => self::normalizeToken($fallbackProduct),
            'matched' => false,
        ];
    }

    public static function normalizeToken(?string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $value);

        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value))) ?? '';
    }

    public static function normalizeSegment(?string $value): string
    {
        $token = self::normalizeToken($value);

        return match ($token) {
            'CONSUMER', 'KONSUMER' => 'CONSUMER',
            'MICRO', 'MIKRO' => 'MICRO',
            'MEDIUM', 'MENENGAH' => 'MEDIUM',
            'SMALL' => 'SMALL',
            default => $token,
        };
    }

    public static function segmentSql(
        string $descriptionColumn = 'description',
        string $fallbackSegmentColumn = 'segmen_dashboard'
    ): string {
        return self::segmentClassificationSql(
            $descriptionColumn,
            self::normalizedSegmentSql($fallbackSegmentColumn)
        );
    }

    public static function productSql(string $fallbackProductColumn = 'produk_dashboard'): string
    {
        return self::normalizedTokenSql($fallbackProductColumn);
    }

    public static function shadowMismatchSql(
        string $descriptionColumn = 'description',
        string $fallbackSegmentColumn = 'segmen_dashboard',
        string $fallbackProductColumn = 'produk_dashboard',
        string $segmentShadowColumn = 'segmen_kinerja',
        string $productShadowColumn = 'produk_kinerja'
    ): string {
        $expectedSegment = self::segmentSql($descriptionColumn, $fallbackSegmentColumn);
        $expectedProduct = self::productSql($fallbackProductColumn);

        return "(UPPER(TRIM(COALESCE({$segmentShadowColumn}, ''))) <> {$expectedSegment}"
            . " OR UPPER(TRIM(COALESCE({$productShadowColumn}, ''))) <> {$expectedProduct})";
    }

    /**
     * Apply the one canonical predicate used by imports, jobs, and snapshots to
     * discover missing, stale, or reclassified Daily Loan shadow values.
     *
     * @param mixed $query
     * @param array<int, string> $requiredColumns
     */
    public static function applyPendingShadowPredicate($query, array $requiredColumns): void
    {
        if (Schema::hasColumn('daily_loan_dinamis', 'shadow_built_at')
            && Schema::hasColumn('daily_loan_dinamis', 'updated_at')) {
            $query
                ->whereNull('shadow_built_at')
                ->orWhereColumn('shadow_built_at', '<', 'updated_at');
        } else {
            foreach ($requiredColumns as $column) {
                $query->orWhereNull($column);
            }
        }

        if (self::hasManualClassificationColumns()) {
            $query->orWhereRaw(self::shadowMismatchSql());
        }

        if (Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus_normalized')
            && Schema::hasColumn('daily_loan_dinamis', 'pn_pemutus1')) {
            $query->orWhere(function ($pnQuery): void {
                $pnQuery->whereNull('pn_pemutus_normalized')
                    ->whereRaw("LENGTH(TRIM(COALESCE(pn_pemutus1, ''))) > 0");
            });
        }
    }

    private static function segmentClassificationSql(string $descriptionColumn, string $fallbackSql): string
    {
        $descriptionSql = self::normalizedTokenSql($descriptionColumn);
        $cases = [];

        foreach (self::normalizedRules() as $rule) {
            $tokens = array_keys($rule['description_tokens']);
            $tokenList = implode(', ', array_map(self::quoteSqlLiteral(...), $tokens));
            $result = self::quoteSqlLiteral($rule['segment']);
            $cases[] = "WHEN {$descriptionSql} IN ({$tokenList}) THEN {$result}";
        }

        return '(CASE ' . implode(' ', $cases) . " ELSE {$fallbackSql} END)";
    }

    private static function normalizedSegmentSql(string $column): string
    {
        $tokenSql = self::normalizedTokenSql($column);

        return "(CASE WHEN {$tokenSql} IN ('CONSUMER', 'KONSUMER') THEN 'CONSUMER'"
            . " WHEN {$tokenSql} IN ('MICRO', 'MIKRO') THEN 'MICRO'"
            . " WHEN {$tokenSql} IN ('MEDIUM', 'MENENGAH') THEN 'MEDIUM'"
            . " WHEN {$tokenSql} = 'SMALL' THEN 'SMALL' ELSE {$tokenSql} END)";
    }

    private static function normalizedTokenSql(string $column): string
    {
        $sql = "TRIM(COALESCE({$column}, ''))";

        foreach ([
            'CHAR(160)',
            "' '",
            "'-'",
            "'_'",
            "'/'",
            "'.'",
            "'>'",
            "'<'",
            "'('",
            "')'",
            "','",
            "':'",
        ] as $character) {
            $sql = "REPLACE({$sql}, {$character}, '')";
        }

        return "UPPER({$sql})";
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @return array<int, array{segment:string, description_tokens:array<string, true>}>
     */
    private static function normalizedRules(): array
    {
        static $rules;

        if ($rules !== null) {
            return $rules;
        }

        $rules = [];
        foreach (self::RULES as $rule) {
            $tokens = [];
            foreach ($rule['descriptions'] as $description) {
                $token = self::normalizeToken($description);
                if ($token !== '') {
                    $tokens[$token] = true;
                }
            }

            $rules[] = [
                'segment' => $rule['segment'],
                'description_tokens' => $tokens,
            ];
        }

        return $rules;
    }

    private static function hasManualClassificationColumns(): bool
    {
        foreach ([
            'description',
            'segmen_dashboard',
            'produk_dashboard',
            'segmen_kinerja',
            'produk_kinerja',
        ] as $column) {
            if (!Schema::hasColumn('daily_loan_dinamis', $column)) {
                return false;
            }
        }

        return true;
    }
}
