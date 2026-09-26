<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class Lw321DailyLoanSyncService
{
    public const SOURCE_TABLE = 'lw321pn';

    public const TARGET_TABLE = 'daily_loan_dinamis';

    public const TARGET_ID_PREFIX = 'LW321PN:';

    private const TARGET_WRITE_LOCK = 'project_abah:table_write:daily_loan_dinamis';

    private const LOCK_WAIT_SECONDS = 300;

    /**
     * @return array{
     *     periods:array<int,string>,
     *     source_rows:int,
     *     inserted_rows:int,
     *     deleted_rows:int,
     *     mutated_periods:array<int,string>,
     *     skipped_periods:array<int,string>,
     *     validation:array{status:string,periods:array<string,array<string,mixed>>}
     * }
     */
    public function synchronize(?string $periodHint = null): array
    {
        $this->assertSchemaReady();

        $periods = $this->resolvePeriods($periodHint);
        $result = [
            'periods' => $periods,
            'source_rows' => 0,
            'inserted_rows' => 0,
            'deleted_rows' => 0,
            'mutated_periods' => [],
            'skipped_periods' => [],
            'validation' => [
                'status' => 'passed',
                'periods' => [],
            ],
        ];

        foreach ($periods as $period) {
            $periodResult = $this->synchronizePeriod($period);
            $result['source_rows'] += $periodResult['source_rows'];
            $result['inserted_rows'] += $periodResult['inserted_rows'];
            $result['deleted_rows'] += $periodResult['deleted_rows'];
            $result['validation']['periods'][$period] = $periodResult['validation'];

            if ($periodResult['mutated']) {
                $result['mutated_periods'][] = $period;
            }
            if ($periodResult['skipped']) {
                $result['skipped_periods'][] = $period;
            }
        }

        return $result;
    }

    /**
     * Validate a freshly imported source period before its import transaction
     * is committed. This keeps deterministic data failures out of the snapshot
     * queue and uses the same rules as materialization.
     *
     * @return array{
     *     row_count:int,
     *     blank_account_rows:int,
     *     null_balance_rows:int,
     *     zero_balance_rows:int,
     *     nonzero_balance_rows:int,
     *     duplicate_account_groups:int,
     *     duplicate_account_rows:int,
     *     duplicate_excess_rows:int,
     *     blank_description_rows:int,
     *     mapped_description_rows:int,
     *     unmapped_description_rows:int,
     *     balance_total:string
     * }
     */
    public function validateSourcePeriod(string $period): array
    {
        $this->assertSchemaReady();

        $normalizedPeriod = StrictDateParser::normalize(trim($period));
        if ($normalizedPeriod === null) {
            throw new RuntimeException("Periode LW321 tidak valid: {$period}.");
        }

        $metrics = $this->sourceValidationMetrics($normalizedPeriod);
        $this->assertSourcePeriodIsSafe($normalizedPeriod, $metrics);

        return $metrics;
    }

    /**
     * @return array{source_rows:int,inserted_rows:int,deleted_rows:int,mutated:bool,skipped:bool,validation:array<string,mixed>}
     */
    private function synchronizePeriod(string $period): array
    {
        $lockAcquired = false;
        $usesMysqlLock = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        try {
            if ($usesMysqlLock) {
                $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
                    self::TARGET_WRITE_LOCK,
                    self::LOCK_WAIT_SECONDS,
                ]);
                $lockAcquired = (int) ($row->acquired ?? 0) === 1;
                if (! $lockAcquired) {
                    throw new RuntimeException('Sinkronisasi LW321 menunggu proses tulis Daily Loan lain terlalu lama.');
                }
            }

            return DB::transaction(function () use ($period, $usesMysqlLock): array {
                $conflictingRows = (int) DB::table(self::TARGET_TABLE)
                    ->where('periode', $period)
                    ->where(function ($query): void {
                        $query->whereNull('uniqueid_namareport')
                            ->orWhere('uniqueid_namareport', 'not like', self::TARGET_ID_PREFIX.'%');
                    })
                    ->count();

                if ($conflictingRows > 0) {
                    if ($usesMysqlLock) {
                        DB::statement('SET @skip_snapshot_invalidation = 1');
                    }

                    $deletedRows = (int) DB::table(self::TARGET_TABLE)
                        ->where('periode', $period)
                        ->where('uniqueid_namareport', 'like', self::TARGET_ID_PREFIX.'%')
                        ->delete();

                    return [
                        'source_rows' => (int) DB::table(self::SOURCE_TABLE)->where('periode', $period)->count(),
                        'inserted_rows' => 0,
                        'deleted_rows' => $deletedRows,
                        'mutated' => $deletedRows > 0,
                        'skipped' => true,
                        'validation' => [
                            'status' => 'skipped_daily_precedence',
                            'authoritative_daily_rows' => $conflictingRows,
                            'stale_lw_rows_removed' => $deletedRows,
                        ],
                    ];
                }

                $sourceValidation = $this->sourceValidationMetrics($period);
                $sourceRows = $sourceValidation['row_count'];

                $this->assertSourcePeriodIsSafe($period, $sourceValidation);

                if ($usesMysqlLock) {
                    DB::statement('SET @skip_snapshot_invalidation = 1');
                }

                $deletedRows = (int) DB::table(self::TARGET_TABLE)
                    ->where('periode', $period)
                    ->where('uniqueid_namareport', 'like', self::TARGET_ID_PREFIX.'%')
                    ->delete();

                $insertedRows = $sourceRows > 0
                    ? ($usesMysqlLock ? $this->insertWithSelect($period) : $this->insertPortable($period))
                    : 0;

                if ($insertedRows !== $sourceRows) {
                    throw new RuntimeException(sprintf(
                        'Materialisasi LW321 periode %s tidak lengkap: %d dari %d baris. Transaksi dibatalkan.',
                        $period,
                        $insertedRows,
                        $sourceRows
                    ));
                }

                $targetValidation = $this->targetValidationMetrics($period);
                $rowCountMatches = $targetValidation['row_count'] === $sourceRows;
                $balanceTotalMatches = $this->balanceTotalsMatch(
                    $sourceValidation['balance_total'],
                    $targetValidation['balance_total']
                );
                $classificationComplete = $targetValidation['unclassified_rows'] === 0;
                $qualityComplete = $targetValidation['unresolved_quality_rows'] === 0;

                if (! $rowCountMatches
                    || ! $balanceTotalMatches
                    || ! $classificationComplete
                    || ! $qualityComplete
                    || $targetValidation['blank_account_rows'] > 0
                    || $targetValidation['null_balance_rows'] > 0) {
                    throw new RuntimeException(sprintf(
                        'Materialisasi LW321 periode %s gagal validasi target: rows %d/%d, saldo %s/%s, rekening kosong %d, saldo NULL %d, klasifikasi kosong %d, kualitas kosong %d. Transaksi dibatalkan.',
                        $period,
                        $targetValidation['row_count'],
                        $sourceRows,
                        $targetValidation['balance_total'],
                        $sourceValidation['balance_total'],
                        $targetValidation['blank_account_rows'],
                        $targetValidation['null_balance_rows'],
                        $targetValidation['unclassified_rows'],
                        $targetValidation['unresolved_quality_rows']
                    ));
                }

                return [
                    'source_rows' => $sourceRows,
                    'inserted_rows' => $insertedRows,
                    'deleted_rows' => $deletedRows,
                    'mutated' => $deletedRows > 0 || $insertedRows > 0,
                    'skipped' => false,
                    'validation' => [
                        'status' => $sourceRows === 0 ? 'empty_source_cleanup' : 'passed',
                        'source' => $sourceValidation,
                        'target' => $targetValidation,
                        'parity' => [
                            'row_count_matches' => $rowCountMatches,
                            'balance_total_matches' => $balanceTotalMatches,
                            'classification_complete' => $classificationComplete,
                            'quality_complete' => $qualityComplete,
                        ],
                    ],
                ];
            });
        } finally {
            if ($usesMysqlLock) {
                try {
                    DB::statement('SET @skip_snapshot_invalidation = NULL');
                } catch (\Throwable) {
                }
            }

            if ($lockAcquired) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::TARGET_WRITE_LOCK]);
                } catch (\Throwable) {
                }
            }
        }
    }

    /**
     * @return array{
     *     row_count:int,
     *     blank_account_rows:int,
     *     null_balance_rows:int,
     *     zero_balance_rows:int,
     *     nonzero_balance_rows:int,
     *     duplicate_account_groups:int,
     *     duplicate_account_rows:int,
     *     duplicate_excess_rows:int,
     *     blank_description_rows:int,
     *     mapped_description_rows:int,
     *     unmapped_description_rows:int,
     *     balance_total:string
     * }
     */
    private function sourceValidationMetrics(string $period): array
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return $this->sourceValidationMetricsPortable($period);
        }

        $sourceSegment = Lw321DailyLoanMapper::segmentSql('l.description');
        $sourceProduct = Lw321DailyLoanMapper::productSql('l.description');
        $age = Lw321DailyLoanMapper::arrearsAgeSql('l.periode', 'l.next_pmt_date', 'l.next_int_pmt_date');
        $derivedKolek = Lw321DailyLoanMapper::kolekSql($age);
        $metrics = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS row_count,
                SUM(CASE WHEN l.no_rekening IS NULL OR TRIM(l.no_rekening) = '' OR TRIM(l.no_rekening) = '-' THEN 1 ELSE 0 END) AS blank_account_rows,
                SUM(CASE WHEN l.balance_dalam_idr IS NULL THEN 1 ELSE 0 END) AS null_balance_rows,
                SUM(CASE WHEN l.balance_dalam_idr = 0 THEN 1 ELSE 0 END) AS zero_balance_rows,
                SUM(CASE WHEN l.balance_dalam_idr IS NOT NULL AND l.balance_dalam_idr <> 0 THEN 1 ELSE 0 END) AS nonzero_balance_rows,
                SUM(CASE WHEN l.description IS NULL OR TRIM(l.description) = '' OR TRIM(l.description) = '-' THEN 1 ELSE 0 END) AS blank_description_rows,
                SUM(CASE WHEN ({$sourceSegment}) IS NOT NULL THEN 1 ELSE 0 END) AS mapped_description_rows,
                SUM(CASE WHEN l.description IS NOT NULL AND TRIM(l.description) <> '' AND TRIM(l.description) <> '-' AND ({$sourceSegment}) IS NULL THEN 1 ELSE 0 END) AS unmapped_description_rows,
                SUM(CASE WHEN ({$age}) IS NULL OR ({$derivedKolek}) IS NULL THEN 1 ELSE 0 END) AS unresolved_quality_rows,
                SUM(CASE WHEN ({$derivedKolek}) IS NOT NULL AND NULLIF(TRIM(COALESCE(l.kol_adk, '')), '') IS NOT NULL
                    AND TRIM(l.kol_adk) <> ({$derivedKolek}) THEN 1 ELSE 0 END) AS raw_kol_mismatch_rows,
                COALESCE(SUM(l.balance_dalam_idr), 0) AS balance_total
            FROM `lw321pn` l
            WHERE l.periode = ?
            SQL, [$period]);

        $accountKey = $this->sourceAccountKeyExpression('no_rekening');
        $duplicateAccounts = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->whereNotNull('no_rekening')
            ->whereRaw("TRIM(no_rekening) <> ''")
            ->whereRaw("TRIM(no_rekening) <> '-'")
            ->selectRaw("{$accountKey} AS account_key, COUNT(*) AS account_rows")
            ->groupByRaw($accountKey)
            ->havingRaw('COUNT(*) > 1');

        $duplicates = DB::query()
            ->fromSub($duplicateAccounts, 'duplicate_accounts')
            ->selectRaw('COUNT(*) AS duplicate_account_groups, COALESCE(SUM(account_rows), 0) AS duplicate_account_rows')
            ->first();

        $duplicateGroups = (int) ($duplicates->duplicate_account_groups ?? 0);
        $duplicateRows = (int) ($duplicates->duplicate_account_rows ?? 0);

        $result = [
            'row_count' => (int) ($metrics->row_count ?? 0),
            'blank_account_rows' => (int) ($metrics->blank_account_rows ?? 0),
            'null_balance_rows' => (int) ($metrics->null_balance_rows ?? 0),
            'zero_balance_rows' => (int) ($metrics->zero_balance_rows ?? 0),
            'nonzero_balance_rows' => (int) ($metrics->nonzero_balance_rows ?? 0),
            'duplicate_account_groups' => $duplicateGroups,
            'duplicate_account_rows' => $duplicateRows,
            'duplicate_excess_rows' => max(0, $duplicateRows - $duplicateGroups),
            'blank_description_rows' => (int) ($metrics->blank_description_rows ?? 0),
            'mapped_description_rows' => (int) ($metrics->mapped_description_rows ?? 0),
            'unmapped_description_rows' => (int) ($metrics->unmapped_description_rows ?? 0),
            'unresolved_classification_rows' => 0,
            'account_reference_rows' => 0,
            'description_reference_rows' => (int) ($metrics->mapped_description_rows ?? 0),
            'unresolved_quality_rows' => (int) ($metrics->unresolved_quality_rows ?? 0),
            'raw_kol_mismatch_rows' => (int) ($metrics->raw_kol_mismatch_rows ?? 0),
            'classification_evaluated' => false,
            'reference_daily_period' => null,
            'balance_total' => $this->balanceMetric($metrics->balance_total ?? 0),
        ];

        $canEvaluateClassification = $result['blank_account_rows'] === 0
            && $result['null_balance_rows'] === 0
            && $result['nonzero_balance_rows'] > 0
            && $result['duplicate_account_groups'] === 0
            && $result['unresolved_quality_rows'] === 0;
        if (! $canEvaluateClassification) {
            return $result;
        }

        $unresolvedSourceRows = $result['blank_description_rows'] + $result['unmapped_description_rows'];
        if ($unresolvedSourceRows === 0) {
            $result['classification_evaluated'] = true;

            return $result;
        }

        $resolution = $this->sourceResolutionSqlContext($period);
        $periodLiteral = self::quoteSqlLiteral($period);
        $resolved = DB::selectOne(<<<SQL
            SELECT
                SUM(CASE WHEN {$resolution['segment']} IS NULL OR {$resolution['product']} IS NULL THEN 1 ELSE 0 END) AS unresolved_classification_rows,
                SUM(CASE WHEN {$resolution['resolution_source']} = 'ACCOUNT' THEN 1 ELSE 0 END) AS account_reference_rows
            FROM `lw321pn` l
            {$resolution['joins']}
            WHERE l.periode = {$periodLiteral}
              AND (({$sourceSegment}) IS NULL OR ({$sourceProduct}) IS NULL)
            SQL);

        $result['unresolved_classification_rows'] = (int) ($resolved->unresolved_classification_rows ?? 0);
        $result['account_reference_rows'] = (int) ($resolved->account_reference_rows ?? 0);
        $result['classification_evaluated'] = true;
        $result['reference_daily_period'] = $resolution['previous_period'];

        return $result;
    }

    /**
     * @param  array{
     *     row_count:int,
     *     blank_account_rows:int,
     *     null_balance_rows:int,
     *     zero_balance_rows:int,
     *     nonzero_balance_rows:int,
     *     duplicate_account_groups:int,
     *     duplicate_account_rows:int,
     *     duplicate_excess_rows:int,
     *     blank_description_rows:int,
     *     mapped_description_rows:int,
     *     unmapped_description_rows:int,
     *     balance_total:string
     * }  $metrics
     */
    private function assertSourcePeriodIsSafe(string $period, array $metrics): void
    {
        if ($metrics['row_count'] === 0) {
            return;
        }

        if ($metrics['blank_account_rows'] > 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: %d baris memiliki nomor rekening kosong.',
                $period,
                $metrics['blank_account_rows']
            ));
        }

        if ($metrics['null_balance_rows'] > 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: %d baris memiliki balance_dalam_idr NULL. Periksa mapping CBAL_Base.',
                $period,
                $metrics['null_balance_rows']
            ));
        }

        if ($metrics['nonzero_balance_rows'] === 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: seluruh %d baris memiliki saldo nol.',
                $period,
                $metrics['row_count']
            ));
        }

        if ($metrics['unresolved_classification_rows'] > 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: %d baris belum memiliki pasangan segmen/produk yang dapat dibuktikan dari DESCRIPTION LW atau rekening+CIF Daily Loan sebelumnya.',
                $period,
                $metrics['unresolved_classification_rows']
            ));
        }

        if ($metrics['unresolved_quality_rows'] > 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: %d baris tidak memiliki NEXT_PMT_DATE/NEXT_INT_PMT_DATE yang cukup untuk menghitung kolektibilitas.',
                $period,
                $metrics['unresolved_quality_rows']
            ));
        }

        if ($metrics['duplicate_account_groups'] > 0) {
            throw new RuntimeException(sprintf(
                'Materialisasi LW321 periode %s ditolak: %d nomor rekening duplikat setelah normalisasi nol di depan pada %d baris.',
                $period,
                $metrics['duplicate_account_groups'],
                $metrics['duplicate_account_rows']
            ));
        }
    }

    private function sourceAccountKeyExpression(string $column): string
    {
        $trimmed = "TRIM(COALESCE({$column}, ''))";
        $withoutLeadingZeros = DB::connection()->getDriverName() === 'sqlite'
            ? "LTRIM({$trimmed}, '0')"
            : "TRIM(LEADING '0' FROM {$trimmed})";

        return 'CASE'
            ." WHEN {$trimmed} = '' THEN ''"
            ." WHEN {$withoutLeadingZeros} = '' THEN '0'"
            ." ELSE {$withoutLeadingZeros} END";
    }

    /**
     * @return array{
     *     joins:string,description:string,segment:string,product:string,
     *     performance_segment:string,performance_product:string,pn_referral:string,
     *     resolution_source:string,previous_period:?string
     * }
     */
    private function sourceResolutionSqlContext(string $period): array
    {
        $previousPeriod = $this->latestAuthoritativeDailyPeriodBefore($period);
        $joins = '';
        $accountDescription = 'NULL';
        $accountSegment = 'NULL';
        $accountProduct = 'NULL';
        $accountPerformanceSegment = 'NULL';
        $accountPerformanceProduct = 'NULL';
        $accountCif = 'NULL';
        $accountReferral = 'NULL';

        if ($previousPeriod !== null) {
            $previousLiteral = self::quoteSqlLiteral($previousPeriod);
            $dailyAccountKey = $this->sourceAccountKeyExpression('d.nomor_rekening1');
            $sourceAccountKey = $this->sourceAccountKeyExpression('l.no_rekening');
            $authoritative = "(d.uniqueid_namareport IS NULL OR d.uniqueid_namareport NOT LIKE '"
                .self::TARGET_ID_PREFIX."%')";
            $dailyCif = "UPPER(NULLIF(NULLIF(TRIM(d.cifno), ''), '-'))";

            $dashboardClass = "CASE WHEN NULLIF(TRIM(d.segmen_dashboard), '') IS NOT NULL"
                ." AND NULLIF(TRIM(d.produk_dashboard), '') IS NOT NULL"
                ." THEN CONCAT(UPPER(TRIM(d.segmen_dashboard)), '|', UPPER(TRIM(d.produk_dashboard))) END";
            $performanceClass = "CASE WHEN NULLIF(TRIM(d.segmen_kinerja), '') IS NOT NULL"
                ." AND NULLIF(TRIM(d.produk_kinerja), '') IS NOT NULL"
                ." THEN CONCAT(UPPER(TRIM(d.segmen_kinerja)), '|', UPPER(TRIM(d.produk_kinerja))) END";

            $joins .= <<<SQL
                LEFT JOIN (
                    SELECT
                        {$dailyAccountKey} AS account_key,
                        MAX(NULLIF(NULLIF(TRIM(d.description), ''), '-')) AS description,
                        CASE WHEN COUNT(DISTINCT {$dashboardClass}) = 1
                            THEN MAX(NULLIF(TRIM(d.segmen_dashboard), '')) END AS segment,
                        CASE WHEN COUNT(DISTINCT {$dashboardClass}) = 1
                            THEN MAX(NULLIF(TRIM(d.produk_dashboard), '')) END AS product,
                        CASE WHEN COUNT(DISTINCT {$performanceClass}) = 1
                            THEN MAX(NULLIF(TRIM(d.segmen_kinerja), '')) END AS performance_segment,
                        CASE WHEN COUNT(DISTINCT {$performanceClass}) = 1
                            THEN MAX(NULLIF(TRIM(d.produk_kinerja), '')) END AS performance_product
                        ,CASE WHEN COUNT(DISTINCT {$dailyCif}) = 1
                            THEN MAX({$dailyCif}) END AS cif_clean
                        ,CASE WHEN COUNT(DISTINCT NULLIF(NULLIF(TRIM(d.pn_referral1), ''), '-')) = 1
                            THEN MAX(NULLIF(NULLIF(TRIM(d.pn_referral1), ''), '-')) END AS pn_referral
                    FROM `daily_loan_dinamis` d
                    WHERE d.periode = {$previousLiteral} AND {$authoritative}
                    GROUP BY {$dailyAccountKey}
                ) ar ON ar.account_key = {$sourceAccountKey}
                SQL;

            $accountDescription = 'ar.description';
            $accountSegment = 'ar.segment';
            $accountProduct = 'ar.product';
            $accountPerformanceSegment = 'ar.performance_segment';
            $accountPerformanceProduct = 'ar.performance_product';
            $accountCif = 'ar.cif_clean';
            $accountReferral = 'ar.pn_referral';
        }

        $sourceSegment = Lw321DailyLoanMapper::segmentSql('l.description');
        $sourceProduct = Lw321DailyLoanMapper::productSql('l.description');
        $sourceCif = "UPPER(NULLIF(NULLIF(TRIM(l.cifno), ''), '-'))";
        $accountIdentity = "({$sourceCif} IS NOT NULL AND {$accountCif} = {$sourceCif})";
        $accountValid = "({$accountIdentity} AND {$accountSegment} IS NOT NULL AND {$accountProduct} IS NOT NULL)";
        $sourceValid = "({$sourceSegment} IS NOT NULL AND {$sourceProduct} IS NOT NULL)";
        $segment = "CASE WHEN {$sourceValid} THEN {$sourceSegment} WHEN {$accountValid} THEN {$accountSegment} END";
        $product = "CASE WHEN {$sourceValid} THEN {$sourceProduct} WHEN {$accountValid} THEN {$accountProduct} END";
        $performanceSegment = "CASE WHEN {$sourceValid} THEN UPPER({$sourceSegment})"
            ." WHEN {$accountValid} THEN COALESCE({$accountPerformanceSegment}, UPPER({$accountSegment}))"
            ." ELSE '' END";
        $performanceProduct = "CASE WHEN {$sourceValid} THEN ".Lw321DailyLoanMapper::normalizedTokenSql($sourceProduct)
            ." WHEN {$accountValid} THEN COALESCE({$accountPerformanceProduct}, "
            .Lw321DailyLoanMapper::normalizedTokenSql($accountProduct).')'
            ." ELSE '' END";
        $description = "CASE WHEN {$sourceValid} THEN NULLIF(NULLIF(TRIM(l.description), ''), '-')"
            ." WHEN {$accountValid} THEN COALESCE({$accountDescription}, {$accountProduct})"
            ." ELSE NULLIF(NULLIF(TRIM(l.description), ''), '-') END";
        $referral = "COALESCE(NULLIF(NULLIF(TRIM(l.pn_referral), ''), '-'),"
            ." CASE WHEN {$accountIdentity} THEN {$accountReferral} END)";
        $resolutionSource = "CASE WHEN {$sourceValid} THEN 'DESCRIPTION'"
            ." WHEN {$accountValid} THEN 'ACCOUNT'"
            ." ELSE 'UNRESOLVED' END";

        return [
            'joins' => $joins,
            'description' => $description,
            'segment' => $segment,
            'product' => $product,
            'performance_segment' => $performanceSegment,
            'performance_product' => $performanceProduct,
            'pn_referral' => $referral,
            'resolution_source' => $resolutionSource,
            'previous_period' => $previousPeriod,
        ];
    }

    private function latestAuthoritativeDailyPeriodBefore(string $period): ?string
    {
        $value = DB::table(self::TARGET_TABLE)
            ->where('periode', '<', $period)
            ->where(function ($query): void {
                $query->whereNull('uniqueid_namareport')
                    ->orWhere('uniqueid_namareport', 'not like', self::TARGET_ID_PREFIX.'%');
            })
            ->max('periode');

        $normalized = StrictDateParser::normalize(trim((string) $value));

        return $normalized === null ? null : $normalized;
    }

    /**
     * SQLite is used by the contract tests. Keep the same resolution order in
     * PHP because MariaDB-specific functions are intentionally absent there.
     */
    private function sourceValidationMetricsPortable(string $period): array
    {
        $references = $this->portableResolutionReferences($period);
        $rows = DB::table(self::SOURCE_TABLE)->where('periode', $period)->get();
        $metrics = [
            'row_count' => 0,
            'blank_account_rows' => 0,
            'null_balance_rows' => 0,
            'zero_balance_rows' => 0,
            'nonzero_balance_rows' => 0,
            'duplicate_account_groups' => 0,
            'duplicate_account_rows' => 0,
            'duplicate_excess_rows' => 0,
            'blank_description_rows' => 0,
            'mapped_description_rows' => 0,
            'unmapped_description_rows' => 0,
            'unresolved_classification_rows' => 0,
            'account_reference_rows' => 0,
            'description_reference_rows' => 0,
            'unresolved_quality_rows' => 0,
            'raw_kol_mismatch_rows' => 0,
            'reference_daily_period' => $references['previous_period'],
            'balance_total' => '0.00',
        ];
        $accountCounts = [];
        $balanceTotal = 0.0;

        foreach ($rows as $object) {
            $row = (array) $object;
            $metrics['row_count']++;
            $account = $this->stringValue($row['no_rekening'] ?? null);
            if ($account === null) {
                $metrics['blank_account_rows']++;
            } else {
                $key = $this->canonicalAccountValue($account);
                $accountCounts[$key] = ($accountCounts[$key] ?? 0) + 1;
            }

            $balance = $this->numberValue($row['balance_dalam_idr'] ?? null);
            if ($balance === null) {
                $metrics['null_balance_rows']++;
            } elseif ($balance == 0.0) {
                $metrics['zero_balance_rows']++;
            } else {
                $metrics['nonzero_balance_rows']++;
                $balanceTotal += $balance;
            }

            $description = $this->stringValue($row['description'] ?? null);
            $sourceClass = Lw321DailyLoanMapper::classifyDescription($description);
            if ($description === null) {
                $metrics['blank_description_rows']++;
            } elseif ($sourceClass['matched']) {
                $metrics['mapped_description_rows']++;
            } else {
                $metrics['unmapped_description_rows']++;
            }

            $resolved = $this->resolvePortableClassification($row, $references);
            if ($resolved['segment'] === null || $resolved['product'] === null) {
                $metrics['unresolved_classification_rows']++;
            }
            $sourceMetric = strtolower($resolved['source']).'_reference_rows';
            if (array_key_exists($sourceMetric, $metrics)) {
                $metrics[$sourceMetric]++;
            }

            $age = Lw321DailyLoanMapper::resolveArrearsAge(
                $row['periode'] ?? null,
                $row['next_pmt_date'] ?? null,
                $row['next_int_pmt_date'] ?? null
            );
            $quality = Lw321DailyLoanMapper::qualityFromAge($age, $this->stringValue($row['flag_restruk'] ?? null));
            if ($quality['kolek'] === null) {
                $metrics['unresolved_quality_rows']++;
            } elseif (($rawKol = $this->stringValue($row['kol_adk'] ?? null)) !== null && $rawKol !== $quality['kolek']) {
                $metrics['raw_kol_mismatch_rows']++;
            }
        }

        $duplicateCounts = array_filter($accountCounts, static fn (int $count): bool => $count > 1);
        $metrics['duplicate_account_groups'] = count($duplicateCounts);
        $metrics['duplicate_account_rows'] = array_sum($duplicateCounts);
        $metrics['duplicate_excess_rows'] = $metrics['duplicate_account_rows'] - $metrics['duplicate_account_groups'];
        $metrics['balance_total'] = $this->balanceMetric($balanceTotal);

        return $metrics;
    }

    /**
     * @return array{
     *     previous_period:?string,
     *     accounts:array<string,array<string,?string>>
     * }
     */
    private function portableResolutionReferences(string $period): array
    {
        $previousPeriod = $this->latestAuthoritativeDailyPeriodBefore($period);
        $accounts = [];

        if ($previousPeriod !== null) {
            $rows = DB::table(self::TARGET_TABLE)
                ->where('periode', $previousPeriod)
                ->where(function ($query): void {
                    $query->whereNull('uniqueid_namareport')
                        ->orWhere('uniqueid_namareport', 'not like', self::TARGET_ID_PREFIX.'%');
                })
                ->get();

            $accountCandidates = [];
            foreach ($rows as $object) {
                $row = (array) $object;
                $accountKey = $this->canonicalAccountValue($row['nomor_rekening1'] ?? null);
                $segment = $this->stringValue($row['segmen_dashboard'] ?? null);
                $product = $this->stringValue($row['produk_dashboard'] ?? null);
                $cif = $this->upperValue($row['cifno'] ?? null);
                if ($accountKey !== '' && $cif !== null) {
                    $accountCandidates[$accountKey]['cifs'][$cif] = true;
                }
                if ($accountKey !== '' && $segment !== null && $product !== null) {
                    $classKey = strtoupper($segment).'|'.strtoupper($product);
                    $accountCandidates[$accountKey]['classes'][$classKey] = [
                        'description' => $this->stringValue($row['description'] ?? null),
                        'segment' => $segment,
                        'product' => $product,
                        'performance_segment' => $this->stringValue($row['segmen_kinerja'] ?? null),
                        'performance_product' => $this->stringValue($row['produk_kinerja'] ?? null),
                    ];
                }
                $referral = $this->stringValue($row['pn_referral1'] ?? null);
                if ($accountKey !== '' && $referral !== null) {
                    $accountCandidates[$accountKey]['referrals'][$referral] = true;
                }
            }

            foreach ($accountCandidates as $key => $candidate) {
                $classes = $candidate['classes'] ?? [];
                $cifs = $candidate['cifs'] ?? [];
                if (count($classes) === 1 && count($cifs) === 1) {
                    $accounts[$key] = array_merge(array_values($classes)[0], [
                        'cif' => (string) array_key_first($cifs),
                        'pn_referral' => count($candidate['referrals'] ?? []) === 1
                            ? (string) array_key_first($candidate['referrals'])
                            : null,
                    ]);
                }
            }
        }

        return [
            'previous_period' => $previousPeriod,
            'accounts' => $accounts,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $references
     * @return array{
     *     description:?string,segment:?string,product:?string,
     *     performance_segment:?string,performance_product:?string,
     *     pn_referral:?string,source:string
     * }
     */
    private function resolvePortableClassification(array $row, array $references): array
    {
        $description = $this->stringValue($row['description'] ?? null);
        $source = Lw321DailyLoanMapper::classifyDescription($description);
        $accountKey = $this->canonicalAccountValue($row['no_rekening'] ?? null);
        $account = $references['accounts'][$accountKey] ?? null;
        $sourceCif = $this->upperValue($row['cifno'] ?? null);
        $accountIdentityMatches = is_array($account)
            && $sourceCif !== null
            && $sourceCif === ($account['cif'] ?? null);
        $referral = $this->stringValue($row['pn_referral'] ?? null)
            ?? ($accountIdentityMatches ? ($account['pn_referral'] ?? null) : null);

        if ($source['matched']) {
            return [
                'description' => $description,
                'segment' => $source['segment'],
                'product' => $source['product'],
                'performance_segment' => strtoupper((string) $source['segment']),
                'performance_product' => Lw321DailyLoanMapper::normalizeToken($source['product']),
                'pn_referral' => $referral,
                'source' => 'description',
            ];
        }

        if ($accountIdentityMatches && ($account['segment'] ?? null) !== null && ($account['product'] ?? null) !== null) {
            return [
                'description' => $description ?? $account['description'] ?? $account['product'],
                'segment' => $account['segment'],
                'product' => $account['product'],
                'performance_segment' => $account['performance_segment']
                    ?? strtoupper((string) $account['segment']),
                'performance_product' => $account['performance_product']
                    ?? Lw321DailyLoanMapper::normalizeToken($account['product']),
                'pn_referral' => $referral,
                'source' => 'account',
            ];
        }

        return [
            'description' => $description,
            'segment' => null,
            'product' => null,
            'performance_segment' => null,
            'performance_product' => null,
            'pn_referral' => $referral,
            'source' => 'unresolved',
        ];
    }

    private function canonicalAccountValue(mixed $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        $withoutLeadingZeros = ltrim($normalized, '0');

        return $withoutLeadingZeros === '' ? '0' : $withoutLeadingZeros;
    }

    /**
     * @return array{row_count:int,blank_account_rows:int,null_balance_rows:int,unclassified_rows:int,unresolved_quality_rows:int,balance_total:string}
     */
    private function targetValidationMetrics(string $period): array
    {
        $metrics = DB::table(self::TARGET_TABLE)
            ->where('periode', $period)
            ->where('uniqueid_namareport', 'like', self::TARGET_ID_PREFIX.'%')
            ->selectRaw(<<<'SQL'
                COUNT(*) AS row_count,
                SUM(CASE WHEN nomor_rekening1 IS NULL OR TRIM(nomor_rekening1) = '' OR TRIM(nomor_rekening1) = '-' THEN 1 ELSE 0 END) AS blank_account_rows,
                SUM(CASE WHEN baki_debet1 IS NULL THEN 1 ELSE 0 END) AS null_balance_rows,
                SUM(CASE WHEN segmen_dashboard IS NULL OR produk_dashboard IS NULL THEN 1 ELSE 0 END) AS unclassified_rows,
                SUM(CASE WHEN kolek IS NULL OR kolek_detail IS NULL OR umur_tunggakan IS NULL THEN 1 ELSE 0 END) AS unresolved_quality_rows,
                COALESCE(SUM(baki_debet1), 0) AS balance_total
                SQL)
            ->first();

        return [
            'row_count' => (int) ($metrics->row_count ?? 0),
            'blank_account_rows' => (int) ($metrics->blank_account_rows ?? 0),
            'null_balance_rows' => (int) ($metrics->null_balance_rows ?? 0),
            'unclassified_rows' => (int) ($metrics->unclassified_rows ?? 0),
            'unresolved_quality_rows' => (int) ($metrics->unresolved_quality_rows ?? 0),
            'balance_total' => $this->balanceMetric($metrics->balance_total ?? 0),
        ];
    }

    private function balanceMetric(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || ! is_numeric($raw)) {
            return '0.00';
        }

        if (stripos($raw, 'e') !== false) {
            return number_format((float) $raw, 2, '.', '');
        }

        $negative = str_starts_with($raw, '-');
        $unsigned = ltrim($raw, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        if ($whole === '0' && $fraction === '00') {
            $negative = false;
        }

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function balanceTotalsMatch(string $sourceTotal, string $targetTotal): bool
    {
        return hash_equals($sourceTotal, $targetTotal);
    }

    private function insertWithSelect(string $period): int
    {
        $resolution = $this->sourceResolutionSqlContext($period);
        $mappings = $this->sqlMappings($resolution);
        $columns = $this->availableTargetColumns(array_keys($mappings));
        $columnSql = implode(', ', array_map(self::quoteIdentifier(...), $columns));
        $selectSql = implode(', ', array_map(static fn (string $column): string => $mappings[$column], $columns));

        $sql = 'INSERT INTO '.self::quoteIdentifier(self::TARGET_TABLE)." ({$columnSql}) "
            ."SELECT {$selectSql} FROM ".self::quoteIdentifier(self::SOURCE_TABLE).' l '
            .$resolution['joins'].' '
            .'WHERE l.'.self::quoteIdentifier('periode').' = ?';

        return (int) DB::affectingStatement($sql, [$period]);
    }

    private function insertPortable(string $period): int
    {
        $targetColumns = array_fill_keys(Schema::getColumnListing(self::TARGET_TABLE), true);
        $references = $this->portableResolutionReferences($period);
        $inserted = 0;
        $buffer = [];

        DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->orderBy('uniqueid_namareport')
            ->chunk(1000, function ($rows) use (&$buffer, &$inserted, $targetColumns, $references): void {
                foreach ($rows as $row) {
                    $mapped = array_intersect_key($this->mapPortableRow((array) $row, $references), $targetColumns);
                    $buffer[] = $mapped;

                    if (count($buffer) >= 1000) {
                        DB::table(self::TARGET_TABLE)->insert($buffer);
                        $inserted += count($buffer);
                        $buffer = [];
                    }
                }
            });

        if ($buffer !== []) {
            DB::table(self::TARGET_TABLE)->insert($buffer);
            $inserted += count($buffer);
        }

        return $inserted;
    }

    /**
     * @return array<string,mixed>
     */
    private function mapPortableRow(array $row, array $references): array
    {
        $period = $this->dateValue($row['periode'] ?? null);
        $nextPaymentDate = $this->dateValue($row['next_pmt_date'] ?? null);
        $nextInterestPaymentDate = $this->dateValue($row['next_int_pmt_date'] ?? null);
        $age = Lw321DailyLoanMapper::resolveArrearsAge($period, $nextPaymentDate, $nextInterestPaymentDate);
        $quality = Lw321DailyLoanMapper::qualityFromAge($age, $this->stringValue($row['flag_restruk'] ?? null));
        $classification = $this->resolvePortableClassification($row, $references);
        $balance = $this->numberValue($row['balance_dalam_idr'] ?? null);
        $kolek = $quality['kolek'];
        $manager = $this->stringValue($row['pn_pengelola_singlepn'] ?? null)
            ?? $this->stringValue($row['pn_pengelola_1'] ?? null);
        $decisionMaker = $this->stringValue($row['pn_pemutus'] ?? null);
        $now = now()->format('Y-m-d H:i:s');
        $segment = $classification['segment'];
        $product = $classification['product'];

        return [
            'uniqueid_namareport' => self::TARGET_ID_PREFIX.hash('sha256', implode('|', [
                (string) ($row['uniqueid_namareport'] ?? ''),
                (string) $period,
                (string) ($row['no_rekening'] ?? ''),
            ])),
            'periode' => $period,
            'kode_kanwil1' => $this->stringValue($row['kode_kanwil'] ?? null),
            'kanwil1' => $this->stringValue($row['kanwil'] ?? null),
            'kode_cabang1' => $this->stringValue($row['kode_kanca'] ?? null),
            'cabang1' => $this->stringValue($row['kanca'] ?? null),
            'branch1' => $this->stringValue($row['kode_uker'] ?? null),
            'unit1' => $this->stringValue($row['uker'] ?? null),
            'curtyp' => $this->stringValue($row['currency'] ?? null),
            'ao_name' => null,
            'cifno' => $this->stringValue($row['cifno'] ?? null),
            'nomor_rekening1' => $this->stringValue($row['no_rekening'] ?? null),
            'status_rekening1' => '1',
            'ln_type' => $this->stringValue($row['ln_type'] ?? null),
            'nama_debitur1' => $this->stringValue($row['nama_debitur'] ?? null),
            'rate' => $this->numberValue($row['rate'] ?? null),
            'jangka_waktu1' => $this->stringValue($row['jangka_waktu'] ?? null),
            'plafon' => $this->numberValue($row['plafon_dalam_idr'] ?? null)
                ?? $this->numberValue($row['plafon'] ?? null),
            'baki_debet1' => $balance,
            'nilai_tercatat1' => $balance,
            'kol_adk1' => $this->stringValue($row['kol_adk'] ?? null),
            'kolek_detail' => $quality['kolek_detail'],
            'kolek' => $kolek,
            'kolektabilitas_lancar' => $kolek === '1' ? $balance : 0,
            'kolektabilitas_dpk' => $kolek === '2' ? $balance : 0,
            'kolektabilitas_kuranglancar' => $kolek === '3' ? $balance : 0,
            'kolektabilitas_diragukan' => $kolek === '4' ? $balance : 0,
            'kolektabilitas_macet' => $kolek === '5' ? $balance : 0,
            'total_kewajiban' => $this->sumValues([
                $row['tunggakan_pokok'] ?? null,
                $row['tunggakan_bunga'] ?? null,
                $row['tunggakan_pinalti'] ?? null,
            ]),
            'tunggakan_pokok' => $this->numberValue($row['tunggakan_pokok'] ?? null),
            'tunggakan_bunga' => $this->numberValue($row['tunggakan_bunga'] ?? null),
            'tunggakan_penalti' => $this->numberValue($row['tunggakan_pinalti'] ?? null),
            'umur_tunggakan' => $age,
            'tgl_realisasi' => $this->dateValue($row['tgl_realisasi'] ?? null),
            'tgl_jatuh_tempo' => $this->dateValue($row['tgl_jatuh_tempo'] ?? null),
            'tanggal_menunggak' => $this->dateValue($row['tgl_menunggak'] ?? null),
            'next_pmt_date' => $nextPaymentDate,
            'next_pmt_int_date' => $nextInterestPaymentDate,
            'freq_payment' => $this->integerValue($row['freq_payment'] ?? null),
            'freq_int_payment' => $this->integerValue($row['freq_int_payment'] ?? null),
            'pn_pengelola1' => $manager,
            'pn_name1' => $manager === null ? null : preg_replace('/^\s*\d+\s*-\s*/', '', $manager),
            'pn_pemrakarsa1' => $this->stringValue($row['pn_pemrakarsa'] ?? null),
            'pn_referral1' => $classification['pn_referral'],
            'pn_restruk1' => $this->stringValue($row['pn_restruk'] ?? null),
            'pn_pengelola2' => $this->stringValue($row['pn_pengelola_2'] ?? null),
            'pn_pemutus1' => $decisionMaker,
            'pn_crm1' => $this->stringValue($row['pn_crm'] ?? null),
            'pn_crr' => $this->stringValue($row['pn_rm_crr'] ?? null),
            'pn_referral_naik_kelas1' => $this->stringValue($row['pn_rm_referral_naik_segmentasi'] ?? null),
            'code' => $this->stringValue($row['code'] ?? null),
            'description' => $classification['description'],
            'segmen_dashboard' => $segment,
            'produk_dashboard' => $product,
            'divisi_segmen_dashboard' => $segment,
            'flag_restruk' => $this->stringValue($row['flag_restruk'] ?? null),
            'os_idr' => $balance,
            'segmen_kinerja' => $classification['performance_segment'] ?? '',
            'produk_kinerja' => $classification['performance_product'] ?? '',
            'cabang_normalized' => $this->upperValue($row['kanca'] ?? null) ?? '',
            'unit_normalized' => $this->upperValue($row['uker'] ?? null) ?? '',
            'branch_normalized' => $this->upperValue($row['kode_uker'] ?? null) ?? '',
            'rm_normalized' => $manager === null ? '' : strtoupper($manager),
            'pn_pemutus_normalized' => $this->personnelNumber($decisionMaker),
            'cifno_clean' => $this->upperValue($row['cifno'] ?? null) ?? '',
            'shadow_built_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function sqlMappings(array $resolution): array
    {
        $source = fn (string $column): string => Schema::hasColumn(self::SOURCE_TABLE, $column)
            ? 'l.'.self::quoteIdentifier($column)
            : 'NULL';
        $nullableText = fn (string $column): string => 'NULLIF(NULLIF(TRIM(COALESCE('.$source($column).", '')), ''), '-')";
        $period = $source('periode');
        $nextPayment = $source('next_pmt_date');
        $nextInterest = $source('next_int_pmt_date');
        $age = Lw321DailyLoanMapper::arrearsAgeSql($period, $nextPayment, $nextInterest);
        $kolek = Lw321DailyLoanMapper::kolekSql($age);
        $detail = Lw321DailyLoanMapper::kolekDetailSql($age, $source('flag_restruk'));
        $segment = $resolution['segment'];
        $product = $resolution['product'];
        $balance = $source('balance_dalam_idr');
        $manager = 'COALESCE('.$nullableText('pn_pengelola_singlepn').', '.$nullableText('pn_pengelola_1').')';
        $managerName = "CASE WHEN {$manager} REGEXP '^[[:space:]]*[0-9]+[[:space:]]*-'"
            ." THEN TRIM(SUBSTRING({$manager}, LOCATE('-', {$manager}) + 1)) ELSE {$manager} END";
        $decisionMaker = $nullableText('pn_pemutus');

        return [
            'uniqueid_namareport' => "CONCAT('".self::TARGET_ID_PREFIX."', SHA2(CONCAT_WS('|', COALESCE("
                .$source('uniqueid_namareport').", ''), COALESCE({$period}, ''), COALESCE(".$source('no_rekening').", '')), 256))",
            'periode' => $period,
            'kode_kanwil1' => $nullableText('kode_kanwil'),
            'kanwil1' => $nullableText('kanwil'),
            'kode_cabang1' => $nullableText('kode_kanca'),
            'cabang1' => $nullableText('kanca'),
            'branch1' => $nullableText('kode_uker'),
            'unit1' => $nullableText('uker'),
            'curtyp' => $nullableText('currency'),
            'ao_name' => 'NULL',
            'cifno' => $nullableText('cifno'),
            'nomor_rekening1' => $nullableText('no_rekening'),
            'status_rekening1' => "'1'",
            'ln_type' => $nullableText('ln_type'),
            'nama_debitur1' => $nullableText('nama_debitur'),
            'rate' => $source('rate'),
            'jangka_waktu1' => $nullableText('jangka_waktu'),
            'plafon' => 'COALESCE('.$source('plafon_dalam_idr').', '.$source('plafon').')',
            'baki_debet1' => $balance,
            'nilai_tercatat1' => $balance,
            'kol_adk1' => $nullableText('kol_adk'),
            'kolek_detail' => $detail,
            'kolek' => $kolek,
            'kolektabilitas_lancar' => "CASE WHEN ({$kolek}) = '1' THEN {$balance} ELSE 0 END",
            'kolektabilitas_dpk' => "CASE WHEN ({$kolek}) = '2' THEN {$balance} ELSE 0 END",
            'kolektabilitas_kuranglancar' => "CASE WHEN ({$kolek}) = '3' THEN {$balance} ELSE 0 END",
            'kolektabilitas_diragukan' => "CASE WHEN ({$kolek}) = '4' THEN {$balance} ELSE 0 END",
            'kolektabilitas_macet' => "CASE WHEN ({$kolek}) = '5' THEN {$balance} ELSE 0 END",
            'total_kewajiban' => 'COALESCE('.$source('tunggakan_pokok').', 0) + COALESCE('
                .$source('tunggakan_bunga').', 0) + COALESCE('.$source('tunggakan_pinalti').', 0)',
            'tunggakan_pokok' => $source('tunggakan_pokok'),
            'tunggakan_bunga' => $source('tunggakan_bunga'),
            'tunggakan_penalti' => $source('tunggakan_pinalti'),
            'umur_tunggakan' => $age,
            'tgl_realisasi' => $source('tgl_realisasi'),
            'tgl_jatuh_tempo' => $source('tgl_jatuh_tempo'),
            'tanggal_menunggak' => $source('tgl_menunggak'),
            'next_pmt_date' => $nextPayment,
            'next_pmt_int_date' => $nextInterest,
            'freq_payment' => $source('freq_payment'),
            'freq_int_payment' => $source('freq_int_payment'),
            'pn_pengelola1' => $manager,
            'pn_name1' => $managerName,
            'pn_pemrakarsa1' => $nullableText('pn_pemrakarsa'),
            'pn_referral1' => $resolution['pn_referral'],
            'pn_restruk1' => $nullableText('pn_restruk'),
            'pn_pengelola2' => $nullableText('pn_pengelola_2'),
            'pn_pemutus1' => $decisionMaker,
            'pn_crm1' => $nullableText('pn_crm'),
            'pn_crr' => $nullableText('pn_rm_crr'),
            'pn_referral_naik_kelas1' => $nullableText('pn_rm_referral_naik_segmentasi'),
            'code' => $nullableText('code'),
            'description' => $resolution['description'],
            'segmen_dashboard' => $segment,
            'produk_dashboard' => $product,
            'divisi_segmen_dashboard' => $segment,
            'flag_restruk' => $nullableText('flag_restruk'),
            'os_idr' => $balance,
            'segmen_kinerja' => $resolution['performance_segment'],
            'produk_kinerja' => $resolution['performance_product'],
            'cabang_normalized' => 'UPPER(COALESCE('.$nullableText('kanca').", ''))",
            'unit_normalized' => 'UPPER(COALESCE('.$nullableText('uker').", ''))",
            'branch_normalized' => 'UPPER(COALESCE('.$nullableText('kode_uker').", ''))",
            'rm_normalized' => "UPPER(COALESCE({$manager}, ''))",
            'pn_pemutus_normalized' => "NULLIF(TRIM(LEADING '0' FROM TRIM(SUBSTRING_INDEX(COALESCE({$decisionMaker}, ''), '-', 1))), '')",
            'cifno_clean' => 'UPPER(COALESCE('.$nullableText('cifno').", ''))",
            'shadow_built_at' => 'NOW()',
            'created_at' => 'NOW()',
            'updated_at' => 'NOW()',
        ];
    }

    /**
     * @param  array<int,string>  $candidates
     * @return array<int,string>
     */
    private function availableTargetColumns(array $candidates): array
    {
        $available = array_fill_keys(Schema::getColumnListing(self::TARGET_TABLE), true);

        return array_values(array_filter(
            $candidates,
            static fn (string $column): bool => isset($available[$column])
        ));
    }

    /**
     * @return array<int,string>
     */
    private function resolvePeriods(?string $periodHint): array
    {
        $rawHint = trim((string) $periodHint);
        if ($rawHint !== '') {
            $normalizedHint = StrictDateParser::normalize($rawHint);
            if ($normalizedHint === null) {
                throw new RuntimeException("Periode LW321 tidak valid: {$rawHint}.");
            }

            return [$normalizedHint];
        }

        $periods = DB::table(self::SOURCE_TABLE)
            ->whereNotNull('periode')
            ->distinct()
            ->pluck('periode')
            ->map(static fn ($period): string => substr(trim((string) $period), 0, 10))
            ->filter()
            ->all();

        $derivedPeriods = DB::table(self::TARGET_TABLE)
            ->where('uniqueid_namareport', 'like', self::TARGET_ID_PREFIX.'%')
            ->whereNotNull('periode')
            ->distinct()
            ->pluck('periode')
            ->map(static fn ($period): string => substr(trim((string) $period), 0, 10))
            ->filter()
            ->all();

        $periods = array_values(array_unique(array_merge($periods, $derivedPeriods)));
        sort($periods);

        return $periods;
    }

    private function assertSchemaReady(): void
    {
        foreach ([self::SOURCE_TABLE, self::TARGET_TABLE] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabel {$table} belum tersedia untuk sinkronisasi LW321.");
            }
        }

        $requiredSource = [
            'uniqueid_namareport', 'periode', 'no_rekening', 'balance_dalam_idr',
            'description', 'cifno', 'next_pmt_date', 'next_int_pmt_date',
            'kol_adk', 'pn_referral', 'flag_restruk',
        ];
        $requiredTarget = [
            'uniqueid_namareport', 'periode', 'nomor_rekening1', 'baki_debet1',
            'cifno', 'description', 'kolek', 'kol_adk1', 'kolek_detail', 'umur_tunggakan',
            'segmen_dashboard', 'produk_dashboard', 'segmen_kinerja', 'produk_kinerja', 'pn_referral1',
        ];

        foreach ($requiredSource as $column) {
            if (! Schema::hasColumn(self::SOURCE_TABLE, $column)) {
                throw new RuntimeException('Kolom wajib '.self::SOURCE_TABLE.".{$column} tidak tersedia.");
            }
        }

        foreach ($requiredTarget as $column) {
            if (! Schema::hasColumn(self::TARGET_TABLE, $column)) {
                throw new RuntimeException('Kolom wajib '.self::TARGET_TABLE.".{$column} tidak tersedia.");
            }
        }
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private static function quoteSqlLiteral(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    private function stringValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' || $normalized === '-' ? null : $normalized;
    }

    private function upperValue(mixed $value): ?string
    {
        $normalized = $this->stringValue($value);

        return $normalized === null ? null : strtoupper($normalized);
    }

    private function numberValue(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function integerValue(mixed $value): ?int
    {
        $number = $this->numberValue($value);

        return $number === null ? null : (int) $number;
    }

    private function dateValue(mixed $value): ?string
    {
        return StrictDateParser::normalize(trim((string) $value));
    }

    /**
     * @param  array<int,mixed>  $values
     */
    private function sumValues(array $values): float
    {
        return array_reduce(
            $values,
            fn (float $sum, mixed $value): float => $sum + ($this->numberValue($value) ?? 0.0),
            0.0
        );
    }

    private function personnelNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $number = ltrim(trim(explode('-', $value, 2)[0]), '0');

        return $number === '' ? null : $number;
    }
}
