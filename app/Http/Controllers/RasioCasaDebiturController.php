<?php

namespace App\Http\Controllers;

use App\Jobs\EnsureRasioCasaSnapshotJob;
use App\Support\ReportSnapshotBuilder;
use App\Support\ReportIndexHintResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

class RasioCasaDebiturController extends Controller
{
    private const PRIORITY_BRANCHES = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
    private const SEGMENTS = ['TOTAL', 'BRIGUNA', 'KPR', 'MIKRO', 'SMC'];
    private const SNAPSHOT_TABLE = 'rasio_casa_debitur_snapshots';
    private const UKER_SNAPSHOT_TABLE = 'rasio_casa_debitur_uker_snapshots';
    private const LOAN_CIF_BRANCH_INDEX = 'idx_dld_periode_cif_cabang';
    private const CASA_CIF_TYPE_INDEX = 'idx_smp_posisi_cif_jenis';

    public function index()
    {
        $defaultPeriod = $this->resolveAvailableLoanPeriod(null) ?: now()->toDateString();
        $branches = collect(self::PRIORITY_BRANCHES)
            ->map(fn (string $branch) => $this->formatBranchLabel($branch))
            ->all();
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->buildRasioFilterOptions();

        return view('report.Rasiocasadebitur', compact('branches', 'defaultPeriod', 'branchOptions', 'branchUkerMap'));
    }

    public function fetchData(Request $request)
    {
        @set_time_limit(0);
        DB::connection()->disableQueryLog();

        try {
            $selectedBranches = collect((array) $request->input('branch_office', []))
                ->map(fn ($branch) => strtoupper(trim((string) $branch)))
                ->filter()
                ->values()
                ->all();
            $selectedUkers = collect((array) $request->input('nama_uker', []))
                ->map(fn ($uker) => strtoupper(trim((string) $uker)))
                ->filter()
                ->reject(fn ($uker) => $uker === 'ALL UKER')
                ->values()
                ->all();
            $isBranchFiltered = !empty($selectedBranches);

            if (!$this->hasAnyColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kolom wajib `baki_debet1` belum tersedia di tabel daily_loan_dinamis. Jalankan migration dan upload ulang data Daily Loan Dinamis.',
                ], 422);
            }

            $requestedDate = $request->input('posisi');
            $currentPeriod = $this->resolveAvailableLoanPeriod($requestedDate);

            if (!$currentPeriod) {
                return response()->json([
                    'status' => 'success',
                    'labels' => $this->buildLabels(null, null),
                    'effective_dates' => [
                        'prev' => null,
                        'curr' => null,
                        'casa_prev' => null,
                        'casa_curr' => null,
                    ],
                    'meta' => [
                        'has_rows' => false,
                        'row_count_prev' => 0,
                        'row_count_curr' => 0,
                        'branch_count' => count(self::PRIORITY_BRANCHES),
                    ],
                    'data' => $this->buildEmptyRows(),
                    'total' => $this->buildEmptyTotal(),
                ]);
            }

            $currDate = Carbon::parse($currentPeriod);
            $prevCandidate = $currDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $previousPeriod = $this->resolveAvailableLoanPeriod($prevCandidate);

            $forceRefresh = $request->boolean('refresh');
            $responseCacheKey = 'rasio_casa:fetch:v' . $this->reportCacheVersion() . ':' . md5(json_encode([
                'curr' => $currentPeriod,
                'prev' => $previousPeriod,
                'branches' => $selectedBranches,
                'ukers' => $selectedUkers,
            ]));

            if (!$forceRefresh) {
                $cachedResponse = Cache::get($responseCacheKey);
                if (is_array($cachedResponse)) {
                    return response()->json($cachedResponse);
                }
            }

            if ($isBranchFiltered) {
                $currentSummary = $this->buildFilteredSummarySnapshot($currentPeriod, $selectedBranches, $selectedUkers, $forceRefresh);
                $previousSummary = $previousPeriod
                    ? $this->buildFilteredSummarySnapshot($previousPeriod, $selectedBranches, $selectedUkers, $forceRefresh)
                    : $this->emptySnapshot();

                $branches = $this->resolveFilteredBranches($previousSummary, $currentSummary);
                [$rows, $total] = $this->assembleFilteredRows(
                    $branches,
                    $previousSummary,
                    $currentSummary,
                    'TOTAL ' . implode(', ', $selectedBranches)
                );
                $groupLabel = 'UKER';
            } else {
                $currentSummary = $this->buildSummarySnapshot($currentPeriod, $forceRefresh);
                $previousSummary = $previousPeriod ? $this->buildSummarySnapshot($previousPeriod, $forceRefresh) : $this->emptySnapshot();

                $branches = $this->resolveDynamicBranches($previousSummary, $currentSummary);
                [$rows, $total] = $this->assembleRows($branches, $previousSummary, $currentSummary);
                $groupLabel = 'BRANCH OFFICE';
            }

            $payload = [
                'status' => 'success',
                'labels' => $this->buildLabels($previousPeriod, $currentPeriod),
                'group_label' => $groupLabel,
                'effective_dates' => [
                    'prev' => $previousPeriod,
                    'curr' => $currentPeriod,
                    'casa_prev' => $previousSummary['casa_date'],
                    'casa_curr' => $currentSummary['casa_date'],
                ],
                'meta' => [
                    'has_rows' => (($previousSummary['row_count'] ?? 0) + ($currentSummary['row_count'] ?? 0)) > 0,
                    'row_count_prev' => (int) ($previousSummary['row_count'] ?? 0),
                    'row_count_curr' => (int) ($currentSummary['row_count'] ?? 0),
                    'branch_count' => count($branches),
                ],
                'data' => $rows,
                'total' => $total,
            ];

            Cache::put($responseCacheKey, $payload, now()->addMinutes(3));

            return response()->json($payload);
        } catch (Throwable $e) {
            Log::error('[RasioCasa] Critical Failure: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data. Server error. Periksa `storage/logs/laravel.log`',
            ], 500);
        }
    }

    private function buildSummarySnapshot(string $loanPeriod, bool $forceRefresh = false): array
    {
        $this->ensureRasioSnapshot($loanPeriod);

        $persisted = $this->loadPersistedSummarySnapshot($loanPeriod);
        if ($persisted !== null) {
            return $persisted;
        }

        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        $cacheKey = 'rasio_casa_debitur:v5:' . md5(json_encode([
            'cache_version' => $this->reportCacheVersion(),
            'loan_period' => $loanPeriod,
            'casa_date' => $casaDate,
            'loan_key' => $this->resolveLoanIdentityColumn(),
            'casa_key' => $this->resolveCasaIdentityColumn(),
        ]));
        $lockKey = $cacheKey . ':lock';
        $latestKey = $cacheKey . ':latest';

        $cached = $forceRefresh ? null : Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $lock = Cache::lock($lockKey, 30);

        try {
            return $lock->block(2, function () use ($cacheKey, $latestKey, $loanPeriod, $forceRefresh, $cached) {
                if (!$forceRefresh) {
                    $lockCached = Cache::get($cacheKey);
                    if ($lockCached) {
                        return $lockCached;
                    }
                }

                $payload = $this->computeSummarySnapshot($loanPeriod);

                Cache::put($cacheKey, $payload, now()->addMinutes(3));
                Cache::put($latestKey, $payload, now()->addMinutes(10));

                return $payload;
            });
        } catch (LockTimeoutException) {
            $latest = Cache::get($latestKey);
            if ($latest) {
                return $latest;
            }

            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }

            $payload = $this->computeSummarySnapshot($loanPeriod);
            Cache::put($cacheKey, $payload, now()->addMinutes(3));
            Cache::put($latestKey, $payload, now()->addMinutes(10));

            return $payload;
        } finally {
            optional($lock)->release();
        }
    }

    private function computeSummarySnapshot(string $loanPeriod): array
    {
        $loanKeyColumn = $this->resolveLoanIdentityColumn();
        $casaKeyColumn = $this->resolveCasaIdentityColumn();
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');
        $loanIdentitySql = "TRIM(d.{$loanKeyColumn})";

        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);

        $loanBranchSql = $this->buildLoanBranchExpression('d', $loanBranchColumn);
        $totalFlagSql = '1';
        $brigunaFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'smc');

        $loanBase = DB::table(DB::raw($this->qualifyIndexedSource('daily_loan_dinamis', 'd', [self::LOAN_CIF_BRANCH_INDEX])))
            ->where('d.periode', $loanPeriod)
            ->whereNotNull("d.{$loanKeyColumn}")
            ->where("d.{$loanKeyColumn}", '<>', '')
            ->whereRaw("{$loanBranchSql} <> ''")
            ->selectRaw("
                {$loanBranchSql} as branch_key,
                {$loanIdentitySql} as identity_key,
                COALESCE(d.{$loanBalanceColumn}, 0) as loan_balance,
                {$totalFlagSql} as has_total,
                {$brigunaFlagSql} as has_briguna,
                {$kprFlagSql} as has_kpr,
                {$mikroFlagSql} as has_mikro,
                {$smcFlagSql} as has_smc
            ");

        $loanPerCif = DB::query()
            ->fromSub($loanBase, 'loan_base')
            ->selectRaw("
                branch_key,
                identity_key,
                SUM(loan_balance) as total_os,
                SUM(CASE WHEN has_briguna = 1 THEN loan_balance ELSE 0 END) as briguna_os,
                SUM(CASE WHEN has_kpr = 1 THEN loan_balance ELSE 0 END) as kpr_os,
                SUM(CASE WHEN has_mikro = 1 THEN loan_balance ELSE 0 END) as mikro_os,
                SUM(CASE WHEN has_smc = 1 THEN loan_balance ELSE 0 END) as smc_os,
                MAX(has_total) as has_total,
                MAX(has_briguna) as has_briguna,
                MAX(has_kpr) as has_kpr,
                MAX(has_mikro) as has_mikro,
                MAX(has_smc) as has_smc
            ")
            ->groupBy('branch_key', 'identity_key');

        $snapshot = $this->emptySnapshot();
        $snapshot['loan_date'] = $loanPeriod;
        $snapshot['casa_date'] = $casaDate;

        $loanRows = $loanPerCif
            ->orderByRaw($this->buildBranchSortExpression('branch_key'))
            ->get();

        $identityVariants = [];
        $identityMappings = [];

        foreach ($loanRows as $row) {
            $branchKey = $this->normalizeBranchKey($row->branch_key ?? null);
            $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

            if ($branchKey === '' || $identityKey === '') {
                continue;
            }

            $snapshot['row_count']++;
            $snapshot['branch_labels'][$branchKey] = $this->formatBranchLabel($branchKey);
            $snapshot['os'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
            $snapshot['casa'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];

            $snapshot['os'][$branchKey]['total'] += (float) ($row->total_os ?? 0);
            $snapshot['os'][$branchKey]['briguna'] += (float) ($row->briguna_os ?? 0);
            $snapshot['os'][$branchKey]['kpr'] += (float) ($row->kpr_os ?? 0);
            $snapshot['os'][$branchKey]['mikro'] += (float) ($row->mikro_os ?? 0);
            $snapshot['os'][$branchKey]['smc'] += (float) ($row->smc_os ?? 0);

            $identityMappings[$identityKey][$branchKey] = [
                'total' => ((int) ($row->has_total ?? 0)) === 1,
                'briguna' => ((int) ($row->has_briguna ?? 0)) === 1,
                'kpr' => ((int) ($row->has_kpr ?? 0)) === 1,
                'mikro' => ((int) ($row->has_mikro ?? 0)) === 1,
                'smc' => ((int) ($row->has_smc ?? 0)) === 1,
            ];

            foreach ($this->buildIdentityVariants($identityKey) as $variant) {
                $identityVariants[$variant] = $identityKey;
            }
        }

        if ($casaDate && !empty($identityVariants)) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $casaBalances = [];

            foreach (array_chunk(array_keys($identityVariants), 2000) as $chunk) {
                $casaQuery = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::CASA_CIF_TYPE_INDEX])))
                    ->where('posisi', $casaDate)
                    ->whereIn($casaKeyColumn, $chunk);

                if ($applyCasaTypeFilter) {
                    $casaQuery->where(function ($query) {
                        $query->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                }

                $casas = $casaQuery
                    ->selectRaw("{$casaKeyColumn} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
                    ->groupBy($casaKeyColumn)
                    ->get();

                foreach ($casas as $casaRow) {
                    $normalizedIdentity = $this->normalizeIdentityKey($casaRow->identity_key ?? null);
                    if ($normalizedIdentity === '') {
                        continue;
                    }

                    $casaBalances[$normalizedIdentity] = ($casaBalances[$normalizedIdentity] ?? 0) + (float) ($casaRow->casa_balance ?? 0);
                }
            }

            foreach ($casaBalances as $identityKey => $balance) {
                foreach (($identityMappings[$identityKey] ?? []) as $branchKey => $flags) {
                    foreach ($flags as $segmentKey => $hasBucket) {
                        if ($hasBucket) {
                            $snapshot['casa'][$branchKey][$segmentKey] += $balance;
                        }
                    }
                }
            }
        }

        return $snapshot;
    }

    private function buildRasioFilterOptions(): array
    {
        $loanPeriod = $this->resolveAvailableLoanPeriod(null);
        if (!$loanPeriod) {
            return [
                'branchOptions' => collect(),
                'branchUkerMap' => collect(),
            ];
        }

        $branchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $ukerColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['unit1', 'unit'], 'unit1');
        $cacheKey = 'rasio_casa_filter_options:v' . $this->reportCacheVersion() . ':' . $loanPeriod;

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($loanPeriod, $branchColumn, $ukerColumn) {
            $rows = DB::table('daily_loan_dinamis')
                ->where('periode', $loanPeriod)
                ->whereNotNull($branchColumn)
                ->whereNotNull($ukerColumn)
                ->whereRaw("TRIM({$branchColumn}) <> ''")
                ->whereRaw("TRIM({$ukerColumn}) <> ''")
                ->selectRaw("UPPER(TRIM({$branchColumn})) as branch_name")
                ->selectRaw("UPPER(TRIM({$ukerColumn})) as uker_name")
                ->distinct()
                ->orderBy('branch_name')
                ->orderBy('uker_name')
                ->get();

            $branchOptions = $rows->pluck('branch_name')
                ->filter()
                ->unique()
                ->values();

            $branchUkerMap = $rows->groupBy('branch_name')
                ->map(function ($items) {
                    return $items->pluck('uker_name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                });

            return [
                'branchOptions' => $branchOptions,
                'branchUkerMap' => $branchUkerMap,
            ];
        });
    }

    private function buildFilteredSummarySnapshot(string $loanPeriod, array $selectedBranches, array $selectedUkers, bool $forceRefresh = false): array
    {
        $selectedBranches = collect($selectedBranches)
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();
        $selectedUkers = collect($selectedUkers)
            ->map(fn ($uker) => strtoupper(trim((string) $uker)))
            ->filter()
            ->values()
            ->all();

        $cacheKey = 'rasio_casa_filtered:v' . $this->reportCacheVersion() . ':' . md5(json_encode([
            'loan_period' => $loanPeriod,
            'branches' => $selectedBranches,
            'ukers' => $selectedUkers,
        ]));

        if (!$forceRefresh) {
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
        }

        $payload = $this->loadPersistedFilteredSummarySnapshot($loanPeriod, $selectedBranches, $selectedUkers);

        if ($payload === null) {
            $this->ensureRasioSnapshot($loanPeriod);
            $this->rebuildRasioSnapshotInline($loanPeriod);
            $payload = $this->loadPersistedFilteredSummarySnapshot($loanPeriod, $selectedBranches, $selectedUkers)
                ?? $this->computeFilteredSummarySnapshot($loanPeriod, $selectedBranches, $selectedUkers);
        }

        Cache::put($cacheKey, $payload, now()->addMinutes(10));

        return $payload;
    }

    private function computeFilteredSummarySnapshot(string $loanPeriod, array $selectedBranches, array $selectedUkers): array
    {
        $loanKeyColumn = $this->resolveLoanIdentityColumn();
        $casaKeyColumn = $this->resolveCasaIdentityColumn();
        $loanBranchColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['cabang1', 'cabang'], 'cabang1');
        $loanUkerColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['unit1', 'unit'], 'unit1');
        $loanSegmentColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['segmen_dashboard'], 'segmen_dashboard');
        $loanProductColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['produk_dashboard'], 'produk_dashboard');
        $loanBalanceColumn = $this->resolveExistingColumn('daily_loan_dinamis', ['baki_debet1', 'baki_debet'], 'baki_debet1');
        $loanIdentitySql = "TRIM(d.{$loanKeyColumn})";

        $casaDate = $this->resolveAvailableCasaPeriod($loanPeriod);
        $selectedBranches = collect($selectedBranches)
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();
        $selectedUkers = collect($selectedUkers)
            ->map(fn ($uker) => strtoupper(trim((string) $uker)))
            ->filter()
            ->values()
            ->all();

        $totalFlagSql = '1';
        $brigunaFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'briguna');
        $kprFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'kpr');
        $mikroFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'mikro');
        $smcFlagSql = $this->buildSegmentFlagExpression('d', $loanSegmentColumn, $loanProductColumn, 'smc');

        $loanBase = DB::table(DB::raw($this->qualifyIndexedSource('daily_loan_dinamis', 'd', [self::LOAN_CIF_BRANCH_INDEX])))
            ->where('d.periode', $loanPeriod)
            ->whereNotNull("d.{$loanKeyColumn}")
            ->where("d.{$loanKeyColumn}", '<>', '')
            ->whereRaw("TRIM(COALESCE(d.{$loanBranchColumn}, '')) <> ''")
            ->whereRaw("TRIM(COALESCE(d.{$loanUkerColumn}, '')) <> ''")
            ->when(!empty($selectedBranches), function ($query) use ($loanBranchColumn, $selectedBranches) {
                $query->whereIn(DB::raw("UPPER(TRIM(d.{$loanBranchColumn}))"), $selectedBranches);
            })
            ->when(!empty($selectedUkers), function ($query) use ($loanUkerColumn, $selectedUkers) {
                $query->whereIn(DB::raw("UPPER(TRIM(d.{$loanUkerColumn}))"), $selectedUkers);
            })
            ->selectRaw("
                UPPER(TRIM(d.{$loanUkerColumn})) as branch_key,
                {$loanIdentitySql} as identity_key,
                COALESCE(d.{$loanBalanceColumn}, 0) as loan_balance,
                {$totalFlagSql} as has_total,
                {$brigunaFlagSql} as has_briguna,
                {$kprFlagSql} as has_kpr,
                {$mikroFlagSql} as has_mikro,
                {$smcFlagSql} as has_smc
            ");

        $loanPerCif = DB::query()
            ->fromSub($loanBase, 'loan_base')
            ->selectRaw("
                branch_key,
                identity_key,
                SUM(loan_balance) as total_os,
                SUM(CASE WHEN has_briguna = 1 THEN loan_balance ELSE 0 END) as briguna_os,
                SUM(CASE WHEN has_kpr = 1 THEN loan_balance ELSE 0 END) as kpr_os,
                SUM(CASE WHEN has_mikro = 1 THEN loan_balance ELSE 0 END) as mikro_os,
                SUM(CASE WHEN has_smc = 1 THEN loan_balance ELSE 0 END) as smc_os,
                MAX(has_total) as has_total,
                MAX(has_briguna) as has_briguna,
                MAX(has_kpr) as has_kpr,
                MAX(has_mikro) as has_mikro,
                MAX(has_smc) as has_smc
            ")
            ->groupBy('branch_key', 'identity_key');

        $snapshot = $this->emptySnapshot();
        $snapshot['loan_date'] = $loanPeriod;
        $snapshot['casa_date'] = $casaDate;
        $identityVariants = [];
        $identityMappings = [];

        $loanRows = $loanPerCif
            ->orderBy('branch_key')
            ->get();

        foreach ($loanRows as $row) {
            $branchKey = strtoupper(trim((string) ($row->branch_key ?? '')));
            $identityKey = $this->normalizeIdentityKey($row->identity_key ?? null);

            if ($branchKey === '' || $identityKey === '') {
                continue;
            }

            $snapshot['row_count']++;
            $snapshot['branch_labels'][$branchKey] = $branchKey;
            $snapshot['os'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
            $snapshot['casa'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];

            $snapshot['os'][$branchKey]['total'] += (float) ($row->total_os ?? 0);
            $snapshot['os'][$branchKey]['briguna'] += (float) ($row->briguna_os ?? 0);
            $snapshot['os'][$branchKey]['kpr'] += (float) ($row->kpr_os ?? 0);
            $snapshot['os'][$branchKey]['mikro'] += (float) ($row->mikro_os ?? 0);
            $snapshot['os'][$branchKey]['smc'] += (float) ($row->smc_os ?? 0);

            $identityMappings[$identityKey][$branchKey] = [
                'total' => ((int) ($row->has_total ?? 0)) === 1,
                'briguna' => ((int) ($row->has_briguna ?? 0)) === 1,
                'kpr' => ((int) ($row->has_kpr ?? 0)) === 1,
                'mikro' => ((int) ($row->has_mikro ?? 0)) === 1,
                'smc' => ((int) ($row->has_smc ?? 0)) === 1,
            ];

            foreach ($this->buildIdentityVariants($identityKey) as $variant) {
                $identityVariants[$variant] = $identityKey;
            }
        }

        if ($casaDate && !empty($identityVariants)) {
            $applyCasaTypeFilter = $this->shouldApplyCasaTypeFilter($casaDate);
            $casaBalances = [];

            foreach (array_chunk(array_keys($identityVariants), 2000) as $chunk) {
                $casaQuery = DB::table(DB::raw($this->qualifyIndexedSource('simpanan_multipn', null, [self::CASA_CIF_TYPE_INDEX])))
                    ->where('posisi', $casaDate)
                    ->whereIn($casaKeyColumn, $chunk);

                if ($applyCasaTypeFilter) {
                    $casaQuery->where(function ($query) {
                        $query->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    });
                }

                $casas = $casaQuery
                    ->selectRaw("{$casaKeyColumn} as identity_key, SUM(COALESCE(saldo_idr, 0)) as casa_balance")
                    ->groupBy($casaKeyColumn)
                    ->get();

                foreach ($casas as $casaRow) {
                    $normalizedIdentity = $this->normalizeIdentityKey($casaRow->identity_key ?? null);
                    if ($normalizedIdentity === '') {
                        continue;
                    }

                    $casaBalances[$normalizedIdentity] = ($casaBalances[$normalizedIdentity] ?? 0) + (float) ($casaRow->casa_balance ?? 0);
                }
            }

            foreach ($casaBalances as $identityKey => $balance) {
                foreach (($identityMappings[$identityKey] ?? []) as $branchKey => $flags) {
                    foreach ($flags as $segmentKey => $hasBucket) {
                        if ($hasBucket) {
                            $snapshot['casa'][$branchKey][$segmentKey] += $balance;
                        }
                    }
                }
            }
        }

        return $snapshot;
    }

    private function loadPersistedSummarySnapshot(string $loanPeriod): ?array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return null;
        }

        $rows = DB::table(self::SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->orderByRaw($this->buildBranchSortExpression('branch_key'))
            ->orderBy('segment_key')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $snapshot = $this->emptySnapshot();
        $snapshot['loan_date'] = $loanPeriod;
        $snapshot['casa_date'] = $rows->first()->casa_period;
        $snapshot['row_count'] = (int) $rows->max('source_row_count');

        foreach ($rows as $row) {
            $branchKey = $this->normalizeBranchKey($row->branch_key ?? null);
            $segmentKey = strtolower(trim((string) ($row->segment_key ?? '')));

            if ($branchKey === '' || $segmentKey === '') {
                continue;
            }

            $snapshot['branch_labels'][$branchKey] = trim((string) ($row->branch_label ?? $this->formatBranchLabel($branchKey)));
            $snapshot['os'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
            $snapshot['casa'][$branchKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];

            if (array_key_exists($segmentKey, $snapshot['os'][$branchKey])) {
                $snapshot['os'][$branchKey][$segmentKey] = (float) ($row->os_amount ?? 0);
                $snapshot['casa'][$branchKey][$segmentKey] = (float) ($row->casa_amount ?? 0);
            }
        }

        return $snapshot;
    }

    private function loadPersistedFilteredSummarySnapshot(string $loanPeriod, array $selectedBranches, array $selectedUkers): ?array
    {
        if (!Schema::hasTable(self::UKER_SNAPSHOT_TABLE) || empty($selectedBranches)) {
            return null;
        }

        $normalizedBranches = collect($selectedBranches)
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();
        $normalizedUkers = collect($selectedUkers)
            ->map(fn ($uker) => strtoupper(trim((string) $uker)))
            ->filter()
            ->values()
            ->all();

        $rows = DB::table(self::UKER_SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->whereIn('source_branch_key', $normalizedBranches)
            ->when(!empty($normalizedUkers), function ($query) use ($normalizedUkers) {
                $query->whereIn('uker_key', $normalizedUkers);
            })
            ->orderByRaw('source_branch_key asc, uker_key asc')
            ->orderBy('segment_key')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $snapshot = $this->emptySnapshot();
        $snapshot['loan_date'] = $loanPeriod;
        $snapshot['casa_date'] = $rows->first()->casa_period;
        $rowCountKeys = [];

        foreach ($rows as $row) {
            $ukerKey = strtoupper(trim((string) ($row->uker_key ?? '')));
            $segmentKey = strtolower(trim((string) ($row->segment_key ?? '')));
            $sourceBranchKey = strtoupper(trim((string) ($row->source_branch_key ?? '')));

            if ($ukerKey === '' || $segmentKey === '') {
                continue;
            }

            $snapshot['branch_labels'][$ukerKey] = trim((string) ($row->uker_label ?? $ukerKey));
            $snapshot['os'][$ukerKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
            $snapshot['casa'][$ukerKey] ??= ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];

            if (array_key_exists($segmentKey, $snapshot['os'][$ukerKey])) {
                $snapshot['os'][$ukerKey][$segmentKey] += (float) ($row->os_amount ?? 0);
                $snapshot['casa'][$ukerKey][$segmentKey] += (float) ($row->casa_amount ?? 0);
            }

            $rowCountKeys[$sourceBranchKey . '|' . $ukerKey] = (int) ($row->source_row_count ?? 0);
        }

        $snapshot['row_count'] = array_sum($rowCountKeys);

        return $snapshot;
    }

    private function ensureRasioSnapshot(string $loanPeriod): void
    {
        $hasSummarySnapshotTable = Schema::hasTable(self::SNAPSHOT_TABLE);
        $hasUkerSnapshotTable = Schema::hasTable(self::UKER_SNAPSHOT_TABLE);

        if (!$hasSummarySnapshotTable && !$hasUkerSnapshotTable) {
            return;
        }

        $cacheKey = 'rasio_casa:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $loanPeriod;
        if (Cache::get($cacheKey) === true) {
            return;
        }

        $summaryExists = !$hasSummarySnapshotTable || DB::table(self::SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->exists();
        $ukerExists = !$hasUkerSnapshotTable || DB::table(self::UKER_SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->exists();
        $exists = $summaryExists && $ukerExists;

        if ($exists) {
            Cache::put($cacheKey, true, now()->addMinutes(10));
            return;
        }

        $hasSourceRows = DB::table('daily_loan_dinamis')
            ->where('periode', $loanPeriod)
            ->exists();

        if (!$hasSourceRows) {
            Cache::put($cacheKey, false, now()->addSeconds(30));
            return;
        }

        $lock = Cache::lock('snapshot:rasio:auto-rebuild:' . $loanPeriod, 60);
        $pendingKey = 'snapshot:rasio:auto-rebuild:pending:' . $loanPeriod;
        $jobDispatched = false;

        try {
            if ($lock->get()) {
                if (Cache::add($pendingKey, now()->toIso8601String(), now()->addMinutes(10))) {
                        EnsureRasioCasaSnapshotJob::dispatch($loanPeriod, static::class . '::ensureRasioSnapshot')
                        ->onQueue((string) config('queue.report_queue', 'default'));
                    $jobDispatched = true;
                }
            }
        } catch (Throwable $e) {
            Log::warning('Auto rebuild rasio snapshot gagal: ' . $e->getMessage(), [
                'loan_period' => $loanPeriod,
            ]);
        } finally {
            optional($lock)->release();
        }

        Log::info('Rasio CASA snapshot unavailable; using source query fallback.', [
            'loan_period' => $loanPeriod,
            'job_dispatched' => $jobDispatched,
        ]);

        Cache::put($cacheKey, false, now()->addSeconds(30));
    }

    private function rebuildRasioSnapshotInline(string $loanPeriod): void
    {
        if (!Schema::hasTable(self::UKER_SNAPSHOT_TABLE) || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return;
        }

        $summaryExists = DB::table(self::SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->exists();
        $ukerExists = DB::table(self::UKER_SNAPSHOT_TABLE)
            ->where('loan_period', $loanPeriod)
            ->exists();

        if ($summaryExists && $ukerExists) {
            Cache::put('rasio_casa:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $loanPeriod, true, now()->addMinutes(10));
            return;
        }

        $lock = Cache::lock('snapshot:rasio:inline-rebuild:' . $loanPeriod, 300);

        try {
            $lock->block(5, function () use ($loanPeriod) {
                $summaryExists = DB::table(self::SNAPSHOT_TABLE)
                    ->where('loan_period', $loanPeriod)
                    ->exists();
                $ukerExists = DB::table(self::UKER_SNAPSHOT_TABLE)
                    ->where('loan_period', $loanPeriod)
                    ->exists();

                if ($summaryExists && $ukerExists) {
                    return;
                }

                app(ReportSnapshotBuilder::class)->rebuildRasioCasa($loanPeriod, true);
                Cache::put('rasio_casa:snapshot_exists:v' . $this->reportCacheVersion() . ':' . $loanPeriod, true, now()->addMinutes(10));
            });
        } catch (LockTimeoutException|Throwable $e) {
            Log::warning('Inline rebuild rasio snapshot gagal atau timeout: ' . $e->getMessage(), [
                'loan_period' => $loanPeriod,
            ]);
        } finally {
            optional($lock)->release();
        }
    }

    private function assembleRows(array $branches, array $previousSummary, array $currentSummary): array
    {
        $rows = [];
        $total = ['branch' => 'TOTAL AREA 6'];

        foreach (self::SEGMENTS as $segment) {
            $segmentKey = strtolower($segment);
            $total[$segmentKey] = [
                'os_prev' => 0,
                'os_curr' => 0,
                'casa_prev' => 0,
                'casa_curr' => 0,
            ];
        }

        foreach ($branches as $branchLabel) {
            $branchKey = $this->normalizeBranchKey($branchLabel);
            $row = ['branch' => $branchLabel];

            foreach (self::SEGMENTS as $segment) {
                $segmentKey = strtolower($segment);
                $prevOs = (float) ($previousSummary['os'][$branchKey][$segmentKey] ?? 0);
                $prevCasa = (float) ($previousSummary['casa'][$branchKey][$segmentKey] ?? 0);
                $currOs = (float) ($currentSummary['os'][$branchKey][$segmentKey] ?? 0);
                $currCasa = (float) ($currentSummary['casa'][$branchKey][$segmentKey] ?? 0);

                $row[$segmentKey] = $this->calculateMetrics(
                    ['os' => $prevOs, 'casa' => $prevCasa],
                    ['os' => $currOs, 'casa' => $currCasa]
                );

                $total[$segmentKey]['os_prev'] += $prevOs;
                $total[$segmentKey]['os_curr'] += $currOs;
                $total[$segmentKey]['casa_prev'] += $prevCasa;
                $total[$segmentKey]['casa_curr'] += $currCasa;
            }

            $rows[] = $row;
        }

        foreach (self::SEGMENTS as $segment) {
            $segmentKey = strtolower($segment);
            $total[$segmentKey] = $this->calculateMetrics(
                ['os' => $total[$segmentKey]['os_prev'], 'casa' => $total[$segmentKey]['casa_prev']],
                ['os' => $total[$segmentKey]['os_curr'], 'casa' => $total[$segmentKey]['casa_curr']]
            );
        }

        return [$rows, $total];
    }

    private function assembleFilteredRows(array $branches, array $previousSummary, array $currentSummary, string $totalLabel): array
    {
        $rows = [];
        $total = ['branch' => $totalLabel];

        foreach (self::SEGMENTS as $segment) {
            $segmentKey = strtolower($segment);
            $total[$segmentKey] = [
                'os_prev' => 0,
                'os_curr' => 0,
                'casa_prev' => 0,
                'casa_curr' => 0,
            ];
        }

        foreach ($branches as $branchLabel) {
            $branchKey = strtoupper(trim((string) $branchLabel));
            $row = ['branch' => $branchLabel];

            foreach (self::SEGMENTS as $segment) {
                $segmentKey = strtolower($segment);
                $prevOs = (float) ($previousSummary['os'][$branchKey][$segmentKey] ?? 0);
                $prevCasa = (float) ($previousSummary['casa'][$branchKey][$segmentKey] ?? 0);
                $currOs = (float) ($currentSummary['os'][$branchKey][$segmentKey] ?? 0);
                $currCasa = (float) ($currentSummary['casa'][$branchKey][$segmentKey] ?? 0);

                $row[$segmentKey] = $this->calculateMetrics(
                    ['os' => $prevOs, 'casa' => $prevCasa],
                    ['os' => $currOs, 'casa' => $currCasa]
                );

                $total[$segmentKey]['os_prev'] += $prevOs;
                $total[$segmentKey]['os_curr'] += $currOs;
                $total[$segmentKey]['casa_prev'] += $prevCasa;
                $total[$segmentKey]['casa_curr'] += $currCasa;
            }

            $rows[] = $row;
        }

        foreach (self::SEGMENTS as $segment) {
            $segmentKey = strtolower($segment);
            $total[$segmentKey] = $this->calculateMetrics(
                ['os' => $total[$segmentKey]['os_prev'], 'casa' => $total[$segmentKey]['casa_prev']],
                ['os' => $total[$segmentKey]['os_curr'], 'casa' => $total[$segmentKey]['casa_curr']]
            );
        }

        return [$rows, $total];
    }

    private function emptySnapshot(): array
    {
        return [
            'loan_date' => null,
            'casa_date' => null,
            'row_count' => 0,
            'branch_labels' => [],
            'os' => [],
            'casa' => [],
        ];
    }

    private function buildEmptyRows(): array
    {
        return collect(self::PRIORITY_BRANCHES)
            ->map(function (string $branchKey) {
                $row = ['branch' => $this->formatBranchLabel($branchKey)];
                foreach (self::SEGMENTS as $segment) {
                    $row[strtolower($segment)] = $this->calculateMetrics(
                        ['os' => 0, 'casa' => 0],
                        ['os' => 0, 'casa' => 0]
                    );
                }

                return $row;
            })
            ->all();
    }

    private function buildEmptyTotal(): array
    {
        $total = ['branch' => 'TOTAL AREA 6'];
        foreach (self::SEGMENTS as $segment) {
            $total[strtolower($segment)] = $this->calculateMetrics(
                ['os' => 0, 'casa' => 0],
                ['os' => 0, 'casa' => 0]
            );
        }

        return $total;
    }

    private function resolveAvailableLoanPeriod(?string $targetDate): ?string
    {
        try {
            $query = DB::table('daily_loan_dinamis');

            if ($targetDate) {
                $query->where('periode', '<=', Carbon::parse($targetDate)->toDateString());
            } else {
                $cacheKey = 'rasio_casa_latest_loan_period:v' . $this->reportCacheVersion();

                return Cache::remember($cacheKey, now()->addMinutes(10), function () {
                    return DB::table('daily_loan_dinamis')->max('periode');
                });
            }

            return $query->max('periode');
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveAvailableCasaPeriod(string $targetDate): ?string
    {
        try {
            return DB::table('simpanan_multipn')
                ->where('posisi', '<=', $targetDate)
                ->max('posisi');
        } catch (Throwable) {
            return null;
        }
    }

    private function buildTableVersionSignature(string $table, string $periodColumn, string $periodValue): string
    {
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
        $updatedColumn = $this->resolveColumnName($table, ['updated_at'], 'updated_at');
        $createdColumn = $this->resolveColumnName($table, ['created_at'], 'created_at');

        $hasUpdated = Schema::hasColumn($table, $updatedColumn);
        $hasCreated = Schema::hasColumn($table, $createdColumn);

        if ($hasUpdated && $hasCreated) {
            return "COALESCE({$updatedColumn}, {$createdColumn})";
        }

        if ($hasUpdated) {
            return $updatedColumn;
        }

        if ($hasCreated) {
            return $createdColumn;
        }

        return $this->resolveIdentityColumn($table);
    }

    private function resolveIdentityColumn(string $table): string
    {
        $columns = Schema::getColumnListing($table);
        $map = [];

        foreach ($columns as $column) {
            $map[strtolower($column)] = $column;
        }

        return $map['uniqueid_namareport']
            ?? $map['uniqueid_dps']
            ?? $map['uniqueid_rcds']
            ?? $map['uniqueid_rds']
            ?? $map['uniqueid_smpn']
            ?? $map['id']
            ?? ($columns[0] ?? 'id');
    }

    private function shouldApplyCasaTypeFilter(string $casaDate): bool
    {
        $cacheKey = 'rasio_casa_apply_type_filter:v' . $this->reportCacheVersion() . ':' . $casaDate;

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($casaDate) {
            try {
                return DB::table('simpanan_multipn')
                    ->where('posisi', $casaDate)
                    ->where(function ($query) {
                        $query->where('jenis_simpanan', 'like', 'GIRO%')
                            ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                    })
                    ->exists();
            } catch (Throwable) {
                return false;
            }
        });
    }

    private function buildLabels(?string $previousPeriod, ?string $currentPeriod): array
    {
        return [
            'prev' => $this->formatShortDateLabel($previousPeriod),
            'curr' => $this->formatShortDateLabel($currentPeriod),
        ];
    }

    private function formatShortDateLabel(?string $date): string
    {
        if (!$date) {
            return '-';
        }

        try {
            $parsed = Carbon::parse($date);
            $bulanIndo = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
            ];

            return $parsed->format('d') . ' ' . $bulanIndo[$parsed->month] . " '" . $parsed->format('y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function buildLoanBranchExpression(string $alias, string $loanBranchColumn): string
    {
        $column = "{$alias}.{$loanBranchColumn}";

        return "
            CASE
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%MADIUN%' THEN 'MADIUN'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%MAGETAN%' THEN 'MAGETAN'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%NGAWI%' THEN 'NGAWI'
                WHEN UPPER(TRIM(COALESCE({$column}, ''))) LIKE '%PONOROGO%' THEN 'PONOROGO'
                ELSE ''
            END
        ";
    }

    private function buildJoinableIdentitySql(string $column): string
    {
        return "CONVERT(UPPER(REPLACE(TRIM(COALESCE({$column}, '')), '''', '')) USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    private function buildBranchSortExpression(string $column): string
    {
        return "
            CASE {$column}
                WHEN 'MADIUN' THEN 1
                WHEN 'MAGETAN' THEN 2
                WHEN 'NGAWI' THEN 3
                WHEN 'PONOROGO' THEN 4
                ELSE 99
            END
        ";
    }

    private function buildSegmentFlagExpression(string $alias, string $segmentColumn, string $productColumn, string $bucket): string
    {
        $segmen = "LOWER(TRIM(COALESCE({$alias}.{$segmentColumn}, '')))";
        $produk = "LOWER(TRIM(COALESCE({$alias}.{$productColumn}, '')))";

        return match ($bucket) {
            'briguna' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%briguna%' THEN 1
                    ELSE 0
                END
            ",
            'kpr' => "
                CASE
                    WHEN {$segmen} LIKE '%consumer%' AND {$produk} LIKE '%kpr%' THEN 1
                    ELSE 0
                END
            ",
            'mikro' => "
                CASE
                    WHEN {$segmen} LIKE '%micro%' OR {$segmen} LIKE '%mikro%' OR {$segmen} LIKE '%umkm%' THEN 1
                    ELSE 0
                END
            ",
            'smc' => "
                CASE
                    WHEN {$segmen} LIKE '%small%' OR {$segmen} LIKE '%smc%' OR {$segmen} LIKE '%menengah%' THEN 1
                    ELSE 0
                END
            ",
            default => '0',
        };
    }

    private function normalizeBranchKey(?string $branch): string
    {
        $value = strtoupper(trim((string) $branch));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^KC[\.\s-]*/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        foreach (self::PRIORITY_BRANCHES as $branchName) {
            if (str_contains($value, $branchName)) {
                return $branchName;
            }
        }

        return $value;
    }

    private function normalizeIdentityKey($value): string
    {
        $normalized = strtoupper(trim((string) $value));
        $normalized = ltrim($normalized, "'");

        return trim($normalized);
    }

    private function buildIdentityVariants(string $identityKey): array
    {
        $variants = [$identityKey];
        if (!str_starts_with($identityKey, "'")) {
            $variants[] = "'" . $identityKey;
        }

        return array_values(array_unique($variants));
    }

    private function formatBranchLabel(string $branchKey): string
    {
        $normalized = $this->normalizeBranchKey($branchKey);
        return $normalized === '' ? 'UNKNOWN BRANCH' : 'KC ' . $normalized;
    }

    private function resolveDynamicBranches(array $previousSummary, array $currentSummary): array
    {
        $branchMap = [];

        foreach ([$previousSummary, $currentSummary] as $dataset) {
            foreach (($dataset['branch_labels'] ?? []) as $branchKey => $label) {
                $normalizedKey = $this->normalizeBranchKey($branchKey);
                if ($normalizedKey !== '') {
                    $branchMap[$normalizedKey] = $this->formatBranchLabel($label);
                }
            }
        }

        if (empty($branchMap)) {
            foreach (self::PRIORITY_BRANCHES as $branchKey) {
                $branchMap[$branchKey] = $this->formatBranchLabel($branchKey);
            }
        }

        uksort($branchMap, fn (string $a, string $b) => $this->compareBranchPriority($a, $b));

        return array_values($branchMap);
    }

    private function resolveFilteredBranches(array $previousSummary, array $currentSummary): array
    {
        $branchMap = [];

        foreach ([$previousSummary, $currentSummary] as $dataset) {
            foreach (($dataset['branch_labels'] ?? []) as $branchKey => $label) {
                $normalizedKey = strtoupper(trim((string) $branchKey));
                $normalizedLabel = strtoupper(trim((string) ($label ?? $branchKey)));
                if ($normalizedKey !== '') {
                    $branchMap[$normalizedKey] = $normalizedLabel !== '' ? $normalizedLabel : $normalizedKey;
                }
            }
        }

        if (empty($branchMap)) {
            return [];
        }

        ksort($branchMap, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($branchMap);
    }

    private function compareBranchPriority(string $left, string $right): int
    {
        $leftIndex = array_search($left, self::PRIORITY_BRANCHES, true);
        $rightIndex = array_search($right, self::PRIORITY_BRANCHES, true);

        $leftIndex = $leftIndex === false ? 999 : $leftIndex;
        $rightIndex = $rightIndex === false ? 999 : $rightIndex;

        if ($leftIndex === $rightIndex) {
            return strcmp($left, $right);
        }

        return $leftIndex <=> $rightIndex;
    }

    private function resolveLoanIdentityColumn(): string
    {
        return $this->resolveColumnName('daily_loan_dinamis', ['nocif', 'cifno', 'CIFNO'], 'cifno');
    }

    private function resolveCasaIdentityColumn(): string
    {
        return $this->resolveColumnName('simpanan_multipn', ['nocif', 'cifno', 'CIFNO'], 'CIFNO');
    }

    private function hasAnyColumn(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return true;
            }
        }

        return false;
    }

    private function resolveExistingColumn(string $table, array $candidates, string $fallback): string
    {
        return $this->resolveColumnName($table, $candidates, $fallback);
    }

    private function resolveColumnName(string $table, array $candidates, string $fallback): string
    {
        $columns = Schema::getColumnListing($table);
        $map = [];

        foreach ($columns as $column) {
            $map[strtolower($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $matched = $map[strtolower($candidate)] ?? null;
            if ($matched) {
                return $matched;
            }
        }

        return $map[strtolower($fallback)] ?? $fallback;
    }

    private function calculateMetrics($prev, $curr)
    {
        $osPrev = (float) ($prev['os'] ?? 0);
        $casaPrev = (float) ($prev['casa'] ?? 0);
        $osCurr = (float) ($curr['os'] ?? 0);
        $casaCurr = (float) ($curr['casa'] ?? 0);

        if ($osCurr == 0.0 && $casaCurr == 0.0) {
            return [
                'os_prev' => $osPrev > 0 ? $osPrev : null,
                'casa_prev' => $casaPrev > 0 ? $casaPrev : null,
                'rasio_prev' => $osPrev > 0 ? ($casaPrev / $osPrev) * 100 : null,
                'os_curr' => null,
                'casa_curr' => null,
                'rasio_curr' => null,
                'mtd' => null,
            ];
        }

        $ratioPrev = $osPrev > 0 ? ($casaPrev / $osPrev) * 100 : 0;
        $ratioCurr = $osCurr > 0 ? ($casaCurr / $osCurr) * 100 : 0;

        return [
            'os_prev' => $osPrev > 0 ? $osPrev : null,
            'casa_prev' => $casaPrev > 0 ? $casaPrev : null,
            'rasio_prev' => $osPrev > 0 ? $ratioPrev : null,
            'os_curr' => $osCurr > 0 ? $osCurr : null,
            'casa_curr' => $casaCurr > 0 ? $casaCurr : null,
            'rasio_curr' => $osCurr > 0 ? $ratioCurr : null,
            'mtd' => ($osPrev > 0 || $osCurr > 0) ? ($ratioCurr - $ratioPrev) : null,
        ];
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
