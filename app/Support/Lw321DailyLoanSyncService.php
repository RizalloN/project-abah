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
     * @return array{periods:array<int,string>, source_rows:int, inserted_rows:int, deleted_rows:int}
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
        ];

        foreach ($periods as $period) {
            $periodResult = $this->synchronizePeriod($period);
            $result['source_rows'] += $periodResult['source_rows'];
            $result['inserted_rows'] += $periodResult['inserted_rows'];
            $result['deleted_rows'] += $periodResult['deleted_rows'];
        }

        return $result;
    }

    /**
     * @return array{source_rows:int, inserted_rows:int, deleted_rows:int}
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
                $sourceRows = (int) DB::table(self::SOURCE_TABLE)
                    ->where('periode', $period)
                    ->count();

                $conflictingRows = (int) DB::table(self::TARGET_TABLE)
                    ->where('periode', $period)
                    ->where('uniqueid_namareport', 'not like', self::TARGET_ID_PREFIX.'%')
                    ->count();

                if ($conflictingRows > 0) {
                    throw new RuntimeException(sprintf(
                        'Periode %s sudah dimiliki Daily Loan Dinamis (%s baris). Hapus sumber periode tersebut sebelum memakai LW321.',
                        $period,
                        number_format($conflictingRows, 0, ',', '.')
                    ));
                }

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

                return [
                    'source_rows' => $sourceRows,
                    'inserted_rows' => $insertedRows,
                    'deleted_rows' => $deletedRows,
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

    private function insertWithSelect(string $period): int
    {
        $mappings = $this->sqlMappings();
        $columns = $this->availableTargetColumns(array_keys($mappings));
        $columnSql = implode(', ', array_map(self::quoteIdentifier(...), $columns));
        $selectSql = implode(', ', array_map(static fn (string $column): string => $mappings[$column], $columns));

        $sql = 'INSERT INTO '.self::quoteIdentifier(self::TARGET_TABLE)." ({$columnSql}) "
            ."SELECT {$selectSql} FROM ".self::quoteIdentifier(self::SOURCE_TABLE).' l '
            .'WHERE l.'.self::quoteIdentifier('periode').' = ?';

        return (int) DB::affectingStatement($sql, [$period]);
    }

    private function insertPortable(string $period): int
    {
        $targetColumns = array_fill_keys(Schema::getColumnListing(self::TARGET_TABLE), true);
        $inserted = 0;
        $buffer = [];

        DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->orderBy('uniqueid_namareport')
            ->chunk(1000, function ($rows) use (&$buffer, &$inserted, $targetColumns): void {
                foreach ($rows as $row) {
                    $mapped = array_intersect_key($this->mapPortableRow((array) $row), $targetColumns);
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
    private function mapPortableRow(array $row): array
    {
        $period = $this->dateValue($row['periode'] ?? null);
        $nextPaymentDate = $this->dateValue($row['next_pmt_date'] ?? null);
        $nextInterestPaymentDate = $this->dateValue($row['next_int_pmt_date'] ?? null);
        $age = Lw321DailyLoanMapper::resolveArrearsAge($period, $nextPaymentDate, $nextInterestPaymentDate);
        $quality = Lw321DailyLoanMapper::qualityFromAge($age, $this->stringValue($row['flag_restruk'] ?? null));
        $classification = Lw321DailyLoanMapper::classifyDescription($this->stringValue($row['description'] ?? null));
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
            'pn_referral1' => $this->stringValue($row['pn_referral'] ?? null),
            'pn_restruk1' => $this->stringValue($row['pn_restruk'] ?? null),
            'pn_pengelola2' => $this->stringValue($row['pn_pengelola_2'] ?? null),
            'pn_pemutus1' => $decisionMaker,
            'pn_crm1' => $this->stringValue($row['pn_crm'] ?? null),
            'pn_crr' => $this->stringValue($row['pn_rm_crr'] ?? null),
            'pn_referral_naik_kelas1' => $this->stringValue($row['pn_rm_referral_naik_segmentasi'] ?? null),
            'code' => $this->stringValue($row['code'] ?? null),
            'description' => $this->stringValue($row['description'] ?? null),
            'segmen_dashboard' => $segment,
            'produk_dashboard' => $product,
            'divisi_segmen_dashboard' => $segment,
            'flag_restruk' => $this->stringValue($row['flag_restruk'] ?? null),
            'os_idr' => $balance,
            'segmen_kinerja' => $segment === null ? '' : strtoupper($segment),
            'produk_kinerja' => $product === null ? '' : Lw321DailyLoanMapper::normalizeToken($product),
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
    private function sqlMappings(): array
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
        $segment = Lw321DailyLoanMapper::segmentSql($source('description'));
        $product = Lw321DailyLoanMapper::productSql($source('description'));
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
            'pn_referral1' => $nullableText('pn_referral'),
            'pn_restruk1' => $nullableText('pn_restruk'),
            'pn_pengelola2' => $nullableText('pn_pengelola_2'),
            'pn_pemutus1' => $decisionMaker,
            'pn_crm1' => $nullableText('pn_crm'),
            'pn_crr' => $nullableText('pn_rm_crr'),
            'pn_referral_naik_kelas1' => $nullableText('pn_rm_referral_naik_segmentasi'),
            'code' => $nullableText('code'),
            'description' => $nullableText('description'),
            'segmen_dashboard' => $segment,
            'produk_dashboard' => $product,
            'divisi_segmen_dashboard' => $segment,
            'flag_restruk' => $nullableText('flag_restruk'),
            'os_idr' => $balance,
            'segmen_kinerja' => "UPPER(COALESCE({$segment}, ''))",
            'produk_kinerja' => Lw321DailyLoanMapper::normalizedTokenSql($product),
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

        $requiredSource = ['uniqueid_namareport', 'periode', 'no_rekening', 'balance_dalam_idr'];
        $requiredTarget = [
            'uniqueid_namareport', 'periode', 'nomor_rekening1', 'baki_debet1',
            'kolek', 'kolek_detail', 'umur_tunggakan', 'segmen_dashboard', 'produk_dashboard',
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
