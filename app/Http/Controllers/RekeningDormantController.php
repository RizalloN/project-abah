<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureRekeningDormantSnapshotJob;
use App\Support\ReportIndexHintResolver;
use App\Support\ReportCacheVersion;
use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

class RekeningDormantController extends Controller
{
    private const SNAPSHOT_TABLE = 'rekening_dormant_snapshots';
    private const DORMANT_SNAPSHOT_VERSION = 2;
    private const AREA_BRANCHES = [
        'KC Madiun',
        'KC Magetan',
        'KC Ngawi',
        'KC Ponorogo',
    ];

    private const BRANCH_PATTERNS = [
        'KC Madiun' => '%KC MADIUN%',
        'KC Magetan' => '%KC MAGETAN%',
        'KC Ngawi' => '%KC NGAWI%',
        'KC Ponorogo' => '%KC PONOROGO%',
    ];
    private const DORMANT_UNIT_INDEX = 'idx_smp_dormant_covering';
    private const DORMANT_SUMMARY_INDEX = 'idx_smp_dormant_covering';

    public function index()
    {
        $latestPeriod = $this->latestPeriod();
        $scope = UserBranchScope::current();

        return view('report.rekening-dormant', [
            'defaultPeriod' => $latestPeriod ?: now()->toDateString(),
            'selectedBranches' => $scope !== null ? [$scope['label']] : [],
            'selectedUnits' => [],
        ]);
    }

    public function filters(Request $request)
    {
        @set_time_limit(30);
        $requestedPeriod = $request->input('posisi');
        $forceRefresh = $request->boolean('refresh');
        $currentPeriod = $this->resolveRequestedDormantPeriod($requestedPeriod);
        $comparisonPeriod = $currentPeriod
            ? $this->resolveComparisonDormantPeriod(
                Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString()
            )
            : null;
        $selectedBranches = $this->normalizeFilterValues($request->input('kantor_cabang'));
        $selectedUnits = $this->normalizeFilterValues($request->input('unit_kerja'));
        $availableBranches = $currentPeriod ? $this->fetchAvailableBranches($currentPeriod, $forceRefresh) : collect();
        $validSelections = $availableBranches
            ->filter(fn (string $branch) => in_array($branch, $selectedBranches, true))
            ->values();
        $availableUnits = $currentPeriod
            ? $this->fetchAvailableUnits($currentPeriod, $validSelections, $forceRefresh)
            : collect();
        $validUnitSelections = $availableUnits
            ->filter(fn (string $unit) => in_array($unit, $selectedUnits, true))
            ->values();

        return response()->json([
            'selected_period' => $currentPeriod,
            'comparison_period' => $comparisonPeriod,
            'branch_options' => $availableBranches
                ->map(fn (string $branch) => [
                    'value' => $branch,
                    'label' => $branch,
                ])
                ->all(),
            'selected_branches' => $validSelections->all(),
            'unit_options' => $availableUnits
                ->map(fn (string $unit) => [
                    'value' => $unit,
                    'label' => $unit,
                ])
                ->all(),
            'selected_units' => $validUnitSelections->all(),
            'audit' => [
                'table' => 'simpanan_multipn',
                'period_column' => 'posisi',
                'branch_column' => 'kantor_cabang',
                'unit_column' => 'unit_kerja',
                'status_column' => 'status',
                'status_filter' => '9',
                'requested_period' => $requestedPeriod,
                'resolved_period' => $currentPeriod,
                'comparison_period' => $comparisonPeriod,
                'branch_option_count' => $availableBranches->count(),
                'unit_option_count' => $availableUnits->count(),
            ],
        ]);
    }

    public function fetchData(Request $request)
    {
        @set_time_limit(0);
        $requestedPeriod = $request->input('posisi');
        $forceRefresh = $request->boolean('refresh');
        $currentPeriod = $this->resolveRequestedDormantPeriod($requestedPeriod);

        if (!$currentPeriod) {
            return response()->json([
                'status' => 'success',
                'labels' => $this->buildLabels(null, null, null, null),
                'effective_dates' => [
                    'curr' => null,
                    'mtd' => null,
                    'm2' => null,
                    'ytd' => null,
                ],
                'data' => [],
                'branches' => [],
                'total' => [
                    'branch' => 'TOTAL AREA 6',
                    'current' => 0,
                    'mtd' => 0,
                    'm2' => 0,
                    'ytd' => 0,
                ],
            ]);
        }

        $currDate = Carbon::parse($currentPeriod);
        $mtdPeriod = $this->resolveComparisonDormantPeriod($currDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString());
        $m2Period = $this->resolveComparisonDormantPeriod($currDate->copy()->subMonthsNoOverflow(2)->endOfMonth()->toDateString());
        $ytdPeriod = $this->resolveComparisonDormantPeriod($currDate->copy()->subYearNoOverflow()->endOfYear()->toDateString());
        $requestedBranches = $this->normalizeFilterValues($request->input('kantor_cabang'));
        $requestedUnits = $this->normalizeFilterValues($request->input('unit_kerja'));
        $isBranchFiltered = !empty($requestedBranches);
        $availableBranches = $this->fetchAvailableBranches($currentPeriod, $forceRefresh);
        $selectedBranches = $availableBranches
            ->filter(fn (string $branch) => empty($requestedBranches) || in_array($branch, $requestedBranches, true))
            ->values();

        if ($selectedBranches->isEmpty()) {
            $selectedBranches = $availableBranches;
        }

        $selectedUnits = collect();

        if (!empty($requestedUnits)) {
            $availableUnits = $this->fetchAvailableUnits($currentPeriod, $selectedBranches, $forceRefresh);
            $selectedUnits = $availableUnits
                ->filter(fn (string $unit) => in_array($unit, $requestedUnits, true))
                ->values();
        }

        $rows = [];
        $totals = [
            'branch' => $isBranchFiltered
                ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches->values()->all()))
                : 'TOTAL AREA 6',
            'current' => 0,
            'mtd' => 0,
            'm2' => 0,
            'ytd' => 0,
            'mtd_base' => 0,
            'm2_base' => 0,
            'ytd_base' => 0,
        ];

        if ($isBranchFiltered) {
            $periodCounts = $this->fetchDormantCountsByUnit(
                $currentPeriod,
                $mtdPeriod,
                $m2Period,
                $ytdPeriod,
                $selectedBranches,
                $selectedUnits,
                $forceRefresh
            );

            $rowKeys = $selectedUnits->isNotEmpty()
                ? $selectedUnits->values()->all()
                : collect(array_keys($periodCounts))->sort()->values()->all();

            foreach ($rowKeys as $unit) {
                $unitSummary = $periodCounts[$unit] ?? [];
                $current = (int) ($unitSummary['current'] ?? 0);
                $mtdBase = (int) ($unitSummary['mtd_base'] ?? 0);
                $m2Base = (int) ($unitSummary['m2_base'] ?? 0);
                $ytdBase = (int) ($unitSummary['ytd_base'] ?? 0);

                $row = [
                    'branch' => $unit,
                    'source_branch' => $unit,
                    'current' => $current,
                    'mtd_base' => $mtdBase,
                    'm2_base' => $m2Base,
                    'ytd_base' => $ytdBase,
                    'mtd' => $current - $mtdBase,
                    'm2' => $current - $m2Base,
                    'ytd' => $current - $ytdBase,
                ];

                $rows[] = $row;

                $totals['current'] += $row['current'];
                $totals['mtd_base'] += $row['mtd_base'];
                $totals['m2_base'] += $row['m2_base'];
                $totals['ytd_base'] += $row['ytd_base'];
                $totals['mtd'] += $row['mtd'];
                $totals['m2'] += $row['m2'];
                $totals['ytd'] += $row['ytd'];
            }
        } else {
            $periodCounts = $this->fetchDormantCountsSummary(
                $currentPeriod,
                $mtdPeriod,
                $m2Period,
                $ytdPeriod,
                $selectedBranches,
                $selectedUnits,
                $forceRefresh
            );

            foreach ($selectedBranches as $branch) {
                $branchSummary = $periodCounts[$branch] ?? [];
                $current = (int) ($branchSummary['current'] ?? 0);
                $mtdBase = (int) ($branchSummary['mtd_base'] ?? 0);
                $m2Base = (int) ($branchSummary['m2_base'] ?? 0);
                $ytdBase = (int) ($branchSummary['ytd_base'] ?? 0);

                $row = [
                    'branch' => $branch,
                    'source_branch' => $branch,
                    'current' => $current,
                    'mtd_base' => $mtdBase,
                    'm2_base' => $m2Base,
                    'ytd_base' => $ytdBase,
                    'mtd' => $current - $mtdBase,
                    'm2' => $current - $m2Base,
                    'ytd' => $current - $ytdBase,
                ];

                $rows[] = $row;

                $totals['current'] += $row['current'];
                $totals['mtd_base'] += $row['mtd_base'];
                $totals['m2_base'] += $row['m2_base'];
                $totals['ytd_base'] += $row['ytd_base'];
                $totals['mtd'] += $row['mtd'];
                $totals['m2'] += $row['m2'];
                $totals['ytd'] += $row['ytd'];
            }
        }

        return response()->json([
            'status' => 'success',
            'group_label' => $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE',
            'labels' => $this->buildLabels($currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod),
            'effective_dates' => [
                'curr' => $currentPeriod,
                'mtd' => $mtdPeriod,
                'm2' => $m2Period,
                'ytd' => $ytdPeriod,
            ],
            'data' => $rows,
            'branches' => $selectedBranches->values()->all(),
            'units' => $selectedUnits->values()->all(),
            'total' => $totals,
            'audit' => [
                'table' => 'simpanan_multipn',
                'period_column' => 'posisi',
                'branch_column' => 'kantor_cabang',
                'unit_column' => 'unit_kerja',
                'status_column' => 'status',
                'status_filter' => '9',
                'count_basis' => 'COUNT(DISTINCT no_rekening)',
                'requested_period' => $requestedPeriod,
                'resolved_period' => $currentPeriod,
                'comparison_periods' => [
                    'mtd' => $mtdPeriod,
                    'm2' => $m2Period,
                    'ytd' => $ytdPeriod,
                ],
                'selected_branch_count' => $selectedBranches->count(),
                'selected_unit_count' => $selectedUnits->count(),
                'selected_branches' => $selectedBranches->values()->all(),
                'selected_units' => $selectedUnits->values()->all(),
                'row_count' => count($rows),
            ],
        ]);
    }

    private function resolveAvailablePeriod(?string $targetDate): ?string
    {
        try {
            $query = DB::table('simpanan_multipn');
            $this->scopeSourceQueryToCurrentBranch($query);

            if ($targetDate) {
                $query->where('posisi', '<=', Carbon::parse($targetDate)->toDateString());
            }

            return $query->max('posisi');
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveRequestedDormantPeriod($requestedPeriod): ?string
    {
        $rawPeriod = trim((string) $requestedPeriod);
        if ($rawPeriod === '') {
            return $this->latestPeriod();
        }

        if (preg_match('/^\d{4}-\d{2}$/', $rawPeriod) === 1) {
            return $this->resolveMonthlyDormantPeriod($rawPeriod);
        }

        return $this->normalizeRequestedPeriod($rawPeriod);
    }

    private function resolveMonthlyDormantPeriod(string $month): ?string
    {
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
            $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

            $sourceQuery = DB::table('simpanan_multipn')
                ->whereBetween('posisi', [$monthStart, $monthEnd])
                ->where('status', '9');
            $this->scopeSourceQueryToCurrentBranch($sourceQuery);
            $sourcePeriod = $sourceQuery->max('posisi');

            if ($sourcePeriod) {
                return Carbon::parse($sourcePeriod)->toDateString();
            }

            if (
                Schema::hasTable(self::SNAPSHOT_TABLE)
                && Schema::hasColumn(self::SNAPSHOT_TABLE, 'snapshot_version')
            ) {
                $snapshotPeriod = $this->dormantSnapshotQuery()
                    ->whereBetween('posisi', [$monthStart, $monthEnd])
                    ->max('posisi');

                if ($snapshotPeriod) {
                    return Carbon::parse($snapshotPeriod)->toDateString();
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function resolveComparisonDormantPeriod(string $targetDate): ?string
    {
        return $this->resolveAvailablePeriod($targetDate);
    }

    private function latestPeriod(): ?string
    {
        $cacheKey = 'rekening_dormant_latest_period:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(120), function () {
            // OPTIMIZED: Try snapshot table first for faster lookup
            $snapshotPeriod = null;
            if (
                Schema::hasTable(self::SNAPSHOT_TABLE)
                && Schema::hasColumn(self::SNAPSHOT_TABLE, 'snapshot_version')
            ) {
                $snapshotPeriod = $this->dormantSnapshotQuery()
                    ->max('posisi');
            }

            if ($snapshotPeriod) {
                return $snapshotPeriod;
            }

            // Fallback to source table
            $sourceQuery = DB::table('simpanan_multipn')->where('status', '9');
            $this->scopeSourceQueryToCurrentBranch($sourceQuery);

            return $sourceQuery->max('posisi');
        });
    }

    private function normalizeRequestedPeriod(?string $targetDate): ?string
    {
        if (!$targetDate) {
            return null;
        }

        try {
            return Carbon::parse($targetDate)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchAvailableBranches(string $period, bool $forceRefresh = false): Collection
    {
        $scope = UserBranchScope::current();

        return collect($scope !== null ? [$scope['label']] : self::AREA_BRANCHES);
    }

    private function fetchAvailableUnits(string $period, Collection $branches, bool $forceRefresh = false): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        if ($this->hasDormantSnapshots([$period])) {
            $cacheKey = 'rekening_dormant_v8_snapshot_unit_options:' . md5(json_encode([
                'cache_version' => $this->reportCacheVersion(),
                'period' => $period,
                'branches' => $branches->values()->all(),
            ]));

            return $this->rememberPayload($cacheKey, now()->addMinutes(120), function () use ($period, $branches) {
                // OPTIMIZED: Single query with minimal columns
                return $this->dormantSnapshotQuery()
                    ->where('posisi', $period)
                    ->whereIn('branch_label', $branches->all())
                    ->where('unit_kerja', '<>', '')
                    ->whereNotNull('unit_kerja')
                    ->select(DB::raw('DISTINCT TRIM(unit_kerja) as unit_kerja'))
                    ->orderBy('unit_kerja')
                    ->pluck('unit_kerja')
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->values();
            }, $forceRefresh);
        }

        $rawBranches = $this->resolveRawBranchesForLabels($period, $branches);

        if ($rawBranches->isEmpty()) {
            return collect();
        }

        $cacheKey = 'rekening_dormant_v6_unit_options:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'period' => $period,
            'raw_branches' => $rawBranches->values()->all(),
        ]));

        return $this->rememberPayload($cacheKey, now()->addMinutes(120), function () use ($period, $rawBranches) {
            // OPTIMIZED: Use index hint and distinct in query
            return $this->baseDormantQuery($period, self::DORMANT_UNIT_INDEX)
                ->whereIn('kantor_cabang', $rawBranches->all())
                ->whereNotNull('unit_kerja')
                ->where('unit_kerja', '<>', '')
                ->select(DB::raw('DISTINCT TRIM(unit_kerja) as unit_kerja'))
                ->orderBy('unit_kerja')
                ->pluck('unit_kerja')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();
        }, $forceRefresh);
    }


    private function fetchDormantCountsSummary(
        string $currentPeriod,
        ?string $mtdPeriod,
        ?string $m2Period,
        ?string $ytdPeriod,
        Collection $branches,
        Collection $units,
        bool $forceRefresh = false
    ): array
    {
        if ($branches->isEmpty()) {
            return [];
        }

        $periods = collect([$currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod])->filter()->values();
        $branchMap = $this->resolveBranchMapForPeriod($currentPeriod);
        $selectedBranchLabels = $branches->values()->all();
        $selectedRawBranches = collect($selectedBranchLabels)
            ->flatMap(fn (string $label) => $branchMap[$label] ?? [])
            ->unique()
            ->values();

        if ($selectedRawBranches->isEmpty()) {
            return [];
        }

        $rawBranchLookup = [];
        foreach ($branchMap as $label => $rawBranches) {
            foreach ($rawBranches as $rawBranch) {
                $rawBranchLookup[$rawBranch] = $label;
            }
        }

        $cacheKey = 'rekening_dormant_v6_counts_summary:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periods' => $periods->all(),
            'branches' => $selectedBranchLabels,
            'units' => $units->values()->all(),
        ]));

        // OPTIMIZED: Always try snapshot first for faster response
        if ($this->hasDormantSnapshots($periods->all())) {
            return $this->rememberPayload($cacheKey, now()->addMinutes(15), function () use (
                $periods,
                $selectedBranchLabels,
                $units,
                $currentPeriod,
                $mtdPeriod,
                $m2Period,
                $ytdPeriod,
            ) {
                // Use batched query for all periods at once
                $rows = $this->dormantSnapshotQuery()
                    ->select('posisi', 'branch_label', DB::raw('SUM(dormant_count) as dormant_count'))
                    ->whereIn('posisi', $periods->all())
                    ->whereIn('branch_label', $selectedBranchLabels)
                    ->when($units->isNotEmpty(), fn ($q) => $q->whereIn('unit_kerja', $units->all()))
                    ->groupBy('posisi', 'branch_label')
                    ->get();

                return $this->formatDormantSummaryCounts($rows, 'branch_label', $currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod);
            }, $forceRefresh);
        }

        // OPTIMIZED: Fallback with single batch query instead of per-period queries
        return $this->rememberPayload($cacheKey, now()->addMinutes(15), function () use (
            $periods,
            $selectedRawBranches,
            $rawBranchLookup,
            $units,
            $currentPeriod,
            $mtdPeriod,
            $m2Period,
            $ytdPeriod,
        ) {
            // BATCH QUERY: Get all periods in one query with index hint
            $rows = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::DORMANT_SUMMARY_INDEX])))
                ->select(
                    'posisi',
                    'kantor_cabang',
                    DB::raw('COUNT(DISTINCT no_rekening) as dormant_count')
                )
                ->whereIn('posisi', $periods->all())
                ->where('status', '9')
                ->whereIn('kantor_cabang', $selectedRawBranches->all())
                ->whereNotNull('no_rekening')
                ->where('no_rekening', '<>', '')
                ->when($units->isNotEmpty(), fn ($q) => $q->whereIn('unit_kerja', $units->all()))
                ->groupBy('posisi', 'kantor_cabang')
                ->get();

            // Process in single pass
            $counts = [];
            foreach ($rows as $row) {
                $branchLabel = $rawBranchLookup[$row->kantor_cabang] ?? null;
                if (!$branchLabel) continue;

                $counts[$branchLabel] ??= [
                    'current' => 0,
                    'mtd_base' => 0,
                    'm2_base' => 0,
                    'ytd_base' => 0,
                ];

                $count = (int) ($row->dormant_count ?? 0);
                if ($row->posisi === $currentPeriod) $counts[$branchLabel]['current'] += $count;
                if ($mtdPeriod && $row->posisi === $mtdPeriod) $counts[$branchLabel]['mtd_base'] += $count;
                if ($m2Period && $row->posisi === $m2Period) $counts[$branchLabel]['m2_base'] += $count;
                if ($ytdPeriod && $row->posisi === $ytdPeriod) $counts[$branchLabel]['ytd_base'] += $count;
            }

            return $counts;
        }, $forceRefresh);
    }

    private function formatDormantSummaryCounts($rows, string $groupField, string $currentPeriod, ?string $mtdPeriod, ?string $m2Period, ?string $ytdPeriod): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $groupValue = trim((string) ($row->{$groupField} ?? ''));
            if ($groupValue === '') continue;

            $counts[$groupValue] ??= [
                'current' => 0,
                'mtd_base' => 0,
                'm2_base' => 0,
                'ytd_base' => 0,
            ];

            $count = (int) ($row->dormant_count ?? 0);
            if ($row->posisi === $currentPeriod) $counts[$groupValue]['current'] += $count;
            if ($mtdPeriod && $row->posisi === $mtdPeriod) $counts[$groupValue]['mtd_base'] += $count;
            if ($m2Period && $row->posisi === $m2Period) $counts[$groupValue]['m2_base'] += $count;
            if ($ytdPeriod && $row->posisi === $ytdPeriod) $counts[$groupValue]['ytd_base'] += $count;
        }

        return $counts;
    }

    private function fetchDormantCountsByUnit(
        string $currentPeriod,
        ?string $mtdPeriod,
        ?string $m2Period,
        ?string $ytdPeriod,
        Collection $branches,
        Collection $units,
        bool $forceRefresh = false
    ): array
    {
        if ($branches->isEmpty()) {
            return [];
        }

        $periods = collect([$currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod])->filter()->values();
        $branchMap = $this->resolveBranchMapForPeriod($currentPeriod);
        $selectedBranchLabels = $branches->values()->all();
        $selectedRawBranches = collect($selectedBranchLabels)
            ->flatMap(fn (string $label) => $branchMap[$label] ?? [])
            ->unique()
            ->values();

        if ($selectedRawBranches->isEmpty()) {
            return [];
        }

        $cacheKey = 'rekening_dormant_v10_counts_by_unit:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periods' => $periods->all(),
            'branches' => $selectedBranchLabels,
            'units' => $units->values()->all(),
        ]));

        // OPTIMIZED: Always try snapshot first for faster response
        if ($this->hasDormantSnapshots($periods->all())) {
            return $this->rememberPayload($cacheKey, now()->addMinutes(15), function () use (
                $periods,
                $selectedBranchLabels,
                $units,
                $currentPeriod,
                $mtdPeriod,
                $m2Period,
                $ytdPeriod,
            ) {
                // Use batched query for all periods at once
                $rows = $this->dormantSnapshotQuery()
                    ->select('posisi', 'unit_kerja', DB::raw('SUM(dormant_count) as dormant_count'))
                    ->whereIn('posisi', $periods->all())
                    ->whereIn('branch_label', $selectedBranchLabels)
                    ->whereNotNull('unit_kerja')
                    ->where('unit_kerja', '<>', '')
                    ->when($units->isNotEmpty(), fn ($q) => $q->whereIn('unit_kerja', $units->all()))
                    ->groupBy('posisi', 'unit_kerja')
                    ->get();

                return $this->formatDormantGroupedCounts($rows, 'unit_kerja', $currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod);
            }, $forceRefresh);
        }

        // OPTIMIZED: Fallback with single batch query instead of per-period queries
        return $this->rememberPayload($cacheKey, now()->addMinutes(15), function () use (
            $periods,
            $selectedRawBranches,
            $units,
            $currentPeriod,
            $mtdPeriod,
            $m2Period,
            $ytdPeriod,
        ) {
            // BATCH QUERY: Get all periods in one query with index hint
            $rows = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::DORMANT_SUMMARY_INDEX])))
                ->select('posisi', 'unit_kerja', DB::raw('COUNT(DISTINCT no_rekening) as dormant_count'))
                ->whereIn('posisi', $periods->all())
                ->where('status', '9')
                ->whereIn('kantor_cabang', $selectedRawBranches->all())
                ->whereNotNull('unit_kerja')
                ->where('unit_kerja', '<>', '')
                ->whereNotNull('no_rekening')
                ->where('no_rekening', '<>', '')
                ->when($units->isNotEmpty(), fn ($q) => $q->whereIn('unit_kerja', $units->all()))
                ->groupBy('posisi', 'unit_kerja')
                ->get();

            return $this->formatDormantGroupedCounts($rows, 'unit_kerja', $currentPeriod, $mtdPeriod, $m2Period, $ytdPeriod);
        }, $forceRefresh);
    }

    private function formatDormantGroupedCounts($rows, string $groupField, string $currentPeriod, ?string $mtdPeriod, ?string $m2Period, ?string $ytdPeriod): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $groupValue = trim((string) ($row->{$groupField} ?? ''));

            if ($groupValue === '') {
                continue;
            }

            $counts[$groupValue] ??= [
                'current' => 0,
                'mtd_base' => 0,
                'm2_base' => 0,
                'ytd_base' => 0,
            ];

            $count = (int) ($row->dormant_count ?? 0);

            if ($row->posisi === $currentPeriod) {
                $counts[$groupValue]['current'] += $count;
            }

            if ($mtdPeriod && $row->posisi === $mtdPeriod) {
                $counts[$groupValue]['mtd_base'] += $count;
            }

            if ($m2Period && $row->posisi === $m2Period) {
                $counts[$groupValue]['m2_base'] += $count;
            }

            if ($ytdPeriod && $row->posisi === $ytdPeriod) {
                $counts[$groupValue]['ytd_base'] += $count;
            }
        }

        return $counts;
    }

    private function baseDormantQuery(string $period, ?string $indexName = null)
    {
        $source = DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, $indexName ? [$indexName] : []));

        return DB::table($source)
            ->where('posisi', $period)
            ->where('status', '9');
    }

    private function resolveRawBranchesForLabels(string $period, Collection $labels): Collection
    {
        $branchMap = $this->resolveBranchMapForPeriod($period);

        return $labels
            ->flatMap(fn (string $label) => $branchMap[$label] ?? [])
            ->unique()
            ->values();
    }

    private function resolveBranchMapForPeriod(string $period): array
    {
        $cacheKey = 'rekening_dormant_v6_branch_map:v' . $this->reportCacheVersion() . ':' . $period;

        return $this->rememberPayload($cacheKey, now()->addMinutes(240), function () use ($period) {
            if ($this->hasDormantSnapshots([$period])) {
                $map = collect(self::AREA_BRANCHES)
                    ->mapWithKeys(fn (string $label) => [$label => []])
                    ->all();

                // OPTIMIZED: Single query with minimal columns needed
                $rows = $this->dormantSnapshotQuery()
                    ->where('posisi', $period)
                    ->select('branch_label', 'raw_branch')
                    ->distinct()
                    ->orderBy('branch_label')
                    ->orderBy('raw_branch')
                    ->get();

                foreach ($rows as $row) {
                    $label = trim((string) ($row->branch_label ?? ''));
                    $rawBranch = trim((string) ($row->raw_branch ?? ''));

                    if ($label !== '' && $rawBranch !== '' && isset($map[$label])) {
                        $map[$label][] = $rawBranch;
                    }
                }

                return $map;
            }

            $map = collect(self::AREA_BRANCHES)
                ->mapWithKeys(fn (string $label) => [$label => []])
                ->all();

            // OPTIMIZED: Use index hint and select only needed columns
            $rawBranches = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::DORMANT_SUMMARY_INDEX])))
                ->where('posisi', $period)
                ->where('status', '9')
                ->whereNotNull('kantor_cabang')
                ->where('kantor_cabang', '<>', '')
                ->select(DB::raw('DISTINCT TRIM(kantor_cabang) as kantor_cabang'))
                ->orderBy('kantor_cabang')
                ->pluck('kantor_cabang')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            // Pre-compute pattern matching in single pass
            $patterns = collect(self::BRANCH_PATTERNS)
                ->mapWithKeys(fn ($pattern, $label) => [$label => str_replace('%', '', strtoupper($pattern))])
                ->all();

            foreach ($rawBranches as $rawBranch) {
                $upperBranch = strtoupper($rawBranch);

                foreach ($patterns as $label => $needle) {
                    if ($needle !== '' && str_contains($upperBranch, $needle)) {
                        $map[$label][] = $rawBranch;
                        break;
                    }
                }
            }

            return $map;
        });
    }

    private function hasDormantSnapshots(array $periods): bool
    {
        $periods = collect($periods)->filter()->values()->all();

        if (
            empty($periods)
            || !Schema::hasTable(self::SNAPSHOT_TABLE)
            || !Schema::hasColumn(self::SNAPSHOT_TABLE, 'snapshot_version')
        ) {
            return false;
        }

        sort($periods);

        // OPTIMIZED: Batch check instead of individual queries
        $cacheKey = 'rekening_dormant:snapshot_batch_check:v' . $this->reportCacheVersion() . ':' . md5(json_encode($periods));
        $cachedResult = Cache::get($cacheKey);
        
        if ($cachedResult !== null) {
            return (bool) $cachedResult;
        }

        // Single query to get all available periods at once
        $availablePeriods = $this->dormantSnapshotQuery()
            ->whereIn('posisi', $periods)
            ->distinct('posisi')
            ->pluck('posisi')
            ->all();

        $availablePeriodSet = array_flip($availablePeriods);
        $requiredPeriods = array_flip($periods);
        $missingPeriods = collect($periods)
            ->filter(fn (string $p) => !isset($availablePeriodSet[$p]))
            ->values()
            ->all();

        // If no missing periods, cache result and return true
        if (empty($missingPeriods)) {
            Cache::put($cacheKey, true, now()->addMinutes(30));
            return true;
        }

        // Only process missing periods for auto-rebuild
        foreach ($missingPeriods as $missingPeriod) {
            $periodCacheKey = 'rekening_dormant:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $missingPeriod;
            if (Cache::get($periodCacheKey) === true) {
                continue;
            }

            $hasSourceRows = DB::table('simpanan_multipn')
                ->where('posisi', $missingPeriod)
                ->where('status', '9')
                ->limit(1)
                ->exists();

            if (!$hasSourceRows) {
                Cache::put($periodCacheKey, false, now()->addSeconds(30));
                unset($requiredPeriods[$missingPeriod]);
                continue;
            }

            $lock = Cache::lock('snapshot:dormant:auto-rebuild:' . $missingPeriod, 60);
            $pendingKey = 'snapshot:dormant:auto-rebuild:pending:' . $missingPeriod;
            $jobDispatched = false;
            $built = false;

            try {
                if ($lock->get()) {
                    try {
                        app(ReportSnapshotBuilder::class)->rebuildRekeningDormant($missingPeriod, false);
                        $built = $this->dormantSnapshotQuery()
                            ->where('posisi', $missingPeriod)
                            ->exists();
                    } catch (Throwable $builderEx) {
                        Log::warning('Synchronous rebuild rekening dormant failed, falling back: ' . $builderEx->getMessage());
                    }

                    if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                        EnsureRekeningDormantSnapshotJob::dispatch($missingPeriod, static::class . '::hasDormantSnapshots')
                            ->onQueue((string) config('queue.report_queue', 'default'));
                        $jobDispatched = true;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Auto rebuild rekening dormant snapshot gagal: ' . $e->getMessage(), [
                    'period' => $missingPeriod,
                ]);
            } finally {
                optional($lock)->release();
            }

            if ($built) {
                Cache::put($periodCacheKey, true, now()->addMinutes(10));
                continue;
            }

            Log::info('Rekening dormant snapshot unavailable; using source query fallback.', [
                'period' => $missingPeriod,
                'job_dispatched' => $jobDispatched,
            ]);

            Cache::put($periodCacheKey, false, now()->addSeconds(30));
        }

        $allExist = count($availablePeriods) === count($requiredPeriods);
        Cache::put($cacheKey, (int) $allExist, now()->addMinutes(5)); // Short TTL for incomplete sets
        return $allExist;
    }

    private function dormantSnapshotQuery()
    {
        $query = DB::table(self::SNAPSHOT_TABLE);

        if (!Schema::hasColumn(self::SNAPSHOT_TABLE, 'snapshot_version')) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('snapshot_version', self::DORMANT_SNAPSHOT_VERSION);
        $scope = UserBranchScope::current();
        if ($scope !== null && Schema::hasColumn(self::SNAPSHOT_TABLE, 'branch_label')) {
            $query->whereRaw("UPPER(TRIM(COALESCE(branch_label, ''))) LIKE ?", [
                '%' . $scope['upper_label'] . '%',
            ]);
        }

        return $query;
    }

    private function buildLabels(?string $currentPeriod, ?string $mtdPeriod, ?string $m2Period, ?string $ytdPeriod): array
    {
        return [
            'curr' => $this->formatPeriodLabel($currentPeriod),
            'mtd' => $this->formatPeriodLabel($mtdPeriod),
            'm2' => $this->formatPeriodLabel($m2Period),
            'ytd' => $this->formatPeriodLabel($ytdPeriod),
        ];
    }

    private function formatPeriodLabel(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            return Carbon::parse($date)->translatedFormat('d M y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function normalizeFilterValues($value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function rememberPayload(string $cacheKey, $ttl, callable $callback, bool $forceRefresh = false, ?callable $fallback = null)
    {
        $latestKey = $cacheKey . ':latest';

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);

        try {
            return $lock->block(2, function () use ($cacheKey, $latestKey, $ttl, $callback, $forceRefresh) {
                if (!$forceRefresh) {
                    $cached = Cache::get($cacheKey);
                    if ($cached !== null) {
                        return $cached;
                    }
                }

                $payload = $callback();
                Cache::put($cacheKey, $payload, $ttl);
                Cache::put($latestKey, $payload, now()->addMinutes(10));

                return $payload;
            });
        } catch (LockTimeoutException) {
            $latest = Cache::get($latestKey);
            if ($latest !== null) {
                return $latest;
            }

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }

            if ($fallback) {
                return $fallback();
            }

            return $callback();
        } finally {
            optional($lock)->release();
        }
    }

    private function buildTableVersionSignature(string $table, string $periodColumn, ?string $periodValue): string
    {
        if (!$periodValue) {
            return 'null-period';
        }

        try {
            $timestampExpression = $this->buildLatestTimestampExpression($table);
            $identityColumn = $this->resolveIdentityColumn($table);
            $row = DB::table($table)
                ->where($periodColumn, $periodValue)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX({$identityColumn}), '') as max_identity,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                $periodValue,
                (int) ($row->total_rows ?? 0),
                (string) ($row->max_identity ?? ''),
                (string) ($row->latest_change ?? '1970-01-01 00:00:00'),
            ]);
        } catch (Throwable) {
            return $periodValue . '|fallback';
        }
    }

    private function buildLatestTimestampExpression(string $table): string
    {
        $hasUpdated = Schema::hasColumn($table, 'updated_at');
        $hasCreated = Schema::hasColumn($table, 'created_at');

        if ($hasUpdated && $hasCreated) {
            return 'COALESCE(updated_at, created_at)';
        }

        if ($hasUpdated) {
            return 'updated_at';
        }

        if ($hasCreated) {
            return 'created_at';
        }

        return $this->resolveIdentityColumn($table);
    }

    private function resolveIdentityColumn(string $table): string
    {
        if (Schema::hasColumn($table, 'uniqueid_rds')) {
            return 'uniqueid_rds';
        }

        if (Schema::hasColumn($table, 'uniqueid_rcds')) {
            return 'uniqueid_rcds';
        }

        if (Schema::hasColumn($table, 'uniqueid_dps')) {
            return 'uniqueid_dps';
        }

        if (Schema::hasColumn($table, 'uniqueid_namareport')) {
            return 'uniqueid_namareport';
        }

        if (Schema::hasColumn($table, 'uniqueid_SMPN')) {
            return 'uniqueid_SMPN';
        }

        if (Schema::hasColumn($table, 'id')) {
            return 'id';
        }

        $columns = Schema::getColumnListing($table);
        if (!empty($columns)) {
            return $columns[0];
        }

        return 'id';
    }

    private function scopeSourceQueryToCurrentBranch($query): void
    {
        $scope = UserBranchScope::current();
        if ($scope === null) {
            return;
        }

        $query->whereRaw("UPPER(TRIM(COALESCE(kantor_cabang, ''))) LIKE ?", [
            '%' . $scope['upper_label'] . '%',
        ]);
    }

    private function reportCacheVersion(): string
    {
        return ReportCacheVersion::get('simpanan') . ':scope:' . UserBranchScope::cacheKey();
    }

    private function qualifyIndexedSource(string $table, ?string $alias = null, array $preferredIndexes = []): string
    {
        return $this->reportIndexHintResolver()->qualify($table, $alias, $preferredIndexes);
    }

    private function reportIndexHintResolver(): ReportIndexHintResolver
    {
        return app(ReportIndexHintResolver::class);
    }
}
