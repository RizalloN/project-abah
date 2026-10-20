<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MantriSourceInputStore
{
    private const BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const BASELINE_COLUMNS = ['nomor_rekening1', 'cifno', 'baki_debet1', 'description', 'segmen_kinerja', 'produk_kinerja', 'unit1', 'cabang1'];

    private string $directory;

    private bool $enabled;

    private array $readCache = [];

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? storage_path('app/mantri-source-inputs'), '/\\');
        $this->enabled = $directory !== null || ! app()->runningUnitTests();
    }

    public function write(string $period, array $data): void
    {
        $period = $this->period($period);
        if (! $this->enabled) {
            throw new RuntimeException('Mantri source input testing requires an explicit temporary directory.');
        }

        $data = $this->normalize($period, $data);
        $digest = hash_init('sha256');
        foreach ($this->serializedChunks($data) as $chunk) {
            hash_update($digest, $chunk);
        }
        $checksum = hash_final($digest);
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Cannot create Mantri source input directory.');
        }

        $lock = fopen($this->directory.'/.write.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock Mantri source inputs.');
        }

        $temporary = null;
        try {
            if ($this->verifiedChecksum($this->path($period)) === $checksum) {
                $this->remember($period, $data);

                return;
            }

            $temporary = tempnam($this->directory, '.mantri-');
            if ($temporary === false) {
                throw new RuntimeException('Cannot persist complete Mantri source inputs.');
            }
            $this->writeCompressedPayload($temporary, $checksum, $data);
            if ($this->verifiedChecksum($temporary) !== $checksum) {
                throw new RuntimeException('Mantri source input checksum verification failed.');
            }
            if (! rename($temporary, $this->path($period))) {
                throw new RuntimeException('Cannot replace Mantri source input archive.');
            }
            $temporary = null;
            if ($this->verifiedChecksum($this->path($period)) !== $checksum) {
                throw new RuntimeException('Mantri source input readback failed.');
            }
            $this->remember($period, $data);
            ReportCacheVersion::bump('pinjaman');
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function read(string $period): ?array
    {
        $period = $this->period($period);
        $path = $this->path($period);
        if (! $this->enabled || ! is_file($path)) {
            return null;
        }
        $checksum = hash_file('sha256', $path);
        if (isset($this->readCache[$period]) && $this->readCache[$period]['checksum'] === $checksum) {
            return $this->readCache[$period]['data'];
        }
        foreach ($this->readCache as $cached) {
            if (count($cached['data']['baseline_accounts'] ?? []) > 20000) {
                $this->readCache = [];
                break;
            }
        }
        unset($cached);
        $data = $this->decode($path, $period);
        if ($data !== null) {
            $this->remember($period, $data);
        }

        return $data;
    }

    /** Complete positions only; a baseline supplement is not a report period. */
    public function periods(): array
    {
        $periods = [];
        foreach ($this->files() as $period => $path) {
            $data = $this->read($period);
            if ($data !== null && $data['complete_presence']) {
                $periods[] = $period;
            }
            unset($data);
        }
        sort($periods, SORT_STRING);

        return $periods;
    }

    /** Valid supplements also carry owner evidence without declaring a position. */
    public function inputPeriods(?string $start = null, ?string $end = null): array
    {
        $start = $start === null ? null : $this->period($start);
        $end = $end === null ? null : $this->period($end);
        $periods = [];
        foreach ($this->files() as $period => $path) {
            if (($start === null || $period >= $start) && ($end === null || $period <= $end)
                && $this->read($period) !== null) {
                $periods[] = $period;
            }
        }

        return $periods;
    }

    public function fingerprint(string $period): string
    {
        $period = $this->period($period);
        $start = substr($period, 0, 7).'-01';
        $previous = date('Y-m-d', strtotime($start.' -1 day'));
        $parts = [];
        foreach ($this->files() as $date => $path) {
            if ($date === $previous || ($date >= $start && $date <= $period)) {
                $parts[] = $date.':'.hash_file('sha256', $path);
            }
        }

        return hash('sha256', implode('|', $parts));
    }

    /** Yield compact archived balances without expanding an entire month-end position in memory. */
    public function baselineAccounts(?array $input): iterable
    {
        foreach (['baseline_accounts', 'unclassified_accounts'] as $kind) {
            foreach ($input[$kind] ?? [] as $row) {
                yield $kind === 'baseline_accounts' && array_is_list($row)
                    ? array_combine(self::BASELINE_COLUMNS, $row) : $row;
            }
        }
    }

    /** Keep this derived Native archive consistent with an already committed managed deletion. */
    public function refreshDailyLoanAfterDelete(?string $period = null): array
    {
        $period = $period === null ? null : $this->period($period);
        if (! $this->enabled) {
            return ['disabled' => true];
        }
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->refreshDailyLoanAfterDelete($period));

            return ['deferred' => true, 'reason' => 'awaiting_outer_commit'];
        }
        if (DB::connection()->getPdo()->inTransaction()) {
            throw new RuntimeException('Native Mantri archives cannot be invalidated before the deletion commits.');
        }
        if (! Schema::hasTable('daily_loan_dinamis') || ! Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')) {
            return ['skipped' => true, 'reason' => 'native_source_not_proven'];
        }
        $result = ['refreshed_periods' => [], 'invalidated_periods' => [], 'skipped_periods' => []];
        foreach ($period === null ? array_keys($this->files()) : [$period] as $date) {
            $input = $this->read($date);
            if (($input['source']['source_kind'] ?? null) !== 'daily_loan_import') {
                $result['skipped_periods'][] = $date;
                unset($input, $this->readCache[$date]);

                continue;
            }
            $hasNative = DB::table('daily_loan_dinamis')->where('periode', $date)
                ->where(function ($query): void {
                    $query->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%');
                })->exists();
            if ($hasNative) {
                unset($input, $this->readCache[$date]);
                $this->captureDailyLoanPeriod($date);
                $result['refreshed_periods'][] = $date;
            } else {
                $input['complete_presence'] = false;
                foreach (['presence', 'bookings', 'unclassified_accounts', 'baseline_accounts'] as $kind) {
                    $input[$kind] = [];
                }
                $input['source']['capture_state'] = 'removed_from_native_daily_loan';
                if (($input['source']['row_count'] ?? 0) > 0) {
                    $input['source']['previous_raw_sha256'] = $input['source']['raw_sha256'] ?? null;
                }
                $input['source']['row_count'] = 0;
                $input['source']['raw_sha256'] = hash('sha256', '');
                $input['source']['max_updated_at'] = null;
                $this->write($date, $input);
                $result['invalidated_periods'][] = $date;
            }
            unset($this->readCache[$date]);
        }

        return $result;
    }

    /** Capture committed Native Daily Loan inputs without reclassifying their canonical products. */
    public function captureDailyLoanPeriod(string $period): array
    {
        $period = $this->period($period);
        if (! $this->enabled) {
            return ['period' => $period, 'disabled' => true];
        }
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->captureDailyLoanPeriod($period));

            return ['period' => $period, 'deferred' => true, 'reason' => 'awaiting_outer_commit'];
        }
        if (DB::connection()->getPdo()->inTransaction()) {
            throw new RuntimeException('Native Mantri inputs cannot be published before the source transaction commits.');
        }
        if (! Schema::hasTable('daily_loan_dinamis')) {
            return ['period' => $period, 'skipped' => true, 'reason' => 'daily_loan_source_unavailable'];
        }
        if (! Schema::hasColumn('daily_loan_dinamis', 'uniqueid_namareport')) {
            return ['period' => $period, 'skipped' => true, 'reason' => 'native_source_not_proven'];
        }
        $ownerEvidence = $this->captureOwnerEvidence($period);

        $query = DB::table('daily_loan_dinamis')->where('periode', $period)
            ->where(function ($query): void {
                $query->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%');
            });
        $presence = [];
        $bookings = [];
        $unclassified = [];
        $baselineAccounts = [];
        $rows = 0;
        $maxUpdated = null;
        $digest = hash_init('sha256');
        $month = substr($period, 0, 7);
        $inputFields = array_fill_keys([
            'periode', 'nomor_rekening1', 'cifno', 'tgl_realisasi', 'plafon', 'baki_debet1',
            'pn_pengelola1', 'pn_pengelola_singlepn', 'pn_pemrakarsa1', 'raw_singlepn_available',
            'cabang1', 'unit1', 'branch1', 'cabang_normalized', 'unit_normalized', 'branch_normalized',
            'description', 'segmen_kinerja', 'produk_kinerja',
        ], true);
        foreach ($query->orderBy('nomor_rekening1')->cursor() as $record) {
            $raw = (array) $record;
            hash_update($digest, $this->json($raw)."\n");
            $rows++;
            if (($raw['periode'] ?? null) !== $period) {
                throw new RuntimeException('Native Mantri inputs contain a different source position date.');
            }
            $account = $this->account($raw['nomor_rekening1'] ?? null);
            if ($account === '') {
                throw new RuntimeException('Native Daily Loan source input contains a blank account.');
            }
            $balance = $this->amount($raw['baki_debet1'] ?? null);
            $presence[] = $account;
            $maxUpdated = max((string) $maxUpdated, (string) ($raw['updated_at'] ?? '')) ?: null;
            $branch = strtoupper((string) ($this->text($raw['cabang1'] ?? null) ?? $this->text($raw['cabang_normalized'] ?? null)));
            if (! in_array($branch, self::BRANCHES, true)) {
                continue;
            }
            $row = $this->normalizeRow($period, array_replace(array_intersect_key($raw, $inputFields), [
                'nomor_rekening1' => $account,
                'baki_debet1' => $balance,
                'cabang1' => $raw['cabang1'] ?? $raw['cabang_normalized'] ?? null,
                'unit1' => $raw['unit1'] ?? $raw['unit_normalized'] ?? null,
                'branch1' => $raw['branch1'] ?? $raw['branch_normalized'] ?? null,
            ]), true);
            if ($row['segmen_kinerja'] === null && $row['produk_kinerja'] === null) {
                $unclassified[] = $row;
            }
            if ($this->isMonthEnd($period) && $this->baselineEligible($row)) {
                $baselineAccounts[] = array_values($this->normalizeBaselineRow($period, $row, true));
            }
            if ($row['tgl_realisasi'] !== null && substr($row['tgl_realisasi'], 0, 7) === $month
                && $row['tgl_realisasi'] <= $period && ! $this->excluded($row['produk_kinerja'])) {
                $bookings[] = $row;
            }
        }
        if ($rows === 0) {
            return ['period' => $period, 'skipped' => true, 'reason' => 'no_native_source_rows'];
        }

        $this->write($period, [
            'version' => 1,
            'period' => $period,
            'source' => [
                'source_kind' => 'daily_loan_import',
                'table' => 'daily_loan_dinamis',
                'classification_basis' => 'canonical_daily_loan',
                'baseline_columns' => self::BASELINE_COLUMNS,
                'row_count' => $rows,
                'max_updated_at' => $maxUpdated,
                'raw_sha256' => hash_final($digest),
            ],
            'complete_presence' => true,
            'presence' => $presence,
            'bookings' => $bookings,
            'unclassified_accounts' => $unclassified,
            'baseline_accounts' => $baselineAccounts,
            'owner_evidence' => $ownerEvidence,
        ]);

        return $this->read($period) ?? throw new RuntimeException('Captured Native Mantri inputs are unreadable.');
    }

    /** Read raw imported inputs after Daily Loan materialization has committed. */
    public function captureLw321Period(string $period): array
    {
        $period = $this->period($period);
        if (! $this->enabled) {
            return ['period' => $period, 'disabled' => true];
        }
        if (! Schema::hasTable('lw321pn')) {
            throw new RuntimeException('LW321 source table is unavailable.');
        }
        $ownerEvidence = $this->captureOwnerEvidence($period);

        $presence = [];
        $bookings = [];
        $unclassified = [];
        $baselineAccounts = [];
        $rows = 0;
        $maxUpdated = null;
        $digest = hash_init('sha256');
        $month = substr($period, 0, 7);
        foreach (DB::table('lw321pn')->where('periode', $period)->orderBy('no_rekening')->cursor() as $record) {
            $raw = (array) $record;
            hash_update($digest, $this->json($raw)."\n");
            $rows++;
            $account = $this->account($raw['no_rekening'] ?? null);
            if ($account === '') {
                throw new RuntimeException('LW321 source input contains a blank account.');
            }
            $balance = $this->amount($raw['balance_dalam_idr'] ?? null);
            $presence[] = $account;
            $maxUpdated = max((string) $maxUpdated, (string) ($raw['updated_at'] ?? '')) ?: null;
            $branch = strtoupper(trim((string) ($raw['kanca'] ?? '')));
            if (! in_array($branch, self::BRANCHES, true)) {
                continue;
            }

            $description = $this->text($raw['description'] ?? null);
            $classification = Lw321DailyLoanMapper::classifyDescription($description);
            $booking = StrictDateParser::normalize($this->text($raw['tgl_realisasi'] ?? null));
            $manager = array_key_exists('pn_pengelola_singlepn', $raw)
                ? $this->text($raw['pn_pengelola_singlepn'])
                : $this->text($raw['pn_pengelola_1'] ?? null);
            $row = [
                'periode' => $period,
                'nomor_rekening1' => $account,
                'cifno' => $this->text($raw['cifno'] ?? null),
                'tgl_realisasi' => $booking,
                'plafon' => $raw['plafon_dalam_idr'] ?? $raw['plafon'] ?? null,
                'baki_debet1' => $balance,
                'pn_pengelola1' => $manager,
                'pn_pengelola_singlepn' => $this->text($raw['pn_pengelola_singlepn'] ?? null),
                'raw_singlepn_available' => array_key_exists('pn_pengelola_singlepn', $raw),
                'pn_pemrakarsa1' => $this->text($raw['pn_pemrakarsa'] ?? null),
                'cabang1' => $this->text($raw['kanca'] ?? null),
                'unit1' => $this->text($raw['uker'] ?? null),
                'branch1' => $this->text($raw['kode_uker'] ?? null),
                'description' => $description,
                'segmen_kinerja' => $classification['segment'],
                'produk_kinerja' => $classification['product'],
            ];
            if (! $classification['matched']) {
                $unclassified[] = $row;
            }
            if ($this->isMonthEnd($period) && $this->baselineEligible($row)) {
                $baselineAccounts[] = array_values($this->normalizeBaselineRow($period, $row, true));
            }
            if ($booking !== null && substr($booking, 0, 7) === $month && $booking <= $period
                && ! $this->excluded($classification['product'], $description)) {
                $bookings[] = $row;
            }
        }
        if ($rows === 0) {
            throw new RuntimeException('An empty LW321 source cannot declare complete account presence.');
        }

        $dailySignature = null;
        if (Schema::hasTable('daily_loan_dinamis')) {
            $query = DB::table('daily_loan_dinamis')->where('periode', $period);
            $dailySignature = ['row_count' => (clone $query)->count()];
            if (Schema::hasColumn('daily_loan_dinamis', 'updated_at')) {
                $dailySignature['max_updated_at'] = $query->max('updated_at');
            }
        }
        $data = [
            'version' => 1,
            'period' => $period,
            'source' => [
                'source_kind' => 'lw321_import',
                'table' => 'lw321pn',
                'baseline_columns' => self::BASELINE_COLUMNS,
                'row_count' => $rows,
                'max_updated_at' => $maxUpdated,
                'raw_sha256' => hash_final($digest),
                'daily_signature' => $dailySignature,
            ],
            'complete_presence' => true,
            'presence' => $presence,
            'bookings' => $bookings,
            'unclassified_accounts' => $unclassified,
            'baseline_accounts' => $baselineAccounts,
            'owner_evidence' => $ownerEvidence,
        ];
        $this->write($period, $data);

        return $this->read($period) ?? throw new RuntimeException('Captured Mantri source inputs are unreadable.');
    }

    private function normalize(string $period, array $data): array
    {
        if (($data['version'] ?? 1) !== 1 || ($data['period'] ?? $period) !== $period
            || ! is_array($data['source'] ?? null) || $data['source'] === []
            || ! is_bool($data['complete_presence'] ?? null)) {
            throw new InvalidArgumentException('Invalid Mantri source input metadata.');
        }
        $presence = [];
        foreach ($data['presence'] ?? [] as $value) {
            $account = $this->account($value);
            if ($account === '') {
                throw new InvalidArgumentException('Mantri presence contains a blank account.');
            }
            $presence[$account] = $account;
        }
        sort($presence, SORT_STRING);
        if ($data['complete_presence'] && $presence === []) {
            throw new InvalidArgumentException('Complete Mantri presence requires actual accounts.');
        }
        $data['version'] = 1;
        $data['period'] = $period;
        $data['presence'] = array_values($presence);
        $presenceLookup = array_fill_keys($data['presence'], true);
        $nativeClassification = ($data['source']['source_kind'] ?? null) === 'daily_loan_import';
        $rowKinds = ['bookings', 'unclassified_accounts'];
        if (array_key_exists('baseline_accounts', $data)) {
            $rowKinds[] = 'baseline_accounts';
        }
        foreach ($rowKinds as $kind) {
            $normalized = [];
            foreach ($data[$kind] ?? [] as $row) {
                if (! is_array($row)) {
                    throw new InvalidArgumentException('Invalid Mantri source input row.');
                }
                $packedInput = null;
                if ($kind === 'baseline_accounts' && array_is_list($row)) {
                    if (count($row) !== count(self::BASELINE_COLUMNS)) {
                        throw new InvalidArgumentException('Invalid compact Mantri baseline input.');
                    }
                    $packedInput = $row;
                    $row = array_combine(self::BASELINE_COLUMNS, $row);
                }
                $branch = strtoupper((string) ($this->text($row['cabang1'] ?? null) ?? $this->text($row['cabang_normalized'] ?? null)));
                if (! in_array($branch, self::BRANCHES, true)) {
                    continue;
                }
                $normalizedRow = $kind === 'baseline_accounts'
                    ? $this->normalizeBaselineRow($period, $row, $nativeClassification)
                    : $this->normalizeRow($period, $row, $nativeClassification);
                if ($normalizedRow !== $row) {
                    $row = $normalizedRow;
                }
                unset($normalizedRow);
                if ($kind === 'bookings') {
                    if ($row['tgl_realisasi'] === null || substr($row['tgl_realisasi'], 0, 7) !== substr($period, 0, 7)
                        || $row['tgl_realisasi'] > $period || $this->excluded($row['produk_kinerja'], $nativeClassification ? null : $row['description'])) {
                        continue;
                    }
                } elseif ($kind === 'baseline_accounts') {
                    if (! $this->isMonthEnd($period)) {
                        throw new InvalidArgumentException('Mantri baseline accounts require an exact calendar month end.');
                    }
                    if (! $this->baselineEligible($row)) {
                        continue;
                    }
                } elseif ($row['segmen_kinerja'] !== null || $row['produk_kinerja'] !== null) {
                    throw new InvalidArgumentException('Unclassified Mantri inputs cannot invent a product classification.');
                }
                $account = $row['nomor_rekening1'];
                if ($data['complete_presence'] && ! isset($presenceLookup[$account])) {
                    throw new InvalidArgumentException('Mantri source row is absent from its complete position.');
                }
                $storedRow = $kind === 'baseline_accounts' ? array_values($row) : $row;
                if ($packedInput !== null && $storedRow === $packedInput) {
                    $storedRow = $packedInput;
                }
                if (isset($normalized[$account]) && $normalized[$account] !== $storedRow) {
                    throw new InvalidArgumentException('Conflicting duplicate Mantri source account.');
                }
                $normalized[$account] = $storedRow;
            }
            ksort($normalized, SORT_STRING);
            $data[$kind] = array_values($normalized);
        }
        $data['owner_evidence'] = $data['owner_evidence'] ?? [];

        return $data;
    }

    private function normalizeRow(string $period, array $row, bool $nativeClassification = false): array
    {
        if (($row['periode'] ?? $period) !== $period) {
            throw new InvalidArgumentException('Mantri input row has a different position date.');
        }
        $row['periode'] = $period;
        $row['nomor_rekening1'] = $this->account($row['nomor_rekening1'] ?? null);
        if ($row['nomor_rekening1'] === '') {
            throw new InvalidArgumentException('Mantri source input requires an account.');
        }
        $row['cifno'] = $this->text($row['cifno'] ?? null);
        $row['baki_debet1'] = $this->amount($row['baki_debet1'] ?? null);
        $row['plafon'] = ($row['plafon'] ?? null) === null ? null : $this->amount($row['plafon']);
        $row['tgl_realisasi'] = StrictDateParser::normalize($this->text($row['tgl_realisasi'] ?? null));
        $row['description'] = $this->text($row['description'] ?? null);
        if ($nativeClassification) {
            $segment = $this->text($row['segmen_kinerja'] ?? null);
            $product = $this->text($row['produk_kinerja'] ?? null);
            $row['segmen_kinerja'] = $segment === null ? null : strtoupper($segment);
            $row['produk_kinerja'] = $product === null ? null : Lw321DailyLoanMapper::normalizeToken($product);
        } else {
            $classification = Lw321DailyLoanMapper::classifyDescription($row['description']);
            $row['segmen_kinerja'] = $classification['matched'] ? strtoupper($classification['segment']) : null;
            $row['produk_kinerja'] = $classification['matched'] ? Lw321DailyLoanMapper::normalizeToken($classification['product']) : null;
        }
        foreach (['cabang1', 'unit1', 'branch1', 'pn_pengelola1', 'pn_pengelola_singlepn', 'pn_pemrakarsa1'] as $field) {
            $row[$field] = $this->text($row[$field] ?? null);
        }
        foreach (['cabang', 'unit', 'branch'] as $field) {
            $canonical = $nativeClassification ? $this->text($row[$field.'_normalized'] ?? null) : null;
            $row[$field.'_normalized'] = strtoupper((string) ($canonical ?? $row[$field.'1']));
        }
        $row['quality'] = ['blank_cif' => $row['cifno'] === null, 'missing_booking_date' => $row['tgl_realisasi'] === null];

        return $row;
    }

    /** Month-end balances need no owner, booking, plafond, or repeated position fields. */
    private function normalizeBaselineRow(string $period, array $row, bool $nativeClassification): array
    {
        if (($row['periode'] ?? $period) !== $period) {
            throw new InvalidArgumentException('Mantri baseline row has a different position date.');
        }
        $account = $this->account($row['nomor_rekening1'] ?? null);
        if ($account === '') {
            throw new InvalidArgumentException('Mantri baseline input requires an account.');
        }
        $description = $this->text($row['description'] ?? null);
        if ($nativeClassification) {
            $segment = $this->text($row['segmen_kinerja'] ?? null);
            $product = $this->text($row['produk_kinerja'] ?? null);
        } else {
            $classification = Lw321DailyLoanMapper::classifyDescription($description);
            $segment = $classification['matched'] ? $classification['segment'] : null;
            $product = $classification['matched'] ? $classification['product'] : null;
        }

        return [
            'nomor_rekening1' => $account,
            'cifno' => $this->text($row['cifno'] ?? null),
            'baki_debet1' => $this->amount($row['baki_debet1'] ?? null),
            'description' => $description,
            'segmen_kinerja' => $segment === null ? null : strtoupper($segment),
            'produk_kinerja' => $product === null ? null : Lw321DailyLoanMapper::normalizeToken($product),
            'unit1' => $this->text($row['unit1'] ?? null) ?? $this->text($row['unit_normalized'] ?? null),
            'cabang1' => $this->text($row['cabang1'] ?? null) ?? $this->text($row['cabang_normalized'] ?? null),
        ];
    }

    private function decode(string $path, string $period): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        try {
            $verifiedChecksum = $this->verifiedChecksum($path);
            $compressed = file_get_contents($path);
            $json = $compressed === false ? false : @gzdecode($compressed);
            if ($json === false) {
                return null;
            }
            $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $data = $envelope['data'] ?? null;
            unset($json, $compressed);
            if (! is_array($data) || ($data['version'] ?? null) !== 1 || ($data['period'] ?? null) !== $period
                || ! is_string($envelope['checksum_sha256'] ?? null)
                || ! hash_equals($envelope['checksum_sha256'], $verifiedChecksum ?? hash('sha256', $this->json($data)))) {
                return null;
            }
            unset($envelope);

            return $this->normalize($period, $data);
        } catch (Throwable) {
            return null;
        }
    }

    /** Verify the exact serialized payload without materializing a second 300k-row array. */
    private function verifiedChecksum(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $stream = @gzopen($path, 'rb');
        if ($stream === false) {
            return null;
        }
        try {
            $header = @gzread($stream, 1024);
            if ($header === false || preg_match('/\A\{"checksum_sha256":"([a-f0-9]{64})","data":/', $header, $match) !== 1) {
                return null;
            }
            $digest = hash_init('sha256');
            $pending = substr($header, strlen($match[0]));
            while (! gzeof($stream)) {
                $chunk = @gzread($stream, 1048576);
                if ($chunk === false || ($chunk === '' && ! gzeof($stream))) {
                    return null;
                }
                $pending .= $chunk;
                if (strlen($pending) > 1) {
                    hash_update($digest, substr($pending, 0, -1));
                    $pending = substr($pending, -1);
                }
            }
            if (strlen($pending) > 1) {
                hash_update($digest, substr($pending, 0, -1));
                $pending = substr($pending, -1);
            }

            return $pending === '}' && hash_equals($match[1], hash_final($digest)) ? $match[1] : null;
        } finally {
            gzclose($stream);
        }
    }

    private function writeCompressedPayload(string $path, string $checksum, array $data): void
    {
        $stream = gzopen($path, 'wb6');
        if ($stream === false) {
            throw new RuntimeException('Cannot open compressed Mantri source inputs.');
        }
        try {
            $buffer = '{"checksum_sha256":"'.$checksum.'","data":';
            foreach ($this->serializedChunks($data) as $chunk) {
                $buffer .= $chunk;
                if (strlen($buffer) >= 65536) {
                    if (gzwrite($stream, $buffer) !== strlen($buffer)) {
                        throw new RuntimeException('Cannot persist complete Mantri source inputs.');
                    }
                    $buffer = '';
                }
            }
            $buffer .= '}';
            if (gzwrite($stream, $buffer) !== strlen($buffer)) {
                throw new RuntimeException('Cannot finish compressed Mantri source inputs.');
            }
        } finally {
            gzclose($stream);
        }
    }

    /** Preserve normal JSON bytes while serializing large account lists one row at a time. */
    private function serializedChunks(array $data): iterable
    {
        yield '{';
        $separator = '';
        foreach ($data as $key => $value) {
            yield $separator.$this->json((string) $key).':';
            $separator = ',';
            if (is_array($value) && array_is_list($value)) {
                yield '[';
                $itemSeparator = '';
                foreach ($value as $item) {
                    yield $itemSeparator.$this->json($item);
                    $itemSeparator = ',';
                }
                yield ']';
            } else {
                yield $this->json($value);
            }
        }
        yield '}';
    }

    private function captureOwnerEvidence(string $period): array
    {
        $this->readCache = [];
        $evidence = $this->read($period)['owner_evidence'] ?? [];
        $this->readCache = [];

        return $evidence;
    }

    private function remember(string $period, array $data): void
    {
        if (count($data['baseline_accounts'] ?? []) > 20000) {
            $this->readCache = [];
        }
        if (count($this->readCache) >= 3) {
            array_shift($this->readCache);
        }
        $this->readCache[$period] = ['checksum' => hash_file('sha256', $this->path($period)), 'data' => $data];
    }

    private function files(): array
    {
        $files = [];
        if (! $this->enabled) {
            return $files;
        }
        foreach (glob($this->directory.'/*.json.gz') ?: [] as $path) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\.json\.gz$/', basename($path), $match) === 1
                && StrictDateParser::normalize($match[1]) === $match[1]) {
                $files[$match[1]] = $path;
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    private function period(string $period): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) !== 1 || StrictDateParser::normalize($period) !== $period) {
            throw new InvalidArgumentException('Mantri source input requires a canonical date.');
        }

        return $period;
    }

    private function path(string $period): string
    {
        return $this->directory.'/'.$period.'.json.gz';
    }

    private function account(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException('Mantri account must be a scalar identifier.');
        }
        $value = strtoupper(trim((string) $value));

        return $value === '' || $value === '-' ? '' : (ltrim($value, '0') ?: '0');
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function amount(mixed $value): string
    {
        if ($value === null || ! is_numeric($value)) {
            throw new InvalidArgumentException('Mantri source input requires a numeric balance or amount.');
        }

        return (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HALF_UP);
    }

    private function excluded(?string $product, ?string $description = null): bool
    {
        $product = Lw321DailyLoanMapper::normalizeToken($product);
        $description = Lw321DailyLoanMapper::normalizeToken($description);

        return str_contains($product, 'BRIGUNA') || in_array($product, ['KURKECIL', 'KURRITEL'], true)
            || $description === 'KREDITMIKROKURRITEL2015';
    }

    private function isMonthEnd(string $period): bool
    {
        return date('Y-m-t', strtotime($period)) === $period;
    }

    private function baselineEligible(array $row): bool
    {
        $segment = strtoupper(trim((string) ($row['segmen_kinerja'] ?? '')));
        $product = Lw321DailyLoanMapper::normalizeToken($row['produk_kinerja'] ?? null);
        if ($segment === 'MICRO') {
            return ! in_array($product, ['KURRITEL', 'KURKECIL'], true);
        }

        return $segment === '' && $product === ''
            && str_starts_with(strtoupper((string) ($this->text($row['unit1'] ?? null) ?? $this->text($row['unit_normalized'] ?? null))), 'UNIT');
    }

    private function json(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
