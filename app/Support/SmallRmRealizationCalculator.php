<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SmallRmRealizationCalculator
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const SOURCE_PRODUCTS = [
        'SMALL',
        'COMMERCIAL',
        'CASHCALL',
        'CASHCOLLATERAL',
        'CASHCOLL',
    ];

    /**
     * Calculate SMALL realization from monthly position reports.
     *
     * A current-month booking is credited once at its stored plafond. An older
     * account whose plafond rises is credited only for the positive increase
     * against the latest available preceding month-end position. This captures
     * same-account supplements without reducing unrelated new facilities for
     * an existing CIF.
     *
     * @param  array<int, string>  $targetPeriods
     * @param  array<int, string>  $productValues
     * @param  array<int, string>  $initiatorValues
     * @return array{
     *     covered_periods:array<string, bool>,
     *     rows:array<int, array<string, mixed>>,
     *     diagnostics:array<string, int|float>
     * }
     */
    public function calculate(
        array $targetPeriods,
        array $productValues = [],
        ?string $selectedCabang = null,
        ?string $selectedRmCategory = null,
        array $initiatorValues = []
    ): array {
        $emptyResult = $this->emptyResult();
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return $emptyResult;
        }

        $columns = array_fill_keys(Schema::getColumnListing(self::SOURCE_TABLE), true);
        $dateColumn = isset($columns['tgl_realisasi1'])
            ? 'tgl_realisasi1'
            : (isset($columns['tgl_realisasi']) ? 'tgl_realisasi' : null);
        $cifColumn = isset($columns['cifno_clean'])
            ? 'cifno_clean'
            : (isset($columns['cifno']) ? 'cifno' : null);
        $requiredColumns = [
            'periode',
            'segmen_kinerja',
            'produk_kinerja',
            'plafon',
            'nomor_rekening1',
            'pn_pemrakarsa1',
        ];

        if ($dateColumn === null
            || $cifColumn === null
            || collect($requiredColumns)->contains(static fn (string $column): bool => ! isset($columns[$column]))
        ) {
            return $emptyResult;
        }

        $periods = collect($targetPeriods)
            ->map(fn ($period): ?string => $this->dateValue($period))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
        if ($periods === []) {
            return $emptyResult;
        }

        $products = collect($productValues === [] ? self::SOURCE_PRODUCTS : $productValues)
            ->map(static fn ($product): string => strtoupper(trim((string) $product)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Coverage is deliberately independent of the initiator filter. A
        // period containing SMALL source data is authoritative even when every
        // realization in a selected scope is zero or lacks an initiator.
        $coveredPeriods = DB::table(self::SOURCE_TABLE)
            ->whereIn('periode', $periods)
            ->where('segmen_kinerja', 'SMALL')
            ->select('periode')
            ->distinct()
            ->pluck('periode')
            ->mapWithKeys(fn ($period): array => [(string) $period => true])
            ->all();

        if ($coveredPeriods === []) {
            return $emptyResult;
        }

        $candidateQuery = DB::table(self::SOURCE_TABLE)
            ->where('segmen_kinerja', 'SMALL')
            ->whereIn('produk_kinerja', $products)
            ->whereIn('periode', $periods);

        $this->applyAssignmentFilters(
            $candidateQuery,
            $columns,
            $selectedCabang,
            $selectedRmCategory,
            $initiatorValues
        );

        $selects = [
            'periode',
            $dateColumn.' as realization_date',
            'pn_pemrakarsa1 as initiator',
            'nomor_rekening1 as account_number',
            'plafon',
            $cifColumn.' as cif_key',
            $this->selectAlias($columns, ['cabang_normalized', 'cabang1'], 'cabang'),
            $this->selectAlias($columns, ['unit_normalized', 'unit1'], 'unit'),
            $this->selectAlias($columns, ['branch_normalized'], 'branch_code'),
        ];
        if (isset($columns['id'])) {
            $selects[] = 'id';
        }

        $candidateRows = $candidateQuery
            ->select($selects)
            ->orderBy('periode')
            ->orderBy($dateColumn)
            ->when(isset($columns['id']), static fn (Builder $query): Builder => $query->orderBy('id'))
            ->get();

        $deduplicated = [];
        $excludedBlankInitiator = 0;
        foreach ($candidateRows as $row) {
            $period = $this->dateValue($row->periode ?? null);
            $realizationDate = $this->dateValue($row->realization_date ?? null);
            $account = $this->normalizeKey($row->account_number ?? null);
            if ($period === null || $account === '' || ($realizationDate !== null && $realizationDate > $period)) {
                continue;
            }

            $initiator = $this->normalizeLabel($row->initiator ?? null);
            if ($initiator === '') {
                $excludedBlankInitiator++;

                continue;
            }

            $cif = $this->normalizeKey($row->cif_key ?? null);
            if ($cif === '') {
                $cif = 'ACCOUNT:'.$account;
            }

            // A facility is credited once even when duplicate source rows have
            // different or missing CIF labels for the same account.
            $dedupeKey = implode('|', [$period, $account]);
            $currentPlafon = max(0.0, (float) ($row->plafon ?? 0));
            if (isset($deduplicated[$dedupeKey])) {
                if ($currentPlafon > $deduplicated[$dedupeKey]['plafon']) {
                    $deduplicated[$dedupeKey]['plafon'] = $currentPlafon;
                }

                continue;
            }
            $deduplicated[$dedupeKey] = [
                'period' => $period,
                'realization_date' => $realizationDate,
                'cif' => $cif,
                'account' => $account,
                'initiator' => $initiator,
                'rm_identity' => $this->rmIdentity($initiator),
                'cabang' => $this->normalizeLabel($row->cabang ?? null),
                'unit' => $this->normalizeLabel($row->unit ?? null),
                'branch_code' => $this->normalizeLabel($row->branch_code ?? null),
                'plafon' => $currentPlafon,
            ];
        }

        if ($deduplicated === []) {
            $emptyResult['covered_periods'] = $coveredPeriods;
            $emptyResult['diagnostics']['excluded_blank_initiator'] = $excludedBlankInitiator;

            return $emptyResult;
        }

        $previousPeriods = $this->previousPeriodsByTarget($periods);
        $previousPlafondByPeriodAccount = [];
        $previousPeriodValues = array_values(array_unique(array_values($previousPeriods)));
        $candidateAccounts = collect($deduplicated)
            ->pluck('account')
            ->unique()
            ->values()
            ->all();
        if ($previousPeriodValues !== [] && $candidateAccounts !== []) {
            $previousRows = DB::table(self::SOURCE_TABLE)
                ->whereIn('periode', $previousPeriodValues)
                ->where('segmen_kinerja', 'SMALL')
                ->whereIn('produk_kinerja', $products)
                ->whereIn('nomor_rekening1', $candidateAccounts)
                ->get(['periode', 'nomor_rekening1 as account_number', 'plafon']);

            foreach ($previousRows as $row) {
                $period = $this->dateValue($row->periode ?? null);
                $account = $this->normalizeKey($row->account_number ?? null);
                if ($period === null || $account === '') {
                    continue;
                }

                $key = $period.'|'.$account;
                $previousPlafondByPeriodAccount[$key] = max(
                    (float) ($previousPlafondByPeriodAccount[$key] ?? 0.0),
                    max(0.0, (float) ($row->plafon ?? 0.0))
                );
            }
        }

        $events = [];
        $aggregatedRows = [];
        $creditedTotal = 0.0;
        $creditedAccounts = 0;
        $bookingAccounts = 0;
        $bookingAmount = 0.0;
        $increaseAccounts = 0;
        $increaseAmount = 0.0;
        foreach ($deduplicated as $candidate) {
            $period = (string) $candidate['period'];
            $realizationDate = $candidate['realization_date'];
            $isCurrentMonthBooking = $realizationDate !== null
                && $realizationDate >= Carbon::parse($period)->startOfMonth()->toDateString()
                && $realizationDate <= $period;
            $previousPeriod = $previousPeriods[$period] ?? null;
            $previousAccountKey = $previousPeriod !== null
                ? $previousPeriod.'|'.$candidate['account']
                : null;
            $hasPreviousAccount = $previousAccountKey !== null
                && array_key_exists($previousAccountKey, $previousPlafondByPeriodAccount);
            $previousPlafond = $hasPreviousAccount
                ? (float) $previousPlafondByPeriodAccount[$previousAccountKey]
                : 0.0;
            $amount = $isCurrentMonthBooking
                ? (float) $candidate['plafon']
                : ($hasPreviousAccount
                    ? max(0.0, (float) $candidate['plafon'] - $previousPlafond)
                    : 0.0);
            if ($amount <= 0.0001) {
                continue;
            }

            $events[implode('|', [
                $period,
                $isCurrentMonthBooking ? (string) $realizationDate : 'PLAFOND_INCREASE',
                $candidate['account'],
            ])] = true;
            $participantKey = implode('|', [
                $period,
                $this->normalizeKey($candidate['cabang']),
                $this->normalizeKey($candidate['unit']),
                $this->normalizeBranchCode($candidate['branch_code']),
                $candidate['rm_identity'],
            ]);
            $aggregatedRows[$participantKey] ??= [
                'period' => $period,
                'cabang' => $candidate['cabang'],
                'unit' => $candidate['unit'],
                'branch_code' => $candidate['branch_code'],
                'rm' => $candidate['initiator'],
                'rm_identity' => $candidate['rm_identity'],
                'deb' => 0,
                'rp' => 0.0,
            ];
            $aggregatedRows[$participantKey]['deb']++;
            $aggregatedRows[$participantKey]['rp'] += $amount;
            $creditedAccounts++;
            $creditedTotal += $amount;
            if ($isCurrentMonthBooking) {
                $bookingAccounts++;
                $bookingAmount += $amount;
            } else {
                $increaseAccounts++;
                $increaseAmount += $amount;
            }
        }

        return [
            'covered_periods' => $coveredPeriods,
            'rows' => array_values($aggregatedRows),
            'diagnostics' => [
                'candidate_accounts' => $creditedAccounts,
                'events' => count($events),
                'excluded_blank_initiator' => $excludedBlankInitiator,
                'booking_accounts' => $bookingAccounts,
                'booking_rp' => $bookingAmount,
                'plafond_increase_accounts' => $increaseAccounts,
                'plafond_increase_rp' => $increaseAmount,
                'credited_rp' => $creditedTotal,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $targetPeriods
     * @return array<string, string>
     */
    private function previousPeriodsByTarget(array $targetPeriods): array
    {
        $previousMonths = collect($targetPeriods)
            ->mapWithKeys(function (string $period): array {
                $month = Carbon::parse($period)->startOfMonth()->subMonthNoOverflow();

                return [$period => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]];
            });
        $available = DB::table(self::SOURCE_TABLE)
            ->where('segmen_kinerja', 'SMALL')
            ->where(function (Builder $query) use ($previousMonths): void {
                foreach ($previousMonths as $index => $range) {
                    $method = $index === 0 ? 'whereBetween' : 'orWhereBetween';
                    $query->{$method}('periode', $range);
                }
            })
            ->select('periode')
            ->distinct()
            ->pluck('periode')
            ->map(fn ($period): ?string => $this->dateValue($period))
            ->filter()
            ->values();

        return $previousMonths
            ->map(function (array $range) use ($available): ?string {
                return $available
                    ->filter(static fn (string $period): bool => $period >= $range[0] && $period <= $range[1])
                    ->max();
            })
            ->filter()
            ->all();
    }

    /** @return array{covered_periods:array<string, bool>,rows:array<int, array<string, mixed>>,diagnostics:array<string, int|float>} */
    private function emptyResult(): array
    {
        return [
            'covered_periods' => [],
            'rows' => [],
            'diagnostics' => [
                'candidate_accounts' => 0,
                'events' => 0,
                'excluded_blank_initiator' => 0,
                'booking_accounts' => 0,
                'booking_rp' => 0.0,
                'plafond_increase_accounts' => 0,
                'plafond_increase_rp' => 0.0,
                'credited_rp' => 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $columns
     * @param  array<int, string>  $initiatorValues
     */
    private function applyAssignmentFilters(
        Builder $query,
        array $columns,
        ?string $selectedCabang,
        ?string $selectedRmCategory,
        array $initiatorValues
    ): void {
        if ($selectedCabang !== null && trim($selectedCabang) !== '') {
            $cabangColumn = isset($columns['cabang_normalized'])
                ? 'cabang_normalized'
                : (isset($columns['cabang1']) ? 'cabang1' : null);
            if ($cabangColumn !== null) {
                $query->whereRaw("UPPER(TRIM({$cabangColumn})) = ?", [strtoupper(trim($selectedCabang))]);
            }
        }

        if (in_array($selectedRmCategory, ['KC', 'KCP'], true)) {
            $unitColumn = isset($columns['unit_normalized'])
                ? 'unit_normalized'
                : (isset($columns['unit1']) ? 'unit1' : null);
            if ($unitColumn !== null) {
                $operator = $selectedRmCategory === 'KCP' ? 'LIKE' : 'NOT LIKE';
                $query->whereRaw("UPPER(TRIM({$unitColumn})) {$operator} 'KCP%'");
            }
        }

        $initiators = collect($initiatorValues)
            ->map(static fn ($value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($initiators !== []) {
            $query->whereIn(DB::raw('UPPER(TRIM(pn_pemrakarsa1))'), $initiators);
        }
    }

    /** @param array<string, bool> $columns */
    private function selectAlias(array $columns, array $candidates, string $alias): mixed
    {
        foreach ($candidates as $candidate) {
            if (isset($columns[$candidate])) {
                return $candidate.' as '.$alias;
            }
        }

        return DB::raw('NULL as '.$alias);
    }

    private function rmIdentity(string $value): string
    {
        if (preg_match('/^\s*0*(\d{5,})\s*-/', $value, $matches) === 1) {
            return 'PN:'.ltrim($matches[1], '0');
        }

        $name = trim(explode('-', $value, 2)[1] ?? $value);

        return 'NAME:'.(preg_replace('/[^A-Z0-9]+/', '', strtoupper($name)) ?? '');
    }

    private function normalizeLabel(mixed $value): string
    {
        return preg_replace('/\s+/', ' ', trim((string) $value)) ?? trim((string) $value);
    }

    private function normalizeKey(mixed $value): string
    {
        return strtoupper($this->normalizeLabel($value));
    }

    private function normalizeBranchCode(mixed $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/\d+/', $value, $matches) === 1) {
            return ltrim($matches[0], '0') ?: '0';
        }

        return strtoupper($value);
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
