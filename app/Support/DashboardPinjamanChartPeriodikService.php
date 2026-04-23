<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DashboardPinjamanChartPeriodikService
{
    private const SNAPSHOT_TABLE = 'dashboard_pinjaman_chart_periodik_snapshots';
    private const RAW_TABLE = 'daily_loan_dinamis';
    private const LOOKUP_TABLE = 'loan_type';

    private const DEFAULT_BRANCHES = [
        'KC MADIUN',
        'KC MAGETAN',
        'KC NGAWI',
        'KC PONOROGO',
    ];

    private const DEFAULT_BRANCH_LABEL = 'Area 6 - All';
    private const DEFAULT_TREND_WINDOW = 6;

    public function buildIndexPayload(?string $requestedPeriod, ?string $selectedBranch = null, array|string|null $selectedUnits = null): array
    {
        $sourceTable = $this->resolveSourceTable();
        $selectedPeriod = $this->resolveEffectivePeriod($requestedPeriod, $sourceTable);
        $normalizedBranch = $this->normalizeBranchSelection($selectedBranch);
        $unitPayload = $this->buildUnitOptions($selectedPeriod, $normalizedBranch, $sourceTable);
        $normalizedUnits = $this->normalizeUnitSelections($selectedUnits);
        $selectedUnitsFiltered = $this->intersectSelectedUnits($normalizedUnits, $unitPayload['unit_options']);

        return array_merge([
            'periods' => $this->fetchPeriods($sourceTable)->all(),
            'selected_period' => $selectedPeriod,
            'selected_period_label' => $this->formatPeriodLabel($selectedPeriod),
        ], $this->buildBranchPayload($normalizedBranch), $unitPayload, [
            'selected_units' => $selectedUnitsFiltered,
            'selected_unit_label' => $this->summarizeUnitSelection($selectedUnitsFiltered, $unitPayload['unit_options']),
            'chart' => $this->buildChartPayload($selectedPeriod, $normalizedBranch, $selectedUnitsFiltered, $sourceTable),
        ]);
    }

    public function buildFilterPayload(?string $requestedPeriod, ?string $selectedBranch = null): array
    {
        $sourceTable = $this->resolveSourceTable();
        $selectedPeriod = $this->resolveEffectivePeriod($requestedPeriod, $sourceTable);
        $normalizedBranch = $this->normalizeBranchSelection($selectedBranch);

        return array_merge([
            'selected_period' => $selectedPeriod,
            'selected_period_label' => $this->formatPeriodLabel($selectedPeriod),
        ], $this->buildBranchPayload($normalizedBranch), $this->buildUnitOptions($selectedPeriod, $normalizedBranch, $sourceTable));
    }

    public function buildChartPayload(?string $selectedPeriod, ?string $selectedBranch = null, array|string|null $selectedUnits = null, ?string $sourceTable = null): array
    {
        $sourceTable ??= $this->resolveSourceTable();
        $resolvedPeriod = $this->resolveEffectivePeriod($selectedPeriod, $sourceTable);
        if ($resolvedPeriod === null) {
            return $this->emptyChartPayload($selectedBranch, $selectedUnits);
        }

        $normalizedBranch = $this->normalizeBranchSelection($selectedBranch);
        $branchScope = $normalizedBranch === 'all'
            ? self::DEFAULT_BRANCHES
            : [$normalizedBranch];

        $normalizedUnits = $this->normalizeUnitSelections($selectedUnits);
        $unitPayload = $this->buildUnitOptions($resolvedPeriod, $normalizedBranch, $sourceTable);
        $trendPeriods = $this->resolveTrendPeriods($resolvedPeriod, $sourceTable);

        if ($trendPeriods === []) {
            return $this->emptyChartPayload($normalizedBranch, $normalizedUnits);
        }

        $trendRows = $this->aggregatePatternCounts($trendPeriods, $branchScope, $normalizedUnits, $sourceTable);
        $currentRows = $this->aggregatePatternCounts([$resolvedPeriod], $branchScope, $normalizedUnits, $sourceTable);

        $trendMatrix = [];
        $trendTotals = [];
        foreach ($trendRows as $row) {
            $period = (string) ($row->periode ?? '');
            $pattern = $this->normalizePatternLabel($row->pola_pembayaran ?? null);
            $count = (int) ($row->total_count ?? 0);

            if ($period === '') {
                continue;
            }

            $trendMatrix[$pattern][$period] = $count;
            $trendTotals[$pattern] = ($trendTotals[$pattern] ?? 0) + $count;
        }

        arsort($trendTotals);
        $orderedPatterns = array_keys($trendTotals);

        $trendSeries = [];
        foreach ($orderedPatterns as $pattern) {
            $trendSeries[] = [
                'label' => $pattern,
                'data' => array_map(
                    fn (string $period) => (int) ($trendMatrix[$pattern][$period] ?? 0),
                    $trendPeriods
                ),
            ];
        }

        $pieCounts = [];
        foreach ($currentRows as $row) {
            $pattern = $this->normalizePatternLabel($row->pola_pembayaran ?? null);
            $pieCounts[$pattern] = (int) (($pieCounts[$pattern] ?? 0) + (int) ($row->total_count ?? 0));
        }

        arsort($pieCounts);
        $pieLabels = array_keys($pieCounts);
        $pieValues = array_values($pieCounts);
        $topPattern = $pieLabels[0] ?? null;

        $selectedUnitCount = count($normalizedUnits);

        return [
            'selected_period' => $resolvedPeriod,
            'selected_period_label' => $this->formatPeriodLabel($resolvedPeriod),
            'selected_branch' => $normalizedBranch,
            'selected_branch_label' => $this->branchLabel($normalizedBranch),
            'selected_units' => $normalizedUnits,
            'selected_unit_label' => $this->summarizeUnitSelection($normalizedUnits, $unitPayload['unit_options']),
            'scope_label' => $this->buildScopeLabel($normalizedBranch, $selectedUnitCount),
            'trend' => [
                'labels' => array_map(fn (string $period) => $this->formatPeriodLabel($period), $trendPeriods),
                'periods' => $trendPeriods,
                'datasets' => $trendSeries,
            ],
            'pie' => [
                'labels' => $pieLabels,
                'values' => $pieValues,
            ],
            'summary' => [
                'total_rekening' => array_sum($pieValues),
                'pattern_count' => count($pieLabels),
                'top_pattern' => $topPattern,
                'top_pattern_count' => $pieCounts[$topPattern] ?? 0,
                'branch_count' => $normalizedBranch === 'all' ? count(self::DEFAULT_BRANCHES) : 1,
                'unit_count' => $selectedUnitCount,
            ],
        ];
    }

    public function resolveEffectivePeriod(?string $requestedPeriod, ?string $sourceTable = null): ?string
    {
        $sourceTable ??= $this->resolveSourceTable();
        $normalizedRequested = $this->normalizePeriod($requestedPeriod);

        if ($normalizedRequested !== null) {
            $resolved = $this->resolveClosestPeriodFromSource($sourceTable, 'periode', $normalizedRequested);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $latestKey = 'dashboard_pinjaman_chart_periodik_v3_latest:v' . $this->reportCacheVersion() . ':' . $sourceTable;

        return Cache::remember($latestKey, now()->addMinutes(10), function () use ($sourceTable) {
            return $this->resolveLatestPeriodFromSource($sourceTable ?? self::RAW_TABLE, 'periode')
                ?? null;
        });
    }

    public function fetchPeriods(?string $sourceTable = null): Collection
    {
        $sourceTable ??= $this->resolveSourceTable();
        $cacheKey = 'dashboard_pinjaman_chart_periodik_v3_periods:v' . $this->reportCacheVersion() . ':' . $sourceTable;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($sourceTable) {
            $periods = $this->fetchPeriodsFromSource($sourceTable ?? self::RAW_TABLE, 'periode');

            if ($periods->isEmpty()) {
                Log::warning('DashboardPinjamanChartPeriodikService: No periods found in chart periodik source.');
            }

            return $periods;
        });
    }

    private function buildBranchPayload(string $selectedBranch): array
    {
        $branchOptions = array_merge(
            [[
                'value' => 'all',
                'label' => $this->branchLabel('all'),
            ]],
            array_map(
                fn (string $branch) => [
                    'value' => $branch,
                    'label' => $branch,
                ],
                self::DEFAULT_BRANCHES
            )
        );

        return [
            'branch_options' => $branchOptions,
            'selected_branch' => $selectedBranch,
            'selected_branch_label' => $this->branchLabel($selectedBranch),
        ];
    }

    private function buildUnitOptions(?string $selectedPeriod, string $selectedBranch, ?string $sourceTable = null): array
    {
        $sourceTable ??= $this->resolveSourceTable();

        if ($selectedPeriod === null) {
            return [
                'unit_options' => [],
                'selected_units' => [],
                'selected_unit_label' => 'Semua Kode Uker',
            ];
        }

        $branchScope = $selectedBranch === 'all'
            ? self::DEFAULT_BRANCHES
            : [$selectedBranch];

        $cacheKey = 'dashboard_pinjaman_chart_periodik_v3_units:v' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'source' => $sourceTable,
            'period' => $selectedPeriod,
            'branch' => $selectedBranch,
        ]));

        $unitOptions = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($selectedPeriod, $branchScope, $selectedBranch, $sourceTable) {
            return $this->fetchUnitOptionsFromSource($sourceTable ?? self::RAW_TABLE, 'periode', $selectedPeriod, $branchScope, $selectedBranch);
        });

        return [
            'unit_options' => $unitOptions,
            'selected_units' => [],
            'selected_unit_label' => 'Semua Kode Uker',
        ];
    }

    private function aggregatePatternCounts(array $periods, array $branches, array $selectedUnits, ?string $sourceTable = null): Collection
    {
        $sourceTable ??= $this->resolveSourceTable();

        if ($periods === [] || $branches === []) {
            return collect();
        }

        if ($sourceTable === self::SNAPSHOT_TABLE) {
            $baseQuery = DB::table($sourceTable . ' as d')
                ->whereIn('d.periode', $periods)
                ->whereIn('d.cabang1', $branches)
                ->selectRaw('d.periode as periode')
                ->selectRaw("COALESCE(NULLIF(d.pola_pembayaran, ''), 'TIDAK TERPETAKAN') as pola_pembayaran");

            $this->applyUnitFilter($baseQuery, $selectedUnits);

            return DB::query()
                ->fromSub($baseQuery, 'loan_pattern_scope')
                ->selectRaw('periode')
                ->selectRaw('pola_pembayaran')
                ->selectRaw('COUNT(*) as total_count')
                ->groupBy('periode', 'pola_pembayaran')
                ->orderBy('periode')
                ->orderBy('pola_pembayaran')
                ->get();
        }

        $baseQuery = DB::table(self::RAW_TABLE . ' as d')
            ->leftJoin(self::LOOKUP_TABLE . ' as lt', function ($join) {
                $join->on(DB::raw('UPPER(TRIM(d.ln_type))'), '=', DB::raw('UPPER(TRIM(lt.loan_type))'));
            })
            ->whereIn('d.periode', $periods)
            ->whereIn(DB::raw('UPPER(TRIM(d.cabang1))'), $branches)
            ->selectRaw('d.periode as periode')
            ->selectRaw("COALESCE(NULLIF(UPPER(TRIM(lt.pola_pembayaran)), ''), 'TIDAK TERPETAKAN') as pola_pembayaran");

        $this->applyUnitFilter($baseQuery, $selectedUnits);

        return DB::query()
            ->fromSub($baseQuery, 'loan_pattern_scope')
            ->selectRaw('periode')
            ->selectRaw('pola_pembayaran')
            ->selectRaw('COUNT(*) as total_count')
            ->groupBy('periode', 'pola_pembayaran')
            ->orderBy('periode')
            ->orderBy('pola_pembayaran')
            ->get();
    }

    private function applyUnitFilter($query, array $selectedUnits): void
    {
        if ($selectedUnits === []) {
            return;
        }

        $sourceTable = $this->resolveSourceTable();
        $isSnapshot = ($sourceTable === self::SNAPSHOT_TABLE);

        $query->where(function ($group) use ($selectedUnits, $isSnapshot) {
            foreach ($selectedUnits as $unitSelection) {
                $branch = (string) ($unitSelection['branch'] ?? '');
                $unit = (string) ($unitSelection['unit'] ?? '');

                if ($branch === '' || $unit === '') {
                    continue;
                }

                $group->orWhere(function ($unitQuery) use ($branch, $unit, $isSnapshot) {
                    if ($isSnapshot) {
                        $unitQuery
                            ->where('d.cabang1', '=', $branch)
                            ->where('d.unit1', '=', $unit);
                    } else {
                        $unitQuery
                            ->whereRaw('UPPER(TRIM(d.cabang1)) = ?', [$branch])
                            ->where(function ($unitMatch) use ($unit) {
                                $unitMatch
                                    ->whereRaw('UPPER(TRIM(d.branch1)) = ?', [$unit])
                                    ->orWhereRaw('UPPER(TRIM(d.unit1)) = ?', [$unit]);
                            });
                    }
                });
            }
        });
    }

    private function resolveTrendPeriods(string $selectedPeriod, ?string $sourceTable = null): array
    {
        return $this->fetchPeriods($sourceTable)
            ->filter(fn (string $period) => $period <= $selectedPeriod)
            ->take(self::DEFAULT_TREND_WINDOW)
            ->reverse()
            ->values()
            ->all();
    }

    private function emptyChartPayload(?string $selectedBranch = null, array|string|null $selectedUnits = null): array
    {
        $normalizedBranch = $this->normalizeBranchSelection($selectedBranch);
        $normalizedUnits = $this->normalizeUnitSelections($selectedUnits);

        return [
            'selected_period' => null,
            'selected_period_label' => '-',
            'selected_branch' => $normalizedBranch,
            'selected_branch_label' => $this->branchLabel($normalizedBranch),
            'selected_units' => $normalizedUnits,
            'selected_unit_label' => $this->summarizeUnitSelection($normalizedUnits),
            'scope_label' => $this->buildScopeLabel($normalizedBranch, count($normalizedUnits)),
            'trend' => [
                'labels' => [],
                'periods' => [],
                'datasets' => [],
            ],
            'pie' => [
                'labels' => [],
                'values' => [],
            ],
            'summary' => [
                'total_rekening' => 0,
                'pattern_count' => 0,
                'top_pattern' => null,
                'top_pattern_count' => 0,
                'branch_count' => $normalizedBranch === 'all' ? count(self::DEFAULT_BRANCHES) : 1,
                'unit_count' => count($normalizedUnits),
            ],
        ];
    }

    private function normalizeBranchSelection(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '' || $normalized === 'ALL') {
            return 'all';
        }

        return in_array($normalized, self::DEFAULT_BRANCHES, true) ? $normalized : 'all';
    }

    private function normalizeUnitSelections(array|string|null $value): array
    {
        $items = $value instanceof Collection
            ? $value->all()
            : (is_array($value) ? $value : [$value]);

        return collect($items)
            ->map(function ($item) {
                if (is_array($item) && isset($item['branch'], $item['unit'])) {
                    $branch = strtoupper(trim((string) $item['branch']));
                    $unit = strtoupper(trim((string) $item['unit']));

                    if ($branch === '' || $unit === '') {
                        return null;
                    }

                    return [
                        'branch' => $branch,
                        'unit' => $unit,
                        'value' => $this->makeUnitKey($branch, $unit),
                    ];
                }

                $normalized = strtoupper(trim((string) $item));

                if ($normalized === '' || !str_contains($normalized, '||')) {
                    return null;
                }

                [$branch, $unit] = array_pad(explode('||', $normalized, 2), 2, '');
                $branch = strtoupper(trim($branch));
                $unit = strtoupper(trim($unit));

                if ($branch === '' || $unit === '') {
                    return null;
                }

                return [
                    'branch' => $branch,
                    'unit' => $unit,
                    'value' => $this->makeUnitKey($branch, $unit),
                ];
            })
            ->filter()
            ->unique(fn (array $item) => $item['value'])
            ->values()
            ->all();
    }

    private function intersectSelectedUnits(array $selectedUnits, array $unitOptions): array
    {
        if ($selectedUnits === [] || $unitOptions === []) {
            return [];
        }

        $available = array_fill_keys(array_map(
            fn (array $option) => strtoupper((string) ($option['value'] ?? '')),
            $unitOptions
        ), true);

        foreach ($unitOptions as $option) {
            $branch = strtoupper(trim((string) ($option['branch'] ?? '')));
            $unit = strtoupper(trim((string) ($option['unit'] ?? '')));
            $unitName = strtoupper(trim((string) ($option['unit_name'] ?? '')));

            if ($branch !== '' && $unit !== '') {
                $available[$branch . '||' . $unit] = true;
            }

            if ($branch !== '' && $unitName !== '') {
                $available[$branch . '||' . $unitName] = true;
            }
        }

        return collect($selectedUnits)
            ->filter(fn (array $unit) => isset($available[strtoupper((string) ($unit['value'] ?? ''))]))
            ->values()
            ->all();
    }

    private function summarizeUnitSelection(array $selectedUnits, array $unitOptions = []): string
    {
        if ($selectedUnits === []) {
            return 'Semua Kode Uker';
        }

        $labelMap = collect($unitOptions)
            ->mapWithKeys(fn (array $option) => [strtoupper((string) ($option['value'] ?? '')) => (string) ($option['label'] ?? '')])
            ->all();

        $labels = collect($selectedUnits)
            ->map(fn (array $unit) => $labelMap[strtoupper((string) ($unit['value'] ?? ''))] ?? strtoupper((string) ($unit['unit'] ?? '')))
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return 'Semua Kode Uker';
        }

        if ($labels->count() === 1) {
            return (string) $labels->first();
        }

        return $labels->count() . ' Kode Uker';
    }

    private function branchLabel(string $branch): string
    {
        if ($branch === 'all') {
            return self::DEFAULT_BRANCH_LABEL;
        }

        return $branch;
    }

    private function buildScopeLabel(string $selectedBranch, int $unitCount): string
    {
        $branchLabel = $this->branchLabel($selectedBranch);

        if ($unitCount <= 0) {
            return $branchLabel;
        }

        return $branchLabel . ' | ' . $unitCount . ' Unit';
    }

    private function makeUnitKey(string $branch, string $unit): string
    {
        return strtoupper(trim($branch)) . '||' . strtoupper(trim($unit));
    }

    private function normalizePeriod(?string $period): ?string
    {
        if ($period === null || trim($period) === '') {
            return null;
        }

        try {
            return Carbon::parse($period)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveLatestPeriodFromSource(string $table, string $periodColumn): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        $cacheKey = 'dashboard_pinjaman_chart_periodik_v3_latest_source:v' . $this->reportCacheVersion() . ':' . $table;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($table, $periodColumn) {
            $latest = DB::table($table)->max($periodColumn);

            return $latest !== null ? (string) $latest : null;
        });
    }

    private function resolveClosestPeriodFromSource(string $table, string $periodColumn, string $requestedPeriod): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        $cacheKey = 'dashboard_pinjaman_chart_periodik_v3_resolved_period:v' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'table' => $table,
            'period_column' => $periodColumn,
            'requested_period' => $requestedPeriod,
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($table, $periodColumn, $requestedPeriod) {
            $resolved = DB::table($table)
                ->where($periodColumn, '<=', $requestedPeriod)
                ->max($periodColumn);

            return $resolved !== null ? (string) $resolved : null;
        });
    }

    private function fetchPeriodsFromSource(string $table, string $periodColumn): Collection
    {
        if (!Schema::hasTable($table)) {
            return collect();
        }

        $cacheKey = 'dashboard_pinjaman_chart_periodik_v3_periods_source:v' . $this->reportCacheVersion() . ':' . $table;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($table, $periodColumn) {
            return DB::table($table)
                ->whereNotNull($periodColumn)
                ->distinct()
                ->orderByDesc($periodColumn)
                ->pluck($periodColumn)
                ->map(fn ($period) => (string) $period)
                ->filter()
                ->values();
        });
    }

    private function fetchUnitOptionsFromSource(string $table, string $periodColumn, string $selectedPeriod, array $branchScope, string $selectedBranch): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table)
            ->where($periodColumn, $selectedPeriod)
            ->whereNotNull('cabang1')
            ->whereRaw("TRIM(COALESCE(cabang1, '')) <> ''")
            ->whereIn(DB::raw('UPPER(TRIM(cabang1))'), $branchScope)
            ->selectRaw('UPPER(TRIM(cabang1)) as branch_name')
            ->selectRaw("UPPER(TRIM(unit1)) as unit_code")
            ->selectRaw("UPPER(TRIM(unit1)) as unit_name")
            ->distinct()
            ->orderBy('branch_name')
            ->orderBy('unit_code')
            ->orderBy('unit_name');

        return $query->get()
            ->map(function ($row) use ($selectedBranch) {
                $branch = (string) ($row->branch_name ?? '');
                $unitCode = (string) ($row->unit_code ?? '');
                $unitName = (string) ($row->unit_name ?? '');

                if ($branch === '') {
                    return null;
                }

                $unitCode = $this->normalizeDisplayValue($unitCode);
                $unitName = $this->normalizeDisplayValue($unitName);
                $unitLabel = $this->formatUnitOptionLabel($branch, $unitCode, $unitName, $selectedBranch === 'all');

                if ($unitLabel === '') {
                    return null;
                }

                return [
                    'value' => $this->makeUnitKey($branch, $unitCode !== '' ? $unitCode : $unitName),
                    'label' => $unitLabel,
                    'branch' => $branch,
                    'unit' => $unitCode !== '' ? $unitCode : $unitName,
                    'unit_name' => $unitName,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizePatternLabel($value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return $normalized !== '' ? $normalized : 'TIDAK TERPETAKAN';
    }

    private function normalizeDisplayValue(?string $value): string
    {
        $normalized = strtoupper(trim((string) $value));

        return $normalized !== '' ? $normalized : '';
    }

    private function formatUnitOptionLabel(string $branch, string $unitCode, string $unitName, bool $includeBranch): string
    {
        $labelParts = array_values(array_filter([
            $unitCode,
            $unitName !== '' && $unitName !== $unitCode ? $unitName : null,
            $includeBranch ? $branch : null,
        ]));

        return implode(' - ', $labelParts);
    }

    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return '-';
        }

        try {
            return Carbon::parse($period)->format('d/m/Y');
        } catch (Throwable) {
            return (string) $period;
        }
    }

    private function resolveSourceTable(): string
    {
        $cacheKey = 'dashboard_pinjaman_chart_periodik_v4_source_table:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            $hasSnapshot = DB::table(self::SNAPSHOT_TABLE)->exists();

            if ($hasSnapshot) {
                return self::SNAPSHOT_TABLE;
            }

            return self::RAW_TABLE;
        });
    }

    private function reportCacheVersion(): int
    {
        return (int) Cache::get('report_cache_version:global', 1);
    }
}
