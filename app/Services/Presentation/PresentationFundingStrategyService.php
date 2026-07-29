<?php

namespace App\Services\Presentation;

use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\NewPayrollReportService;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PresentationFundingStrategyService
{
    private const DEFAULT_BRANCHES = [
        'KC MADIUN',
        'KC MAGETAN',
        'KC NGAWI',
        'KC PONOROGO',
    ];

    public function __construct(
        private readonly BusinessClusterReportService $businessCluster,
        private readonly NewPayrollReportService $newPayroll,
        private readonly RkaLookupService $rkaLookup
    ) {}

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    public function build(?string $period, array $branches): array
    {
        $target = $period ? Carbon::parse($period)->startOfDay() : now()->startOfDay();
        $branches = $this->normalizeBranches($branches);
        if ($branches === []) {
            $branches = self::DEFAULT_BRANCHES;
        }

        $channels = $this->buildDigitalChannels($target, $branches);
        $casaRows = $this->buildCasaDebiturRows($target, $branches);
        $payrollRows = $this->buildPayrollRows($target, $branches);
        $dormant = $this->buildDormantChannel($target, $branches);

        $scopeOptions = array_merge(
            [['key' => 'area6', 'label' => 'Area 6 Konsol']],
            collect($branches)->map(fn (string $branch): array => [
                'key' => $branch,
                'label' => $this->displayBranch($branch),
            ])->all()
        );

        $scopes = [];
        foreach ($scopeOptions as $option) {
            $scopeKey = (string) $option['key'];
            $scopeBranches = $scopeKey === 'area6' ? $branches : [$scopeKey];
            $businessCluster = $this->buildBusinessClusterRows($scopeBranches);

            $scopes[$scopeKey] = [
                'scope_key' => $scopeKey,
                'scope_label' => (string) $option['label'],
                'period_label' => $target->format('d M y'),
                'digital' => [
                    'title' => '1. Optimalisasi Digital Channel',
                    'rows' => collect($channels)
                        ->map(fn (array $channel): array => $this->channelRow($channel, $scopeBranches))
                        ->values()
                        ->all(),
                ],
                'casa_debitur' => [
                    'title' => '2. Rekening Transaksi Debitur',
                    'period_label' => (string) ($casaRows['period_label'] ?? '-'),
                    'rows' => $this->scopeCasaRows($casaRows, $scopeKey),
                ],
                'business_cluster' => [
                    'title' => '3. Bisnis Cluster',
                    'period_label' => (string) ($businessCluster['period_label'] ?? '-'),
                    'rows' => (array) ($businessCluster['rows'] ?? []),
                    'total' => (int) ($businessCluster['total'] ?? 0),
                ],
                'payroll' => [
                    'title' => '4. Peningkatan Payroll Berkualitas',
                    'period_label' => (string) ($payrollRows['period_label'] ?? '-'),
                    'rows' => $this->scopePayrollRows($payrollRows, $scopeKey),
                ],
                'dormant' => [
                    'title' => '5. Rekening Dormant',
                    'row' => $this->channelRow($dormant, $scopeBranches),
                ],
                'supporting' => $this->emptySupportingStrategies(),
            ];
        }

        return [
            'available' => true,
            'period_label' => $target->format('d M y'),
            'scope_options' => $scopeOptions,
            'scopes' => $scopes,
        ];
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<int, array<string, mixed>>
     */
    private function buildDigitalChannels(Carbon $target, array $branches): array
    {
        $qris = $this->buildQrisChannel($target, $branches);
        $qris['rka_by_branch'] = $this->qrisVolumeRkaByBranch($target, $branches);

        return [
            $this->buildEdcChannel($target, $branches),
            $qris,
            $this->buildCasaMerchantChannel($target, $branches),
            $this->buildBrimoChannel($target, $branches),
            $this->buildBrilinkChannel($target, $branches),
            $this->buildQlolaChannel($target, $branches),
        ];
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, float>
     */
    private function qrisVolumeRkaByBranch(Carbon $target, array $branches): array
    {
        try {
            $definitions = ['vol' => ['mata_anggaran' => ['Sales Volume QRIS']]];
            $monthColumn = $this->rkaLookup->resolveMonthColumn($target);
            $year = (int) $target->format('Y');
            $directBranches = array_values(array_filter(
                $branches,
                fn (string $branch): bool => $branch === 'KC PONOROGO'
            ));
            $regionPatterns = collect($branches)
                ->reject(fn (string $branch): bool => $branch === 'KC PONOROGO')
                ->map(fn (string $branch): string => trim((string) preg_replace('/^KC\s+/i', '', $branch)))
                ->filter()
                ->values()
                ->all();
            $values = [];

            if ($directBranches !== []) {
                $direct = $this->rkaLookup->aggregateByGroup(
                    $definitions,
                    $monthColumn,
                    $directBranches,
                    [],
                    'kanca',
                    $year
                );
                $values = (array) data_get($direct, 'vol', []);
            }

            if ($regionPatterns !== []) {
                $regional = $this->rkaLookup->aggregateByGroupWithRegionalFilter(
                    $definitions,
                    $monthColumn,
                    $regionPatterns,
                    $year,
                    [],
                    'region'
                );
                foreach ((array) data_get($regional, 'vol', []) as $region => $value) {
                    $values['KC '.strtoupper(trim((string) $region))] = (float) $value;
                }
            }

            $fallback = $this->rkaLookup->aggregateByKancaWithSummaryFallback(
                $definitions,
                $monthColumn,
                $branches,
                $year
            );
            foreach ($branches as $branch) {
                if (abs((float) ($values[$branch] ?? 0)) <= 0.0) {
                    $values[$branch] = (float) data_get($fallback, "vol.{$branch}", 0);
                }
            }

            return collect($values)
                ->map(fn ($value): float => (float) $value)
                ->all();
        } catch (Throwable $e) {
            Log::warning('RKA QRIS presentasi gagal disusun: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildEdcChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods('jumlah_merchant_detail', 'POSISI', $target);
        $maps = $this->emptyMetricMaps($branches);

        if ($periods['current'] !== null) {
            $query = DB::table('jumlah_merchant_detail')
                ->selectRaw('UPPER(TRIM(NAMA_KANCA)) as branch');

            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(NAMA_KANCA))'), $branches)
                ->whereIn(DB::raw('DATE(POSISI)'), array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(NAMA_KANCA))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps);
        }

        return $this->channelDefinition(
            'edc',
            'EDC',
            'MID aktif',
            'integer',
            $periods,
            $maps,
            'jumlah_merchant_detail'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildQrisChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods('jumlah_merchant_qris_detail', 'POSISI', $target);
        $maps = $this->emptyMetricMaps($branches);

        if ($periods['current'] !== null) {
            $volume = "COALESCE(CAST(NULLIF(REPLACE(AKUMULASI_SV_TOTAL, ',', ''), '') AS DECIMAL(20,2)), 0)";
            $query = DB::table('jumlah_merchant_qris_detail')
                ->selectRaw('UPPER(TRIM(MBDESC)) as branch');

            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COALESCE(SUM(CASE WHEN POSISI = ? THEN {$volume} ELSE 0 END), 0) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(MBDESC))'), $branches)
                ->whereIn('POSISI', array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(MBDESC))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps);
        }

        return $this->channelDefinition(
            'qris',
            'QRIS',
            'Sales volume',
            'currency',
            $periods,
            $maps,
            'jumlah_merchant_qris_detail'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildCasaMerchantChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods(
            ['casa_brilink_web', 'casa_brilink_edc'],
            'periode',
            $target->copy()->endOfMonth()
        );
        $maps = $this->emptyMetricMaps($branches);

        foreach (['casa_brilink_web', 'casa_brilink_edc'] as $table) {
            if (! Schema::hasTable($table) || $periods['current'] === null) {
                continue;
            }

            $query = DB::table($table)->selectRaw('UPPER(TRIM(mbdesc)) as branch');
            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COALESCE(SUM(CASE WHEN periode = ? THEN COALESCE(jml_nominal_casa, 0) ELSE 0 END), 0) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(mbdesc))'), $branches)
                ->whereIn('periode', array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(mbdesc))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps, true);
        }

        return $this->channelDefinition(
            'casa_merchant',
            'CASA Merchant',
            'Saldo CASA merchant',
            'currency',
            $periods,
            $maps,
            'casa_brilink_web + casa_brilink_edc'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildBrimoChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods(['user_brimo_rpt_v2', 'user_brimo_fin'], 'posisi', $target);
        $maps = $this->emptyMetricMaps($branches);

        foreach (['user_brimo_rpt_v2', 'user_brimo_fin'] as $table) {
            if (! Schema::hasTable($table) || $periods['current'] === null) {
                continue;
            }

            $query = DB::table($table)
                ->selectRaw('UPPER(TRIM(COALESCE(mbdesc, branch))) as branch');
            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COALESCE(SUM(CASE WHEN posisi = ? THEN COALESCE(jumlah, 0) ELSE 0 END), 0) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(COALESCE(mbdesc, branch)))'), $branches)
                ->whereIn('posisi', array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(COALESCE(mbdesc, branch)))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps, true);
        }

        return $this->channelDefinition(
            'brimo',
            'BRIMO',
            'Total Ureg',
            'currency',
            $periods,
            $maps,
            'user_brimo_rpt_v2 + user_brimo_fin'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildBrilinkChannel(Carbon $target, array $branches): array
    {
        $periods = $this->monthNamePeriods(
            'brilink_web_laporan_summary_transaksi_brilink_web',
            'periode',
            $target
        );
        $maps = $this->emptyMetricMaps($branches);

        if ($periods['current'] !== null) {
            $query = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                ->selectRaw('UPPER(TRIM(cabang)) as branch');

            foreach ($periods as $key => $periodName) {
                $query->selectRaw(
                    "COALESCE(SUM(CASE WHEN periode = ? THEN COALESCE(total_nominal, 0) ELSE 0 END), 0) as metric_{$key}",
                    [$periodName]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $branches)
                ->whereIn('periode', array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(cabang))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps);
        }

        return $this->channelDefinition(
            'brilink',
            'BRILINK',
            'Volume transaksi',
            'currency',
            $this->monthPeriodLabels($periods, $target),
            $maps,
            'brilink_web_laporan_summary_transaksi_brilink_web'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildQlolaChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods('usak_ibbiz_uker', 'periode', $target);
        $maps = $this->emptyMetricMaps($branches);

        if ($periods['current'] !== null) {
            $branchExpression = "UPPER(TRIM(CASE WHEN LOCATE(' - ', kanca) > 0 THEN SUBSTRING(kanca, LOCATE(' - ', kanca) + 3) ELSE kanca END))";
            $query = DB::table('usak_ibbiz_uker')
                ->selectRaw("{$branchExpression} as branch");

            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COUNT(CASE WHEN periode = ? AND UPPER(TRIM(deskripsi)) IN ('ACTIVE', 'ACTIVATED') THEN 1 END) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw($branchExpression), $branches)
                ->whereIn('periode', array_values(array_unique(array_filter($periods))))
                ->groupByRaw($branchExpression)
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps);
        }

        return $this->channelDefinition(
            'qlola',
            'QLOLA',
            'Nasabah aktif',
            'integer',
            $periods,
            $maps,
            'usak_ibbiz_uker'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildDormantChannel(Carbon $target, array $branches): array
    {
        $periods = $this->datePeriods('rekening_dormant_snapshots', 'posisi', $target);
        $maps = $this->emptyMetricMaps($branches);

        if ($periods['current'] !== null) {
            $query = DB::table('rekening_dormant_snapshots')
                ->selectRaw('UPPER(TRIM(branch_label)) as branch');

            foreach ($periods as $key => $date) {
                $query->selectRaw(
                    "COALESCE(SUM(CASE WHEN posisi = ? THEN COALESCE(dormant_count, 0) ELSE 0 END), 0) as metric_{$key}",
                    [$date]
                );
            }

            $rows = $query
                ->whereIn(DB::raw('UPPER(TRIM(branch_label))'), $branches)
                ->whereIn('posisi', array_values(array_unique(array_filter($periods))))
                ->groupByRaw('UPPER(TRIM(branch_label))')
                ->get();

            $maps = $this->rowsToMetricMaps($rows, $maps);
        }

        return $this->channelDefinition(
            'dormant',
            'Rekening Dormant',
            'Jumlah rekening',
            'integer',
            $periods,
            $maps,
            'rekening_dormant_snapshots'
        );
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildCasaDebiturRows(Carbon $target, array $branches): array
    {
        if (! Schema::hasTable('rasio_casa_debitur_snapshots')) {
            return ['period_label' => '-', 'area' => [], 'branches' => []];
        }

        $period = DB::table('rasio_casa_debitur_snapshots')
            ->where('loan_period', '<=', $target->toDateString())
            ->max('loan_period');
        if (! $period) {
            return ['period_label' => '-', 'area' => [], 'branches' => []];
        }

        $rows = DB::table('rasio_casa_debitur_snapshots')
            ->where('loan_period', $period)
            ->whereIn(DB::raw('UPPER(TRIM(branch_key))'), $branches)
            ->get();

        $areaRows = collect($branches)->map(function (string $branch) use ($rows): array {
            $row = $rows->first(fn ($item): bool => strtoupper(trim((string) ($item->branch_key ?? ''))) === $branch
                && strtolower(trim((string) ($item->segment_key ?? ''))) === 'total'
            );

            return $this->casaMetricRow($this->displayBranch($branch), $row);
        })->all();

        $branchRows = [];
        foreach ($branches as $branch) {
            $branchSource = $rows->filter(fn ($item): bool => strtoupper(trim((string) ($item->branch_key ?? ''))) === $branch
            );
            $branchRows[$branch] = [
                $this->casaMetricRow(
                    'SME',
                    $branchSource->first(fn ($item): bool => strtolower((string) $item->segment_key) === 'smc')
                ),
                $this->casaMetricRow(
                    'Konsumer',
                    (object) [
                        'os_amount' => $branchSource
                            ->whereIn('segment_key', ['briguna', 'kpr'])
                            ->sum('os_amount'),
                        'casa_amount' => $branchSource
                            ->whereIn('segment_key', ['briguna', 'kpr'])
                            ->sum('casa_amount'),
                    ]
                ),
                $this->casaMetricRow(
                    'Mikro',
                    $branchSource->first(fn ($item): bool => strtolower((string) $item->segment_key) === 'mikro')
                ),
            ];
        }

        return [
            'period_label' => Carbon::parse($period)->format('d M y'),
            'area' => $areaRows,
            'branches' => $branchRows,
        ];
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildPayrollRows(Carbon $target, array $branches): array
    {
        if (! Schema::hasTable('performance_pis_per_produk')) {
            return ['period_label' => '-', 'rows' => []];
        }

        try {
            $request = Request::create('/', 'GET', ['posisi' => $target->toDateString()]);
            $payload = $this->newPayroll->fetchData($request)->getData(true);
            $allowed = array_flip($branches);

            $rows = collect((array) ($payload['data'] ?? []))
                ->filter(fn (array $row): bool => isset($allowed[strtoupper(trim((string) ($row['branch'] ?? '')))])
                )
                ->map(fn (array $row): array => [
                    'key' => strtoupper(trim((string) ($row['branch'] ?? ''))),
                    'label' => $this->displayBranch((string) ($row['branch'] ?? '-')),
                    'rekening' => $this->payrollMetric((array) ($row['rekening'] ?? []), 'integer'),
                    'saldo' => $this->payrollMetric((array) ($row['saldo'] ?? []), 'currency'),
                    'kualitas' => $this->payrollMetric((array) ($row['kualitas'] ?? []), 'integer'),
                ])
                ->values()
                ->all();

            return [
                'period_label' => ! empty($payload['effective_snapshot'])
                    ? Carbon::parse((string) $payload['effective_snapshot'])->format('d M y')
                    : '-',
                'rows' => $rows,
            ];
        } catch (Throwable $e) {
            Log::warning('Payload payroll presentasi gagal disusun: '.$e->getMessage());

            return ['period_label' => '-', 'rows' => []];
        }
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function buildBusinessClusterRows(array $branches): array
    {
        try {
            $displayBranches = array_map(fn (string $branch): string => $this->displayBranch($branch), $branches);
            $report = $this->businessCluster->buildReport($displayBranches);
            $rows = collect($report['rows'] ?? [])
                ->sortByDesc(fn ($row): int => (int) data_get($row, 'jumlah', 0))
                ->take(5)
                ->map(function ($row): array {
                    $jumlah = (int) data_get($row, 'jumlah', 0);
                    $sudah = (int) data_get($row, 'sudah_bri', 0);

                    return [
                        'category' => (string) data_get($row, 'kategori', '-'),
                        'total' => $jumlah,
                        'total_fmt' => $jumlah > 0 ? $this->formatInteger($jumlah) : '-',
                        'sudah_bri' => $sudah,
                        'sudah_bri_fmt' => $sudah > 0 ? $this->formatInteger($sudah) : '-',
                        'belum_bri' => (int) data_get($row, 'belum_bri', 0),
                        'belum_bri_fmt' => (int) data_get($row, 'belum_bri', 0) > 0
                            ? $this->formatInteger((int) data_get($row, 'belum_bri', 0))
                            : '-',
                        'penetration' => $jumlah > 0 ? ($sudah / $jumlah) * 100 : null,
                        'penetration_fmt' => $jumlah > 0 ? $this->formatPercent(($sudah / $jumlah) * 100) : '-',
                    ];
                })
                ->values()
                ->all();

            return [
                'period_label' => (string) ($report['latestPosition'] ?? '-'),
                'rows' => $rows,
                'total' => (int) ($report['totalJumlah'] ?? 0),
            ];
        } catch (Throwable $e) {
            Log::warning('Payload business cluster presentasi gagal disusun: '.$e->getMessage());

            return ['period_label' => '-', 'rows' => [], 'total' => 0];
        }
    }

    /**
     * @param  array<string, mixed>  $channel
     * @param  array<int, string>  $branches
     * @return array<string, mixed>
     */
    private function channelRow(array $channel, array $branches): array
    {
        $positions = [];
        foreach (['ytd', 'mtd', 'current'] as $key) {
            $raw = $this->sumScopeMap((array) data_get($channel, "maps.{$key}", []), $branches);
            $available = data_get($channel, "periods.{$key}") !== null;
            $positions[$key] = $this->metricPoint(
                $available ? $raw : null,
                data_get($channel, "periods.{$key}"),
                (string) ($channel['format'] ?? 'integer')
            );
        }

        $deltaYtd = $positions['current']['raw'] !== null && $positions['ytd']['raw'] !== null
            ? (float) $positions['current']['raw'] - (float) $positions['ytd']['raw']
            : null;
        $deltaMtd = $positions['current']['raw'] !== null && $positions['mtd']['raw'] !== null
            ? (float) $positions['current']['raw'] - (float) $positions['mtd']['raw']
            : null;
        $rka = $this->sumScopeMap((array) ($channel['rka_by_branch'] ?? []), $branches);
        $hasRka = ! empty($channel['rka_by_branch']) && abs($rka) > 0.0;

        return [
            'key' => (string) ($channel['key'] ?? ''),
            'label' => (string) ($channel['label'] ?? '-'),
            'metric_label' => (string) ($channel['metric_label'] ?? ''),
            'format' => (string) ($channel['format'] ?? 'integer'),
            'positions' => $positions,
            'deltas' => [
                'ytd' => $this->metricPoint(
                    $deltaYtd,
                    data_get($channel, 'periods.ytd'),
                    (string) ($channel['format'] ?? 'integer'),
                    true
                ),
                'mtd' => $this->metricPoint(
                    $deltaMtd,
                    data_get($channel, 'periods.mtd'),
                    (string) ($channel['format'] ?? 'integer'),
                    true
                ),
            ],
            'rka' => [
                'raw' => $hasRka ? $rka : null,
                'fmt' => $hasRka
                    ? $this->formatMetric($rka, (string) ($channel['format'] ?? 'integer'))
                    : '-',
            ],
            'source' => (string) ($channel['source'] ?? '-'),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function payrollMetric(array $source, string $format): array
    {
        $current = isset($source['curr']) ? (float) $source['curr'] : null;
        $mtd = isset($source['prev']) ? (float) $source['prev'] : null;
        $ytd = isset($source['yoy_prev']) ? (float) $source['yoy_prev'] : null;
        $rka = isset($source['rka']) && (float) $source['rka'] > 0 ? (float) $source['rka'] : null;

        return [
            'positions' => [
                'ytd' => ['raw' => $ytd, 'fmt' => $this->formatNullableMetric($ytd, $format)],
                'mtd' => ['raw' => $mtd, 'fmt' => $this->formatNullableMetric($mtd, $format)],
                'current' => ['raw' => $current, 'fmt' => $this->formatNullableMetric($current, $format)],
            ],
            'deltas' => [
                'ytd' => [
                    'raw' => $current !== null && $ytd !== null ? $current - $ytd : null,
                    'fmt' => $this->formatSignedMetric(
                        $current !== null && $ytd !== null ? $current - $ytd : null,
                        $format
                    ),
                ],
                'mtd' => [
                    'raw' => $current !== null && $mtd !== null ? $current - $mtd : null,
                    'fmt' => $this->formatSignedMetric(
                        $current !== null && $mtd !== null ? $current - $mtd : null,
                        $format
                    ),
                ],
            ],
            'rka' => [
                'raw' => $rka,
                'fmt' => $this->formatNullableMetric($rka, $format),
            ],
            'achievement' => [
                'raw' => isset($source['penc_pct']) ? (float) $source['penc_pct'] : null,
                'fmt' => isset($source['penc_pct'])
                    ? $this->formatPercent((float) $source['penc_pct'])
                    : '-',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payrollRows
     * @return array<int, array<string, mixed>>
     */
    private function scopePayrollRows(array $payrollRows, string $scopeKey): array
    {
        $rows = collect((array) ($payrollRows['rows'] ?? []));
        if ($scopeKey === 'area6') {
            return $rows->values()->all();
        }

        return $rows
            ->filter(fn (array $row): bool => strtoupper((string) ($row['key'] ?? '')) === strtoupper($scopeKey))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $casaRows
     * @return array<int, array<string, mixed>>
     */
    private function scopeCasaRows(array $casaRows, string $scopeKey): array
    {
        if ($scopeKey === 'area6') {
            return array_values((array) ($casaRows['area'] ?? []));
        }

        return array_values((array) data_get($casaRows, "branches.{$scopeKey}", []));
    }

    private function casaMetricRow(string $label, mixed $source): array
    {
        $os = (float) data_get($source, 'os_amount', 0);
        $casa = (float) data_get($source, 'casa_amount', 0);
        $ratio = $os > 0 ? ($casa / $os) * 100 : null;

        return [
            'label' => $label,
            'os' => $os,
            'os_fmt' => $this->formatCurrency($os),
            'casa' => $casa,
            'casa_fmt' => $this->formatCurrency($casa),
            'ratio' => $ratio,
            'ratio_fmt' => $ratio !== null ? $this->formatPercent($ratio) : '-',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function emptySupportingStrategies(): array
    {
        return [
            [
                'number' => 6,
                'key' => 'anak_perusahaan',
                'title' => 'Kolaborasi Perusahaan Anak',
                'position' => '-',
                'delta_ytd' => '-',
                'delta_mtd' => '-',
                'rka' => '-',
            ],
            [
                'number' => 7,
                'key' => 'nasabah_prioritas',
                'title' => 'Optimalisasi Nasabah Prioritas BOD/BOC',
                'position' => '-',
                'delta_ytd' => '-',
                'delta_mtd' => '-',
                'rka' => '-',
            ],
            [
                'number' => 8,
                'key' => 'fungsi_rm',
                'title' => 'Penguatan Produk & Fungsi RM',
                'position' => '-',
                'delta_ytd' => '-',
                'delta_mtd' => '-',
                'rka' => '-',
            ],
        ];
    }

    /**
     * @param  string|array<int, string>  $tables
     * @return array{ytd: ?string, mtd: ?string, current: ?string}
     */
    private function datePeriods(string|array $tables, string $column, Carbon $target): array
    {
        $tables = is_array($tables) ? $tables : [$tables];
        $availableTables = array_values(array_filter($tables, fn (string $table): bool => Schema::hasTable($table)));
        if ($availableTables === []) {
            return ['ytd' => null, 'mtd' => null, 'current' => null];
        }

        return [
            'ytd' => $this->latestDateAcrossTables(
                $availableTables,
                $column,
                $target->copy()->subYearNoOverflow()->endOfYear()
            ),
            'mtd' => $this->latestDateAcrossTables(
                $availableTables,
                $column,
                $target->copy()->subMonthNoOverflow()->endOfMonth()
            ),
            'current' => $this->latestDateAcrossTables($availableTables, $column, $target),
        ];
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function latestDateAcrossTables(array $tables, string $column, Carbon $boundary): ?string
    {
        return collect($tables)
            ->map(fn (string $table) => DB::table($table)
                ->where($column, '<=', $boundary->toDateString())
                ->max($column))
            ->filter()
            ->map(fn ($value): string => Carbon::parse((string) $value)->toDateString())
            ->sortDesc()
            ->first();
    }

    /**
     * @return array{ytd: ?string, mtd: ?string, current: ?string}
     */
    private function monthNamePeriods(string $table, string $column, Carbon $target): array
    {
        if (! Schema::hasTable($table)) {
            return ['ytd' => null, 'mtd' => null, 'current' => null];
        }

        $periods = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column)
            ->map(function ($value): ?array {
                try {
                    return [
                        'value' => (string) $value,
                        'date' => Carbon::createFromFormat('F Y', (string) $value)->startOfMonth(),
                    ];
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values();

        $resolve = fn (Carbon $boundary): ?string => $periods
            ->filter(fn (array $row): bool => $row['date']->lte($boundary->copy()->endOfMonth()))
            ->sortByDesc(fn (array $row): int => $row['date']->timestamp)
            ->pluck('value')
            ->first();

        return [
            'ytd' => $resolve($target->copy()->subYearNoOverflow()->endOfYear()),
            'mtd' => $resolve($target->copy()->subMonthNoOverflow()->endOfMonth()),
            'current' => $resolve($target),
        ];
    }

    /**
     * @param  array{ytd: ?string, mtd: ?string, current: ?string}  $periods
     * @return array{ytd: ?string, mtd: ?string, current: ?string}
     */
    private function monthPeriodLabels(array $periods, Carbon $target): array
    {
        $format = function (?string $period, string $key) use ($target): ?string {
            if ($period === null) {
                return null;
            }

            try {
                $date = Carbon::createFromFormat('F Y', $period);
                if ($key === 'current' && $date->isSameMonth($target)) {
                    return $target->toDateString();
                }

                return $date->endOfMonth()->toDateString();
            } catch (Throwable) {
                return null;
            }
        };

        return [
            'ytd' => $format($periods['ytd'], 'ytd'),
            'mtd' => $format($periods['mtd'], 'mtd'),
            'current' => $format($periods['current'], 'current'),
        ];
    }

    /**
     * @param  array<int, string>  $branches
     * @return array{ytd: array<string, float>, mtd: array<string, float>, current: array<string, float>}
     */
    private function emptyMetricMaps(array $branches): array
    {
        $empty = array_fill_keys($branches, 0.0);

        return ['ytd' => $empty, 'mtd' => $empty, 'current' => $empty];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, array<string, float>>  $maps
     * @return array<string, array<string, float>>
     */
    private function rowsToMetricMaps(Collection $rows, array $maps, bool $add = false): array
    {
        foreach ($rows as $row) {
            $branch = strtoupper(trim((string) ($row->branch ?? '')));
            if ($branch === '') {
                continue;
            }

            foreach (['ytd', 'mtd', 'current'] as $key) {
                $value = (float) ($row->{"metric_{$key}"} ?? 0);
                $maps[$key][$branch] = $add
                    ? (float) ($maps[$key][$branch] ?? 0) + $value
                    : $value;
            }
        }

        return $maps;
    }

    /**
     * @param  array<string, mixed>  $periods
     * @param  array<string, array<string, float>>  $maps
     * @return array<string, mixed>
     */
    private function channelDefinition(
        string $key,
        string $label,
        string $metricLabel,
        string $format,
        array $periods,
        array $maps,
        string $source
    ): array {
        return compact('key', 'label', 'metricLabel', 'format', 'periods', 'maps', 'source') + [
            'metric_label' => $metricLabel,
            'rka_by_branch' => [],
        ];
    }

    /**
     * @param  array<string, float|int>  $map
     * @param  array<int, string>  $branches
     */
    private function sumScopeMap(array $map, array $branches): float
    {
        return collect($branches)->sum(fn (string $branch): float => (float) ($map[$branch] ?? 0));
    }

    /**
     * @return array{raw: ?float, fmt: string, label: string}
     */
    private function metricPoint(
        ?float $value,
        mixed $period,
        string $format,
        bool $signed = false
    ): array {
        $label = $period ? Carbon::parse((string) $period)->format('d M y') : '-';

        return [
            'raw' => $value,
            'fmt' => $signed
                ? $this->formatSignedMetric($value, $format)
                : $this->formatNullableMetric($value, $format),
            'label' => $label,
        ];
    }

    private function formatNullableMetric(?float $value, string $format): string
    {
        return $value === null ? '-' : $this->formatMetric($value, $format);
    }

    private function formatSignedMetric(?float $value, string $format): string
    {
        if ($value === null) {
            return '-';
        }

        $sign = $value > 0 ? '+' : '';

        return $sign.$this->formatMetric($value, $format);
    }

    private function formatMetric(float $value, string $format): string
    {
        return $format === 'currency'
            ? $this->formatCurrency($value)
            : $this->formatInteger($value);
    }

    private function formatCurrency(float $value): string
    {
        $absolute = abs($value);
        $sign = $value < 0 ? '-' : '';

        if ($absolute >= 1_000_000_000_000) {
            return $sign.'Rp'.number_format($absolute / 1_000_000_000_000, 2, ',', '.').' T';
        }
        if ($absolute >= 1_000_000_000) {
            return $sign.'Rp'.number_format($absolute / 1_000_000_000, 2, ',', '.').' M';
        }
        if ($absolute >= 1_000_000) {
            return $sign.'Rp'.number_format($absolute / 1_000_000, 2, ',', '.').' Jt';
        }

        return $sign.'Rp'.number_format($absolute, 0, ',', '.');
    }

    private function formatInteger(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 2, ',', '.').'%';
    }

    /**
     * @param  array<int, string>  $branches
     * @return array<int, string>
     */
    private function normalizeBranches(array $branches): array
    {
        return collect($branches)
            ->map(fn ($branch): string => strtoupper(trim((string) $branch)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function displayBranch(string $branch): string
    {
        return collect(preg_split('/\s+/', strtolower(trim($branch))) ?: [])
            ->map(fn (string $word): string => strtoupper($word) === 'KC' ? 'KC' : ucfirst($word))
            ->join(' ');
    }
}
