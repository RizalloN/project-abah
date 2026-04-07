<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

class RekeningDormantController extends Controller
{
    public function index()
    {
        $latestPeriod = DB::table('simpanan_multipn')->max('posisi');

        return view('report.rekening-dormant', [
            'defaultPeriod' => $latestPeriod ?: now()->toDateString(),
            'selectedBranches' => [],
            'selectedUnits' => [],
        ]);
    }

    public function filters(Request $request)
    {
        @set_time_limit(30);
        $requestedPeriod = $request->input('posisi');
        $forceRefresh = $request->boolean('refresh');
        $currentPeriod = $this->resolveAvailablePeriod($requestedPeriod);
        $comparisonPeriod = $currentPeriod
            ? $this->resolveAvailablePeriod(Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString())
            : null;
        $selectedBranches = $this->normalizeFilterValues($request->input('kantor_cabang'));
        $selectedUnits = $this->normalizeFilterValues($request->input('unit_kerja'));
        $availableBranches = $currentPeriod ? $this->fetchAvailableBranches($currentPeriod, $forceRefresh) : collect();
        $validSelections = $availableBranches
            ->filter(fn (string $branch) => in_array($branch, $selectedBranches, true))
            ->values();
        $availableUnits = $currentPeriod
            ? $this->fetchAvailableUnits($currentPeriod, $validSelections->isNotEmpty() ? $validSelections : $availableBranches, $forceRefresh)
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
        $currentPeriod = $this->resolveAvailablePeriod($requestedPeriod);

        if (!$currentPeriod) {
            return response()->json([
                'status' => 'success',
                'labels' => $this->buildLabels(null, null, null, null),
                'effective_dates' => [
                    'curr' => null,
                    'mtd' => null,
                    'ytd' => null,
                    'yoy' => null,
                ],
                'data' => [],
                'branches' => [],
                'total' => [
                    'branch' => 'TOTAL AREA 6',
                    'current' => 0,
                    'mtd' => 0,
                    'ytd' => 0,
                    'yoy' => 0,
                ],
            ]);
        }

        $currDate = Carbon::parse($currentPeriod);
        $mtdPeriod = $this->resolveAvailablePeriod($currDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString());
        $ytdPeriod = $this->resolveAvailablePeriod($currDate->copy()->subYearNoOverflow()->endOfYear()->toDateString());
        $yoyPeriod = $this->resolveAvailablePeriod($currDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString());
        $requestedBranches = $this->normalizeFilterValues($request->input('kantor_cabang'));
        $requestedUnits = $this->normalizeFilterValues($request->input('unit_kerja'));
        $availableBranches = $this->fetchAvailableBranches($currentPeriod, $forceRefresh);
        $selectedBranches = $availableBranches
            ->filter(fn (string $branch) => empty($requestedBranches) || in_array($branch, $requestedBranches, true))
            ->values();

        if ($selectedBranches->isEmpty()) {
            $selectedBranches = $availableBranches;
        }

        $availableUnits = $this->fetchAvailableUnits($currentPeriod, $selectedBranches, $forceRefresh);
        $selectedUnits = $availableUnits
            ->filter(fn (string $unit) => empty($requestedUnits) || in_array($unit, $requestedUnits, true))
            ->values();

        $periodCounts = $this->fetchDormantCountsSummary(
            $currentPeriod,
            $mtdPeriod,
            $ytdPeriod,
            $yoyPeriod,
            $selectedBranches,
            $selectedUnits,
            $forceRefresh
        );

        $rows = [];
        $totals = [
            'branch' => 'TOTAL AREA 6',
            'current' => 0,
            'mtd' => 0,
            'ytd' => 0,
            'yoy' => 0,
        ];

        foreach ($selectedBranches as $branch) {
            $branchSummary = $periodCounts[$branch] ?? [];
            $current = (int) ($branchSummary['current'] ?? 0);
            $mtdBase = (int) ($branchSummary['mtd_base'] ?? 0);
            $ytdBase = (int) ($branchSummary['ytd_base'] ?? 0);
            $yoyBase = (int) ($branchSummary['yoy_base'] ?? 0);

            $row = [
                'branch' => $branch,
                'source_branch' => $branch,
                'current' => $current,
                'mtd' => $current - $mtdBase,
                'ytd' => $current - $ytdBase,
                'yoy' => $current - $yoyBase,
            ];

            $rows[] = $row;

            $totals['current'] += $row['current'];
            $totals['mtd'] += $row['mtd'];
            $totals['ytd'] += $row['ytd'];
            $totals['yoy'] += $row['yoy'];
        }

        return response()->json([
            'status' => 'success',
            'labels' => $this->buildLabels($currentPeriod, $mtdPeriod, $ytdPeriod, $yoyPeriod),
            'effective_dates' => [
                'curr' => $currentPeriod,
                'mtd' => $mtdPeriod,
                'ytd' => $ytdPeriod,
                'yoy' => $yoyPeriod,
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
                'count_basis' => 'COUNT(status)',
                'requested_period' => $requestedPeriod,
                'resolved_period' => $currentPeriod,
                'comparison_periods' => [
                    'mtd' => $mtdPeriod,
                    'ytd' => $ytdPeriod,
                    'yoy' => $yoyPeriod,
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

            if ($targetDate) {
                $query->where('posisi', '<=', Carbon::parse($targetDate)->toDateString());
            }

            return $query->max('posisi');
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchAvailableBranches(string $period, bool $forceRefresh = false): Collection
    {
        $cacheKey = 'rekening_dormant_v3_branch_options:' . md5(json_encode([
            'period' => $period,
            'version' => $this->buildTableVersionSignature('simpanan_multipn', 'posisi', $period),
        ]));

        return $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($period) {
            return $this->baseDormantQuery($period)
                ->whereNotNull('kantor_cabang')
                ->where('kantor_cabang', '<>', '')
                ->select('kantor_cabang')
                ->distinct()
                ->orderBy('kantor_cabang')
                ->pluck('kantor_cabang')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();
        }, $forceRefresh);
    }

    private function fetchAvailableUnits(string $period, Collection $branches, bool $forceRefresh = false): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        $cacheKey = 'rekening_dormant_v3_unit_options:' . md5(json_encode([
            'period' => $period,
            'branches' => $branches->values()->all(),
            'version' => $this->buildTableVersionSignature('simpanan_multipn', 'posisi', $period),
        ]));

        return $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use ($period, $branches) {
            return $this->baseDormantQuery($period)
                ->whereIn('kantor_cabang', $branches->all())
                ->whereNotNull('unit_kerja')
                ->where('unit_kerja', '<>', '')
                ->select('unit_kerja')
                ->distinct()
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
        ?string $ytdPeriod,
        ?string $yoyPeriod,
        Collection $branches,
        Collection $units,
        bool $forceRefresh = false
    ): array
    {
        if ($branches->isEmpty()) {
            return [];
        }

        $periods = collect([$currentPeriod, $mtdPeriod, $ytdPeriod, $yoyPeriod])->filter()->values();
        $periodVersions = $periods
            ->mapWithKeys(fn (string $period) => [$period => $this->buildTableVersionSignature('simpanan_multipn', 'posisi', $period)])
            ->all();

        $cacheKey = 'rekening_dormant_v3_counts_summary:' . md5(json_encode([
            'periods' => $periods->all(),
            'branches' => $branches->values()->all(),
            'units' => $units->values()->all(),
            'versions' => $periodVersions,
        ]));

        return $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use (
            $periods,
            $branches,
            $units,
            $currentPeriod,
            $mtdPeriod,
            $ytdPeriod,
            $yoyPeriod
        ) {
            $rows = DB::table('simpanan_multipn')
                ->select(
                    'posisi',
                    'kantor_cabang',
                    DB::raw('COUNT(status) as dormant_count')
                )
                ->whereIn('posisi', $periods->all())
                ->where('status', '9')
                ->whereIn('kantor_cabang', $branches->all())
                ->when($units->isNotEmpty(), fn ($query) => $query->whereIn('unit_kerja', $units->all()))
                ->groupBy('posisi', 'kantor_cabang')
                ->get();

            $counts = [];

            foreach ($rows as $row) {
                $counts[$row->kantor_cabang] ??= [
                    'current' => 0,
                    'mtd_base' => 0,
                    'ytd_base' => 0,
                    'yoy_base' => 0,
                ];

                $count = (int) ($row->dormant_count ?? 0);

                if ($row->posisi === $currentPeriod) {
                    $counts[$row->kantor_cabang]['current'] = $count;
                }

                if ($mtdPeriod && $row->posisi === $mtdPeriod) {
                    $counts[$row->kantor_cabang]['mtd_base'] = $count;
                }

                if ($ytdPeriod && $row->posisi === $ytdPeriod) {
                    $counts[$row->kantor_cabang]['ytd_base'] = $count;
                }

                if ($yoyPeriod && $row->posisi === $yoyPeriod) {
                    $counts[$row->kantor_cabang]['yoy_base'] = $count;
                }
            }

            return $counts;
        }, $forceRefresh);
    }

    private function baseDormantQuery(string $period)
    {
        return DB::table('simpanan_multipn')
            ->where('posisi', $period)
            ->where('status', '9');
    }

    private function buildLabels(?string $currentPeriod, ?string $mtdPeriod, ?string $ytdPeriod, ?string $yoyPeriod): array
    {
        return [
            'curr' => $this->formatPeriodLabel($currentPeriod),
            'mtd' => $this->formatPeriodLabel($mtdPeriod),
            'ytd' => $this->formatPeriodLabel($ytdPeriod),
            'yoy' => $this->formatPeriodLabel($yoyPeriod),
        ];
    }

    private function formatPeriodLabel(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            return Carbon::parse($date)->translatedFormat('d M Y');
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

    private function rememberPayload(string $cacheKey, $ttl, callable $callback, bool $forceRefresh = false)
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
            return $lock->block(15, function () use ($cacheKey, $latestKey, $ttl, $callback, $forceRefresh) {
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

            $payload = $callback();
            Cache::put($cacheKey, $payload, $ttl);
            Cache::put($latestKey, $payload, now()->addMinutes(10));

            return $payload;
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
            $row = DB::table($table)
                ->where($periodColumn, $periodValue)
                ->selectRaw("
                    COUNT(*) as total_rows,
                    COALESCE(MAX(id), 0) as max_id,
                    COALESCE(MAX({$timestampExpression}), '1970-01-01 00:00:00') as latest_change
                ")
                ->first();

            return implode('|', [
                $periodValue,
                (int) ($row->total_rows ?? 0),
                (int) ($row->max_id ?? 0),
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

        return 'id';
    }
}
