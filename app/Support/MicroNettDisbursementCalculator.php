<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MicroNettDisbursementCalculator
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const KUR_RITEL_DESCRIPTION = 'Kredit Mikro - KUR Ritel 2015';

    private const UNASSIGNED_RM = 'RM LAIN';

    private const AREA_6_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const READ_CACHE_VERSION = 'v2-kanwil-gross-singlepn';

    private const MANTRI_READ_CACHE_VERSION = 'v16-verified-booking-cohort';

    private const READ_CACHE_SECONDS = 1800;

    /**
     * Report reads share one month scan of the nominative per data version;
     * snapshot builders keep calling kurRm() directly for a fresh read.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function cachedKurRm(string $period): array
    {
        return $this->rememberNonEmpty('kur-rm', $period, fn (): array => $this->kurRm($period));
    }

    /** @return array<int, array<string, int|float|string>> */
    public function cachedMantri(string $period): array
    {
        return $this->aggregateMantriAccounts($this->cachedMantriAccounts($period));
    }

    /** @return array<int, array<string, int|float|string>> */
    public function cachedMantriAccounts(string $period): array
    {
        return $this->rememberNonEmpty('mantri-accounts', $period, fn (): array => $this->mantriAccounts($period));
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    public function kurRm(string $period): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $ownerColumn = Schema::hasColumn(self::SOURCE_TABLE, 'pn_pengelola1') ? 'pn_pengelola1' : 'pn_pemrakarsa1';
        $rows = $this->kurRitelQuery($period)
            ->whereBetween('tgl_realisasi', [$periodStart, $period])
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), self::AREA_6_BRANCHES)
            ->get([
                'periode', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                'branch_normalized', 'branch1', $ownerColumn, 'nomor_rekening1',
                'cifno', 'plafon', 'tgl_realisasi',
            ]);
        $rows = $this->deduplicateCurrentAccounts($rows, $ownerColumn, true);

        if ($rows->isEmpty()) {
            return [];
        }

        $metrics = [];

        foreach ($rows as $row) {
            $cabang = $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null);
            $unit = $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null);
            $branchCode = $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null);
            $rm = strtoupper(trim((string) ($row->{$ownerColumn} ?? '')));
            if ($rm === '') {
                $rm = self::UNASSIGNED_RM;
            }

            $key = implode('|', [$cabang, $unit, $branchCode, $rm]);
            $metrics[$key] ??= $this->blankKurMetric($period, $cabang, $unit, $branchCode, $rm);

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $plafond = (float) ($row->plafon ?? 0);
            // Kanwil RM KUR COUNTIFS/SUMIFS uses the current SinglePN manager
            // and booking plafond. A prior CIF balance is not deducted here.
            $nett = $plafond;
            $date = Carbon::parse((string) $row->tgl_realisasi);
            $week = min(4, intdiv(max(0, $date->day - 1), 7) + 1);

            if ($plafond > 0) {
                $metrics[$key]['accounts'][$account !== '' ? $account : $cif] = true;
                $metrics[$key]["w{$week}_accounts"][$account !== '' ? $account : $cif] = true;
            }
            $metrics[$key]['realisasi_os'] += $nett;
            $metrics[$key]["w{$week}_realisasi_os"] += $nett;

            $tier = $plafond < 250000000 ? 'lt_250' : ($plafond > 250000000 ? 'gt_250' : null);
            if ($tier !== null) {
                if ($plafond > 0) {
                    $metrics[$key]["{$tier}_accounts"][$account !== '' ? $account : $cif] = true;
                }
                $metrics[$key]["{$tier}_realisasi_os"] += $nett;
            }
        }

        return array_values(array_map(function (array $metric): array {
            $metric['realisasi_deb'] = count($metric['accounts']);
            unset($metric['accounts']);

            foreach (['w1', 'w2', 'w3', 'w4', 'lt_250', 'gt_250'] as $prefix) {
                $metric["{$prefix}_realisasi_deb"] = count($metric["{$prefix}_accounts"]);
                unset($metric["{$prefix}_accounts"]);
            }

            return $metric;
        }, $metrics));
    }

    /**
     * Nett Mantri non-Briguna memakai ledger booking PN pemrakarsa.
     * Pengurang adalah OS akhir bulan sebelumnya untuk rekening CIF yang
     * sudah tidak ada pada posisi berjalan. Nett minimum nol diterapkan pada
     * setiap rekening sebelum agregasi PN, kategori, maupun periode.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function mantri(string $period): array
    {
        return $this->aggregateMantriAccounts($this->mantriAccounts($period));
    }

    /**
     * Canonical Mantri bookings shared by landing and KPI, before roster scope.
     *
     * @return array<int, array<string, int|float|string>>
     */
    public function mantriAccounts(string $period): array
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return [];
        }

        $periodDate = Carbon::parse($period);
        $periodStart = $periodDate->copy()->startOfMonth()->toDateString();
        $inputStore = app(MantriSourceInputStore::class);
        $inputs = [];
        foreach ($inputStore->inputPeriods($periodStart, $period) as $inputPeriod) {
            if ($inputPeriod >= $periodStart && $inputPeriod <= $period) {
                $input = $inputStore->read($inputPeriod);
                // Historical positions contribute MTD bookings and identity;
                // only the selected position proves current account presence.
                unset($input['unclassified_accounts'], $input['baseline_accounts']);
                if ($inputPeriod !== $period) {
                    unset($input['presence']);
                }
                $inputs[$inputPeriod] = $input;
            }
        }
        $hasCurrentSource = DB::table(self::SOURCE_TABLE)->where('periode', $period)->exists();
        if (($inputs[$period]['source']['source_kind'] ?? '') === 'lw321_import'
            && $this->hasNativeMantriSource($period)) {
            // Native Daily Loan replaces that LW position. Earlier flow
            // archives and audited origination evidence remain independent.
            $inputs[$period]['bookings'] = [];
            $inputs[$period]['presence'] = [];
            $inputs[$period]['complete_presence'] = false;
        }
        if (! $hasCurrentSource && empty($inputs[$period]['complete_presence'])) {
            return [];
        }
        $ownerColumn = 'pn_pemrakarsa1';
        $ownerColumns = array_values(array_filter(['pn_pemrakarsa1', 'pn_pengelola1'], fn (string $column): bool => Schema::hasColumn(self::SOURCE_TABLE, $column)));
        $rows = $this->mantriSourceRows($periodStart, $period, $ownerColumns);
        $archivedRows = [];
        $approvedOwners = [];
        $rawOwners = [];
        $registeredBookings = [];
        $firstVerifiedPosition = $period;
        foreach ($inputs as $input) {
            if (! empty($input['complete_presence'])) {
                $firstVerifiedPosition = min($firstVerifiedPosition, $input['period']);
            }
            foreach ($input['bookings'] ?? [] as $row) {
                $archivedRows[] = (object) $row;
                $registeredBookings[$this->mantriBookingIdentity($row['nomor_rekening1'], $row['cifno'], $row['tgl_realisasi'])] = true;
                if (($input['source']['source_kind'] ?? '') === 'lw321_import'
                    && ($row['raw_singlepn_available'] ?? false) === true) {
                    $key = $row['periode'].'|'.$this->mantriBookingIdentity($row['nomor_rekening1'], $row['cifno'], $row['tgl_realisasi']);
                    $rawOwners[$key] = $row['pn_pengelola_singlepn'];
                }
            }
            foreach ($input['owner_evidence'] ?? [] as $evidence) {
                $ownerBasis = $evidence['owner_basis'] ?? $input['source']['owner_basis'] ?? '';
                $sourceColumn = $evidence['source_owner_column'] ?? $input['source']['source_owner_column'] ?? '';
                if (($ownerBasis !== 'pn_pemrakarsa1' && $sourceColumn !== 'AM')
                    || ($evidence['as_of'] ?? $input['period']) > $period) {
                    continue;
                }
                $key = $this->mantriBookingIdentity($evidence['account'] ?? '', $evidence['cif'] ?? '', $evidence['realization_date'] ?? '');
                $approvedOwners[$key] = $evidence;
            }
        }
        $rows->each(function (object $row) use ($rawOwners): void {
            $key = $row->periode.'|'.$this->mantriBookingIdentity($row->nomor_rekening1, $row->cifno, $row->tgl_realisasi);
            if (str_starts_with((string) ($row->uniqueid_namareport ?? ''), Lw321DailyLoanSyncService::TARGET_ID_PREFIX)
                && array_key_exists($key, $rawOwners)) {
                // A materialized legacy-manager fallback cannot replace an
                // explicitly blank SinglePN in its matching raw import.
                $row->pn_pengelola1 = $rawOwners[$key];
            }
        });
        // Carry the recorded reference cohort and subsequent observations.
        // Older stock history can enrich identity, but cannot introduce a
        // booking already absent when the verified cohort was initialized.
        $rows = collect($archivedRows)->merge($rows)->filter(function (object $row) use ($periodStart, $period): bool {
            return (string) $row->tgl_realisasi >= $periodStart && (string) $row->tgl_realisasi <= $period;
        });
        $rows->each(function (object $row) use ($ownerColumn): void {
            $row->{$ownerColumn} = $this->mantriSinglePn($row->{$ownerColumn} ?? null);
        });
        $rows = $this->deduplicateMantriBookings($rows)
            ->filter(fn (object $row): bool => (string) $row->periode >= $firstVerifiedPosition
                || isset($registeredBookings[$this->mantriBookingIdentity($row->nomor_rekening1, $row->cifno, $row->tgl_realisasi)]))
            ->reject(fn (object $row): bool => $this->excludedMantriBooking($row))
            ->sortBy(fn (object $row): string => (string) $row->tgl_realisasi.'|'.$this->canonicalAccount($row->nomor_rekening1 ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        [$previousOs, $supplementedCifs] = $this->previousClosedMantriOs($periodDate, $rows->pluck('cifno')->all(), $inputs[$period] ?? null, $inputStore);
        $bookings = [];

        foreach ($rows as $row) {
            $cabang = $this->normalizedValue($row->cabang_normalized ?? null, $row->cabang1 ?? null);
            $unit = $this->normalizedValue($row->unit_normalized ?? null, $row->unit1 ?? null);
            $branchCode = $this->normalizedValue($row->branch_normalized ?? null, $row->branch1 ?? null);
            $owner = strtoupper(trim((string) ($row->{$ownerColumn} ?? '')));
            if ($owner === '') {
                $owner = self::UNASSIGNED_RM;
            }

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            $identity = $approvedOwners[$this->mantriBookingIdentity($account, $cif, (string) $row->tgl_realisasi)] ?? null;
            $approvedOwner = $identity === null ? '' : $this->mantriSinglePn(str_pad((string) ($identity['pn'] ?? ''), 8, '0', STR_PAD_LEFT).' - '.($identity['name'] ?? ''));
            if ($approvedOwner !== '') {
                // Audited origination attribution is independent of a later
                // portfolio transfer. HR placement still comes from live BRIHC.
                $owner = $approvedOwner;
            }
            $plafond = (float) ($row->plafon ?? 0);
            $closedPreviousOs = (float) ($previousOs[$cif] ?? 0);
            $nett = max(0.0, $plafond - $closedPreviousOs);
            $bookings[] = [
                'periode' => $period, 'cabang' => $cabang, 'unit' => $unit,
                'branch_code' => $branchCode, 'owner' => $owner,
                'account_key' => $account !== '' ? $account : ($cif !== '' ? 'CIF:'.$cif : 'ROW:'.count($bookings)),
                'cif_key' => $cif, 'plafon' => $plafond,
                'product' => (string) ($row->produk_kinerja ?? ''),
                'previous_os' => $closedPreviousOs, 'net_amount' => $nett,
                'realization_date' => (string) $row->tgl_realisasi,
                'identity_source' => $approvedOwner === '' ? self::SOURCE_TABLE : 'mantri_origination_ledger',
                'baseline_source' => isset($supplementedCifs[$cif]) ? 'mantri_source_input' : self::SOURCE_TABLE,
            ];
        }

        return $bookings;
    }

    /** Reuse overlapping report periods, invalidated by every loan mutation. */
    private function mantriSourceRows(string $start, string $period, array $ownerColumns): Collection
    {
        $version = ReportCacheVersion::get('pinjaman');
        $prefix = 'micro-nett:mantri-position-v2:'.$version.':';
        $periods = DB::table(self::SOURCE_TABLE)->whereBetween('periode', [$start, $period])
            ->distinct()->orderBy('periode')->pluck('periode')->all();
        $slices = [];
        $missing = [];
        foreach ($periods as $date) {
            $slice = Cache::get($prefix.$date);
            if (is_array($slice)) {
                $slices[$date] = $slice;
            } else {
                $missing[] = $date;
            }
        }
        if ($missing !== []) {
            $build = function () use ($prefix, $missing, $start, $ownerColumns): array {
                $result = [];
                $load = [];
                foreach ($missing as $date) {
                    $slice = Cache::get($prefix.$date);
                    if (is_array($slice)) {
                        $result[$date] = $slice;
                    } else {
                        $load[] = $date;
                    }
                }
                if ($load === []) {
                    return $result;
                }
                $columns = [
                    'periode', 'cabang_normalized', 'cabang1', 'unit_normalized', 'unit1',
                    'branch_normalized', 'branch1', ...$ownerColumns, 'nomor_rekening1',
                    'cifno', 'plafon', 'tgl_realisasi', 'description', 'produk_kinerja',
                ];
                if (Schema::hasColumn(self::SOURCE_TABLE, 'uniqueid_namareport')) {
                    $columns[] = 'uniqueid_namareport';
                }
                foreach ($load as $date) {
                    $result[$date] = [];
                }
                $rows = $this->sourceQueryForPeriodRange()->whereIn('periode', $load)
                    ->whereBetween('tgl_realisasi', [$start, Carbon::parse($start)->endOfMonth()->toDateString()])
                    ->whereIn(DB::raw("UPPER(COALESCE(NULLIF(TRIM(cabang1), ''), TRIM(cabang_normalized)))"), self::AREA_6_BRANCHES)->get($columns);
                foreach ($rows as $row) {
                    $result[(string) $row->periode][] = (array) $row;
                }
                foreach ($load as $date) {
                    Cache::put($prefix.$date, $result[$date], now()->addSeconds(self::READ_CACHE_SECONDS));
                }

                return $result;
            };
            try {
                $slices += Cache::lock($prefix.$start.':build', 300)->block(120, $build);
            } catch (LockTimeoutException) {
                $slices += $build();
            }
        }
        ksort($slices, SORT_STRING);
        $rows = collect();
        foreach ($slices as $slice) {
            foreach ($slice as $row) {
                if ((string) $row['tgl_realisasi'] <= $period) {
                    $rows->push((object) $row);
                }
            }
        }

        return $rows;
    }

    private function excludedMantriBooking(object $row): bool
    {
        $product = $this->normalizeToken((string) ($row->produk_kinerja ?? ''));
        $description = $this->normalizeToken((string) ($row->description ?? ''));

        return in_array($product, ['BRIGUNAMIKRO', 'BRIGUNAKONSUMER', 'KURKECIL', 'KURRITEL'], true)
            || $description === $this->normalizeToken(self::KUR_RITEL_DESCRIPTION)
            || $description === 'KREDITMIKROGBT' || str_contains($description, 'BRIGUNA');
    }

    private function hasNativeMantriSource(string $period): bool
    {
        return Schema::hasColumn(self::SOURCE_TABLE, 'uniqueid_namareport')
            && DB::table(self::SOURCE_TABLE)->where('periode', $period)
                ->where(function (Builder $query): void {
                    $query->whereNull('uniqueid_namareport')
                        ->orWhere('uniqueid_namareport', 'not like', Lw321DailyLoanSyncService::TARGET_ID_PREFIX.'%');
                })->exists();
    }

    private function mantriSinglePn(mixed $value): string
    {
        $value = trim((string) $value);
        $prefix = trim(substr($value, 0, 8));
        if (! ctype_digit($prefix) || (int) $prefix === 0) {
            return '';
        }
        $name = str_contains($value, '-') ? trim(explode('-', $value, 2)[1]) : '';

        return str_pad($prefix, 8, '0', STR_PAD_LEFT).($name !== '' ? ' - '.strtoupper($name) : '');
    }

    private function mantriBookingIdentity(mixed $account, mixed $cif, mixed $date): string
    {
        return $this->canonicalAccount($account).'|'.strtoupper(trim((string) $cif)).'|'.substr((string) $date, 0, 10);
    }

    private function deduplicateMantriBookings(Collection $rows): Collection
    {
        $accounts = [];
        $ordered = $rows->values()->sortBy(static fn (object $row, int $index): string => (string) $row->periode.'|'.str_pad((string) $index, 12, '0', STR_PAD_LEFT));
        foreach ($ordered as $index => $row) {
            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $key = $account !== '' ? $account : 'ROW:'.$index;
            $old = $accounts[$key] ?? null;
            $next = clone $row;
            if ($old !== null && $this->mantriBookingIdentity($old->nomor_rekening1, $old->cifno, $old->tgl_realisasi)
                === $this->mantriBookingIdentity($row->nomor_rekening1, $row->cifno, $row->tgl_realisasi)) {
                if ((string) $old->periode === (string) $row->periode) {
                    $next->plafon = max((float) $old->plafon, (float) $row->plafon);
                }
                if (trim((string) ($next->pn_pengelola1 ?? '')) === '') {
                    $next->pn_pengelola1 = $old->pn_pengelola1 ?? '';
                }
                if (trim((string) ($next->pn_pemrakarsa1 ?? '')) === '') {
                    // Preserve a previously observed initiator only for the
                    // exact same booking; a manager is never an initiator.
                    $next->pn_pemrakarsa1 = $old->pn_pemrakarsa1 ?? '';
                }
            }
            $accounts[$key] = $next;
        }

        return collect(array_values($accounts));
    }

    /** @return array{array<string,float>,array<string,bool>} */
    private function previousClosedMantriOs(Carbon $periodDate, array $cifs, ?array $currentInput, MantriSourceInputStore $inputStore): array
    {
        $period = $periodDate->toDateString();
        $baselinePeriod = $periodDate->copy()->startOfMonth()->subDay()->toDateString();
        $requested = array_fill_keys(array_filter(array_map(static fn ($cif) => strtoupper(trim((string) $cif)), $cifs)), true);
        $present = array_fill_keys($currentInput['presence'] ?? [], true);
        foreach (DB::table(self::SOURCE_TABLE)->where('periode', $period)->select('nomor_rekening1')->cursor() as $row) {
            $present[$this->canonicalAccount($row->nomor_rekening1)] = true;
        }

        $prior = DB::table(self::SOURCE_TABLE)->where('periode', $baselinePeriod)
            ->whereIn(DB::raw('UPPER(TRIM(cifno))'), array_keys($requested))
            ->get(['nomor_rekening1', 'cifno', 'baki_debet1', 'description', 'produk_kinerja', 'segmen_kinerja', 'unit1', 'unit_normalized', 'cabang1', 'cabang_normalized']);
        $known = [];
        $closed = [];
        foreach ($prior as $row) {
            $account = $this->canonicalAccount($row->nomor_rekening1);
            if ($account === '') {
                continue;
            }
            $known[$account] = true;
            $segment = strtoupper(trim((string) $row->segmen_kinerja));
            $eligibleSegment = $segment === 'MICRO'
                || ($segment === '' && trim((string) $row->produk_kinerja) === ''
                    && str_starts_with($this->normalizedValue($row->unit1, $row->unit_normalized), 'UNIT'));
            if (! $eligibleSegment || ! in_array($this->normalizedValue($row->cabang1, $row->cabang_normalized), self::AREA_6_BRANCHES, true)
                || $this->normalizeToken((string) $row->description) === $this->normalizeToken(self::KUR_RITEL_DESCRIPTION)
                || in_array($this->normalizeToken((string) $row->produk_kinerja), ['KURKECIL', 'KURRITEL'], true) || isset($present[$account])) {
                continue;
            }
            $cif = strtoupper(trim((string) $row->cifno));
            $key = $cif.'|'.$account;
            $closed[$key] = isset($closed[$key]) ? max($closed[$key], (float) $row->baki_debet1) : (float) $row->baki_debet1;
        }
        $supplemented = [];
        $baselineInput = $inputStore->read($baselinePeriod);
        $archiveSupplement = ($baselineInput['source']['source_kind'] ?? '') !== 'lw321_import'
            || ! $this->hasNativeMantriSource($baselinePeriod);
        foreach ($inputStore->baselineAccounts($archiveSupplement ? $baselineInput : null) as $row) {
            if (in_array($baselineInput['source']['source_kind'] ?? '', ['lw321_import', 'daily_loan_import'], true)
                && strtoupper(trim((string) ($row['segmen_kinerja'] ?? ''))) !== 'MICRO'
                && ! str_starts_with($this->normalizedValue($row['unit1'] ?? null, $row['unit_normalized'] ?? null), 'UNIT')) {
                // An unknown KC product is not evidence of a MICRO balance.
                continue;
            }
            $account = $this->canonicalAccount($row['nomor_rekening1']);
            $cif = strtoupper(trim((string) ($row['cifno'] ?? '')));
            if (isset($known[$account]) || isset($present[$account]) || ! isset($requested[$cif])) {
                continue;
            }
            $closed[$cif.'|'.$account] = (float) $row['baki_debet1'];
            $supplemented[$cif] = true;
        }
        $totals = [];
        foreach ($closed as $key => $amount) {
            $cif = strstr($key, '|', true);
            $totals[$cif] = ($totals[$cif] ?? 0.0) + $amount;
        }

        return [$totals, $supplemented];
    }

    /** @param array<int, array<string, int|float|string>> $bookings */
    private function aggregateMantriAccounts(array $bookings): array
    {
        $metrics = [];
        foreach ($bookings as $booking) {
            $key = implode('|', [$booking['cabang'], $booking['unit'], $booking['branch_code'], $booking['owner']]);
            $metrics[$key] ??= array_intersect_key($booking, array_flip(['periode', 'cabang', 'unit', 'branch_code', 'owner']))
                + ['accounts' => [], 'realisasi_os' => 0.0];
            $metrics[$key]['accounts'][$booking['account_key']] = true;
            $metrics[$key]['realisasi_os'] += $booking['net_amount'];
        }

        return array_values(array_map(function (array $metric): array {
            $metric['realisasi_deb'] = count($metric['accounts']);
            unset($metric['accounts']);

            return $metric;
        }, $metrics));
    }

    private function kurRitelQuery(string $period): Builder
    {
        return $this->kurRitelBaseQuery()
            ->where('periode', $period);
    }

    private function kurRitelBaseQuery(): Builder
    {
        return $this->sourceQueryForPeriodRange()
            ->where('segmen_kinerja', 'MICRO')
            ->whereIn('produk_kinerja', ['KURMIKRO', 'KURKECIL'])
            ->whereRaw($this->normalizedDescriptionSql('description').' = ?', [$this->normalizeToken(self::KUR_RITEL_DESCRIPTION)]);
    }

    /**
     * @param  array<int, mixed>  $cifs
     * @return array<string, float>
     */
    private function previousKurRitelOsByCif(Carbon $periodDate, array $cifs): array
    {
        $previousPeriod = DB::table(self::SOURCE_TABLE)
            ->where('periode', '<=', $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->where('segmen_kinerja', 'MICRO')
            ->whereNotIn('produk_kinerja', ['BRIGUNAMIKRO', 'BRIGUNAKONSUMER'])
            ->orderByDesc('periode')->value('periode');
        $cifs = array_values(array_unique(array_filter(array_map(
            static fn ($cif): string => strtoupper(trim((string) $cif)),
            $cifs
        ))));

        if ($previousPeriod === null || $cifs === []) {
            return [];
        }

        $query = $this->kurRitelQuery((string) $previousPeriod)
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), self::AREA_6_BRANCHES)
            ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifs);

        return $this->accountOsByCif($query);
    }

    /** @param array<int, mixed> $cifs */
    private function previousCompleteOsByCif(Carbon $periodDate, array $cifs, ?string &$previousPeriod = null): array
    {
        $previousPeriod = DB::table(self::SOURCE_TABLE)
            ->where('periode', '<=', $periodDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString())
            ->where('segmen_kinerja', 'MICRO')
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), self::AREA_6_BRANCHES)
            ->orderByDesc('periode')->value('periode');
        $cifs = array_values(array_unique(array_filter(array_map(
            static fn ($cif): string => strtoupper(trim((string) $cif)),
            $cifs
        ))));

        if ($previousPeriod === null || $cifs === []) {
            return [];
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->where('periode', $previousPeriod)
            ->where('segmen_kinerja', 'MICRO')
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), self::AREA_6_BRANCHES)
            ->whereIn(DB::raw('UPPER(TRIM(cifno))'), $cifs);

        $previousRows = $query->get(['cifno', 'nomor_rekening1', 'baki_debet1']);

        return $this->accountRowsOsByCif($previousRows);
    }

    /** @return array<string, float> */
    private function accountOsByCif(Builder $query): array
    {
        return $this->accountRowsOsByCif($query->get(['cifno', 'nomor_rekening1', 'baki_debet1']));
    }

    /** @param Collection<int, object> $rows @return array<string, float> */
    private function accountRowsOsByCif(Collection $rows): array
    {
        $accounts = [];
        $missingAccountSequence = 0;

        foreach ($rows as $row) {
            $cif = strtoupper(trim((string) ($row->cifno ?? '')));
            if ($cif === '') {
                continue;
            }

            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            // A missing account number cannot establish that two rows are duplicates.
            $key = $account !== '' ? $cif.'|'.$account : $cif.'|ROW:'.(++$missingAccountSequence);
            $amount = (float) ($row->baki_debet1 ?? 0);
            $accounts[$key] = isset($accounts[$key]) ? max($accounts[$key], $amount) : $amount;
        }

        $totals = [];
        foreach ($accounts as $key => $amount) {
            $cif = strstr($key, '|', true);
            $totals[$cif] = ($totals[$cif] ?? 0.0) + $amount;
        }

        return $totals;
    }

    /** @param Collection<int, object> $rows @return Collection<int, object> */
    private function deduplicateCurrentAccounts(Collection $rows, string $ownerColumn = 'pn_pemrakarsa1', bool $latestOwner = false): Collection
    {
        $accounts = [];
        $orderedRows = $rows->values()->sortBy(static fn (object $row, int $index): string => implode('|', [
            (string) ($row->periode ?? ''),
            (string) ($row->tgl_realisasi ?? ''),
            str_pad((string) $index, 12, '0', STR_PAD_LEFT),
        ]));

        foreach ($orderedRows as $index => $row) {
            $account = $this->canonicalAccount($row->nomor_rekening1 ?? null);
            $key = $account !== '' ? 'ACCOUNT:'.$account : 'ROW:'.$index;

            if (! isset($accounts[$key])) {
                $accounts[$key] = clone $row;

                continue;
            }

            $current = $accounts[$key];
            if ((float) ($row->plafon ?? 0) > (float) ($current->plafon ?? 0)) {
                $current->plafon = $row->plafon;
            }
            if (($latestOwner || trim((string) ($current->{$ownerColumn} ?? '')) === '') && trim((string) ($row->{$ownerColumn} ?? '')) !== '') {
                $current->{$ownerColumn} = $row->{$ownerColumn};
                if ($latestOwner) {
                    // Mantri is attributed to the latest observed manager and
                    // that position's territory; KUR keeps booking ownership.
                    foreach (['cabang_normalized', 'cabang1', 'unit_normalized', 'unit1', 'branch_normalized', 'branch1'] as $column) {
                        $current->{$column} = $row->{$column} ?? null;
                    }
                }
            }
        }

        return collect(array_values($accounts));
    }

    private function canonicalAccount(mixed $account): string
    {
        $account = strtoupper(trim((string) $account));
        if ($account === '') {
            return '';
        }

        $canonical = ltrim($account, '0');

        return $canonical !== '' ? $canonical : '0';
    }

    private function blankKurMetric(string $period, string $cabang, string $unit, string $branchCode, string $rm): array
    {
        $metric = [
            'periode' => $period,
            'cabang' => $cabang,
            'unit' => $unit,
            'branch_code' => $branchCode,
            'rm' => $rm,
            'accounts' => [],
            'realisasi_os' => 0.0,
        ];

        foreach (['w1', 'w2', 'w3', 'w4', 'lt_250', 'gt_250'] as $prefix) {
            $metric["{$prefix}_accounts"] = [];
            $metric["{$prefix}_realisasi_os"] = 0.0;
        }

        return $metric;
    }

    private function normalizedValue(mixed $normalized, mixed $fallback): string
    {
        $value = trim((string) $normalized);

        return strtoupper($value !== '' ? $value : trim((string) $fallback));
    }

    /** @param callable(): array $compute */
    private function rememberNonEmpty(string $kind, string $period, callable $compute): array
    {
        $keyParts = [
            'micro-nett', str_starts_with($kind, 'mantri') ? self::MANTRI_READ_CACHE_VERSION : self::READ_CACHE_VERSION, $kind,
            ReportCacheVersion::get('pinjaman'), Carbon::parse($period)->toDateString(),
        ];
        if (str_starts_with($kind, 'mantri')) {
            $keyParts[] = app(MantriSourceInputStore::class)->fingerprint($period);
        }
        $key = implode(':', $keyParts);
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $build = function () use ($key, $compute): array {
            $cached = Cache::get($key);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }

            $result = $compute();
            // An empty month may still be loading; never pin "no data".
            if ($result !== []) {
                Cache::put($key, $result, now()->addSeconds(self::READ_CACHE_SECONDS));
            }

            return $result;
        };

        // Concurrent report tabs wait for one scan instead of each running it.
        try {
            return Cache::lock($key.':build', 300)->block(120, $build);
        } catch (LockTimeoutException) {
            return $build();
        }
    }

    private function sourceQueryForPeriodRange(): Builder
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::query()->fromRaw(self::SOURCE_TABLE.' FORCE INDEX (idx_snapshot_filter_optimized)');
        }

        return DB::table(self::SOURCE_TABLE);
    }

    private function normalizedDescriptionSql(string $column): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE({$column}, '')), ' ', ''), '-', ''), '_', ''), CHAR(160), ''))";
    }

    private function normalizeToken(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-_\x{00A0}]+/u', '', trim($value)));
    }
}
