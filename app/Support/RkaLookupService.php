<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RkaLookupService
{
    private array $loadedRowsCache = [];

    /**
     * uker_key values that are kanca-level rollup rows (e.g. '45-KC Madiun'
     * normalized to 'KC MADIUN'). When aggregating per-uker definitions we
     * exclude these to prevent double-counting against KCP/UNIT detail rows.
     */
    private const KANCA_SUMMARY_UKER_KEYS = [
        'KC MADIUN',
        'KC MAGETAN',
        'KC NGAWI',
        'KC PONOROGO',
    ];

    private const MONTH_COLUMN_MAP = [
        1 => 'jan',
        2 => 'feb',
        3 => 'mar',
        4 => 'apr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'aug',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dec',
    ];

    private const MONTH_LABEL_MAP = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC',
    ];

    public function resolveMonthColumn(Carbon|string|null $date): string
    {
        $resolvedDate = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return self::MONTH_COLUMN_MAP[(int) $resolvedDate->format('n')] ?? 'jan';
    }

    public function resolveMonthLabel(Carbon|string|null $date): string
    {
        $resolvedDate = $date instanceof Carbon ? $date : Carbon::parse((string) $date);

        return self::MONTH_LABEL_MAP[(int) $resolvedDate->format('n')] ?? 'JAN';
    }

    public function aggregateByGroup(
        array $definitions,
        string $monthColumn,
        array $kancas = [],
        array $units = [],
        string $groupBy = 'kanca',
        ?int $year = null
    ): array {
        $monthColumn = strtolower(trim($monthColumn));
        $normalizedKancas = $this->normalizeScopeValues($kancas);
        $normalizedUnits = $this->normalizeScopeValues($units);
        $rows = $this->loadRows([$monthColumn], $year);

        // When the caller explicitly filtered to one or more uker rows AND those
        // uker rows are kanca-summary rows (e.g. 'KC MADIUN'), the user really
        // wants the kanca rollup numbers, not the children. Detect that case so
        // matchesDefinition does not exclude the summary row by default.
        $unitsTargetsKancaSummary = false;
        if (!empty($normalizedUnits)) {
            foreach ($normalizedUnits as $unitKey) {
                if ($this->isKancaSummaryRow($unitKey)) {
                    $unitsTargetsKancaSummary = true;
                    break;
                }
            }
        }

        $groups = [];
        foreach ($definitions as $definitionKey => $definition) {
            $groups[$definitionKey] = [];
        }

        $rowsConsidered = 0;
        $rowsMatched = 0;

        foreach ($rows as $row) {
            if (!empty($normalizedKancas) && !$this->matchesAnyScopeValue($row['kanca_key'], $normalizedKancas)) {
                continue;
            }

            if (!empty($normalizedUnits) && !$this->matchesAnyScopeValue($row['uker_key'], $normalizedUnits)) {
                continue;
            }

            $rowsConsidered++;

            $groupKey = $groupBy === 'uker' ? $row['uker_key'] : $row['kanca_key'];
            if ($groupKey === '') {
                continue;
            }

            $rowMatchedAny = false;
            foreach ($definitions as $definitionKey => $definition) {
                $effectiveDefinition = $definition;
                if ($unitsTargetsKancaSummary) {
                    $effectiveDefinition['include_kanca_summary'] = true;
                }

                if (!$this->matchesDefinition($row, $effectiveDefinition)) {
                    continue;
                }

                $rowMatchedAny = true;
                $groups[$definitionKey][$groupKey] = round(
                    ($groups[$definitionKey][$groupKey] ?? 0) + (float) ($row['months'][$monthColumn] ?? 0),
                    2
                );
            }
            if ($rowMatchedAny) {
                $rowsMatched++;
            }
        }

        $this->logEmptyAggregateDiagnostic($groups, $rowsConsidered, $rowsMatched, $monthColumn, $normalizedKancas, $normalizedUnits, $groupBy, $year);

        return $groups;
    }

    /**
     * Emit a single warning when an aggregate scope returns all-zero groups
     * but the scope actually had data rows. Helps diagnose RKA-display "0"
     * regressions without flooding logs on legitimately empty scopes.
     *
     * @param array<string, array<string, float>> $groups
     */
    private function logEmptyAggregateDiagnostic(array $groups, int $rowsConsidered, int $rowsMatched, string $monthColumn, array $normalizedKancas, array $normalizedUnits, string $groupBy, ?int $year): void
    {
        if ($rowsConsidered === 0) {
            return; // legitimately no rows for this scope, not a bug
        }

        $hasAnyValue = false;
        foreach ($groups as $definitionGroups) {
            foreach ($definitionGroups as $value) {
                if (abs((float) $value) > 0.0) {
                    $hasAnyValue = true;
                    break 2;
                }
            }
        }

        if ($hasAnyValue) {
            return;
        }

        try {
            Log::warning('RKA aggregate scope returned all-zero groups despite source rows.', [
                'month_column' => $monthColumn,
                'group_by' => $groupBy,
                'year' => $year,
                'kancas' => $normalizedKancas,
                'units' => $normalizedUnits,
                'rows_considered' => $rowsConsidered,
                'rows_matched_any_definition' => $rowsMatched,
                'definition_keys' => array_keys($groups),
            ]);
        } catch (\Throwable) {
            // logging must not break aggregation
        }
    }

    public function aggregateForScope(
        array $definitions,
        string $monthColumn,
        ?string $kanca = null,
        ?string $unit = null,
        ?int $year = null
    ): array {
        $monthColumn = strtolower(trim($monthColumn));
        $rows = $this->loadRows([$monthColumn], $year);

        // If the caller targets a kanca-summary uker explicitly (e.g. unit='KC Madiun'
        // when no granular uker is chosen), surface the kanca rollup row instead of
        // treating it as a double-count candidate.
        $normalizedUnit = $unit !== null ? $this->normalizeScopeValue($unit) : null;
        $normalizedKanca = $kanca !== null ? $this->normalizeScopeValue($kanca) : null;
        $unitTargetsKancaSummary = $normalizedUnit !== null && $this->isKancaSummaryRow($normalizedUnit);
        $kancaTargetsSummaryOnly = $normalizedUnit === null
            && $normalizedKanca !== null
            && $this->isKancaSummaryRow($normalizedKanca);

        $result = [];
        foreach (array_keys($definitions) as $definitionKey) {
            $result[$definitionKey] = 0.0;
        }

        foreach ($rows as $row) {
            if (!$this->matchesScope($row, $kanca, $unit)) {
                continue;
            }

            foreach ($definitions as $definitionKey => $definition) {
                $effectiveDefinition = $definition;
                if ($unitTargetsKancaSummary || $kancaTargetsSummaryOnly) {
                    $effectiveDefinition['include_kanca_summary'] = true;
                }

                if (!$this->matchesDefinition($row, $effectiveDefinition)) {
                    continue;
                }

                $result[$definitionKey] = round(
                    (float) ($result[$definitionKey] ?? 0) + (float) ($row['months'][$monthColumn] ?? 0),
                    2
                );
            }
        }

        return $result;
    }

    /**
     * Aggregate branch-level RKA using the normal kanca rows, then fall back to
     * explicit kanca-summary rows when a branch would otherwise render as zero.
     *
     * This is intentionally branch-level only. Unit-filtered views must keep
     * their unit scope so an actually empty unit does not borrow a branch total.
     *
     * @param array<string, array<string, mixed>> $definitions
     * @param array<int, string> $kancas
     * @return array<string, array<string, float>>
     */
    public function aggregateByKancaWithSummaryFallback(
        array $definitions,
        string $monthColumn,
        array $kancas = [],
        ?int $year = null
    ): array {
        $normalizedKancas = $this->normalizeScopeValues($kancas);

        $directGroups = $this->aggregateByGroup(
            $definitions,
            $monthColumn,
            $normalizedKancas,
            [],
            'kanca',
            $year
        );

        $summaryGroups = $this->aggregateByGroup(
            $definitions,
            $monthColumn,
            $normalizedKancas,
            $normalizedKancas,
            'kanca',
            $year
        );

        $groups = [];
        foreach (array_keys($definitions) as $definitionKey) {
            $groups[$definitionKey] = [];

            foreach ($normalizedKancas as $kancaKey) {
                $value = (float) ($directGroups[$definitionKey][$kancaKey] ?? 0);

                if (abs($value) <= 0.0) {
                    $value = (float) ($summaryGroups[$definitionKey][$kancaKey] ?? 0);
                }

                $groups[$definitionKey][$kancaKey] = round($value, 2);
            }
        }

        return $groups;
    }

    private function loadRows(array $monthColumns, ?int $year = null): Collection
    {
        $normalizedMonthColumns = collect($monthColumns)
            ->map(fn ($column) => strtolower(trim((string) $column)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedMonthColumns)) {
            $normalizedMonthColumns = ['jan'];
        }

        $cacheKey = md5(json_encode([
            'months' => $normalizedMonthColumns,
            'year' => $year,
        ]));

        if (isset($this->loadedRowsCache[$cacheKey])) {
            return $this->loadedRowsCache[$cacheKey];
        }

        return $this->loadedRowsCache[$cacheKey] = DB::table('rka')
            ->select(array_merge(['kanca', 'desc_uker', 'mata_anggaran'], $normalizedMonthColumns))
            ->when($year !== null, function ($query) use ($year) {
                $query->whereYear('created_at', $year);
            })
            ->get()
            ->map(function ($row) use ($normalizedMonthColumns) {
                $months = [];

                foreach ($normalizedMonthColumns as $monthColumn) {
                    $months[$monthColumn] = (float) ($row->{$monthColumn} ?? 0);
                }

                return [
                    'kanca_key' => $this->normalizeScopeValue($row->kanca) ?? '',
                    'uker_key' => $this->normalizeScopeValue($row->desc_uker) ?? '',
                    'mata_anggaran_key' => $this->normalizeLookupValue($row->mata_anggaran) ?? '',
                    'months' => $months,
                ];
            });
    }

    public function availableYears(): array
    {
        $driver = DB::connection()->getDriverName();
        $yearExpression = $driver === 'sqlite' ? "strftime('%Y', created_at) as year" : 'YEAR(created_at) as year';

        return DB::table('rka')
            ->whereNotNull('created_at')
            ->selectRaw($yearExpression)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values()
            ->all();
    }

    private function normalizeScopeValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $this->normalizeScopeValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeScopeValue($value): ?string
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['ALL', 'ALL KANCA', 'ALL UKER'], true)) {
            return null;
        }

        $normalized = ltrim($normalized, "'\" ");
        $normalized = preg_replace('/^\d+\s*[\p{Pd}]+\s*/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\d+\s+/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s*\(([^)]*)\)\s*$/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s*[\p{Pd}]+\s*/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[.]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+DETAIL$/u', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function matchesDefinition(array $row, array $definition): bool
    {
        if (isset($definition['mata_anggaran'])) {
            $mataAnggarans = $this->normalizeLookupValues((array) $definition['mata_anggaran']);
            if (empty($mataAnggarans)) {
                return false; // If filter is provided but empty, match nothing (safety)
            }
            if (!in_array($row['mata_anggaran_key'], $mataAnggarans, true)) {
                return false;
            }
        }

        $ukerContainsAny = $this->normalizeLookupValues((array) ($definition['uker_contains_any'] ?? []));
        if (!empty($ukerContainsAny)) {
            // When a uker_contains_any filter is set, the caller wants detail-uker
            // rows (KC / KCP / UNIT). Kanca-level summary rows (e.g. uker_key
            // 'KC MADIUN') would double-count against KCP/UNIT detail rows that
            // already sum into the same total. Exclude them by default; callers
            // that explicitly want the summary can pass include_kanca_summary=true.
            $includeKancaSummary = (bool) ($definition['include_kanca_summary'] ?? false);
            if (!$includeKancaSummary && $this->isKancaSummaryRow($row['uker_key'])) {
                return false;
            }

            $matchesAny = false;

            foreach ($ukerContainsAny as $needle) {
                if ($needle !== '' && $this->ukerContainsToken($row['uker_key'], $needle)) {
                    $matchesAny = true;
                    break;
                }
            }

            if (!$matchesAny) {
                return false;
            }
        }

        $ukerNotContainsAny = $this->normalizeLookupValues((array) ($definition['uker_not_contains_any'] ?? []));
        foreach ($ukerNotContainsAny as $needle) {
            if ($needle !== '' && $this->ukerContainsToken($row['uker_key'], $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match needle as a whole token in the uker key so 'KC' doesn't match
     * 'KCP DOLOPO' and 'UNIT' doesn't match a hypothetical 'UNIT*' substring.
     * Tokens are alphanumeric runs separated by non-alphanumeric chars.
     */
    private function ukerContainsToken(string $ukerKey, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '' || $ukerKey === '') {
            return false;
        }

        $pattern = '/(^|[^A-Z0-9])' . preg_quote($needle, '/') . '([^A-Z0-9]|$)/u';

        return (bool) preg_match($pattern, $ukerKey);
    }

    /**
     * Return true when uker_key is a kanca-level rollup row whose value already
     * aggregates all child uker rows for that kanca.
     */
    private function isKancaSummaryRow(string $ukerKey): bool
    {
        return in_array($ukerKey, self::KANCA_SUMMARY_UKER_KEYS, true);
    }

    private function normalizeLookupValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => $this->normalizeLookupValue($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeLookupValue($value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Aggregate RKA data by regional filter (matches regional names in kanca)
     * Used for branches like KC Madiun, KC Ngawi, KC Magetan to retrieve RKA by UKER
     */
    public function aggregateByGroupWithRegionalFilter(
        array $definitions,
        string $monthColumn,
        array $regionPatterns = [],  // ['MADIUN', 'NGAWI', 'MAGETAN']
        ?int $year = null,
        array $units = [],
        string $groupBy = 'region'
    ): array {
        // Normalize month column
        $normalizedMonth = strtolower(trim((string) $monthColumn));
        $normalizedUnits = $this->normalizeScopeValues($units);
        $groupBy = strtolower(trim($groupBy)) === 'uker' ? 'uker' : 'region';
        
        // Load all rows for this period
        $loadedRows = $this->loadRows([$normalizedMonth], $year);
        
        $groups = [];
        foreach ($definitions as $definitionKey => $definition) {
            $groups[$definitionKey] = [];
        }

        // Process each row and group by regional pattern match
        foreach ($loadedRows as $row) {
            foreach ($definitions as $definitionKey => $definition) {
                if (!$this->matchesDefinition($row, $definition)) {
                    continue;
                }

                // For each region pattern, check if it matches this row's kanca_key (region)
                foreach ($regionPatterns as $region) {
                    $regionUpper = strtoupper(trim($region));
                    if ($regionUpper !== '' && (str_contains($row['kanca_key'], $regionUpper) || str_contains($row['uker_key'], $regionUpper))) {
                        if (!empty($normalizedUnits) && !$this->matchesAnyScopeValue($row['uker_key'], $normalizedUnits)) {
                            continue;
                        }

                        $groupKey = $groupBy === 'uker' ? $row['uker_key'] : $region;
                        if ($groupKey === '') {
                            continue;
                        }

                        // Found a match - aggregate by this region
                        if (!isset($groups[$definitionKey][$groupKey])) {
                            $groups[$definitionKey][$groupKey] = 0;
                        }
                        // Access month value from nested 'months' array
                        $monthValue = $row['months'][$normalizedMonth] ?? 0;
                        $groups[$definitionKey][$groupKey] += (float) $monthValue;
                    }
                }
            }
        }

        return $groups;
    }

    private function matchesScope(array $row, ?string $kancaLabel, ?string $unitLabel): bool
    {
        if ($kancaLabel === null && $unitLabel === null) {
            return true;
        }

        $kancaMatch = true;
        if ($kancaLabel !== null) {
            $kancaMatch = $this->flexibleMatch($row['kanca_key'], $kancaLabel);
        }

        $unitMatch = true;
        if ($unitLabel !== null) {
            $unitMatch = $this->flexibleMatch($row['uker_key'], $unitLabel);
        }

        return $kancaMatch && $unitMatch;
    }

    /**
     * Match dashboard filter values against imported RKA labels using the same
     * tolerant rules as single-scope lookups. This prevents grouped unit
     * aggregations from collapsing to zero when the dashboard has a slug/truncated
     * key while RKA keeps coded labels such as "3885-UNIT ...".
     *
     * @param array<int, string> $targets
     */
    private function matchesAnyScopeValue(string $sourceKey, array $targets): bool
    {
        $sourceKind = $this->scopeKind($sourceKey);

        foreach ($targets as $target) {
            $targetKind = $this->scopeKind($target);
            if ($sourceKind !== null && $targetKind !== null && $sourceKind !== $targetKind) {
                continue;
            }

            if ($sourceKey === $target || $this->flexibleMatch($sourceKey, $target)) {
                return true;
            }
        }

        return false;
    }

    private function scopeKind(string $value): ?string
    {
        $normalized = $this->normalizeScopeValue($value);
        if ($normalized === null) {
            return null;
        }

        foreach (['KCP', 'UNIT', 'KC'] as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . ' ')) {
                return $prefix;
            }
        }

        return null;
    }

    private function flexibleMatch(string $ukerKey, string $label): bool
    {
        $normalizedLabel = $this->normalizeScopeValue($label);
        if ($normalizedLabel === null) {
            return true;
        }

        // Exact match first
        if ($ukerKey === $normalizedLabel) {
            return true;
        }

        // Try slug-based matching for better flexibility (matches 'kc-madiun' to '45-KC MADIUN')
        $ukerSlug = \Illuminate\Support\Str::slug($ukerKey);
        $labelSlug = \Illuminate\Support\Str::slug($label);

        if ($labelSlug !== '' && (str_contains($ukerSlug, $labelSlug) || str_contains($labelSlug, $ukerSlug))) {
            return true;
        }

        // Last resort: keyword matching
        $keywords = array_filter(explode('-', $labelSlug), fn($p) => !in_array($p, ['kc', 'kcp', 'unit']));
        if (!empty($keywords)) {
            foreach ($keywords as $word) {
                if (!str_contains($ukerSlug, $word)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }
}
