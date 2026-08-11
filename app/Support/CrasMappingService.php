<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrasMappingService
{
    private const TABLE = 'cras';

    private const FILTER_COLUMNS = [
        'sektor' => 'sektor_ekonomi',
        'sub_sektor' => 'sub_sektor_ekonomi',
        'loan_type' => 'loan_type',
        'segmen' => 'segmen',
        'produk_tiering' => 'ket_produk_tiering',
        'kualitas' => 'kualitas',
    ];

    private const METRICS = [
        'plafond' => ['label' => 'Plafon', 'format' => 'currency'],
        'baki_debet' => ['label' => 'OS / Baki Debet', 'format' => 'currency'],
        'jumlah_debitur' => ['label' => 'Debitur', 'format' => 'count'],
        'jumlah_rekening' => ['label' => 'Rekening', 'format' => 'count'],
        'biaya_ckpn' => ['label' => 'Biaya CKPN', 'format' => 'currency'],
        'ckpn_mo' => ['label' => 'CKPN MO', 'format' => 'currency'],
        'realisasi_ph' => ['label' => 'Realisasi PH', 'format' => 'currency'],
        'recovery_total' => ['label' => 'Recovery Total', 'format' => 'currency'],
        'saldo_ph' => ['label' => 'Saldo PH', 'format' => 'currency'],
        'tunggakan_bunga' => ['label' => 'Tunggakan Bunga', 'format' => 'currency'],
        'tunggakan_kecil' => ['label' => 'Tunggakan Kecil', 'format' => 'currency'],
        'tunggakan_pokok' => ['label' => 'Tunggakan Pokok', 'format' => 'currency'],
    ];

    private const DERIVED_METRICS = [
        'npl_os' => ['label' => 'NPL', 'format' => 'currency'],
        'sml_os' => ['label' => 'SML', 'format' => 'currency'],
        'npl_debitur' => ['label' => 'Debitur NPL', 'format' => 'count'],
        'sml_debitur' => ['label' => 'Debitur SML', 'format' => 'count'],
        'npl_ratio' => ['label' => 'Rasio NPL', 'format' => 'percent'],
        'sml_ratio' => ['label' => 'Rasio SML', 'format' => 'percent'],
    ];

    private const HEATMAP_METRICS = [
        'baki_debet',
        'npl_os',
        'sml_os',
        'npl_ratio',
        'sml_ratio',
        'plafond',
        'jumlah_debitur',
        'npl_debitur',
        'sml_debitur',
        'jumlah_rekening',
        'biaya_ckpn',
        'ckpn_mo',
        'realisasi_ph',
        'recovery_total',
        'saldo_ph',
        'total_tunggakan',
        'tunggakan_bunga',
        'tunggakan_kecil',
        'tunggakan_pokok',
    ];

    public function payload(array $input = []): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return $this->emptyPayload('Data SSA CRAS belum tersedia.');
        }

        $periodOptions = $this->periodOptions();
        if ($periodOptions === []) {
            return $this->emptyPayload('Belum ada periode SSA CRAS yang dapat ditampilkan.');
        }

        $period = $this->resolvePeriod((string) ($input['periode'] ?? ''), $periodOptions);
        $selected = $this->selectedFilters($input, $period);
        $cacheKey = 'cras_mapping:v4:'.md5(json_encode($selected));

        $computed = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($selected): array {
            $units = $this->aggregateUnits($selected);
            $totals = $this->sumUnitMetrics($units);
            $mappedUnits = array_values(array_filter($units, fn (array $unit): bool => $unit['district_codes'] !== []));
            $districtCodes = array_values(array_unique(array_merge(...array_map(
                fn (array $unit): array => $unit['district_codes'],
                $mappedUnits
            ))));

            return [
                'units' => $units,
                'metrics' => $totals,
                'coverage' => [
                    'source_row_count' => (int) array_sum(array_column($units, 'source_rows')),
                    'total_unit_count' => count($units),
                    'mapped_unit_count' => count($mappedUnits),
                    'unmapped_unit_count' => count($units) - count($mappedUnits),
                    'mapped_district_count' => count($districtCodes),
                ],
            ];
        });

        $lpg = app(CrasLpgPortfolioService::class)->payload($input, $period, $selected['wilayah']);

        return [
            'ready' => true,
            'message' => '',
            'title' => 'Marketshare CRAS LPG',
            'subtitle' => 'Pemetaan sektor ekonomi ke sektor industri dan kategori Sector Acceptance Criteria untuk Micro dan Small.',
            'updated_at' => Carbon::parse($period)->translatedFormat('d F Y'),
            'filters' => [
                'selected' => $selected,
                'options' => $this->filterOptions($selected, $periodOptions),
            ],
            'metric_definitions' => $this->metricDefinitions(),
            'heatmap' => [
                'selected' => in_array((string) ($input['metric'] ?? ''), self::HEATMAP_METRICS, true)
                    ? (string) $input['metric']
                    : 'baki_debet',
                'options' => $this->heatmapMetricDefinitions(),
            ],
            'units' => $computed['units'],
            'metrics' => $computed['metrics'],
            'coverage' => $computed['coverage'],
            'lpg' => $lpg,
            'source' => [
                'label' => 'SSA CRAS',
                'geojson_label' => (string) config('marketshare-geography.source.label', 'Batas Administrasi Kecamatan'),
                'geojson_url' => asset((string) config('marketshare-geography.source.geojson_path', 'data/marketshare-area6-kecamatan.geojson')),
            ],
        ];
    }

    private function emptyPayload(string $message): array
    {
        return [
            'ready' => false,
            'message' => $message,
            'filters' => ['selected' => [], 'options' => []],
            'metric_definitions' => $this->metricDefinitions(),
            'heatmap' => ['selected' => 'baki_debet', 'options' => []],
            'units' => [],
            'metrics' => $this->emptyMetrics(),
            'coverage' => [],
        ];
    }

    private function periodOptions(): array
    {
        return Cache::remember('cras_mapping:periods:v1', now()->addMinutes(10), function (): array {
            return DB::table(self::TABLE)
                ->whereNotNull('cras_periode')
                ->select('cras_periode')
                ->distinct()
                ->orderByDesc('cras_periode')
                ->pluck('cras_periode')
                ->map(function ($period): array {
                    $value = Carbon::parse($period)->toDateString();

                    return [
                        'value' => $value,
                        'label' => Carbon::parse($value)->translatedFormat('d M Y'),
                    ];
                })
                ->all();
        });
    }

    private function resolvePeriod(string $requested, array $options): string
    {
        $values = array_column($options, 'value');

        return in_array($requested, $values, true) ? $requested : (string) $values[0];
    }

    private function selectedFilters(array $input, string $period): array
    {
        $selected = [
            'periode' => $period,
            'wilayah' => $this->normalizeOption((string) ($input['wilayah'] ?? 'all')),
        ];

        foreach (array_keys(self::FILTER_COLUMNS) as $key) {
            $selected[$key] = $this->normalizeOption((string) ($input[$key] ?? 'all'));
        }

        $userScope = UserBranchScope::current();
        if ($userScope !== null) {
            $selected['wilayah'] = $userScope['key'];
        }

        $branches = array_keys((array) config('marketshare-geography.branches', []));
        if ($selected['wilayah'] !== 'all' && ! in_array($selected['wilayah'], $branches, true)) {
            $selected['wilayah'] = 'all';
        }

        return $selected;
    }

    private function normalizeOption(string $value): string
    {
        $value = trim($value);

        return $value === '' || strlen($value) > 255 ? 'all' : $value;
    }

    private function filterOptions(array $selected, array $periodOptions): array
    {
        $baseKey = md5(json_encode($selected));

        return Cache::remember('cras_mapping:filters:v4:'.$baseKey, now()->addMinutes(10), function () use ($selected, $periodOptions): array {
            $branchOptions = [['value' => 'all', 'label' => 'Seluruh Area 6']];
            $userScope = UserBranchScope::current();
            if ($userScope !== null) {
                $branchOptions = [[
                    'value' => $userScope['key'],
                    'label' => $userScope['label'],
                ]];
            } else {
                foreach ((array) config('marketshare-geography.branches', []) as $key => $definition) {
                    $branchOptions[] = [
                        'value' => (string) $key,
                        'label' => (string) ($definition['label'] ?? ucfirst((string) $key)),
                    ];
                }
            }

            $options = [
                'periode' => $periodOptions,
                'wilayah' => $branchOptions,
            ];

            foreach (self::FILTER_COLUMNS as $key => $column) {
                $query = DB::table(self::TABLE)->where('cras_periode', $selected['periode']);
                $this->applyRegionFilter($query, $selected['wilayah']);
                foreach (self::FILTER_COLUMNS as $otherKey => $otherColumn) {
                    if ($otherKey !== $key && ($selected[$otherKey] ?? 'all') !== 'all') {
                        $query->where($otherColumn, $selected[$otherKey]);
                    }
                }

                $values = $query
                    ->whereNotNull($column)
                    ->whereRaw("TRIM(`{$column}`) <> ''")
                    ->select($column)
                    ->distinct()
                    ->orderBy($column)
                    ->pluck($column)
                    ->map(fn ($value): string => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $selectedValue = (string) ($selected[$key] ?? 'all');
                if ($selectedValue !== 'all' && ! in_array($selectedValue, $values, true)) {
                    array_unshift($values, $selectedValue);
                }

                $options[$key] = array_merge(
                    [['value' => 'all', 'label' => 'Semua']],
                    array_map(fn (string $value): array => ['value' => $value, 'label' => $value], $values)
                );
            }

            return $options;
        });
    }

    private function aggregateUnits(array $selected): array
    {
        $query = DB::table(self::TABLE)->where('cras_periode', $selected['periode']);
        $this->applyFilters($query, $selected);

        $rows = DB::connection()->getDriverName() === 'mysql'
            ? $this->aggregateUnitsWithSql($query)
            : $this->aggregateUnitsInPhp($query);

        $districtMapping = (array) config('marketshare-geography.unit_districts', []);
        $units = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $code = $this->normalizeUnitCode((string) ($row['br_number'] ?? ''));
            if ($code === '') {
                continue;
            }

            $metrics = [];
            foreach (array_keys(self::METRICS) as $metric) {
                $metrics[$metric] = round((float) ($row[$metric] ?? 0), 2);
            }
            foreach (['npl_os', 'sml_os', 'npl_debitur', 'sml_debitur'] as $metric) {
                $metrics[$metric] = round((float) ($row[$metric] ?? 0), 2);
            }
            $metrics['total_tunggakan'] = round(
                $metrics['tunggakan_bunga'] + $metrics['tunggakan_kecil'] + $metrics['tunggakan_pokok'],
                2
            );
            $metrics['npl_ratio'] = $this->percentage($metrics['npl_os'], $metrics['baki_debet']);
            $metrics['sml_ratio'] = $this->percentage($metrics['sml_os'], $metrics['baki_debet']);

            $units[] = [
                'code' => $code,
                'name' => trim((string) ($row['ket_unit_kerja'] ?? '')) ?: $code,
                'branch' => trim((string) ($row['ket_kanca'] ?? '')),
                'branch_key' => $this->branchKey((string) ($row['ket_kanca'] ?? '')),
                'district_codes' => array_values(array_map('strval', (array) ($districtMapping[$code] ?? []))),
                'source_rows' => (int) ($row['source_rows'] ?? 0),
                'values' => $metrics,
            ];
        }

        usort($units, function (array $left, array $right): int {
            return strnatcasecmp($left['branch'], $right['branch'])
                ?: strnatcasecmp($left['name'], $right['name']);
        });

        return $units;
    }

    private function aggregateUnitsWithSql(Builder $query): array
    {
        $selects = [
            'TRIM(`br_number`) AS br_number',
            'TRIM(`ket_unit_kerja`) AS ket_unit_kerja',
            'TRIM(`ket_kanca`) AS ket_kanca',
            'COUNT(*) AS source_rows',
        ];
        foreach (array_keys(self::METRICS) as $metric) {
            $selects[] = 'SUM('.$this->mysqlNumericExpression($metric).") AS `{$metric}`";
        }
        $qualityExpression = 'UPPER(TRIM(`kualitas`))';
        $nplCondition = $this->mysqlNplCondition($qualityExpression);
        $smlCondition = $this->mysqlSmlCondition($qualityExpression);
        $selects[] = 'SUM(CASE WHEN '.$nplCondition.' THEN '.$this->mysqlNumericExpression('baki_debet').' ELSE 0 END) AS `npl_os`';
        $selects[] = 'SUM(CASE WHEN '.$smlCondition.' THEN '.$this->mysqlNumericExpression('baki_debet').' ELSE 0 END) AS `sml_os`';
        $selects[] = 'SUM(CASE WHEN '.$nplCondition.' THEN '.$this->mysqlNumericExpression('jumlah_debitur').' ELSE 0 END) AS `npl_debitur`';
        $selects[] = 'SUM(CASE WHEN '.$smlCondition.' THEN '.$this->mysqlNumericExpression('jumlah_debitur').' ELSE 0 END) AS `sml_debitur`';

        return $query
            ->selectRaw(implode(', ', $selects))
            ->groupByRaw('TRIM(`br_number`), TRIM(`ket_unit_kerja`), TRIM(`ket_kanca`)')
            ->get()
            ->all();
    }

    private function aggregateUnitsInPhp(Builder $query): array
    {
        $columns = array_merge(['br_number', 'ket_unit_kerja', 'ket_kanca', 'kualitas'], array_keys(self::METRICS));
        $groups = [];

        foreach ($query->select($columns)->cursor() as $row) {
            $code = $this->normalizeUnitCode((string) $row->br_number);
            $key = implode('|', [$code, trim((string) $row->ket_unit_kerja), trim((string) $row->ket_kanca)]);
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'br_number' => $row->br_number,
                    'ket_unit_kerja' => $row->ket_unit_kerja,
                    'ket_kanca' => $row->ket_kanca,
                    'source_rows' => 0,
                ] + array_fill_keys(
                    array_merge(array_keys(self::METRICS), ['npl_os', 'sml_os', 'npl_debitur', 'sml_debitur']),
                    0.0
                );
            }

            $groups[$key]['source_rows']++;
            foreach (array_keys(self::METRICS) as $metric) {
                $groups[$key][$metric] += $this->parseNumber($row->{$metric} ?? 0);
            }
            $qualityBucket = $this->qualityBucket((string) ($row->kualitas ?? ''));
            if ($qualityBucket === 'npl') {
                $groups[$key]['npl_os'] += $this->parseNumber($row->baki_debet ?? 0);
                $groups[$key]['npl_debitur'] += $this->parseNumber($row->jumlah_debitur ?? 0);
            } elseif ($qualityBucket === 'sml') {
                $groups[$key]['sml_os'] += $this->parseNumber($row->baki_debet ?? 0);
                $groups[$key]['sml_debitur'] += $this->parseNumber($row->jumlah_debitur ?? 0);
            }
        }

        return array_values($groups);
    }

    private function mysqlNumericExpression(string $column): string
    {
        $clean = "REPLACE(REPLACE(REPLACE(REPLACE(TRIM(`{$column}`), ',', ''), '\"', ''), '(', '-'), ')', '')";

        return "COALESCE(CAST(NULLIF({$clean}, '') AS DECIMAL(30, 4)), 0)";
    }

    private function parseNumber(mixed $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $value = str_replace([',', '"', '(', ')', ' '], '', $value);
        $number = is_numeric($value) ? (float) $value : 0.0;

        return $negative ? -abs($number) : $number;
    }

    private function mysqlNplCondition(string $qualityExpression): string
    {
        return "({$qualityExpression} = 'NPL' OR {$qualityExpression} IN ('3', '4', '5', 'KL', 'D', 'M', 'KURANG LANCAR', 'DIRAGUKAN', 'MACET'))";
    }

    private function mysqlSmlCondition(string $qualityExpression): string
    {
        return "({$qualityExpression} LIKE 'SML%' OR {$qualityExpression} IN ('2', 'DPK', 'DALAM PERHATIAN KHUSUS'))";
    }

    private function qualityBucket(string $quality): ?string
    {
        $normalized = strtoupper(trim((string) preg_replace('/\s+/', ' ', $quality)));
        if ($normalized === 'NPL' || in_array($normalized, ['3', '4', '5', 'KL', 'D', 'M', 'KURANG LANCAR', 'DIRAGUKAN', 'MACET'], true)) {
            return 'npl';
        }
        if (str_starts_with($normalized, 'SML') || in_array($normalized, ['2', 'DPK', 'DALAM PERHATIAN KHUSUS'], true)) {
            return 'sml';
        }

        return null;
    }

    private function applyFilters(Builder $query, array $selected): void
    {
        $this->applyRegionFilter($query, $selected['wilayah']);
        foreach (self::FILTER_COLUMNS as $key => $column) {
            if (($selected[$key] ?? 'all') !== 'all') {
                $query->where($column, $selected[$key]);
            }
        }
    }

    private function applyRegionFilter(Builder $query, string $region): void
    {
        if ($region === 'all') {
            return;
        }

        $definition = (array) config("marketshare-geography.branches.{$region}", []);
        $label = trim((string) ($definition['label'] ?? ''));
        if ($label !== '') {
            $query->where('ket_kanca', 'KC '.$label);
        }
    }

    private function normalizeUnitCode(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, -5), 5, '0', STR_PAD_LEFT);
    }

    private function branchKey(string $branch): string
    {
        $branch = strtoupper($branch);
        foreach ((array) config('marketshare-geography.branches', []) as $key => $definition) {
            $label = strtoupper(trim((string) ($definition['label'] ?? '')));
            if ($label !== '' && str_contains($branch, $label)) {
                return (string) $key;
            }
        }

        return 'other';
    }

    private function sumUnitMetrics(array $units): array
    {
        $totals = $this->emptyMetrics();
        foreach ($units as $unit) {
            foreach (array_keys($totals) as $metric) {
                if (in_array($metric, ['npl_ratio', 'sml_ratio'], true)) {
                    continue;
                }
                $totals[$metric] += (float) ($unit['values'][$metric] ?? 0);
            }
        }

        $totals['npl_ratio'] = $this->percentage($totals['npl_os'], $totals['baki_debet']);
        $totals['sml_ratio'] = $this->percentage($totals['sml_os'], $totals['baki_debet']);

        return array_map(fn (float $value): float => round($value, 2), $totals);
    }

    private function emptyMetrics(): array
    {
        return array_fill_keys(
            array_merge(array_keys(self::METRICS), array_keys(self::DERIVED_METRICS), ['total_tunggakan']),
            0.0
        );
    }

    private function percentage(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 4) : 0.0;
    }

    private function metricDefinitions(): array
    {
        $definitions = [];
        foreach (self::METRICS as $key => $metric) {
            $definitions[] = ['key' => $key] + $metric;
        }
        foreach (self::DERIVED_METRICS as $key => $metric) {
            $definitions[] = ['key' => $key] + $metric;
        }
        $definitions[] = ['key' => 'total_tunggakan', 'label' => 'Total Tunggakan', 'format' => 'currency'];

        return $definitions;
    }

    private function heatmapMetricDefinitions(): array
    {
        $definitions = collect($this->metricDefinitions())->keyBy('key');

        return array_values(array_filter(array_map(
            fn (string $key): ?array => $definitions->get($key),
            self::HEATMAP_METRICS
        )));
    }
}
