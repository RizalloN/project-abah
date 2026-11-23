<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recognize an old-dated facility only when its known contractual limit rose. */
final class MicroSameAccountTopupDetector
{
    private const BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private array $nativePeriods = [];

    private array $sourceColumns = [];

    public function bookings(string $period): array
    {
        $period = Carbon::parse($period)->toDateString();
        $key = 'micro-old-date-topup:v2:'.ReportCacheVersion::get('pinjaman').':'.$period.':'
            .app(MantriSourceInputStore::class)->fingerprint($period);

        return Cache::remember($key, 1800, fn (): array => $this->detect($period));
    }

    /** Capture only accounts present in this committed position, never a flow carried from another day. */
    public function captureNativePeriod(string $period): array
    {
        return $this->detect(Carbon::parse($period)->toDateString(), 'daily_loan_dinamis');
    }

    public function captureLwPeriod(string $period): array
    {
        return $this->detect(Carbon::parse($period)->toDateString(), 'lw321pn');
    }

    private function detect(string $period, ?string $captureSource = null): array
    {
        $start = substr($period, 0, 7).'-01';
        $previous = Carbon::parse($start)->subDay()->toDateString();
        $store = app(MantriSourceInputStore::class);
        $sources = $this->sources($previous, $period);
        if ($captureSource !== null) {
            $sources[$period] = $captureSource;
        }
        $baseline = [];
        if (isset($sources[$previous])) {
            $table = $sources[$previous];
            $accountColumn = $table === 'lw321pn' ? 'no_rekening' : 'nomor_rekening1';
            $limitColumn = $this->limitColumn($table);
            $baselineQuery = $this->query($table, [$previous])->select([$accountColumn, 'cifno', $limitColumn]);
            foreach ($this->positionRows($baselineQuery) as $row) {
                $key = $this->identity($row->{$accountColumn}, $row->cifno);
                if ($key !== null && $row->{$limitColumn} !== null && is_numeric($row->{$limitColumn})) {
                    $baseline[$key] = max($baseline[$key] ?? 0, (float) $row->{$limitColumn});
                }
            }
        } else {
            $input = $store->read($previous);
            foreach ($store->baselineAccounts(! empty($input['complete_presence']) ? $input : null) as $row) {
                $key = $this->identity($row['nomor_rekening1'] ?? null, $row['cifno'] ?? null);
                if ($key !== null && isset($row['plafon']) && is_numeric($row['plafon'])) {
                    $baseline[$key] = max($baseline[$key] ?? 0, (float) $row['plafon']);
                }
            }
            unset($input);
        }
        if ($baseline === []) {
            return [];
        }

        // Read only three financial keys per indexed position. Only proven
        // increases need their full financial/identity rows in memory.
        $candidates = [];
        foreach (['daily_loan_dinamis', 'lw321pn'] as $table) {
            $dates = array_keys(array_filter($sources, fn (string $source, string $date): bool => $source === $table && $date >= $start, ARRAY_FILTER_USE_BOTH));
            if ($dates === []) {
                continue;
            }
            foreach ($dates as $date) {
                $accountColumn = $table === 'lw321pn' ? 'no_rekening' : 'nomor_rekening1';
                $limitColumn = $this->limitColumn($table);
                // An exact position keeps the existing periode index usable. A
                // month-wide GROUP BY made MySQL scan the entire loan table.
                $query = $this->query($table, [$date])->where('tgl_realisasi', '<', $start)
                    ->where('tgl_realisasi', '>', '0001-01-01')->where($limitColumn, '>', 0);
                $observations = (clone $query)->select([$accountColumn, 'cifno', $limitColumn]);
                foreach ($this->positionRows($observations) as $row) {
                    $key = $this->identity($row->{$accountColumn}, $row->cifno);
                    $limit = (float) $row->{$limitColumn};
                    if ($key === null || ! array_key_exists($key, $baseline) || $limit <= $baseline[$key]) {
                        continue;
                    }
                    $old = $candidates[$key] ?? null;
                    if ($old === null || $limit > $old['limit']) {
                        $candidates[$key] = ['limit' => $limit, 'date' => $date, 'observed' => $date, 'table' => $table,
                            'account' => (string) $row->{$accountColumn}, 'cif' => (string) $row->cifno];
                    } elseif ($limit === $old['limit']) {
                        $candidates[$key]['date'] = min($old['date'], $date);
                        if ($date > $old['observed']) {
                            $candidates[$key] = array_replace($candidates[$key], ['observed' => $date,
                                'table' => $table, 'account' => (string) $row->{$accountColumn}, 'cif' => (string) $row->cifno]);
                        }
                    }
                }
            }
        }
        $bookings = [];
        $groups = [];
        foreach ($candidates as $key => $candidate) {
            $groups[$candidate['table'].'|'.$candidate['observed']][$key] = $candidate;
        }
        foreach ($groups as $group => $targets) {
            [$table, $date] = explode('|', $group);
            foreach (array_chunk($targets, 500, true) as $chunk) {
                $accountColumn = $table === 'lw321pn' ? 'no_rekening' : 'nomor_rekening1';
                $query = $this->query($table, [$date])->whereIn($accountColumn, array_column($chunk, 'account'));
                foreach ($query->get() as $record) {
                    $row = $this->bookingRow($table, (array) $record);
                    $key = $this->identity($row['nomor_rekening1'], $row['cifno']);
                    if (! isset($chunk[$key]) || (float) $row['plafon'] !== $chunk[$key]['limit']) {
                        continue;
                    }
                    $row['source_realization_date'] = StrictDateParser::normalize($row['tgl_realisasi']);
                    if ($row['source_realization_date'] === null || $row['source_realization_date'] >= $start) {
                        continue;
                    }
                    $row['tgl_realisasi'] = $chunk[$key]['date'];
                    $row['booking_kind'] = 'same_account_topup';
                    $row['topup_source'] = $table;
                    $row['reference_plafon'] = $baseline[$key];
                    $row['observed_plafon'] = $row['plafon'];
                    $bookings[$key] = $row;
                }
            }
        }
        // A retained archive is evidence only when that source position has
        // actually gone. A reimport at the same position supersedes it.
        foreach ($store->inputPeriods($start, $period) as $date) {
            if (isset($sources[$date])) {
                continue;
            }
            $input = $store->read($date);
            if (empty($input['complete_presence'])) {
                continue;
            }
            foreach ($input['same_account_bookings'] ?? [] as $row) {
                $key = $this->identity($row['nomor_rekening1'] ?? null, $row['cifno'] ?? null);
                if ($key === null || ! isset($baseline[$key]) || (float) $row['plafon'] <= $baseline[$key]) {
                    continue;
                }
                $old = $bookings[$key] ?? null;
                if ($old === null || (float) $row['plafon'] > (float) $old['plafon']) {
                    $bookings[$key] = $row;
                } elseif ((float) $row['plafon'] === (float) $old['plafon']) {
                    $effective = min($old['tgl_realisasi'], $row['tgl_realisasi']);
                    $bookings[$key] = $row['periode'] > $old['periode'] ? $row : $old;
                    $bookings[$key]['tgl_realisasi'] = $effective;
                }
            }
        }
        if ($captureSource !== null) {
            // Persist the current account and owner, with the first observed
            // date for this limit. Do not store already-closed earlier flows.
            $present = [];
            $cifs = array_values(array_unique(array_column($bookings, 'cifno')));
            foreach (array_chunk($cifs, 500) as $chunk) {
                foreach ($this->query($captureSource, [$period])->whereIn('cifno', $chunk)->where('tgl_realisasi', '<', $start)->get() as $record) {
                    $row = $this->bookingRow($captureSource, (array) $record);
                    $key = $this->identity($row['nomor_rekening1'], $row['cifno']);
                    if (isset($bookings[$key]) && (float) $row['plafon'] === (float) $bookings[$key]['plafon']) {
                        $present[] = array_replace($row, array_intersect_key($bookings[$key], array_flip([
                            'tgl_realisasi', 'source_realization_date', 'booking_kind', 'topup_source', 'reference_plafon', 'observed_plafon',
                        ])));
                    }
                }
            }

            return $present;
        }
        ksort($bookings, SORT_STRING);

        return array_values($bookings);
    }

    private function sources(string $previous, string $period): array
    {
        $sources = [];
        $this->nativePeriods = [];
        $this->sourceColumns = [];
        if (Schema::hasTable('lw321pn')) {
            foreach (DB::table('lw321pn')->whereBetween('periode', [$previous, $period])->distinct()->pluck('periode') as $date) {
                $sources[(string) $date] = 'lw321pn';
            }
        }
        if (Schema::hasTable('daily_loan_dinamis')) {
            $periods = DB::table('daily_loan_dinamis')->whereBetween('periode', [$previous, $period])->select('periode')->distinct();
            $hasSourceId = $this->hasSourceColumn('daily_loan_dinamis', 'uniqueid_namareport');
            $positions = DB::query()->fromSub($periods, 'daily_positions')->select('daily_positions.periode');
            if ($hasSourceId) {
                // Stop at the first native row for each indexed position.
                $native = DB::table('daily_loan_dinamis')
                    ->selectRaw('1')
                    ->whereColumn('daily_loan_dinamis.periode', 'daily_positions.periode')
                    ->where(fn (Builder $q) => $q->whereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%'))
                    ->limit(1);
                $positions->selectSub($native, 'has_native');
            }
            foreach ($positions->get() as $position) {
                $date = (string) $position->periode;
                if (! $hasSourceId || $position->has_native !== null) {
                    $sources[$date] = 'daily_loan_dinamis';
                    $this->nativePeriods[$date] = true;
                } else {
                    $sources[$date] ??= 'daily_loan_dinamis';
                }
            }
        }

        return $sources;
    }

    private function query(string $table, array $dates): Builder
    {
        $query = DB::table($table);
        count($dates) === 1 ? $query->where('periode', $dates[0]) : $query->whereIn('periode', $dates);
        if ($table === 'lw321pn') {
            return $query->whereIn(DB::raw('UPPER(TRIM(kanca))'), self::BRANCHES)
                ->whereRaw('UPPER('.Lw321DailyLoanMapper::segmentSql('description').") = 'MICRO'")
                ->whereRaw("UPPER(COALESCE(description, '')) NOT LIKE '%BRIGUNA%'")
                ->whereRaw("UPPER(REPLACE(REPLACE(description, ' ', ''), '-', '')) <> 'KREDITMIKROGBT'");
        }
        if ($this->hasSourceColumn($table, 'uniqueid_namareport')) {
            // Where a native position exists, materialized fallback rows are
            // not a second financial source for that position.
            $nativeDates = array_values(array_intersect($dates, array_keys($this->nativePeriods)));
            if ($nativeDates !== []) {
                $query->where(fn (Builder $q) => $q->whereNotIn('periode', $nativeDates)
                    ->orWhereNull('uniqueid_namareport')->orWhere('uniqueid_namareport', 'not like', 'LW321PN:%'));
            }
        }
        $branch = $this->hasSourceColumn($table, 'cabang_normalized')
            ? "UPPER(COALESCE(NULLIF(TRIM(cabang1), ''), TRIM(cabang_normalized)))" : 'UPPER(TRIM(cabang1))';
        $query->whereIn(DB::raw($branch), self::BRANCHES);
        if ($this->hasSourceColumn($table, 'segmen_kinerja')) {
            $query->whereRaw("UPPER(TRIM(segmen_kinerja)) = 'MICRO'");
        } else {
            $query->whereRaw(Lw321DailyLoanMapper::segmentSql('description')." = 'MICRO'");
        }
        if ($this->hasSourceColumn($table, 'produk_kinerja')) {
            $query->where(fn (Builder $q) => $q->whereNull('produk_kinerja')->orWhereRaw("UPPER(produk_kinerja) NOT LIKE '%BRIGUNA%'"));
        }
        if ($this->hasSourceColumn($table, 'description')) {
            $query->whereRaw("UPPER(COALESCE(description, '')) NOT LIKE '%BRIGUNA%'")
                ->whereRaw("UPPER(REPLACE(REPLACE(COALESCE(description, ''), ' ', ''), '-', '')) <> 'KREDITMIKROGBT'");
        }

        return $query;
    }

    private function bookingRow(string $table, array $raw): array
    {
        if ($table !== 'lw321pn') {
            return array_replace($raw, ['nomor_rekening1' => $this->account($raw['nomor_rekening1']),
                'cifno' => strtoupper(trim((string) ($raw['cifno'] ?? '')))]);
        }
        $classification = Lw321DailyLoanMapper::classifyDescription($raw['description'] ?? null);

        return array_replace($raw, [
            'nomor_rekening1' => $this->account($raw['no_rekening']), 'cifno' => strtoupper(trim((string) ($raw['cifno'] ?? ''))),
            'plafon' => $raw[$this->limitColumn($table)] ?? null, 'baki_debet1' => $raw['balance_dalam_idr'] ?? null,
            'pn_pengelola1' => $raw['pn_pengelola_singlepn'] ?? $raw['pn_pengelola_1'] ?? null,
            'pn_pemrakarsa1' => $raw['pn_pemrakarsa'] ?? null,
            'cabang1' => $raw['kanca'] ?? null, 'unit1' => $raw['uker'] ?? null, 'branch1' => $raw['kode_uker'] ?? null,
            'segmen_kinerja' => $classification['segment'], 'produk_kinerja' => $classification['product'],
            'produk_dashboard' => $classification['product'],
        ]);
    }

    private function limitColumn(string $table): string
    {
        return $table === 'lw321pn' && $this->hasSourceColumn($table, 'plafon_dalam_idr') ? 'plafon_dalam_idr' : 'plafon';
    }

    private function hasSourceColumn(string $table, string $column): bool
    {
        $columns = $this->sourceColumns[$table] ??= array_map('strtolower', Schema::getColumnListing($table));

        return in_array($column, $columns, true);
    }

    /** @return iterable<int, \stdClass> */
    private function positionRows(Builder $query): iterable
    {
        // The detector only consumes raw scalars and has no after-query callbacks.
        return $query->getConnection()->cursor($query->toSql(), $query->getBindings());
    }

    private function identity(mixed $account, mixed $cif): ?string
    {
        $account = $this->account($account);
        $cif = strtoupper(trim((string) $cif));

        return $account !== '' && $cif !== '' ? $account.'|'.$cif : null;
    }

    private function account(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return $value === '' ? '' : (ltrim($value, '0') ?: '0');
    }
}
