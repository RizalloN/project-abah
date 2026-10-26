<?php

namespace App\Services\Presentation;

use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kinerja segments of the segmented presentation (Simpanan, Pinjaman, Kualitas,
 * Recovery DH) for one scope, read from dashboard_harian_snapshots.
 *
 * The snapshot keeps one row per outlet (KC detail, KCP, Unit) plus one branch
 * row whose kanca_key equals its unit_key; outlet rows sum exactly to the
 * branch row. So the Area scope breaks down per branch (branch rows) and a
 * branch scope breaks down per outlet (its outlet rows), both from the same
 * figures the landing page prints.
 */
class PresentationSegmentDataService
{
    private const TABLE = 'dashboard_harian_snapshots';

    private const BRANCHES = [
        'madiun' => ['kanca_key' => 'kc-madiun', 'label' => 'KC Madiun'],
        'magetan' => ['kanca_key' => 'kc-magetan', 'label' => 'KC Magetan'],
        'ngawi' => ['kanca_key' => 'kc-ngawi', 'label' => 'KC Ngawi'],
        'ponorogo' => ['kanca_key' => 'kc-ponorogo', 'label' => 'KC Ponorogo'],
    ];

    private const TIMESERIES_MONTHS = 12;

    /** @var array<string, bool> */
    private array $columnExists = [];

    /** Scope keys a user may open: everything for area users, only their branch otherwise. */
    public function allowedScopes(): array
    {
        $pinned = UserBranchScope::current();
        $scopes = $pinned === null
            ? array_merge([UserBranchScope::AREA_SCOPE => 'Area 6 Konsol'], array_map(fn (array $branch): string => $branch['label'], self::BRANCHES))
            : [$pinned['key'] => self::BRANCHES[$pinned['key']]['label'] ?? $pinned['label']];

        return collect($scopes)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all();
    }

    /** The requested scope when allowed, else the user's own default. */
    public function resolveScope(?string $requested): string
    {
        $allowed = array_column($this->allowedScopes(), 'key');
        $requested = strtolower(trim((string) $requested));

        return in_array($requested, $allowed, true) ? $requested : $allowed[0];
    }

    public function build(?string $period, ?string $scopeKey): array
    {
        $scopeKey = $this->resolveScope($scopeKey);
        $isArea = $scopeKey === UserBranchScope::AREA_SCOPE;
        $scope = [
            'key' => $scopeKey,
            'label' => $isArea ? 'Area 6 Konsol' : self::BRANCHES[$scopeKey]['label'],
            'level' => $isArea ? 'area' : 'branch',
            'entity_level' => $isArea ? 'branch' : 'unit',
        ];

        if (!Schema::hasTable(self::TABLE)) {
            return $this->unavailable($scope);
        }

        $available = $this->availablePeriods();
        $current = $this->onOrBefore($available, $period ? Carbon::parse($period)->toDateString() : ($available->last() ?? ''));
        if ($current === null) {
            return $this->unavailable($scope);
        }

        $compare = $this->comparePeriods($available, $current);
        $months = $this->monthEnds($available, $current);
        $periods = collect([$current])
            ->merge(collect($compare)->pluck('period'))
            ->merge($months)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $this->loadRows($periods, $isArea ? null : self::BRANCHES[$scopeKey]['kanca_key']);
        $byPeriod = $rows->groupBy('snapshot_period');
        $currentRows = $byPeriod->get($current, collect());
        $entities = $this->entities($currentRows, $isArea);

        $snapshot = function (string $period) use ($byPeriod, $isArea): array {
            return $this->snapshotFor($byPeriod->get($period, collect()), $isArea);
        };
        $now = $snapshot($current);
        $then = collect($compare)->map(fn (array $item): array => $item['period'] ? $snapshot($item['period']) : [])->all();
        $series = collect($months)->mapWithKeys(fn (string $month): array => [$month => $snapshot($month)['total']])->all();

        $context = [
            'now' => $now,
            'then' => $then,
            'entities' => $entities,
            'series' => $series,
        ];

        return [
            'available' => collect($now['total'])->contains(fn ($value): bool => $value !== null),
            'period' => $current,
            'period_label' => $this->formatDate($current),
            'scope' => $scope,
            'scopes' => $this->allowedScopes(),
            'compare' => collect($compare)->map(fn (array $item): array => [
                'period' => $item['period'],
                'label' => $item['period'] ? $this->formatDate($item['period']) : '-',
                'title' => $item['title'],
            ])->all(),
            'timeseries_labels' => array_map(fn (string $month): string => Carbon::parse($month)->translatedFormat('M y'), $months),
            'entities' => $entities,
            'simpanan' => $this->simpanan($context),
            'pinjaman' => $this->pinjaman($context),
            'kualitas' => $this->kualitas($context),
            'recovery' => $this->recovery($context),
        ];
    }

    /* ---------------------------------------------------------------- segments -- */

    private function simpanan(array $context): array
    {
        $products = [
            'giro' => ['label' => 'Giro', 'columns' => ['giro_ritel', 'giro_mikro', 'giro_wholesale']],
            'tabungan' => ['label' => 'Tabungan', 'columns' => ['tabungan_ritel', 'tabungan_mikro', 'tabungan_wholesale']],
            'deposito' => ['label' => 'Deposito', 'columns' => ['deposito_ritel', 'deposito_mikro', 'deposito_wholesale']],
        ];
        $segments = [
            'ritel' => ['label' => 'Ritel', 'columns' => ['giro_ritel', 'tabungan_ritel', 'deposito_ritel']],
            'mikro' => ['label' => 'Mikro', 'columns' => ['giro_mikro', 'tabungan_mikro', 'deposito_mikro']],
            'wholesale' => ['label' => 'Wholesale', 'columns' => ['giro_wholesale', 'tabungan_wholesale', 'deposito_wholesale']],
        ];
        $total = ['label' => 'Total Simpanan', 'columns' => ['total_simpanan']];
        $casa = ['label' => 'CASA', 'columns' => ['total_casa']];

        return [
            'total' => $this->metric($context, $total),
            'casa' => $this->metric($context, $casa, $total),
            'products' => $this->metrics($context, $products, $total),
            'segments' => $this->metrics($context, $segments, $total, dropEmpty: true),
            'rows' => $this->entityRows($context, array_merge(['total' => $total], $products, $segments, ['casa' => $casa])),
            'timeseries' => $this->timeseries($context, array_merge(['total' => $total], $products)),
        ];
    }

    private function pinjaman(array $context): array
    {
        $total = ['label' => 'Total Pinjaman', 'columns' => ['total_os_non_commercial']];
        $segments = [
            'sme' => ['label' => 'SME', 'columns' => ['sme_os']],
            'konsumer' => ['label' => 'Konsumer', 'columns' => ['consumer_os']],
            'mikro' => ['label' => 'Mikro', 'columns' => ['micro_os']],
        ];

        return [
            'total' => $this->metric($context, $total),
            'segments' => $this->metrics($context, $segments, $total),
            'products' => $this->productGroups($context, 'os', $total),
            'rows' => $this->entityRows($context, array_merge(['total' => $total], $segments)),
            'timeseries' => $this->timeseries($context, array_merge(['total' => $total], $segments)),
        ];
    }

    private function kualitas(array $context): array
    {
        $os = ['label' => 'Outstanding', 'columns' => ['total_os_non_commercial']];
        $sml = ['label' => 'SML', 'columns' => ['total_sml_abs_non_commercial']];
        $npl = ['label' => 'NPL', 'columns' => ['total_npl_abs_non_commercial']];
        $segments = [
            'sme' => ['label' => 'SME', 'os' => ['sme_os'], 'sml' => ['sme_sml'], 'npl' => ['sme_npl']],
            'konsumer' => ['label' => 'Konsumer', 'os' => ['consumer_os'], 'sml' => ['consumer_sml'], 'npl' => ['consumer_npl']],
            'mikro' => ['label' => 'Mikro', 'os' => ['micro_os'], 'sml' => ['micro_sml'], 'npl' => ['micro_npl']],
        ];

        $segmentQuality = collect($segments)->map(fn (array $segment, string $key): array => $this->qualityItem(
            $context,
            $key,
            $segment['label'],
            $segment['os'],
            $segment['sml'],
            $segment['npl']
        ))->values()->all();

        $ratioSeries = function (array $numerator) use ($context, $os): array {
            return collect($context['series'])->map(function (array $values) use ($numerator, $os): ?float {
                $base = $this->sum($values, $os['columns']);
                $value = $this->sum($values, $numerator);

                return $base !== null && $base > 0 && $value !== null ? round($value / $base * 100, 4) : null;
            })->values()->all();
        };

        $entityQuality = collect($context['entities'])->map(function (array $entity) use ($context, $os, $sml, $npl): array {
            $values = $context['now']['entities'][$entity['key']] ?? [];
            $base = $this->sum($values, $os['columns']);
            $smlValue = $this->sum($values, $sml['columns']);
            $nplValue = $this->sum($values, $npl['columns']);

            return [
                'key' => $entity['key'],
                'label' => $entity['label'],
                'kind' => $entity['kind'],
                'os' => $this->money($base),
                'sml' => $this->money($smlValue),
                'npl' => $this->money($nplValue),
                'sml_ratio' => $this->ratio($smlValue, $base),
                'npl_ratio' => $this->ratio($nplValue, $base),
            ];
        })->values()->all();

        return [
            'total' => $this->qualityItem($context, 'total', 'Total', $os['columns'], $sml['columns'], $npl['columns']),
            'segments' => $segmentQuality,
            'products' => collect($this->productDefinitions())->map(fn (array $group): array => [
                'key' => $group['key'],
                'label' => $group['label'],
                'products' => collect($group['products'])
                    ->map(fn (array $product): array => $this->qualityItem(
                        $context,
                        $product['key'],
                        $product['label'],
                        [$product['prefix'] . '_os'],
                        [$product['prefix'] . '_sml'],
                        [$product['prefix'] . '_npl']
                    ))
                    ->filter(fn (array $item): bool => $item['os']['value'] != 0.0)
                    ->values()
                    ->all(),
            ])->values()->all(),
            'rows' => $entityQuality,
            'timeseries' => [
                'series' => [
                    ['key' => 'sml_ratio', 'label' => 'Rasio SML', 'unit' => 'percent', 'values' => $ratioSeries($sml['columns'])],
                    ['key' => 'npl_ratio', 'label' => 'Rasio NPL', 'unit' => 'percent', 'values' => $ratioSeries($npl['columns'])],
                ],
            ],
        ];
    }

    private function recovery(array $context): array
    {
        $total = ['label' => 'Recovery DH', 'columns' => ['rec_dh_total']];
        $segments = [
            'kecil' => ['label' => 'Kecil', 'columns' => ['rec_dh_small']],
            'konsumer' => ['label' => 'Konsumer', 'columns' => ['rec_dh_consumer']],
            'mikro' => ['label' => 'Mikro', 'columns' => ['rec_dh_micro']],
        ];

        return [
            'total' => $this->metric($context, $total),
            'segments' => $this->metrics($context, $segments, $total),
            'rows' => $this->entityRows($context, array_merge(['total' => $total], $segments)),
            'timeseries' => $this->timeseries($context, array_merge(['total' => $total], $segments)),
        ];
    }

    /* ----------------------------------------------------------------- shaping -- */

    private function productDefinitions(): array
    {
        return [
            ['key' => 'sme', 'label' => 'SME', 'products' => [
                ['key' => 'kecil_non_cashcoll', 'label' => 'Kecil Non Cashcoll', 'prefix' => 'kecil_non_cashcoll'],
                ['key' => 'cashcoll', 'label' => 'Cashcoll', 'prefix' => 'cashcoll'],
            ]],
            ['key' => 'konsumer', 'label' => 'Konsumer', 'products' => [
                ['key' => 'briguna', 'label' => 'Briguna', 'prefix' => 'briguna_konsumer'],
                ['key' => 'kpr', 'label' => 'KPR', 'prefix' => 'kpr'],
                ['key' => 'kkb', 'label' => 'KKB', 'prefix' => 'kkb'],
            ]],
            ['key' => 'mikro', 'label' => 'Mikro', 'products' => [
                ['key' => 'kupedes', 'label' => 'Kupedes', 'prefix' => 'kupedes'],
                ['key' => 'kur_mikro', 'label' => 'KUR Mikro', 'prefix' => 'kur_mikro'],
                ['key' => 'briguna_mikro', 'label' => 'Briguna Mikro', 'prefix' => 'briguna_mikro'],
                ['key' => 'kur_kecil', 'label' => 'KUR Kecil', 'prefix' => 'kur_kecil'],
                ['key' => 'kpp', 'label' => 'KUR KPP', 'prefix' => 'kur_kpp'],
            ]],
        ];
    }

    /** Products per segment for one metric (os, sml or npl), each with its share of the scope total. */
    private function productGroups(array $context, string $metric, array $total): array
    {
        return collect($this->productDefinitions())->map(function (array $group) use ($context, $metric, $total): array {
            $products = collect($group['products'])
                ->mapWithKeys(fn (array $product): array => [$product['key'] => ['label' => $product['label'], 'columns' => [$product['prefix'] . '_' . $metric]]])
                ->all();

            return [
                'key' => $group['key'],
                'label' => $group['label'],
                'products' => $this->metrics($context, $products, $total, dropEmpty: true),
            ];
        })->values()->all();
    }

    /** @param array<string, array{label: string, columns: array<int, string>}> $definitions */
    private function metrics(array $context, array $definitions, ?array $shareOf = null, bool $dropEmpty = false): array
    {
        return collect($definitions)
            ->map(fn (array $definition, string $key): array => ['key' => $key] + $this->metric($context, $definition, $shareOf))
            ->filter(function (array $item) use ($dropEmpty, $definitions, $context): bool {
                if (!$dropEmpty || $item['value'] !== null) {
                    return !$dropEmpty || $item['value'] != 0.0;
                }

                // Retain an unknown source category so composition cannot normalize the remaining ones to 100%.
                return collect($definitions[$item['key']]['columns'])->contains(
                    fn (string $column): bool => array_key_exists($column, $context['now']['total'])
                );
            })
            ->values()
            ->all();
    }

    /** Current value, its deltas against each compare period, and its share of $shareOf. */
    private function metric(array $context, array $definition, ?array $shareOf = null): array
    {
        $value = $this->sum($context['now']['total'], $definition['columns']);
        $deltas = [];
        foreach ($context['then'] as $key => $snapshot) {
            $previous = $this->sum($snapshot['total'] ?? [], $definition['columns']);
            $deltas[$key] = $value === null || $previous === null
                ? null
                : $this->money($value - $previous, true);
        }
        $base = $shareOf ? $this->sum($context['now']['total'], $shareOf['columns']) : 0.0;

        return array_merge(
            ['label' => $definition['label']],
            $this->money($value),
            [
                'deltas' => $deltas,
                'share' => $shareOf ? $this->ratio($value, $base) : null,
            ]
        );
    }

    private function qualityItem(array $context, string $key, string $label, array $os, array $sml, array $npl): array
    {
        $now = $context['now']['total'];
        $base = $this->sum($now, $os);
        $smlValue = $this->sum($now, $sml);
        $nplValue = $this->sum($now, $npl);
        $ratioDelta = function (array $columns) use ($context, $os, $base): array {
            $currentValue = $this->sum($context['now']['total'], $columns);
            $current = $base !== null && $base > 0 && $currentValue !== null ? $currentValue / $base * 100 : null;
            $deltas = [];
            foreach ($context['then'] as $period => $snapshot) {
                $previousBase = $this->sum($snapshot['total'] ?? [], $os);
                $previousValue = $this->sum($snapshot['total'] ?? [], $columns);
                $deltas[$period] = $current === null || $previousBase === null || $previousBase <= 0 || $previousValue === null
                    ? null
                    : $this->points($current - $previousValue / $previousBase * 100);
            }

            return $deltas;
        };

        return [
            'key' => $key,
            'label' => $label,
            'os' => $this->money($base),
            'sml' => $this->money($smlValue),
            'npl' => $this->money($nplValue),
            'sml_ratio' => $this->ratio($smlValue, $base) + ['deltas' => $ratioDelta($sml)],
            'npl_ratio' => $this->ratio($nplValue, $base) + ['deltas' => $ratioDelta($npl)],
        ];
    }

    /** One row per entity (branch or outlet) with every metric, its share of the scope, and its MtD change. */
    private function entityRows(array $context, array $definitions): array
    {
        $scopeTotals = collect($definitions)->map(fn (array $definition): ?float => $this->sum($context['now']['total'], $definition['columns']))->all();

        return collect($context['entities'])->map(function (array $entity) use ($context, $definitions, $scopeTotals): array {
            $now = $context['now']['entities'][$entity['key']] ?? [];
            $mtd = $context['then']['mtd']['entities'][$entity['key']] ?? null;
            $values = [];
            foreach ($definitions as $key => $definition) {
                $value = $this->sum($now, $definition['columns']);
                $previous = $mtd === null ? null : $this->sum($mtd, $definition['columns']);
                $values[$key] = $this->money($value) + [
                    'share' => $this->ratio($value, $scopeTotals[$key])['value'],
                    'mtd' => $value === null || $previous === null ? null : $this->money($value - $previous, true),
                ];
            }

            return ['key' => $entity['key'], 'label' => $entity['label'], 'kind' => $entity['kind'], 'values' => $values];
        })->values()->all();
    }

    private function timeseries(array $context, array $definitions): array
    {
        return [
            'series' => collect($definitions)->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => $definition['label'],
                'unit' => 'money',
                'values' => collect($context['series'])->map(fn (array $values): ?float => $values === [] ? null : $this->sum($values, $definition['columns']))->values()->all(),
            ])->values()->all(),
        ];
    }

    /* ------------------------------------------------------------------ source -- */

    private function availablePeriods(): Collection
    {
        return DB::table(self::TABLE)
            ->select('snapshot_period')
            ->distinct()
            ->orderBy('snapshot_period')
            ->pluck('snapshot_period')
            ->map(fn ($period): string => substr((string) $period, 0, 10))
            ->values();
    }

    private function onOrBefore(Collection $available, string $target): ?string
    {
        return $available->filter(fn (string $period): bool => $period <= $target)->last();
    }

    /** DtD, MtD (last position of the previous month), YtD (last of the previous year) and YoY. */
    private function comparePeriods(Collection $available, string $current): array
    {
        $date = Carbon::parse($current);
        $before = $available->filter(fn (string $period): bool => $period < $current)->last();

        return [
            'dtd' => ['title' => 'DtD', 'period' => $before],
            'mtd' => ['title' => 'MtD', 'period' => $this->onOrBefore($available, $date->copy()->startOfMonth()->subDay()->toDateString())],
            'ytd' => ['title' => 'YtD', 'period' => $this->onOrBefore($available, $date->copy()->startOfYear()->subDay()->toDateString())],
            'yoy' => ['title' => 'YoY', 'period' => $this->onOrBefore($available, $date->copy()->subYear()->toDateString())],
        ];
    }

    /** The last available position of each of the past months, ending with the current position. */
    private function monthEnds(Collection $available, string $current): array
    {
        $start = Carbon::parse($current)->startOfMonth();
        $months = [];
        for ($offset = self::TIMESERIES_MONTHS - 1; $offset >= 1; $offset--) {
            $month = $start->copy()->subMonthsNoOverflow($offset);
            $period = $this->onOrBefore($available, $month->copy()->endOfMonth()->toDateString());
            if ($period !== null && Carbon::parse($period)->isSameMonth($month)) {
                $months[] = $period;
            }
        }
        $months[] = $current;

        return array_values(array_unique($months));
    }

    private function loadRows(array $periods, ?string $kancaKey): Collection
    {
        $columns = collect($this->metricColumns())->filter(fn (string $column): bool => $this->hasColumn($column))->values()->all();
        $query = DB::table(self::TABLE)
            ->whereIn('snapshot_period', $periods)
            ->whereIn('kanca_key', $kancaKey ? [$kancaKey] : array_column(self::BRANCHES, 'kanca_key'))
            ->select(array_merge(['snapshot_period', 'kanca_key', 'kanca_label', 'unit_key', 'unit_label'], $columns));

        return $query->get()->map(function (object $row): array {
            $values = (array) $row;
            $values['snapshot_period'] = substr((string) $row->snapshot_period, 0, 10);

            return $values;
        });
    }

    /** Totals of one period plus each entity's values; the Area reads branch rows, a branch its outlet rows. */
    private function snapshotFor(Collection $rows, bool $isArea): array
    {
        $entities = [];
        $total = [];
        foreach ($rows as $row) {
            $isBranchRow = $row['kanca_key'] === $row['unit_key'];
            if ($isArea !== $isBranchRow) {
                continue;
            }
            $key = $isArea ? $row['kanca_key'] : $row['unit_key'];
            $values = collect($this->metricColumns())
                ->filter(fn (string $column): bool => array_key_exists($column, $row))
                ->mapWithKeys(fn (string $column): array => [$column => $row[$column] === null ? null : (float) $row[$column]])
                ->all();
            $entities[$key] = $values;
            foreach ($values as $column => $value) {
                if (!array_key_exists($column, $total)) {
                    $total[$column] = $value;
                } elseif ($total[$column] !== null && $value !== null) {
                    $total[$column] += $value;
                } else {
                    $total[$column] = null;
                }
            }
        }

        return ['total' => $total, 'entities' => $entities];
    }

    private function entities(Collection $rows, bool $isArea): array
    {
        return $rows
            ->filter(fn (array $row): bool => ($row['kanca_key'] === $row['unit_key']) === $isArea)
            ->map(function (array $row) use ($isArea): array {
                $key = $isArea ? $row['kanca_key'] : $row['unit_key'];
                $kind = $isArea ? 'branch' : (str_starts_with($key, 'unit-') ? 'unit' : (str_starts_with($key, 'kcp-') ? 'kcp' : 'kc'));
                $label = (string) ($isArea ? $row['kanca_label'] : $row['unit_label']);

                return [
                    'key' => $key,
                    'label' => $kind === 'kc' ? $label . ' (kantor cabang)' : $label,
                    'kind' => $kind,
                    'order' => ['branch' => 0, 'kc' => 0, 'kcp' => 1, 'unit' => 2][$kind],
                ];
            })
            ->sortBy(fn (array $entity): string => $entity['order'] . '|' . $entity['label'])
            ->map(fn (array $entity): array => array_diff_key($entity, ['order' => true]))
            ->values()
            ->all();
    }

    private function metricColumns(): array
    {
        $products = ['kecil_non_cashcoll', 'cashcoll', 'briguna_konsumer', 'kpr', 'kkb', 'kupedes', 'kur_mikro', 'briguna_mikro', 'kur_kecil', 'kur_kpp'];

        return array_merge(
            [
                'total_simpanan', 'total_casa',
                'giro_ritel', 'giro_mikro', 'giro_wholesale',
                'tabungan_ritel', 'tabungan_mikro', 'tabungan_wholesale',
                'deposito_ritel', 'deposito_mikro', 'deposito_wholesale',
                'total_os_non_commercial', 'total_sml_abs_non_commercial', 'total_npl_abs_non_commercial',
                'sme_os', 'consumer_os', 'micro_os', 'sme_sml', 'consumer_sml', 'micro_sml', 'sme_npl', 'consumer_npl', 'micro_npl',
                'rec_dh_total', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro',
            ],
            collect($products)->flatMap(fn (string $prefix): array => [$prefix . '_os', $prefix . '_sml', $prefix . '_npl'])->all()
        );
    }

    private function hasColumn(string $column): bool
    {
        return $this->columnExists[$column] ??= Schema::hasColumn(self::TABLE, $column);
    }

    /* ------------------------------------------------------------- formatting -- */

    private function sum(array $values, array $columns): ?float
    {
        $known = false;
        $total = 0.0;
        foreach ($columns as $column) {
            // Older schemas may omit optional products; a present NULL is missing source data.
            if (!array_key_exists($column, $values)) {
                continue;
            }
            if ($values[$column] === null) {
                return null;
            }
            $known = true;
            $total += (float) $values[$column];
        }

        return $known ? $total : null;
    }

    private function money(?float $value, bool $signed = false): array
    {
        if ($value === null) {
            return ['value' => null, 'formatted' => '-'];
        }
        $absolute = abs($value);
        [$divisor, $suffix, $decimals] = match (true) {
            $absolute >= 1e12 => [1e12, ' T', 2],
            $absolute >= 1e9 => [1e9, ' M', 2],
            $absolute >= 1e6 => [1e6, ' Jt', 2],
            default => [1, '', 0],
        };
        $text = 'Rp' . number_format($absolute / $divisor, $decimals, ',', '.') . $suffix;
        $sign = $value < 0 ? '-' : ($signed && $value > 0 ? '+' : '');

        return ['value' => round($value, 2), 'formatted' => $sign . $text];
    }

    private function ratio(?float $value, ?float $base): array
    {
        if ($value === null || $base === null || $base <= 0.0) {
            return ['value' => null, 'formatted' => '-'];
        }
        $ratio = $value / $base * 100;

        return ['value' => round($ratio, 4), 'formatted' => number_format($ratio, 2, ',', '.') . '%'];
    }

    private function points(float $value): array
    {
        return ['value' => round($value, 4), 'formatted' => ($value > 0 ? '+' : ($value < 0 ? '-' : '')) . number_format(abs($value), 2, ',', '.') . ' pp'];
    }

    private function formatDate(string $period): string
    {
        return Carbon::parse($period)->locale('id')->translatedFormat('d M Y');
    }

    private function unavailable(array $scope): array
    {
        return ['available' => false, 'scope' => $scope, 'scopes' => $this->allowedScopes(), 'entities' => []];
    }
}
