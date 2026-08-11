<?php

namespace App\Services\Presentation;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PresentationDeckDataService
{
    private const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';

    private const BRANCHES = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function build(array $payload, array $options): array
    {
        $period = Carbon::parse((string) data_get($payload, 'meta.period'))->toDateString();
        $periods = $this->comparisonPeriods($period);
        $definitions = $this->metricDefinitions();
        $snapshot = $this->loadSnapshotMetrics(array_values($periods), $definitions);

        $globalScope = $this->normaliseScope((string) ($options['global_scope'] ?? $options['funding_scope'] ?? 'area6'));
        $scopeLabel = $globalScope === 'area6' ? 'Area 6 Konsolidasi' : $globalScope;
        $fundingScope = $globalScope;
        $smeScope = $globalScope;
        $consumerScope = $globalScope;
        $payloadScopeKey = $this->payloadScopeKey($globalScope);
        $fundingStructureScopes = (array) data_get($payload, 'funding_structure.scopes', []);
        $creditStructureScopes = (array) data_get($payload, 'credit_structure.scopes', []);
        $usePrognosa = filter_var($options['use_prognosa'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $comparisonScope = (array) data_get($payload, ['comparison', 'scopes', $payloadScopeKey], []);
        $prognosaMeta = (array) data_get($payload, 'comparison.prognosa', []);

        return [
            'meta' => [
                'title' => trim((string) ($options['title'] ?? 'Performance Review - Area 6 Region 13')),
                'subtitle' => 'Performance Review ' . $scopeLabel . ' - Region 13',
                'scope' => $globalScope,
                'scope_label' => $scopeLabel,
                'period' => $period,
                'period_label' => Carbon::parse($period)->translatedFormat('d F Y'),
                'generated_at' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y H:i'),
                'source_note' => 'Sumber: snapshot dashboard, RKA, Market Share Area 6, Mapping REKAP/DASHBOARD, dan report digital terbaru.',
                'template' => public_path('BRI_Presentation Template.pptx'),
                'bri_logo' => public_path('images/bri-logo-template.png'),
                'danantara_logo' => public_path('images/danantara-logo-template.png'),
                'use_prognosa' => $usePrognosa && (bool) ($prognosaMeta['available'] ?? false),
                'prognosa' => $prognosaMeta,
            ],
            'periods' => collect($periods)->map(fn (string $date, string $key): array => [
                'key' => $key,
                'date' => $date,
                'label' => strtoupper($key) . ' ' . Carbon::parse($date)->translatedFormat('d M y'),
            ])->values()->all(),
            'agenda' => [
                'Market Share Area 6',
                'Mapping Potensi dan Penetrasi',
                'Summary Funding Area atau Cabang',
                'Funding per Produk dan Timeseries',
                'Rangkuman 8 Strategi Funding',
                'Outstanding Summary',
                'Pinjaman SME dan Kuadran RM',
                'Pinjaman Konsumer dan Kuadran RM',
                'Highlight Kinerja Mikro',
                'Kualitas SML',
                'Kualitas NPL',
                'Timeseries Kinerja Terintegrasi',
                'Prioritas Aksi Berikutnya',
            ],
            'marketshare' => $this->buildMarketShare(
                (array) data_get($payload, 'marketshare', []),
                $globalScope,
                $scopeLabel
            ),
            'structured' => [
                'funding' => (array) ($fundingStructureScopes[$payloadScopeKey]
                    ?? $fundingStructureScopes[$globalScope]
                    ?? $fundingStructureScopes['area6']
                    ?? []),
                'credit' => (array) ($creditStructureScopes[$payloadScopeKey]
                    ?? $creditStructureScopes[$globalScope]
                    ?? $creditStructureScopes['area6']
                    ?? []),
            ],
            'comparison' => [
                'periods' => (array) data_get($payload, 'comparison.periods', []),
                'scope' => $comparisonScope,
                'prognosa' => $prognosaMeta,
            ],
            'funding' => $this->buildSection(
                'funding',
                'Performance Funding / Dana',
                'value',
                $fundingScope,
                (string) ($options['funding_product'] ?? 'total'),
                $periods,
                $snapshot,
                $definitions['funding'],
                $payload
            ),
            'sme' => $this->buildSection(
                'sme',
                'Performance SME',
                'os',
                $smeScope,
                (string) ($options['sme_product'] ?? 'total'),
                $periods,
                $snapshot,
                $definitions['sme'],
                $payload
            ),
            'consumer' => $this->buildSection(
                'consumer',
                'Performance Konsumer',
                'os',
                $consumerScope,
                (string) ($options['consumer_product'] ?? 'total'),
                $periods,
                $snapshot,
                $definitions['consumer'],
                $payload
            ),
            'productivity' => $this->buildProductivity($payload, $globalScope, $scopeLabel),
            'kts' => (array) data_get($payload, 'kts', []),
            'trend_groups' => $this->buildTrendGroups($payload, $globalScope, $scopeLabel),
            'strategies' => $this->buildStrategies((array) data_get($payload, 'digital_strategy.cards', [])),
            'funding_strategies' => $this->buildFundingStrategies(
                (array) data_get($payload, 'digital_strategy', []),
                $globalScope,
                $scopeLabel
            ),
        ];
    }

    /**
     * @param array<string, string> $periods
     * @param array<string, mixed> $snapshot
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildSection(
        string $key,
        string $title,
        string $primaryMetric,
        string $scope,
        string $selectedProduct,
        array $periods,
        array $snapshot,
        array $definitions,
        array $payload
    ): array {
        $selectedProduct = isset($definitions[$selectedProduct]) ? $selectedProduct : 'total';
        $overview = [];

        $overviewScopes = $scope === 'area6' ? array_merge(['area6'], self::BRANCHES) : [$scope];
        foreach ($overviewScopes as $rowScope) {
            $rka = $this->resolveRka($payload, $rowScope, $key, 'total', $primaryMetric);
            $overview[] = $this->performanceRow(
                $rowScope === 'area6' ? 'AREA 6' : $rowScope,
                $snapshot,
                $rowScope,
                $key,
                'total',
                $primaryMetric,
                $periods,
                $rka
            );
        }

        $products = [];
        foreach ($definitions as $productKey => $definition) {
            $products[] = array_merge(
                $this->performanceRow(
                    (string) $definition['label'],
                    $snapshot,
                    $scope,
                    $key,
                    $productKey,
                    $primaryMetric,
                    $periods,
                    $this->resolveRka($payload, $scope, $key, $productKey, $primaryMetric)
                ),
                [
                    'sml' => $key === 'funding' ? null : $this->qualitySummary($snapshot, $scope, $key, $productKey, 'sml', $periods),
                    'npl' => $key === 'funding' ? null : $this->qualitySummary($snapshot, $scope, $key, $productKey, 'npl', $periods),
                ]
            );
        }

        return [
            'key' => $key,
            'title' => $title,
            'primary_metric' => $primaryMetric,
            'scope' => $scope,
            'scope_label' => $scope === 'area6' ? 'Area 6 Konsolidasi' : $scope,
            'selected_product' => $selectedProduct,
            'selected_product_label' => (string) $definitions[$selectedProduct]['label'],
            'overview_rows' => $overview,
            'product_rows' => $products,
            'timeseries' => $this->buildTimeseries($scope, $key, $selectedProduct, $definitions[$selectedProduct]),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<string, string> $periods
     * @return array<string, mixed>
     */
    private function performanceRow(
        string $label,
        array $snapshot,
        string $scope,
        string $section,
        string $product,
        string $metric,
        array $periods,
        ?float $rka
    ): array {
        $current = $this->snapshotValue($snapshot, $periods['current'], $scope, $section, $product, $metric);
        $deltas = [];
        foreach (['yoy', 'ytd', 'mom', 'mtd', 'dtd'] as $key) {
            $base = $this->snapshotValue($snapshot, $periods[$key], $scope, $section, $product, $metric);
            $deltas[$key] = $base === null || $current === null ? null : $current - $base;
        }

        return [
            'label' => $label,
            'current' => $current,
            'share' => $this->shareOfArea($snapshot, $periods['current'], $scope, $section, $product, $metric),
            'deltas' => $deltas,
            'rka' => $rka,
            'gap' => $current !== null && $rka !== null && $rka >= 0 ? $current - $rka : null,
            'achievement' => $current !== null && $rka !== null && $rka > 0 ? ($current / $rka) * 100 : null,
        ];
    }

    /** @return array<string, mixed> */
    private function qualitySummary(array $snapshot, string $scope, string $section, string $product, string $metric, array $periods): array
    {
        $current = $this->snapshotValue($snapshot, $periods['current'], $scope, $section, $product, $metric);
        $deltas = [];
        foreach (['yoy', 'ytd', 'mom', 'mtd', 'dtd'] as $key) {
            $base = $this->snapshotValue($snapshot, $periods[$key], $scope, $section, $product, $metric);
            $deltas[$key] = $base === null || $current === null ? null : $current - $base;
        }

        $os = $this->snapshotValue($snapshot, $periods['current'], $scope, $section, $product, 'os');

        return [
            'current' => $current,
            'ratio' => $current !== null && $os ? ($current / $os) * 100 : null,
            'deltas' => $deltas,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @return array<string, mixed>
     */
    private function loadSnapshotMetrics(array $periods, array $definitions): array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return [];
        }

        $flat = [];
        foreach ($definitions as $section => $products) {
            foreach ($products as $product => $definition) {
                foreach ((array) $definition['expressions'] as $metric => $expression) {
                    $flat[$section . '__' . $product . '__' . $metric] = $expression;
                }
            }
        }

        $query = $this->summarySnapshotQuery()
            ->whereIn('snapshot_period', array_values(array_unique($periods)))
            ->selectRaw('snapshot_period')
            ->selectRaw("COALESCE(kanca_label, '') as branch_label");

        foreach ($flat as $alias => $expression) {
            $query->selectRaw("COALESCE(SUM({$expression}), 0) as `{$alias}`");
        }

        $rows = $query
            ->groupBy('snapshot_period', 'kanca_label')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->snapshot_period)->toDateString();
            $branch = trim((string) ($row->branch_label ?? ''));
            foreach ($flat as $alias => $_expression) {
                [$section, $product, $metric] = explode('__', $alias);
                $result[$date][$branch][$section][$product][$metric] = (float) ($row->{$alias} ?? 0.0);
                $result[$date]['area6'][$section][$product][$metric] =
                    (float) ($result[$date]['area6'][$section][$product][$metric] ?? 0.0)
                    + (float) ($row->{$alias} ?? 0.0);
            }
        }

        return $result;
    }

    /** @return array<string, string> */
    private function comparisonPeriods(string $current): array
    {
        return [
            'yoy' => $this->resolvePeriodOnOrBefore(Carbon::parse($current)->subYear()->toDateString()),
            'ytd' => $this->resolvePeriodOnOrBefore(Carbon::parse($current)->subYear()->endOfYear()->toDateString()),
            'mom' => $this->resolvePeriodOnOrBefore(Carbon::parse($current)->subMonthNoOverflow()->toDateString()),
            'mtd' => $this->resolvePeriodOnOrBefore(Carbon::parse($current)->subMonthNoOverflow()->endOfMonth()->toDateString()),
            'dtd' => $this->resolvePeriodBefore($current),
            'current' => $current,
        ];
    }

    private function resolvePeriodOnOrBefore(string $date): string
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return $date;
        }

        return (string) ($this->summarySnapshotQuery()
            ->where('snapshot_period', '<=', $date)
            ->orderByDesc('snapshot_period')
            ->value('snapshot_period') ?: $date);
    }

    private function resolvePeriodBefore(string $date): string
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return Carbon::parse($date)->subDay()->toDateString();
        }

        return (string) ($this->summarySnapshotQuery()
            ->where('snapshot_period', '<', $date)
            ->orderByDesc('snapshot_period')
            ->value('snapshot_period') ?: Carbon::parse($date)->subDay()->toDateString());
    }

    private function summarySnapshotQuery(): Builder
    {
        $query = DB::table(self::SNAPSHOT_TABLE)
            ->whereIn(DB::raw('UPPER(TRIM(kanca_label))'), array_map('strtoupper', self::BRANCHES));

        if (Schema::hasColumn(self::SNAPSHOT_TABLE, 'kanca_key') && Schema::hasColumn(self::SNAPSHOT_TABLE, 'unit_key')) {
            $query->whereColumn('kanca_key', 'unit_key');
        } elseif (Schema::hasColumn(self::SNAPSHOT_TABLE, 'scope')) {
            $query->where('scope', 'branch');
        }

        return $query;
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function metricDefinitions(): array
    {
        $funding = [
            'total' => ['label' => 'Total Simpanan', 'expressions' => ['value' => 'COALESCE(total_simpanan, 0)']],
            'giro' => ['label' => 'Giro', 'expressions' => ['value' => 'COALESCE(giro_ritel, 0) + COALESCE(giro_mikro, 0) + COALESCE(giro_wholesale, 0)']],
            'tabungan' => ['label' => 'Tabungan', 'expressions' => ['value' => 'COALESCE(tabungan_ritel, 0) + COALESCE(tabungan_mikro, 0) + COALESCE(tabungan_wholesale, 0)']],
            'deposito' => ['label' => 'Deposito', 'expressions' => ['value' => 'COALESCE(deposito_ritel, 0) + COALESCE(deposito_mikro, 0) + COALESCE(deposito_wholesale, 0)']],
            'casa' => ['label' => 'CASA', 'expressions' => ['value' => 'COALESCE(total_casa, 0)']],
        ];

        $sme = [
            'total' => ['label' => 'Total SME', 'expressions' => $this->segmentExpressions('sme')],
            'non_cashcoll' => ['label' => 'Kredit Non Cashcoll', 'expressions' => $this->productExpressions('kecil_non_cashcoll')],
            'cashcoll' => ['label' => 'Kredit Cashcoll', 'expressions' => $this->productExpressions('cashcoll')],
        ];

        $consumer = [
            'total' => ['label' => 'Total Konsumer', 'expressions' => $this->segmentExpressions('consumer')],
            'briguna' => ['label' => 'Briguna', 'expressions' => $this->productExpressions('briguna_konsumer')],
            'kpr' => ['label' => 'KPR', 'expressions' => $this->productExpressions('kpr')],
            'kkb' => ['label' => 'KKB', 'expressions' => $this->productExpressions('kkb')],
        ];

        return compact('funding', 'sme', 'consumer');
    }

    /** @return array<string, string> */
    private function segmentExpressions(string $segment): array
    {
        return match ($segment) {
            'sme' => [
                'os' => 'CASE WHEN COALESCE(sme_os, 0) <> 0 THEN COALESCE(sme_os, 0) ELSE COALESCE(kecil_non_cashcoll_os, 0) + COALESCE(cashcoll_os, 0) END',
                'sml' => 'CASE WHEN COALESCE(sme_sml, 0) <> 0 THEN COALESCE(sme_sml, 0) ELSE COALESCE(kecil_non_cashcoll_sml, 0) + COALESCE(cashcoll_sml, 0) END',
                'npl' => 'CASE WHEN COALESCE(sme_npl, 0) <> 0 THEN COALESCE(sme_npl, 0) ELSE COALESCE(kecil_non_cashcoll_npl, 0) + COALESCE(cashcoll_npl, 0) END',
            ],
            default => [
                'os' => 'CASE WHEN COALESCE(consumer_os, 0) <> 0 THEN COALESCE(consumer_os, 0) ELSE COALESCE(briguna_konsumer_os, 0) + COALESCE(kpr_os, 0) + COALESCE(kkb_os, 0) END',
                'sml' => 'CASE WHEN COALESCE(consumer_sml, 0) <> 0 THEN COALESCE(consumer_sml, 0) ELSE COALESCE(briguna_konsumer_sml, 0) + COALESCE(kpr_sml, 0) + COALESCE(kkb_sml, 0) END',
                'npl' => 'CASE WHEN COALESCE(consumer_npl, 0) <> 0 THEN COALESCE(consumer_npl, 0) ELSE COALESCE(briguna_konsumer_npl, 0) + COALESCE(kpr_npl, 0) + COALESCE(kkb_npl, 0) END',
            ],
        };
    }

    /** @return array<string, string> */
    private function productExpressions(string $prefix): array
    {
        return [
            'os' => "COALESCE({$prefix}_os, 0)",
            'sml' => "COALESCE({$prefix}_sml, 0)",
            'npl' => "COALESCE({$prefix}_npl, 0)",
        ];
    }

    private function snapshotValue(array $snapshot, string $date, string $scope, string $section, string $product, string $metric): ?float
    {
        $value = data_get($snapshot, [$date, $scope, $section, $product, $metric]);

        return $value === null ? null : (float) $value;
    }

    private function shareOfArea(array $snapshot, string $date, string $scope, string $section, string $product, string $metric): ?float
    {
        $value = $this->snapshotValue($snapshot, $date, $scope, $section, $product, $metric);
        $area = $this->snapshotValue($snapshot, $date, 'area6', $section, $product, $metric);

        return $value !== null && $area ? ($value / $area) * 100 : null;
    }

    /** @param array<string, mixed> $payload */
    private function resolveRka(
        array $payload,
        string $scope,
        string $section,
        string $product = 'total',
        string $metric = 'os'
    ): ?float
    {
        $scopeKey = $this->payloadScopeKey($scope);
        $comparisonScope = (array) data_get($payload, ['comparison', 'scopes', $scopeKey], []);
        $comparisonValue = null;

        if ($section === 'funding') {
            if ($product === 'total') {
                $comparisonValue = data_get($comparisonScope, 'funding.total.rka');
            } else {
                $productRow = collect((array) data_get($comparisonScope, 'funding.products', []))
                    ->firstWhere('key', $product);
                $comparisonValue = data_get($productRow, 'rka');
            }
        } elseif (in_array($section, ['sme', 'consumer'], true)) {
            $segment = collect((array) data_get($comparisonScope, 'credit.segments', []))
                ->firstWhere('key', $section);
            if ($product === 'total') {
                $comparisonValue = data_get($segment, "{$metric}.rka");
            } else {
                $productRow = collect((array) data_get($segment, 'products', []))
                    ->firstWhere('key', $product);
                $comparisonValue = data_get($productRow, "{$metric}.rka");
            }
        }

        if (is_numeric($comparisonValue) && (float) $comparisonValue >= 0.0) {
            return (float) $comparisonValue;
        }

        if ($product !== 'total') {
            return null;
        }

        $metric = match ($section) {
            'funding' => 'simpanan',
            'sme' => 'sme_os',
            'consumer' => 'consumer_os',
            default => null,
        };
        if (!$metric) {
            return null;
        }

        $rows = (array) data_get($payload, 'performance_overview.matrix.rows.area6', []);
        if ($scope === 'area6') {
            $values = collect($rows)->pluck("metrics.{$metric}.rka_raw")->filter(fn ($value) => is_numeric($value));

            return $values->isEmpty() ? null : (float) $values->sum();
        }

        $row = collect($rows)->first(fn (array $item): bool => strtoupper(trim((string) ($item['label'] ?? ''))) === strtoupper(trim($scope)));
        $value = data_get($row, "metrics.{$metric}.rka_raw");

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /** @param array<string, mixed> $definition */
    private function buildTimeseries(string $scope, string $section, string $product, array $definition): array
    {
        if (!Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return ['labels' => [], 'series' => []];
        }

        $latest = $this->summarySnapshotQuery()->max('snapshot_period');
        if (!$latest) {
            return ['labels' => [], 'series' => []];
        }

        $start = Carbon::parse($latest)->subMonths(12)->startOfMonth()->toDateString();
        $query = $this->summarySnapshotQuery()
            ->whereBetween('snapshot_period', [$start, $latest])
            ->selectRaw('snapshot_period');

        if ($scope !== 'area6') {
            $query->whereRaw('UPPER(TRIM(kanca_label)) = ?', [strtoupper($scope)]);
        }

        foreach ((array) $definition['expressions'] as $metric => $expression) {
            $query->selectRaw("COALESCE(SUM({$expression}), 0) as `{$metric}`");
        }

        $query->groupBy('snapshot_period')->orderBy('snapshot_period');
        $rows = $query->get();
        $monthly = $rows->groupBy(fn ($row): string => Carbon::parse($row->snapshot_period)->format('Y-m'))
            ->map(fn ($items) => $items->last())
            ->values();

        $metrics = $section === 'funding' ? ['value' => 'Simpanan'] : ['os' => 'OS', 'sml' => 'SML', 'npl' => 'NPL'];

        return [
            'labels' => $monthly->map(fn ($row): string => Carbon::parse($row->snapshot_period)->translatedFormat('M y'))->all(),
            'series' => collect($metrics)->map(function (string $label, string $metric) use ($monthly): array {
                return [
                    'key' => $metric,
                    'label' => $label,
                    'values' => $monthly->map(fn ($row): float => round((float) ($row->{$metric} ?? 0.0) / 1000000, 2))->all(),
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildProductivity(array $payload, string $scope, string $scopeLabel): array
    {
        $root = (array) data_get($payload, 'productivity', []);
        $scopePayload = (array) data_get($root, 'scopes.' . $this->payloadScopeKey($scope), []);
        $definitions = (array) data_get($root, 'categories', []);
        $pdwkScope = (array) data_get($payload, 'micro.pdwk.scopes.' . $this->payloadScopeKey($scope), []);
        $categories = [];

        foreach (['retail_sme', 'retail_consumer', 'micro'] as $key) {
            $category = (array) data_get($scopePayload, "categories.{$key}", []);
            $categories[] = [
                'key' => $key,
                'label' => (string) ($category['label'] ?? data_get($definitions, "{$key}.label", $key)),
                'available' => (bool) ($category['available'] ?? false),
                'total' => (array) ($category['total'] ?? []),
                'rows' => array_values(array_slice((array) ($category['rows'] ?? []), 0, 8)),
                'pdwk' => $key === 'micro' ? $pdwkScope : [],
            ];
        }

        return [
            'available' => (bool) data_get($root, 'available', false),
            'scope' => $scope,
            'scope_label' => $scopeLabel,
            'period_label' => (string) data_get($root, 'period_label', '-'),
            'categories' => $categories,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildTrendGroups(array $payload, string $scope, string $scopeLabel): array
    {
        $root = (array) data_get($payload, 'timeseries', []);
        $scopePayload = (array) data_get($root, 'scopes.' . $this->payloadScopeKey($scope), []);
        $groups = (array) data_get($root, 'groups', []);
        $series = (array) data_get($scopePayload, 'series', []);

        return collect($groups)->map(function (array $group, string $key) use ($scopePayload, $scopeLabel, $series): array {
            $groupSeries = collect((array) ($group['keys'] ?? []))
                ->map(fn (string $seriesKey): ?array => isset($series[$seriesKey]) ? (array) $series[$seriesKey] : null)
                ->filter()
                ->values()
                ->all();

            return [
                'key' => $key,
                'label' => (string) ($group['label'] ?? $key),
                'description' => (string) ($group['description'] ?? ''),
                'scope_label' => $scopeLabel,
                'labels' => array_values((array) ($scopePayload['labels'] ?? [])),
                'series' => $groupSeries,
            ];
        })->values()->all();
    }

    /** @param array<int, mixed> $cards */
    private function buildStrategies(array $cards): array
    {
        return collect($cards)->map(fn (array $card): array => [
            'key' => (string) ($card['key'] ?? ''),
            'title' => (string) ($card['title'] ?? '-'),
            'current_value' => (string) ($card['current_value'] ?? '-'),
            'current_label' => (string) ($card['current_label'] ?? ''),
            'secondary_value' => (string) ($card['secondary_value'] ?? '-'),
            'secondary_label' => (string) ($card['secondary_label'] ?? ''),
            'trend' => (string) ($card['trend'] ?? '-'),
            'source' => (string) ($card['source'] ?? '-'),
            'stats' => array_values((array) ($card['stats'] ?? [])),
        ])->values()->all();
    }

    /**
     * @param array<string, mixed> $strategyPayload
     * @return array<string, mixed>
     */
    private function buildFundingStrategies(
        array $strategyPayload,
        string $scope,
        string $scopeLabel
    ): array {
        $scopes = (array) ($strategyPayload['scopes'] ?? []);
        $scopeKey = $this->payloadScopeKey($scope);
        $selected = (array) (
            $scopes[$scopeKey]
            ?? $scopes['area6']
            ?? collect($scopes)->first()
            ?? []
        );

        $selected['scope_key'] = (string) ($selected['scope_key'] ?? $scopeKey);
        $selected['scope_label'] = (string) ($selected['scope_label'] ?? $scopeLabel);
        $selected['period_label'] = (string) (
            $selected['period_label']
            ?? $strategyPayload['period_label']
            ?? '-'
        );

        return $selected;
    }

    /**
     * @param array<string, mixed> $marketShare
     * @return array<string, mixed>
     */
    private function buildMarketShare(array $marketShare, string $scope, string $scopeLabel): array
    {
        $scopeKey = $this->marketShareScopeKey($scope);
        $area = (array) data_get($marketShare, 'area6', []);
        $areaSegments = (array) ($area['segments'] ?? []);
        $areaSegmentKey = (string) ($area['default_segment'] ?? 'dpk');
        $areaSegment = (array) (
            $areaSegments[$areaSegmentKey]
            ?? $areaSegments['dpk']
            ?? collect($areaSegments)->first()
            ?? []
        );
        $areaSegmentKey = (string) ($areaSegment['key'] ?? $areaSegmentKey ?: 'dpk');
        $areaKind = (string) ($areaSegment['kind'] ?? 'deposit');
        $areaRows = array_values(array_filter(
            (array) ($areaSegment['rows'] ?? []),
            fn (array $row): bool => $scopeKey === 'area6'
                || $this->marketShareScopeKey((string) ($row['branch'] ?? '')) === $scopeKey
        ));
        $areaRows = array_map(
            fn (array $row): array => $this->marketShareAreaRow($row, $areaKind),
            $areaRows
        );
        $areaTotal = (array) (
            collect($areaRows)->firstWhere('total', true)
            ?? ($scopeKey !== 'area6' ? ($areaRows[0] ?? []) : [])
        );

        $sektoral = (array) data_get($marketShare, 'sektoral', []);
        $sektoralScopes = (array) ($sektoral['scopes'] ?? []);
        $selectedSektoral = (array) (
            $sektoralScopes[$scopeKey]
            ?? $sektoralScopes['area6']
            ?? collect($sektoralScopes)->first()
            ?? []
        );
        $sectorRows = array_values((array) ($selectedSektoral['rows'] ?? []));
        usort($sectorRows, fn (array $left, array $right): int =>
            (float) ($right['bri_os'] ?? 0) <=> (float) ($left['bri_os'] ?? 0)
        );

        $mapping = (array) data_get($marketShare, 'mapping', []);
        $units = array_values(array_filter(
            (array) ($mapping['units'] ?? []),
            fn (array $unit): bool => $scopeKey === 'area6'
                || strtolower((string) ($unit['branch'] ?? '')) === $scopeKey
        ));
        $branches = array_values(array_filter(
            (array) ($mapping['branches'] ?? []),
            fn (array $branch): bool => $scopeKey === 'area6'
                || strtolower((string) ($branch['key'] ?? '')) === $scopeKey
        ));
        $mappingTotals = $scopeKey === 'area6'
            ? (array) ($mapping['area_totals'] ?? [])
            : (array) data_get($branches, '0.totals', []);
        if ($mappingTotals === []) {
            $potential = array_sum(array_map(
                fn (array $unit): float => (float) data_get($unit, 'values.total.potential', 0),
                $units
            ));
            $existing = array_sum(array_map(
                fn (array $unit): float => (float) data_get($unit, 'values.total.existing', 0),
                $units
            ));
            $mappingTotals = [
                'potential' => $potential,
                'existing' => $existing,
                'penetration' => $potential > 0 ? ($existing / $potential) * 100 : 0,
            ];
        }

        $mappingDashboard = (array) ($mapping['dashboard'] ?? []);
        $useMappingDashboard = $scopeKey === 'area6' && !empty($mappingDashboard['ready']);
        if ($useMappingDashboard) {
            $dashboardMetrics = array_merge(
                array_values((array) ($mappingDashboard['totalMetrics'] ?? [])),
                array_values((array) ($mappingDashboard['headlineMetrics'] ?? []))
            );
            $dashboardPotential = $this->marketShareMetricNumber(
                $dashboardMetrics,
                ['TOTAL POTENSI', 'POTENSI']
            );
            $dashboardExisting = $this->marketShareMetricNumber(
                $dashboardMetrics,
                ['TOTAL DEBITUR', 'DEBITUR', 'EXISTING']
            );
            $dashboardPenetration = $this->marketShareMetricNumber(
                $dashboardMetrics,
                ['PENETRASI', 'SHARE TOTAL']
            );

            if ($dashboardPotential !== null) {
                $mappingTotals['potential'] = $dashboardPotential;
            }
            if ($dashboardExisting !== null) {
                $mappingTotals['existing'] = $dashboardExisting;
            }
            if ($dashboardPenetration !== null) {
                $mappingTotals['penetration'] = $dashboardPenetration;
            } elseif ((float) ($mappingTotals['potential'] ?? 0) > 0) {
                $mappingTotals['penetration'] = ((float) ($mappingTotals['existing'] ?? 0)
                    / (float) $mappingTotals['potential']) * 100;
            }
        }

        $ranking = $units;
        usort($ranking, fn (array $left, array $right): int =>
            (float) data_get($right, 'values.total.penetration', 0)
                <=> (float) data_get($left, 'values.total.penetration', 0)
        );

        return [
            'available' => $areaRows !== [] || $selectedSektoral !== [] || !empty($mapping['ready']),
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'area6' => [
                'available' => $areaRows !== [],
                'title' => (string) ($area['title'] ?? 'Marketshare - Area 6'),
                'subtitle' => (string) ($area['subtitle'] ?? ''),
                'period' => (string) ($area['period'] ?? '-'),
                'unit' => (string) ($area['unit'] ?? 'Rp dalam Miliar'),
                'source' => (string) ($area['source'] ?? '-'),
                'scope_label' => $scopeLabel,
                'segment_key' => $areaSegmentKey,
                'segment_label' => (string) ($areaSegment['label'] ?? 'Total DPK'),
                'kind' => $areaKind,
                'headers' => (array) ($areaSegment['headers'] ?? []),
                'insights' => (array) ($areaSegment['insights'] ?? []),
                'rows' => $areaRows,
                'total' => $areaTotal,
            ],
            'sektoral' => [
                'available' => $selectedSektoral !== [],
                'title' => (string) ($sektoral['title'] ?? 'Market Share Sektoral'),
                'period' => (string) ($sektoral['period'] ?? '-'),
                'unit' => (string) ($sektoral['unit'] ?? 'Rp dalam Miliar'),
                'source' => (string) ($sektoral['source'] ?? '-'),
                'scope_label' => (string) ($selectedSektoral['label'] ?? $scopeLabel),
                'total' => (array) ($selectedSektoral['total'] ?? []),
                'rows' => $sectorRows,
                'top_sectors' => array_slice($sectorRows, 0, 6),
            ],
            'mapping' => [
                'ready' => !empty($mapping['ready']) && $units !== [],
                'title' => (string) ($mapping['title'] ?? 'Mapping Market Share'),
                'sheet' => (string) ($mapping['sheet'] ?? 'REKAP'),
                'updated_at' => (string) ($mapping['updated_at'] ?? '-'),
                'scope_label' => $scopeLabel,
                'totals' => $mappingTotals,
                'cards' => $this->marketShareMappingCards(
                    $mappingTotals,
                    $mappingDashboard,
                    $useMappingDashboard
                ),
                'dashboard' => [
                    'ready' => $useMappingDashboard,
                    'title' => (string) ($mappingDashboard['title'] ?? ''),
                    'subtitle' => (string) ($mappingDashboard['subtitle'] ?? ''),
                    'selectedSector' => (string) ($mappingDashboard['selectedSector'] ?? '-'),
                    'demographics' => array_values((array) ($mappingDashboard['demographics'] ?? [])),
                    'headlineMetrics' => array_values((array) ($mappingDashboard['headlineMetrics'] ?? [])),
                    'totalMetrics' => array_values((array) ($mappingDashboard['totalMetrics'] ?? [])),
                    'highlights' => $this->marketShareHighlightTexts(
                        (array) ($mappingDashboard['highlights'] ?? [])
                    ),
                    'filters' => (array) ($mappingDashboard['filters'] ?? []),
                    'charts' => (array) ($mappingDashboard['charts'] ?? []),
                    'sheet' => (string) ($mappingDashboard['sheet'] ?? 'DASHBOARD'),
                    'updated_at' => (string) ($mappingDashboard['updated_at'] ?? $mapping['updated_at'] ?? '-'),
                ],
                'coverage' => [
                    'unit_count' => count($units),
                    'mapped_unit_count' => count(array_filter(
                        $units,
                        fn (array $unit): bool => (array) ($unit['district_codes'] ?? []) !== []
                    )),
                    'district_count' => count(array_unique(array_merge(...array_map(
                        fn (array $unit): array => array_map('strval', (array) ($unit['district_codes'] ?? [])),
                        $units
                    )))),
                ],
                'ranking' => array_slice($ranking, 0, 6),
                'map_points' => $this->buildMarketShareMapPoints(
                    $units,
                    (array) data_get($mapping, 'geojson.features', [])
                ),
                'source' => (array) ($mapping['source'] ?? []),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function marketShareAreaRow(array $row, string $kind): array
    {
        $values = array_values((array) ($row['values'] ?? []));
        if ($kind === 'loan') {
            $briCurrentIndex = 2;
            $industryCurrentIndex = 9;
            $marketShareCurrentIndex = 16;
            $marketSharePreviousIndex = 15;
            $briYtdIndex = 6;
        } elseif ($kind === 'quality') {
            $briCurrentIndex = 0;
            $industryCurrentIndex = 8;
            $marketShareCurrentIndex = 16;
            $marketSharePreviousIndex = 16;
            $briYtdIndex = 6;
        } else {
            $briCurrentIndex = 2;
            $industryCurrentIndex = 6;
            $marketShareCurrentIndex = 14;
            $marketSharePreviousIndex = 13;
            $briYtdIndex = 3;
        }

        $briCurrent = $this->marketShareNumber($values[$briCurrentIndex] ?? null);
        $industryCurrent = $this->marketShareNumber($values[$industryCurrentIndex] ?? null);
        $outsideCurrent = $kind === 'deposit'
            ? $this->marketShareNumber($values[10] ?? null)
            : null;
        if ($outsideCurrent === null && $briCurrent !== null && $industryCurrent !== null) {
            $outsideCurrent = max(0.0, $industryCurrent - $briCurrent);
        }

        return [
            'label' => (string) ($row['branch'] ?? '-'),
            'branch' => (string) ($row['branch'] ?? '-'),
            'total' => (bool) ($row['total'] ?? false),
            'bri_current' => $briCurrent,
            'industry_current' => $industryCurrent,
            'outside_current' => $outsideCurrent,
            'market_share_current' => $this->marketShareNumber($values[$marketShareCurrentIndex] ?? null),
            'market_share_previous' => $this->marketShareNumber($values[$marketSharePreviousIndex] ?? null),
            'bri_ytd' => $this->marketShareNumber($values[$briYtdIndex] ?? null),
            'source_values' => $values,
        ];
    }

    /** @param array<int, array<string, mixed>> $metrics */
    private function marketShareMetricNumber(array $metrics, array $labels): ?float
    {
        $labels = array_map(fn (string $label): string => $this->marketShareMetricToken($label), $labels);
        foreach ($metrics as $metric) {
            $metricLabel = $this->marketShareMetricToken((string) ($metric['label'] ?? ''));
            if ($metricLabel === '') {
                continue;
            }
            foreach ($labels as $label) {
                if ($label !== '' && ($metricLabel === $label || str_contains($metricLabel, $label))) {
                    return $this->marketShareNumber(
                        $metric['raw'] ?? $metric['numeric'] ?? $metric['value'] ?? null
                    );
                }
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function marketShareMappingCards(array $totals, array $dashboard, bool $useDashboard): array
    {
        $cards = [
            ['label' => 'POTENSI', 'value' => $totals['potential'] ?? null, 'kind' => 'integer', 'meta' => $useDashboard ? 'Dashboard Area 6' : 'Basis nasabah REKAP'],
            ['label' => 'EXISTING', 'value' => $totals['existing'] ?? null, 'kind' => 'integer', 'meta' => $useDashboard ? 'Debitur Dashboard' : 'Debitur BRI REKAP'],
            ['label' => 'PENETRASI', 'value' => $totals['penetration'] ?? null, 'kind' => 'percent', 'meta' => 'Existing / potensi'],
        ];

        if ($useDashboard) {
            $demographics = array_values((array) ($dashboard['demographics'] ?? []));
            $headlineMetrics = array_values((array) ($dashboard['headlineMetrics'] ?? []));
            $headline = collect($demographics)->first(function (array $metric): bool {
                return str_contains(
                    $this->marketShareMetricToken((string) ($metric['label'] ?? '')),
                    'ORANG'
                );
            }) ?? ($demographics[0] ?? null);
            $usesDemographic = is_array($headline);

            if (!$usesDemographic) {
                $headline = $headlineMetrics[0] ?? null;
            }

            if (is_array($headline)) {
                $headlineLabel = strtoupper((string) ($headline['label'] ?? 'KPI SEKTOR'));
                $selectedSector = trim((string) ($dashboard['selectedSector'] ?? ''));
                if (!$usesDemographic) {
                    $headlineLabel = trim($headlineLabel . ' ' . ($selectedSector !== '' && $selectedSector !== '-'
                        ? strtoupper($selectedSector)
                        : 'SEKTOR'));
                }
                $cards[] = [
                    'label' => $headlineLabel,
                    'value' => $this->marketShareNumber($headline['raw'] ?? $headline['numeric'] ?? $headline['value'] ?? null),
                    'kind' => 'integer',
                    'meta' => $usesDemographic ? 'Demografi dashboard' : 'Sektor dashboard terpilih',
                ];
            }
        }

        if (count($cards) < 4) {
            $cards[] = [
                'label' => 'GAP PASAR',
                'value' => max(0.0, (float) ($totals['potential'] ?? 0) - (float) ($totals['existing'] ?? 0)),
                'kind' => 'integer',
                'meta' => 'Potensi belum tergarap',
            ];
        }

        return array_slice($cards, 0, 4);
    }

    /** @return array<int, string> */
    private function marketShareHighlightTexts(array $highlights): array
    {
        $texts = [];
        foreach ($highlights as $highlight) {
            if (is_string($highlight)) {
                $text = trim($highlight);
            } elseif (is_array($highlight)) {
                $label = trim((string) ($highlight['label'] ?? $highlight['title'] ?? ''));
                $value = trim((string) ($highlight['value'] ?? $highlight['text'] ?? $highlight['description'] ?? ''));
                $text = trim($label . ($label !== '' && $value !== '' ? ': ' : '') . $value);
            } else {
                $text = '';
            }
            if ($text !== '') {
                $texts[] = $text;
            }
        }

        return array_slice($texts, 0, 4);
    }

    private function marketShareMetricToken(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($value)));
    }

    private function marketShareNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $normalized = trim($value, "() \t\n\r\0\x0B");
        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized) ?? '';
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (substr_count($normalized, ',') === 1 && substr_count($normalized, '.') === 0) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
        if (!is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        return $negative ? -abs($number) : $number;
    }

    /**
     * @param array<int, array<string, mixed>> $units
     * @param array<int, array<string, mixed>> $features
     * @return array<int, array<string, mixed>>
     */
    private function buildMarketShareMapPoints(array $units, array $features): array
    {
        $featureCentroids = [];
        foreach ($features as $feature) {
            $districtCode = trim((string) data_get($feature, 'properties.KDCPUM', ''));
            $pairs = $this->marketShareCoordinatePairs(data_get($feature, 'geometry.coordinates', []));
            if ($districtCode === '' || $pairs === []) {
                continue;
            }
            $featureCentroids[$districtCode] = [
                'longitude' => array_sum(array_column($pairs, 0)) / count($pairs),
                'latitude' => array_sum(array_column($pairs, 1)) / count($pairs),
            ];
        }

        $rawPoints = [];
        foreach ($units as $unit) {
            $centroids = array_values(array_filter(array_map(
                fn ($code): ?array => $featureCentroids[trim((string) $code)] ?? null,
                (array) ($unit['district_codes'] ?? [])
            )));
            if ($centroids === []) {
                continue;
            }
            $rawPoints[] = [
                'label' => (string) ($unit['label'] ?? $unit['name'] ?? '-'),
                'branch_label' => (string) ($unit['branch_label'] ?? '-'),
                'longitude' => array_sum(array_column($centroids, 'longitude')) / count($centroids),
                'latitude' => array_sum(array_column($centroids, 'latitude')) / count($centroids),
                'potential' => (float) data_get($unit, 'values.total.potential', 0),
                'existing' => (float) data_get($unit, 'values.total.existing', 0),
                'penetration' => (float) data_get($unit, 'values.total.penetration', 0),
            ];
        }

        if ($rawPoints === []) {
            return [];
        }
        $longitudes = array_column($rawPoints, 'longitude');
        $latitudes = array_column($rawPoints, 'latitude');
        $minLongitude = min($longitudes);
        $maxLongitude = max($longitudes);
        $minLatitude = min($latitudes);
        $maxLatitude = max($latitudes);

        return array_map(function (array $point) use ($minLongitude, $maxLongitude, $minLatitude, $maxLatitude): array {
            $point['x'] = $maxLongitude === $minLongitude
                ? 0.5
                : (($point['longitude'] - $minLongitude) / ($maxLongitude - $minLongitude));
            $point['y'] = $maxLatitude === $minLatitude
                ? 0.5
                : 1 - (($point['latitude'] - $minLatitude) / ($maxLatitude - $minLatitude));
            unset($point['longitude'], $point['latitude']);

            return $point;
        }, $rawPoints);
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function marketShareCoordinatePairs(mixed $coordinates): array
    {
        if (!is_array($coordinates)) {
            return [];
        }
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
            return [[(float) $coordinates[0], (float) $coordinates[1]]];
        }

        $pairs = [];
        foreach ($coordinates as $coordinate) {
            $pairs = array_merge($pairs, $this->marketShareCoordinatePairs($coordinate));
        }

        return $pairs;
    }

    private function marketShareScopeKey(string $scope): string
    {
        if ($scope === 'area6') {
            return 'area6';
        }

        return strtolower(trim((string) preg_replace('/^KC\s+/i', '', $scope)));
    }

    private function normaliseScope(string $scope): string
    {
        if (strtolower($scope) === 'area6') {
            return 'area6';
        }

        return collect(self::BRANCHES)->first(fn (string $branch): bool => strtoupper($branch) === strtoupper(trim($scope))) ?: 'area6';
    }

    private function payloadScopeKey(string $scope): string
    {
        return $scope === 'area6' ? 'area6' : strtoupper($scope);
    }
}
