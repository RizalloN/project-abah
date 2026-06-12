<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\DashboardHarianSnapshotService;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class KejarLabaReportController extends Controller
{
    public function index(Request $request): View
    {
        $snapshotService = app(DashboardHarianSnapshotService::class);
        $rkaService = app(RkaLookupService::class);
        $area6Branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

        // 1. Resolve Available Periods from cognos_recovery and lw325_ph
        $cognosPeriods = DB::table('cognos_recovery')
            ->select('periode')
            ->distinct()
            ->pluck('periode');

        $phPeriods = Schema::hasTable('lw325_ph') 
            ? DB::table('lw325_ph')->select('periode')->distinct()->pluck('periode') 
            : collect();

        $availablePeriods = $cognosPeriods->concat($phPeriods)
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

        // 2. Fetch Filter Options (Branches and Units) from cognos_recovery
        $availableKanca = DB::table('cognos_recovery')
            ->select('cabang as value', 'cabang as label')
            ->distinct()
            ->whereIn('cabang', $area6Branches)
            ->orderBy('cabang')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

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

        // Fetch ALL Units for Area 6 branches so the UI can dynamically filter them on the client-side
        $availableUnits = DB::table('cognos_recovery')
            ->select('unit_kerja as value', 'unit_kerja as label', 'cabang as kanca_value')
            ->distinct()
            ->whereIn('cabang', $area6Branches)
            ->orderBy('unit_kerja')
            ->get()
            ->map(fn($item) => (array)$item)
            ->toArray();

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
            $m1EffectivePeriod = $availablePeriods->contains($m1Period) ? $m1Period : null;

            // YoY is 1 year before the selected period
            $yoyPeriod = $selectedCarbon->copy()->subYearNoOverflow()->toDateString();
            $yoyEffectivePeriod = $availablePeriods->first(fn($p) => $p <= $yoyPeriod);

            // YTD is December end of the previous year
            $ytdPeriod = $selectedCarbon->copy()->subYearNoOverflow()->endOfYear()->toDateString();
            $ytdEffectivePeriod = $availablePeriods->first(fn($p) => $p <= $ytdPeriod);

            // 3. Fetch Metrics from cognos_recovery or lw325_ph
            $hasCognosCurrent = DB::table('cognos_recovery')->where('periode', $selectedPeriod)->exists();
            
            $currentMetrics = !$hasCognosCurrent && Schema::hasTable('lw325_ph')
                ? $this->getRecoveryMetricsFromPh($selectedPeriod, $selectedKanca, $selectedUnit)
                : $this->getRecoveryMetricsFromCognos($selectedPeriod, $selectedKanca, $selectedUnit);

            $m1Metrics = [];
            $hasCognosM1 = false;
            if ($m1EffectivePeriod) {
                $hasCognosM1 = DB::table('cognos_recovery')->where('periode', $m1EffectivePeriod)->exists();
                $m1Metrics = !$hasCognosM1 && Schema::hasTable('lw325_ph')
                    ? $this->getRecoveryMetricsFromPh($m1EffectivePeriod, $selectedKanca, $selectedUnit)
                    : $this->getRecoveryMetricsFromCognos($m1EffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $yoyMetrics = [];
            $hasCognosYoY = false;
            if ($yoyEffectivePeriod) {
                $hasCognosYoY = DB::table('cognos_recovery')->where('periode', $yoyEffectivePeriod)->exists();
                $yoyMetrics = !$hasCognosYoY && Schema::hasTable('lw325_ph')
                    ? $this->getRecoveryMetricsFromPh($yoyEffectivePeriod, $selectedKanca, $selectedUnit)
                    : $this->getRecoveryMetricsFromCognos($yoyEffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $ytdMetrics = [];
            $hasCognosYtd = false;
            if ($ytdEffectivePeriod) {
                $hasCognosYtd = DB::table('cognos_recovery')->where('periode', $ytdEffectivePeriod)->exists();
                $ytdMetrics = !$hasCognosYtd && Schema::hasTable('lw325_ph')
                    ? $this->getRecoveryMetricsFromPh($ytdEffectivePeriod, $selectedKanca, $selectedUnit)
                    : $this->getRecoveryMetricsFromCognos($ytdEffectivePeriod, $selectedKanca, $selectedUnit);
            }

            $yoyPeriodLabel = $yoyEffectivePeriod 
                ? Carbon::parse($yoyEffectivePeriod)->translatedFormat('d M y') 
                : '-';
            $ytdPeriodLabel = $ytdEffectivePeriod 
                ? Carbon::parse($ytdEffectivePeriod)->translatedFormat('d M y') 
                : '-';
            $m1PeriodLabel = $m1EffectivePeriod 
                ? Carbon::parse($m1EffectivePeriod)->translatedFormat('d M y') 
                : '-';
            $selectedPeriodLabel = Carbon::parse($selectedPeriod)->translatedFormat('d M y');

            // 5. Build Final Rows
            if ($isArea6All) {
                $branchCodes = $this->fetchBranchOfficeCodes($area6Branches);
                
                $branchCurrentMetrics = !$hasCognosCurrent && Schema::hasTable('lw325_ph')
                    ? $this->getBranchOfficeRecoveryMetricsFromPh($selectedPeriod, $area6Branches)
                    : $this->getBranchOfficeRecoveryMetricsFromCognos($selectedPeriod, $area6Branches);
                
                $branchM1Metrics = [];
                if ($m1EffectivePeriod) {
                    $branchM1Metrics = !$hasCognosM1 && Schema::hasTable('lw325_ph')
                        ? $this->getBranchOfficeRecoveryMetricsFromPh($m1EffectivePeriod, $area6Branches)
                        : $this->getBranchOfficeRecoveryMetricsFromCognos($m1EffectivePeriod, $area6Branches);
                }

                $branchYoyMetrics = [];
                if ($yoyEffectivePeriod) {
                    $branchYoyMetrics = !$hasCognosYoY && Schema::hasTable('lw325_ph')
                        ? $this->getBranchOfficeRecoveryMetricsFromPh($yoyEffectivePeriod, $area6Branches)
                        : $this->getBranchOfficeRecoveryMetricsFromCognos($yoyEffectivePeriod, $area6Branches);
                }

                $branchYtdMetrics = [];
                if ($ytdEffectivePeriod) {
                    $branchYtdMetrics = !$hasCognosYtd && Schema::hasTable('lw325_ph')
                        ? $this->getBranchOfficeRecoveryMetricsFromPh($ytdEffectivePeriod, $area6Branches)
                        : $this->getBranchOfficeRecoveryMetricsFromCognos($ytdEffectivePeriod, $area6Branches);
                }

                foreach ($area6Branches as $index => $branchOffice) {
                    $curr = $branchCurrentMetrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $prev = $branchM1Metrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $yoy = $branchYoyMetrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $ytd = $branchYtdMetrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $branchOffice,
                        'buc' => $branchCodes[$branchOffice] ?? '-',
                        'branch_office' => $branchOffice,
                        'recovery_yoy' => $yoy,
                        'recovery_ytd' => $ytd,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'delta_yoy' => [
                            'micro' => $curr['micro'] - $yoy['micro'],
                            'small' => $curr['small'] - $yoy['small'],
                            'consumer' => $curr['consumer'] - $yoy['consumer'],
                            'total' => $curr['total'] - $yoy['total'],
                        ],
                        'delta_ytd' => [
                            'micro' => $curr['micro'] - $ytd['micro'],
                            'small' => $curr['small'] - $ytd['small'],
                            'consumer' => $curr['consumer'] - $ytd['consumer'],
                            'total' => $curr['total'] - $ytd['total'],
                        ],
                        'delta_m1' => [
                            'micro' => $curr['micro'] - $prev['micro'],
                            'small' => $curr['small'] - $prev['small'],
                            'consumer' => $curr['consumer'] - $prev['consumer'],
                            'total' => $curr['total'] - $prev['total'],
                        ],
                    ];
                }
            } else {
                // Detail mode: show recovery per unit kerja under the selected cabang
                $unitList = DB::table('cognos_recovery')
                    ->select('cabang', 'sub_bc', 'unit_kerja')
                    ->distinct()
                    ->whereIn('cabang', $area6Branches);

                if (!empty($selectedKanca)) {
                    $unitList->whereIn('cabang', $selectedKanca);
                }
                if ($selectedUnit !== 'all') {
                    $unitList->where('unit_kerja', $selectedUnit);
                }

                $units = $unitList->get()
                    ->sortBy(function($u) {
                        preg_match('/\d+/', $u->unit_kerja, $matches);
                        $code = $matches[0] ?? '99999';
                        return $u->cabang . '|' . str_pad($code, 5, '0', STR_PAD_LEFT);
                    })
                    ->values();

                foreach ($units as $index => $u) {
                    $lookupKey = trim(strtoupper($u->cabang)) . '|' . $this->normalizeUnitName($u->unit_kerja);

                    $curr = $currentMetrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $prev = $m1Metrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $yoy = $yoyMetrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $ytd = $ytdMetrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $u->cabang,
                        'buc' => $u->sub_bc,
                        'unit' => $u->unit_kerja,
                        'recovery_yoy' => $yoy,
                        'recovery_ytd' => $ytd,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'delta_yoy' => [
                            'micro' => $curr['micro'] - $yoy['micro'],
                            'small' => $curr['small'] - $yoy['small'],
                            'consumer' => $curr['consumer'] - $yoy['consumer'],
                            'total' => $curr['total'] - $yoy['total'],
                        ],
                        'delta_ytd' => [
                            'micro' => $curr['micro'] - $ytd['micro'],
                            'small' => $curr['small'] - $ytd['small'],
                            'consumer' => $curr['consumer'] - $ytd['consumer'],
                            'total' => $curr['total'] - $ytd['total'],
                        ],
                        'delta_m1' => [
                            'micro' => $curr['micro'] - $prev['micro'],
                            'small' => $curr['small'] - $prev['small'],
                            'consumer' => $curr['consumer'] - $prev['consumer'],
                            'total' => $curr['total'] - $prev['total'],
                        ],
                    ];
                }
            }
        }

        $summary = [
            'total_recovery' => collect($rows)->sum(fn($r) => $r['recovery_curr']['total'] ?? 0),
        ];

        // Fetch position options for secondary filters
        $filters = $snapshotService->fetchFilterOptions($selectedPeriod);
        $posisiRkaOptions = $filters['posisi_rka'] ?? [];

        return view('report.kejar-laba', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $selectedPeriodLabel,
            'yoyPeriodLabel' => $yoyPeriodLabel ?? '-',
            'ytdPeriodLabel' => $ytdPeriodLabel ?? '-',
            'm1PeriodLabel' => $m1PeriodLabel ?? '-',
            'rows' => $rows,
            'summary' => $summary,
            'posisi_rka_options' => $posisiRkaOptions,
            'selectedRka' => $request->input('rka_period'),
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

    private function getBranchOfficeRecoveryMetricsFromCognos(string $period, array $branchOffices = []): array
    {
        $query = DB::table('cognos_recovery')
            ->whereDate('periode', $period)
            ->whereIn('cabang', $branchOffices)
            ->select('cabang', 'segmen_2')
            ->selectRaw('SUM(total_recovery) as total_recovery')
            ->groupBy('cabang', 'segmen_2');

        $data = $query->get();

        $metrics = [];
        foreach ($data as $row) {
            $key = $row->cabang;
            if (!isset($metrics[$key])) {
                $metrics[$key] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
            }

            $seg = strtoupper((string) $row->segmen_2);
            $val = (float) $row->total_recovery;

            if (in_array($seg, ['MICRO', 'MIKRO'])) {
                $metrics[$key]['micro'] += $val;
            } elseif (in_array($seg, ['SMALL', 'KECIL'])) {
                $metrics[$key]['small'] += $val;
            } elseif (in_array($seg, ['CONSUMER', 'KONSUMER'])) {
                $metrics[$key]['consumer'] += $val;
            }

            $metrics[$key]['total'] += $val;
        }

        return $metrics;
    }


    private function getBranchOfficeRecoveryMetricsFromPh(string $period, array $branchOffices = []): array
    {
        $metrics = [];
        foreach ($branchOffices as $branch) {
            $metrics[$branch] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
        }

        if (!Schema::hasTable('lw325_ph')) return $metrics;

        $m1Period = $this->resolvePreviousMonthEndPhPeriod($period);

        if (!$m1Period) return $metrics;

        $tupokQuery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($m1Period, $period) {
                $join->on('n.kanwil', '=', 'o.kanwil')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->on('n.acctno', '=', 'o.acctno')
                    ->whereRaw('n.periode = ?', [$period])
                    ->whereRaw('o.periode = ?', [$m1Period]);
            })
            ->selectRaw("o.kanca")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) as amount")
            ->selectRaw("'tupok' as type")
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');

        if (!empty($branchOffices)) {
            $tupokQuery->whereIn('n.kanca', $branchOffices);
        }

        $lunasQuery = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($m1Period, $period) {
                $join->on('o.kanwil', '=', 'n.kanwil')
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->on('o.acctno', '=', 'n.acctno')
                    ->whereRaw('n.periode = ?', [$period]);
            })
            ->selectRaw("o.kanca")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("COALESCE(o.pokok, 0) as amount")
            ->selectRaw("'lunas' as type")
            ->where('o.periode', $m1Period)
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');

        if (!empty($branchOffices)) {
            $lunasQuery->whereIn('o.kanca', $branchOffices);
        }

        $combined = $tupokQuery->unionAll($lunasQuery);

        $results = DB::query()
            ->fromSub($combined, 'ph')
            ->select('kanca', 'segmen_dashboard')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('kanca', 'segmen_dashboard')
            ->get();

        foreach ($results as $row) {
            $key = $row->kanca;
            if (!isset($metrics[$key])) continue;

            $seg = strtoupper(trim((string)$row->segmen_dashboard));
            $val = (float)$row->total;

            if (in_array($seg, ['MICRO', 'MIKRO'])) {
                $metrics[$key]['micro'] += $val;
            } elseif (in_array($seg, ['SMALL', 'KECIL'])) {
                $metrics[$key]['small'] += $val;
            } elseif (in_array($seg, ['CONSUMER', 'KONSUMER'])) {
                $metrics[$key]['consumer'] += $val;
            }

            $metrics[$key]['total'] += $val;
        }

        return $metrics;
    }

    private function getRecoveryMetricsFromPh(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $metrics = [];
        if (!Schema::hasTable('lw325_ph')) return $metrics;

        $m1Period = $this->resolvePreviousMonthEndPhPeriod($period);

        if (!$m1Period) return $metrics;

        $tupokQuery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($m1Period, $period) {
                $join->on('n.kanwil', '=', 'o.kanwil')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->on('n.acctno', '=', 'o.acctno')
                    ->whereRaw('n.periode = ?', [$period])
                    ->whereRaw('o.periode = ?', [$m1Period]);
            })
            ->selectRaw("o.kanca")
            ->selectRaw("o.unit")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) as amount")
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->whereNotNull('n.acctno')
            ->where('n.acctno', '<>', '')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');

        $lunasQuery = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($m1Period, $period) {
                $join->on('o.kanwil', '=', 'n.kanwil')
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->on('o.acctno', '=', 'n.acctno')
                    ->whereRaw('n.periode = ?', [$period]);
            })
            ->selectRaw("o.kanca")
            ->selectRaw("o.unit")
            ->selectRaw("o.segmen_dashboard")
            ->selectRaw("COALESCE(o.pokok, 0) as amount")
            ->where('o.periode', $m1Period)
            ->whereNull('n.acctno')
            ->whereNotNull('o.acctno')
            ->where('o.acctno', '<>', '');

        if (!empty($kancas)) {
            $tupokQuery->whereIn('n.kanca', $kancas);
            $lunasQuery->whereIn('o.kanca', $kancas);
        }
        
        if ($unit !== 'all') {
            $normalizedUnit = $this->normalizeUnitName($unit);
            $tupokQuery->where(DB::raw('TRIM(UPPER(n.unit))'), $normalizedUnit);
            $lunasQuery->where(DB::raw('TRIM(UPPER(o.unit))'), $normalizedUnit);
        }

        $combined = $tupokQuery->unionAll($lunasQuery);

        $results = DB::query()
            ->fromSub($combined, 'ph')
            ->select('kanca', 'unit', 'segmen_dashboard')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('kanca', 'unit', 'segmen_dashboard')
            ->get();

        foreach ($results as $row) {
            $key = trim(strtoupper($row->kanca)) . '|' . $this->normalizeUnitName($row->unit);
            if (!isset($metrics[$key])) {
                $metrics[$key] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
            }

            $seg = strtoupper(trim((string)$row->segmen_dashboard));
            $val = (float)$row->total;

            if (in_array($seg, ['MICRO', 'MIKRO'])) {
                $metrics[$key]['micro'] += $val;
            } elseif (in_array($seg, ['SMALL', 'KECIL'])) {
                $metrics[$key]['small'] += $val;
            } elseif (in_array($seg, ['CONSUMER', 'KONSUMER'])) {
                $metrics[$key]['consumer'] += $val;
            }

            $metrics[$key]['total'] += $val;
        }

        return $metrics;
    }

    private function getRecoveryMetricsFromCognos(string $period, array $kancas = [], string $unit = 'all'): array
    {
        $area6 = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];
        $query = DB::table('cognos_recovery')
            ->whereDate('periode', $period)
            ->whereIn('cabang', $area6)
            ->select('cabang', 'unit_kerja', 'segmen_2')
            ->selectRaw('SUM(total_recovery) as total_recovery')
            ->groupBy('cabang', 'unit_kerja', 'segmen_2');

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
                $metrics[$key] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
            }

            $seg = strtoupper($row->segmen_2);
            $val = (float)$row->total_recovery;

            if (in_array($seg, ['MICRO', 'MIKRO'])) {
                $metrics[$key]['micro'] += $val;
            } elseif (in_array($seg, ['SMALL', 'KECIL'])) {
                $metrics[$key]['small'] += $val;
            } elseif (in_array($seg, ['CONSUMER', 'KONSUMER'])) {
                $metrics[$key]['consumer'] += $val;
            }
            
            $metrics[$key]['total'] += $val;
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

        try {
            $previousMonthEnd = Carbon::parse($period)
                ->startOfMonth()
                ->subDay()
                ->toDateString();

            return DB::table('lw325_ph')
                ->whereDate('periode', $previousMonthEnd)
                ->value('periode');
        } catch (\Throwable) {
            return null;
        }
    }
}
