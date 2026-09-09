<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class ConsumerRmPositionHistoryStore
{
    public const SOURCE_TABLE = 'daily_loan_dinamis';

    public const HISTORY_TABLE = 'consumer_rm_position_history';

    public const CAPTURE_TABLE = 'consumer_rm_position_captures';

    private const LOCK_WAIT_SECONDS = 60;

    private const HISTORY_COLUMNS = [
        'periode',
        'produk',
        'cifno_clean',
        'account_key',
        'tgl_realisasi',
        'plafon',
        'baki_debet',
        'cabang',
        'unit',
        'branch_code',
        'rm',
        'pn_pengelola',
        'pn_pemrakarsa',
        'lookup_order',
        'status_rekening1',
        'flag_restruk',
        'pn_restruk1',
        'restruk_ke1',
        'jenis_restruk1',
        'tgl_akad_restruk',
    ];

    private const CAPTURE_COLUMNS = [
        'periode',
        'source_rows',
        'archived_rows',
        'content_hash',
        'verified',
        'captured_at',
    ];

    /**
     * Capture one immutable Consumer position before the source can be pruned.
     * A verified capture is never replaced implicitly: a resumed prune can
     * therefore call this method safely after part of its source was removed.
     * A completed re-import must opt in with $replaceVerified = true.
     *
     * @return array{
     *     period:string,
     *     source_rows:int,
     *     archived_rows:int,
     *     verified:bool,
     *     skipped:bool,
     *     reason:?string
     * }
     */
    public function capturePeriod(string $period, bool $replaceVerified = false): array
    {
        $period = $this->normalizePeriod($period);

        if (! $this->archiveSchemaReady()) {
            return $this->result($period, 0, 0, false, true, 'archive_tables_unavailable');
        }

        $existing = $this->verifiedManifest($period);
        if (! $replaceVerified && $existing !== null) {
            return $this->result(
                $period,
                (int) $existing->source_rows,
                (int) $existing->archived_rows,
                true,
                false,
                'already_verified'
            );
        }

        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return $this->result($period, 0, 0, false, true, 'source_table_unavailable');
        }

        $sourceColumns = $this->resolveSourceColumns();
        if ($sourceColumns === null) {
            return $this->result($period, 0, 0, false, true, 'source_schema_unavailable');
        }

        $usesMysqlLock = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $lockName = 'project_abah:consumer_rm_capture:'.str_replace('-', '', $period);
        $lockAcquired = false;

        try {
            if ($usesMysqlLock) {
                $lock = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [
                    $lockName,
                    self::LOCK_WAIT_SECONDS,
                ]);
                $lockAcquired = (int) ($lock->acquired ?? 0) === 1;
                if (! $lockAcquired) {
                    throw new RuntimeException("Arsip posisi Consumer RM {$period} menunggu proses capture lain terlalu lama.");
                }
            }

            return DB::transaction(function () use ($period, $replaceVerified, $sourceColumns): array {
                // Recheck while holding the capture lock. This closes the race
                // between two workers that both observed no manifest initially.
                $existing = $this->verifiedManifest($period);
                if (! $replaceVerified && $existing !== null) {
                    return $this->result(
                        $period,
                        (int) $existing->source_rows,
                        (int) $existing->archived_rows,
                        true,
                        false,
                        'already_verified'
                    );
                }

                if (! DB::table(self::SOURCE_TABLE)->where('periode', $period)->exists()) {
                    return $this->result($period, 0, 0, false, true, 'source_period_unavailable');
                }

                [$sourceRows, $archiveRows] = $this->buildArchiveRows($period, $sourceColumns);
                $archivedRows = count($archiveRows);
                $contentHash = hash('sha256', json_encode(
                    ['source_rows' => $sourceRows, 'rows' => array_values($archiveRows)],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ));

                DB::table(self::HISTORY_TABLE)->where('periode', $period)->delete();
                foreach (array_chunk(array_values($archiveRows), 500) as $chunk) {
                    DB::table(self::HISTORY_TABLE)->insert($chunk);
                }

                $storedRows = (int) DB::table(self::HISTORY_TABLE)
                    ->where('periode', $period)
                    ->count();
                if ($storedRows !== $archivedRows) {
                    throw new RuntimeException(sprintf(
                        'Capture posisi Consumer RM %s tidak lengkap: %d dari %d baris.',
                        $period,
                        $storedRows,
                        $archivedRows
                    ));
                }

                $now = now();
                DB::table(self::CAPTURE_TABLE)->updateOrInsert(
                    ['periode' => $period],
                    [
                        'source_rows' => $sourceRows,
                        'archived_rows' => $archivedRows,
                        'content_hash' => $contentHash,
                        'verified' => true,
                        'captured_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                return $this->result($period, $sourceRows, $archivedRows, true, false, null);
            }, 3);
        } finally {
            if ($lockAcquired) {
                try {
                    DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (\Throwable) {
                }
            }
        }
    }

    public function hasCapture(string $period): bool
    {
        $period = $this->normalizePeriod($period);

        return $this->archiveSchemaReady() && $this->verifiedManifest($period) !== null;
    }

    /** @return array<int, string> */
    public function availablePeriods(?string $start = null, ?string $end = null): array
    {
        if (! $this->archiveSchemaReady()) {
            return [];
        }

        $start = $start === null ? null : $this->normalizePeriod($start);
        $end = $end === null ? null : $this->normalizePeriod($end);
        if ($start !== null && $end !== null && $start > $end) {
            throw new InvalidArgumentException('Rentang periode arsip Consumer RM tidak valid.');
        }

        $query = DB::table(self::CAPTURE_TABLE)
            ->where('verified', true)
            ->orderBy('periode');
        if ($start !== null) {
            $query->where('periode', '>=', $start);
        }
        if ($end !== null) {
            $query->where('periode', '<=', $end);
        }

        $manifests = $query->get(['periode', 'archived_rows']);
        if ($manifests->isEmpty()) {
            return [];
        }

        $periods = $manifests
            ->pluck('periode')
            ->map(static fn ($value): string => substr((string) $value, 0, 10))
            ->all();
        $storedCounts = DB::table(self::HISTORY_TABLE)
            ->whereIn('periode', $periods)
            ->selectRaw('periode, COUNT(*) AS row_count')
            ->groupBy('periode')
            ->pluck('row_count', 'periode');

        return $manifests
            ->filter(static function ($manifest) use ($storedCounts): bool {
                $period = substr((string) $manifest->periode, 0, 10);

                return (int) ($storedCounts[$period] ?? 0) === (int) $manifest->archived_rows;
            })
            ->map(static fn ($manifest): string => substr((string) $manifest->periode, 0, 10))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $columns
     * @return array{0:int,1:array<string,array<string,mixed>>}
     */
    private function buildArchiveRows(string $period, array $columns): array
    {
        $selected = [
            'periode',
            $columns['product'].' as source_product',
            $columns['cif'].' as source_cif',
            $columns['account'].' as source_account',
            $columns['realization_date'].' as source_realization_date',
            $columns['plafon'].' as source_plafon',
            $columns['outstanding'].' as source_outstanding',
            $this->aliasedColumn($columns['cabang'], 'source_cabang'),
            $this->aliasedColumn($columns['unit'], 'source_unit'),
            $this->aliasedColumn($columns['branch_code'], 'source_branch_code'),
            $this->aliasedColumn($columns['rm'], 'source_rm'),
            $this->aliasedColumn($columns['manager'], 'source_manager'),
            $this->aliasedColumn($columns['initiator'], 'source_initiator'),
            $columns['lookup_order'].' as source_lookup_order',
            $this->aliasedColumn($columns['account_status'], 'source_account_status'),
            $this->aliasedColumn($columns['restructure_flag'], 'source_restructure_flag'),
            $this->aliasedColumn($columns['restructure_pn'], 'source_restructure_pn'),
            $this->aliasedColumn($columns['restructure_number'], 'source_restructure_number', 'NULL'),
            $this->aliasedColumn($columns['restructure_type'], 'source_restructure_type'),
            $this->aliasedColumn($columns['restructure_date'], 'source_restructure_date', 'NULL'),
        ];

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $period)
            ->where($columns['segment'], 'CONSUMER')
            ->whereIn($columns['product'], ['BRIGUNAKONSUMER', 'KPR'])
            ->whereNotNull($columns['account'])
            ->whereRaw('TRIM('.$this->wrap($columns['account']).") <> ''")
            ->whereNotNull($columns['cif'])
            ->whereRaw('TRIM('.$this->wrap($columns['cif']).") <> ''")
            ->select($selected)
            ->orderBy($columns['product'])
            ->orderBy($columns['cif'])
            ->orderBy($columns['account']);

        foreach (['rm', 'initiator', 'lookup_order'] as $orderColumn) {
            if ($columns[$orderColumn] !== null) {
                $query->orderBy($columns[$orderColumn]);
            }
        }

        $sourceRows = 0;
        $archiveRows = [];
        $query->chunk(2000, function ($rows) use (&$sourceRows, &$archiveRows, $period): void {
            foreach ($rows as $source) {
                $sourceRows++;
                $product = $this->canonicalProduct((string) ($source->source_product ?? ''));
                $cif = strtoupper(trim((string) ($source->source_cif ?? '')));
                $account = $this->canonicalAccount((string) ($source->source_account ?? ''));
                if ($product === '' || $cif === '' || $account === '') {
                    continue;
                }

                $key = $product."\x1F".$cif."\x1F".$account;
                $candidate = [
                    'periode' => $period,
                    'produk' => $product,
                    'cifno_clean' => $cif,
                    'account_key' => $account,
                    'tgl_realisasi' => $this->dateValue($source->source_realization_date ?? null),
                    'plafon' => $this->decimalValue($source->source_plafon ?? null),
                    'baki_debet' => $this->decimalValue($source->source_outstanding ?? null),
                    'cabang' => trim((string) ($source->source_cabang ?? '')),
                    'unit' => trim((string) ($source->source_unit ?? '')),
                    'branch_code' => trim((string) ($source->source_branch_code ?? '')),
                    'rm' => trim((string) ($source->source_rm ?? '')),
                    'pn_pengelola' => trim((string) ($source->source_manager ?? '')),
                    'pn_pemrakarsa' => trim((string) ($source->source_initiator ?? '')),
                    'lookup_order' => trim((string) ($source->source_lookup_order ?? '')),
                    'status_rekening1' => $this->nullableString($source->source_account_status ?? null),
                    'flag_restruk' => $this->nullableString($source->source_restructure_flag ?? null),
                    'pn_restruk1' => $this->nullableString($source->source_restructure_pn ?? null),
                    'restruk_ke1' => $this->nullableInteger($source->source_restructure_number ?? null),
                    'jenis_restruk1' => $this->nullableString($source->source_restructure_type ?? null),
                    'tgl_akad_restruk' => $this->dateValue($source->source_restructure_date ?? null),
                ];
                if ($candidate['lookup_order'] === '') {
                    $candidate['lookup_order'] = trim((string) ($source->source_account ?? $account));
                }

                if (! isset($archiveRows[$key])) {
                    $archiveRows[$key] = $candidate;

                    continue;
                }

                // Canonical account aliases (for example 00450 and 450) are
                // one facility. Preserve the same max/min rules used by the
                // Consumer realization calculator for duplicated extracts.
                $archiveRows[$key]['plafon'] = $this->maxDecimal(
                    (string) $archiveRows[$key]['plafon'],
                    (string) $candidate['plafon']
                );
                $archiveRows[$key]['baki_debet'] = $this->maxDecimal(
                    (string) $archiveRows[$key]['baki_debet'],
                    (string) $candidate['baki_debet']
                );
                $archiveRows[$key]['lookup_order'] = min(
                    (string) $archiveRows[$key]['lookup_order'],
                    (string) $candidate['lookup_order']
                );
                $archiveRows[$key]['tgl_realisasi'] = $this->earlierDate(
                    $archiveRows[$key]['tgl_realisasi'],
                    $candidate['tgl_realisasi']
                );
            }
        });

        ksort($archiveRows, SORT_STRING);

        return [$sourceRows, $archiveRows];
    }

    /** @return array<string, string|null>|null */
    private function resolveSourceColumns(): ?array
    {
        $columns = array_fill_keys(Schema::getColumnListing(self::SOURCE_TABLE), true);
        $resolve = static function (array $candidates) use ($columns): ?string {
            foreach ($candidates as $candidate) {
                if (isset($columns[$candidate])) {
                    return $candidate;
                }
            }

            return null;
        };

        $resolved = [
            'period' => $resolve(['periode']),
            'segment' => $resolve(['segmen_kinerja']),
            'product' => $resolve(['produk_kinerja']),
            'cif' => $resolve(['cifno_clean', 'cifno']),
            'account' => $resolve(['nomor_rekening1']),
            'realization_date' => $resolve(['tgl_realisasi1', 'tgl_realisasi']),
            'plafon' => $resolve(['plafon']),
            'outstanding' => $resolve(['baki_debet1']),
            'cabang' => $resolve(['cabang_normalized', 'cabang1']),
            'unit' => $resolve(['unit_normalized', 'unit1']),
            'branch_code' => $resolve(['branch_normalized', 'branch1']),
            'rm' => $resolve(['rm_normalized', 'pn_pengelola1']),
            'manager' => $resolve(['pn_pengelola1', 'rm_normalized']),
            'initiator' => $resolve(['pn_pemrakarsa1']),
            'lookup_order' => $resolve(['uniqueid_namareport', 'nomor_rekening1']),
            'account_status' => $resolve(['status_rekening1']),
            'restructure_flag' => $resolve(['flag_restruk']),
            'restructure_pn' => $resolve(['pn_restruk1']),
            'restructure_number' => $resolve(['restruk_ke1']),
            'restructure_type' => $resolve(['jenis_restruk1']),
            'restructure_date' => $resolve(['tgl_akad_restruk']),
        ];

        foreach (['period', 'segment', 'product', 'cif', 'account', 'realization_date', 'plafon', 'outstanding', 'lookup_order'] as $required) {
            if ($resolved[$required] === null) {
                return null;
            }
        }

        return $resolved;
    }

    private function archiveSchemaReady(): bool
    {
        return Schema::hasTable(self::HISTORY_TABLE)
            && Schema::hasTable(self::CAPTURE_TABLE)
            && Schema::hasColumns(self::HISTORY_TABLE, self::HISTORY_COLUMNS)
            && Schema::hasColumns(self::CAPTURE_TABLE, self::CAPTURE_COLUMNS);
    }

    private function verifiedManifest(string $period): ?object
    {
        $manifest = DB::table(self::CAPTURE_TABLE)
            ->where('periode', $period)
            ->where('verified', true)
            ->first(['source_rows', 'archived_rows', 'content_hash']);
        if ($manifest === null || trim((string) $manifest->content_hash) === '') {
            return null;
        }

        $storedRows = (int) DB::table(self::HISTORY_TABLE)
            ->where('periode', $period)
            ->count();

        return $storedRows === (int) $manifest->archived_rows ? $manifest : null;
    }

    private function aliasedColumn(?string $column, string $alias, string $fallback = "''"): string|Expression
    {
        return $column === null
            ? DB::raw($fallback.' as '.$this->wrap($alias))
            : $column.' as '.$alias;
    }

    private function wrap(string $column): string
    {
        return DB::connection()->getQueryGrammar()->wrap($column);
    }

    private function canonicalProduct(string $product): string
    {
        $token = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($product))) ?? '';

        return match ($token) {
            'BRIGUNAKONSUMER' => 'BRIGUNA-KONSUMER',
            'KPR' => 'KPR',
            default => '',
        };
    }

    private function canonicalAccount(string $account): string
    {
        $account = strtoupper(trim($account));
        if ($account === '') {
            return '';
        }

        return ltrim($account, '0') ?: '0';
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return substr(trim((string) $value), 0, 10);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function earlierDate(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }
        if ($right === null) {
            return $left;
        }

        return min($left, $right);
    }

    private function decimalValue(mixed $value): string
    {
        $text = trim((string) ($value ?? '0'));
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $text, $matches)) {
            return number_format((float) $value, 2, '.', '');
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($matches[3] ?? '', 0, 2), 2, '0');
        $negative = ($matches[1] ?? '') === '-' && ($whole !== '0' || $fraction !== '00');

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function maxDecimal(string $left, string $right): string
    {
        $left = $this->decimalValue($left);
        $right = $this->decimalValue($right);

        return $this->compareDecimal($left, $right) >= 0 ? $left : $right;
    }

    private function compareDecimal(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $leftDigits = str_replace(['-', '.'], '', $left);
        $rightDigits = str_replace(['-', '.'], '', $right);
        $comparison = strlen($leftDigits) <=> strlen($rightDigits);
        if ($comparison === 0) {
            $comparison = strcmp($leftDigits, $rightDigits);
        }

        return $leftNegative ? -$comparison : $comparison;
    }

    private function normalizePeriod(string $period): string
    {
        $period = trim($period);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $period);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $period
        ) {
            throw new InvalidArgumentException("Periode arsip Consumer RM tidak valid: {$period}");
        }

        return $period;
    }

    /**
     * @return array{period:string,source_rows:int,archived_rows:int,verified:bool,skipped:bool,reason:?string}
     */
    private function result(
        string $period,
        int $sourceRows,
        int $archivedRows,
        bool $verified,
        bool $skipped,
        ?string $reason
    ): array {
        return [
            'period' => $period,
            'source_rows' => $sourceRows,
            'archived_rows' => $archivedRows,
            'verified' => $verified,
            'skipped' => $skipped,
            'reason' => $reason,
        ];
    }
}
