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
        $coverageRows = $this->sourceQueryForPeriodRange()
            ->whereIn('periode', $periods)
            ->whereIn('produk_kinerja', $products)
            ->distinct()
            ->get([
                'periode',
                'segmen_kinerja',
                'produk_kinerja',
                $this->selectAlias($columns, ['description'], 'description'),
            ]);
        $coveredPeriods = [];
        foreach ($coverageRows as $row) {
            $classification = DailyLoanManualSegmentRule::classify(
                $row->description ?? null,
                $row->segmen_kinerja ?? null,
                $row->produk_kinerja ?? null
            );
            if ($classification['segment'] === 'SMALL'
                && in_array($classification['product'], $products, true)) {
                $coveredPeriods[(string) $row->periode] = true;
            }
        }

        if ($coveredPeriods === []) {
            return $emptyResult;
        }

        $candidateQuery = $this->sourceQueryForPeriodRange()
            ->whereIn('produk_kinerja', $products)
            ->where(function (Builder $query) use ($periods): void {
                foreach ($periods as $index => $period) {
                    $range = [Carbon::parse($period)->startOfMonth()->toDateString(), $period];
                    $method = $index === 0 ? 'whereBetween' : 'orWhereBetween';
                    $query->{$method}('periode', $range);
                }
            });

        // Resolve the indexed period/segment catalog before loading account
        // rows. Exact predicates can use all leading columns of the existing
        // snapshot index; broad monthly ranges cannot.
        $catalog = (clone $candidateQuery)->distinct()->get(['periode', 'segmen_kinerja', 'produk_kinerja']);
        $observationPeriods = $catalog->pluck('periode')->unique()->values()->all();
        $segments = $catalog->pluck('segmen_kinerja')->unique()->values();
        $candidateQuery = $this->sourceQueryForPeriodRange()
            ->whereIn('periode', $observationPeriods)
            ->whereIn('produk_kinerja', $products)
            ->where(function (Builder $query) use ($segments): void {
                $query->whereIn('segmen_kinerja', $segments->filter(fn ($value) => $value !== null)->all());
                if ($segments->contains(null)) {
                    $query->orWhereNull('segmen_kinerja');
                }
            });

        // Select the relevant accounts first, then retain their complete
        // monthly observations across assignment changes. Filtering the
        // observations themselves can invent a different event owner.
        $assignmentQuery = clone $candidateQuery;
        $this->applyAssignmentFilters(
            $assignmentQuery,
            $columns,
            $selectedCabang,
            $selectedRmCategory,
            $initiatorValues
        );
        $accountAliases = null;
        if ($selectedCabang !== null || $selectedRmCategory !== null || $initiatorValues !== []) {
            $accountAliases = [];
            foreach ($assignmentQuery->distinct()->pluck('nomor_rekening1') as $rawAccount) {
                $account = $this->canonicalAccount($rawAccount);
                if ($account !== '') {
                    foreach ($this->accountLookupCandidates($account, [(string) $rawAccount]) as $alias) {
                        $accountAliases[$alias] = true;
                    }
                }
            }
        }

        $selects = [
            'periode',
            $dateColumn.' as realization_date',
            'pn_pemrakarsa1 as initiator',
            'nomor_rekening1 as account_number',
            'plafon',
            'segmen_kinerja',
            'produk_kinerja',
            $cifColumn.' as cif_key',
            $this->selectAlias($columns, ['description'], 'description'),
            $this->selectAlias($columns, ['cabang_normalized', 'cabang1'], 'cabang'),
            $this->selectAlias($columns, ['unit_normalized', 'unit1'], 'unit'),
            $this->selectAlias($columns, ['branch_normalized'], 'branch_code'),
        ];
        if (isset($columns['id'])) {
            $selects[] = 'id';
        }

        if ($accountAliases !== null) {
            // Resolve dates once, then use the existing (periode, rekening)
            // index in bounded batches without dropping assignment history.
            $candidateRows = collect();
            foreach (array_chunk(array_map('strval', array_keys($accountAliases)), 200) as $aliases) {
                $candidateRows = $candidateRows->concat(DB::table(self::SOURCE_TABLE)
                    ->whereIn('periode', $observationPeriods)
                    ->whereIn('produk_kinerja', $products)
                    ->whereIn('nomor_rekening1', $aliases)
                    ->get($selects));
            }
        } else {
            $candidateRows = $candidateQuery->get($selects);
        }
        $candidateRows = $candidateRows
            ->sortBy(static fn (object $row): string => implode('|', [
                (string) ($row->periode ?? ''),
                (string) ($row->realization_date ?? ''),
                str_pad((string) ($row->id ?? ''), 20, '0', STR_PAD_LEFT),
            ]));

        // An official description seen at any position up to the cutoff owns
        // the account classification for the monthly event. This prevents an
        // earlier stale SMALL shadow from surviving when a later source row
        // identifies the same account as MEDIUM (for example RITKOM).
        $officialSegmentsByAccount = [];
        foreach ($candidateRows as $row) {
            $account = $this->canonicalAccount($row->account_number ?? null);
            if ($account === '') {
                continue;
            }
            $classification = DailyLoanManualSegmentRule::classify(
                $row->description ?? null,
                $row->segmen_kinerja ?? null,
                $row->produk_kinerja ?? null
            );
            if ($classification['matched']) {
                $officialSegmentsByAccount[$account][$classification['segment']] = true;
            }
        }

        $deduplicated = [];
        $excludedBlankInitiator = 0;
        foreach ($candidateRows as $row) {
            $sourcePeriod = $this->dateValue($row->periode ?? null);
            $realizationDate = $this->dateValue($row->realization_date ?? null);
            $rawAccount = $this->normalizeKey($row->account_number ?? null);
            $account = $this->canonicalAccount($rawAccount);
            if ($sourcePeriod === null
                || $account === ''
                || ($realizationDate !== null && $realizationDate > $sourcePeriod)) {
                continue;
            }

            $classification = DailyLoanManualSegmentRule::classify(
                $row->description ?? null,
                $row->segmen_kinerja ?? null,
                $row->produk_kinerja ?? null
            );
            $officialSegments = array_keys($officialSegmentsByAccount[$account] ?? []);
            $effectiveSegment = count($officialSegments) === 1
                ? $officialSegments[0]
                : (count($officialSegments) > 1 ? '' : $classification['segment']);
            if ($effectiveSegment !== 'SMALL'
                || ! in_array($classification['product'], $products, true)) {
                continue;
            }

            $initiator = $this->normalizeLabel($row->initiator ?? null);
            $cif = $this->normalizeKey($row->cif_key ?? null);
            if ($cif === '') {
                $cif = 'ACCOUNT:'.$account;
            }

            foreach ($periods as $targetPeriod) {
                $monthStart = Carbon::parse($targetPeriod)->startOfMonth()->toDateString();
                if ($sourcePeriod < $monthStart || $sourcePeriod > $targetPeriod) {
                    continue;
                }

                $dedupeKey = implode('|', [$targetPeriod, $account]);
                $currentPlafon = max(0.0, (float) ($row->plafon ?? 0));
                if (! isset($deduplicated[$dedupeKey])) {
                    $deduplicated[$dedupeKey] = [
                        'period' => $targetPeriod,
                        'realization_date' => $realizationDate,
                        'cif' => $cif,
                        'account' => $account,
                        'account_aliases' => [$rawAccount => true],
                        'initiator' => $initiator,
                        'rm_identity' => $initiator !== '' ? $this->rmIdentity($initiator) : '',
                        'cabang' => $this->normalizeLabel($row->cabang ?? null),
                        'unit' => $this->normalizeLabel($row->unit ?? null),
                        'branch_code' => $this->normalizeLabel($row->branch_code ?? null),
                        'plafon' => $currentPlafon,
                        'plafon_observations' => $initiator !== '' ? [[
                            'plafon' => $currentPlafon,
                            'initiator' => $initiator,
                            'cabang' => $this->normalizeLabel($row->cabang ?? null),
                            'unit' => $this->normalizeLabel($row->unit ?? null),
                            'branch_code' => $this->normalizeLabel($row->branch_code ?? null),
                        ]] : [],
                    ];

                    continue;
                }

                $deduplicated[$dedupeKey]['account_aliases'][$rawAccount] = true;
                $observations = $deduplicated[$dedupeKey]['plafon_observations'];
                $lastObservedPlafon = $observations !== [] ? $observations[array_key_last($observations)]['plafon'] : -1.0;
                if ($initiator !== '' && $currentPlafon > $lastObservedPlafon) {
                    $deduplicated[$dedupeKey]['plafon_observations'][] = [
                        'plafon' => $currentPlafon,
                        'initiator' => $initiator,
                        'cabang' => $this->normalizeLabel($row->cabang ?? null),
                        'unit' => $this->normalizeLabel($row->unit ?? null),
                        'branch_code' => $this->normalizeLabel($row->branch_code ?? null),
                    ];
                }
                $deduplicated[$dedupeKey]['plafon'] = max(
                    (float) $deduplicated[$dedupeKey]['plafon'],
                    $currentPlafon
                );
                if ($deduplicated[$dedupeKey]['initiator'] === '' && $initiator !== '') {
                    $deduplicated[$dedupeKey]['initiator'] = $initiator;
                    $deduplicated[$dedupeKey]['rm_identity'] = $this->rmIdentity($initiator);
                    $deduplicated[$dedupeKey]['cabang'] = $this->normalizeLabel($row->cabang ?? null);
                    $deduplicated[$dedupeKey]['unit'] = $this->normalizeLabel($row->unit ?? null);
                    $deduplicated[$dedupeKey]['branch_code'] = $this->normalizeLabel($row->branch_code ?? null);
                }
                if ($deduplicated[$dedupeKey]['realization_date'] === null && $realizationDate !== null) {
                    $deduplicated[$dedupeKey]['realization_date'] = $realizationDate;
                }
            }
        }

        foreach ($deduplicated as $key => $candidate) {
            if ($candidate['initiator'] !== '') {
                continue;
            }

            $excludedBlankInitiator++;
            unset($deduplicated[$key]);
        }

        if ($deduplicated === []) {
            $emptyResult['covered_periods'] = $coveredPeriods;
            $emptyResult['diagnostics']['excluded_blank_initiator'] = $excludedBlankInitiator;

            return $emptyResult;
        }

        $previousPeriods = $this->previousPeriodsByTarget($periods, $products, $columns);
        $previousPlafondByPeriodAccount = [];
        $previousPeriodValues = array_values(array_unique(array_values($previousPeriods)));
        $candidateAccounts = [];
        foreach ($deduplicated as $candidate) {
            foreach ($this->accountLookupCandidates($candidate['account'], array_keys($candidate['account_aliases'])) as $alias) {
                $candidateAccounts[$alias] = true;
            }
        }
        $candidateAccounts = array_map('strval', array_keys($candidateAccounts));
        if ($previousPeriodValues !== [] && $candidateAccounts !== []) {
            // Keep account predicates below MariaDB's large-IN conversion
            // threshold so (periode, nomor_rekening1) remains a range lookup.
            $previousRows = (function () use ($previousPeriodValues, $products, $candidateAccounts, $columns): \Generator {
                foreach (array_chunk($candidateAccounts, 200) as $accounts) {
                    yield from DB::table(self::SOURCE_TABLE)
                        ->whereIn('periode', $previousPeriodValues)
                        ->whereIn('produk_kinerja', $products)
                        ->whereIn('nomor_rekening1', $accounts)
                        ->get([
                            'periode',
                            'nomor_rekening1 as account_number',
                            'plafon',
                            'segmen_kinerja',
                            'produk_kinerja',
                            $this->selectAlias($columns, ['description'], 'description'),
                        ]);
                }
            })();

            foreach ($previousRows as $row) {
                $period = $this->dateValue($row->periode ?? null);
                $account = $this->canonicalAccount($row->account_number ?? null);
                if ($period === null || $account === '') {
                    continue;
                }

                $classification = DailyLoanManualSegmentRule::classify(
                    $row->description ?? null,
                    $row->segmen_kinerja ?? null,
                    $row->produk_kinerja ?? null
                );
                if ($classification['segment'] !== 'SMALL'
                    || ! in_array($classification['product'], $products, true)) {
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

            // An old facility can change initiator when its limit is raised.
            // Attribute that event to the first observed increase, not to the
            // owner of the unchanged opening balance earlier in the month.
            if (! $isCurrentMonthBooking && $hasPreviousAccount) {
                $increaseOwnerFound = false;
                foreach ($candidate['plafon_observations'] as $observation) {
                    if ($observation['plafon'] > $previousPlafond) {
                        $candidate = array_replace($candidate, array_diff_key($observation, ['plafon' => true]));
                        $candidate['rm_identity'] = $this->rmIdentity($candidate['initiator']);
                        $increaseOwnerFound = true;
                        break;
                    }
                }
                if (! $increaseOwnerFound) {
                    $excludedBlankInitiator++;

                    continue;
                }
                $observations = $candidate['plafon_observations'];
                $amount = max(0.0, (float) $observations[array_key_last($observations)]['plafon'] - $previousPlafond);
            }
            if (! $this->matchesEventAssignment($candidate, $columns, $selectedCabang, $selectedRmCategory, $initiatorValues)) {
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
    private function previousPeriodsByTarget(array $targetPeriods, array $products, array $columns): array
    {
        $previousMonths = collect($targetPeriods)
            ->mapWithKeys(function (string $period): array {
                $month = Carbon::parse($period)->startOfMonth()->subMonthNoOverflow();

                return [$period => [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]];
            });
        $resolved = [];
        return $previousMonths->map(function (array $range) use ($products, $columns, &$resolved): ?string {
            if (array_key_exists($range[0], $resolved)) {
                return $resolved[$range[0]];
            }
            $dates = DB::table(self::SOURCE_TABLE)->whereBetween('periode', $range)
                ->distinct()->orderByDesc('periode')->pluck('periode');
            foreach ($dates as $date) {
                $classifications = $this->sourceQueryForPeriodRange()->where('periode', $date)
                    ->whereIn('produk_kinerja', $products)->distinct()->get([
                        'segmen_kinerja', 'produk_kinerja',
                        $this->selectAlias($columns, ['description'], 'description'),
                    ]);
                foreach ($classifications as $row) {
                    $classification = DailyLoanManualSegmentRule::classify($row->description ?? null, $row->segmen_kinerja ?? null, $row->produk_kinerja ?? null);
                    if ($classification['segment'] === 'SMALL' && in_array($classification['product'], $products, true)) {
                        return $resolved[$range[0]] = $this->dateValue($date);
                    }
                }
            }

            return $resolved[$range[0]] = null;
        })->filter()->all();
    }

    private function sourceQueryForPeriodRange(): Builder
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::query()->fromRaw(self::SOURCE_TABLE.' FORCE INDEX (idx_snapshot_filter_optimized)');
        }

        return DB::table(self::SOURCE_TABLE);
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
    private function matchesEventAssignment(array $candidate, array $columns, ?string $cabang, ?string $category, array $initiators): bool
    {
        if ($cabang !== null && trim($cabang) !== ''
            && (isset($columns['cabang_normalized']) || isset($columns['cabang1']))
            && strtoupper(trim($candidate['cabang'])) !== strtoupper(trim($cabang))) {
            return false;
        }
        if (in_array($category, ['KC', 'KCP'], true)
            && (isset($columns['unit_normalized']) || isset($columns['unit1']))
            && str_starts_with(strtoupper(trim($candidate['unit'])), 'KCP') !== ($category === 'KCP')) {
            return false;
        }
        $initiators = array_values(array_filter(array_map(static fn ($value): string => strtoupper(trim((string) $value)), $initiators)));

        return $initiators === [] || in_array(strtoupper(trim($candidate['initiator'])), $initiators, true);
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

    private function canonicalAccount(mixed $value): string
    {
        $account = $this->normalizeKey($value);
        if ($account === '') {
            return '';
        }

        return ltrim($account, '0') ?: '0';
    }

    /**
     * Build exact aliases for an indexed whereIn lookup. No function is
     * applied to nomor_rekening1, so the lookup remains SARGable.
     *
     * @param  array<int, string>  $rawAliases
     * @return array<int, string>
     */
    private function accountLookupCandidates(string $canonical, array $rawAliases): array
    {
        $aliases = array_fill_keys(
            array_filter($rawAliases, static fn (string $value): bool => $value !== ''),
            true
        );
        $aliases[$canonical] = true;
        if (ctype_digit($canonical) && strlen($canonical) < 15) {
            $aliases[str_pad($canonical, 15, '0', STR_PAD_LEFT)] = true;
        }

        return array_map('strval', array_keys($aliases));
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
