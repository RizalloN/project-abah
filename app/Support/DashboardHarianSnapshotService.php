<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DashboardHarianSnapshotService
{
    public const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';
    private const LOAN_TABLE = 'daily_loan_dinamis';
    private const SAVINGS_TABLE = 'simpanan_multipn';
    private const METRIC_COLUMNS = [
        'total_simpanan',
        'simpanan_ritel',
        'giro_ritel',
        'deposito_ritel',
        'tabungan_ritel',
        'simpanan_mikro',
        'giro_mikro',
        'deposito_mikro',
        'tabungan_mikro',
        'total_casa',
        'casa_ritel',
        'casa_mikro',
        'total_os',
        'total_os_non_commercial',
        'commercial_os',
        'sme_os',
        'kecil_os',
        'kecil_non_cashcoll_os',
        'cashcoll_os',
        'medium_os',
        'consumer_os',
        'briguna_konsumer_os',
        'kpr_os',
        'kkb_os',
        'micro_os',
        'briguna_mikro_os',
        'kupedes_os',
        'kur_mikro_os',
        'kur_kecil_os',
        'kur_kpp_os',
        'micro_cashcoll_os',
        'total_sml_abs_non_commercial',
        'total_npl_abs_non_commercial',
    ];
    private const ROW_DEFINITIONS = [
        ['key' => 'total_simpanan', 'label' => '1. Total Simpanan', 'type' => 'currency', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'simpanan_ritel', 'label' => 'A. Ritel', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'giro_ritel', 'label' => 'Giro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'deposito_ritel', 'label' => 'Deposito', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'tabungan_ritel', 'label' => 'Tabungan', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'simpanan_mikro', 'label' => 'B. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'giro_mikro', 'label' => 'Giro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'deposito_mikro', 'label' => 'Deposito', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'tabungan_mikro', 'label' => 'Tabungan', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'total_os', 'label' => '2. Total OS', 'type' => 'currency', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_os_non_commercial', 'label' => 'Total OS Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'commercial_os', 'label' => 'A. Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'sme_os', 'label' => 'B. SME', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'kecil_os', 'label' => 'Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kecil_non_cashcoll_os', 'label' => 'Kecil Non Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'cashcoll_os', 'label' => 'Cashcoll', 'type' => 'currency', 'depth' => 3, 'accent' => 'muted'],
        ['key' => 'medium_os', 'label' => 'Medium', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'consumer_os', 'label' => 'C. Konsumer', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_konsumer_os', 'label' => 'Briguna', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kpr_os', 'label' => 'KPR', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kkb_os', 'label' => 'KKB', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'micro_os', 'label' => 'D. Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'section'],
        ['key' => 'briguna_mikro_os', 'label' => 'Briguna Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kupedes_os', 'label' => 'Kupedes', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_mikro_os', 'label' => 'KUR Mikro', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kecil_os', 'label' => 'KUR Kecil', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'kur_kpp_os', 'label' => 'KUR KPP', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'micro_cashcoll_os', 'label' => 'Mikro Cashcoll', 'type' => 'currency', 'depth' => 2, 'accent' => 'default'],
        ['key' => 'total_sml_pct_non_commercial', 'label' => '3. Total SML (%) Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_sml_abs_non_commercial', 'label' => 'Total SML (abs) Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'total_npl_pct_non_commercial', 'label' => '4. Total NPL (%) Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_npl_abs_non_commercial', 'label' => 'Total NPL (abs) Non Commercial', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'casa_pct', 'label' => '5. %CASA', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'total_casa', 'label' => 'Total CASA', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'casa_ritel', 'label' => 'CASA Ritel', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'casa_mikro', 'label' => 'CASA Mikro', 'type' => 'currency', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'ldr_non_commercial', 'label' => '6. Total LDR Non Commercial', 'type' => 'percent', 'depth' => 0, 'accent' => 'strong'],
        ['key' => 'ldr_ritel_non_commercial', 'label' => 'LDR Ritel Non Commercial', 'type' => 'percent', 'depth' => 1, 'accent' => 'default'],
        ['key' => 'ldr_mikro_non_commercial', 'label' => 'LDR Mikro Non Commercial', 'type' => 'percent', 'depth' => 1, 'accent' => 'default'],
    ];

    public function rebuild(?string $period = null, bool $force = false): array
    {
        $results = [];
        $periods = $this->resolveSharedPeriods($period);

        foreach ($periods as $snapshotPeriod) {
            $results[$snapshotPeriod] = $this->buildPeriodSnapshot($snapshotPeriod, $force);
        }

        if ($period === null) {
            $this->cleanupSnapshotOrphans($periods);
        }

        return $results;
    }

    public function buildPeriodSnapshot(string $period, bool $force = false): int
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE) || !Schema::hasTable(self::LOAN_TABLE) || !Schema::hasTable(self::SAVINGS_TABLE)) {
            return 0;
        }

        if (!$force) {
            $existingCount = (int) DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->count();
            if ($existingCount > 0) {
                return $existingCount;
            }
        }

        if (!$this->sourcePeriodExists(self::LOAN_TABLE, 'periode', $period) || !$this->sourcePeriodExists(self::SAVINGS_TABLE, 'posisi', $period)) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        [$payload, $sourceRowCount] = $this->buildAggregatedRowsForPeriod($period);

        if ($payload === []) {
            DB::table(self::SNAPSHOT_TABLE)->where('snapshot_period', $period)->delete();

            return 0;
        }

        foreach (array_chunk($payload, 250) as $chunk) {
            DB::table(self::SNAPSHOT_TABLE)->upsert(
                $chunk,
                ['snapshot_period', 'kanca_key', 'unit_key'],
                array_merge(['kanca_label', 'unit_label'], self::METRIC_COLUMNS, ['source_row_count', 'updated_at'])
            );
        }

        $validIds = array_column($payload, 'uniqueid_dhs');
        DB::table(self::SNAPSHOT_TABLE)
            ->where('snapshot_period', $period)
            ->whereNotIn('uniqueid_dhs', $validIds)
            ->delete();

        return count($payload);
    }

    public function fetchPeriods(): Collection
    {
        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                return DB::table(self::SNAPSHOT_TABLE)
                    ->select('snapshot_period')
                    ->distinct()
                    ->orderByDesc('snapshot_period')
                    ->pluck('snapshot_period')
                    ->map(fn ($value) => Carbon::parse($value)->toDateString())
                    ->values();
            }
        } catch (Throwable) {
            // Fall through to source intersection.
        }

        return collect($this->resolveSharedPeriods());
    }

    public function resolveEffectivePeriod(?string $requestedPeriod): ?string
    {
        $targetDate = $this->normalizeDate($requestedPeriod);

        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                $query = DB::table(self::SNAPSHOT_TABLE);

                if ($targetDate) {
                    $query->where('snapshot_period', '<=', $targetDate);
                }

                return $query->max('snapshot_period');
            }
        } catch (Throwable) {
            // Fall through to source lookup.
        }

        $periods = $this->resolveSharedPeriods($targetDate);

        return $periods[0] ?? null;
    }

    public function resolveComparisonPeriods(string $selectedPeriod, ?string $rkaPeriod = null): array
    {
        $selected = Carbon::parse($selectedPeriod);
        $resolvedRka = $this->resolveEffectivePeriod($rkaPeriod ?? $selectedPeriod);

        return [
            'current' => $selectedPeriod,
            'yoy' => $this->resolveEffectivePeriod($selected->copy()->subYearNoOverflow()->toDateString()),
            'ytd' => $this->resolveEffectivePeriod($selected->copy()->subYearNoOverflow()->endOfYear()->toDateString()),
            'mtm' => $this->resolveEffectivePeriod($selected->copy()->subMonthNoOverflow()->toDateString()),
            'mtd' => $this->resolveEffectivePeriod($selected->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()),
            'h1' => $this->resolvePreviousPeriod($selectedPeriod),
            'rka' => $resolvedRka,
            'rka_dec' => $resolvedRka ? $this->resolveEffectivePeriod(Carbon::parse($resolvedRka)->endOfYear()->toDateString()) : null,
        ];
    }

    public function fetchFilterOptions(?string $period = null): array
    {
        $effectivePeriod = $this->resolveEffectivePeriod($period);
        $periodOptions = $this->fetchPeriods()
            ->map(fn ($value) => [
                'value' => $value,
                'label' => $this->formatPeriodLabel($value),
            ])
            ->all();

        if (!$effectivePeriod) {
            return [
                'kanca' => [['value' => 'all', 'label' => 'Semua Kanca']],
                'unit_kerja' => [['value' => 'all', 'label' => 'Semua Unit Kerja']],
                'posisi_terakhir' => $periodOptions,
                'posisi_rka' => $periodOptions,
            ];
        }

        $kancas = collect();
        $units = collect();

        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE)) {
                $kancas = DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', $effectivePeriod)
                    ->select('kanca_key as value', 'kanca_label as label')
                    ->distinct()
                    ->orderBy('kanca_label')
                    ->get();

                $units = DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', $effectivePeriod)
                    ->select('unit_key as value', 'unit_label as label')
                    ->distinct()
                    ->orderBy('unit_label')
                    ->get();
            }
        } catch (Throwable) {
            $kancas = collect();
            $units = collect();
        }

        if ($kancas->isEmpty() || $units->isEmpty()) {
            [$payload] = $this->buildAggregatedRowsForPeriod($effectivePeriod);

            $kancas = collect($payload)
                ->map(fn (array $row) => ['value' => $row['kanca_key'], 'label' => $row['kanca_label']])
                ->unique('value')
                ->sortBy('label')
                ->values();

            $units = collect($payload)
                ->map(fn (array $row) => ['value' => $row['unit_key'], 'label' => $row['unit_label']])
                ->unique('value')
                ->sortBy('label')
                ->values();
        }

        return [
            'kanca' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Kanca']], $kancas->map(fn ($row) => (array) $row)->all())),
            'unit_kerja' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Unit Kerja']], $units->map(fn ($row) => (array) $row)->all())),
            'posisi_terakhir' => $periodOptions,
            'posisi_rka' => $periodOptions,
        ];
    }

    public function buildDashboardPayload(?string $selectedPeriod, ?string $rkaPeriod = null, ?string $kancaKey = null, ?string $unitKey = null): array
    {
        if (!$selectedPeriod) {
            return [
                'selected_period' => null,
                'selected_period_label' => 'Belum ada data',
                'selected_rka_period' => null,
                'selected_rka_label' => 'Belum ada data',
                'comparison_periods' => [],
                'rows' => [],
                'summary' => [
                    'source' => self::SNAPSHOT_TABLE,
                    'kanca_label' => 'Semua Kanca',
                    'unit_label' => 'Semua Unit Kerja',
                    'row_count' => 0,
                    'current_total_simpanan' => 0,
                    'current_total_os_non_commercial' => 0,
                    'current_casa_pct' => 0,
                ],
            ];
        }

        $comparisonPeriods = $this->resolveComparisonPeriods($selectedPeriod, $rkaPeriod);
        $periodKeys = array_values(array_unique(array_filter(array_values($comparisonPeriods))));
        $metricsByPeriod = $this->loadMetricsForPeriods($periodKeys, $kancaKey, $unitKey);

        $currentMetrics = $metricsByPeriod[$comparisonPeriods['current']] ?? $this->finalizeMetrics($this->emptyMetrics());
        $yoyMetrics = $comparisonPeriods['yoy'] ? ($metricsByPeriod[$comparisonPeriods['yoy']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $ytdMetrics = $comparisonPeriods['ytd'] ? ($metricsByPeriod[$comparisonPeriods['ytd']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $mtmMetrics = $comparisonPeriods['mtm'] ? ($metricsByPeriod[$comparisonPeriods['mtm']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $mtdMetrics = $comparisonPeriods['mtd'] ? ($metricsByPeriod[$comparisonPeriods['mtd']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $h1Metrics = $comparisonPeriods['h1'] ? ($metricsByPeriod[$comparisonPeriods['h1']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $rkaMetrics = $comparisonPeriods['rka'] ? ($metricsByPeriod[$comparisonPeriods['rka']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());
        $rkaDecMetrics = $comparisonPeriods['rka_dec'] ? ($metricsByPeriod[$comparisonPeriods['rka_dec']] ?? $this->finalizeMetrics($this->emptyMetrics())) : $this->finalizeMetrics($this->emptyMetrics());

        $rows = collect(self::ROW_DEFINITIONS)->map(function (array $definition) use (
            $currentMetrics,
            $yoyMetrics,
            $ytdMetrics,
            $mtmMetrics,
            $mtdMetrics,
            $h1Metrics,
            $rkaMetrics,
            $rkaDecMetrics
        ) {
            $metricKey = $definition['key'];

            return [
                'key' => $metricKey,
                'label' => $definition['label'],
                'type' => $definition['type'],
                'depth' => $definition['depth'],
                'accent' => $definition['accent'],
                'values' => [
                    'yoy' => (float) ($yoyMetrics[$metricKey] ?? 0),
                    'ytd' => (float) ($ytdMetrics[$metricKey] ?? 0),
                    'mtm' => (float) ($mtmMetrics[$metricKey] ?? 0),
                    'mtd' => (float) ($mtdMetrics[$metricKey] ?? 0),
                    'h1' => (float) ($h1Metrics[$metricKey] ?? 0),
                    'current' => (float) ($currentMetrics[$metricKey] ?? 0),
                    'rka' => (float) ($rkaMetrics[$metricKey] ?? 0),
                    'rka_dec' => (float) ($rkaDecMetrics[$metricKey] ?? 0),
                ],
                'deltas' => [
                    'yoy' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($yoyMetrics[$metricKey] ?? 0),
                    'ytd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($ytdMetrics[$metricKey] ?? 0),
                    'dtd' => (float) ($currentMetrics[$metricKey] ?? 0) - (float) ($h1Metrics[$metricKey] ?? 0),
                ],
            ];
        })->values()->all();

        $source = collect(array_keys($metricsByPeriod))->count() === count($periodKeys) && Schema::hasTable(self::SNAPSHOT_TABLE)
            ? self::SNAPSHOT_TABLE
            : 'source_fallback';

        return [
            'selected_period' => $selectedPeriod,
            'selected_period_label' => $this->formatPeriodLabel($selectedPeriod),
            'selected_rka_period' => $comparisonPeriods['rka'],
            'selected_rka_label' => $this->formatPeriodLabel($comparisonPeriods['rka']),
            'comparison_periods' => [
                'yoy' => ['period' => $comparisonPeriods['yoy'], 'label' => $this->formatPeriodLabel($comparisonPeriods['yoy'])],
                'ytd' => ['period' => $comparisonPeriods['ytd'], 'label' => $this->formatPeriodLabel($comparisonPeriods['ytd'])],
                'mtm' => ['period' => $comparisonPeriods['mtm'], 'label' => $this->formatPeriodLabel($comparisonPeriods['mtm'])],
                'mtd' => ['period' => $comparisonPeriods['mtd'], 'label' => $this->formatPeriodLabel($comparisonPeriods['mtd'])],
                'h1' => ['period' => $comparisonPeriods['h1'], 'label' => $this->formatPeriodLabel($comparisonPeriods['h1'])],
                'rka' => ['period' => $comparisonPeriods['rka'], 'label' => $this->formatPeriodLabel($comparisonPeriods['rka'])],
                'rka_dec' => ['period' => $comparisonPeriods['rka_dec'], 'label' => $this->formatPeriodLabel($comparisonPeriods['rka_dec'])],
            ],
            'rows' => $rows,
            'summary' => [
                'source' => $source,
                'kanca_label' => $this->displayFilterLabel($kancaKey, 'Semua Kanca', $selectedPeriod, 'kanca'),
                'unit_label' => $this->displayFilterLabel($unitKey, 'Semua Unit Kerja', $selectedPeriod, 'unit_kerja'),
                'row_count' => count($rows),
                'current_total_simpanan' => (float) ($currentMetrics['total_simpanan'] ?? 0),
                'current_total_os_non_commercial' => (float) ($currentMetrics['total_os_non_commercial'] ?? 0),
                'current_casa_pct' => (float) ($currentMetrics['casa_pct'] ?? 0),
            ],
        ];
    }

    private function loadMetricsForPeriods(array $periods, ?string $kancaKey, ?string $unitKey): array
    {
        $normalizedPeriods = array_values(array_unique(array_filter(array_map([$this, 'normalizeDate'], $periods))));
        if ($normalizedPeriods === []) {
            return [];
        }

        $metricsByPeriod = [];
        $snapshotPeriods = [];

        if (Schema::hasTable(self::SNAPSHOT_TABLE)) {
            $selects = collect(self::METRIC_COLUMNS)
                ->map(fn (string $column) => "COALESCE(SUM({$column}), 0) as {$column}")
                ->implode(",\n");

            $query = DB::table(self::SNAPSHOT_TABLE)
                ->whereIn('snapshot_period', $normalizedPeriods)
                ->groupBy('snapshot_period')
                ->orderBy('snapshot_period')
                ->selectRaw('snapshot_period')
                ->selectRaw($selects)
                ->selectRaw('MAX(source_row_count) as source_row_count');

            $this->applySnapshotFilter($query, 'kanca_key', $kancaKey);
            $this->applySnapshotFilter($query, 'unit_key', $unitKey);

            foreach ($query->get() as $row) {
                $snapshotPeriods[] = $row->snapshot_period;
                $metricsByPeriod[$row->snapshot_period] = $this->finalizeMetrics((array) $row);
            }
        }

        $missingPeriods = array_values(array_diff($normalizedPeriods, $snapshotPeriods));

        foreach ($missingPeriods as $missingPeriod) {
            $metricsByPeriod[$missingPeriod] = $this->buildMetricsFromSource($missingPeriod, $kancaKey, $unitKey);
        }

        return $metricsByPeriod;
    }

    private function buildMetricsFromSource(string $period, ?string $kancaKey, ?string $unitKey): array
    {
        [$payload] = $this->buildAggregatedRowsForPeriod($period);
        $metrics = $this->emptyMetrics();

        foreach ($payload as $row) {
            if ($this->normalizeFilterValue($kancaKey) !== null && $row['kanca_key'] !== $this->normalizeFilterValue($kancaKey)) {
                continue;
            }

            if ($this->normalizeFilterValue($unitKey) !== null && $row['unit_key'] !== $this->normalizeFilterValue($unitKey)) {
                continue;
            }

            $this->accumulateMetrics($metrics, $row);
        }

        return $this->finalizeMetrics($metrics);
    }

    private function buildAggregatedRowsForPeriod(string $period): array
    {
        $buckets = [];
        $sourceRowCount = 0;

        foreach ($this->fetchSavingsAggregates($period) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_kantor_cabang ?? $row->raw_unit_kerja ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $unitLabel = $this->normalizeUnitLabel($row->raw_unit_kerja ?? null, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel);

            $buckets[$bucketKey]['giro_ritel'] += (float) ($row->giro_ritel ?? 0);
            $buckets[$bucketKey]['deposito_ritel'] += (float) ($row->deposito_ritel ?? 0);
            $buckets[$bucketKey]['tabungan_ritel'] += (float) ($row->tabungan_ritel ?? 0);
            $buckets[$bucketKey]['giro_mikro'] += (float) ($row->giro_mikro ?? 0);
            $buckets[$bucketKey]['deposito_mikro'] += (float) ($row->deposito_mikro ?? 0);
            $buckets[$bucketKey]['tabungan_mikro'] += (float) ($row->tabungan_mikro ?? 0);
            $sourceRowCount++;
        }

        foreach ($this->fetchLoanAggregates($period) as $row) {
            $kancaLabel = $this->normalizeKancaLabel($row->raw_cabang ?? $row->raw_unit ?? null);
            if ($kancaLabel === '') {
                continue;
            }

            $unitLabel = $this->normalizeUnitLabel($row->raw_unit ?? null, $kancaLabel);
            $bucketKey = $this->makeBucketKey($kancaLabel, $unitLabel);
            $this->initializeBucket($buckets, $bucketKey, $kancaLabel, $unitLabel);

            $buckets[$bucketKey]['commercial_os'] += (float) ($row->commercial_os ?? 0);
            $buckets[$bucketKey]['sme_os'] += (float) ($row->sme_os ?? 0);
            $buckets[$bucketKey]['kecil_os'] += (float) ($row->kecil_os ?? 0);
            $buckets[$bucketKey]['kecil_non_cashcoll_os'] += (float) ($row->kecil_non_cashcoll_os ?? 0);
            $buckets[$bucketKey]['cashcoll_os'] += (float) ($row->cashcoll_os ?? 0);
            $buckets[$bucketKey]['medium_os'] += (float) ($row->medium_os ?? 0);
            $buckets[$bucketKey]['consumer_os'] += (float) ($row->consumer_os ?? 0);
            $buckets[$bucketKey]['briguna_konsumer_os'] += (float) ($row->briguna_konsumer_os ?? 0);
            $buckets[$bucketKey]['kpr_os'] += (float) ($row->kpr_os ?? 0);
            $buckets[$bucketKey]['kkb_os'] += (float) ($row->kkb_os ?? 0);
            $buckets[$bucketKey]['micro_os'] += (float) ($row->micro_os ?? 0);
            $buckets[$bucketKey]['briguna_mikro_os'] += (float) ($row->briguna_mikro_os ?? 0);
            $buckets[$bucketKey]['kupedes_os'] += (float) ($row->kupedes_os ?? 0);
            $buckets[$bucketKey]['kur_mikro_os'] += (float) ($row->kur_mikro_os ?? 0);
            $buckets[$bucketKey]['kur_kecil_os'] += (float) ($row->kur_kecil_os ?? 0);
            $buckets[$bucketKey]['kur_kpp_os'] += (float) ($row->kur_kpp_os ?? 0);
            $buckets[$bucketKey]['micro_cashcoll_os'] += (float) ($row->micro_cashcoll_os ?? 0);
            $buckets[$bucketKey]['total_sml_abs_non_commercial'] += (float) ($row->total_sml_abs_non_commercial ?? 0);
            $buckets[$bucketKey]['total_npl_abs_non_commercial'] += (float) ($row->total_npl_abs_non_commercial ?? 0);
            $sourceRowCount++;
        }

        $payload = [];
        foreach ($buckets as $row) {
            $metrics = $this->finalizeMetrics($row);
            $payload[] = array_merge(
                [
                    'uniqueid_dhs' => md5(implode('|', ['dhs', $period, $row['kanca_key'], $row['unit_key']])),
                    'snapshot_period' => $period,
                    'kanca_key' => $row['kanca_key'],
                    'kanca_label' => $row['kanca_label'],
                    'unit_key' => $row['unit_key'],
                    'unit_label' => $row['unit_label'],
                ],
                collect(self::METRIC_COLUMNS)->mapWithKeys(fn (string $metric) => [$metric => (float) ($metrics[$metric] ?? 0)])->all(),
                [
                    'source_row_count' => $sourceRowCount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return [$payload, $sourceRowCount];
    }

    private function fetchSavingsAggregates(string $period): Collection
    {
        $unit = "UPPER(TRIM(COALESCE(unit_kerja, '')))";
        $type = "UPPER(TRIM(COALESCE(jenis_simpanan, '')))";
        $retail = "({$unit} LIKE '%KC%' OR {$unit} LIKE '%KCP%')";
        $micro = "{$unit} LIKE '%UNIT%'";

        return DB::table(self::SAVINGS_TABLE)
            ->where('posisi', $period)
            ->selectRaw("TRIM(COALESCE(kantor_cabang, '')) as raw_kantor_cabang")
            ->selectRaw("TRIM(COALESCE(unit_kerja, '')) as raw_unit_kerja")
            ->selectRaw("SUM(CASE WHEN {$retail} AND {$type} LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as giro_ritel")
            ->selectRaw("SUM(CASE WHEN {$retail} AND {$type} LIKE 'DEPOSITO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as deposito_ritel")
            ->selectRaw("SUM(CASE WHEN {$retail} AND {$type} LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as tabungan_ritel")
            ->selectRaw("SUM(CASE WHEN {$micro} AND {$type} LIKE 'GIRO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as giro_mikro")
            ->selectRaw("SUM(CASE WHEN {$micro} AND {$type} LIKE 'DEPOSITO%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as deposito_mikro")
            ->selectRaw("SUM(CASE WHEN {$micro} AND {$type} LIKE 'TABUNGAN%' THEN COALESCE(saldo_idr, 0) ELSE 0 END) as tabungan_mikro")
            ->groupBy('raw_kantor_cabang', 'raw_unit_kerja')
            ->get();
    }

    private function fetchLoanAggregates(string $period): Collection
    {
        $segment = "LOWER(TRIM(COALESCE(segmen_dashboard, '')))";
        $product = "LOWER(TRIM(COALESCE(produk_dashboard, '')))";
        $description = "LOWER(TRIM(COALESCE(description, '')))";
        $balance = 'COALESCE(baki_debet1, 0)';
        $kol = "CAST(NULLIF(TRIM(COALESCE(kol_adk1, '')), '') AS UNSIGNED)";

        return DB::table(self::LOAN_TABLE)
            ->where('periode', $period)
            ->selectRaw("TRIM(COALESCE(cabang1, '')) as raw_cabang")
            ->selectRaw("TRIM(COALESCE(unit1, '')) as raw_unit")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'commercial' THEN {$balance} ELSE 0 END) as commercial_os")
            ->selectRaw("SUM(CASE WHEN {$segment} IN ('small', 'medium') THEN {$balance} ELSE 0 END) as sme_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'small' THEN {$balance} ELSE 0 END) as kecil_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'small' AND {$product} = 'commercial' THEN {$balance} ELSE 0 END) as kecil_non_cashcoll_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'small' AND {$product} = 'cashcall' THEN {$balance} ELSE 0 END) as cashcoll_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'medium' AND {$product} = 'medium' THEN {$balance} ELSE 0 END) as medium_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'consumer' THEN {$balance} ELSE 0 END) as consumer_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'consumer' AND {$product} = 'briguna-konsumer' THEN {$balance} ELSE 0 END) as briguna_konsumer_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'consumer' AND {$product} = 'kpr' THEN {$balance} ELSE 0 END) as kpr_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'consumer' AND {$product} = 'kkb' THEN {$balance} ELSE 0 END) as kkb_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' THEN {$balance} ELSE 0 END) as micro_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'briguna-mikro' THEN {$balance} ELSE 0 END) as briguna_mikro_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'kupedes' THEN {$balance} ELSE 0 END) as kupedes_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'kur-mikro' AND {$description} = 'kur mikro baru' THEN {$balance} ELSE 0 END) as kur_mikro_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'kur-mikro' AND {$description} LIKE 'kredit mikro - kur ritel 2015%' THEN {$balance} ELSE 0 END) as kur_kecil_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'kpr' THEN {$balance} ELSE 0 END) as kur_kpp_os")
            ->selectRaw("SUM(CASE WHEN {$segment} = 'micro' AND {$product} = 'cash collateral' THEN {$balance} ELSE 0 END) as micro_cashcoll_os")
            ->selectRaw("SUM(CASE WHEN {$segment} <> 'commercial' AND {$kol} = 2 THEN {$balance} ELSE 0 END) as total_sml_abs_non_commercial")
            ->selectRaw("SUM(CASE WHEN {$segment} <> 'commercial' AND {$kol} > 2 AND {$kol} <= 5 THEN {$balance} ELSE 0 END) as total_npl_abs_non_commercial")
            ->groupBy('raw_cabang', 'raw_unit')
            ->get();
    }

    private function finalizeMetrics(array $metrics): array
    {
        $final = $this->emptyMetrics();

        foreach (self::METRIC_COLUMNS as $column) {
            $final[$column] = (float) ($metrics[$column] ?? 0);
        }

        $final['simpanan_ritel'] = $final['giro_ritel'] + $final['deposito_ritel'] + $final['tabungan_ritel'];
        $final['simpanan_mikro'] = $final['giro_mikro'] + $final['deposito_mikro'] + $final['tabungan_mikro'];
        $final['total_simpanan'] = $final['simpanan_ritel'] + $final['simpanan_mikro'];
        $final['casa_ritel'] = $final['giro_ritel'] + $final['tabungan_ritel'];
        $final['casa_mikro'] = $final['giro_mikro'] + $final['tabungan_mikro'];
        $final['total_casa'] = $final['casa_ritel'] + $final['casa_mikro'];
        $final['total_os'] = $final['commercial_os'] + $final['sme_os'] + $final['consumer_os'] + $final['micro_os'];
        $final['total_os_non_commercial'] = $final['sme_os'] + $final['consumer_os'] + $final['micro_os'];
        $final['ldr_non_commercial'] = $this->safePercent($final['total_os_non_commercial'], $final['total_simpanan']);
        $final['ldr_ritel_non_commercial'] = $this->safePercent($final['sme_os'] + $final['consumer_os'], $final['simpanan_ritel']);
        $final['ldr_mikro_non_commercial'] = $this->safePercent($final['micro_os'], $final['simpanan_mikro']);
        $final['casa_pct'] = $this->safePercent($final['total_casa'], $final['total_simpanan']);
        $final['total_sml_pct_non_commercial'] = $this->safePercent($final['total_sml_abs_non_commercial'], $final['total_os_non_commercial']);
        $final['total_npl_pct_non_commercial'] = $this->safePercent($final['total_npl_abs_non_commercial'], $final['total_os_non_commercial']);

        return $final;
    }

    private function emptyMetrics(): array
    {
        $metrics = [
            'kanca_key' => '',
            'kanca_label' => '',
            'unit_key' => '',
            'unit_label' => '',
            'source_row_count' => 0,
            'casa_pct' => 0.0,
            'total_sml_pct_non_commercial' => 0.0,
            'total_npl_pct_non_commercial' => 0.0,
            'ldr_non_commercial' => 0.0,
            'ldr_ritel_non_commercial' => 0.0,
            'ldr_mikro_non_commercial' => 0.0,
        ];

        foreach (self::METRIC_COLUMNS as $column) {
            $metrics[$column] = 0.0;
        }

        return $metrics;
    }

    private function accumulateMetrics(array &$target, array $source): void
    {
        foreach (self::METRIC_COLUMNS as $column) {
            $target[$column] = (float) ($target[$column] ?? 0) + (float) ($source[$column] ?? 0);
        }

        $target['source_row_count'] = (int) ($target['source_row_count'] ?? 0) + (int) ($source['source_row_count'] ?? 0);
    }

    private function initializeBucket(array &$buckets, string $bucketKey, string $kancaLabel, string $unitLabel): void
    {
        if (isset($buckets[$bucketKey])) {
            return;
        }

        $buckets[$bucketKey] = $this->emptyMetrics();
        $buckets[$bucketKey]['kanca_key'] = $this->slugKey($kancaLabel);
        $buckets[$bucketKey]['kanca_label'] = $kancaLabel;
        $buckets[$bucketKey]['unit_key'] = $this->slugKey($unitLabel);
        $buckets[$bucketKey]['unit_label'] = $unitLabel;
    }

    private function makeBucketKey(string $kancaLabel, string $unitLabel): string
    {
        return $this->slugKey($kancaLabel) . '|' . $this->slugKey($unitLabel);
    }

    private function slugKey(string $value): string
    {
        return Str::slug(trim($value), '-');
    }

    private function normalizeKancaLabel($value): string
    {
        $normalized = strtoupper($this->cleanBranchValue((string) $value));

        if ($normalized === '') {
            return '';
        }

        foreach (['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'] as $branchName) {
            if (str_contains($normalized, $branchName)) {
                return 'KC ' . Str::title(Str::lower($branchName));
            }
        }

        if (preg_match('/\bKC[P]?\b/', $normalized) === 1) {
            return Str::title(Str::lower($normalized));
        }

        return '';
    }

    private function normalizeUnitLabel($value, string $fallbackKanca): string
    {
        $clean = $this->cleanBranchValue((string) $value);

        if ($clean === '') {
            return $fallbackKanca;
        }

        $upper = strtoupper($clean);

        if (str_contains($upper, 'KC ') || str_contains($upper, 'KCP ')) {
            $kanca = $this->normalizeKancaLabel($clean);

            return $kanca !== '' ? $kanca : Str::title(Str::lower($clean));
        }

        if (str_contains($upper, 'UNIT ')) {
            $suffix = trim(substr($clean, stripos($upper, 'UNIT ') + 5));

            return 'UNIT ' . Str::title(Str::lower($suffix));
        }

        return Str::title(Str::lower($clean));
    }

    private function cleanBranchValue(string $value): string
    {
        $clean = trim($value);
        $clean = ltrim($clean, "'");
        $clean = preg_replace('/^\d+\s*--\s*/', '', $clean) ?? $clean;
        $clean = preg_replace('/\(.+\)$/', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    private function resolveSharedPeriods(?string $targetDate = null): array
    {
        $query = DB::table(self::LOAN_TABLE . ' as loan')
            ->select('loan.periode')
            ->whereNotNull('loan.periode')
            ->whereExists(function ($builder) {
                $builder->selectRaw('1')
                    ->from(self::SAVINGS_TABLE . ' as savings')
                    ->whereColumn('savings.posisi', 'loan.periode');
            });

        if ($targetDate) {
            $resolved = $query
                ->where('loan.periode', '<=', $this->normalizeDate($targetDate))
                ->orderByDesc('loan.periode')
                ->value('loan.periode');

            return $resolved ? [Carbon::parse($resolved)->toDateString()] : [];
        }

        return $query
            ->distinct()
            ->orderByDesc('loan.periode')
            ->pluck('loan.periode')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->values()
            ->all();
    }

    private function cleanupSnapshotOrphans(array $validPeriods): void
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return;
        }

        $query = DB::table(self::SNAPSHOT_TABLE);

        if ($validPeriods !== []) {
            $query->whereNotIn('snapshot_period', $validPeriods)->delete();

            return;
        }

        $query->delete();
    }

    private function resolvePreviousPeriod(string $period): ?string
    {
        try {
            if (Schema::hasTable(self::SNAPSHOT_TABLE) && DB::table(self::SNAPSHOT_TABLE)->exists()) {
                return DB::table(self::SNAPSHOT_TABLE)
                    ->where('snapshot_period', '<', $period)
                    ->max('snapshot_period');
            }
        } catch (Throwable) {
            // Fall through to shared periods.
        }

        return $this->resolveEffectivePeriod(Carbon::parse($period)->subDay()->toDateString());
    }

    private function sourcePeriodExists(string $table, string $column, string $period): bool
    {
        return DB::table($table)->where($column, $period)->exists();
    }

    private function normalizeDate(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return 'Belum ada data';
        }

        return Carbon::parse($period)->translatedFormat('d M Y');
    }

    private function applySnapshotFilter($query, string $column, ?string $value): void
    {
        $normalized = $this->normalizeFilterValue($value);

        if ($normalized !== null) {
            $query->where($column, $normalized);
        }
    }

    private function normalizeFilterValue(?string $value): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === 'all') {
            return null;
        }

        return $normalized;
    }

    private function displayFilterLabel(?string $value, string $fallback, string $period, string $group): string
    {
        $normalized = $this->normalizeFilterValue($value);
        if ($normalized === null) {
            return $fallback;
        }

        $options = $this->fetchFilterOptions($period)[$group] ?? [];
        foreach ($options as $option) {
            if ((string) ($option['value'] ?? '') === $normalized) {
                return (string) ($option['label'] ?? $fallback);
            }
        }

        return $fallback;
    }

    private function safePercent(float $value, float $base): float
    {
        if ($base == 0.0) {
            return 0.0;
        }

        return ($value / $base) * 100;
    }
}
