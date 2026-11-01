<?php

namespace App\Services\Presentation;

use App\Support\UserBranchScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Kinerja segments of the segmented presentation (Simpanan, Pinjaman, Kualitas,
 * Recovery DH) for one scope. Balances/quality use dashboard_harian_snapshots;
 * Recovery DH follows the Data PH report's LW325/Cognos source and own period.
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

        if (! Schema::hasTable(self::TABLE)) {
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
            'recovery' => $this->recoveryFromDataPh($current, $scopeKey, $entities),
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

            $entityContext = ['now' => ['total' => $values], 'then' => collect($context['then'])->map(fn (array $snapshot): array => ['total' => $snapshot['entities'][$entity['key']] ?? []])->all()];
            $quality = $this->qualityItem($entityContext, $entity['key'], $entity['label'], $os['columns'], $sml['columns'], $npl['columns']);

            return [
                'key' => $entity['key'],
                'label' => $entity['label'],
                'kind' => $entity['kind'],
                'os' => $this->money($base),
                'sml' => $this->money($smlValue),
                'npl' => $this->money($nplValue),
                'sml_ratio' => $quality['sml_ratio'],
                'npl_ratio' => $quality['npl_ratio'],
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
                        [$product['prefix'].'_os'],
                        [$product['prefix'].'_sml'],
                        [$product['prefix'].'_npl']
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

    /** Recovery follows the Data PH report, not the GI405-derived dashboard columns. */
    private function recoveryFromDataPh(string $dashboardPeriod, string $scopeKey, array $snapshotEntities): array
    {
        $isArea = $scopeKey === UserBranchScope::AREA_SCOPE;
        $branches = $isArea ? array_column(self::BRANCHES, 'label') : [self::BRANCHES[$scopeKey]['label']];
        $periodsFor = function (string $table, string $branchColumn) use ($branches): Collection {
            return Schema::hasTable($table)
                ? DB::table($table)->whereIn($branchColumn, $branches)->distinct()->orderBy('periode')->pluck('periode')->map(fn ($date): string => substr((string) $date, 0, 10))
                : collect();
        };
        $phPeriods = $periodsFor('lw325_ph', 'kanca');
        $cognosPeriods = $periodsFor('cognos_recovery', 'cabang');
        $available = $phPeriods->isNotEmpty() ? $phPeriods : $cognosPeriods;
        $current = $this->onOrBefore($available, $dashboardPeriod);
        $comparisonPeriods = $phPeriods->merge($cognosPeriods)->unique()->sort()->values();
        $compare = $current ? $this->comparePeriods($comparisonPeriods, $current) : array_fill_keys(['dtd', 'mtd', 'ytd', 'yoy'], ['period' => null]);
        if ($current) {
            // Data PH's MtD and YtD baselines are calendar month/year ends, never an older position.
            foreach (['mtd' => Carbon::parse($current)->startOfMonth()->subDay()->toDateString(), 'ytd' => Carbon::parse($current)->startOfYear()->subDay()->toDateString()] as $key => $target) {
                $compare[$key]['period'] = $comparisonPeriods->contains($target) ? $target : null;
            }
            $yearAgo = Carbon::parse($current)->subYearNoOverflow()->toDateString();
            $compare['yoy']['period'] = $this->onOrBefore($available, $yearAgo) ?? $this->onOrBefore($comparisonPeriods, $yearAgo);
        }
        $months = $current ? $this->monthEnds($available, $current) : [];
        $needed = collect([$current])->merge(collect($compare)->pluck('period'))->merge($months)->filter()->unique();
        $knownKeys = [];
        foreach ($snapshotEntities as $entity) {
            $label = preg_replace('/ \(kantor cabang\)$/', '', $entity['label']);
            $knownKeys[$this->phUnitName($label)] = $entity;
        }
        $snapshots = [];
        $sourcePeriods = [];
        foreach ($needed as $period) {
            $source = $this->dataPhRecoveryRows($period, $branches);
            $sourcePeriods[$period] = ['table' => $source['table'], 'baseline_period' => $source['baseline_period'], 'available' => $source['available']];
            $entities = [];
            $totals = [];
            $definitions = [];
            foreach ($source['rows'] as $row) {
                $branchKey = array_search($row['branch'], array_column(self::BRANCHES, 'label', 'kanca_key'), true);
                if ($branchKey === false) {
                    continue;
                }
                $unitName = $this->phUnitName($row['unit']);
                $key = $isArea ? $branchKey : ($knownKeys[$unitName]['key'] ?? (Str::slug($unitName) === $branchKey ? $branchKey.'-detail' : Str::slug($unitName)));
                $entities[$key] ??= array_fill_keys(['rec_dh_total', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_micro'], 0.0);
                $entities[$key]['rec_dh_total'] += $row['total'];
                $entities[$key]['rec_dh_small'] += $row['small'];
                $entities[$key]['rec_dh_consumer'] += $row['consumer_briguna'] + $row['consumer_kpr'];
                $entities[$key]['rec_dh_micro'] += $row['micro'];
                $kind = $isArea ? 'branch' : (str_starts_with($unitName, 'UNIT ') ? 'unit' : (str_starts_with($unitName, 'KCP ') ? 'kcp' : 'kc'));
                $definitions[$key] = ['key' => $key, 'label' => $isArea ? $row['branch'] : ($knownKeys[$unitName]['label'] ?? $unitName), 'kind' => $kind];
            }
            foreach ($entities as $values) {
                foreach ($values as $column => $value) {
                    $totals[$column] = ($totals[$column] ?? 0.0) + $value;
                }
            }
            $snapshots[$period] = ['total' => $totals, 'entities' => $entities, 'definitions' => array_values($definitions)];
        }
        $now = $snapshots[$current] ?? ['total' => [], 'entities' => [], 'definitions' => []];
        $context = ['now' => $now,
            'then' => collect($compare)->map(fn (array $item): array => $snapshots[$item['period']] ?? [])->all(),
            'entities' => $now['definitions'],
            'series' => collect($months)->mapWithKeys(fn (string $period): array => [$period => $snapshots[$period]['total']])->all()];
        $result = $this->recovery($context);
        $result['available'] = $sourcePeriods[$current]['available'] ?? false;
        $result['period'] = $current;
        $result['period_label'] = $current ? $this->formatDate($current) : '-';
        $result['compare'] = collect($compare)->map(fn (array $item): array => ['period' => $item['period'], 'label' => $item['period'] ? $this->formatDate($item['period']) : '-'])->all();
        $result['timeseries_labels'] = array_map(fn (string $period): string => Carbon::parse($period)->translatedFormat('M y'), $months);
        $result['source'] = ['label' => 'Data PH', 'table' => $sourcePeriods[$current]['table'] ?? null,
            'period' => $current, 'period_label' => $result['period_label'], 'basis' => 'recovery_bulanan_data_ph',
            'baseline_period' => $sourcePeriods[$current]['baseline_period'] ?? null, 'periods' => $sourcePeriods,
            'note' => 'Pergerakan pokok LW325 PH terhadap posisi terakhir bulan sebelumnya, termasuk klaim baru; Cognos menjadi fallback seperti laporan Data PH. Konsumer mencakup Briguna dan KPR sesuai klasifikasi laporan.'];

        return $result;
    }

    /** Same account normalization, previous-month attribution and claim share (100%) as Data PH. */
    private function dataPhRecoveryRows(string $period, array $branches): array
    {
        $metrics = [];
        $baseline = null;
        $phAvailable = false;
        if (Schema::hasTable('lw325_ph')) {
            $query = DB::table('lw325_ph')->where('periode', $period)->whereNotNull('acctno')->where('acctno', '<>', '');
            $phAvailable = $query->exists();
            $baseline = DB::table('lw325_ph')->whereBetween('periode', [Carbon::parse($period)->startOfMonth()->subMonth()->toDateString(), Carbon::parse($period)->startOfMonth()->subDay()->toDateString()])->max('periode');
            if ($phAvailable && $baseline) {
                $account = fn (string $alias): string => DB::connection()->getDriverName() === 'sqlite'
                    ? "LTRIM(TRIM(COALESCE({$alias}.acctno, '')), '0')"
                    : "TRIM(LEADING '0' FROM TRIM(COALESCE({$alias}.acctno, '')))";
                $currentKey = $account('n');
                $previousKey = $account('o');
                $flag = Schema::hasColumn('lw325_ph', 'flag_klaim') ? "CASE WHEN UPPER(TRIM(COALESCE(o.flag_klaim, ''))) = 'Y' THEN 'Y' ELSE 'N' END" : "'N'";
                // Current and old-account existence are global: transferring KC/unit is not repayment.
                $currentRows = DB::table('lw325_ph as n')->where('n.periode', $period)->whereNotNull('n.acctno')->where('n.acctno', '<>', '')
                    ->selectRaw("{$currentKey} as account_key, SUM(COALESCE(n.pokok, 0)) as current_pokok")->groupByRaw($currentKey);
                $previousRows = DB::table('lw325_ph as o')->where('o.periode', $baseline)->whereIn('o.kanca', $branches)->whereNotNull('o.acctno')->where('o.acctno', '<>', '')
                    ->selectRaw("{$previousKey} as account_key, o.kanca, o.unit, o.segmen_dashboard, o.produk_dashboard, {$flag} as flag_klaim, SUM(COALESCE(o.pokok, 0)) as old_pokok")
                    ->groupByRaw("{$previousKey}, o.kanca, o.unit, o.segmen_dashboard, o.produk_dashboard, {$flag}");
                $movements = DB::query()->fromSub($previousRows, 'o')->leftJoinSub($currentRows, 'n', fn ($join) => $join->on('o.account_key', '=', 'n.account_key'))
                    ->select('o.kanca', 'o.unit', 'o.segmen_dashboard', 'o.produk_dashboard')
                    ->selectRaw('SUM(CASE WHEN n.account_key IS NULL THEN o.old_pokok WHEN o.old_pokok > COALESCE(n.current_pokok,0) THEN o.old_pokok - n.current_pokok ELSE 0 END) as recovery')
                    ->groupBy('o.kanca', 'o.unit', 'o.segmen_dashboard', 'o.produk_dashboard')->get();
                foreach ($movements as $row) {
                    $this->addPhRecovery($metrics, $row->kanca, $row->unit, $this->phSegment($row->segmen_dashboard, $row->produk_dashboard), (float) $row->recovery);
                }
                foreach (DB::table('lw325_ph')->where('periode', $period)->whereIn('kanca', $branches)->whereNotNull('unit')->where('unit', '<>', '')->distinct()->get(['kanca', 'unit']) as $row) {
                    $this->addPhRecovery($metrics, $row->kanca, $row->unit, null, 0.0);
                }
                if (Schema::hasColumn('lw325_ph', 'flag_klaim') && Schema::hasColumn('lw325_ph', 'clmamt')) {
                    $newClaims = DB::table('lw325_ph as n')->where('n.periode', $period)->whereIn('n.kanca', $branches)->whereNotNull('n.acctno')->where('n.acctno', '<>', '')
                        ->whereRaw("UPPER(TRIM(COALESCE(n.flag_klaim, ''))) = 'Y'")
                        ->selectRaw("{$currentKey} as account_key, n.kanca, n.unit, n.segmen_dashboard, n.produk_dashboard, SUM(COALESCE(n.clmamt,0)) as claim")
                        ->groupByRaw("{$currentKey}, n.kanca, n.unit, n.segmen_dashboard, n.produk_dashboard");
                    $oldAccounts = DB::table('lw325_ph as o')->where('o.periode', $baseline)->whereNotNull('o.acctno')->where('o.acctno', '<>', '')->selectRaw("DISTINCT {$previousKey} as account_key");
                    $claims = DB::query()->fromSub($newClaims, 'n')->leftJoinSub($oldAccounts, 'o', fn ($join) => $join->on('o.account_key', '=', 'n.account_key'))
                        ->whereNull('o.account_key')->select('n.kanca', 'n.unit', 'n.segmen_dashboard', 'n.produk_dashboard')->selectRaw('SUM(n.claim) as claim')
                        ->groupBy('n.kanca', 'n.unit', 'n.segmen_dashboard', 'n.produk_dashboard')->get();
                    foreach ($claims as $row) {
                        if ((float) $row->claim > 0) {
                            $this->addPhRecovery($metrics, $row->kanca, $row->unit, $this->phSegment($row->segmen_dashboard, $row->produk_dashboard), (float) $row->claim);
                        }
                    }
                }
            }
        }
        $hasRecovery = collect($metrics)->contains(fn (array $row): bool => collect(['total', 'micro', 'small', 'consumer_briguna', 'consumer_kpr'])->contains(fn (string $key): bool => abs($row[$key]) > 0.0001));
        if (! $hasRecovery && Schema::hasTable('cognos_recovery')) {
            $cognos = DB::table('cognos_recovery')->where('periode', $period)->whereIn('cabang', $branches)
                ->select('cabang', 'unit_kerja', 'segmen_2', 'produk')->selectRaw('SUM(COALESCE(total_recovery,0)) as recovery')->groupBy('cabang', 'unit_kerja', 'segmen_2', 'produk')->get();
            if ($cognos->isNotEmpty()) {
                $metrics = [];
                foreach ($cognos as $row) {
                    $segment = $this->phSegment($row->segmen_2, $row->produk);
                    $token = strtoupper((string) $row->segmen_2);
                    if ($segment === null && (str_contains($token, 'CONSUMER') || str_contains($token, 'KONSUMER'))) {
                        $segment = 'consumer_briguna';
                    }
                    $this->addPhRecovery($metrics, $row->cabang, $row->unit_kerja, $segment, (float) $row->recovery);
                }

                return ['rows' => array_values($metrics), 'table' => 'cognos_recovery', 'baseline_period' => null, 'available' => true];
            }
        }

        return ['rows' => array_values($metrics), 'table' => 'lw325_ph', 'baseline_period' => $baseline, 'available' => $phAvailable && $baseline !== null];
    }

    private function phUnitName(string $unit): string
    {
        return trim(strtoupper(preg_replace('/^\d+\s*--\s*/', '', $unit) ?? $unit));
    }

    private function phSegment(?string $segment, ?string $product): ?string
    {
        $segment = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $segment))) ?? '';
        $product = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $product))) ?? '';
        if (str_contains($segment, 'MICRO') || str_contains($segment, 'MIKRO')) {
            return 'micro';
        }
        if (str_contains($segment, 'SMALL') || str_contains($segment, 'KECIL')) {
            return 'small';
        }
        $consumer = str_contains($segment, 'CONSUMER') || str_contains($segment, 'KONSUMER');
        if (($consumer && str_contains($product, 'BRIGUNA')) || str_contains($product, 'BRIGUNAKONSUMER') || str_contains($product, 'BRIGUNACONSUMER')) {
            return 'consumer_briguna';
        }
        if ($consumer && str_contains($product, 'KPR')) {
            return 'consumer_kpr';
        }

        return null;
    }

    private function addPhRecovery(array &$metrics, string $branch, ?string $unit, ?string $segment, float $amount): void
    {
        $key = strtoupper(trim($branch)).'|'.$this->phUnitName((string) $unit);
        $metrics[$key] ??= ['branch' => $branch, 'unit' => (string) $unit, 'total' => 0.0, 'micro' => 0.0, 'small' => 0.0, 'consumer_briguna' => 0.0, 'consumer_kpr' => 0.0];
        if ($segment === null) {
            return;
        }
        $metrics[$key][$segment] += $amount;
        $metrics[$key]['total'] += $amount;
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
                ->mapWithKeys(fn (array $product): array => [$product['key'] => ['label' => $product['label'], 'columns' => [$product['prefix'].'_'.$metric]]])
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
                if (! $dropEmpty || $item['value'] !== null) {
                    return ! $dropEmpty || $item['value'] != 0.0;
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

        $previous = function (array $columns) use ($context, $os): array {
            return collect($context['then'])->map(fn (array $snapshot): array => $this->ratio(
                $this->sum($snapshot['total'] ?? [], $columns),
                $this->sum($snapshot['total'] ?? [], $os)
            ))->all();
        };

        return [
            'key' => $key,
            'label' => $label,
            'os' => $this->money($base),
            'sml' => $this->money($smlValue),
            'npl' => $this->money($nplValue),
            'sml_ratio' => $this->ratio($smlValue, $base) + ['deltas' => $ratioDelta($sml), 'previous' => $previous($sml)],
            'npl_ratio' => $this->ratio($nplValue, $base) + ['deltas' => $ratioDelta($npl), 'previous' => $previous($npl)],
        ];
    }

    /** One row per entity with its share and changes against every available comparison. */
    private function entityRows(array $context, array $definitions): array
    {
        $scopeTotals = collect($definitions)->map(fn (array $definition): ?float => $this->sum($context['now']['total'], $definition['columns']))->all();

        return collect($context['entities'])->map(function (array $entity) use ($context, $definitions, $scopeTotals): array {
            $now = $context['now']['entities'][$entity['key']] ?? [];
            $values = [];
            foreach ($definitions as $key => $definition) {
                $value = $this->sum($now, $definition['columns']);
                $deltas = collect($context['then'])->map(function (array $snapshot) use ($entity, $definition, $value): ?array {
                    $previous = $this->sum($snapshot['entities'][$entity['key']] ?? [], $definition['columns']);

                    return $value === null || $previous === null ? null : $this->money($value - $previous, true);
                })->all();
                $values[$key] = $this->money($value) + [
                    'share' => $this->ratio($value, $scopeTotals[$key])['value'],
                    'mtd' => $deltas['mtd'] ?? null,
                    'deltas' => $deltas,
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
                if (! array_key_exists($column, $total)) {
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
                    'label' => $kind === 'kc' ? $label.' (kantor cabang)' : $label,
                    'kind' => $kind,
                    'order' => ['branch' => 0, 'kc' => 0, 'kcp' => 1, 'unit' => 2][$kind],
                ];
            })
            ->sortBy(fn (array $entity): string => $entity['order'].'|'.$entity['label'])
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
            collect($products)->flatMap(fn (string $prefix): array => [$prefix.'_os', $prefix.'_sml', $prefix.'_npl'])->all()
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
            if (! array_key_exists($column, $values)) {
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
        $text = 'Rp'.number_format($absolute / $divisor, $decimals, ',', '.').$suffix;
        $sign = $value < 0 ? '-' : ($signed && $value > 0 ? '+' : '');

        return ['value' => round($value, 2), 'formatted' => $sign.$text];
    }

    private function ratio(?float $value, ?float $base): array
    {
        if ($value === null || $base === null || $base <= 0.0) {
            return ['value' => null, 'formatted' => '-'];
        }
        $ratio = $value / $base * 100;

        return ['value' => round($ratio, 4), 'formatted' => number_format($ratio, 2, ',', '.').'%'];
    }

    private function points(float $value): array
    {
        return ['value' => round($value, 4), 'formatted' => ($value > 0 ? '+' : ($value < 0 ? '-' : '')).number_format(abs($value), 2, ',', '.').' pp'];
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
