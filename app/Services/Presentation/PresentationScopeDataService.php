<?php

namespace App\Services\Presentation;

use App\Support\DashboardHarianSnapshotService;
use App\Support\RkaLookupService;
use App\Support\SargableDateFilter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PresentationScopeDataService
{
    private const SNAPSHOT_TABLE = 'dashboard_harian_snapshots';

    private const RM_SNAPSHOT_TABLE = 'performance_rm_snapshots';

    private const FINANCIAL_TABLE = 'ssa_almafacts';

    private const CONSUMER_MONTHLY_TARGETS = [
        'ARISSULISTYAWAN' => 3700000000.0,
        'ZULFAENDYCRISMANA' => 3700000000.0,
        'RATNADWISISWIYANTORO' => 3700000000.0,
        'RIDHOARDIANTO' => 3700000000.0,
        'DIMASPERDANAHADIWIJAYA' => 3700000000.0,
        'RONSROHANATALIBATA' => 3750000000.0,
        'RONAROHANATALIBATA' => 3750000000.0,
        'ARDINI' => 3850000000.0,
        'NAVANYOGAPRATAMA' => 1900000000.0,
        'MUHAMADSYAMSUDINHIMAWIJAYA' => 3700000000.0,
        'BAGUSPRASETYO' => 3750000000.0,
        'ARIANISETYOPALUPI' => 3750000000.0,
        'TITINOKTAVIA' => 3850000000.0,
        'FARIDRAMOLDONI' => 3700000000.0,
    ];

    /**
     * @param array<string, mixed> $matrix
     * @param array<int, string> $branches
     * @return array<string, mixed>
     */
    public function build(?string $period, ?string $loanPeriod, array $matrix, array $branches): array
    {
        $summary = $this->buildSummary($period, $matrix, $branches);

        return array_merge($summary, [
            'timeseries' => $this->buildTimeseries($period, $summary['scope_options'], $branches),
            'productivity' => $this->buildProductivity($loanPeriod, $summary['scope_options'], $branches),
        ]);
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<int, string> $branches
     * @return array<string, mixed>
     */
    public function buildSummary(?string $period, array $matrix, array $branches): array
    {
        $scopeOptions = $this->scopeOptions($branches);
        $snapshotRows = $this->loadCurrentSnapshotRows($period, $branches);
        $summaryScopes = $this->buildSummaryScopes($matrix, $scopeOptions);
        $prognosa = app(PresentationPrognosaWeeklyService::class)->payload();
        $comparison = $this->buildComparisonPayload(
            $period,
            $scopeOptions,
            $branches,
            $summaryScopes,
            $prognosa
        );

        return [
            'scope_options' => $scopeOptions,
            'summary_scopes' => $summaryScopes,
            'savings_scopes' => $this->buildSavingsScopes($snapshotRows, $scopeOptions, $period),
            'funding_structure_scopes' => $this->buildFundingStructureScopes($snapshotRows, $scopeOptions, $period),
            'credit_structure_scopes' => $this->buildCreditStructureScopes($snapshotRows, $scopeOptions, $period),
            'loan_product_scopes' => $this->buildLoanProductScopes($snapshotRows, $scopeOptions, $period),
            'financial_scopes' => $this->buildFinancialScopes($period, $scopeOptions, $branches),
            'comparison_periods' => $comparison['periods'],
            'comparison_scopes' => $comparison['scopes'],
            'comparison_prognosa' => $comparison['prognosa'],
        ];
    }

    /** @param array<int, string> $branches */
    private function scopeOptions(array $branches): array
    {
        return array_merge(
            [['key' => 'area6', 'label' => 'Area 6 Konsol']],
            collect($branches)
                ->filter(fn (string $branch): bool => trim($branch) !== '')
                ->unique(fn (string $branch): string => strtoupper(trim($branch)))
                ->map(fn (string $branch): array => [
                    'key' => $this->scopeKey($branch),
                    'label' => trim($branch),
                ])
                ->values()
                ->all()
        );
    }

    /**
     * @param array<string, mixed> $matrix
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @return array<string, mixed>
     */
    private function buildSummaryScopes(array $matrix, array $scopeOptions): array
    {
        $branchRows = collect(data_get($matrix, 'rows.area6', []));
        $scopes = [];

        foreach ($scopeOptions as $option) {
            $rows = $option['key'] === 'area6'
                ? $branchRows
                : $branchRows->filter(
                    fn (array $row): bool => $this->scopeKey((string) ($row['label'] ?? '')) === $option['key']
                );

            $metrics = [];
            foreach (['simpanan', 'os', 'sml', 'npl', 'sme_os', 'consumer_os', 'micro_os'] as $metric) {
                $metrics[$metric] = $this->aggregateMatrixMetric($rows, $metric);
            }

            $simpanan = (float) data_get($metrics, 'simpanan.latest', 0.0);
            $os = (float) data_get($metrics, 'os.latest', 0.0);
            $sml = (float) data_get($metrics, 'sml.latest', 0.0);
            $npl = (float) data_get($metrics, 'npl.latest', 0.0);
            $smlRatio = $this->percentOf($sml, $os);
            $nplRatio = $this->percentOf($npl, $os);
            $ldr = $simpanan > 0 ? $os / $simpanan : null;

            $cards = [
                $this->summaryCard('simpanan', 'Total Simpanan', $simpanan, $metrics['simpanan'], 'currency'),
                $this->summaryCard('os', 'Total OS Non Commercial', $os, $metrics['os'], 'currency'),
                [
                    'key' => 'ldr',
                    'label' => 'LDR',
                    'available' => $ldr !== null,
                    'value' => $ldr === null ? 'Data belum tersedia' : number_format($ldr, 2, ',', '.') . 'x',
                    'value_raw' => $ldr,
                    'trend' => '-',
                    'meta' => 'OS dibandingkan total simpanan',
                    'source' => self::SNAPSHOT_TABLE,
                ],
                array_merge(
                    $this->summaryCard('sml', 'SML', $sml, $metrics['sml'], 'currency'),
                    ['ratio' => $this->formatPercent($smlRatio), 'ratio_raw' => $smlRatio]
                ),
                array_merge(
                    $this->summaryCard('npl', 'NPL', $npl, $metrics['npl'], 'currency'),
                    ['ratio' => $this->formatPercent($nplRatio), 'ratio_raw' => $nplRatio]
                ),
            ];

            $scopes[$option['key']] = [
                'available' => $rows->isNotEmpty(),
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'cards' => $cards,
                'metrics' => $metrics,
                'highlights' => [
                    'Simpanan ' . $this->formatCurrency($simpanan),
                    'OS ' . $this->formatCurrency($os),
                    'SML ' . $this->formatCurrency($sml) . ' (' . $this->formatPercent($smlRatio) . ')',
                    'NPL ' . $this->formatCurrency($npl) . ' (' . $this->formatPercent($nplRatio) . ')',
                ],
            ];
        }

        return $scopes;
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function aggregateMatrixMetric(Collection $rows, string $metric): array
    {
        $aggregate = [
            'latest' => 0.0,
            'ytd' => 0.0,
            'mtm' => 0.0,
            'mtd' => 0.0,
            'rka' => 0.0,
            'gap' => 0.0,
            'series' => [0.0, 0.0, 0.0, 0.0],
        ];

        foreach ($rows as $row) {
            $item = (array) data_get($row, "metrics.{$metric}", []);
            $aggregate['latest'] += (float) ($item['latest_raw'] ?? 0.0);
            $aggregate['ytd'] += (float) ($item['ytd_raw'] ?? 0.0);
            $aggregate['mtm'] += (float) ($item['mtm_raw'] ?? 0.0);
            $aggregate['mtd'] += (float) ($item['mtd_raw'] ?? 0.0);
            $aggregate['rka'] += (float) ($item['rka_raw'] ?? 0.0);
            $aggregate['gap'] += (float) ($item['gap_raw'] ?? 0.0);

            foreach ((array) ($item['series'] ?? []) as $index => $value) {
                $aggregate['series'][$index] = (float) ($aggregate['series'][$index] ?? 0.0) + (float) $value;
            }
        }

        $aggregate['achievement'] = $aggregate['rka'] > 0
            ? ($aggregate['latest'] / $aggregate['rka']) * 100
            : null;
        $aggregate['latest_fmt'] = $this->formatCurrency($aggregate['latest']);
        $aggregate['rka_fmt'] = $aggregate['rka'] > 0 ? $this->formatCurrency($aggregate['rka']) : '-';
        $aggregate['gap_fmt'] = $aggregate['rka'] > 0 ? $this->formatSignedCurrency($aggregate['gap']) : '-';
        $aggregate['achievement_fmt'] = $aggregate['achievement'] === null
            ? '-'
            : $this->formatPercent($aggregate['achievement']);

        return $aggregate;
    }

    /** @param array<string, mixed> $metric */
    private function summaryCard(string $key, string $label, float $value, array $metric, string $type): array
    {
        $baseline = $value - (float) ($metric['mtm'] ?? 0.0);
        $trend = $baseline != 0.0 ? (((float) ($metric['mtm'] ?? 0.0) / abs($baseline)) * 100) : 0.0;

        return [
            'key' => $key,
            'label' => $label,
            'available' => true,
            'value' => $type === 'currency' ? $this->formatCurrency($value) : (string) $value,
            'value_raw' => $value,
            'trend' => $this->formatSignedPercent($trend),
            'meta' => 'Scope presentasi aktif',
            'source' => self::SNAPSHOT_TABLE,
        ];
    }

    /**
     * @param array<string, array<string, float>> $snapshotRows
     * @param array<int, array{key: string, label: string}> $scopeOptions
     */
    private function buildSavingsScopes(array $snapshotRows, array $scopeOptions, ?string $period): array
    {
        $scopes = [];
        foreach ($scopeOptions as $option) {
            $row = $snapshotRows[$option['key']] ?? [];
            $total = (float) ($row['simpanan'] ?? 0.0);
            $definitions = [
                'total_simpanan' => ['label' => 'Total Simpanan', 'field' => 'simpanan', 'tone' => '#0857c3', 'icon' => 'fas fa-piggy-bank'],
                'giro' => ['label' => 'Giro', 'field' => 'giro', 'tone' => '#307fe2', 'icon' => 'fas fa-building-columns'],
                'tabungan' => ['label' => 'Tabungan', 'field' => 'tabungan', 'tone' => '#71c5e8', 'icon' => 'fas fa-wallet'],
                'deposito' => ['label' => 'Deposito', 'field' => 'deposito', 'tone' => '#ccad95', 'icon' => 'fas fa-vault'],
                'casa' => ['label' => 'CASA', 'field' => 'casa', 'tone' => '#059669', 'icon' => 'fas fa-layer-group'],
            ];

            $cards = collect($definitions)->map(function (array $definition, string $key) use ($row, $total): array {
                $value = (float) ($row[$definition['field']] ?? 0.0);
                $percentage = $key === 'total_simpanan' ? 100.0 : $this->percentOf($value, $total);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'value_raw' => $value,
                    'value' => $this->formatCurrency($value),
                    'pct_raw' => $percentage,
                    'pct' => $this->formatPercent($percentage),
                    'tone' => $definition['tone'],
                    'icon' => $definition['icon'],
                ];
            })->values()->all();

            $scopes[$option['key']] = [
                'available' => $total > 0.0,
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'period' => $period,
                'period_label' => $this->formatPeriod($period),
                'cards' => $cards,
            ];
        }

        return $scopes;
    }

    /**
     * @param array<string, array<string, float>> $snapshotRows
     * @param array<int, array{key: string, label: string}> $scopeOptions
     */
    private function buildLoanProductScopes(array $snapshotRows, array $scopeOptions, ?string $period): array
    {
        $definitions = [
            ['key' => 'kupedes', 'label' => 'Kupedes', 'icon' => 'fas fa-users'],
            ['key' => 'kur_mikro', 'label' => 'KUR Mikro', 'icon' => 'fas fa-store'],
            ['key' => 'briguna_mikro', 'label' => 'Briguna Mikro', 'icon' => 'fas fa-id-card'],
            ['key' => 'kpp', 'label' => 'KPP', 'icon' => 'fas fa-briefcase'],
            ['key' => 'kur_kecil', 'label' => 'KUR Kecil', 'icon' => 'fas fa-building'],
        ];
        $scopes = [];

        foreach ($scopeOptions as $option) {
            $raw = $snapshotRows[$option['key']] ?? [];
            $rows = collect($definitions)->map(function (array $definition) use ($raw): array {
                $prefix = $definition['key'];
                $os = (float) ($raw[$prefix . '_os'] ?? 0.0);
                $sml = (float) ($raw[$prefix . '_sml'] ?? 0.0);
                $npl = (float) ($raw[$prefix . '_npl'] ?? 0.0);

                return [
                    'key' => $prefix,
                    'label' => $definition['label'],
                    'icon' => $definition['icon'],
                    'os_raw' => $os,
                    'sml_raw' => $sml,
                    'npl_raw' => $npl,
                    'os' => $this->formatCurrency($os),
                    'sml' => $this->formatCurrency($sml),
                    'npl' => $this->formatCurrency($npl),
                    'sml_pct' => $os > 0 ? $this->formatPercent(($sml / $os) * 100) : '-',
                    'npl_pct' => $os > 0 ? $this->formatPercent(($npl / $os) * 100) : '-',
                ];
            })->values()->all();

            $scopes[$option['key']] = [
                'available' => collect($rows)->contains(fn (array $row): bool => (float) $row['os_raw'] > 0.0),
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'period' => $period,
                'period_label' => $this->formatPeriod($period),
                'rows' => $rows,
            ];
        }

        return $scopes;
    }

    /**
     * @param array<string, array<string, float>> $snapshotRows
     * @param array<int, array{key: string, label: string}> $scopeOptions
     */
    private function buildFundingStructureScopes(array $snapshotRows, array $scopeOptions, ?string $period): array
    {
        $scopes = [];

        foreach ($scopeOptions as $option) {
            $structure = $this->fundingStructureRow(
                $snapshotRows[$option['key']] ?? [],
                $option['key'],
                $option['label']
            );
            $structure['period'] = $period;
            $structure['period_label'] = $this->formatPeriod($period);
            $structure['branches'] = $option['key'] === 'area6'
                ? collect($scopeOptions)
                    ->reject(fn (array $branch): bool => $branch['key'] === 'area6')
                    ->map(fn (array $branch): array => $this->fundingStructureRow(
                        $snapshotRows[$branch['key']] ?? [],
                        $branch['key'],
                        $branch['label']
                    ))
                    ->values()
                    ->all()
                : [];

            $scopes[$option['key']] = $structure;
        }

        return $scopes;
    }

    /** @param array<string, float> $raw */
    private function fundingStructureRow(array $raw, string $scopeKey, string $scopeLabel): array
    {
        $total = (float) ($raw['simpanan'] ?? 0.0);
        $segmentDefinitions = [
            'retail' => ['label' => 'Ritel', 'fields' => ['giro_ritel', 'tabungan_ritel', 'deposito_ritel']],
            'wholesale' => ['label' => 'Wholesale', 'fields' => ['giro_wholesale', 'tabungan_wholesale', 'deposito_wholesale']],
            'micro' => ['label' => 'Mikro', 'fields' => ['giro_mikro', 'tabungan_mikro', 'deposito_mikro']],
        ];
        $productDefinitions = [
            'giro' => ['label' => 'Giro', 'field' => 'giro'],
            'tabungan' => ['label' => 'Tabungan', 'field' => 'tabungan'],
            'deposito' => ['label' => 'Deposito', 'field' => 'deposito'],
        ];

        $segments = collect($segmentDefinitions)->map(function (array $definition, string $key) use ($raw, $total): array {
            $value = collect($definition['fields'])->sum(fn (string $field): float => (float) ($raw[$field] ?? 0.0));

            return $this->fundingStructureItem($key, $definition['label'], $value, $total);
        })->values()->all();
        $products = collect($productDefinitions)->map(function (array $definition, string $key) use ($raw, $total): array {
            return $this->fundingStructureItem(
                $key,
                $definition['label'],
                (float) ($raw[$definition['field']] ?? 0.0),
                $total
            );
        })->values()->all();

        return [
            'available' => $total > 0.0,
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'total_raw' => $total,
            'total' => $this->formatCurrency($total),
            'segments' => $segments,
            'products' => $products,
        ];
    }

    private function fundingStructureItem(string $key, string $label, float $value, float $total): array
    {
        $share = $this->percentOf($value, $total);

        return [
            'key' => $key,
            'label' => $label,
            'value_raw' => $value,
            'value' => $this->formatCurrency($value),
            'share_raw' => $share,
            'share' => $this->formatPercent($share),
        ];
    }

    /**
     * @param array<string, array<string, float>> $snapshotRows
     * @param array<int, array{key: string, label: string}> $scopeOptions
     */
    private function buildCreditStructureScopes(array $snapshotRows, array $scopeOptions, ?string $period): array
    {
        $scopes = [];

        foreach ($scopeOptions as $option) {
            $structure = $this->creditStructureRow(
                $snapshotRows[$option['key']] ?? [],
                $option['key'],
                $option['label']
            );
            $structure['period'] = $period;
            $structure['period_label'] = $this->formatPeriod($period);
            $structure['branches'] = $option['key'] === 'area6'
                ? collect($scopeOptions)
                    ->reject(fn (array $branch): bool => $branch['key'] === 'area6')
                    ->map(fn (array $branch): array => $this->creditStructureRow(
                        $snapshotRows[$branch['key']] ?? [],
                        $branch['key'],
                        $branch['label']
                    ))
                    ->values()
                    ->all()
                : [];

            $scopes[$option['key']] = $structure;
        }

        return $scopes;
    }

    /** @param array<string, float> $raw */
    private function creditStructureRow(array $raw, string $scopeKey, string $scopeLabel): array
    {
        $definitions = [
            'sme' => [
                'label' => 'SME',
                'products' => [
                    'non_cashcoll' => ['label' => 'Kecil Non Cashcoll', 'prefix' => 'kecil_non_cashcoll'],
                    'cashcoll' => ['label' => 'Cashcoll', 'prefix' => 'cashcoll'],
                ],
            ],
            'consumer' => [
                'label' => 'Konsumer',
                'products' => [
                    'briguna' => ['label' => 'Briguna', 'prefix' => 'briguna_konsumer'],
                    'kpr' => ['label' => 'KPR', 'prefix' => 'kpr'],
                ],
            ],
            'micro' => [
                'label' => 'Mikro',
                'products' => [
                    'briguna_mikro' => ['label' => 'Briguna Mikro', 'prefix' => 'briguna_mikro'],
                    'kupedes' => ['label' => 'Kupedes', 'prefix' => 'kupedes'],
                    'kur_mikro' => ['label' => 'KUR Mikro', 'prefix' => 'kur_mikro'],
                    'kur_kecil' => ['label' => 'KUR Kecil', 'prefix' => 'kur_kecil'],
                    'kpp' => ['label' => 'KUR KPP', 'prefix' => 'kpp'],
                ],
            ],
        ];

        $segments = collect($definitions)->map(function (array $definition, string $segmentKey) use ($raw): array {
            $products = collect($definition['products'])->map(function (array $product, string $productKey) use ($raw): array {
                return $this->creditMetricItem($productKey, $product['label'], $product['prefix'], $raw);
            })->filter(
                fn (array $product): bool => (float) $product['os_raw'] !== 0.0
                    || (float) $product['sml_raw'] !== 0.0
                    || (float) $product['npl_raw'] !== 0.0
            )->values();

            $fallback = [
                'os' => (float) $products->sum('os_raw'),
                'sml' => (float) $products->sum('sml_raw'),
                'npl' => (float) $products->sum('npl_raw'),
            ];
            $metrics = [];
            foreach (['os', 'sml', 'npl'] as $metric) {
                $direct = (float) ($raw[$segmentKey . '_' . $metric] ?? 0.0);
                $metrics[$metric] = $direct !== 0.0 ? $direct : $fallback[$metric];
            }

            return array_merge(
                [
                    'key' => $segmentKey,
                    'label' => $definition['label'],
                    'products' => $products->all(),
                ],
                $this->creditMetricValues($metrics['os'], $metrics['sml'], $metrics['npl'])
            );
        })->values()->all();

        $segmentRows = collect($segments);
        $directTotalOs = (float) ($raw['os'] ?? 0.0);
        $directTotalSml = (float) ($raw['sml'] ?? 0.0);
        $directTotalNpl = (float) ($raw['npl'] ?? 0.0);
        $totalOs = $directTotalOs !== 0.0 ? $directTotalOs : (float) $segmentRows->sum('os_raw');
        $totalSml = $directTotalSml !== 0.0 ? $directTotalSml : (float) $segmentRows->sum('sml_raw');
        $totalNpl = $directTotalNpl !== 0.0 ? $directTotalNpl : (float) $segmentRows->sum('npl_raw');

        return [
            'available' => $totalOs > 0.0,
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'total' => $this->creditMetricValues($totalOs, $totalSml, $totalNpl),
            'segments' => $segments,
        ];
    }

    /** @param array<string, float> $raw */
    private function creditMetricItem(string $key, string $label, string $prefix, array $raw): array
    {
        return array_merge([
            'key' => $key,
            'label' => $label,
        ], $this->creditMetricValues(
            (float) ($raw[$prefix . '_os'] ?? 0.0),
            (float) ($raw[$prefix . '_sml'] ?? 0.0),
            (float) ($raw[$prefix . '_npl'] ?? 0.0)
        ));
    }

    private function creditMetricValues(float $os, float $sml, float $npl): array
    {
        $smlRatio = $this->percentOf($sml, $os);
        $nplRatio = $this->percentOf($npl, $os);

        return [
            'os_raw' => $os,
            'os' => $this->formatCurrency($os),
            'sml_raw' => $sml,
            'sml' => $this->formatCurrency($sml),
            'sml_ratio_raw' => $smlRatio,
            'sml_ratio' => $this->formatPercent($smlRatio),
            'npl_raw' => $npl,
            'npl' => $this->formatCurrency($npl),
            'npl_ratio_raw' => $nplRatio,
            'npl_ratio' => $this->formatPercent($nplRatio),
        ];
    }

    /**
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @param array<int, string> $branches
     */
    private function buildFinancialScopes(?string $targetPeriod, array $scopeOptions, array $branches): array
    {
        $empty = [];
        foreach ($scopeOptions as $option) {
            $empty[$option['key']] = [
                'available' => false,
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'period' => null,
                'period_label' => 'Belum ada data',
                'cards' => [],
                'branches' => [],
            ];
        }

        if (!Schema::hasTable(self::FINANCIAL_TABLE)) {
            return $empty;
        }

        $periodQuery = DB::table(self::FINANCIAL_TABLE)
            ->whereIn(DB::raw('UPPER(TRIM(kanca_konsolidasi))'), array_map('strtoupper', $branches));
        if ($targetPeriod) {
            SargableDateFilter::apply($periodQuery, 'month_day_year_of_posisi', '<=', $targetPeriod);
        }
        $period = $periodQuery->max('month_day_year_of_posisi');
        if (!$period) {
            return $empty;
        }

        $metrics = [
            'profit_after_tax' => ['label' => 'Laba Setelah Pajak', 'source' => '15. Laba Setelah Pajak', 'type' => 'money', 'tone' => '#0857c3'],
            'ppop' => ['label' => 'PPOP', 'source' => '10. PPOP', 'type' => 'money', 'tone' => '#307fe2'],
            'nim' => ['label' => 'NIM', 'source' => '22. NIM (%)', 'type' => 'percent', 'tone' => '#059669'],
            'bopo' => ['label' => 'BOPO', 'source' => '28. BOPO (%)', 'type' => 'percent', 'tone' => '#dc2626'],
            'cer' => ['label' => 'CER', 'source' => '29. CER (%)', 'type' => 'percent', 'tone' => '#f59e0b'],
            'roa_before_tax' => ['label' => 'ROA Before Tax', 'source' => '26. ROA sebelum Pajak (%)', 'type' => 'percent', 'tone' => '#0f766e'],
            'roa_after_tax' => ['label' => 'ROA After Tax', 'source' => '27. ROA setelah Pajak (%)', 'type' => 'percent', 'tone' => '#7c3aed'],
            'casa' => ['label' => 'CASA', 'source' => '38. CASA (%)', 'type' => 'percent', 'tone' => '#00aeef'],
        ];

        $rows = SargableDateFilter::apply(
            DB::table(self::FINANCIAL_TABLE),
            'month_day_year_of_posisi',
            '=',
            $period
        )
            ->whereIn(DB::raw('UPPER(TRIM(kanca_konsolidasi))'), array_map('strtoupper', $branches))
            ->whereIn('keterangan', collect($metrics)->pluck('source')->all())
            ->get(['kanca_konsolidasi', 'keterangan', 'saldo']);

        $scopes = [];
        foreach ($scopeOptions as $option) {
            $scopeRows = $option['key'] === 'area6'
                ? $rows
                : $rows->filter(fn ($row): bool => $this->scopeKey((string) $row->kanca_konsolidasi) === $option['key']);

            $cards = collect($metrics)->map(function (array $metric, string $key) use ($scopeRows): array {
                $metricRows = $scopeRows->where('keterangan', $metric['source']);
                $raw = $metric['type'] === 'percent'
                    ? (float) $metricRows->avg('saldo')
                    : (float) $metricRows->sum('saldo');

                return [
                    'key' => $key,
                    'label' => $metric['label'],
                    'value_raw' => $raw,
                    'value' => $metric['type'] === 'percent' ? $this->formatPercent($raw) : $this->formatCurrency($raw),
                    'type' => $metric['type'],
                    'tone' => $metric['tone'],
                ];
            })->values()->all();

            $profitRows = $scopeRows->where('keterangan', '15. Laba Setelah Pajak')
                ->groupBy(fn ($row): string => trim((string) $row->kanca_konsolidasi))
                ->map(function (Collection $items, string $branch): array {
                    $value = (float) $items->sum('saldo');

                    return ['name' => $branch, 'value_raw' => $value, 'value' => $this->formatCurrency($value)];
                })
                ->values()
                ->all();

            $scopes[$option['key']] = [
                'available' => collect($cards)->contains(fn (array $card): bool => (float) $card['value_raw'] !== 0.0),
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'period' => Carbon::parse($period)->toDateString(),
                'period_label' => $this->formatPeriod((string) $period),
                'cards' => $cards,
                'branches' => $profitRows,
            ];
        }

        return $scopes;
    }

    /**
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @param array<int, string> $branches
     */
    private function buildTimeseries(?string $period, array $scopeOptions, array $branches): array
    {
        $empty = [
            'available' => false,
            'source' => self::SNAPSHOT_TABLE,
            'unit' => 'Rp Juta',
            'groups' => $this->timeseriesGroups(),
            'scope_options' => $scopeOptions,
            'scopes' => [],
            'labels' => [],
            'series' => [],
        ];

        if (!$period || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return $empty;
        }

        $branchColumn = $this->snapshotBranchColumn();
        if (!$branchColumn) {
            return $empty;
        }

        $end = Carbon::parse($period)->toDateString();
        $start = Carbon::parse($end)->subMonths(12)->startOfMonth()->toDateString();
        $query = $this->summarySnapshotQuery($branches)
            ->whereBetween('snapshot_period', [$start, $end])
            ->selectRaw('snapshot_period')
            ->selectRaw("{$branchColumn} as branch_label");

        foreach ($this->timeseriesSqlMetrics() as $alias => $columns) {
            $query->selectRaw($this->sumColumnsSql($columns, $alias));
        }

        $rows = $query
            ->groupBy('snapshot_period', $branchColumn)
            ->orderBy('snapshot_period')
            ->get();

        $dailyScopes = ['area6' => []];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->snapshot_period)->toDateString();
            $scopeKey = $this->scopeKey((string) $row->branch_label);
            $metrics = $this->rowMetrics($row, array_keys($this->timeseriesSqlMetrics()));
            $dailyScopes[$scopeKey][$date] = $metrics;
            $dailyScopes['area6'][$date] = $this->sumMetricRows($dailyScopes['area6'][$date] ?? [], $metrics);
        }

        $scopeViews = [];
        foreach ($scopeOptions as $option) {
            $scopeViews[$option['key']] = $this->formatTimeseriesScope(
                $dailyScopes[$option['key']] ?? [],
                $option['key'],
                $option['label'],
                $end
            );
        }

        $area = $scopeViews['area6'] ?? ['labels' => [], 'series' => []];

        return array_merge($empty, [
            'available' => collect($scopeViews)->contains(fn (array $scope): bool => (bool) ($scope['available'] ?? false)),
            'scopes' => $scopeViews,
            'labels' => $area['labels'] ?? [],
            'series' => array_values($area['series'] ?? []),
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function timeseriesGroups(): array
    {
        return [
            'business' => ['label' => 'Skala Bisnis', 'description' => 'Pergerakan total Dana dan OS.', 'keys' => ['simpanan', 'os']],
            'funding' => ['label' => 'Funding Mix', 'description' => 'Giro, tabungan, deposito, dan CASA.', 'keys' => ['giro', 'tabungan', 'deposito', 'casa']],
            'segments' => ['label' => 'Segmen Kredit', 'description' => 'OS SME, Mikro, dan Konsumer.', 'keys' => ['sme_os', 'micro_os', 'consumer_os']],
            'quality' => ['label' => 'Kualitas Kredit', 'description' => 'Nominal dan rasio SML serta NPL.', 'keys' => ['sml', 'npl', 'sml_ratio', 'npl_ratio']],
            'ratios' => ['label' => 'Rasio Utama', 'description' => 'LDR, CASA, SML, dan NPL.', 'keys' => ['ldr_ratio', 'casa_ratio', 'sml_ratio', 'npl_ratio']],
        ];
    }

    /** @return array<string, array<int, string>> */
    private function timeseriesSqlMetrics(): array
    {
        return [
            'simpanan' => ['total_simpanan'],
            'giro' => ['giro_ritel', 'giro_mikro', 'giro_wholesale'],
            'tabungan' => ['tabungan_ritel', 'tabungan_mikro', 'tabungan_wholesale'],
            'deposito' => ['deposito_ritel', 'deposito_mikro', 'deposito_wholesale'],
            'casa' => ['total_casa'],
            'os' => ['total_os_non_commercial'],
            'sml' => ['total_sml_abs_non_commercial'],
            'npl' => ['total_npl_abs_non_commercial'],
            'sme_os' => ['sme_os'],
            'micro_os' => ['micro_os'],
            'consumer_os' => ['consumer_os'],
        ];
    }

    /**
     * @param array<string, array<string, float>> $dailyRows
     * @return array<string, mixed>
     */
    private function formatTimeseriesScope(
        array $dailyRows,
        string $scopeKey,
        string $scopeLabel,
        ?string $dailyEndPeriod = null
    ): array
    {
        ksort($dailyRows);
        $monthly = collect($dailyRows)
            ->map(fn (array $metrics, string $date): array => ['date' => $date, 'metrics' => $metrics])
            ->groupBy(fn (array $row): string => Carbon::parse($row['date'])->format('Y-m'))
            ->map(fn (Collection $rows): array => $rows->last())
            ->values();

        $definitions = [
            'simpanan' => ['label' => 'Simpanan', 'format' => 'currency', 'axis' => 'y', 'color' => '#0857c3'],
            'os' => ['label' => 'Outstanding', 'format' => 'currency', 'axis' => 'y', 'color' => '#10b981'],
            'giro' => ['label' => 'Giro', 'format' => 'currency', 'axis' => 'y', 'color' => '#307fe2'],
            'tabungan' => ['label' => 'Tabungan', 'format' => 'currency', 'axis' => 'y', 'color' => '#71c5e8'],
            'deposito' => ['label' => 'Deposito', 'format' => 'currency', 'axis' => 'y', 'color' => '#ccad95'],
            'casa' => ['label' => 'CASA', 'format' => 'currency', 'axis' => 'y', 'color' => '#059669'],
            'sme_os' => ['label' => 'OS SME', 'format' => 'currency', 'axis' => 'y', 'color' => '#1155c8'],
            'micro_os' => ['label' => 'OS Mikro', 'format' => 'currency', 'axis' => 'y', 'color' => '#00a6d6'],
            'consumer_os' => ['label' => 'OS Konsumer', 'format' => 'currency', 'axis' => 'y', 'color' => '#7c3aed'],
            'sml' => ['label' => 'SML Nominal', 'format' => 'currency', 'axis' => 'y', 'color' => '#f59e0b'],
            'npl' => ['label' => 'NPL Nominal', 'format' => 'currency', 'axis' => 'y', 'color' => '#ef4444'],
            'sml_ratio' => ['label' => 'Rasio SML', 'format' => 'percent', 'axis' => 'y1', 'color' => '#d97706'],
            'npl_ratio' => ['label' => 'Rasio NPL', 'format' => 'percent', 'axis' => 'y1', 'color' => '#dc2626'],
            'ldr_ratio' => ['label' => 'LDR', 'format' => 'percent', 'axis' => 'y1', 'color' => '#7c3aed'],
            'casa_ratio' => ['label' => 'CASA', 'format' => 'percent', 'axis' => 'y1', 'color' => '#059669'],
        ];

        $series = [];
        foreach ($definitions as $key => $definition) {
            $values = $monthly->map(function (array $row) use ($key): float {
                $metrics = $row['metrics'];

                return match ($key) {
                    'sml_ratio' => $this->percentOf((float) ($metrics['sml'] ?? 0.0), (float) ($metrics['os'] ?? 0.0)),
                    'npl_ratio' => $this->percentOf((float) ($metrics['npl'] ?? 0.0), (float) ($metrics['os'] ?? 0.0)),
                    'ldr_ratio' => $this->percentOf((float) ($metrics['os'] ?? 0.0), (float) ($metrics['simpanan'] ?? 0.0)),
                    'casa_ratio' => $this->percentOf((float) ($metrics['casa'] ?? 0.0), (float) ($metrics['simpanan'] ?? 0.0)),
                    default => (float) ($metrics[$key] ?? 0.0) / 1000000,
                };
            })->all();

            $series[$key] = array_merge($definition, [
                'key' => $key,
                'values' => $values,
                'display_values' => collect($values)->map(
                    fn (float $value): string => $definition['format'] === 'percent'
                        ? $this->formatPercent($value)
                        : $this->formatCurrency($value * 1000000)
                )->all(),
            ]);
        }

        $latestDailyDate = $dailyEndPeriod ?: collect(array_keys($dailyRows))->last();
        $dailyEndDate = $latestDailyDate
            ? Carbon::parse((string) $latestDailyDate)->toDateString()
            : null;
        $dailyStartDate = $dailyEndDate
            ? Carbon::parse($dailyEndDate)
                ->startOfMonth()
                ->subMonthsNoOverflow(3)
                ->toDateString()
            : null;
        $availableDailyRows = collect($dailyRows)
            ->filter(
                fn (array $metrics, string $date): bool => (!$dailyStartDate || $date >= $dailyStartDate)
                    && (!$dailyEndDate || $date <= $dailyEndDate)
            );
        $daily = collect();
        if ($dailyStartDate && $dailyEndDate) {
            $cursor = Carbon::parse($dailyStartDate);
            $endDate = Carbon::parse($dailyEndDate);
            while ($cursor->lte($endDate)) {
                $date = $cursor->toDateString();
                $daily->push([
                    'date' => $date,
                    'metrics' => $availableDailyRows->get($date),
                ]);
                $cursor->addDay();
            }
        }
        $dailySeries = [];
        foreach ($definitions as $key => $definition) {
            $values = $daily->map(function (array $row) use ($key): ?float {
                $metrics = $row['metrics'];
                if (!is_array($metrics)) {
                    return null;
                }

                return match ($key) {
                    'sml_ratio' => $this->percentOf((float) ($metrics['sml'] ?? 0.0), (float) ($metrics['os'] ?? 0.0)),
                    'npl_ratio' => $this->percentOf((float) ($metrics['npl'] ?? 0.0), (float) ($metrics['os'] ?? 0.0)),
                    'ldr_ratio' => $this->percentOf((float) ($metrics['os'] ?? 0.0), (float) ($metrics['simpanan'] ?? 0.0)),
                    'casa_ratio' => $this->percentOf((float) ($metrics['casa'] ?? 0.0), (float) ($metrics['simpanan'] ?? 0.0)),
                    default => (float) ($metrics[$key] ?? 0.0) / 1000000,
                };
            })->all();

            $dailySeries[$key] = array_merge($definition, [
                'key' => $key,
                'values' => $values,
                'display_values' => collect($values)->map(
                    fn (?float $value): string => $value === null
                        ? '-'
                        : ($definition['format'] === 'percent'
                            ? $this->formatPercent($value)
                            : $this->formatCurrency($value * 1000000))
                )->all(),
            ]);
        }

        return [
            'available' => $monthly->isNotEmpty(),
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'labels' => $monthly->map(fn (array $row): string => Carbon::parse($row['date'])->translatedFormat('M y'))->all(),
            'periods' => $monthly->pluck('date')->all(),
            'series' => $series,
            'daily' => [
                'available' => $availableDailyRows->isNotEmpty(),
                'start_period' => $dailyStartDate,
                'end_period' => $dailyEndDate,
                'labels' => $daily->map(fn (array $row): string => Carbon::parse($row['date'])->translatedFormat('d M y'))->all(),
                'periods' => $daily->pluck('date')->all(),
                'series' => $dailySeries,
            ],
        ];
    }

    /**
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @param array<int, string> $branches
     * @param array<string, mixed> $summaryScopes
     * @return array{periods: array<string, array<string, string>>, scopes: array<string, mixed>}
     */
    private function buildComparisonPayload(
        ?string $period,
        array $scopeOptions,
        array $branches,
        array $summaryScopes,
        array $prognosa = []
    ): array {
        $prognosaMeta = (array) data_get($prognosa, 'meta', []);
        if (!$period || !Schema::hasTable(self::SNAPSHOT_TABLE) || !$this->snapshotBranchColumn()) {
            return ['periods' => [], 'scopes' => [], 'prognosa' => $prognosaMeta];
        }

        $periods = $this->resolveComparisonPeriods($period, $branches);
        if ($periods === []) {
            return ['periods' => [], 'scopes' => [], 'prognosa' => $prognosaMeta];
        }
        $prognosaMeta['comparison_position_date'] = data_get($periods, 'current.date');
        $prognosaMeta['comparison_position_label'] = data_get($periods, 'current.label');

        $snapshotRows = $this->loadComparisonSnapshotRows(
            collect($periods)->pluck('date')->unique()->values()->all(),
            $branches
        );
        $rkaScopes = $this->buildComparisonRkaScopes($period, $scopeOptions);
        $scopes = [];
        foreach ($scopeOptions as $option) {
            $scopes[$option['key']] = $this->buildComparisonScope(
                $snapshotRows,
                $periods,
                $option['key'],
                $option['label'],
                (array) ($summaryScopes[$option['key']] ?? []),
                (array) ($rkaScopes[$option['key']] ?? []),
                (array) data_get($prognosa, ['scopes', $option['key']], [])
            );
        }

        if (isset($scopes['area6'])) {
            $branchScopes = collect($scopeOptions)
                ->reject(fn (array $option): bool => $option['key'] === 'area6')
                ->filter(fn (array $option): bool => isset($scopes[$option['key']]))
                ->values();

            $scopes['area6']['funding']['branches'] = $branchScopes->map(
                fn (array $option): array => array_merge(
                    [
                        'scope_key' => $option['key'],
                        'scope_label' => $option['label'],
                    ],
                    (array) data_get($scopes, "{$option['key']}.funding", [])
                )
            )->all();
            $scopes['area6']['credit']['branches'] = $branchScopes->map(
                fn (array $option): array => array_merge(
                    [
                        'scope_key' => $option['key'],
                        'scope_label' => $option['label'],
                    ],
                    (array) data_get($scopes, "{$option['key']}.credit", [])
                )
            )->all();
        }

        return ['periods' => $periods, 'scopes' => $scopes, 'prognosa' => $prognosaMeta];
    }

    /**
     * Build one exact-branch RKA matrix, then aggregate the same values for
     * Area 6. Definitions mirror the operational Dana and Pinjaman dashboards.
     *
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @return array<string, array<string, float>>
     */
    private function buildComparisonRkaScopes(string $period, array $scopeOptions): array
    {
        if (!Schema::hasTable('rka')) {
            return [];
        }

        try {
            $lookup = app(RkaLookupService::class);
            $rkaPeriod = app(DashboardHarianSnapshotService::class)
                ->resolveEffectiveRkaPeriod(null, $period);
            $rkaDate = Carbon::parse($rkaPeriod ?: $period);
            $monthColumn = $lookup->resolveMonthColumn($rkaDate);
            $year = (int) $rkaDate->format('Y');
            $definitions = $this->comparisonRkaDefinitions();
            $scopes = [];

            foreach ($scopeOptions as $option) {
                if ($option['key'] === 'area6') {
                    continue;
                }

                $values = $lookup->aggregateForScope(
                    $definitions,
                    $monthColumn,
                    $option['label'],
                    null,
                    $year
                );
                $scopes[$option['key']] = $this->finalizeComparisonRkaValues($values);
            }

            $areaValues = [];
            foreach ($scopes as $values) {
                foreach ($values as $metric => $value) {
                    $areaValues[$metric] = round(
                        (float) ($areaValues[$metric] ?? 0.0) + (float) $value,
                        2
                    );
                }
            }
            $scopes['area6'] = $areaValues;

            return $scopes;
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function comparisonRkaDefinitions(): array
    {
        $retailOutlets = ['uker_contains_any' => ['KC', 'KCP'], 'include_kanca_summary' => true];
        $microOutlets = ['uker_contains_any' => ['UNIT']];

        return [
            'simpanan' => ['mata_anggaran' => ['A.1. DPK Retail Funding Total', 'A.2. DPK Korporasi']],
            'funding_retail' => array_merge(
                ['mata_anggaran' => ['A.1. DPK Retail Funding Total']],
                $retailOutlets
            ),
            'funding_wholesale' => array_merge(
                ['mata_anggaran' => ['A.2. DPK Korporasi']],
                $retailOutlets
            ),
            'funding_micro' => array_merge(
                ['mata_anggaran' => ['A.1. DPK Retail Funding Total']],
                $microOutlets
            ),
            'giro' => ['mata_anggaran' => ['Giro Retail Funding Total', 'A.2.a. Giro Korporasi']],
            'tabungan' => ['mata_anggaran' => ['Tabungan Retail Funding Total']],
            'deposito' => ['mata_anggaran' => ['Deposito Retail Funding Total', 'A.2.b. Deposito Korporasi']],

            'os' => ['mata_anggaran' => ['B. KREDIT TOTAL']],
            'sml' => ['mata_anggaran' => ['DPK Rp Total']],
            'npl' => ['mata_anggaran' => ['NPL Rp Total']],

            'sme_os' => array_merge(
                ['mata_anggaran' => ['B.2. SMALL', 'B.3. MEDIUM']],
                $retailOutlets
            ),
            'sme_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp Small', 'DPK Rp Medium']],
                $retailOutlets
            ),
            'sme_npl' => array_merge(
                ['mata_anggaran' => ['NPL Rp Small', 'NPL Rp Medium']],
                $retailOutlets
            ),
            'consumer_os' => array_merge(
                ['mata_anggaran' => ['B.4. KONSUMER']],
                $retailOutlets
            ),
            'consumer_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp Konsumer']],
                $retailOutlets
            ),
            'consumer_npl' => array_merge(
                ['mata_anggaran' => ['NPL Rp Konsumer']],
                $retailOutlets
            ),
            'micro_os' => ['mata_anggaran' => ['B.1. MIKRO']],
            'micro_sml' => ['mata_anggaran' => ['DPK Rp Mikro']],
            'micro_npl' => ['mata_anggaran' => ['NPL Rp Mikro']],

            'sme_non_cashcoll_os' => array_merge(
                ['mata_anggaran' => ['B.2.a. Kredit Kecil Non Cash Collateral']],
                $retailOutlets
            ),
            'sme_non_cashcoll_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp Kecil Non Cash Collateral']],
                $retailOutlets
            ),
            'sme_non_cashcoll_npl' => array_merge(
                ['mata_anggaran' => ['NPL Rp Kecil Non Cash Collateral']],
                $retailOutlets
            ),
            'sme_cashcoll_os' => array_merge(
                ['mata_anggaran' => ['B.2.b. Kredit Kecil Cash Collateral']],
                $retailOutlets
            ),
            'sme_cashcoll_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp Kecil Cash Collateral']],
                $retailOutlets
            ),
            'sme_small_npl_basis' => array_merge(
                ['mata_anggaran' => ['NPL Rp Small']],
                $retailOutlets
            ),

            'consumer_briguna_os' => array_merge(
                ['mata_anggaran' => ['B.5.a. Briguna']],
                $retailOutlets
            ),
            'consumer_briguna_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp Briguna']],
                $retailOutlets
            ),
            'consumer_briguna_npl' => array_merge(
                ['mata_anggaran' => ['NPL Rp Briguna']],
                $retailOutlets
            ),
            'consumer_kpr_os' => array_merge(
                ['mata_anggaran' => ['B.5.b. KPR']],
                $retailOutlets
            ),
            'consumer_kpr_sml' => array_merge(
                ['mata_anggaran' => ['DPK Rp KPR']],
                $retailOutlets
            ),
            'consumer_kpr_npl' => array_merge(
                ['mata_anggaran' => ['NPL Rp KPR']],
                $retailOutlets
            ),

            'micro_briguna_os' => ['mata_anggaran' => ['B.1.b. Briguna Mikro']],
            'micro_briguna_sml' => ['mata_anggaran' => ['DPK Rp Briguna Mikro']],
            'micro_briguna_npl' => ['mata_anggaran' => ['NPL Rp Briguna Mikro']],
            'micro_kupedes_os' => ['mata_anggaran' => ['B.1.a. Kupedes Komersial']],
            'micro_kupedes_sml' => ['mata_anggaran' => ['DPK Rp Kupedes Komersial']],
            'micro_kupedes_npl' => ['mata_anggaran' => ['NPL Rp Kupedes Komersial']],
            'micro_kur_mikro_os' => ['mata_anggaran' => ['B.1.c. KUR Mikro']],
            'micro_kur_mikro_sml' => ['mata_anggaran' => ['DPK Rp KUR Mikro']],
            'micro_kur_mikro_npl' => ['mata_anggaran' => ['NPL Rp KUR Mikro']],
            'micro_kur_kecil_os' => ['mata_anggaran' => ['B.1.d. KUR Kecil']],
            'micro_kur_kecil_sml' => ['mata_anggaran' => ['DPK Rp KUR Kecil']],
            'micro_kur_kecil_npl' => ['mata_anggaran' => ['NPL Rp KUR Kecil']],
            'micro_kpp_os' => ['mata_anggaran' => ['B.1.e. KPP']],
            'micro_kpp_sml' => ['mata_anggaran' => ['DPK Rp KPP']],
            'micro_kpp_npl' => ['mata_anggaran' => ['NPL Rp KPP']],
        ];
    }

    /**
     * The RKA source has no standalone NPL Cash Collateral row. Derive it from
     * NPL Small less NPL Non Cash Collateral, matching the displayed SME split.
     *
     * @param array<string, float|int> $values
     * @return array<string, float>
     */
    private function finalizeComparisonRkaValues(array $values): array
    {
        $result = collect($values)
            ->map(fn ($value): float => round((float) $value, 2))
            ->all();
        $result['sme_cashcoll_npl'] = max(
            0.0,
            round(
                (float) ($result['sme_small_npl_basis'] ?? 0.0)
                - (float) ($result['sme_non_cashcoll_npl'] ?? 0.0),
                2
            )
        );
        unset($result['sme_small_npl_basis']);

        return $result;
    }

    /**
     * @param array<int, string> $branches
     * @return array<string, array<string, string>>
     */
    private function resolveComparisonPeriods(string $period, array $branches): array
    {
        $selected = Carbon::parse($period)->toDateString();
        $available = $this->summarySnapshotQuery($branches)
            ->where('snapshot_period', '<=', $selected)
            ->select('snapshot_period')
            ->distinct()
            ->orderBy('snapshot_period')
            ->pluck('snapshot_period')
            ->map(fn ($value): string => Carbon::parse($value)->toDateString())
            ->unique()
            ->values();

        if ($available->isEmpty()) {
            return [];
        }

        $resolveOnOrBefore = static function (string $target) use ($available): string {
            return (string) ($available->filter(fn (string $date): bool => $date <= $target)->last() ?: $target);
        };
        $current = $resolveOnOrBefore($selected);
        $currentDate = Carbon::parse($current);
        $previous = (string) ($available->filter(fn (string $date): bool => $date < $current)->last()
            ?: $currentDate->copy()->subDay()->toDateString());
        $targets = [
            'yoy' => $currentDate->copy()->subYearNoOverflow()->toDateString(),
            'ytd' => $currentDate->copy()->subYear()->endOfYear()->toDateString(),
            'm2' => $currentDate->copy()->subMonthsNoOverflow(2)->toDateString(),
            'mom' => $currentDate->copy()->subMonthNoOverflow()->toDateString(),
            'mtd' => $currentDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        ];
        $labels = [
            'yoy' => 'YoY',
            'ytd' => 'YtD',
            'm2' => 'M-2',
            'mom' => 'MoM',
            'mtd' => 'MtD',
            'dtd' => 'DtD',
            'current' => 'Posisi',
        ];

        $resolved = [];
        foreach ($targets as $key => $target) {
            $date = $resolveOnOrBefore($target);
            $resolved[$key] = [
                'key' => $key,
                'name' => $labels[$key],
                'date' => $date,
                'label' => Carbon::parse($date)->translatedFormat('d M y'),
            ];
        }
        $resolved['dtd'] = [
            'key' => 'dtd',
            'name' => $labels['dtd'],
            'date' => $previous,
            'label' => Carbon::parse($previous)->translatedFormat('d M y'),
        ];
        $resolved['current'] = [
            'key' => 'current',
            'name' => $labels['current'],
            'date' => $current,
            'label' => Carbon::parse($current)->translatedFormat('d M y'),
        ];

        return $resolved;
    }

    /**
     * @param array<int, string> $periods
     * @param array<int, string> $branches
     * @return array<string, array<string, array<string, float>>>
     */
    private function loadComparisonSnapshotRows(array $periods, array $branches): array
    {
        if ($periods === []) {
            return [];
        }

        $branchColumn = $this->snapshotBranchColumn();
        $metrics = $this->comparisonMetricColumns();
        $query = $this->summarySnapshotQuery($branches)
            ->whereIn('snapshot_period', $periods)
            ->selectRaw('snapshot_period')
            ->selectRaw("{$branchColumn} as branch_label");

        foreach ($metrics as $alias => $columns) {
            $query->selectRaw($this->sumColumnsSql($columns, $alias));
        }

        $rows = $query
            ->groupBy('snapshot_period', $branchColumn)
            ->orderBy('snapshot_period')
            ->get();
        $result = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->snapshot_period)->toDateString();
            $scopeKey = $this->scopeKey((string) $row->branch_label);
            $values = $this->rowMetrics($row, array_keys($metrics));
            $result[$date][$scopeKey] = $values;
            $result[$date]['area6'] = $this->sumMetricRows($result[$date]['area6'] ?? [], $values);
        }

        return $result;
    }

    /** @return array<string, array<int, string>> */
    private function comparisonMetricColumns(): array
    {
        return array_merge($this->timeseriesSqlMetrics(), [
            'funding_retail' => ['giro_ritel', 'tabungan_ritel', 'deposito_ritel'],
            'funding_wholesale' => ['giro_wholesale', 'tabungan_wholesale', 'deposito_wholesale'],
            'funding_micro' => ['giro_mikro', 'tabungan_mikro', 'deposito_mikro'],
            'sme_sml' => ['kecil_non_cashcoll_sml', 'cashcoll_sml'],
            'sme_npl' => ['kecil_non_cashcoll_npl', 'cashcoll_npl'],
            'consumer_sml' => ['briguna_konsumer_sml', 'kpr_sml', 'kkb_sml'],
            'consumer_npl' => ['briguna_konsumer_npl', 'kpr_npl', 'kkb_npl'],
            'micro_sml' => ['briguna_mikro_sml', 'kupedes_sml', 'kur_mikro_sml', 'kur_kecil_sml', 'kur_kpp_sml'],
            'micro_npl' => ['briguna_mikro_npl', 'kupedes_npl', 'kur_mikro_npl', 'kur_kecil_npl', 'kur_kpp_npl'],
            'sme_non_cashcoll_os' => ['kecil_non_cashcoll_os'],
            'sme_non_cashcoll_sml' => ['kecil_non_cashcoll_sml'],
            'sme_non_cashcoll_npl' => ['kecil_non_cashcoll_npl'],
            'sme_cashcoll_os' => ['cashcoll_os'],
            'sme_cashcoll_sml' => ['cashcoll_sml'],
            'sme_cashcoll_npl' => ['cashcoll_npl'],
            'consumer_briguna_os' => ['briguna_konsumer_os'],
            'consumer_briguna_sml' => ['briguna_konsumer_sml'],
            'consumer_briguna_npl' => ['briguna_konsumer_npl'],
            'consumer_kpr_os' => ['kpr_os'],
            'consumer_kpr_sml' => ['kpr_sml'],
            'consumer_kpr_npl' => ['kpr_npl'],
            'micro_briguna_os' => ['briguna_mikro_os'],
            'micro_briguna_sml' => ['briguna_mikro_sml'],
            'micro_briguna_npl' => ['briguna_mikro_npl'],
            'micro_kupedes_os' => ['kupedes_os'],
            'micro_kupedes_sml' => ['kupedes_sml'],
            'micro_kupedes_npl' => ['kupedes_npl'],
            'micro_kur_mikro_os' => ['kur_mikro_os'],
            'micro_kur_mikro_sml' => ['kur_mikro_sml'],
            'micro_kur_mikro_npl' => ['kur_mikro_npl'],
            'micro_kur_kecil_os' => ['kur_kecil_os'],
            'micro_kur_kecil_sml' => ['kur_kecil_sml'],
            'micro_kur_kecil_npl' => ['kur_kecil_npl'],
            'micro_kpp_os' => ['kur_kpp_os'],
            'micro_kpp_sml' => ['kur_kpp_sml'],
            'micro_kpp_npl' => ['kur_kpp_npl'],
        ]);
    }

    /**
     * @param array<string, array<string, array<string, float>>> $snapshotRows
     * @param array<string, array<string, string>> $periods
     * @param array<string, mixed> $summaryScope
     * @param array<string, float> $rkaValues
     * @return array<string, mixed>
     */
    private function buildComparisonScope(
        array $snapshotRows,
        array $periods,
        string $scopeKey,
        string $scopeLabel,
        array $summaryScope,
        array $rkaValues,
        array $prognosaScope = []
    ): array {
        $fundingSegments = [
            'retail' => ['label' => 'Ritel', 'metric' => 'funding_retail'],
            'wholesale' => ['label' => 'Wholesale', 'metric' => 'funding_wholesale'],
            'micro' => ['label' => 'Mikro', 'metric' => 'funding_micro'],
        ];
        $fundingProducts = [
            'giro' => ['label' => 'Giro', 'metric' => 'giro'],
            'tabungan' => ['label' => 'Tabungan', 'metric' => 'tabungan'],
            'deposito' => ['label' => 'Deposito', 'metric' => 'deposito'],
        ];
        $creditSegments = [
            'sme' => [
                'label' => 'SME',
                'rka' => 'sme_os',
                'products' => [
                    'non_cashcoll' => ['label' => 'Kecil Non Cashcoll', 'prefix' => 'sme_non_cashcoll'],
                    'cashcoll' => ['label' => 'Cashcoll', 'prefix' => 'sme_cashcoll'],
                ],
            ],
            'consumer' => [
                'label' => 'Konsumer',
                'rka' => 'consumer_os',
                'products' => [
                    'briguna' => ['label' => 'Briguna', 'prefix' => 'consumer_briguna'],
                    'kpr' => ['label' => 'KPR', 'prefix' => 'consumer_kpr'],
                ],
            ],
            'micro' => [
                'label' => 'Mikro',
                'rka' => 'micro_os',
                'products' => [
                    'briguna_mikro' => ['label' => 'Briguna Mikro', 'prefix' => 'micro_briguna'],
                    'kupedes' => ['label' => 'Kupedes', 'prefix' => 'micro_kupedes'],
                    'kur_mikro' => ['label' => 'KUR Mikro', 'prefix' => 'micro_kur_mikro'],
                    'kur_kecil' => ['label' => 'KUR Kecil', 'prefix' => 'micro_kur_kecil'],
                    'kpp' => ['label' => 'KUR KPP', 'prefix' => 'micro_kpp'],
                ],
            ],
        ];
        $summaryMetrics = (array) ($summaryScope['metrics'] ?? []);
        $rka = static function (string $metric) use ($rkaValues, $summaryMetrics): ?float {
            $direct = $rkaValues[$metric] ?? null;
            if (array_key_exists($metric, $rkaValues) && is_numeric($direct) && (float) $direct >= 0.0) {
                return (float) $direct;
            }

            $summary = data_get($summaryMetrics, "{$metric}.rka");

            return is_numeric($summary) && (float) $summary > 0.0
                ? (float) $summary
                : null;
        };
        $prognosa = static fn (string $metric): array => (array) data_get(
            $prognosaScope,
            ['metrics', $metric],
            []
        );

        $funding = [
            'total' => $this->comparisonMetric(
                $snapshotRows,
                $periods,
                $scopeKey,
                'simpanan',
                $rka('simpanan'),
                false,
                $prognosa('simpanan')
            ),
            'segments' => collect($fundingSegments)->map(
                fn (array $definition, string $key): array => array_merge(
                    ['key' => $key, 'label' => $definition['label']],
                    $this->comparisonMetric(
                        $snapshotRows,
                        $periods,
                        $scopeKey,
                        $definition['metric'],
                        $rka($definition['metric']),
                        false,
                        $prognosa($definition['metric'])
                    )
                )
            )->values()->all(),
            'products' => collect($fundingProducts)->map(
                fn (array $definition, string $key): array => array_merge(
                    ['key' => $key, 'label' => $definition['label']],
                    $this->comparisonMetric(
                        $snapshotRows,
                        $periods,
                        $scopeKey,
                        $definition['metric'],
                        $rka($definition['metric']),
                        false,
                        $prognosa($definition['metric'])
                    )
                )
            )->values()->all(),
            'branches' => [],
        ];

        $creditTotal = $this->comparisonCreditMetrics(
            $snapshotRows,
            $periods,
            $scopeKey,
            ['os' => 'os', 'sml' => 'sml', 'npl' => 'npl'],
            ['os' => $rka('os'), 'sml' => $rka('sml'), 'npl' => $rka('npl')],
            [
                'os' => $prognosa('os'),
                'sml' => $prognosa('sml'),
                'npl' => $prognosa('npl'),
            ]
        );
        $segments = collect($creditSegments)->map(function (array $definition, string $key) use (
            $snapshotRows,
            $periods,
            $scopeKey,
            $rka,
            $prognosa
        ): array {
            $metrics = $this->comparisonCreditMetrics(
                $snapshotRows,
                $periods,
                $scopeKey,
                ['os' => "{$key}_os", 'sml' => "{$key}_sml", 'npl' => "{$key}_npl"],
                [
                    'os' => $rka("{$key}_os"),
                    'sml' => $rka("{$key}_sml"),
                    'npl' => $rka("{$key}_npl"),
                ],
                [
                    'os' => $prognosa("{$key}_os"),
                    'sml' => $prognosa("{$key}_sml"),
                    'npl' => $prognosa("{$key}_npl"),
                ]
            );
            $products = collect($definition['products'])->map(function (array $product, string $productKey) use (
                $snapshotRows,
                $periods,
                $scopeKey,
                $rka,
                $prognosa
            ): array {
                $aliases = [
                    'os' => $product['prefix'] . '_os',
                    'sml' => $product['prefix'] . '_sml',
                    'npl' => $product['prefix'] . '_npl',
                ];

                return array_merge(
                    ['key' => $productKey, 'label' => $product['label']],
                    $this->comparisonCreditMetrics(
                        $snapshotRows,
                        $periods,
                        $scopeKey,
                        $aliases,
                        [
                            'os' => $rka($aliases['os']),
                            'sml' => $rka($aliases['sml']),
                            'npl' => $rka($aliases['npl']),
                        ],
                        [
                            'os' => $prognosa($aliases['os']),
                            'sml' => $prognosa($aliases['sml']),
                            'npl' => $prognosa($aliases['npl']),
                        ]
                    )
                );
            })->values()->all();

            return array_merge(
                ['key' => $key, 'label' => $definition['label'], 'products' => $products],
                $metrics
            );
        })->values()->all();

        return [
            'available' => (bool) ($funding['total']['available'] || $creditTotal['os']['available']),
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'funding' => $funding,
            'credit' => [
                'total' => $creditTotal,
                'segments' => $segments,
                'branches' => [],
            ],
        ];
    }

    /**
     * @param array<string, array<string, array<string, float>>> $snapshotRows
     * @param array<string, array<string, string>> $periods
     * @param array<string, string> $aliases
     * @param array<string, float|null> $rka
     * @return array<string, mixed>
     */
    private function comparisonCreditMetrics(
        array $snapshotRows,
        array $periods,
        string $scopeKey,
        array $aliases,
        array $rka = [],
        array $prognosa = []
    ): array {
        $os = $this->comparisonMetric(
            $snapshotRows,
            $periods,
            $scopeKey,
            $aliases['os'],
            $rka['os'] ?? null,
            false,
            (array) ($prognosa['os'] ?? [])
        );
        $sml = $this->comparisonMetric(
            $snapshotRows,
            $periods,
            $scopeKey,
            $aliases['sml'],
            $rka['sml'] ?? null,
            true,
            (array) ($prognosa['sml'] ?? [])
        );
        $npl = $this->comparisonMetric(
            $snapshotRows,
            $periods,
            $scopeKey,
            $aliases['npl'],
            $rka['npl'] ?? null,
            true,
            (array) ($prognosa['npl'] ?? [])
        );

        $appendQualityRatios = function (array $quality) use ($periods, $os): array {
            $quality['ratio_positions'] = [];
            $quality['ratio_positions_fmt'] = [];
            foreach (array_keys($periods) as $periodKey) {
                $ratio = $this->percentOf(
                    (float) ($quality['positions'][$periodKey] ?? 0.0),
                    (float) ($os['positions'][$periodKey] ?? 0.0)
                );
                $quality['ratio_positions'][$periodKey] = $ratio;
                $quality['ratio_positions_fmt'][$periodKey] = $this->formatPercent($ratio);
            }
            $quality['ratio_deltas'] = [];
            foreach (['yoy', 'ytd', 'mom', 'mtd', 'dtd'] as $periodKey) {
                $quality['ratio_deltas'][$periodKey] =
                    (float) ($quality['ratio_positions']['current'] ?? 0.0)
                    - (float) ($quality['ratio_positions'][$periodKey] ?? 0.0);
            }

            return $quality;
        };
        $sml = $appendQualityRatios($sml);
        $npl = $appendQualityRatios($npl);
        $appendPrognosaRatio = function (array $quality) use ($os): array {
            $quality['prognosa_ratio'] = ($quality['prognosa_available'] ?? false)
                && ($os['prognosa_available'] ?? false)
                ? $this->percentOf(
                    (float) ($quality['prognosa'] ?? 0.0),
                    (float) ($os['prognosa'] ?? 0.0)
                )
                : null;
            $quality['prognosa_ratio_fmt'] = $quality['prognosa_ratio'] === null
                ? '-'
                : $this->formatPercent((float) $quality['prognosa_ratio']);

            return $quality;
        };
        $sml = $appendPrognosaRatio($sml);
        $npl = $appendPrognosaRatio($npl);

        return ['os' => $os, 'sml' => $sml, 'npl' => $npl];
    }

    /**
     * @param array<string, array<string, array<string, float>>> $snapshotRows
     * @param array<string, array<string, string>> $periods
     * @return array<string, mixed>
     */
    private function comparisonMetric(
        array $snapshotRows,
        array $periods,
        string $scopeKey,
        string $metric,
        ?float $rka = null,
        bool $inverseTarget = false,
        array $prognosa = []
    ): array {
        $positions = [];
        foreach ($periods as $periodKey => $definition) {
            $positions[$periodKey] = (float) data_get(
                $snapshotRows,
                [$definition['date'], $scopeKey, $metric],
                0.0
            );
        }
        $current = (float) ($positions['current'] ?? 0.0);
        $deltas = [];
        foreach (['yoy', 'ytd', 'mom', 'mtd', 'dtd'] as $periodKey) {
            $deltas[$periodKey] = $current - (float) ($positions[$periodKey] ?? 0.0);
        }
        $hasRka = $rka !== null && $rka >= 0.0;
        $gap = $hasRka ? $current - $rka : null;
        $achievement = $rka !== null && $rka > 0
            ? ($inverseTarget
                ? ($current > 0 ? ($rka / $current) * 100 : 100.0)
                : ($current / $rka) * 100)
            : null;
        $hasPrognosa = (bool) ($prognosa['available'] ?? false)
            && is_numeric($prognosa['value'] ?? null);
        $prognosaValue = $hasPrognosa ? (float) $prognosa['value'] : null;
        $prognosaDelta = $prognosaValue === null ? null : $prognosaValue - $current;

        return [
            'available' => collect($positions)->contains(fn (float $value): bool => $value != 0.0),
            'metric' => $metric,
            'positions' => $positions,
            'positions_fmt' => collect($positions)->map(
                fn (float $value): string => $this->formatCurrency($value)
            )->all(),
            'deltas' => $deltas,
            'deltas_fmt' => collect($deltas)->map(
                fn (float $value): string => $this->formatSignedCurrency($value)
            )->all(),
            'current' => $current,
            'current_fmt' => $this->formatCurrency($current),
            'rka' => $rka,
            'rka_fmt' => $hasRka ? $this->formatCurrency($rka) : '-',
            'gap' => $gap,
            'gap_fmt' => $gap === null ? '-' : $this->formatSignedCurrency($gap),
            'achievement' => $achievement,
            'achievement_fmt' => $achievement === null ? '-' : $this->formatPercent($achievement),
            'prognosa_available' => $hasPrognosa,
            'prognosa' => $prognosaValue,
            'prognosa_fmt' => $prognosaValue === null ? '-' : $this->formatCurrency($prognosaValue),
            'prognosa_delta' => $prognosaDelta,
            'prognosa_delta_fmt' => $prognosaDelta === null
                ? '-'
                : $this->formatSignedCurrency($prognosaDelta),
        ];
    }

    /**
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @param array<int, string> $branches
     */
    private function buildProductivity(?string $loanPeriod, array $scopeOptions, array $branches): array
    {
        $empty = [
            'available' => false,
            'source' => self::RM_SNAPSHOT_TABLE,
            'period' => null,
            'period_label' => 'Belum ada data',
            'categories' => [
                'retail_sme' => ['label' => 'RM Ritel - SME', 'segment' => 'SMALL'],
                'retail_consumer' => ['label' => 'RM Ritel - Konsumer', 'segment' => 'CONSUMER'],
                'micro' => ['label' => 'RM Mikro', 'segment' => 'MICRO'],
            ],
            'scope_options' => $scopeOptions,
            'scopes' => [],
        ];

        if (!Schema::hasTable(self::RM_SNAPSHOT_TABLE)) {
            return $empty;
        }

        $periodQuery = DB::table(self::RM_SNAPSHOT_TABLE);
        if ($loanPeriod) {
            $periodQuery->where('periode', '<=', $loanPeriod);
        }
        $period = $periodQuery->max('periode');
        if (!$period) {
            return $empty;
        }

        $rows = DB::table(self::RM_SNAPSHOT_TABLE)
            ->where('periode', $period)
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), array_map('strtoupper', $branches))
            ->whereIn('segmen', ['SMALL', 'CONSUMER', 'MICRO'])
            ->whereNotNull('rm')
            ->whereRaw("TRIM(rm) <> ''")
            ->selectRaw('cabang, segmen, rm, MIN(unit) as unit')
            ->selectRaw('COALESCE(SUM(realisasi_deb), 0) as realisasi_deb')
            ->selectRaw('COALESCE(SUM(realisasi_os), 0) as realisasi_os')
            ->selectRaw('COALESCE(SUM(loan_os), 0) as loan_os')
            ->selectRaw('COALESCE(SUM(total_deb), 0) as total_deb')
            ->selectRaw('COALESCE(SUM(sml_os), 0) as sml_os')
            ->selectRaw('COALESCE(SUM(npl_os), 0) as npl_os')
            ->selectRaw('COALESCE(SUM(restruk_os), 0) as restruk_os')
            ->groupBy('cabang', 'segmen', 'rm')
            ->get();

        $categoryDefinitions = $empty['categories'];
        $quadrantHistory = $this->buildRmQuadrantHistory(
            (string) $period,
            $scopeOptions,
            $branches,
            $categoryDefinitions
        );
        $scopes = [];
        foreach ($scopeOptions as $option) {
            $scopeRows = $option['key'] === 'area6'
                ? $rows
                : $rows->filter(fn ($row): bool => $this->scopeKey((string) $row->cabang) === $option['key']);
            $categories = [];

            foreach ($categoryDefinitions as $key => $definition) {
                $segmentRows = $scopeRows->where('segmen', $definition['segment'])
                    ->map(function ($row): array {
                        $realisasiOs = (float) $row->realisasi_os;
                        $realisasiDeb = (int) $row->realisasi_deb;
                        $loanOs = (float) $row->loan_os;
                        $lar = (float) $row->restruk_os + (float) $row->sml_os + (float) $row->npl_os;

                        return [
                            'name' => trim((string) $row->rm),
                            'branch' => trim((string) $row->cabang),
                            'unit' => trim((string) $row->unit),
                            'realisasi_deb' => $realisasiDeb,
                            'realisasi_os' => $realisasiOs,
                            'realisasi_os_fmt' => $this->formatCurrency($realisasiOs),
                            'average_ticket' => $realisasiDeb > 0 ? $realisasiOs / $realisasiDeb : 0.0,
                            'average_ticket_fmt' => $this->formatCurrency($realisasiDeb > 0 ? $realisasiOs / $realisasiDeb : 0.0),
                            'loan_os' => $loanOs,
                            'loan_os_fmt' => $this->formatCurrency($loanOs),
                            'lar' => $lar,
                            'lar_fmt' => $this->formatCurrency($lar),
                            'lar_pct' => $this->percentOf($lar, $loanOs),
                            'lar_pct_fmt' => $this->formatPercent($this->percentOf($lar, $loanOs)),
                        ];
                    })
                    ->sortByDesc('realisasi_os')
                    ->values();

                $totalRealisasi = (float) $segmentRows->sum('realisasi_os');
                $totalDeb = (int) $segmentRows->sum('realisasi_deb');
                $totalLoan = (float) $segmentRows->sum('loan_os');
                $totalLar = (float) $segmentRows->sum('lar');
                $rmCount = $segmentRows->count();

                $categories[$key] = [
                    'available' => $segmentRows->isNotEmpty(),
                    'key' => $key,
                    'label' => $definition['label'],
                    'segment' => $definition['segment'],
                    'total' => [
                        'rm_count' => $rmCount,
                        'realisasi_deb' => $totalDeb,
                        'realisasi_os' => $totalRealisasi,
                        'realisasi_os_fmt' => $this->formatCurrency($totalRealisasi),
                        'average_per_rm' => $rmCount > 0 ? $totalRealisasi / $rmCount : 0.0,
                        'average_per_rm_fmt' => $this->formatCurrency($rmCount > 0 ? $totalRealisasi / $rmCount : 0.0),
                        'average_ticket' => $totalDeb > 0 ? $totalRealisasi / $totalDeb : 0.0,
                        'average_ticket_fmt' => $this->formatCurrency($totalDeb > 0 ? $totalRealisasi / $totalDeb : 0.0),
                        'loan_os' => $totalLoan,
                        'loan_os_fmt' => $this->formatCurrency($totalLoan),
                        'lar' => $totalLar,
                        'lar_fmt' => $this->formatCurrency($totalLar),
                        'lar_pct' => $this->percentOf($totalLar, $totalLoan),
                        'lar_pct_fmt' => $this->formatPercent($this->percentOf($totalLar, $totalLoan)),
                    ],
                    'rows' => $segmentRows->take(16)->all(),
                    'quadrant_history' => (array) data_get(
                        $quadrantHistory,
                        "{$option['key']}.{$key}",
                        ['labels' => [], 'rows' => []]
                    ),
                ];
            }

            $scopes[$option['key']] = [
                'scope_key' => $option['key'],
                'scope_label' => $option['label'],
                'categories' => $categories,
            ];
        }

        return array_merge($empty, [
            'available' => $rows->isNotEmpty(),
            'period' => Carbon::parse($period)->toDateString(),
            'period_label' => $this->formatPeriod((string) $period),
            'scopes' => $scopes,
        ]);
    }

    /**
     * @param array<int, array{key: string, label: string}> $scopeOptions
     * @param array<int, string> $branches
     * @param array<string, array<string, string>> $categoryDefinitions
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function buildRmQuadrantHistory(
        string $period,
        array $scopeOptions,
        array $branches,
        array $categoryDefinitions
    ): array {
        if (!Schema::hasColumn(self::RM_SNAPSHOT_TABLE, 'quadrant')) {
            return [];
        }

        $end = Carbon::parse($period)->toDateString();
        $start = Carbon::parse($end)->startOfYear()->toDateString();
        $availablePeriods = DB::table(self::RM_SNAPSHOT_TABLE)
            ->whereBetween('periode', [$start, $end])
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), array_map('strtoupper', $branches))
            ->whereIn('segmen', ['SMALL', 'CONSUMER'])
            ->select('periode')
            ->distinct()
            ->orderBy('periode')
            ->pluck('periode')
            ->map(fn ($value): string => Carbon::parse($value)->toDateString())
            ->groupBy(fn (string $date): string => Carbon::parse($date)->format('Y-m'))
            ->map(fn (Collection $dates): string => (string) $dates->last())
            ->values();

        if ($availablePeriods->isEmpty()) {
            return [];
        }

        $rows = DB::table(self::RM_SNAPSHOT_TABLE)
            ->whereIn('periode', $availablePeriods->all())
            ->whereIn(DB::raw('UPPER(TRIM(cabang))'), array_map('strtoupper', $branches))
            ->whereIn('segmen', ['SMALL', 'CONSUMER'])
            ->whereNotNull('rm')
            ->whereRaw("TRIM(rm) <> ''")
            ->select('periode', 'cabang', 'segmen', 'rm', 'quadrant', 'realisasi_os')
            ->get();
        $consumerTargets = $this->consumerMonthlyTargetMap();

        $history = [];
        foreach ($scopeOptions as $option) {
            $scopeRows = $option['key'] === 'area6'
                ? $rows
                : $rows->filter(fn ($row): bool => $this->scopeKey((string) $row->cabang) === $option['key']);

            foreach ($categoryDefinitions as $categoryKey => $definition) {
                if (!in_array($definition['segment'], ['SMALL', 'CONSUMER'], true)) {
                    $history[$option['key']][$categoryKey] = ['labels' => [], 'rows' => []];
                    continue;
                }

                $segmentRows = $scopeRows->where('segmen', $definition['segment']);
                $monthlyRows = $availablePeriods->map(function (string $date) use (
                    $consumerTargets,
                    $definition,
                    $segmentRows
                ): array {
                    if ($definition['segment'] === 'CONSUMER') {
                        return $this->consumerQuadrantHistoryRow($segmentRows, $consumerTargets, $date);
                    }

                    $periodRows = $segmentRows->filter(
                        fn ($row): bool => Carbon::parse($row->periode)->toDateString() === $date
                            && in_array((int) $row->quadrant, [1, 2, 3, 4], true)
                    )->unique(fn ($row): string => implode('|', [
                        strtoupper(trim((string) $row->cabang)),
                        strtoupper(trim((string) $row->rm)),
                    ]));
                    $counts = $periodRows->countBy(fn ($row): int => (int) $row->quadrant);

                    return [
                        'period' => $date,
                        'label' => Carbon::parse($date)->translatedFormat('M y'),
                        'q4' => (int) ($counts[4] ?? 0),
                        'q3' => (int) ($counts[3] ?? 0),
                        'q2' => (int) ($counts[2] ?? 0),
                        'q1' => (int) ($counts[1] ?? 0),
                        'total' => $periodRows->count(),
                        'source_total' => $periodRows->count(),
                    ];
                })->values();
                $latest = (array) ($monthlyRows->last() ?? []);

                $history[$option['key']][$categoryKey] = [
                    'labels' => $monthlyRows->pluck('label')->all(),
                    'rows' => $monthlyRows->all(),
                    'coverage' => [
                        'classified' => (int) ($latest['total'] ?? 0),
                        'source_total' => (int) ($latest['source_total'] ?? 0),
                    ],
                ];
            }
        }

        return $history;
    }

    /**
     * @param Collection<int, object> $segmentRows
     * @param array<string, float> $consumerTargets
     * @return array<string, int|string>
     */
    private function consumerQuadrantHistoryRow(
        Collection $segmentRows,
        array $consumerTargets,
        string $date
    ): array {
        $rowsThroughPeriod = $segmentRows->filter(
            fn ($row): bool => Carbon::parse($row->periode)->toDateString() <= $date
        );
        $grouped = $rowsThroughPeriod->groupBy(
            fn ($row): string => $this->consumerRmTargetKey((string) $row->rm)
        )->filter(fn (Collection $rows, string $key): bool => $key !== '');
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach ($grouped as $targetKey => $rmRows) {
            $target = (float) ($consumerTargets[$targetKey] ?? 0.0);
            if ($target <= 0.0) {
                continue;
            }

            $averageRealisation = (float) $rmRows->sum('realisasi_os')
                / max(1, Carbon::parse($date)->month);
            $achievement = ($averageRealisation / $target) * 100;
            $quadrant = match (true) {
                $achievement >= 105.0 => 1,
                $achievement >= 100.0 => 2,
                $achievement >= 50.0 => 3,
                default => 4,
            };
            $counts[$quadrant]++;
        }

        return [
            'period' => $date,
            'label' => Carbon::parse($date)->translatedFormat('M y'),
            'q4' => $counts[4],
            'q3' => $counts[3],
            'q2' => $counts[2],
            'q1' => $counts[1],
            'total' => array_sum($counts),
            'source_total' => $grouped->count(),
        ];
    }

    /** @return array<string, float> */
    private function consumerMonthlyTargetMap(): array
    {
        $targets = self::CONSUMER_MONTHLY_TARGETS;
        if (
            !Schema::hasTable('performance_targets')
            || !Schema::hasColumn('performance_targets', 'category')
            || !Schema::hasColumn('performance_targets', 'rm_name')
            || !Schema::hasColumn('performance_targets', 'target_os')
        ) {
            return $targets;
        }

        DB::table('performance_targets')
            ->whereIn('category', ['BRIGUNA-KONSUMER', 'KPR', 'CONSUMER'])
            ->select('rm_name', 'target_os')
            ->get()
            ->groupBy(fn ($row): string => $this->consumerRmTargetKey((string) $row->rm_name))
            ->each(function (Collection $rows, string $key) use (&$targets): void {
                if ($key !== '' && !array_key_exists($key, $targets)) {
                    $targets[$key] = (float) $rows->sum('target_os');
                }
            });

        return $targets;
    }

    private function consumerRmTargetKey(string $rm): string
    {
        $name = trim(explode('-', $rm, 2)[1] ?? $rm);

        return preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?? '';
    }

    /**
     * @param array<int, string> $branches
     * @return array<string, array<string, float>>
     */
    private function loadCurrentSnapshotRows(?string $period, array $branches): array
    {
        if (!$period || !Schema::hasTable(self::SNAPSHOT_TABLE)) {
            return [];
        }

        $branchColumn = $this->snapshotBranchColumn();
        if (!$branchColumn) {
            return [];
        }

        $metrics = array_merge($this->timeseriesSqlMetrics(), [
            'giro_ritel' => ['giro_ritel'],
            'giro_mikro' => ['giro_mikro'],
            'giro_wholesale' => ['giro_wholesale'],
            'tabungan_ritel' => ['tabungan_ritel'],
            'tabungan_mikro' => ['tabungan_mikro'],
            'tabungan_wholesale' => ['tabungan_wholesale'],
            'deposito_ritel' => ['deposito_ritel'],
            'deposito_mikro' => ['deposito_mikro'],
            'deposito_wholesale' => ['deposito_wholesale'],
            'kecil_non_cashcoll_os' => ['kecil_non_cashcoll_os'],
            'kecil_non_cashcoll_sml' => ['kecil_non_cashcoll_sml'],
            'kecil_non_cashcoll_npl' => ['kecil_non_cashcoll_npl'],
            'cashcoll_os' => ['cashcoll_os'],
            'cashcoll_sml' => ['cashcoll_sml'],
            'cashcoll_npl' => ['cashcoll_npl'],
            'briguna_konsumer_os' => ['briguna_konsumer_os'],
            'briguna_konsumer_sml' => ['briguna_konsumer_sml'],
            'briguna_konsumer_npl' => ['briguna_konsumer_npl'],
            'kpr_os' => ['kpr_os'],
            'kpr_sml' => ['kpr_sml'],
            'kpr_npl' => ['kpr_npl'],
            'kkb_os' => ['kkb_os'],
            'kkb_sml' => ['kkb_sml'],
            'kkb_npl' => ['kkb_npl'],
            'kupedes_os' => ['kupedes_os'],
            'kupedes_sml' => ['kupedes_sml'],
            'kupedes_npl' => ['kupedes_npl'],
            'kur_mikro_os' => ['kur_mikro_os'],
            'kur_mikro_sml' => ['kur_mikro_sml'],
            'kur_mikro_npl' => ['kur_mikro_npl'],
            'briguna_mikro_os' => ['briguna_mikro_os'],
            'briguna_mikro_sml' => ['briguna_mikro_sml'],
            'briguna_mikro_npl' => ['briguna_mikro_npl'],
            'kpp_os' => ['kur_kpp_os'],
            'kpp_sml' => ['kur_kpp_sml'],
            'kpp_npl' => ['kur_kpp_npl'],
            'kur_kecil_os' => ['kur_kecil_os'],
            'kur_kecil_sml' => ['kur_kecil_sml'],
            'kur_kecil_npl' => ['kur_kecil_npl'],
        ]);

        $query = $this->summarySnapshotQuery($branches)
            ->where('snapshot_period', $period)
            ->selectRaw("{$branchColumn} as branch_label");
        foreach ($metrics as $alias => $columns) {
            $query->selectRaw($this->sumColumnsSql($columns, $alias));
        }

        $rows = $query->groupBy($branchColumn)->get();
        $result = ['area6' => []];
        foreach ($rows as $row) {
            $scopeKey = $this->scopeKey((string) $row->branch_label);
            $values = $this->rowMetrics($row, array_keys($metrics));
            $result[$scopeKey] = $values;
            $result['area6'] = $this->sumMetricRows($result['area6'], $values);
        }

        return $result;
    }

    /** @param array<int, string> $branches */
    private function summarySnapshotQuery(array $branches)
    {
        $branchColumn = $this->snapshotBranchColumn();
        $query = DB::table(self::SNAPSHOT_TABLE)
            ->whereIn(DB::raw("UPPER(TRIM({$branchColumn}))"), array_map('strtoupper', $branches));

        if (Schema::hasColumn(self::SNAPSHOT_TABLE, 'kanca_key') && Schema::hasColumn(self::SNAPSHOT_TABLE, 'unit_key')) {
            return $query->whereColumn('kanca_key', 'unit_key');
        }

        if (Schema::hasColumn(self::SNAPSHOT_TABLE, 'scope')) {
            return $query->where('scope', 'branch');
        }

        return $query;
    }

    private function snapshotBranchColumn(): ?string
    {
        if (Schema::hasColumn(self::SNAPSHOT_TABLE, 'kanca_label')) {
            return 'kanca_label';
        }

        return Schema::hasColumn(self::SNAPSHOT_TABLE, 'branch_label') ? 'branch_label' : null;
    }

    /** @param array<int, string> $columns */
    private function sumColumnsSql(array $columns, string $alias): string
    {
        $expressions = collect($columns)
            ->filter(fn (string $column): bool => Schema::hasColumn(self::SNAPSHOT_TABLE, $column))
            ->map(fn (string $column): string => "COALESCE({$column}, 0)")
            ->values();
        $expression = $expressions->isEmpty() ? '0' : $expressions->implode(' + ');

        return "COALESCE(SUM({$expression}), 0) as `{$alias}`";
    }

    /** @param array<int, string> $keys */
    private function rowMetrics(object $row, array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => (float) ($row->{$key} ?? 0.0)])
            ->all();
    }

    /** @param array<string, float> $left @param array<string, float> $right */
    private function sumMetricRows(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            $left[$key] = (float) ($left[$key] ?? 0.0) + (float) $value;
        }

        return $left;
    }

    private function scopeKey(string $scope): string
    {
        return strtolower(trim($scope)) === 'area6' ? 'area6' : strtoupper(trim($scope));
    }

    private function percentOf(float $value, float $base): float
    {
        return $base != 0.0 ? ($value / $base) * 100 : 0.0;
    }

    private function formatPeriod(?string $period): string
    {
        return $period ? Carbon::parse($period)->translatedFormat('d M Y') : 'Belum ada data';
    }

    private function formatCurrency(float $value): string
    {
        $absolute = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($absolute >= 1000000000000) {
            return $sign . 'Rp' . number_format($absolute / 1000000000000, 2, ',', '.') . ' T';
        }
        if ($absolute >= 1000000000) {
            return $sign . 'Rp' . number_format($absolute / 1000000000, 2, ',', '.') . ' M';
        }
        if ($absolute >= 1000000) {
            return $sign . 'Rp' . number_format($absolute / 1000000, 2, ',', '.') . ' Jt';
        }

        return $sign . 'Rp' . number_format($absolute, 0, ',', '.');
    }

    private function formatSignedCurrency(float $value): string
    {
        return ($value >= 0 ? '+' : '-') . $this->formatCurrency(abs($value));
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.') . '%';
    }

    private function formatSignedPercent(float $value): string
    {
        return ($value >= 0 ? '+' : '-') . $this->formatPercent(abs($value));
    }
}
