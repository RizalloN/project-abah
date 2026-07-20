<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DataPhReportController extends Controller
{
    /** @var array<string, bool> */
    private array $phPeriodRowAvailability = [];

    /** @var array<string, string|null> */
    private array $previousPhPeriodByPeriod = [];

    public function index(Request $request): View
    {
        $area6Branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

        $phPeriods = Schema::hasTable('lw325_ph')
            ? DB::table('lw325_ph')->select('periode')->distinct()->pluck('periode')
            : collect();

        $cognosPeriods = DB::table('cognos_recovery')
            ->select('periode')
            ->distinct()
            ->pluck('periode');

        $availablePeriods = ($phPeriods->isNotEmpty() ? $phPeriods : $cognosPeriods)
            ->unique()
            ->map(fn($p) => Carbon::parse($p)->toDateString())
            ->sortDesc()
            ->values();

        $comparisonPeriods = $phPeriods
            ->merge($cognosPeriods)
            ->unique()
            ->map(fn($p) => Carbon::parse($p)->toDateString())
            ->sortDesc()
            ->values();

        $requestedPeriod = $request->input('periode');
        $selectedPeriod = null;

        if ($requestedPeriod && $availablePeriods->contains($requestedPeriod)) {
            $selectedPeriod = $requestedPeriod;
        } elseif ($availablePeriods->isNotEmpty()) {
            $selectedPeriod = $availablePeriods->first();
        }

        // 2. Fetch Filter Options from nominatif PH, with legacy recovery table as display fallback.
        $availableKanca = $this->fetchPhKancaOptions($area6Branches, $selectedPeriod);

        // Handle selected Kancas (KC)
        $selectedKanca = $request->input('kanca');
        if (is_string($selectedKanca)) {
            $selectedKanca = array_filter(explode(',', $selectedKanca));
        }

        if (!is_array($selectedKanca)) {
            $selectedKanca = [];
        }

        $selectedKanca = array_values(array_filter(
            $selectedKanca,
            fn($kanca) => in_array($kanca, $area6Branches, true)
        ));

        // Default to Area 6 if no filter is applied
        if (empty($selectedKanca) && !$request->has('kanca')) {
            $selectedKanca = $area6Branches;
        }

        $isArea6All = count($selectedKanca) === count($area6Branches)
            && empty(array_diff($area6Branches, $selectedKanca));

        // Fetch ALL Units for Area 6 branches so the UI can dynamically filter them on the client-side.
        $availableUnits = $this->fetchPhUnitOptions($area6Branches, $selectedPeriod);

        $selectedUnit = $request->input('unit_kerja', 'all');
        if ($isArea6All) {
            $selectedUnit = 'all';
        }

        $rows = [];
        $selectedPeriodLabel = 'No Data';

        if ($selectedPeriod) {
            $selectedCarbon = Carbon::parse($selectedPeriod);
            
            // M-1 is the end of the previous month
            $m1Period = $selectedCarbon->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $m1EffectivePeriod = $comparisonPeriods->contains($m1Period) ? $m1Period : null;

            // YoY is 1 year before the selected period
            $yoyPeriod = $selectedCarbon->copy()->subYearNoOverflow()->toDateString();
            $yoyEffectivePeriod = $availablePeriods->first(fn($p) => $p <= $yoyPeriod)
                ?? $comparisonPeriods->first(fn($p) => $p <= $yoyPeriod);

            // YTD is December end of the previous year
            $ytdPeriod = $selectedCarbon->copy()->subYearNoOverflow()->endOfYear()->toDateString();
            $ytdEffectivePeriod = $comparisonPeriods->contains($ytdPeriod) ? $ytdPeriod : null;

            // 3. Fetch Metrics from nominatif PH.
            $currentMetrics = $this->getDataPhRecoveryMetrics($selectedPeriod, $selectedKanca, $selectedUnit);
            $sisaPhMetrics = $this->getSisaPhMetricsFromPh($selectedPeriod, $selectedKanca, $selectedUnit);

            $m1Metrics = [];
            if ($m1EffectivePeriod) {
                $m1Metrics = $this->getDataPhRecoveryMetrics($m1EffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $yoyMetrics = [];
            if ($yoyEffectivePeriod) {
                $yoyMetrics = $this->getDataPhRecoveryMetrics($yoyEffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $ytdMetrics = [];
            if ($ytdEffectivePeriod) {
                $ytdMetrics = $this->getDataPhRecoveryMetrics($ytdEffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $yoyPeriodLabel = $this->formatDataPhPeriodLabel($yoyEffectivePeriod);
            $ytdPeriodLabel = $this->formatDataPhPeriodLabel($ytdEffectivePeriod);
            $m1PeriodLabel = $this->formatDataPhPeriodLabel($m1EffectivePeriod);
            $selectedPeriodLabel = $this->formatDataPhPeriodLabel($selectedPeriod);

            // 5. Build Final Rows
            if ($isArea6All) {
                $branchCodes = $this->fetchBranchOfficeCodes($area6Branches);
                
                $branchCurrentMetrics = $this->getBranchOfficeDataPhRecoveryMetrics($selectedPeriod, $area6Branches);
                $branchSisaPhMetrics = $this->getBranchOfficeSisaPhMetricsFromPh($selectedPeriod, $area6Branches);
                
                $branchM1Metrics = [];
                if ($m1EffectivePeriod) {
                    $branchM1Metrics = $this->getBranchOfficeDataPhRecoveryMetrics($m1EffectivePeriod, $area6Branches);
                }

                $branchYoyMetrics = [];
                if ($yoyEffectivePeriod) {
                    $branchYoyMetrics = $this->getBranchOfficeDataPhRecoveryMetrics($yoyEffectivePeriod, $area6Branches);
                }

                $branchYtdMetrics = [];
                if ($ytdEffectivePeriod) {
                    $branchYtdMetrics = $this->getBranchOfficeDataPhRecoveryMetrics($ytdEffectivePeriod, $area6Branches);
                }

                foreach ($area6Branches as $index => $branchOffice) {
                    $curr = $branchCurrentMetrics[$branchOffice] ?? $this->emptyDataPhMetrics();
                    $prev = $branchM1Metrics[$branchOffice] ?? $this->emptyDataPhMetrics();
                    $yoy = $branchYoyMetrics[$branchOffice] ?? $this->emptyDataPhMetrics();
                    $ytd = $branchYtdMetrics[$branchOffice] ?? $this->emptyDataPhMetrics();
                    $sisaPh = $branchSisaPhMetrics[$branchOffice] ?? $this->emptyDataPhMetrics();

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $branchOffice,
                        'buc' => $branchCodes[$branchOffice] ?? '-',
                        'branch_office' => $branchOffice,
                        'recovery_yoy' => $yoy,
                        'recovery_ytd' => $ytd,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'sisa_ph' => $sisaPh,
                        'delta_yoy' => $this->diffDataPhMetrics($curr, $yoy),
                        'delta_ytd' => $this->diffDataPhMetrics($curr, $ytd),
                        'delta_m1' => $this->diffDataPhMetrics($curr, $prev),
                    ];
                }
            } else {
                // Detail mode: show recovery per unit kerja under the selected cabang
                $units = $this->fetchPhUnitRows($area6Branches, $selectedPeriod, $selectedKanca, $selectedUnit)
                    ->sortBy(function($u) {
                        preg_match('/\d+/', $u->unit, $matches);
                        $code = $matches[0] ?? '99999';
                        return $u->cabang . '|' . str_pad($code, 5, '0', STR_PAD_LEFT);
                    })
                    ->values();

                foreach ($units as $index => $u) {
                    $lookupKey = trim(strtoupper($u->cabang)) . '|' . $this->normalizeUnitName($u->unit);

                    $curr = $currentMetrics[$lookupKey] ?? $this->emptyDataPhMetrics();
                    $prev = $m1Metrics[$lookupKey] ?? $this->emptyDataPhMetrics();
                    $yoy = $yoyMetrics[$lookupKey] ?? $this->emptyDataPhMetrics();
                    $ytd = $ytdMetrics[$lookupKey] ?? $this->emptyDataPhMetrics();
                    $sisaPh = $sisaPhMetrics[$lookupKey] ?? $this->emptyDataPhMetrics();

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $u->cabang,
                        'buc' => '-',
                        'unit' => $u->unit,
                        'recovery_yoy' => $yoy,
                        'recovery_ytd' => $ytd,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'sisa_ph' => $sisaPh,
                        'delta_yoy' => $this->diffDataPhMetrics($curr, $yoy),
                        'delta_ytd' => $this->diffDataPhMetrics($curr, $ytd),
                        'delta_m1' => $this->diffDataPhMetrics($curr, $prev),
                    ];
                }
            }

        }

        $summary = [
            'total_recovery' => collect($rows)->sum(fn($r) => $r['recovery_curr']['total'] ?? 0),
            'total_sisa_ph' => collect($rows)->sum(fn($r) => $r['sisa_ph']['total'] ?? 0),
        ];
        $grandTotals = $this->buildGrandTotalMetrics($rows);

        return view('report.data-ph', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $selectedPeriodLabel,
            'yoyPeriodLabel' => $yoyPeriodLabel ?? '-',
            'ytdPeriodLabel' => $ytdPeriodLabel ?? '-',
            'm1PeriodLabel' => $m1PeriodLabel ?? '-',
            'rows' => $rows,
            'summary' => $summary,
            'grandTotals' => $grandTotals,
            'area6Branches' => $area6Branches,
            'isArea6All' => $isArea6All,
            'filters' => [
                'kanca' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Kanca']], $availableKanca)),
                'unit_kerja' => array_values(array_merge([['value' => 'all', 'label' => 'Semua Unit Kerja']], $availableUnits)),
            ],
            'selected' => [
                'kanca' => $selectedKanca,
                'unit_kerja' => $selectedUnit,
            ]
        ]);
    }

    public function nominatif(Request $request): JsonResponse
    {
        if (!Schema::hasTable('lw325_ph')) {
            return response()->json([
                'columns' => [],
                'rows' => [],
                'total_count' => 0,
                'display_count' => 0,
                'total_pokok' => 0,
                'message' => 'Tabel lw325_ph belum tersedia.',
            ]);
        }

        try {
            $period = Carbon::parse((string) $request->input('periode'))->toDateString();
        } catch (\Throwable) {
            return response()->json([
                'columns' => [],
                'rows' => [],
                'total_count' => 0,
                'display_count' => 0,
                'total_pokok' => 0,
                'message' => 'Periode nominatif tidak valid.',
            ], 422);
        }

        $segment = strtolower(trim((string) $request->input('segment', 'total')));
        $kancas = $this->normalizeDataPhKancaRequest($request->input('kanca'));
        $unit = trim((string) $request->input('unit_kerja', 'all'));
        $limit = max(1, min((int) $request->input('limit', 1000), 2000));
        $columns = array_values(array_filter(
            Schema::getColumnListing('lw325_ph'),
            fn (string $column): bool => $column !== 'uniqueid_namareport'
        ));

        $baseQuery = DB::table('lw325_ph')
            ->where('periode', $period);

        if ($kancas !== []) {
            $baseQuery->whereIn('kanca', $kancas);
        }

        if ($unit !== '' && strtolower($unit) !== 'all') {
            $baseQuery->where(DB::raw('TRIM(UPPER(unit))'), $this->normalizeUnitName($unit));
        }

        $this->applyDataPhSegmentFilter($baseQuery, $segment);

        $totalCount = (clone $baseQuery)->count();
        $totalPokok = (float) ((clone $baseQuery)->sum(DB::raw('COALESCE(pokok, 0)')) ?? 0);

        $rows = $baseQuery
            ->select($columns)
            ->orderBy('kanca')
            ->orderBy('unit')
            ->orderBy('acctno')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();

        return response()->json([
            'columns' => $this->formatNominatifColumns($columns),
            'rows' => $rows,
            'total_count' => $totalCount,
            'display_count' => count($rows),
            'total_pokok' => $totalPokok,
            'segment' => $segment,
            'period' => $period,
            'limit' => $limit,
        ]);
    }

    private function fetchRkaTargetsByCode(string $monthColumn, int $year): array
    {
        $rkaDefinitions = [
            'micro' => ['C. 1. a. Recovery Ekstrakomtabel Mikro', 'C. 1. b. Recovery Ekstrakomtabel Kece'],
            'small' => ['C. 2. Recovery Ekstrakomtabel Small'],
            'consumer' => ['C. 4. Recovery Ekstrakomtabel Konsumer'],
            'total' => ['C. RECOVERY EKSTRAKOMTABEL'],
        ];

        $allMataAnggaran = array_merge(...array_values($rkaDefinitions));

        $results = DB::table('rka')
            ->whereIn('mata_anggaran', $allMataAnggaran)
            ->whereYear('created_at', $year)
            ->select('desc_uker', 'mata_anggaran', $monthColumn)
            ->get();

        $rkaByCode = [];
        foreach ($results as $row) {
            // Extract code from desc_uker (8114-UNIT... -> 8114)
            preg_match('/\d+/', $row->desc_uker, $matches);
            if (!isset($matches[0])) continue;

            $code = (int)$matches[0];
            $val = (float)$row->{$monthColumn};

            if (!isset($rkaByCode[$code])) {
                $rkaByCode[$code] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
            }

            foreach ($rkaDefinitions as $key => $mataAnggarans) {
                if (in_array($row->mata_anggaran, $mataAnggarans, true)) {
                    $rkaByCode[$code][$key] += $val;
                    break;
                }
            }
        }

        return $rkaByCode;
    }

    private function fetchBranchOfficeRkaTargets(string $monthColumn, int $year, array $branchOffices): array
    {
        $rkaDefinitions = [
            'micro' => [
                'mata_anggaran' => ['C. 1. a. Recovery Ekstrakomtabel Mikro', 'C. 1. b. Recovery Ekstrakomtabel Kece'],
            ],
            'small' => [
                'mata_anggaran' => ['C. 2. Recovery Ekstrakomtabel Small'],
            ],
            'consumer' => [
                'mata_anggaran' => ['C. 4. Recovery Ekstrakomtabel Konsumer'],
            ],
            'total' => [
                'mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL'],
            ],
        ];

        $rkaService = app(RkaLookupService::class);
        $results = [];

        foreach ($branchOffices as $branchOffice) {
            $results[$branchOffice] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];

            if ($branchOffice === 'KC Ponorogo') {
                $branchKey = strtoupper($branchOffice);
                $direct = $rkaService->aggregateByGroup(
                    $rkaDefinitions,
                    $monthColumn,
                    [$branchOffice],
                    [],
                    'kanca',
                    $year
                );

                foreach (array_keys($rkaDefinitions) as $definitionKey) {
                    $results[$branchOffice][$definitionKey] += (float) ($direct[$definitionKey][$branchKey] ?? 0);
                }

                continue;
            }

            $regionPatterns = match ($branchOffice) {
                'KC Madiun' => ['MADIUN'],
                'KC Magetan' => ['MAGETAN'],
                'KC Ngawi' => ['NGAWI'],
                default => [],
            };

            if (!empty($regionPatterns)) {
                $regional = $rkaService->aggregateByGroupWithRegionalFilter(
                    $rkaDefinitions,
                    $monthColumn,
                    $regionPatterns,
                    $year
                );

                $regionKey = $regionPatterns[0];
                foreach (array_keys($rkaDefinitions) as $definitionKey) {
                    $results[$branchOffice][$definitionKey] += (float) ($regional[$definitionKey][$regionKey] ?? 0);
                }
            }
        }

        $fallbackGroups = $rkaService->aggregateByKancaWithSummaryFallback(
            $rkaDefinitions,
            $monthColumn,
            $branchOffices,
            $year
        );

        foreach ($branchOffices as $branchOffice) {
            $branchKey = strtoupper(trim($branchOffice));
            foreach (array_keys($rkaDefinitions) as $definitionKey) {
                if (abs((float) ($results[$branchOffice][$definitionKey] ?? 0)) <= 0.0) {
                    $results[$branchOffice][$definitionKey] = (float) ($fallbackGroups[$definitionKey][$branchKey] ?? 0);
                }
            }
        }

        return $results;
    }

    private function fetchPhKancaOptions(array $area6Branches, ?string $period): array
    {
        if (Schema::hasTable('lw325_ph')) {
            $query = DB::table('lw325_ph')
                ->select('kanca as value', 'kanca as label')
                ->distinct()
                ->whereIn('kanca', $area6Branches);

            if ($period) {
                $query->where('periode', $period);
            }

            $options = $query
                ->orderBy('kanca')
                ->get()
                ->map(fn($item) => (array) $item)
                ->toArray();

            if (!empty($options)) {
                return $options;
            }
        }

        return DB::table('cognos_recovery')
            ->select('cabang as value', 'cabang as label')
            ->distinct()
            ->whereIn('cabang', $area6Branches)
            ->orderBy('cabang')
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }

    private function fetchPhUnitOptions(array $area6Branches, ?string $period): array
    {
        if (Schema::hasTable('lw325_ph')) {
            $query = DB::table('lw325_ph')
                ->select('unit as value', 'unit as label', 'kanca as kanca_value')
                ->distinct()
                ->whereIn('kanca', $area6Branches)
                ->whereNotNull('unit')
                ->where('unit', '<>', '');

            if ($period) {
                $query->where('periode', $period);
            }

            $options = $query
                ->orderBy('unit')
                ->get()
                ->map(fn($item) => (array) $item)
                ->toArray();

            if (!empty($options)) {
                return $options;
            }
        }

        return DB::table('cognos_recovery')
            ->select('unit_kerja as value', 'unit_kerja as label', 'cabang as kanca_value')
            ->distinct()
            ->whereIn('cabang', $area6Branches)
            ->orderBy('unit_kerja')
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }

    private function fetchPhUnitRows(array $area6Branches, ?string $period, array $selectedKanca, string $selectedUnit)
    {
        if (Schema::hasTable('lw325_ph')) {
            $query = DB::table('lw325_ph')
                ->select('kanca as cabang', 'unit')
                ->distinct()
                ->whereIn('kanca', $area6Branches)
                ->whereNotNull('unit')
                ->where('unit', '<>', '');

            if ($period) {
                $query->where('periode', $period);
            }
            if (!empty($selectedKanca)) {
                $query->whereIn('kanca', $selectedKanca);
            }
            if ($selectedUnit !== 'all') {
                $query->where(DB::raw('TRIM(UPPER(unit))'), $this->normalizeUnitName($selectedUnit));
            }

            $rows = $query->get();
            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        $query = DB::table('cognos_recovery')
            ->select('cabang', 'unit_kerja as unit')
            ->distinct()
            ->whereIn('cabang', $area6Branches);

        if (!empty($selectedKanca)) {
            $query->whereIn('cabang', $selectedKanca);
        }
        if ($selectedUnit !== 'all') {
            $query->where('unit_kerja', $selectedUnit);
        }

        return $query->get();
    }

    private function emptyDataPhMetrics(): array
    {
        return [
            'micro' => 0.0,
            'small' => 0.0,
            'consumer_briguna' => 0.0,
            'consumer_kpr' => 0.0,
            'total' => 0.0,
        ];
    }

    private function diffDataPhMetrics(array $current, array $comparison): array
    {
        $diff = $this->emptyDataPhMetrics();
        foreach (array_keys($diff) as $key) {
            $diff[$key] = (float) ($current[$key] ?? 0.0) - (float) ($comparison[$key] ?? 0.0);
        }

        return $diff;
    }

    private function buildGrandTotalMetrics(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $metricGroups = [
            'sisa_ph',
            'recovery_yoy',
            'recovery_ytd',
            'recovery_m1',
            'recovery_curr',
            'delta_yoy',
            'delta_ytd',
            'delta_m1',
        ];

        $totals = [];
        foreach ($metricGroups as $group) {
            $totals[$group] = $this->emptyDataPhMetrics();
        }

        foreach ($rows as $row) {
            foreach ($metricGroups as $group) {
                foreach (array_keys($totals[$group]) as $segmentKey) {
                    $totals[$group][$segmentKey] += (float) ($row[$group][$segmentKey] ?? 0.0);
                }
            }
        }

        return $totals;
    }

    private function formatDataPhPeriodLabel(?string $period): string
    {
        if (!$period) {
            return '-';
        }

        try {
            return Carbon::parse($period)->format('d M y');
        } catch (\Throwable) {
            return '-';
        }
    }

    private function getDataPhRecoveryMetrics(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $metrics = $this->getRecoveryMetricsFromPh($period, $kancas, $unit);

        if ($this->hasAnyDataPhMetric($metrics)) {
            return $metrics;
        }

        return $this->getRecoveryMetricsFromCognos($period, $kancas, $unit);
    }

    private function getBranchOfficeDataPhRecoveryMetrics(string $period, array $branchOffices = []): array
    {
        $metrics = $this->getBranchOfficeRecoveryMetricsFromPh($period, $branchOffices);

        if ($this->hasAnyDataPhMetric($metrics)) {
            return $metrics;
        }

        return $this->getBranchOfficeRecoveryMetricsFromCognos($period, $branchOffices);
    }

    private function hasAnyDataPhMetric(array $metrics): bool
    {
        foreach ($metrics as $group) {
            foreach ((array) $group as $value) {
                if (abs((float) $value) > 0.0001) {
                    return true;
                }
            }
        }

        return false;
    }

    private function addDataPhMetric(array &$metrics, string $key, ?string $segmentKey, float $value): void
    {
        if (!$segmentKey) {
            return;
        }

        $metrics[$key] ??= $this->emptyDataPhMetrics();
        $metrics[$key][$segmentKey] += $value;
        $metrics[$key]['total'] += $value;
    }

    private function normalizePhToken(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $value))) ?? '';
    }

    private function classifyPhSegment(?string $segment, ?string $product): ?string
    {
        $segmentToken = $this->normalizePhToken($segment);
        $productToken = $this->normalizePhToken($product);
        $isConsumer = str_contains($segmentToken, 'CONSUMER') || str_contains($segmentToken, 'KONSUMER');

        if (str_contains($segmentToken, 'MICRO') || str_contains($segmentToken, 'MIKRO')) {
            return 'micro';
        }

        if (str_contains($segmentToken, 'SMALL') || str_contains($segmentToken, 'KECIL')) {
            return 'small';
        }

        if (($isConsumer && str_contains($productToken, 'BRIGUNA'))
            || str_contains($productToken, 'BRIGUNAKONSUMER')
            || str_contains($productToken, 'BRIGUNACONSUMER')) {
            return 'consumer_briguna';
        }

        if ($isConsumer && str_contains($productToken, 'KPR')) {
            return 'consumer_kpr';
        }

        return null;
    }

    private function normalizeDataPhKancaRequest(mixed $value): array
    {
        $raw = is_array($value) ? $value : explode(',', (string) $value);

        return collect($raw)
            ->map(fn ($item): string => trim((string) $item))
            ->filter(fn (string $item): bool => $item !== '' && strtolower($item) !== 'all')
            ->values()
            ->all();
    }

    private function applyDataPhSegmentFilter($query, string $segment): void
    {
        $segmentExpr = "UPPER(COALESCE(segmen_dashboard, ''))";
        $productExpr = "UPPER(COALESCE(produk_dashboard, ''))";

        match ($segment) {
            'micro' => $query->where(function ($q) use ($segmentExpr) {
                $q->whereRaw("{$segmentExpr} LIKE ?", ['%MICRO%'])
                    ->orWhereRaw("{$segmentExpr} LIKE ?", ['%MIKRO%']);
            }),
            'small' => $query->where(function ($q) use ($segmentExpr) {
                $q->whereRaw("{$segmentExpr} LIKE ?", ['%SMALL%'])
                    ->orWhereRaw("{$segmentExpr} LIKE ?", ['%KECIL%']);
            }),
            'consumer_briguna' => $query->where(function ($q) use ($segmentExpr, $productExpr) {
                $q->where(function ($consumer) use ($segmentExpr, $productExpr) {
                    $consumer->where(function ($seg) use ($segmentExpr) {
                        $seg->whereRaw("{$segmentExpr} LIKE ?", ['%CONSUMER%'])
                            ->orWhereRaw("{$segmentExpr} LIKE ?", ['%KONSUMER%']);
                    })->whereRaw("{$productExpr} LIKE ?", ['%BRIGUNA%']);
                })
                    ->orWhereRaw("{$productExpr} LIKE ?", ['%BRIGUNAKONSUMER%'])
                    ->orWhereRaw("{$productExpr} LIKE ?", ['%BRIGUNACONSUMER%']);
            }),
            'consumer_kpr' => $query->where(function ($q) use ($segmentExpr, $productExpr) {
                $q->where(function ($seg) use ($segmentExpr) {
                    $seg->whereRaw("{$segmentExpr} LIKE ?", ['%CONSUMER%'])
                        ->orWhereRaw("{$segmentExpr} LIKE ?", ['%KONSUMER%']);
                })->whereRaw("{$productExpr} LIKE ?", ['%KPR%']);
            }),
            default => null,
        };
    }

    private function formatNominatifColumns(array $columns): array
    {
        $numericColumns = [
            'saldo_pertama_ph_pokok',
            'saldo_pertama_ph_bunga',
            'besar_realisasi',
            'plafon',
            'pokok',
            'bunga',
            'angpok',
            'angbung',
            'sisapok',
            'sisabun',
            'clmamt1',
            'clmapr1',
            'os_penuh_berjalan1',
            'saldo_pertama_kali_charge_off',
            'deffered_bunga',
            'sai_deffered',
            'sai_tunggakan',
            'deffered_bunga_ph',
            'sai_tunggakan_ph',
            'sai_deffered_ph',
            'wcbal',
            'waccint',
            'wadvpmt',
            'wpenint',
            'wmisc',
            'wothchg',
            'wpmtamt',
            'wamount',
            'clmamt',
            'clmapr',
            'jw',
            'at',
            'jumlah_pn',
            'jumlah_pn_all',
        ];
        $dateColumns = ['periode', 'tgl_ph', 'tgl_realisasi', 'wpstdt', 'wpstdt6', 'created_at', 'updated_at'];

        return array_map(fn (string $column): array => [
            'key' => $column,
            'label' => strtoupper(str_replace('_', ' ', $column)),
            'type' => in_array($column, $numericColumns, true)
                ? 'number'
                : (in_array($column, $dateColumns, true) ? 'date' : 'text'),
        ], $columns);
    }

    private function classifyCognosRecoverySegment(?string $segment, ?string $product): ?string
    {
        $segmentKey = $this->classifyPhSegment($segment, $product);
        if ($segmentKey) {
            return $segmentKey;
        }

        $segmentToken = $this->normalizePhToken($segment);
        if (str_contains($segmentToken, 'CONSUMER') || str_contains($segmentToken, 'KONSUMER')) {
            return 'consumer_briguna';
        }

        return null;
    }

    private function fetchBranchOfficeCodes(array $branchOffices): array
    {
        $codes = DB::table('cognos_recovery')
            ->whereIn('cabang', $branchOffices)
            ->select('cabang')
            ->selectRaw("MIN(CAST(NULLIF(TRIM(sub_bc), '') AS UNSIGNED)) as sub_bc_code")
            ->groupBy('cabang')
            ->get();

        $mapped = [];
        foreach ($codes as $row) {
            $mapped[(string) $row->cabang] = $row->sub_bc_code !== null ? (string) $row->sub_bc_code : '-';
        }

        return $mapped;
    }

    private function hasLw325PhRowsForPeriod(string $period, array $kancas = [], string $unit = 'all'): bool
    {
        if (!Schema::hasTable('lw325_ph')) {
            return false;
        }

        $cacheKey = implode('|', [
            $period,
            implode(',', $kancas),
            $unit,
        ]);
        if (array_key_exists($cacheKey, $this->phPeriodRowAvailability)) {
            return $this->phPeriodRowAvailability[$cacheKey];
        }

        $query = DB::table('lw325_ph')
            ->where('periode', $period)
            ->whereNotNull('acctno')
            ->where('acctno', '<>', '');

        if (!empty($kancas)) {
            $query->whereIn('kanca', $kancas);
        }

        if ($unit !== 'all') {
            $query->where(DB::raw('TRIM(UPPER(unit))'), $this->normalizeUnitName($unit));
        }

        return $this->phPeriodRowAvailability[$cacheKey] = $query->exists();
    }

    private function phAccountKeySql(string $alias): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "LTRIM(TRIM(COALESCE({$alias}.acctno, '')), '0')";
        }

        return "TRIM(LEADING '0' FROM TRIM(COALESCE({$alias}.acctno, '')))";
    }

    private function getBranchOfficeRecoveryMetricsFromCognos(string $period, array $branchOffices = []): array
    {
        $query = DB::table('cognos_recovery')
            ->where('periode', $period)
            ->whereIn('cabang', $branchOffices)
            ->select('cabang', 'segmen_2', 'produk')
            ->selectRaw('SUM(total_recovery) as total_recovery')
            ->groupBy('cabang', 'segmen_2', 'produk');

        $data = $query->get();

        $metrics = [];
        foreach ($branchOffices as $branch) {
            $metrics[$branch] = $this->emptyDataPhMetrics();
        }

        foreach ($data as $row) {
            $key = $row->cabang;
            if (!isset($metrics[$key])) {
                $metrics[$key] = $this->emptyDataPhMetrics();
            }

            $val = (float) $row->total_recovery;
            $segmentKey = $this->classifyCognosRecoverySegment($row->segmen_2, $row->produk);
            $this->addDataPhMetric($metrics, $key, $segmentKey, $val);
        }

        return $metrics;
    }


    private function getBranchOfficeRecoveryMetricsFromPh(string $period, array $branchOffices = []): array
    {
        $metrics = [];
        foreach ($branchOffices as $branch) {
            $metrics[$branch] = $this->emptyDataPhMetrics();
        }

        if (!Schema::hasTable('lw325_ph')) return $metrics;

        if (!$this->hasLw325PhRowsForPeriod($period)) {
            return $metrics;
        }

        $m1Period = $this->resolvePreviousMonthEndPhPeriod($period);

        if (!$m1Period) return $metrics;

        $currentAccountKey = $this->phAccountKeySql('n');
        $previousAccountKey = $this->phAccountKeySql('o');

        $currentRows = DB::table('lw325_ph as n')
            ->where('n.periode', $period)
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->selectRaw("{$currentAccountKey} as account_key")
            ->selectRaw('SUM(COALESCE(n.pokok, 0)) as current_pokok')
            ->groupByRaw($currentAccountKey);

        $previousRows = DB::table('lw325_ph as o')
            ->where('o.periode', $m1Period)
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("{$previousAccountKey} as account_key")
            ->selectRaw("o.kanca")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("o.produk_dashboard")
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as old_pokok')
            ->groupByRaw("{$previousAccountKey}, o.kanca, o.segmen_dashboard, o.produk_dashboard");

        if (!empty($branchOffices)) {
            $previousRows->whereIn('o.kanca', $branchOffices);
        }

        $amountExpression = "
            CASE
                WHEN n.account_key IS NULL THEN COALESCE(o.old_pokok, 0)
                WHEN COALESCE(o.old_pokok, 0) > COALESCE(n.current_pokok, 0)
                THEN COALESCE(o.old_pokok, 0) - COALESCE(n.current_pokok, 0)
                ELSE 0
            END
        ";

        $results = DB::query()
            ->fromSub($previousRows, 'o')
            ->leftJoinSub($currentRows, 'n', function ($join) {
                $join->on('o.account_key', '=', 'n.account_key');
            })
            ->select('o.kanca', 'o.segmen_dashboard', 'o.produk_dashboard')
            ->selectRaw("SUM({$amountExpression}) as total")
            ->whereRaw("({$amountExpression}) > 0")
            ->groupBy('o.kanca', 'o.segmen_dashboard', 'o.produk_dashboard')
            ->get();

        foreach ($results as $row) {
            $key = $row->kanca;
            if (!isset($metrics[$key])) continue;

            $val = (float)$row->total;
            $segmentKey = $this->classifyPhSegment($row->segmen_dashboard, $row->produk_dashboard);
            $this->addDataPhMetric($metrics, $key, $segmentKey, $val);
        }

        return $metrics;
    }

    private function getBranchOfficeSisaPhMetricsFromPh(string $period, array $branchOffices = []): array
    {
        $metrics = [];
        foreach ($branchOffices as $branch) {
            $metrics[$branch] = $this->emptyDataPhMetrics();
        }

        if (!Schema::hasTable('lw325_ph')) return $metrics;

        $results = DB::table('lw325_ph')
            ->where('periode', $period)
            ->when(!empty($branchOffices), fn($query) => $query->whereIn('kanca', $branchOffices))
            ->select('kanca', 'segmen_dashboard', 'produk_dashboard')
            ->selectRaw('SUM(COALESCE(pokok, 0)) as total')
            ->groupBy('kanca', 'segmen_dashboard', 'produk_dashboard')
            ->get();

        foreach ($results as $row) {
            $key = $row->kanca;
            if (!isset($metrics[$key])) continue;

            $segmentKey = $this->classifyPhSegment($row->segmen_dashboard, $row->produk_dashboard);
            $this->addDataPhMetric($metrics, $key, $segmentKey, (float) $row->total);
        }

        return $metrics;
    }

    private function getRecoveryMetricsFromPh(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $metrics = [];
        if (!Schema::hasTable('lw325_ph')) return $metrics;

        if (!$this->hasLw325PhRowsForPeriod($period)) {
            return $metrics;
        }

        $m1Period = $this->resolvePreviousMonthEndPhPeriod($period);

        if (!$m1Period) return $metrics;

        $currentAccountKey = $this->phAccountKeySql('n');
        $previousAccountKey = $this->phAccountKeySql('o');

        $currentRows = DB::table('lw325_ph as n')
            ->where('n.periode', $period)
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->selectRaw("{$currentAccountKey} as account_key")
            ->selectRaw('SUM(COALESCE(n.pokok, 0)) as current_pokok')
            ->groupByRaw($currentAccountKey);

        $previousRows = DB::table('lw325_ph as o')
            ->where('o.periode', $m1Period)
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '')
            ->selectRaw("{$previousAccountKey} as account_key")
            ->selectRaw("o.kanca")
            ->selectRaw("o.unit")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("o.produk_dashboard")
            ->selectRaw('SUM(COALESCE(o.pokok, 0)) as old_pokok')
            ->groupByRaw("{$previousAccountKey}, o.kanca, o.unit, o.segmen_dashboard, o.produk_dashboard");

        if (!empty($kancas)) {
            $previousRows->whereIn('o.kanca', $kancas);
        }
        
        if ($unit !== 'all') {
            $normalizedUnit = $this->normalizeUnitName($unit);
            $previousRows->where(DB::raw('TRIM(UPPER(o.unit))'), $normalizedUnit);
        }

        $amountExpression = "
            CASE
                WHEN n.account_key IS NULL THEN COALESCE(o.old_pokok, 0)
                WHEN COALESCE(o.old_pokok, 0) > COALESCE(n.current_pokok, 0)
                THEN COALESCE(o.old_pokok, 0) - COALESCE(n.current_pokok, 0)
                ELSE 0
            END
        ";

        $results = DB::query()
            ->fromSub($previousRows, 'o')
            ->leftJoinSub($currentRows, 'n', function ($join) {
                $join->on('o.account_key', '=', 'n.account_key');
            })
            ->select('o.kanca', 'o.unit', 'o.segmen_dashboard', 'o.produk_dashboard')
            ->selectRaw("SUM({$amountExpression}) as total")
            ->whereRaw("({$amountExpression}) > 0")
            ->groupBy('o.kanca', 'o.unit', 'o.segmen_dashboard', 'o.produk_dashboard')
            ->get();

        foreach ($results as $row) {
            $key = trim(strtoupper($row->kanca)) . '|' . $this->normalizeUnitName($row->unit);
            if (!isset($metrics[$key])) {
                $metrics[$key] = $this->emptyDataPhMetrics();
            }

            $val = (float)$row->total;
            $segmentKey = $this->classifyPhSegment($row->segmen_dashboard, $row->produk_dashboard);
            $this->addDataPhMetric($metrics, $key, $segmentKey, $val);
        }

        return $metrics;
    }

    private function getSisaPhMetricsFromPh(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $metrics = [];
        if (!Schema::hasTable('lw325_ph')) return $metrics;

        $query = DB::table('lw325_ph')
            ->where('periode', $period)
            ->select('kanca', 'unit', 'segmen_dashboard', 'produk_dashboard')
            ->selectRaw('SUM(COALESCE(pokok, 0)) as total')
            ->groupBy('kanca', 'unit', 'segmen_dashboard', 'produk_dashboard');

        if (!empty($kancas)) {
            $query->whereIn('kanca', $kancas);
        }

        if ($unit !== 'all') {
            $query->where(DB::raw('TRIM(UPPER(unit))'), $this->normalizeUnitName($unit));
        }

        foreach ($query->get() as $row) {
            $key = trim(strtoupper($row->kanca)) . '|' . $this->normalizeUnitName($row->unit);
            $metrics[$key] ??= $this->emptyDataPhMetrics();

            $segmentKey = $this->classifyPhSegment($row->segmen_dashboard, $row->produk_dashboard);
            $this->addDataPhMetric($metrics, $key, $segmentKey, (float) $row->total);
        }

        return $metrics;
    }

    private function getRecoveryMetricsFromCognos(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $area6 = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        $query = DB::table('cognos_recovery')
            ->where('periode', $period)
            ->whereIn('cabang', $area6)
            ->select('cabang', 'unit_kerja', 'segmen_2', 'produk')
            ->selectRaw('SUM(total_recovery) as total_recovery')
            ->groupBy('cabang', 'unit_kerja', 'segmen_2', 'produk');

        if (!empty($kancas)) {
            $query->whereIn('cabang', $kancas);
        }
        if ($unit !== 'all') {
            $query->where('unit_kerja', $unit);
        }

        $data = $query->get();

        $metrics = [];
        foreach ($data as $row) {
            $key = trim(strtoupper($row->cabang)) . '|' . $this->normalizeUnitName($row->unit_kerja);
            if (!isset($metrics[$key])) {
                $metrics[$key] = $this->emptyDataPhMetrics();
            }

            $val = (float)$row->total_recovery;
            $segmentKey = $this->classifyCognosRecoverySegment($row->segmen_2, $row->produk);
            $this->addDataPhMetric($metrics, $key, $segmentKey, $val);
        }

        return $metrics;
    }

    private function normalizeUnitName(string $name): string
    {
        $normalized = preg_replace('/^\d+\s*--\s*/', '', $name);
        return trim(strtoupper($normalized));
    }

    private function resolvePreviousMonthEndPhPeriod(string $period): ?string
    {
        if (!Schema::hasTable('lw325_ph')) {
            return null;
        }

        if (array_key_exists($period, $this->previousPhPeriodByPeriod)) {
            return $this->previousPhPeriodByPeriod[$period];
        }

        try {
            $previousMonthEnd = Carbon::parse($period)
                ->startOfMonth()
                ->subDay()
                ->toDateString();
            $previousMonthStart = Carbon::parse($previousMonthEnd)
                ->startOfMonth()
                ->toDateString();

            return $this->previousPhPeriodByPeriod[$period] = DB::table('lw325_ph')
                ->whereBetween('periode', [$previousMonthStart, $previousMonthEnd])
                ->orderByDesc('periode')
                ->value('periode');
        } catch (\Throwable) {
            return $this->previousPhPeriodByPeriod[$period] = null;
        }
    }
}
