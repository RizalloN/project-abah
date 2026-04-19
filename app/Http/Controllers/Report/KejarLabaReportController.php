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

        // 1. Resolve Periods from cognos_recovery (real data in database)
        $availablePeriods = DB::table('cognos_recovery')
            ->select('periode')
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->map(fn($p) => \Carbon\Carbon::parse($p)->toDateString());

        $requestedPeriod = $request->input('periode');
        $selectedPeriod = null;

        if ($requestedPeriod && $availablePeriods->contains($requestedPeriod)) {
            $selectedPeriod = $requestedPeriod;
        } elseif ($availablePeriods->isNotEmpty()) {
            $selectedPeriod = $availablePeriods->first();
        }

        $rows = [];
        $selectedPeriodLabel = 'No Data';

        if ($selectedPeriod) {
            $selectedPeriodLabel = Carbon::parse($selectedPeriod)->translatedFormat('d M Y');
            $selectedCarbon = Carbon::parse($selectedPeriod);
            
            // M-1 is the end of the previous month
            $m1Period = $selectedCarbon->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            // Actually, we want THE PREVIOUS available snapshot period for M-1 from the service
            $m1EffectivePeriod = $snapshotService->resolveEffectivePeriod($m1Period);

            // 2. Fetch Unit List (from RKA to ensure we have targets)
            $rkaYear = (int)$selectedCarbon->format('Y');
            $units = DB::table('rka')
                ->whereYear('created_at', $rkaYear)
                ->select('kanca', 'desc_uker')
                ->distinct()
                ->get()
                ->map(function ($item) {
                    $desc = (string)$item->desc_uker;
                    $parts = explode('-', $desc, 2);
                    $buc = trim($parts[0] ?? '');
                    $unitName = trim($parts[1] ?? $desc);
                    
                    return [
                        'kanca' => $item->kanca,
                        'buc' => $buc,
                        'unit' => $unitName,
                        'desc_uker' => $desc,
                    ];
                })
                ->sortBy([['kanca', 'asc'], ['unit', 'asc']])
                ->values();

            // 3. Fetch Metrics
            $currentMetrics = $this->getRecoveryMetrics($selectedPeriod);
            $m1Metrics = $m1EffectivePeriod ? $this->getRecoveryMetrics($m1EffectivePeriod) : [];
            
            // allow overriding the RKA month via `rka_period` GET parameter; fall back to selectedPeriod
            $rkaRequested = $request->input('rka_period');
            $rkaEffective = $snapshotService->resolveEffectiveRkaPeriod($rkaRequested, $selectedPeriod);
            $rkaForMonth = $rkaEffective ? Carbon::parse($rkaEffective) : $selectedCarbon;
            $rkaMonthColumn = $rkaService->resolveMonthColumn($rkaForMonth);
            $rkaDefinitions = [
                'micro' => ['mata_anggaran' => ['C. 1. Recovery Ekstrakomtabel Total Mikro']],
                'small' => ['mata_anggaran' => ['C. 2. Recovery Ekstrakomtabel Small']],
                'consumer' => ['mata_anggaran' => ['C. 4. Recovery Ekstrakomtabel Konsumer']],
                'total' => ['mata_anggaran' => ['C. RECOVERY EKSTRAKOMTABEL']],
            ];
            
            $rkaData = $rkaService->aggregateByGroup($rkaDefinitions, $rkaMonthColumn, [], [], 'uker', $rkaYear);

            // 4. Build Rows
            foreach ($units as $index => $u) {
                $ukerKey = strtoupper(trim($u['desc_uker']));
                
                // DashboardHarianSnapshotService uses Str::slug(trim($value), '-') for unit_key
                $unitKey = \Illuminate\Support\Str::slug(trim($u['unit']), '-');
                $kancaKey = \Illuminate\Support\Str::slug(trim($u['kanca']), '-');
                $lookupKey = $kancaKey . '|' . $unitKey;

                $curr = $currentMetrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                $prev = $m1Metrics[$lookupKey] ?? ['micro' => 0, 'small' => 0, 'consumer' => 0, 'total' => 0];
                
                $rkaMicro = (float)($rkaData['micro'][$ukerKey] ?? 0);
                $rkaSmall = (float)($rkaData['small'][$ukerKey] ?? 0);
                $rkaConsumer = (float)($rkaData['consumer'][$ukerKey] ?? 0);
                $rkaTotal = (float)($rkaData['total'][$ukerKey] ?? 0);

                $rows[] = [
                    'no' => $index + 1,
                    'kanca' => $u['kanca'],
                    'buc' => $u['buc'],
                    'unit' => $u['unit'],
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

        $summary = [
            'total_rka' => collect($rows)->sum(fn($r) => $r['rka']['total']),
            'total_recovery' => collect($rows)->sum(fn($r) => $r['recovery_curr']['total']),
        ];

        // fetch filter options (posisi rka / posisi terakhir) and cognos recovery totals
        $filters = $snapshotService->fetchFilterOptions($selectedPeriod);
        $posisiRkaOptions = $filters['posisi_rka'] ?? [];
        $posisiTerakhirOptions = $filters['posisi_terakhir'] ?? [];

        $cognosRecoveryTotal = 0;
        if ($selectedPeriod) {
            try {
                $cognosRecoveryTotal = (float) DB::table('cognos_recovery')
                    ->whereDate('periode', $selectedPeriod)
                    ->sum('total_recovery');
            } catch (\Throwable $e) {
                $cognosRecoveryTotal = 0;
            }
        }

        return view('report.kejar-laba', [
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $selectedPeriodLabel,
            'rows' => $rows,
            'summary' => $summary,
            'posisi_rka_options' => $posisiRkaOptions,
            'posisi_terakhir_options' => $posisiTerakhirOptions,
            'cognos_recovery_total' => $cognosRecoveryTotal,
            'selectedRka' => $request->input('rka_period'),
        ]);
    }

    private function getRecoveryMetrics(string $period): array
    {
        $data = DB::table('dashboard_harian_snapshots')
            ->where('snapshot_period', $period)
            ->select('kanca_key', 'unit_key', 'rec_dh_micro', 'rec_dh_small', 'rec_dh_consumer', 'rec_dh_total')
            ->get();

        $metrics = [];
        foreach ($data as $row) {
            $key = $row->kanca_key . '|' . $row->unit_key;
            $metrics[$key] = [
                'micro' => (float)$row->rec_dh_micro,
                'small' => (float)$row->rec_dh_small,
                'consumer' => (float)$row->rec_dh_consumer,
                'total' => (float)$row->rec_dh_total,
            ];
        }

        return $metrics;
    }
}
