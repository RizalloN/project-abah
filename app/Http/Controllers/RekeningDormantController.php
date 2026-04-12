<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureRekeningDormantSnapshotJob;
use App\Support\ReportIndexHintResolver;
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
    private const DORMANT_UNIT_INDEX = 'idx_smp_posisi_status_cabang_unit';
    private const DORMANT_SUMMARY_INDEX = 'idx_smp_posisi_status_cabang_unit';

    public function index()
    {
        $latestPeriod = $this->latestPeriod();

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
        $currentPeriod = $this->normalizeRequestedPeriod($requestedPeriod) ?? $this->latestPeriod();
        $comparisonPeriod = $currentPeriod
            ? Carbon::parse($currentPeriod)->subMonthNoOverflow()->endOfMonth()->toDateString()
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
        $currentPeriod = $this->normalizeRequestedPeriod($requestedPeriod) ?? $this->latestPeriod();

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
        $mtdPeriod = $currDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $ytdPeriod = $currDate->copy()->subYearNoOverflow()->endOfYear()->toDateString();
        $yoyPeriod = $currDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString();
        $requestedBranches = $this->normalizeFilterValues($request->input('kantor_cabang'));
        $requestedUnits = $this->normalizeFilterValues($request->input('unit_kerja'));
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

    private function latestPeriod(): ?string
    {
        $cacheKey = 'rekening_dormant_latest_period:v' . $this->reportCacheVersion();

        return Cache::remember($cacheKey, now()->addMinutes(10), function () {
            return DB::table('simpanan_multipn')->max('posisi');
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
        return collect(self::AREA_BRANCHES);
    }

    private function fetchAvailableUnits(string $period, Collection $branches, bool $forceRefresh = false): Collection
    {
        if ($branches->isEmpty()) {
            return collect();
        }

        if ($this->hasDormantSnapshots([$period])) {
            $cacheKey = 'rekening_dormant_v7_snapshot_unit_options:' . md5(json_encode([
                'cache_version' => $this->reportCacheVersion(),
                'period' => $period,
                'branches' => $branches->values()->all(),
            ]));

            return $this->rememberPayload($cacheKey, now()->addMinutes(10), function () use ($period, $branches) {
                return DB::table(self::SNAPSHOT_TABLE)
                    ->where('posisi', $period)
                    ->whereIn('branch_label', $branches->all())
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

        $rawBranches = $this->resolveRawBranchesForLabels($period, $branches);

        if ($rawBranches->isEmpty()) {
            return collect();
        }

        $cacheKey = 'rekening_dormant_v5_unit_options:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'period' => $period,
            'raw_branches' => $rawBranches->values()->all(),
        ]));

        return $this->rememberPayload($cacheKey, now()->addMinutes(10), function () use ($period, $rawBranches) {
            return $this->baseDormantQuery($period, self::DORMANT_UNIT_INDEX)
                ->whereIn('kantor_cabang', $rawBranches->all())
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

        $cacheKey = 'rekening_dormant_v4_counts_summary:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'periods' => $periods->all(),
            'branches' => $selectedBranchLabels,
            'units' => $units->values()->all(),
        ]));

        if ($this->hasDormantSnapshots($periods->all())) {
            return $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use (
                $periods,
                $selectedBranchLabels,
                $units,
                $currentPeriod,
                $mtdPeriod,
                $ytdPeriod,
                $yoyPeriod
            ) {
                $rows = DB::table(self::SNAPSHOT_TABLE)
                    ->select('posisi', 'branch_label', DB::raw('SUM(dormant_count) as dormant_count'))
                    ->whereIn('posisi', $periods->all())
                    ->whereIn('branch_label', $selectedBranchLabels)
                    ->when($units->isNotEmpty(), fn ($query) => $query->whereIn('unit_kerja', $units->all()))
                    ->groupBy('posisi', 'branch_label')
                    ->get();

                $counts = [];

                foreach ($rows as $row) {
                    $branchLabel = trim((string) ($row->branch_label ?? ''));

                    if ($branchLabel === '') {
                        continue;
                    }

                    $counts[$branchLabel] ??= [
                        'current' => 0,
                        'mtd_base' => 0,
                        'ytd_base' => 0,
                        'yoy_base' => 0,
                    ];

                    $count = (int) ($row->dormant_count ?? 0);

                    if ($row->posisi === $currentPeriod) {
                        $counts[$branchLabel]['current'] += $count;
                    }

                    if ($mtdPeriod && $row->posisi === $mtdPeriod) {
                        $counts[$branchLabel]['mtd_base'] += $count;
                    }

                    if ($ytdPeriod && $row->posisi === $ytdPeriod) {
                        $counts[$branchLabel]['ytd_base'] += $count;
                    }

                    if ($yoyPeriod && $row->posisi === $yoyPeriod) {
                        $counts[$branchLabel]['yoy_base'] += $count;
                    }
                }

                return $counts;
            }, $forceRefresh);
        }

        return $this->rememberPayload($cacheKey, now()->addMinutes(3), function () use (
            $periods,
            $selectedRawBranches,
            $rawBranchLookup,
            $units,
            $currentPeriod,
            $mtdPeriod,
            $ytdPeriod,
            $yoyPeriod
        ) {
            $rows = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::DORMANT_SUMMARY_INDEX])))
                ->select(
                    'posisi',
                    'kantor_cabang',
                    DB::raw('COUNT(*) as dormant_count')
                )
                ->whereIn('posisi', $periods->all())
                ->where('status', '9')
                ->whereIn('kantor_cabang', $selectedRawBranches->all())
                ->when($units->isNotEmpty(), fn ($query) => $query->whereIn('unit_kerja', $units->all()))
                ->groupBy('posisi', 'kantor_cabang')
                ->get();

            $counts = [];

            foreach ($rows as $row) {
                $branchLabel = $rawBranchLookup[$row->kantor_cabang] ?? null;

                if (!$branchLabel) {
                    continue;
                }

                $counts[$branchLabel] ??= [
                    'current' => 0,
                    'mtd_base' => 0,
                    'ytd_base' => 0,
                    'yoy_base' => 0,
                ];

                $count = (int) ($row->dormant_count ?? 0);

                if ($row->posisi === $currentPeriod) {
                    $counts[$branchLabel]['current'] += $count;
                }

                if ($mtdPeriod && $row->posisi === $mtdPeriod) {
                    $counts[$branchLabel]['mtd_base'] += $count;
                }

                if ($ytdPeriod && $row->posisi === $ytdPeriod) {
                    $counts[$branchLabel]['ytd_base'] += $count;
                }

                if ($yoyPeriod && $row->posisi === $yoyPeriod) {
                    $counts[$branchLabel]['yoy_base'] += $count;
                }
            }

            return $counts;
        }, $forceRefresh);
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

        return $this->rememberPayload($cacheKey, now()->addMinutes(30), function () use ($period) {
            if ($this->hasDormantSnapshots([$period])) {
                $map = collect(self::AREA_BRANCHES)
                    ->mapWithKeys(fn (string $label) => [$label => []])
                    ->all();

                $rows = DB::table(self::SNAPSHOT_TABLE)
                    ->where('posisi', $period)
                    ->select('branch_label', 'raw_branch')
                    ->distinct()
                    ->orderBy('branch_label')
                    ->orderBy('raw_branch')
                    ->get();

                foreach ($rows as $row) {
                    $label = trim((string) ($row->branch_label ?? ''));
                    $rawBranch = trim((string) ($row->raw_branch ?? ''));

                    if ($label !== '' && $rawBranch !== '' && array_key_exists($label, $map)) {
                        $map[$label][] = $rawBranch;
                    }
                }

                return $map;
            }

            $map = collect(self::AREA_BRANCHES)
                ->mapWithKeys(fn (string $label) => [$label => []])
                ->all();

            $rawBranches = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::DORMANT_SUMMARY_INDEX])))
                ->where('posisi', $period)
                ->where('status', '9')
                ->whereNotNull('kantor_cabang')
                ->where('kantor_cabang', '<>', '')
                ->select('kantor_cabang')
                ->distinct()
                ->orderBy('kantor_cabang')
                ->pluck('kantor_cabang')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();

            foreach ($rawBranches as $rawBranch) {
                $upperBranch = strtoupper($rawBranch);

                foreach (self::BRANCH_PATTERNS as $label => $pattern) {
                    $needle = str_replace('%', '', strtoupper($pattern));
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

        if (empty($periods) || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return false;
        }

        sort($periods);

        $missingPeriods = collect($periods)
            ->filter(function (string $period) {
                return !DB::table(self::SNAPSHOT_TABLE)
                    ->where('posisi', $period)
                    ->exists();
            })
            ->values();

        foreach ($missingPeriods as $missingPeriod) {
            $cacheKey = 'rekening_dormant:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $missingPeriod;
            if (Cache::get($cacheKey) === true) {
                continue;
            }

            $hasSourceRows = DB::table('simpanan_multipn')
                ->where('posisi', $missingPeriod)
                ->where('status', '9')
                ->exists();

            if (!$hasSourceRows) {
                Cache::put($cacheKey, false, now()->addSeconds(30));
                continue;
            }

            $lock = Cache::lock('snapshot:dormant:auto-rebuild:' . $missingPeriod, 60);
            $pendingKey = 'snapshot:dormant:auto-rebuild:pending:' . $missingPeriod;
            $jobDispatched = false;

            try {
                if ($lock->get()) {
                    if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                        EnsureRekeningDormantSnapshotJob::dispatch($missingPeriod, static::class . '::hasDormantSnapshots')
                            ->onQueue('reports');
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

            Log::info('Rekening dormant snapshot unavailable; using source query fallback.', [
                'period' => $missingPeriod,
                'job_dispatched' => $jobDispatched,
            ]);

            Cache::put($cacheKey, false, now()->addSeconds(30));
        }

        return DB::table(self::SNAPSHOT_TABLE)
            ->whereIn('posisi', $periods)
            ->distinct()
            ->count('posisi') === count($periods);
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

    private function reportCacheVersion(): int
    {
        return (int) Cache::get('report_cache_version:global', 1);
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
