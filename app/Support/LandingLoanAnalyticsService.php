<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class LandingLoanAnalyticsService
{
    private const SOURCE_TABLE = 'daily_loan_dinamis';

    private const SSA_LOAN_TABLE = 'ssa_pinjaman';

    private const PERFORMANCE_SNAPSHOT_TABLE = 'performance_rm_cabang_snapshots';

    private const CACHE_VERSION = 'v7-sme-ssa-monthly-os-realization';

    private const RESTRUCTURING_FREQUENCY_CACHE_VERSION = 'v1';

    private const AREA_BRANCHES = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];

    private const SEGMENTS = [
        'sme' => 'SMALL',
        'consumer' => 'CONSUMER',
        'micro' => 'MICRO',
    ];

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function payload(
        ?string $requestedPeriod,
        ?array $branchScope = null,
        bool $forceRefresh = false,
        ?string $segmentScope = null
    ): array
    {
        $segmentScope = is_string($segmentScope) && isset(self::SEGMENTS[$segmentScope]) ? $segmentScope : null;
        $empty = $this->emptyPayload($requestedPeriod, $branchScope);
        if (! $this->sourceIsReady()) {
            return $empty;
        }

        try {
            $period = $this->resolvePeriod($requestedPeriod, $branchScope, $segmentScope);
            if ($period === null) {
                return $empty;
            }

            $year = Carbon::parse($period)->year;
            $cacheKey = implode(':', [
                'landing',
                'loan-analytics',
                self::CACHE_VERSION,
                ReportCacheVersion::composite(['pinjaman', 'harian']),
                $year,
                $period,
                $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                $segmentScope ?? 'all-segments',
            ]);

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($period, $year, $branchScope, $segmentScope): array {
                $periods = $this->periodsForYear($year, $period, $branchScope, $segmentScope);
                $monthEnds = $periods
                    ->filter(static fn (string $candidate): bool => Carbon::parse($candidate)->isLastOfMonth())
                    ->values();
                $segments = $segmentScope !== null
                    ? [$segmentScope => self::SEGMENTS[$segmentScope]]
                    : self::SEGMENTS;

                return [
                    'meta' => [
                        'available' => $monthEnds->isNotEmpty(),
                        'period' => $period,
                        'period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
                        'year' => $year,
                        'scope_label' => $branchScope['label'] ?? 'Area 6',
                        'source' => 'Daily Loan Dinamis',
                        'error' => '',
                    ],
                    'quality' => $this->qualityPayload($year, $monthEnds, $branchScope, $segments),
                    'tariff_relief' => $segmentScope === null || $segmentScope === 'sme'
                        ? $this->tariffPayload($period, $year, $branchScope)
                        : $this->emptyTariffPayload(),
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Analitik landing pinjaman gagal dihitung.', [
                'period' => $requestedPeriod,
                'scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                'error' => $exception->getMessage(),
            ]);

            $empty['meta']['error'] = $exception->getMessage();

            return $empty;
        }
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function restructuringFrequency(
        ?string $requestedPeriod,
        ?array $branchScope = null,
        bool $forceRefresh = false
    ): array {
        $empty = $this->emptyRestructuringFrequencyPayload($requestedPeriod, $branchScope);
        if (! $this->restructuringFrequencySourceIsReady()) {
            return $empty;
        }

        try {
            $period = $this->resolvePeriod($requestedPeriod, $branchScope, 'sme');
            if ($period === null) {
                return $empty;
            }

            $cacheKey = implode(':', [
                'landing',
                'sme-restructuring-frequency',
                self::RESTRUCTURING_FREQUENCY_CACHE_VERSION,
                ReportCacheVersion::composite(['pinjaman', 'harian']),
                $period,
                $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
            ]);

            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }

            return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($period, $branchScope, $empty): array {
                $identityExpression = $this->restructuringDebtorIdentityExpression();
                if ($identityExpression === null) {
                    return $empty;
                }

                $accountExpression = Schema::hasColumn(self::SOURCE_TABLE, 'nomor_rekening1')
                    ? "NULLIF(TRIM(nomor_rekening1), '')"
                    : $identityExpression;
                $query = DB::table(self::SOURCE_TABLE)
                    ->where('periode', $period)
                    ->where('segmen_kinerja', self::SEGMENTS['sme'])
                    ->where('restruk_ke1', '>', 0);
                $this->applyBranchScope($query, $branchScope);

                $rows = (clone $query)
                    ->selectRaw('restruk_ke1 AS frequency')
                    ->selectRaw("COUNT(DISTINCT {$identityExpression}) AS debtor_count")
                    ->selectRaw("COUNT(DISTINCT {$accountExpression}) AS account_count")
                    ->selectRaw('SUM(COALESCE(baki_debet1, 0)) AS os_amount')
                    ->groupBy('restruk_ke1')
                    ->orderBy('restruk_ke1')
                    ->get();

                $totals = (clone $query)
                    ->selectRaw("COUNT(DISTINCT {$identityExpression}) AS debtor_count")
                    ->selectRaw("COUNT(DISTINCT {$accountExpression}) AS account_count")
                    ->selectRaw('SUM(COALESCE(baki_debet1, 0)) AS os_amount')
                    ->first();

                return [
                    'available' => $rows->isNotEmpty(),
                    'period' => $period,
                    'period_label' => Carbon::parse($period)->translatedFormat('d M Y'),
                    'scope_label' => $branchScope['label'] ?? 'Area 6',
                    'source' => 'Daily Loan Dinamis',
                    'unit' => 'Rp Juta',
                    'total_debtors' => (int) ($totals->debtor_count ?? 0),
                    'total_accounts' => (int) ($totals->account_count ?? 0),
                    'total_os_juta' => (float) ($totals->os_amount ?? 0) / 1_000_000,
                    'buckets' => $rows->map(static function (object $row): array {
                        $frequency = max(1, (int) $row->frequency);

                        return [
                            'frequency' => $frequency,
                            'label' => 'Restruk '.$frequency.' kali',
                            'debtors' => (int) $row->debtor_count,
                            'accounts' => (int) $row->account_count,
                            'os_juta' => (float) $row->os_amount / 1_000_000,
                        ];
                    })->values()->all(),
                    'error' => '',
                ];
            });
        } catch (Throwable $exception) {
            Log::warning('Frekuensi restrukturisasi SME gagal dihitung.', [
                'period' => $requestedPeriod,
                'scope' => $branchScope['key'] ?? UserBranchScope::AREA_SCOPE,
                'error' => $exception->getMessage(),
            ]);

            $empty['error'] = $exception->getMessage();

            return $empty;
        }
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    public function decorateDashboard(array $dashboard, ?string $requestedPeriod, ?array $branchScope = null): array
    {
        $analytics = $this->payload($requestedPeriod, $branchScope);
        foreach (array_keys(self::SEGMENTS) as $scope) {
            data_set(
                $dashboard,
                'area6_portfolio.scopes.'.$scope.'.quality_timeseries',
                (array) data_get($analytics, 'quality.'.$scope, [])
            );
        }
        data_set(
            $dashboard,
            'area6_portfolio.scopes.sme.tariff_relief',
            (array) data_get($analytics, 'tariff_relief', [])
        );

        return $dashboard;
    }

    /**
     * @param  Collection<int, string>  $monthEnds
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    private function qualityPayload(int $year, Collection $monthEnds, ?array $branchScope, array $segments): array
    {
        $rows = $this->microQualitySnapshotRows($monthEnds, $branchScope, $segments);
        if ($rows === null) {
            $rows = collect();
        }
        if ($rows->isEmpty() && $monthEnds->isNotEmpty()) {
            $query = DB::table(self::SOURCE_TABLE)
                ->whereIn('periode', $monthEnds->all())
                ->whereIn('segmen_kinerja', array_values($segments));
            $this->applyBranchScope($query, $branchScope);

            $rows = $query
                ->selectRaw('periode')
                ->selectRaw('segmen_kinerja AS segment_key')
                ->selectRaw("SUM(CASE WHEN (COALESCE(kolek, 0) * 1) = 1 AND UPPER(TRIM(COALESCE(flag_restruk, ''))) = 'Y' THEN COALESCE(baki_debet1, 0) ELSE 0 END) AS lr_amount")
                ->selectRaw('SUM(CASE WHEN (COALESCE(kolek, 0) * 1) = 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) AS sml_amount')
                ->selectRaw('SUM(CASE WHEN (COALESCE(kolek, 0) * 1) > 2 THEN COALESCE(baki_debet1, 0) ELSE 0 END) AS npl_amount')
                ->groupBy('periode', 'segmen_kinerja')
                ->get()
                ->keyBy(static fn (object $row): string => strtoupper(trim((string) $row->segment_key)).'|'.substr((string) $row->periode, 0, 10));
        }

        $labels = [];
        $periodLabels = [];
        $monthPeriods = $monthEnds->keyBy(static fn (string $candidate): int => Carbon::parse($candidate)->month);
        for ($month = 1; $month <= 12; $month++) {
            $monthDate = Carbon::create($year, $month, 1);
            $labels[] = $monthDate->translatedFormat('M');
            $period = $monthPeriods->get($month);
            $periodLabels[] = $period ? Carbon::parse($period)->translatedFormat('d M Y') : '-';
        }

        $payload = [];
        foreach ($segments as $scope => $segment) {
            $series = ['lr' => [], 'sml' => [], 'npl' => [], 'lar' => []];
            $points = [];
            for ($month = 1; $month <= 12; $month++) {
                $period = $monthPeriods->get($month);
                $row = $period ? $rows->get($segment.'|'.$period) : null;
                $lr = $row ? ((float) $row->lr_amount / 1_000_000) : null;
                $sml = $row ? ((float) $row->sml_amount / 1_000_000) : null;
                $npl = $row ? ((float) $row->npl_amount / 1_000_000) : null;
                $lar = $row ? ($lr + $sml + $npl) : null;
                $series['lr'][] = $lr;
                $series['sml'][] = $sml;
                $series['npl'][] = $npl;
                $series['lar'][] = $lar;
                $points[] = [
                    'period' => $period,
                    'period_label' => $period ? Carbon::parse($period)->translatedFormat('d M Y') : '-',
                    'lr' => $lr,
                    'sml' => $sml,
                    'npl' => $npl,
                    'lar' => $lar,
                ];
            }

            $payload[$scope] = [
                'available' => collect($series['lar'])->contains(static fn ($value): bool => $value !== null),
                'year' => $year,
                'labels' => $labels,
                'period_labels' => $periodLabels,
                'series' => $series,
                'points' => $points,
                'unit' => 'Rp Juta',
            ];
        }

        return $payload;
    }

    /**
     * Membaca snapshot yang sudah diagregasi per cabang agar Timeseries Mikro tidak
     * memindai jutaan baris Daily Loan setiap halaman dibuka. Null berarti snapshot
     * tidak kompatibel dan caller harus memakai query sumber sebagai fallback.
     *
     * @param  Collection<int, string>  $monthEnds
     * @param  array<string, mixed>|null  $branchScope
     * @param  array<string, string>  $segments
     * @return Collection<string, object>|null
     */
    private function microQualitySnapshotRows(Collection $monthEnds, ?array $branchScope, array $segments): ?Collection
    {
        if ($segments !== ['micro' => 'MICRO'] || $monthEnds->isEmpty()) {
            return null;
        }
        if (! Schema::hasTable(self::PERFORMANCE_SNAPSHOT_TABLE)) {
            return null;
        }

        foreach (['periode', 'cabang', 'segmen', 'restruk_os', 'sml_os', 'npl_os'] as $column) {
            if (! Schema::hasColumn(self::PERFORMANCE_SNAPSHOT_TABLE, $column)) {
                return null;
            }
        }

        $query = DB::table(self::PERFORMANCE_SNAPSHOT_TABLE)
            ->whereIn('periode', $monthEnds->all())
            ->where('segmen', 'MICRO');
        $this->applySnapshotBranchScope($query, $branchScope);

        return $query
            ->selectRaw('periode')
            ->selectRaw('segmen AS segment_key')
            ->selectRaw('SUM(COALESCE(restruk_os, 0)) AS lr_amount')
            ->selectRaw('SUM(COALESCE(sml_os, 0)) AS sml_amount')
            ->selectRaw('SUM(COALESCE(npl_os, 0)) AS npl_amount')
            ->groupBy('periode', 'segmen')
            ->get()
            ->keyBy(static fn (object $row): string => strtoupper(trim((string) $row->segment_key)).'|'.substr((string) $row->periode, 0, 10));
    }

    /**
     * @param  array<string, mixed>|null  $branchScope
     * @return array<string, mixed>
     */
    private function tariffPayload(string $period, int $year, ?array $branchScope): array
    {
        if (! $this->ssaTariffSourceIsReady()) {
            return $this->emptyTariffPayload();
        }

        $pairs = [];
        $latestAvailablePeriod = Carbon::parse($period)->startOfDay();
        for ($month = 1; $month <= 12; $month++) {
            $closing = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
            if ($closing->gt($latestAvailablePeriod)) {
                break;
            }

            $pairs[$closing->format('Y-m')] = [
                'previous' => $closing->copy()->subDay()->toDateString(),
                'closing' => $closing->toDateString(),
            ];
        }

        if ($pairs === []) {
            return $this->emptyTariffPayload();
        }

        $queryPeriods = collect($pairs)->flatMap(static fn (array $pair): array => array_values($pair))->unique()->values();
        $query = DB::table(self::SSA_LOAN_TABLE)
            ->whereIn('month_day_year_of_periode', $queryPeriods->all())
            ->whereRaw("UPPER(TRIM(COALESCE(segmen_dashboard, ''))) = 'SMALL'")
            ->whereBetween('kolektabilitas_one_obligor', [1, 5]);
        $this->applySsaBranchScope($query, $branchScope);

        $balances = $query
            ->selectRaw('month_day_year_of_periode AS periode')
            ->selectRaw('SUM(COALESCE(baki_debet, 0)) AS os_amount')
            ->groupBy('month_day_year_of_periode')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                substr((string) $row->periode, 0, 10) => (float) $row->os_amount / 1_000_000,
            ]);
        $realizationBalances = $this->tariffRealizationBalances($queryPeriods, $branchScope);

        $points = [];
        foreach ($pairs as $monthKey => $pair) {
            if (! $balances->has($pair['previous']) || ! $balances->has($pair['closing'])) {
                continue;
            }

            $previousOs = (float) $balances->get($pair['previous']);
            $closingOs = (float) $balances->get($pair['closing']);
            $realizationAvailable = $realizationBalances->has($pair['previous'])
                && $realizationBalances->has($pair['closing']);
            $dailyRealization = $realizationAvailable
                ? max(0.0, (float) $realizationBalances->get($pair['closing']) - (float) $realizationBalances->get($pair['previous']))
                : 0.0;
            $rawDelta = $closingOs - $previousOs;

            $points[] = [
                'month_key' => $monthKey,
                'label' => Carbon::parse($pair['closing'])->translatedFormat('M'),
                'previous_period' => $pair['previous'],
                'previous_period_label' => Carbon::parse($pair['previous'])->translatedFormat('d M Y'),
                'closing_period' => $pair['closing'],
                'closing_period_label' => Carbon::parse($pair['closing'])->translatedFormat('d M Y'),
                'previous_os' => $previousOs,
                'closing_os' => $closingOs,
                'delta_os' => $rawDelta,
                'daily_realization' => $dailyRealization,
                'daily_realization_available' => $realizationAvailable,
                'adjusted_delta_os' => $rawDelta - $dailyRealization,
            ];
        }

        $latest = collect($points)->last();

        return [
            'available' => $points !== [],
            'labels' => array_column($points, 'label'),
            'series' => [
                'previous' => array_column($points, 'previous_os'),
                'closing' => array_column($points, 'closing_os'),
                'daily_realization' => array_column($points, 'daily_realization'),
                'adjusted_delta' => array_column($points, 'adjusted_delta_os'),
            ],
            'points' => $points,
            'latest' => $latest,
            'unit' => 'Rp Juta',
            'source' => 'SSA Pinjaman',
        ];
    }

    /**
     * Realisasi pada hari closing dihitung dari selisih realisasi MTD snapshot
     * closing terhadap H-1. Ini menjaga query tetap ringan dan tidak memindai
     * tabel Daily Loan yang berukuran besar.
     *
     * @param  Collection<int, string>  $periods
     * @param  array<string, mixed>|null  $branchScope
     * @return Collection<string, float>
     */
    private function tariffRealizationBalances(Collection $periods, ?array $branchScope): Collection
    {
        if (! Schema::hasTable(self::PERFORMANCE_SNAPSHOT_TABLE)) {
            return collect();
        }

        foreach (['periode', 'cabang', 'segmen', 'realisasi_os'] as $column) {
            if (! Schema::hasColumn(self::PERFORMANCE_SNAPSHOT_TABLE, $column)) {
                return collect();
            }
        }

        $query = DB::table(self::PERFORMANCE_SNAPSHOT_TABLE)
            ->whereIn('periode', $periods->all())
            ->whereRaw("UPPER(TRIM(COALESCE(segmen, ''))) = 'SMALL'");
        $this->applySnapshotBranchScope($query, $branchScope);

        return $query
            ->selectRaw('periode')
            ->selectRaw('SUM(COALESCE(realisasi_os, 0)) AS realization_amount')
            ->groupBy('periode')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                substr((string) $row->periode, 0, 10) => (float) $row->realization_amount / 1_000_000,
            ]);
    }

    /** @param array<string, mixed>|null $branchScope */
    private function applySsaBranchScope(Builder $query, ?array $branchScope): void
    {
        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        $branches = $branch !== '' ? [$branch] : self::AREA_BRANCHES;

        $query->where(function (Builder $scope) use ($branches): void {
            foreach ($branches as $index => $branchLabel) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $scope->{$method}(
                    "UPPER(TRIM(COALESCE(nama_cabang, ''))) LIKE ?",
                    ['%'.$branchLabel.'%']
                );
            }
        });
    }

    private function ssaTariffSourceIsReady(): bool
    {
        if (! Schema::hasTable(self::SSA_LOAN_TABLE)) {
            return false;
        }

        foreach (['month_day_year_of_periode', 'nama_cabang', 'segmen_dashboard', 'baki_debet', 'kolektabilitas_one_obligor'] as $column) {
            if (! Schema::hasColumn(self::SSA_LOAN_TABLE, $column)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed>|null $branchScope */
    private function resolvePeriod(?string $requestedPeriod, ?array $branchScope, ?string $segmentScope): ?string
    {
        if ($segmentScope === 'micro' && $this->microQualitySnapshotIsReady()) {
            $snapshotQuery = DB::table(self::PERFORMANCE_SNAPSHOT_TABLE)->where('segmen', 'MICRO');
            $this->applySnapshotBranchScope($snapshotQuery, $branchScope);
            if ($requestedPeriod !== null && trim($requestedPeriod) !== '') {
                $snapshotQuery->where('periode', '<=', Carbon::parse($requestedPeriod)->toDateString());
            }

            $snapshotPeriod = $snapshotQuery->max('periode');
            $freshnessReference = $requestedPeriod !== null && trim($requestedPeriod) !== ''
                ? Carbon::parse($requestedPeriod)
                : Carbon::now();
            if (
                $snapshotPeriod !== null
                && Carbon::parse($snapshotPeriod)->isSameMonth($freshnessReference)
            ) {
                return substr((string) $snapshotPeriod, 0, 10);
            }
        }

        $query = DB::table(self::SOURCE_TABLE);
        if ($segmentScope !== null) {
            $query->where('segmen_kinerja', self::SEGMENTS[$segmentScope]);
        }
        $this->applyBranchScope($query, $branchScope);
        if ($requestedPeriod !== null && trim($requestedPeriod) !== '') {
            $query->where('periode', '<=', Carbon::parse($requestedPeriod)->toDateString());
        }

        $period = $query->orderByDesc('periode')->value('periode');

        return $period ? substr((string) $period, 0, 10) : null;
    }

    /**
     * @return Collection<int, string>
     */
    /** @param array<string, mixed>|null $branchScope */
    private function periodsForYear(
        int $year,
        string $period,
        ?array $branchScope,
        ?string $segmentScope
    ): Collection
    {
        if ($segmentScope === 'micro' && $this->microQualitySnapshotIsReady()) {
            $snapshotQuery = DB::table(self::PERFORMANCE_SNAPSHOT_TABLE)
                ->whereBetween('periode', [sprintf('%d-01-01', $year), $period])
                ->where('segmen', 'MICRO');
            $this->applySnapshotBranchScope($snapshotQuery, $branchScope);
            $snapshotPeriods = $snapshotQuery
                ->distinct()
                ->orderBy('periode')
                ->pluck('periode')
                ->map(static fn ($value): string => substr((string) $value, 0, 10))
                ->unique()
                ->values();
            if ($snapshotPeriods->isNotEmpty()) {
                return $snapshotPeriods;
            }
        }

        $query = DB::table(self::SOURCE_TABLE)
            ->whereBetween('periode', [sprintf('%d-01-01', $year), $period]);
        if ($segmentScope !== null) {
            $query->where('segmen_kinerja', self::SEGMENTS[$segmentScope]);
        }
        $this->applyBranchScope($query, $branchScope);

        return $query
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(static fn ($value): string => substr((string) $value, 0, 10))
            ->unique()
            ->values();
    }

    /** @param array<string, mixed>|null $branchScope */
    private function applyBranchScope(Builder $query, ?array $branchScope): void
    {
        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        if ($branch !== '') {
            $query->where('cabang_normalized', $branch);

            return;
        }

        $query->whereIn('cabang_normalized', self::AREA_BRANCHES);
    }

    /** @param array<string, mixed>|null $branchScope */
    private function applySnapshotBranchScope(Builder $query, ?array $branchScope): void
    {
        $branch = strtoupper(trim((string) ($branchScope['upper_label'] ?? '')));
        if ($branch !== '') {
            $query->whereRaw('UPPER(TRIM(cabang)) = ?', [$branch]);

            return;
        }

        $query->whereIn(DB::raw('UPPER(TRIM(cabang))'), self::AREA_BRANCHES);
    }

    private function microQualitySnapshotIsReady(): bool
    {
        if (! Schema::hasTable(self::PERFORMANCE_SNAPSHOT_TABLE)) {
            return false;
        }

        foreach (['periode', 'cabang', 'segmen', 'restruk_os', 'sml_os', 'npl_os'] as $column) {
            if (! Schema::hasColumn(self::PERFORMANCE_SNAPSHOT_TABLE, $column)) {
                return false;
            }
        }

        return true;
    }

    private function sourceIsReady(): bool
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return false;
        }

        foreach (['periode', 'baki_debet1', 'kolek', 'flag_restruk', 'segmen_kinerja', 'cabang_normalized'] as $column) {
            if (! Schema::hasColumn(self::SOURCE_TABLE, $column)) {
                return false;
            }
        }

        return true;
    }

    private function restructuringFrequencySourceIsReady(): bool
    {
        if (! Schema::hasTable(self::SOURCE_TABLE)) {
            return false;
        }

        foreach (['periode', 'baki_debet1', 'restruk_ke1', 'segmen_kinerja', 'cabang_normalized'] as $column) {
            if (! Schema::hasColumn(self::SOURCE_TABLE, $column)) {
                return false;
            }
        }

        return $this->restructuringDebtorIdentityExpression() !== null;
    }

    private function restructuringDebtorIdentityExpression(): ?string
    {
        $identityParts = [];
        foreach (['cifno_clean', 'cifno', 'nomor_rekening1'] as $column) {
            if (Schema::hasColumn(self::SOURCE_TABLE, $column)) {
                $identityParts[] = "NULLIF(TRIM({$column}), '')";
            }
        }

        if ($identityParts === []) {
            return null;
        }

        return count($identityParts) === 1
            ? $identityParts[0]
            : 'COALESCE('.implode(', ', $identityParts).')';
    }

    /** @return array<string, mixed> */
    private function emptyRestructuringFrequencyPayload(?string $period, ?array $branchScope): array
    {
        $periodLabel = '-';
        if ($period !== null && trim($period) !== '') {
            try {
                $periodLabel = Carbon::parse($period)->translatedFormat('d M Y');
            } catch (Throwable) {
                $periodLabel = '-';
            }
        }

        return [
            'available' => false,
            'period' => $period,
            'period_label' => $periodLabel,
            'scope_label' => $branchScope['label'] ?? 'Area 6',
            'source' => 'Daily Loan Dinamis',
            'unit' => 'Rp Juta',
            'total_debtors' => 0,
            'total_accounts' => 0,
            'total_os_juta' => 0.0,
            'buckets' => [],
            'error' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function emptyPayload(?string $period, ?array $branchScope): array
    {
        $year = $period ? Carbon::parse($period)->year : Carbon::now()->year;
        $quality = [];
        foreach (array_keys(self::SEGMENTS) as $scope) {
            $quality[$scope] = [
                'available' => false,
                'year' => $year,
                'labels' => [],
                'period_labels' => [],
                'series' => ['lr' => [], 'sml' => [], 'npl' => [], 'lar' => []],
                'points' => [],
                'unit' => 'Rp Juta',
            ];
        }

        return [
            'meta' => [
                'available' => false,
                'period' => $period,
                'period_label' => $period ? Carbon::parse($period)->translatedFormat('d M Y') : '-',
                'year' => $year,
                'scope_label' => $branchScope['label'] ?? 'Area 6',
                'source' => 'Daily Loan Dinamis',
                'error' => '',
            ],
            'quality' => $quality,
            'tariff_relief' => $this->emptyTariffPayload(),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyTariffPayload(): array
    {
        return [
            'available' => false,
            'labels' => [],
            'series' => ['previous' => [], 'closing' => []],
            'points' => [],
            'latest' => null,
            'unit' => 'Rp Juta',
            'source' => 'SSA Pinjaman',
        ];
    }
}
