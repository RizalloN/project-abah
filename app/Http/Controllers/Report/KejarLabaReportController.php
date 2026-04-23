<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Support\DashboardHarianSnapshotService;
use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KejarLabaReportController extends Controller
{
    public function index(Request $request): View
    {
        $snapshotService = app(DashboardHarianSnapshotService::class);
        $rkaService = app(RkaLookupService::class);
        $area6Branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

        // 1. Resolve Available Periods from cognos_recovery
        $availablePeriods = DB::table('cognos_recovery')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn($p) => Carbon::parse($p)->toDateString());

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

        // Fetch Units based on selected Kancas
        $unitQuery = DB::table('cognos_recovery')
            ->select('unit_kerja as value', 'unit_kerja as label', 'cabang as kanca_value')
            ->distinct()
            ->whereIn('cabang', $area6Branches);
        
        if (!empty($selectedKanca)) {
            $unitQuery->whereIn('cabang', $selectedKanca);
        }
        
        $availableUnits = $unitQuery->orderBy('unit_kerja')
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
            $selectedPeriodLabel = Carbon::parse($selectedPeriod)->translatedFormat('d M Y');
            $selectedCarbon = Carbon::parse($selectedPeriod);
            
            // M-1 is the end of the previous month
            $m1Period = $selectedCarbon->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            // Try to find the closest available snapshot for M-1
            $m1EffectivePeriod = DB::table('cognos_recovery')
                ->where('periode', '<=', $m1Period)
                ->orderByDesc('periode')
                ->value('periode');

            // 3. Fetch Metrics from cognos_recovery
            $currentMetrics = $this->getRecoveryMetricsFromCognos($selectedPeriod, $selectedKanca, $selectedUnit);
            $m1Metrics = $m1EffectivePeriod ? $this->getRecoveryMetricsFromCognos($m1EffectivePeriod, $selectedKanca, $selectedUnit) : [];
            
            // 4. Handle RKA Targets
            $rkaRequested = $request->input('rka_period');
            $rkaEffective = $snapshotService->resolveEffectiveRkaPeriod($rkaRequested, $selectedPeriod);
            $rkaForMonth = $rkaEffective ? Carbon::parse($rkaEffective) : $selectedCarbon;
            $rkaYear = $rkaForMonth->year;
            $rkaMonthColumn = $rkaService->resolveMonthColumn($rkaForMonth);
            
            $rkaByCode = $this->fetchRkaTargetsByCode($rkaMonthColumn, $rkaYear);

            // 5. Build Final Rows
            if ($isArea6All) {
                $branchCodes = $this->fetchBranchOfficeCodes($area6Branches);
                $branchCurrentMetrics = $this->getBranchOfficeRecoveryMetricsFromCognos($selectedPeriod, $area6Branches);
                $branchM1Metrics = $m1EffectivePeriod
                    ? $this->getBranchOfficeRecoveryMetricsFromCognos($m1EffectivePeriod, $area6Branches)
                    : [];
                $branchRkaByOffice = $this->fetchBranchOfficeRkaTargets($rkaMonthColumn, $rkaYear, $area6Branches);

                foreach ($area6Branches as $index => $branchOffice) {
                    $curr = $branchCurrentMetrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $prev = $branchM1Metrics[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $rka = $branchRkaByOffice[$branchOffice] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];

                    $rkaMicro = (float) ($rka['micro'] ?? 0);
                    $rkaSmall = (float) ($rka['small'] ?? 0);
                    $rkaConsumer = (float) ($rka['consumer'] ?? 0);
                    $rkaTotal = (float) ($rka['total'] ?? 0);

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $branchOffice,
                        'buc' => $branchCodes[$branchOffice] ?? '-',
                        'branch_office' => $branchOffice,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'rka' => [
                            'micro' => $rkaMicro,
                            'small' => $rkaSmall,
                            'consumer' => $rkaConsumer,
                            'total' => $rkaTotal,
                        ],
                        'delta' => [
                            'micro' => $curr['micro'] - $rkaMicro,
                            'small' => $curr['small'] - $rkaSmall,
                            'consumer' => $curr['consumer'] - $rkaConsumer,
                            'total' => $curr['total'] - $rkaTotal,
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
                    $lookupKey = $u->cabang . '|' . $u->unit_kerja;

                    $curr = $currentMetrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                    $prev = $m1Metrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];

                    // Extraction of numeric code (03885 -> 3885)
                    preg_match('/\d+/', $u->unit_kerja, $matches);
                    $uCode = isset($matches[0]) ? (int)$matches[0] : null;

                    $rka = $uCode !== null ? ($rkaByCode[$uCode] ?? null) : null;
                    $rkaMicro = (float)($rka['micro'] ?? 0);
                    $rkaSmall = (float)($rka['small'] ?? 0);
                    $rkaConsumer = (float)($rka['consumer'] ?? 0);
                    $rkaTotal = (float)($rka['total'] ?? 0);

                    $rows[] = [
                        'no' => $index + 1,
                        'kanca' => $u->cabang,
                        'buc' => $u->sub_bc,
                        'unit' => $u->unit_kerja,
                        'recovery_m1' => $prev,
                        'recovery_curr' => $curr,
                        'rka' => [
                            'micro' => $rkaMicro,
                            'small' => $rkaSmall,
                            'consumer' => $rkaConsumer,
                            'total' => $rkaTotal,
                        ],
                        'delta' => [
                            'micro' => $curr['micro'] - $rkaMicro,
                            'small' => $curr['small'] - $rkaSmall,
                            'consumer' => $curr['consumer'] - $rkaConsumer,
                            'total' => $curr['total'] - $rkaTotal,
                        ]
                    ];
                }
            }
        }

        $summary = [
            'total_rka' => collect($rows)->sum(fn($r) => $r['rka']['total']),
            'total_recovery' => collect($rows)->sum(fn($r) => $r['recovery_curr']['total']),
        ];

        // Fetch position options for secondary filters
        $filters = $snapshotService->fetchFilterOptions($selectedPeriod);
        $posisiRkaOptions = $filters['posisi_rka'] ?? [];

        return view('report.kejar-laba', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $selectedPeriodLabel,
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
            'micro' => 'C. 1. a. Recovery Ekstrakomtabel Mikro',
            'small' => 'C. 2. Recovery Ekstrakomtabel Small',
            'consumer' => 'C. 4. Recovery Ekstrakomtabel Konsumer',
            'total' => 'C. RECOVERY EKSTRAKOMTABEL',
        ];

        $results = DB::table('rka')
            ->whereIn('mata_anggaran', array_values($rkaDefinitions))
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

            foreach ($rkaDefinitions as $key => $mataAnggaran) {
                if ($row->mata_anggaran === $mataAnggaran) {
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
                'mata_anggaran' => ['C. 1. a. Recovery Ekstrakomtabel Mikro'],
                'uker_contains_any' => ['KC', 'KCP'],
            ],
            'small' => [
                'mata_anggaran' => ['C. 2. Recovery Ekstrakomtabel Small'],
                'uker_contains_any' => ['KC', 'KCP'],
            ],
            'consumer' => [
                'mata_anggaran' => ['C. 4. Recovery Ekstrakomtabel Konsumer'],
                'uker_contains_any' => ['KC', 'KCP'],
            ],
            'total' => [
                'mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL'],
                'uker_contains_any' => ['KC', 'KCP'],
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

            if ($seg === 'MICRO') {
                $metrics[$key]['micro'] += $val;
            } elseif ($seg === 'SMALL') {
                $metrics[$key]['small'] += $val;
            } elseif ($seg === 'CONSUMER') {
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
            $key = $row->cabang . '|' . $row->unit_kerja;
            if (!isset($metrics[$key])) {
                $metrics[$key] = ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
            }

            $seg = strtoupper($row->segmen_2);
            $val = (float)$row->total_recovery;

            if ($seg === 'MICRO') {
                $metrics[$key]['micro'] += $val;
            } elseif ($seg === 'SMALL') {
                $metrics[$key]['small'] += $val;
            } elseif ($seg === 'CONSUMER') {
                $metrics[$key]['consumer'] += $val;
            }
            
            $metrics[$key]['total'] += $val;
        }

        return $metrics;
    }
}
