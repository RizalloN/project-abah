<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
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
     * A new CIF is credited at the gross booked plafond. When the CIF already
     * existed at the preceding month-end, realization is the positive increase
     * between the complete current and preceding CIF plafond. Comparing the
     * complete CIF keeps supplements correct when the facility retains its
     * account number or is replaced by a newly opened account.
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
            ->where(function (Builder $query) use ($periods, $dateColumn): void {
                foreach ($periods as $index => $period) {
                    $start = Carbon::parse($period)->startOfMonth()->toDateString();
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $periodQuery) use ($period, $start, $dateColumn): void {
                        $periodQuery
                            ->where('periode', $period)
                            ->whereBetween($dateColumn, [$start, $period]);
                    });
                }
            });

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
            if ($period === null || $realizationDate === null || $account === '') {
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

            $dedupeKey = implode('|', [$period, $cif, $account]);
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
        $exposurePeriods = collect($periods)
            ->merge(array_values($previousPeriods))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $candidateCifs = collect($deduplicated)
            ->pluck('cif')
            ->reject(static fn (string $cif): bool => str_starts_with($cif, 'ACCOUNT:'))
            ->unique()
            ->values()
            ->all();
        $fallbackAccounts = collect($deduplicated)
            ->filter(static fn (array $candidate): bool => str_starts_with($candidate['cif'], 'ACCOUNT:'))
            ->pluck('account')
            ->unique()
            ->values()
            ->all();
        $plafondByPeriodCif = [];

        if ($exposurePeriods !== [] && ($candidateCifs !== [] || $fallbackAccounts !== [])) {
            $exposureRows = DB::table(self::SOURCE_TABLE)
                ->whereIn('periode', $exposurePeriods)
                ->where('segmen_kinerja', 'SMALL')
                ->whereIn('produk_kinerja', $products)
                ->where(function (Builder $query) use ($cifColumn, $candidateCifs, $fallbackAccounts): void {
                    if ($candidateCifs !== []) {
                        $query->whereIn($cifColumn, $candidateCifs);
                    }
                    if ($fallbackAccounts !== []) {
                        $method = $candidateCifs === [] ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('nomor_rekening1', $fallbackAccounts);
                    }
                })
                ->get(['periode', $cifColumn.' as cif_key', 'nomor_rekening1 as account_number', 'plafon']);

            $exposureAccounts = [];
            foreach ($exposureRows as $row) {
                $period = $this->dateValue($row->periode ?? null);
                $account = $this->normalizeKey($row->account_number ?? null);
                if ($period === null || $account === '') {
                    continue;
                }
                $cif = $this->normalizeKey($row->cif_key ?? null);
                if ($cif === '') {
                    $cif = 'ACCOUNT:'.$account;
                }
                $accountKey = implode('|', [$period, $cif, $account]);
                $exposureAccounts[$accountKey] = max(
                    (float) ($exposureAccounts[$accountKey] ?? 0.0),
                    max(0.0, (float) ($row->plafon ?? 0.0))
                );
            }

            foreach ($exposureAccounts as $accountKey => $plafond) {
                [$period, $cif] = explode('|', $accountKey, 3);
                $periodCifKey = $period.'|'.$cif;
                $plafondByPeriodCif[$periodCifKey] =
                    (float) ($plafondByPeriodCif[$periodCifKey] ?? 0.0) + $plafond;
            }
        }

        $candidatesByPeriodCif = collect($deduplicated)->groupBy(
            static fn (array $candidate): string => $candidate['period'].'|'.$candidate['cif']
        );
        $creditedAmounts = [];
        $supplementAccounts = 0;
        $supplementAmount = 0.0;
        $newAccounts = 0;
        $newAmount = 0.0;

        foreach ($candidatesByPeriodCif as $periodCifKey => $candidates) {
            /** @var Collection<int, array<string, mixed>> $candidates */
            $first = $candidates->first();
            $period = (string) $first['period'];
            $cif = (string) $first['cif'];
            $previousPeriod = $previousPeriods[$period] ?? null;
            $previousPlafond = $previousPeriod !== null
                ? (float) ($plafondByPeriodCif[$previousPeriod.'|'.$cif] ?? 0.0)
                : 0.0;
            $isSupplement = $previousPeriod !== null && $previousPlafond > 0.0;
            $grossBooked = (float) $candidates->sum('plafon');
            $currentPlafond = (float) ($plafondByPeriodCif[$periodCifKey] ?? $grossBooked);
            $realizationAmount = $isSupplement
                ? max(0.0, $currentPlafond - $previousPlafond)
                : $grossBooked;

            foreach ($candidates as $candidate) {
                $dedupeKey = implode('|', [$candidate['period'], $candidate['cif'], $candidate['account']]);
                $share = $grossBooked > 0.0 ? (float) $candidate['plafon'] / $grossBooked : 0.0;
                $creditedAmounts[$dedupeKey] = $realizationAmount * $share;
            }

            if ($isSupplement) {
                $supplementAccounts += $realizationAmount > 0.0 ? $candidates->count() : 0;
                $supplementAmount += $realizationAmount;
            } else {
                $newAccounts += $realizationAmount > 0.0 ? $candidates->count() : 0;
                $newAmount += $realizationAmount;
            }
        }

        $events = [];
        $aggregatedRows = [];
        $creditedTotal = 0.0;
        foreach ($deduplicated as $candidate) {
            $events[implode('|', [
                $candidate['period'],
                $candidate['realization_date'],
                $candidate['cif'],
            ])] = true;
            $participantKey = implode('|', [
                $candidate['period'],
                $this->normalizeKey($candidate['cabang']),
                $this->normalizeKey($candidate['unit']),
                $this->normalizeBranchCode($candidate['branch_code']),
                $candidate['rm_identity'],
            ]);
            $aggregatedRows[$participantKey] ??= [
                'period' => $candidate['period'],
                'cabang' => $candidate['cabang'],
                'unit' => $candidate['unit'],
                'branch_code' => $candidate['branch_code'],
                'rm' => $candidate['initiator'],
                'rm_identity' => $candidate['rm_identity'],
                'deb' => 0,
                'rp' => 0.0,
            ];
            $dedupeKey = implode('|', [$candidate['period'], $candidate['cif'], $candidate['account']]);
            $amount = (float) ($creditedAmounts[$dedupeKey] ?? 0.0);
            if ($amount > 0.0001) {
                $aggregatedRows[$participantKey]['deb']++;
            }
            $aggregatedRows[$participantKey]['rp'] += $amount;
            $creditedTotal += $amount;
        }

        return [
            'covered_periods' => $coveredPeriods,
            'rows' => array_values($aggregatedRows),
            'diagnostics' => [
                'candidate_accounts' => count($deduplicated),
                'events' => count($events),
                'excluded_blank_initiator' => $excludedBlankInitiator,
                'new_accounts' => $newAccounts,
                'new_rp' => $newAmount,
                'supplement_accounts' => $supplementAccounts,
                'supplement_rp' => $supplementAmount,
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
                'new_accounts' => 0,
                'new_rp' => 0.0,
                'supplement_accounts' => 0,
                'supplement_rp' => 0.0,
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
