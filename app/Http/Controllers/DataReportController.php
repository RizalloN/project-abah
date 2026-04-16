<?php

namespace App\Http\Controllers;

use App\Support\RkaLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataReportController extends Controller
{
    public function performanceNewPayroll()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->buildBranchUkerFilterOptions(
            'performance_pis_per_produk',
            'kanca',
            'uker'
        );

        return view('report.kinerja-new-payroll', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function fetchNewPayrollData(Request $request)
    {
        $selectedDate = Carbon::parse($request->input('posisi', date('Y-m-d')));
        $rkaMonthColumn = $this->rkaLookupService()->resolveMonthColumn($selectedDate);
        $rkaMonthLabel = $this->rkaLookupService()->resolveMonthLabel($selectedDate);
        $defaultBranches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($uker) => strtoupper(trim((string) $uker)))
            ->filter()
            ->reject(fn ($uker) => $uker === 'ALL UKER')
            ->values()
            ->all();
        $isBranchFiltered = !empty($selectedBranches);
        $branches = $isBranchFiltered ? $selectedBranches : $defaultBranches;
        $groupExpression = $isBranchFiltered ? 'UPPER(TRIM(uker))' : 'UPPER(TRIM(kanca))';
        $groupLabel = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $totalLabel = $isBranchFiltered
            ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
            : 'TOTAL AREA 6';
        $newPayrollRka = $this->rkaLookupService()->aggregateByGroup(
            [
                'rekening' => ['mata_anggaran' => ['New Rekening Payroll Ritel']],
            ],
            $rkaMonthColumn,
            $branches,
            $selectedUkers,
            $isBranchFiltered ? 'uker' : 'kanca'
        );

        $effectiveSnapshot = DB::table('performance_pis_per_produk')
            ->whereDate('posisi', '<=', $selectedDate->toDateString())
            ->max('posisi');

        if (!$effectiveSnapshot) {
            $labels = $this->buildNewPayrollLabels($selectedDate);
            $labels['rka'] = 'RKA ' . $rkaMonthLabel;

            return response()->json([
                'status' => 'success',
                'labels' => $labels,
                'effective_snapshot' => null,
                'data' => [],
                'total' => $this->buildEmptyNewPayrollTotal(),
            ]);
        }

        $currStart = $selectedDate->copy()->startOfMonth()->toDateString();
        $currEnd = $selectedDate->copy()->endOfMonth()->toDateString();
        $prevStart = $selectedDate->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $prevEnd = Carbon::parse($prevStart)->endOfMonth()->toDateString();
        $yoyStart = $selectedDate->copy()->subYearNoOverflow()->startOfMonth()->toDateString();
        $yoyEnd = Carbon::parse($yoyStart)->endOfMonth()->toDateString();

        $rows = DB::table('performance_pis_per_produk')
            ->selectRaw("{$groupExpression} as branch")
            ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_curr', [$currStart, $currEnd])
            ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_prev', [$prevStart, $prevEnd])
            ->selectRaw('COUNT(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN 1 END) as rekening_yoy_prev', [$yoyStart, $yoyEnd])
            ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_curr', [$currStart, $currEnd])
            ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_prev', [$prevStart, $prevEnd])
            ->selectRaw('SUM(CASE WHEN tanggal_pembuatan_rekening BETWEEN ? AND ? THEN saldo_britama_kerjasama ELSE 0 END) as saldo_yoy_prev', [$yoyStart, $yoyEnd])
            ->whereDate('posisi', $effectiveSnapshot)
            ->whereIn(DB::raw('UPPER(TRIM(kanca))'), array_map('strtoupper', $branches))
            ->when(!empty($selectedUkers), function ($query) use ($selectedUkers) {
                $query->whereIn(DB::raw('UPPER(TRIM(uker))'), $selectedUkers);
            })
            ->groupBy(DB::raw($groupExpression))
            ->get()
            ->keyBy('branch');

        $displayKeys = $isBranchFiltered
            ? $rows->keys()->sort()->values()->all()
            : $defaultBranches;

        $data = [];
        $total = [
            'branch' => $totalLabel,
            'rekening' => ['curr' => 0, 'prev' => 0, 'yoy_prev' => 0, 'rka' => null],
            'saldo' => ['curr' => 0, 'prev' => 0, 'yoy_prev' => 0, 'rka' => null],
            'kualitas' => ['curr' => null, 'prev' => null, 'yoy_prev' => null, 'rka' => null],
        ];
        $totalRekeningRka = 0.0;

        foreach ($displayKeys as $branch) {
            $row = $rows->get(strtoupper($branch));
            $groupKey = strtoupper(trim((string) $branch));

            $rekeningCurr = (int) ($row->rekening_curr ?? 0);
            $rekeningPrev = (int) ($row->rekening_prev ?? 0);
            $rekeningYoyPrev = (int) ($row->rekening_yoy_prev ?? 0);

            $saldoCurr = (float) ($row->saldo_curr ?? 0);
            $saldoPrev = (float) ($row->saldo_prev ?? 0);
            $saldoYoyPrev = (float) ($row->saldo_yoy_prev ?? 0);
            $rekeningRka = round((float) ($newPayrollRka['rekening'][$groupKey] ?? 0), 2);

            $rekeningMetric = $this->calculateNewPayrollMetrics($rekeningCurr, $rekeningPrev, $rekeningYoyPrev);
            $rekeningMetric['rka'] = $rekeningRka;
            $rekeningMetric['penc_pct'] = $rekeningRka > 0 ? (($rekeningCurr / $rekeningRka) * 100) : null;

            $data[] = [
                'branch' => strtoupper($branch),
                'rekening' => $rekeningMetric,
                'saldo' => $this->calculateNewPayrollMetrics($saldoCurr, $saldoPrev, $saldoYoyPrev),
                'kualitas' => $this->emptyNewPayrollMetric(),
            ];

            $total['rekening']['curr'] += $rekeningCurr;
            $total['rekening']['prev'] += $rekeningPrev;
            $total['rekening']['yoy_prev'] += $rekeningYoyPrev;

            $total['saldo']['curr'] += $saldoCurr;
            $total['saldo']['prev'] += $saldoPrev;
            $total['saldo']['yoy_prev'] += $saldoYoyPrev;
            $totalRekeningRka += $rekeningRka;
        }

        $total['rekening'] = $this->calculateNewPayrollMetrics(
            $total['rekening']['curr'],
            $total['rekening']['prev'],
            $total['rekening']['yoy_prev']
        );
        $total['rekening']['rka'] = round($totalRekeningRka, 2);
        $total['rekening']['penc_pct'] = $totalRekeningRka > 0 ? (($total['rekening']['curr'] / $totalRekeningRka) * 100) : null;

        $total['saldo'] = $this->calculateNewPayrollMetrics(
            $total['saldo']['curr'],
            $total['saldo']['prev'],
            $total['saldo']['yoy_prev']
        );

        $labels = $this->buildNewPayrollLabels($selectedDate);
        $labels['rka'] = 'RKA ' . $rkaMonthLabel;

        return response()->json([
            'status' => 'success',
            'labels' => $labels,
            'effective_snapshot' => Carbon::parse($effectiveSnapshot)->toDateString(),
            'group_label' => $groupLabel,
            'data' => $data,
            'total' => $total,
        ]);
    }

    // 🔥 1. VIEW PERFORMANCE EDC
    public function performanceEdc()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->buildBranchUkerFilterOptions(
            'jumlah_merchant_detail',
            'NAMA_KANCA',
            'NAMA_UKER'
        );

        return view('report.performance-edc', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    // 🔥 2. VIEW PERFORMANCE QRIS
    public function performanceQris()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->buildBranchUkerFilterOptions(
            'merchant_qris',
            'NAMA_KCI',
            'NAMA_BRANCH'
        );

        return view('report.performance-qris', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    // 🔥 3. VIEW PERFORMANCE BRILINK
    public function performanceBrilink()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->buildBrilinkFilterOptions();

        return view('report.performance-brilink', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    // 🔥 5. VIEW PERFORMANCE BRIMO
    // 🔥 4. MESIN PENGOLAH DATA UTAMA (AJAX API)
    public function programReferralPartnerPerusahaanAnak(Request $request)
    {
        return $this->buildKolaborasiSnapshotReport(
            $request,
            'input_rekanan',
            'report.program-referral-partner-perusahaan-anak',
            'input_rekanan + simpanan_multipn',
            'Kolaborasi Perusahaan Anak'
        );
    }

    public function nasabahPrioritasBodBoc(Request $request)
    {
        return $this->buildKolaborasiSnapshotReport(
            $request,
            'bod_boc',
            'report.nasabah-prioritas-bod-boc',
            'bod_boc + simpanan_multipn',
            'Nasabah Prioritas BOD/BOC'
        );
    }

    private function buildKolaborasiSnapshotReport(
        Request $request,
        string $sourceTable,
        string $viewName,
        string $sourceLabel,
        string $pageTitle
    ) {
        $statusColumn = $sourceTable === 'bod_boc' ? 'ket_nasabah' : 'status_nasabah';

        $selectedDateInput = (string) $request->query('posisi_terakhir', '');
        $today = now()->endOfDay();
        $selectedDate = $selectedDateInput !== ''
            ? Carbon::parse($selectedDateInput)->endOfDay()
            : $today->copy();

        if ($selectedDate->greaterThan($today)) {
            $selectedDate = $today->copy();
        }

        $positions = collect([
            $selectedDate->copy()->subYearNoOverflow()->endOfYear()->toDateString(),
            $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            $selectedDate->copy()->toDateString(),
        ])->unique()->values();

        while ($positions->count() < 3) {
            $seed = Carbon::parse($positions->first())->subYearNoOverflow()->endOfYear()->toDateString();
            $positions->prepend($seed);
            $positions = $positions->unique()->values();
        }

        $positionBuckets = $positions
            ->mapWithKeys(function ($position) {
                $date = Carbon::parse($position);
                return [$date->format('Y-m') => $position];
            });

        $windowStart = Carbon::parse($positions->first())->startOfMonth()->toDateString();

        $sourceRows = DB::table($sourceTable . ' as src')
            ->whereNotNull('src.periode')
            ->whereBetween('src.periode', [$windowStart, $selectedDate->toDateString()])
            ->selectRaw('DATE(src.periode) as periode')
            ->selectRaw('TRIM(src.cif) as cif')
            ->selectRaw('TRIM(COALESCE(src.' . $statusColumn . ', "")) as status_nasabah')
            ->orderBy('periode')
            ->get();

        $cifList = $sourceRows
            ->pluck('cif')
            ->map(fn ($cif) => trim((string) $cif))
            ->filter(fn ($cif) => $cif !== '')
            ->unique()
            ->values();

        $simpananRows = collect();
        if ($cifList->isNotEmpty()) {
            // Subquery untuk mendapatkan 'posisi' (tanggal) terakhir per CIF
            $latestPosisiQuery = DB::table('simpanan_multipn')
                ->selectRaw('CIFNO, MAX(posisi) as max_posisi')
                ->whereNotNull('CIFNO')
                ->where('posisi', '<=', $selectedDate->toDateString())
                ->whereIn('CIFNO', $cifList->all())
                ->groupBy('CIFNO');

            // Join subquery dengan data utama agar hanya data terbaru yang di-SUM
            $simpananRows = DB::table('simpanan_multipn as sm')
                ->joinSub($latestPosisiQuery, 'latest', function ($join) {
                    $join->on('sm.CIFNO', '=', 'latest.CIFNO')
                         ->on('sm.posisi', '=', 'latest.max_posisi');
                })
                ->selectRaw('TRIM(sm.CIFNO) as cif')
                ->selectRaw('DATE(sm.posisi) as posisi')
                ->selectRaw("MAX(COALESCE(NULLIF(TRIM(sm.kantor_cabang), ''), 'Branch Office Belum Terpetakan')) as kantor_cabang")
                ->selectRaw('SUM(COALESCE(sm.saldo_idr, 0)) as saldo_idr')
                ->groupBy(DB::raw('TRIM(sm.CIFNO)'), DB::raw('DATE(sm.posisi)'))
                ->get();
        }

        $latestSaldoByCif = [];
        foreach ($simpananRows as $simpananRow) {
            $cif = trim((string) ($simpananRow->cif ?? ''));
            if ($cif === '') {
                continue;
            }

            $latestSaldoByCif[$cif] = [
                'posisi' => (string) ($simpananRow->posisi ?? ''),
                'kantor_cabang' => trim((string) ($simpananRow->kantor_cabang ?: 'Branch Office Belum Terpetakan')),
                'saldo_idr' => (float) ($simpananRow->saldo_idr ?? 0),
            ];
        }

        $sourceRows = $sourceRows
            ->map(function ($row) use ($latestSaldoByCif, $positionBuckets) {
                $cif = trim((string) ($row->cif ?? ''));
                $simpanan = $latestSaldoByCif[$cif] ?? null;
                $periode = trim((string) ($row->periode ?? ''));
                $bucketKey = $periode !== '' ? Carbon::parse($periode)->format('Y-m') : null;

                $row->kantor_cabang = $simpanan['kantor_cabang'] ?? 'Branch Office Belum Terpetakan';
                $row->saldo_idr = $simpanan['saldo_idr'] ?? 0;
                $row->is_matched = $simpanan ? 1 : 0;
                $row->bucket_periode = $bucketKey && $positionBuckets->has($bucketKey)
                    ? $positionBuckets->get($bucketKey)
                    : null;
                $row->status_nasabah = trim((string) ($row->status_nasabah ?? ''));

                return $row;
            })
            ->filter(fn ($row) => !empty($row->bucket_periode))
            ->sortBy([
                ['kantor_cabang', 'asc'],
                ['bucket_periode', 'asc'],
            ])
            ->values();

        $latestPosition = $positions->last();
        $previousPosition = $positions->slice(-2, 1)->first();
        $pipelineByRegional = [];
        $stats = [];
        $matchedCount = 0;

        foreach ($sourceRows as $row) {
            $regional = trim((string) ($row->kantor_cabang ?: 'Branch Office Belum Terpetakan'));
            $cif = trim((string) $row->cif);
            $posisi = (string) $row->bucket_periode;
            $isMatched = (int) ($row->is_matched ?? 0) === 1;
            $statusNasabah = strtolower(trim((string) ($row->status_nasabah ?? '')));
            $stats[$regional] ??= [];
            $stats[$regional][$posisi] ??= [
                'pipeline_cifs' => [],
                'sudah_cifs' => [],
                'belum_cifs' => [],
                'saldo_cif' => 0,
            ];
            $stats[$regional][$posisi]['pipeline_cifs'][$cif] = true;
            $pipelineByRegional[$regional][$cif] = true;

            if (str_contains($statusNasabah, 'belum')) {
                $stats[$regional][$posisi]['belum_cifs'][$cif] = true;
            } elseif (str_contains($statusNasabah, 'sudah')) {
                $stats[$regional][$posisi]['sudah_cifs'][$cif] = true;
            }

            if ($isMatched && isset($stats[$regional][$posisi]['sudah_cifs'][$cif])) {
                $stats[$regional][$posisi]['saldo_cif'] += (float) ($row->saldo_idr ?? 0);
                $matchedCount++;
            }
        }

        $regionals = collect(array_unique(array_merge(
            array_keys($pipelineByRegional),
            array_keys($stats)
        )))->sort()->values();

        $tableRows = [];
        $grandTotals = [
            'total_pipeline' => 0,
            'positions' => [],
            'akuisisi_pct' => 0,
            'growth_saldo_pct' => 0,
        ];

        foreach ($positions as $position) {
            $grandTotals['positions'][$position] = [
                'belum_terakuisisi' => 0,
                'sudah_terakuisisi' => 0,
                'saldo_cif' => 0,
            ];
        }

        foreach ($regionals as $regional) {
            $totalPipeline = isset($pipelineByRegional[$regional]) ? count($pipelineByRegional[$regional]) : 0;
            $row = [
                'regional' => $regional,
                'total_pipeline' => $totalPipeline,
                'positions' => [],
                'akuisisi_pct' => 0,
                'growth_saldo_pct' => 0,
            ];
            $runningBelum = 0;
            $runningSudah = 0;
            $runningSaldo = 0;
            $runningYear = null;

            foreach ($positions as $position) {
                $positionYear = Carbon::parse($position)->year;
                if ($runningYear !== null && $runningYear !== $positionYear) {
                    $runningBelum = 0;
                    $runningSudah = 0;
                    $runningSaldo = 0;
                }
                $runningYear = $positionYear;

                $regionalStats = $stats[$regional][$position] ?? [
                    'pipeline_cifs' => [],
                    'sudah_cifs' => [],
                    'belum_cifs' => [],
                    'saldo_cif' => 0,
                ];
                $sudah = count($regionalStats['sudah_cifs']);
                $belum = count($regionalStats['belum_cifs']);
                $runningBelum += $belum;
                $runningSudah += $sudah;
                $runningSaldo += (float) $regionalStats['saldo_cif'];

                $row['positions'][$position] = [
                    'belum_terakuisisi' => $runningBelum,
                    'sudah_terakuisisi' => $runningSudah,
                    'saldo_cif' => $runningSaldo,
                ];

                $grandTotals['positions'][$position]['belum_terakuisisi'] += $runningBelum;
                $grandTotals['positions'][$position]['sudah_terakuisisi'] += $runningSudah;
                $grandTotals['positions'][$position]['saldo_cif'] += $runningSaldo;
            }

            if ($latestPosition && isset($row['positions'][$latestPosition])) {
                $latestSudah = $row['positions'][$latestPosition]['sudah_terakuisisi'];
                $row['akuisisi_pct'] = $totalPipeline > 0 ? ($latestSudah / $totalPipeline) * 100 : 0;
            }

            if ($latestPosition && $previousPosition && isset($row['positions'][$previousPosition])) {
                $latestSaldo = $row['positions'][$latestPosition]['saldo_cif'] ?? 0;
                $previousSaldo = $row['positions'][$previousPosition]['saldo_cif'] ?? 0;
                $row['growth_saldo_pct'] = $previousSaldo > 0
                    ? (($latestSaldo - $previousSaldo) / $previousSaldo) * 100
                    : 0;
            }

            $grandTotals['total_pipeline'] += $totalPipeline;
            $tableRows[] = $row;
        }

        if ($latestPosition && isset($grandTotals['positions'][$latestPosition])) {
            $grandLatestSudah = $grandTotals['positions'][$latestPosition]['sudah_terakuisisi'];
            $grandTotals['akuisisi_pct'] = $grandTotals['total_pipeline'] > 0
                ? ($grandLatestSudah / $grandTotals['total_pipeline']) * 100
                : 0;
        }

        if ($latestPosition && $previousPosition && isset($grandTotals['positions'][$previousPosition])) {
            $grandLatestSaldo = $grandTotals['positions'][$latestPosition]['saldo_cif'] ?? 0;
            $grandPreviousSaldo = $grandTotals['positions'][$previousPosition]['saldo_cif'] ?? 0;
            $grandTotals['growth_saldo_pct'] = $grandPreviousSaldo > 0
                ? (($grandLatestSaldo - $grandPreviousSaldo) / $grandPreviousSaldo) * 100
                : 0;
        }

        return view($viewName, [
            'positions' => $positions,
            'tableRows' => $tableRows,
            'grandTotals' => $grandTotals,
            'matchedCount' => $matchedCount,
            'selectedDate' => $selectedDate->toDateString(),
            'sourceLabel' => $sourceLabel,
            'pageTitle' => $pageTitle,
        ]);
    }

    private function buildBranchUkerFilterOptions(string $table, string $branchColumn, string $ukerColumn): array
    {
        $branchUkerRows = DB::table($table)
            ->selectRaw("TRIM($branchColumn) as branch_name")
            ->selectRaw("TRIM($ukerColumn) as uker_name")
            ->whereNotNull($branchColumn)
            ->whereNotNull($ukerColumn)
            ->whereRaw("TRIM($branchColumn) <> ''")
            ->whereRaw("TRIM($ukerColumn) <> ''")
            ->distinct()
            ->orderBy('branch_name')
            ->orderBy('uker_name')
            ->get();

        return [
            'branchOptions' => $branchUkerRows
                ->pluck('branch_name')
                ->filter()
                ->unique()
                ->values(),
            'branchUkerMap' => $branchUkerRows
                ->groupBy('branch_name')
                ->map(function ($rows) {
                    return $rows->pluck('uker_name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                }),
        ];
    }

    public function fetchData(Request $request)
    {
        $branches = $request->input('branches', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']);
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($branch) => trim((string) $branch))
            ->filter()
            ->values()
            ->all();
        if (!empty($selectedBranches)) {
            $branches = $selectedBranches;
        }
        $isBranchFiltered = !empty($selectedBranches);
        $groupColumn = $isBranchFiltered ? 'NAMA_UKER' : 'NAMA_KANCA';
        $groupLabel = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($uker) => trim((string) $uker))
            ->filter()
            ->reject(fn ($uker) => strtoupper($uker) === 'ALL UKER')
            ->values()
            ->all();
        $upperBranches = array_map('strtoupper', $branches);
        $upperSelectedUkers = array_map('strtoupper', $selectedUkers);
        $totalBranchLabel = !empty($selectedBranches)
            ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
            : 'TOTAL AREA 6';
        $posisi = $request->input('posisi');
        $tab = $request->input('tab', 'edc');

        if (!$posisi) $posisi = date('Y-m-d');

        $selectedDate = Carbon::parse($posisi);
        $rkaMonthColumn = $this->resolveRkaMonthColumn($selectedDate);
        $rkaMonthLabel = $this->resolveRkaMonthLabel($selectedDate);

        $dateCurr = $selectedDate->copy()->toDateString();
        $dateMtD  = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $dateYtD  = $selectedDate->copy()->subYearNoOverflow()->endOfYear()->toDateString();
        $dateYoY  = $selectedDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString();
        
        $datePrevMoM = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $labels = [
            'curr' => Carbon::parse($dateCurr)->translatedFormat('d F Y'),
            'mtd'  => Carbon::parse($dateMtD)->translatedFormat('M\'y'),
            'ytd'  => Carbon::parse($dateYtD)->translatedFormat('M\'y'),
            'yoy'  => Carbon::parse($dateYoY)->translatedFormat('M\'y'),
            'prev_mom' => Carbon::parse($datePrevMoM)->translatedFormat('d M Y'),
            'rka' => $rkaMonthLabel,
        ];

        // =================================================================================
        // LOGIKA TAB 1: PERFORMANCE EDC
        // =================================================================================
        if ($tab === 'edc') {
            $edcRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
                    'sv' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );
            
            $q = DB::table('jumlah_merchant_detail')
                ->select(DB::raw("UPPER($groupColumn) as branch"))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_yoy", [$dateYoY])

                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_mtd", [$dateMtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_yoy", [$dateYoY])

                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_yoy", [$dateYoY]);

            $q->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $upperBranches);
            if ($isBranchFiltered) {
                $q->whereNotNull('NAMA_UKER')
                    ->whereRaw("TRIM(NAMA_UKER) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $upperSelectedUkers);
            }
            $rows = $q->groupBy('branch')->get();

            $data = [];
            $total = [
                'mid_curr'=>0,'mid_mtd'=>0,'mid_ytd'=>0,'mid_yoy'=>0,
                'prod_curr'=>0,'prod_mtd'=>0,'prod_ytd'=>0,'prod_yoy'=>0,
                'sv_curr'=>0,'sv_mtd'=>0,'sv_yoy'=>0
            ];
            $totalProdRka = 0.0;
            $totalSvRka = 0.0;

            foreach ($rows as $r) {
                $branchKey = strtoupper(trim((string) ($r->branch ?? '')));
                $prodRka = round((float) ($edcRkaGroups['prod'][$branchKey] ?? 0), 2);
                $svRka = round((float) ($edcRkaGroups['sv'][$branchKey] ?? 0), 2);
                $prodPencPct = $prodRka > 0 ? (($r->prod_curr / $prodRka) * 100) : 0;

                $data[] = [
                    'branch'=>$r->branch,
                    'mid'=>[
                        'curr'=>$r->mid_curr, 'mtd'=>$r->mid_mtd, 'ytd'=>$r->mid_ytd, 'yoy'=>$r->mid_yoy,
                        'mtd_val'=>$r->mid_curr - $r->mid_mtd, 'mtd_pct'=>$r->mid_mtd>0?(($r->mid_curr-$r->mid_mtd)/$r->mid_mtd)*100:0,
                        'ytd_val'=>$r->mid_curr - $r->mid_ytd, 'yoy_val'=>$r->mid_curr - $r->mid_yoy
                    ],
                    'prod'=>[
                        'curr'=>$r->prod_curr, 'pct_tid'=>$r->mid_curr>0?($r->prod_curr/$r->mid_curr)*100:0,
                        'mtd_val'=>$r->prod_curr-$r->prod_mtd, 'mtd_pct'=>$r->prod_mtd>0?(($r->prod_curr-$r->prod_mtd)/$r->prod_mtd)*100:0,
                        'ytd_val'=>$r->prod_curr-$r->prod_ytd, 'yoy_val'=>$r->prod_curr-$r->prod_yoy, 'rka'=>$prodRka,'penc_pct'=>round($prodPencPct, 2)
                    ],
                    'sv'=>[
                        'curr'=>round($r->sv_curr/1000000000,2), 'mtd_val'=>round(($r->sv_curr-$r->sv_mtd)/1000000000,2),
                        'mtd_pct'=>$r->sv_mtd>0?(($r->sv_curr-$r->sv_mtd)/$r->sv_mtd)*100:0,
                        'yoy_val'=>round(($r->sv_curr-$r->sv_yoy)/1000000000,2), 'rka'=>$svRka,'penc_pct'=>0
                    ]
                ];

                $total['mid_curr'] += $r->mid_curr; $total['mid_mtd'] += $r->mid_mtd; $total['mid_ytd'] += $r->mid_ytd; $total['mid_yoy'] += $r->mid_yoy;
                $total['prod_curr'] += $r->prod_curr; $total['prod_mtd'] += $r->prod_mtd; $total['prod_ytd'] += $r->prod_ytd; $total['prod_yoy'] += $r->prod_yoy;
                $total['sv_curr'] += $r->sv_curr; $total['sv_mtd'] += $r->sv_mtd; $total['sv_yoy'] += $r->sv_yoy;
                $totalProdRka += $prodRka;
                $totalSvRka += $svRka;
            }

            $totalProdPencPct = $totalProdRka > 0 ? (($total['prod_curr'] / $totalProdRka) * 100) : 0;

            return response()->json([
                'status'=>'success', 'labels'=>$labels, 'group_label' => $groupLabel, 'data'=>$data,
                'total'=>[
                    'branch'=>$totalBranchLabel,
                    'mid'=>[
                        'curr'=>$total['mid_curr'], 'mtd'=>$total['mid_mtd'], 'ytd'=>$total['mid_ytd'], 'yoy'=>$total['mid_yoy'],
                        'mtd_val'=>$total['mid_curr']-$total['mid_mtd'], 'mtd_pct'=>$total['mid_mtd']>0?(($total['mid_curr']-$total['mid_mtd'])/$total['mid_mtd'])*100:0,
                        'ytd_val'=>$total['mid_curr']-$total['mid_ytd'], 'yoy_val'=>$total['mid_curr']-$total['mid_yoy']
                    ],
                    'prod'=>[
                        'curr'=>$total['prod_curr'], 'pct_tid'=>$total['mid_curr']>0?($total['prod_curr']/$total['mid_curr'])*100:0,
                        'mtd_val'=>$total['prod_curr']-$total['prod_mtd'], 'mtd_pct'=>$total['prod_mtd']>0?(($total['prod_curr']-$total['prod_mtd'])/$total['prod_mtd'])*100:0,
                        'ytd_val'=>$total['prod_curr']-$total['prod_ytd'], 'yoy_val'=>$total['prod_curr']-$total['prod_yoy'], 'rka'=>$totalProdRka,'penc_pct'=>round($totalProdPencPct, 2)
                    ],
                    'sv'=>[
                        'curr'=>round($total['sv_curr']/1000000000,2), 'mtd_val'=>round(($total['sv_curr']-$total['sv_mtd'])/1000000000,2),
                        'mtd_pct'=>$total['sv_mtd']>0?(($total['sv_curr']-$total['sv_mtd'])/$total['sv_mtd'])*100:0,
                        'yoy_val'=>round(($total['sv_curr']-$total['sv_yoy'])/1000000000,2), 'rka'=>round($totalSvRka, 2),'penc_pct'=>0
                    ]
                ]
            ]);
        }

        // =================================================================================
        // LOGIKA TAB 1B: PERFORMANCE EDC MERCHANT PRODUKTIF
        // =================================================================================
        elseif ($tab === 'merchant_prod') {
            $merchantRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            $query = DB::table('jumlah_merchant_detail')
                ->select(DB::raw("UPPER($groupColumn) as branch"))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_prev_month", [$datePrevMoM])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_yoy", [$dateYoY]);

            $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $upperBranches);
            if ($isBranchFiltered) {
                $query->whereNotNull('NAMA_UKER')
                    ->whereRaw("TRIM(NAMA_UKER) <> ''");
            }
            if (!empty($selectedUkers)) {
                $query->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $upperSelectedUkers);
            }

            $rows = $query->groupBy('branch')->get();

            $data = [];
            $totals = [
                'mid_curr' => 0,
                'prod_curr' => 0,
                'prod_prev_month' => 0,
                'prod_ytd' => 0,
                'prod_yoy' => 0,
            ];
            $totalProdRka = 0.0;

            foreach ($rows as $row) {
                $branchKey = strtoupper(trim((string) ($row->branch ?? '')));
                $midCurr = (int) ($row->mid_curr ?? 0);
                $prodCurr = (int) ($row->prod_curr ?? 0);
                $prodPrevMonth = (int) ($row->prod_prev_month ?? 0);
                $prodYtd = (int) ($row->prod_ytd ?? 0);
                $prodYoy = (int) ($row->prod_yoy ?? 0);
                $prodRka = round((float) ($merchantRkaGroups['prod'][$branchKey] ?? 0), 2);
                $prodPencPct = $prodRka > 0 ? (($prodCurr / $prodRka) * 100) : 0;

                $data[] = [
                    'branch' => $row->branch,
                    'prod' => [
                        'feb_prev' => $prodYoy,
                        'dec_prev' => $prodYtd,
                        'jan_prev' => $prodPrevMonth,
                        'curr' => $prodCurr,
                        'pct_tid' => $midCurr > 0
                            ? round(($prodCurr / $midCurr) * 100, 1)
                            : 0,
                        'mtd_val' => $prodCurr - $prodPrevMonth,
                        'mtd_pct' => $prodPrevMonth > 0
                            ? round((($prodCurr - $prodPrevMonth) / $prodPrevMonth) * 100, 1)
                            : 0,
                        'ytd_val' => $prodCurr - $prodYtd,
                        'yoy_val' => $prodCurr - $prodYoy,
                        'rka' => $prodRka,
                        'penc_pct' => round($prodPencPct, 1),
                    ],
                ];

                $totals['mid_curr'] += $midCurr;
                $totals['prod_curr'] += $prodCurr;
                $totals['prod_prev_month'] += $prodPrevMonth;
                $totals['prod_ytd'] += $prodYtd;
                $totals['prod_yoy'] += $prodYoy;
                $totalProdRka += $prodRka;
            }

            $totalProdPencPct = $totalProdRka > 0 ? (($totals['prod_curr'] / $totalProdRka) * 100) : 0;

            $labels = [
                'merchant_feb_prev' => Carbon::parse($dateYoY)->translatedFormat("M'y"),
                'merchant_dec_prev' => Carbon::parse($dateYtD)->translatedFormat("M'y"),
                'merchant_jan_prev' => Carbon::parse($datePrevMoM)->translatedFormat("M'y"),
                'merchant_curr' => Carbon::parse($dateCurr)->translatedFormat('d M y'),
                'rka' => 'RKA ' . $rkaMonthLabel,
            ];

            return response()->json([
                'status' => 'success',
                'labels' => $labels,
                'group_label' => $groupLabel,
                'data' => $data,
                'total' => [
                    'branch' => $totalBranchLabel,
                    'prod' => [
                        'feb_prev' => $totals['prod_yoy'],
                        'dec_prev' => $totals['prod_ytd'],
                        'jan_prev' => $totals['prod_prev_month'],
                        'curr' => $totals['prod_curr'],
                        'pct_tid' => $totals['mid_curr'] > 0 ? round(($totals['prod_curr'] / $totals['mid_curr']) * 100, 1) : 0,
                        'mtd_val' => $totals['prod_curr'] - $totals['prod_prev_month'],
                        'mtd_pct' => $totals['prod_prev_month'] > 0 ? round((($totals['prod_curr'] - $totals['prod_prev_month']) / $totals['prod_prev_month']) * 100, 1) : 0,
                        'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'],
                        'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'],
                        'rka' => round($totalProdRka, 2),
                        'penc_pct' => round($totalProdPencPct, 1),
                    ],
                ],
            ]);
        }

        // =================================================================================
        // LOGIKA TAB 1C: PERFORMANCE SV MERCHANT EDC AKUMULASI
        // =================================================================================
        elseif ($tab === 'sv_merchant_accum') {
            $svRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'sv' => ['mata_anggaran' => ['Sales Volume Merchant (EDC)']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            $query = DB::table('jumlah_merchant_detail')
                ->select(DB::raw("UPPER($groupColumn) as branch"))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_dec_prev", [$dateYtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_jan_prev", [$datePrevMoM])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_feb_prev", [$dateYoY]);

            $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $upperBranches);
            if ($isBranchFiltered) {
                $query->whereNotNull('NAMA_UKER')
                    ->whereRaw("TRIM(NAMA_UKER) <> ''");
            }
            if (!empty($selectedUkers)) {
                $query->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $upperSelectedUkers);
            }

            $rows = $query->groupBy('branch')->get();

            $data = [];
            $totals = [
                'sv_curr' => 0,
                'sv_dec_prev' => 0,
                'sv_jan_prev' => 0,
                'sv_feb_prev' => 0,
            ];
            $totalSvRka = 0.0;

            foreach ($rows as $row) {
                $branchKey = strtoupper(trim((string) ($row->branch ?? '')));
                $svCurr = round(((float) ($row->sv_curr ?? 0)) / 1000000, 0);
                $svDecPrev = round(((float) ($row->sv_dec_prev ?? 0)) / 1000000, 0);
                $svJanPrev = round(((float) ($row->sv_jan_prev ?? 0)) / 1000000, 0);
                $svFebPrev = round(((float) ($row->sv_feb_prev ?? 0)) / 1000000, 0);
                $svRka = round((float) ($svRkaGroups['sv'][$branchKey] ?? 0) / 1000000, 0);
                $svPencPct = $svRka > 0 ? (($svCurr / $svRka) * 100) : 0;

                $data[] = [
                    'branch' => $row->branch,
                    'sv' => [
                        'feb_prev' => $svFebPrev,
                        'dec_prev' => $svDecPrev,
                        'jan_prev' => $svJanPrev,
                        'curr' => $svCurr,
                        'mtd_val' => $svCurr - $svJanPrev,
                        'mtd_pct' => $svJanPrev > 0 ? round((($svCurr - $svJanPrev) / $svJanPrev) * 100, 1) : 0,
                        'yoy_val' => $svCurr - $svFebPrev,
                        'rka' => $svRka,
                        'penc_pct' => round($svPencPct, 1),
                    ],
                ];

                $totals['sv_curr'] += $svCurr;
                $totals['sv_dec_prev'] += $svDecPrev;
                $totals['sv_jan_prev'] += $svJanPrev;
                $totals['sv_feb_prev'] += $svFebPrev;
                $totalSvRka += $svRka;
            }

            $totalSvPencPct = $totalSvRka > 0 ? (($totals['sv_curr'] / $totalSvRka) * 100) : 0;

            $labels = [
                'merchant_sv_feb_prev' => Carbon::parse($dateYoY)->translatedFormat("M'y"),
                'merchant_sv_dec_prev' => Carbon::parse($dateYtD)->translatedFormat("M'y"),
                'merchant_sv_jan_prev' => Carbon::parse($datePrevMoM)->translatedFormat("M'y"),
                'merchant_sv_curr' => Carbon::parse($dateCurr)->translatedFormat('d M y'),
                'rka' => 'RKA ' . Carbon::parse($dateCurr)->translatedFormat("M'y"),
            ];

            return response()->json([
                'status' => 'success',
                'labels' => $labels,
                'group_label' => $groupLabel,
                'data' => $data,
                'total' => [
                    'branch' => $totalBranchLabel,
                    'sv' => [
                        'feb_prev' => $totals['sv_feb_prev'],
                        'dec_prev' => $totals['sv_dec_prev'],
                        'jan_prev' => $totals['sv_jan_prev'],
                        'curr' => $totals['sv_curr'],
                        'mtd_val' => $totals['sv_curr'] - $totals['sv_jan_prev'],
                        'mtd_pct' => $totals['sv_jan_prev'] > 0 ? round((($totals['sv_curr'] - $totals['sv_jan_prev']) / $totals['sv_jan_prev']) * 100, 1) : 0,
                        'yoy_val' => $totals['sv_curr'] - $totals['sv_feb_prev'],
                        'rka' => round($totalSvRka, 0),
                        'penc_pct' => round($totalSvPencPct, 1),
                    ],
                ],
            ]);
        }

        // =================================================================================
        // LOGIKA TAB 2: MID & TID (LAMA)
        // =================================================================================
        elseif ($tab === 'mid_tid') {
            $query = DB::table('jumlah_merchant_detail')
                ->select(DB::raw("UPPER($groupColumn) as branch"))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_yoy", [$dateYoY])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd", [$dateMtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_yoy", [$dateYoY]);

            $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $upperBranches);
            if ($isBranchFiltered) {
                $query->whereNotNull('NAMA_UKER')
                    ->whereRaw("TRIM(NAMA_UKER) <> ''");
            }
            if (!empty($selectedUkers)) {
                $query->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $upperSelectedUkers);
            }
            $rawData = $query->groupBy('branch')->get();

            $data = [];
            $totals = [
                'mid_curr' => 0, 'mid_mtd' => 0, 'mid_ytd' => 0, 'mid_yoy' => 0,
                'tid_curr' => 0, 'tid_mtd' => 0, 'tid_ytd' => 0, 'tid_yoy' => 0
            ];

            foreach ($rawData as $row) {
                $mid_mtd_val = $row->mid_curr - $row->mid_mtd; $mid_mtd_pct = $row->mid_mtd > 0 ? ($mid_mtd_val / $row->mid_mtd) * 100 : 0;
                $tid_mtd_val = $row->tid_curr - $row->tid_mtd; $tid_mtd_pct = $row->tid_mtd > 0 ? ($tid_mtd_val / $row->tid_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $row->branch,
                    'mid' => [
                        'yoy' => $row->mid_yoy, 'ytd' => $row->mid_ytd, 'mtd' => $row->mid_mtd, 'curr' => $row->mid_curr,
                        'mtd_val' => $mid_mtd_val, 'mtd_pct' => round($mid_mtd_pct, 1),
                        'ytd_val' => $row->mid_curr - $row->mid_ytd, 'yoy_val' => $row->mid_curr - $row->mid_yoy
                    ],
                    'tid' => [
                        'yoy' => $row->tid_yoy, 'ytd' => $row->tid_ytd, 'mtd' => $row->tid_mtd, 'curr' => $row->tid_curr,
                        'mtd_val' => $tid_mtd_val, 'mtd_pct' => round($tid_mtd_pct, 1),
                        'ytd_val' => $row->tid_curr - $row->tid_ytd, 'yoy_val' => $row->tid_curr - $row->tid_yoy,
                        'rka' => 0, 'penc_pct' => 0
                    ]
                ];

                $totals['mid_curr'] += $row->mid_curr; $totals['mid_mtd'] += $row->mid_mtd; $totals['mid_ytd'] += $row->mid_ytd; $totals['mid_yoy'] += $row->mid_yoy;
                $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd; $totals['tid_ytd'] += $row->tid_ytd; $totals['tid_yoy'] += $row->tid_yoy;
            }

            $t_mid_mtd_val = $totals['mid_curr'] - $totals['mid_mtd']; $t_mid_mtd_pct = $totals['mid_mtd'] > 0 ? ($t_mid_mtd_val / $totals['mid_mtd']) * 100 : 0;
            $t_tid_mtd_val = $totals['tid_curr'] - $totals['tid_mtd']; $t_tid_mtd_pct = $totals['tid_mtd'] > 0 ? ($t_tid_mtd_val / $totals['tid_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => $totalBranchLabel,
                'mid' => [
                    'yoy' => $totals['mid_yoy'], 'ytd' => $totals['mid_ytd'], 'mtd' => $totals['mid_mtd'], 'curr' => $totals['mid_curr'],
                    'mtd_val' => $t_mid_mtd_val, 'mtd_pct' => round($t_mid_mtd_pct, 1), 'ytd_val' => $totals['mid_curr'] - $totals['mid_ytd'], 'yoy_val' => $totals['mid_curr'] - $totals['mid_yoy']
                ],
                'tid' => [
                    'yoy' => $totals['tid_yoy'], 'ytd' => $totals['tid_ytd'], 'mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'],
                    'mtd_val' => $t_tid_mtd_val, 'mtd_pct' => round($t_tid_mtd_pct, 1), 'ytd_val' => $totals['tid_curr'] - $totals['tid_ytd'], 'yoy_val' => $totals['tid_curr'] - $totals['tid_yoy'],
                    'rka' => 0, 'penc_pct' => 0
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'group_label' => $groupLabel, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB 3: PRODUKTIVITAS EDC MoM
        // =================================================================================
        elseif ($tab === 'prod_mom') {
            $edcRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            $q = DB::table('jumlah_merchant_detail')
                ->select(DB::raw("UPPER($groupColumn) as branch"))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME = '0' THEN MID END) as sv0_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME = '0' THEN MID END) as sv0_mtd", [$datePrevMoM])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('1 - <1jt', '1jt - <15jt') THEN MID END) as sv1_15_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('1 - <1jt', '1jt - <15jt') THEN MID END) as sv1_15_mtd", [$datePrevMoM])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('15jt - <50jt', '>=50jt') THEN MID END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('15jt - <50jt', '>=50jt') THEN MID END) as prod_mtd", [$datePrevMoM])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd", [$datePrevMoM])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SALES_VOLUME, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as sv_vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SALES_VOLUME, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as sv_vol_mtd", [$datePrevMoM]);

            $q->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $upperBranches);
            if ($isBranchFiltered) {
                $q->whereNotNull('NAMA_UKER')
                    ->whereRaw("TRIM(NAMA_UKER) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $upperSelectedUkers);
            }
            $rawData = $q->groupBy('branch')->get();

            $data = [];
            $totals = [
                'sv0_curr' => 0, 'sv0_mtd' => 0, 'sv1_15_curr' => 0, 'sv1_15_mtd' => 0,
                'prod_curr' => 0, 'prod_mtd' => 0, 'tid_curr' => 0, 'tid_mtd' => 0,
                'sv_vol_curr' => 0, 'sv_vol_mtd' => 0
            ];

            $totalProdRka = 0.0;
            foreach ($rawData as $row) {
                $branchKey = strtoupper(trim((string) ($row->branch ?? '')));
                $prodRka = round((float) ($edcRkaGroups['prod'][$branchKey] ?? 0), 2);
                $prodGap = round($row->prod_curr - $prodRka, 2);
                $prodPenc = $prodRka > 0 ? (($row->prod_curr / $prodRka) * 100) : 0;
                $sv0_mom = $row->sv0_curr - $row->sv0_mtd; $sv0_pct = $row->sv0_mtd > 0 ? ($sv0_mom / $row->sv0_mtd) * 100 : 0;
                $sv1_15_mom = $row->sv1_15_curr - $row->sv1_15_mtd; $sv1_15_pct = $row->sv1_15_mtd > 0 ? ($sv1_15_mom / $row->sv1_15_mtd) * 100 : 0;
                $prod_mom = $row->prod_curr - $row->prod_mtd; $prod_pct = $row->prod_mtd > 0 ? ($prod_mom / $row->prod_mtd) * 100 : 0;
                $tid_mom = $row->tid_curr - $row->tid_mtd; $tid_pct = $row->tid_mtd > 0 ? ($tid_mom / $row->tid_mtd) * 100 : 0;
                $sv_vol_curr = $row->sv_vol_curr / 1000000000; $sv_vol_mtd = $row->sv_vol_mtd / 1000000000;
                $sv_vol_mom = $sv_vol_curr - $sv_vol_mtd; $sv_vol_pct = $sv_vol_mtd > 0 ? ($sv_vol_mom / $sv_vol_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $row->branch,
                    'sv0' => ['mtd' => $row->sv0_mtd, 'curr' => $row->sv0_curr, 'mom' => $sv0_mom, 'pct' => round($sv0_pct, 1)],
                    'sv1_15' => ['mtd' => $row->sv1_15_mtd, 'curr' => $row->sv1_15_curr, 'mom' => $sv1_15_mom, 'pct' => round($sv1_15_pct, 1)],
                    'prod' => ['mtd' => $row->prod_mtd, 'curr' => $row->prod_curr, 'mom' => $prod_mom, 'pct' => round($prod_pct, 1), 'rka' => $prodRka, 'gap' => $prodGap, 'penc' => round($prodPenc, 2)],
                    'tid' => ['mtd' => $row->tid_mtd, 'curr' => $row->tid_curr, 'mom' => $tid_mom, 'pct' => round($tid_pct, 1)],
                    'sv_vol' => ['mtd' => round($sv_vol_mtd, 2), 'curr' => round($sv_vol_curr, 2), 'mom' => round($sv_vol_mom, 2), 'pct' => round($sv_vol_pct, 1)]
                ];

                $totals['sv0_curr'] += $row->sv0_curr; $totals['sv0_mtd'] += $row->sv0_mtd;
                $totals['sv1_15_curr'] += $row->sv1_15_curr; $totals['sv1_15_mtd'] += $row->sv1_15_mtd;
                $totals['prod_curr'] += $row->prod_curr; $totals['prod_mtd'] += $row->prod_mtd;
                $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd;
                $totals['sv_vol_curr'] += $sv_vol_curr; $totals['sv_vol_mtd'] += $sv_vol_mtd;
                $totalProdRka += $prodRka;
            }

            $t_sv0_mom = $totals['sv0_curr'] - $totals['sv0_mtd']; $t_sv0_pct = $totals['sv0_mtd'] > 0 ? ($t_sv0_mom / $totals['sv0_mtd']) * 100 : 0;
            $t_sv1_mom = $totals['sv1_15_curr'] - $totals['sv1_15_mtd']; $t_sv1_pct = $totals['sv1_15_mtd'] > 0 ? ($t_sv1_mom / $totals['sv1_15_mtd']) * 100 : 0;
            $t_prod_mom = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mom / $totals['prod_mtd']) * 100 : 0;
            $t_tid_mom = $totals['tid_curr'] - $totals['tid_mtd']; $t_tid_pct = $totals['tid_mtd'] > 0 ? ($t_tid_mom / $totals['tid_mtd']) * 100 : 0;
            $t_vol_mom = $totals['sv_vol_curr'] - $totals['sv_vol_mtd']; $t_vol_pct = $totals['sv_vol_mtd'] > 0 ? ($t_vol_mom / $totals['sv_vol_mtd']) * 100 : 0;
            $totalProdGap = round($totals['prod_curr'] - $totalProdRka, 2);
            $totalProdPenc = $totalProdRka > 0 ? (($totals['prod_curr'] / $totalProdRka) * 100) : 0;

            $grandTotal = [
                'branch' => $totalBranchLabel,
                'sv0' => ['mtd' => $totals['sv0_mtd'], 'curr' => $totals['sv0_curr'], 'mom' => $t_sv0_mom, 'pct' => round($t_sv0_pct, 1)],
                'sv1_15' => ['mtd' => $totals['sv1_15_mtd'], 'curr' => $totals['sv1_15_curr'], 'mom' => $t_sv1_mom, 'pct' => round($t_sv1_pct, 1)],
                'prod' => ['mtd' => $totals['prod_mtd'], 'curr' => $totals['prod_curr'], 'mom' => $t_prod_mom, 'pct' => round($t_prod_pct, 1), 'rka' => $totalProdRka, 'gap' => $totalProdGap, 'penc' => round($totalProdPenc, 2)],
                'tid' => ['mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'], 'mom' => $t_tid_mom, 'pct' => round($t_tid_pct, 1)],
                'sv_vol' => ['mtd' => round($totals['sv_vol_mtd'],2), 'curr' => round($totals['sv_vol_curr'],2), 'mom' => round($t_vol_mom,2), 'pct' => round($t_vol_pct, 1)]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'group_label' => $groupLabel, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB QRIS: FORMAT MATRIKS
        // =================================================================================
        elseif ($tab === 'qris') {
            $isQrisBranchFiltered = !empty($selectedBranches);
            $qrisGroupColumn = $isQrisBranchFiltered ? 'NAMA_BRANCH' : 'NAMA_KCI';
            $qrisGroupLabel = $isQrisBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
            $qrisTotalLabel = !empty($selectedBranches)
                ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
                : 'TOTAL AREA 6';
            $qrisRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'jml' => ['mata_anggaran' => ['User QRIS']],
                    'prod' => ['mata_anggaran' => ['Jumlah QRIS yang Produktif']],
                    'vol' => ['mata_anggaran' => ['Sales Volume QRIS']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isQrisBranchFiltered ? 'uker' : 'kanca'
            );
            
            $q1 = DB::table('merchant_qris')
                ->select(DB::raw("UPPER($qrisGroupColumn) as branch"))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_ytd", [$dateYtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_yoy", [$dateYoY]);
            $q1->whereIn(DB::raw('UPPER(NAMA_KCI)'), $upperBranches);
            if ($isQrisBranchFiltered) {
                $q1->whereNotNull('NAMA_BRANCH')
                    ->whereRaw("TRIM(NAMA_BRANCH) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q1->whereIn(DB::raw('UPPER(TRIM(NAMA_BRANCH))'), $upperSelectedUkers);
            }
            $dataQris = $q1->groupBy('branch')->get()->keyBy('branch');

            $q2 = DB::table('merchant_qris_volume')
                ->select(DB::raw("UPPER($qrisGroupColumn) as branch"))
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_mtd", [$dateMtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_yoy", [$dateYoY])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_ytd", [$dateYtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_yoy", [$dateYoY]);
            
            $q2->whereIn(DB::raw('UPPER(NAMA_KCI)'), $upperBranches);
            if ($isQrisBranchFiltered) {
                $q2->whereNotNull('NAMA_BRANCH')
                    ->whereRaw("TRIM(NAMA_BRANCH) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q2->whereIn(DB::raw('UPPER(TRIM(NAMA_BRANCH))'), $upperSelectedUkers);
            }
            $dataVol = $q2->groupBy('branch')->get()->keyBy('branch');

            $data = [];
            $totals = [
                'jml_curr' => 0, 'jml_mtd' => 0, 'jml_ytd' => 0, 'jml_yoy' => 0,
                'prod_curr' => 0, 'prod_mtd' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0,
                'vol_curr' => 0, 'vol_mtd' => 0, 'vol_ytd' => 0, 'vol_yoy' => 0
            ];
            $totalJmlRka = 0.0;
            $totalProdRka = 0.0;
            $totalVolRka = 0.0;

            $groupKeys = $isQrisBranchFiltered
                ? $dataQris->keys()->merge($dataVol->keys())->unique()->values()->all()
                : array_map('strtoupper', $branches);

            foreach ($groupKeys as $branchRaw) {
                $b = strtoupper($branchRaw);
                $rowQ = $dataQris->get($b);
                $rowV = $dataVol->get($b);

                $jml_curr = $rowQ->jml_curr ?? 0; $jml_mtd = $rowQ->jml_mtd ?? 0; $jml_ytd = $rowQ->jml_ytd ?? 0; $jml_yoy = $rowQ->jml_yoy ?? 0;
                $prod_curr = $rowV->prod_curr ?? 0; $prod_mtd = $rowV->prod_mtd ?? 0; $prod_ytd = $rowV->prod_ytd ?? 0; $prod_yoy = $rowV->prod_yoy ?? 0;
                
                $vol_curr = ($rowV->vol_curr ?? 0) / 1000000; 
                $vol_mtd = ($rowV->vol_mtd ?? 0) / 1000000; 
                $vol_ytd = ($rowV->vol_ytd ?? 0) / 1000000; 
                $vol_yoy = ($rowV->vol_yoy ?? 0) / 1000000;
                $jmlRka = round((float) ($qrisRkaGroups['jml'][$b] ?? 0), 2);
                $prodRka = round((float) ($qrisRkaGroups['prod'][$b] ?? 0), 2);
                $volRka = round(((float) ($qrisRkaGroups['vol'][$b] ?? 0)) / 1000000, 2);

                $jml_mtd_val = $jml_curr - $jml_mtd; $jml_mtd_pct = $jml_mtd > 0 ? ($jml_mtd_val / $jml_mtd) * 100 : 0;
                $prod_pct_jml = $jml_curr > 0 ? ($prod_curr / $jml_curr) * 100 : 0;
                $prod_mtd_val = $prod_curr - $prod_mtd; $prod_mtd_pct = $prod_mtd > 0 ? ($prod_mtd_val / $prod_mtd) * 100 : 0;
                $vol_mtd_val = $vol_curr - $vol_mtd; $vol_mtd_pct = $vol_mtd > 0 ? ($vol_mtd_val / $vol_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $b,
                    'jml' => [
                        'curr' => $jml_curr, 'mtd_val' => $jml_mtd_val, 'mtd_pct' => round($jml_mtd_pct, 1),
                        'ytd_val' => $jml_curr - $jml_ytd, 'yoy_val' => $jml_curr - $jml_yoy,
                        'rka' => $jmlRka, 'penc_pct' => $jmlRka > 0 ? round(($jml_curr / $jmlRka) * 100, 2) : 0
                    ],
                    'prod' => [
                        'curr' => $prod_curr, 'pct_jml' => round($prod_pct_jml, 1),
                        'mtd_val' => $prod_mtd_val, 'mtd_pct' => round($prod_mtd_pct, 1),
                        'ytd_val' => $prod_curr - $prod_ytd, 'yoy_val' => $prod_curr - $prod_yoy,
                        'rka' => $prodRka, 'penc_pct' => $prodRka > 0 ? round(($prod_curr / $prodRka) * 100, 2) : 0
                    ],
                    'vol' => [
                        'curr' => round($vol_curr, 2), 'mtd_val' => round($vol_mtd_val, 2), 'mtd_pct' => round($vol_mtd_pct, 1),
                        'ytd_val' => round($vol_curr - $vol_ytd, 2), 'yoy_val' => round($vol_curr - $vol_yoy, 2),
                        'rka' => $volRka, 'penc_pct' => $volRka > 0 ? round(($vol_curr / $volRka) * 100, 2) : 0
                    ]
                ];

                $totals['jml_curr'] += $jml_curr; $totals['jml_mtd'] += $jml_mtd; $totals['jml_ytd'] += $jml_ytd; $totals['jml_yoy'] += $jml_yoy;
                $totals['prod_curr'] += $prod_curr; $totals['prod_mtd'] += $prod_mtd; $totals['prod_ytd'] += $prod_ytd; $totals['prod_yoy'] += $prod_yoy;
                $totals['vol_curr'] += $vol_curr; $totals['vol_mtd'] += $vol_mtd; $totals['vol_ytd'] += $vol_ytd; $totals['vol_yoy'] += $vol_yoy;
                $totalJmlRka += $jmlRka;
                $totalProdRka += $prodRka;
                $totalVolRka += $volRka;
            }

            $t_jml_mtd_val = $totals['jml_curr'] - $totals['jml_mtd']; $t_jml_mtd_pct = $totals['jml_mtd'] > 0 ? ($t_jml_mtd_val / $totals['jml_mtd']) * 100 : 0;
            $t_prod_pct_jml = $totals['jml_curr'] > 0 ? ($totals['prod_curr'] / $totals['jml_curr']) * 100 : 0;
            $t_prod_mtd_val = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_mtd_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mtd_val / $totals['prod_mtd']) * 100 : 0;
            $t_vol_mtd_val = $totals['vol_curr'] - $totals['vol_mtd']; $t_vol_mtd_pct = $totals['vol_mtd'] > 0 ? ($t_vol_mtd_val / $totals['vol_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => $qrisTotalLabel,
                'jml' => [
                    'curr' => $totals['jml_curr'], 'mtd_val' => $t_jml_mtd_val, 'mtd_pct' => round($t_jml_mtd_pct, 1),
                    'ytd_val' => $totals['jml_curr'] - $totals['jml_ytd'], 'yoy_val' => $totals['jml_curr'] - $totals['jml_yoy'],
                    'rka' => round($totalJmlRka, 2), 'penc_pct' => $totalJmlRka > 0 ? round(($totals['jml_curr'] / $totalJmlRka) * 100, 2) : 0
                ],
                'prod' => [
                    'curr' => $totals['prod_curr'], 'pct_jml' => round($t_prod_pct_jml, 1),
                    'mtd_val' => $t_prod_mtd_val, 'mtd_pct' => round($t_prod_mtd_pct, 1),
                    'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'],
                    'rka' => round($totalProdRka, 2), 'penc_pct' => $totalProdRka > 0 ? round(($totals['prod_curr'] / $totalProdRka) * 100, 2) : 0
                ],
                'vol' => [
                    'curr' => round($totals['vol_curr'], 2), 'mtd_val' => round($t_vol_mtd_val, 2), 'mtd_pct' => round($t_vol_mtd_pct, 1),
                    'ytd_val' => round($totals['vol_curr'] - $totals['vol_ytd'], 2), 'yoy_val' => round($totals['vol_curr'] - $totals['vol_yoy'], 2),
                    'rka' => round($totalVolRka, 2), 'penc_pct' => $totalVolRka > 0 ? round(($totals['vol_curr'] / $totalVolRka) * 100, 2) : 0
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'group_label' => $qrisGroupLabel, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB QRIS MoM
        // =================================================================================
        elseif ($tab === 'qris_mom') {
            $isQrisBranchFiltered = !empty($selectedBranches);
            $qrisGroupColumn = $isQrisBranchFiltered ? 'NAMA_BRANCH' : 'NAMA_KCI';
            $qrisGroupLabel = $isQrisBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
            $qrisTotalLabel = !empty($selectedBranches)
                ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
                : 'TOTAL AREA 6';
            $qrisRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'prod' => ['mata_anggaran' => ['Jumlah QRIS yang Produktif']],
                ],
                $rkaMonthColumn,
                $upperBranches,
                $upperSelectedUkers,
                $isQrisBranchFiltered ? 'uker' : 'kanca'
            );

            $q1 = DB::table('merchant_qris')
                ->select(DB::raw("UPPER($qrisGroupColumn) as branch"))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_prev", [$datePrevMoM])
                ->whereIn(DB::raw('UPPER(NAMA_KCI)'), $upperBranches);
            if ($isQrisBranchFiltered) {
                $q1->whereNotNull('NAMA_BRANCH')
                    ->whereRaw("TRIM(NAMA_BRANCH) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q1->whereIn(DB::raw('UPPER(TRIM(NAMA_BRANCH))'), $upperSelectedUkers);
            }
            $q1 = $q1
                ->groupBy('branch')
                ->get()
                ->keyBy('branch');

            $q2 = DB::table('merchant_qris_volume')
                ->select(DB::raw("UPPER($qrisGroupColumn) as branch"))
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME=0 THEN 1 END) as sv0_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME=0 THEN 1 END) as sv0_prev", [$datePrevMoM])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME>=50000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME>=50000 THEN 1 END) as prod_prev", [$datePrevMoM])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_prev", [$datePrevMoM])
                ->whereIn(DB::raw('UPPER(NAMA_KCI)'), $upperBranches);
            if ($isQrisBranchFiltered) {
                $q2->whereNotNull('NAMA_BRANCH')
                    ->whereRaw("TRIM(NAMA_BRANCH) <> ''");
            }
            if (!empty($selectedUkers)) {
                $q2->whereIn(DB::raw('UPPER(TRIM(NAMA_BRANCH))'), $upperSelectedUkers);
            }
            $q2 = $q2
                ->groupBy('branch')
                ->get()
                ->keyBy('branch');

            $data = [];
            
            $totals = [
                'store_curr' => 0, 'store_prev' => 0,
                'sv0_curr' => 0, 'sv0_prev' => 0,
                'prod_curr' => 0, 'prod_prev' => 0,
                'vol_curr' => 0, 'vol_prev' => 0
            ];
            $totalProdRka = 0.0;

            $groupKeys = $isQrisBranchFiltered
                ? $q1->keys()->merge($q2->keys())->unique()->values()->all()
                : array_map('strtoupper', $branches);

            foreach ($groupKeys as $branchRaw) {
                $b = strtoupper($branchRaw);

                $rowStore = $q1->get($b);
                $rowV = $q2->get($b);

                $store_curr = $rowStore->store_curr ?? 0;
                $store_prev = $rowStore->store_prev ?? 0;

                $sv0_curr = $rowV->sv0_curr ?? 0;
                $sv0_prev = $rowV->sv0_prev ?? 0;

                $prod_curr = $rowV->prod_curr ?? 0;
                $prod_prev = $rowV->prod_prev ?? 0;

                $vol_curr = ($rowV->vol_curr ?? 0) / 1000000; 
                $vol_prev = ($rowV->vol_prev ?? 0) / 1000000;
                $prodRka = round((float) ($qrisRkaGroups['prod'][$b] ?? 0), 2);

                $data[] = [
                    'branch' => $b,
                    'sv0' => [
                        'prev' => $sv0_prev,
                        'curr' => $sv0_curr,
                        'mom' => $sv0_curr - $sv0_prev,
                        'pct' => $sv0_prev > 0 ? round((($sv0_curr - $sv0_prev)/$sv0_prev)*100, 1) : 0
                    ],
                    'prod' => [
                        'prev' => $prod_prev,
                        'curr' => $prod_curr,
                        'mom' => $prod_curr - $prod_prev,
                        'pct' => $prod_prev > 0 ? round((($prod_curr - $prod_prev)/$prod_prev)*100, 1) : 0,
                        'rka' => $prodRka, 'gap' => round($prod_curr - $prodRka, 2), 'penc' => $prodRka > 0 ? round(($prod_curr / $prodRka) * 100, 2) : 0
                    ],
                    'store' => [
                        'prev' => $store_prev,
                        'curr' => $store_curr,
                        'mom' => $store_curr - $store_prev,
                        'pct' => $store_prev > 0 ? round((($store_curr - $store_prev)/$store_prev)*100, 1) : 0
                    ],
                    'vol' => [
                        'prev' => round($vol_prev,2),
                        'curr' => round($vol_curr,2),
                        'mom' => round($vol_curr - $vol_prev,2),
                        'pct' => $vol_prev > 0 ? round((($vol_curr - $vol_prev)/$vol_prev)*100, 1) : 0
                    ]
                ];

                $totals['store_curr'] += $store_curr; $totals['store_prev'] += $store_prev;
                $totals['sv0_curr'] += $sv0_curr;     $totals['sv0_prev'] += $sv0_prev;
                $totals['prod_curr'] += $prod_curr;   $totals['prod_prev'] += $prod_prev;
                $totals['vol_curr'] += $vol_curr;     $totals['vol_prev'] += $vol_prev;
                $totalProdRka += $prodRka;
            }

            $t_sv0_mom = $totals['sv0_curr'] - $totals['sv0_prev']; 
            $t_sv0_pct = $totals['sv0_prev'] > 0 ? ($t_sv0_mom / $totals['sv0_prev']) * 100 : 0;
            
            $t_prod_mom = $totals['prod_curr'] - $totals['prod_prev']; 
            $t_prod_pct = $totals['prod_prev'] > 0 ? ($t_prod_mom / $totals['prod_prev']) * 100 : 0;
            
            $t_store_mom = $totals['store_curr'] - $totals['store_prev']; 
            $t_store_pct = $totals['store_prev'] > 0 ? ($t_store_mom / $totals['store_prev']) * 100 : 0;
            
            $t_vol_mom = $totals['vol_curr'] - $totals['vol_prev']; 
            $t_vol_pct = $totals['vol_prev'] > 0 ? ($t_vol_mom / $totals['vol_prev']) * 100 : 0;

            $grandTotal = [
                'branch' => $qrisTotalLabel,
                'sv0' => [
                    'prev' => $totals['sv0_prev'], 'curr' => $totals['sv0_curr'], 'mom' => $t_sv0_mom, 'pct' => round($t_sv0_pct, 1)
                ],
                'prod' => [
                    'prev' => $totals['prod_prev'], 'curr' => $totals['prod_curr'], 'mom' => $t_prod_mom, 'pct' => round($t_prod_pct, 1),
                    'rka' => round($totalProdRka, 2), 'gap' => round($totals['prod_curr'] - $totalProdRka, 2), 'penc' => $totalProdRka > 0 ? round(($totals['prod_curr'] / $totalProdRka) * 100, 2) : 0
                ],
                'store' => [
                    'prev' => $totals['store_prev'], 'curr' => $totals['store_curr'], 'mom' => $t_store_mom, 'pct' => round($t_store_pct, 1)
                ],
                'vol' => [
                    'prev' => round($totals['vol_prev'], 2), 'curr' => round($totals['vol_curr'], 2), 'mom' => round($t_vol_mom, 2), 'pct' => round($t_vol_pct, 1)
                ]
            ];

            return response()->json([
                'status' => 'success',
                'labels' => $labels,
                'group_label' => $qrisGroupLabel,
                'data' => $data,
                'total' => $grandTotal
            ]);
        }

        // =================================================================================
        // 🔥 LOGIKA TAB BRILINK (ENGINE BARU DENGAN FIX LOCALE BAHASA & EXACT MATCH)
        // =================================================================================
        elseif ($tab === 'brilink') {

            $bulanInput = $request->input('periode_bulan');

            if (!$bulanInput) {
                return response()->json(['status' => 'error', 'msg' => 'Periode kosong']);
            }

            // Parser Flexible & Kunci Locale EN
            if (preg_match('/^\d{4}-\d{2}$/', $bulanInput)) {
                $current = Carbon::createFromFormat('Y-m', $bulanInput)->startOfMonth()->locale('en');
            } else {
                $current = Carbon::createFromFormat('F Y', $bulanInput)->startOfMonth()->locale('en');
            }

            $prevMonth = $current->copy()->subMonth()->locale('en');
            $lastYearSameMonth = $current->copy()->subYear()->locale('en');
            $lastYearEnd = Carbon::create($current->year - 1, 12, 1)->locale('en');

            // Format ke String English Wajib (Tanpa translatedFormat)
            $periodeCurr = $current->format('F Y');
            $periodePrev = $prevMonth->format('F Y');
            $periodeYoY  = $lastYearSameMonth->format('F Y');
            $periodeYtD  = $lastYearEnd->format('F Y');

            $data = [];
            
            $totals = [
                'agen' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
                'juragan' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
                'bep' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
                'trx' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
                'volume' => ['curr' => 0, 'mtd' => 0, 'yoy' => 0],
                'casa' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0]
            ];

            $isBranchFiltered = !empty($selectedBranches);
            $groupLabel = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
            $brilinkFilterOptions = $this->buildBrilinkFilterOptions();
            $brilinkBranchUkerMap = $brilinkFilterOptions['branchUkerMap'] ?? collect();
            $displayItems = !empty($selectedUkers)
                ? $selectedUkers
                : ($isBranchFiltered ? $this->getBrilinkUkersForBranches($branches, $brilinkBranchUkerMap) : $branches);
            $displayColumn = $isBranchFiltered ? 'uker' : 'cabang';
            $casaDisplayColumn = $isBranchFiltered ? 'brdesc' : 'mbdesc';
            $brilinkRkaMonthColumn = $this->rkaLookupService()->resolveMonthColumn($current);
            $brilinkRkaMonthLabel = $this->rkaLookupService()->resolveMonthLabel($current);
            $brilinkRkaGroups = $this->rkaLookupService()->aggregateByGroup(
                [
                    'agen' => ['mata_anggaran' => ['Jumlah Agen Brilink']],
                    'juragan' => ['mata_anggaran' => ['Jumlah Agen Brilink Jawara', 'Jumlah Agen Brilink Juragan']],
                    'bep' => ['mata_anggaran' => ['Jumlah Agen Brilink yang BEP']],
                ],
                $brilinkRkaMonthColumn,
                $branches,
                $selectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            $selectedCasaDate = $current->copy()->endOfMonth();
            $latestCasaWeb = DB::table('casa_brilink_web')
                ->whereDate('periode', '<=', $selectedCasaDate->toDateString())
                ->max('periode');
            $latestCasaEdc = DB::table('casa_brilink_edc')
                ->whereDate('periode', '<=', $selectedCasaDate->toDateString())
                ->max('periode');

            $latestCasaCandidates = array_filter([$latestCasaWeb, $latestCasaEdc]);
            $effectiveCasaDate = !empty($latestCasaCandidates)
                ? Carbon::parse(max($latestCasaCandidates))
                : $selectedCasaDate->copy();

            $casaPrevDate = $effectiveCasaDate->copy()->subMonthNoOverflow()->endOfMonth();
            $casaYoyDate = $effectiveCasaDate->copy()->subYearNoOverflow()->endOfMonth();
            $casaYtdDate = Carbon::create($effectiveCasaDate->year - 1, 12, 1)->endOfMonth();

            $branchAliasMap = $this->buildBranchAliasMap($branches);
            $branchLookupKeys = array_values(array_unique(array_merge(...array_values($branchAliasMap))));

            $fetchCasaByPeriod = function (Carbon $period) use ($branchLookupKeys, $branchAliasMap, $isBranchFiltered, $selectedUkers, $casaDisplayColumn) {

                $webRows = DB::table('casa_brilink_web')
                    ->selectRaw("UPPER(TRIM($casaDisplayColumn)) as branch")
                    ->selectRaw('SUM(COALESCE(jml_nominal_casa, 0)) as total_nominal')
                    ->whereDate('periode', $period->toDateString())
                    ->whereIn(DB::raw('UPPER(TRIM(mbdesc))'), $branchLookupKeys)
                    ->when($isBranchFiltered && !empty($selectedUkers), function ($query) use ($selectedUkers, $casaDisplayColumn) {
                        $query->whereIn(DB::raw("UPPER(TRIM($casaDisplayColumn))"), $selectedUkers);
                    })
                    ->groupBy(DB::raw("UPPER(TRIM($casaDisplayColumn))"))
                    ->get();

                $edcRows = DB::table('casa_brilink_edc')
                    ->selectRaw("UPPER(TRIM($casaDisplayColumn)) as branch")
                    ->selectRaw('SUM(COALESCE(jml_nominal_casa, 0)) as total_nominal')
                    ->whereDate('periode', $period->toDateString())
                    ->whereIn(DB::raw('UPPER(TRIM(mbdesc))'), $branchLookupKeys)
                    ->when($isBranchFiltered && !empty($selectedUkers), function ($query) use ($selectedUkers, $casaDisplayColumn) {
                        $query->whereIn(DB::raw("UPPER(TRIM($casaDisplayColumn))"), $selectedUkers);
                    })
                    ->groupBy(DB::raw("UPPER(TRIM($casaDisplayColumn))"))
                    ->get();

                $merged = [];

                foreach ($webRows as $row) {
                    $branch = strtoupper(trim((string) $row->branch));
                    $canonicalBranch = $this->resolveCanonicalBranchKey($branchAliasMap, $branch);
                    $merged[$canonicalBranch] = ($merged[$canonicalBranch] ?? 0) + (float) $row->total_nominal;
                }

                foreach ($edcRows as $row) {
                    $branch = strtoupper(trim((string) $row->branch));
                    $canonicalBranch = $this->resolveCanonicalBranchKey($branchAliasMap, $branch);
                    $merged[$canonicalBranch] = ($merged[$canonicalBranch] ?? 0) + (float) $row->total_nominal;
                }

                return $merged;
            };

            $casaCurrMap = $fetchCasaByPeriod($effectiveCasaDate);
            $casaPrevMap = $fetchCasaByPeriod($casaPrevDate);
            $casaYtdMap = $fetchCasaByPeriod($casaYtdDate);
            $casaYoyMap = $fetchCasaByPeriod($casaYoyDate);
            $brilinkRows = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                ->selectRaw("UPPER(TRIM($displayColumn)) as branch")
                ->addSelect('periode')
                ->selectRaw('COUNT(*) as agen')
                ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 750000 THEN 1 ELSE 0 END) as juragan')
                ->selectRaw('SUM(CASE WHEN COALESCE(total_fee, 0) >= 150000 THEN 1 ELSE 0 END) as bep')
                ->selectRaw('COALESCE(SUM(COALESCE(total_transaksi, 0)), 0) as trx')
                ->selectRaw('COALESCE(SUM(COALESCE(total_nominal, 0)), 0) as volume')
                ->whereIn('periode', array_values(array_unique([$periodeCurr, $periodePrev, $periodeYoY, $periodeYtD])))
                ->whereIn(DB::raw('UPPER(TRIM(cabang))'), $branchLookupKeys)
                ->when($isBranchFiltered && !empty($selectedUkers), function ($query) use ($selectedUkers, $displayColumn) {
                    $query->whereIn(DB::raw("UPPER(TRIM($displayColumn))"), $selectedUkers);
                })
                ->groupBy('periode', DB::raw("UPPER(TRIM($displayColumn))"))
                ->get();

            $brilinkMap = [];
            foreach ($brilinkRows as $row) {
                $period = (string) ($row->periode ?? '');
                $rawBranchKey = strtoupper(trim((string) ($row->branch ?? '')));
                $branchKey = $this->resolveCanonicalBranchKey($branchAliasMap, $rawBranchKey);
                $brilinkMap[$period][$branchKey] = [
                    'agen' => (int) ($row->agen ?? 0),
                    'juragan' => (int) ($row->juragan ?? 0),
                    'bep' => (int) ($row->bep ?? 0),
                    'trx' => (float) ($row->trx ?? 0),
                    'volume' => (float) ($row->volume ?? 0),
                ];
            }

            foreach ($displayItems as $branch) {
                $branchKey = $this->resolveCanonicalBranchKey($branchAliasMap, strtoupper(trim((string) $branch)));
                $currData = $brilinkMap[$periodeCurr][$branchKey] ?? null;
                $prevData = $brilinkMap[$periodePrev][$branchKey] ?? null;
                $yoyData = $brilinkMap[$periodeYoY][$branchKey] ?? null;
                $ytdData = $brilinkMap[$periodeYtD][$branchKey] ?? null;

                // 🔥 VALIDASI SUPER AMAN: Cek apakah data bulan ini memang ada di DB
                $hasCurrData = $currData !== null;

                // LOGIKA METRIK AMAN DENGAN VARIABEL TERPISAH
                $agen_curr = (int) ($currData['agen'] ?? 0);
                $agen_prev = (int) ($prevData['agen'] ?? 0);
                $agen_yoy  = (int) ($yoyData['agen'] ?? 0);
                $agen_ytd  = (int) ($ytdData['agen'] ?? 0);

                $juragan_curr = (int) ($currData['juragan'] ?? 0);
                $juragan_prev = (int) ($prevData['juragan'] ?? 0);
                $juragan_yoy  = (int) ($yoyData['juragan'] ?? 0);
                $juragan_ytd  = (int) ($ytdData['juragan'] ?? 0);

                $bep_curr = (int) ($currData['bep'] ?? 0);
                $bep_prev = (int) ($prevData['bep'] ?? 0);
                $bep_yoy  = (int) ($yoyData['bep'] ?? 0);
                $bep_ytd  = (int) ($ytdData['bep'] ?? 0);

                $trx_curr = (float) ($currData['trx'] ?? 0);
                $trx_prev = (float) ($prevData['trx'] ?? 0);
                $trx_yoy  = (float) ($yoyData['trx'] ?? 0);
                $trx_ytd  = (float) ($ytdData['trx'] ?? 0);

                $vol_curr = (float) ($currData['volume'] ?? 0);
                $vol_prev = (float) ($prevData['volume'] ?? 0);
                $vol_yoy  = (float) ($yoyData['volume'] ?? 0);

                // 🔥 JANGAN HITUNG SELISIH JIKA BULAN INI BELUM DIUPLOAD
                $agen_mtd = $hasCurrData ? ($agen_curr - $agen_prev) : 0;
                $agen_ytd_val = $hasCurrData ? ($agen_curr - $agen_ytd) : 0;
                $agen_yoy_val = $hasCurrData ? ($agen_curr - $agen_yoy) : 0;

                $juragan_mtd = $hasCurrData ? ($juragan_curr - $juragan_prev) : 0;
                $juragan_ytd_val = $hasCurrData ? ($juragan_curr - $juragan_ytd) : 0;
                $juragan_yoy_val = $hasCurrData ? ($juragan_curr - $juragan_yoy) : 0;

                $bep_mtd = $hasCurrData ? ($bep_curr - $bep_prev) : 0;
                $bep_ytd_val = $hasCurrData ? ($bep_curr - $bep_ytd) : 0;
                $bep_yoy_val = $hasCurrData ? ($bep_curr - $bep_yoy) : 0;

                $trx_mtd = $hasCurrData ? ($trx_curr - $trx_prev) : 0;
                $trx_ytd_val = $hasCurrData ? ($trx_curr - $trx_ytd) : 0;
                $trx_yoy_val = $hasCurrData ? ($trx_curr - $trx_yoy) : 0;

                $vol_mtd = $hasCurrData ? ($vol_curr - $vol_prev) : 0;
                $vol_yoy_val = $hasCurrData ? ($vol_curr - $vol_yoy) : 0;

                $branchKey = strtoupper(trim($branch));
                $casa_curr = (float) ($casaCurrMap[$branchKey] ?? 0);
                $casa_prev = (float) ($casaPrevMap[$branchKey] ?? 0);
                $casa_ytd = (float) ($casaYtdMap[$branchKey] ?? 0);
                $casa_yoy = (float) ($casaYoyMap[$branchKey] ?? 0);

                $casa_has_curr = $casa_curr > 0 || $casa_prev > 0 || $casa_ytd > 0 || $casa_yoy > 0;
                $casa_mtd = $casa_has_curr ? ($casa_curr - $casa_prev) : 0;
                $casa_ytd_val = $casa_has_curr ? ($casa_curr - $casa_ytd) : 0;
                $casa_yoy_val = $casa_has_curr ? ($casa_curr - $casa_yoy) : 0;
                $agenRka = round((float) ($brilinkRkaGroups['agen'][$branchKey] ?? 0), 2);
                $juraganRka = round((float) ($brilinkRkaGroups['juragan'][$branchKey] ?? 0), 2);
                $bepRka = round((float) ($brilinkRkaGroups['bep'][$branchKey] ?? 0), 2);

                $data[] = [
                    'branch' => $branch,
                    'agen' => [
                        'curr' => $agen_curr, 'mtd' => $agen_mtd, 'ytd' => $agen_ytd_val, 'yoy' => $agen_yoy_val,
                        'rka' => $agenRka, 'penc_pct' => $agenRka > 0 ? round(($agen_curr / $agenRka) * 100, 2) : 0,
                    ],
                    'juragan' => [
                        'curr' => $juragan_curr, 'mtd' => $juragan_mtd, 'ytd' => $juragan_ytd_val, 'yoy' => $juragan_yoy_val,
                        'rka' => $juraganRka, 'penc_pct' => $juraganRka > 0 ? round(($juragan_curr / $juraganRka) * 100, 2) : 0,
                    ],
                    'bep' => [
                        'curr' => $bep_curr, 'mtd' => $bep_mtd, 'ytd' => $bep_ytd_val, 'yoy' => $bep_yoy_val,
                        'rka' => $bepRka, 'penc_pct' => $bepRka > 0 ? round(($bep_curr / $bepRka) * 100, 2) : 0,
                    ],
                    'trx' => [
                        'curr' => $trx_curr, 'mtd' => $trx_mtd, 'ytd' => $trx_ytd_val, 'yoy' => $trx_yoy_val,
                    ],
                    'volume' => [
                        'curr' => $vol_curr, 'mtd' => $vol_mtd, 'yoy' => $vol_yoy_val,
                    ],
                    'casa' => [
                        'curr' => round($casa_curr, 2), 'mtd' => round($casa_mtd, 2),
                        'ytd' => round($casa_ytd_val, 2), 'yoy' => round($casa_yoy_val, 2),
                    ],
                ];
                
                $totals['agen']['curr'] += $agen_curr; $totals['agen']['mtd'] += $agen_mtd; $totals['agen']['ytd'] += $agen_ytd_val; $totals['agen']['yoy'] += $agen_yoy_val;
                $totals['juragan']['curr'] += $juragan_curr; $totals['juragan']['mtd'] += $juragan_mtd; $totals['juragan']['ytd'] += $juragan_ytd_val; $totals['juragan']['yoy'] += $juragan_yoy_val;
                $totals['bep']['curr'] += $bep_curr; $totals['bep']['mtd'] += $bep_mtd; $totals['bep']['ytd'] += $bep_ytd_val; $totals['bep']['yoy'] += $bep_yoy_val;
                $totals['trx']['curr'] += $trx_curr; $totals['trx']['mtd'] += $trx_mtd; $totals['trx']['ytd'] += $trx_ytd_val; $totals['trx']['yoy'] += $trx_yoy_val;
                $totals['volume']['curr'] += $vol_curr; $totals['volume']['mtd'] += $vol_mtd; $totals['volume']['yoy'] += $vol_yoy_val;
                $totals['casa']['curr'] += $casa_curr; $totals['casa']['mtd'] += $casa_mtd; $totals['casa']['ytd'] += $casa_ytd_val; $totals['casa']['yoy'] += $casa_yoy_val;
                $totals['agen']['rka'] = round((float) ($totals['agen']['rka'] ?? 0) + $agenRka, 2);
                $totals['juragan']['rka'] = round((float) ($totals['juragan']['rka'] ?? 0) + $juraganRka, 2);
                $totals['bep']['rka'] = round((float) ($totals['bep']['rka'] ?? 0) + $bepRka, 2);
            }

            $totals['agen']['penc_pct'] = ($totals['agen']['rka'] ?? 0) > 0 ? round(($totals['agen']['curr'] / $totals['agen']['rka']) * 100, 2) : 0;
            $totals['juragan']['penc_pct'] = ($totals['juragan']['rka'] ?? 0) > 0 ? round(($totals['juragan']['curr'] / $totals['juragan']['rka']) * 100, 2) : 0;
            $totals['bep']['penc_pct'] = ($totals['bep']['rka'] ?? 0) > 0 ? round(($totals['bep']['curr'] / $totals['bep']['rka']) * 100, 2) : 0;

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'group_label' => $groupLabel,
                'labels' => [
                    'curr' => $periodeCurr,
                    'rka' => $brilinkRkaMonthLabel,
                    'casa_curr' => $effectiveCasaDate->translatedFormat("M'y"),
                    'casa_dec' => $casaYtdDate->translatedFormat("M'y"),
                    'casa_prev' => $casaPrevDate->translatedFormat('d-M'),
                    'casa_end' => $effectiveCasaDate->translatedFormat('d-M'),
                ],
                'total' => [
                    'branch' => 'TOTAL AREA 6',
                    'agen' => $totals['agen'],
                    'juragan' => $totals['juragan'],
                    'bep' => $totals['bep'],
                    'trx' => $totals['trx'],
                    'volume' => $totals['volume'],
                    'casa' => [
                        'curr' => round($totals['casa']['curr'], 2),
                        'mtd' => round($totals['casa']['mtd'], 2),
                        'ytd' => round($totals['casa']['ytd'], 2),
                        'yoy' => round($totals['casa']['yoy'], 2),
                    ]
                ]
            ]);
        }

        // =================================================================================
        // 🔥 LOGIKA TAB BRIMO: UREG REKENING & FINANSIAL (OPTIMIZED SINGLE QUERY)
        // =================================================================================
        elseif ($tab === 'brimo') {
            // 🔥 FIX DATEPICKER: Cari tanggal posisi terakhir yang tersedia di DB <= tanggal dipilih.
            // Ini mengatasi MtD = 0 ketika user memilih tanggal tengah bulan,
            // karena data disimpan per akhir bulan (bukan harian).
            $latestAvailableDate = DB::table('user_brimo_rpt_v2')
                ->where('posisi', '<=', $dateCurr)
                ->max('posisi');

            // Gunakan tanggal efektif (tanggal data terakhir tersedia), fallback ke tanggal dipilih
            $effectiveCurr = $latestAvailableDate
                ? Carbon::parse($latestAvailableDate)
                : Carbon::parse($dateCurr);

            // Hitung ulang semua periode berdasarkan tanggal efektif
            $effectiveDateCurr = $effectiveCurr->toDateString();
            $effectiveDateMtD  = $effectiveCurr->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $effectiveDateYtD  = $effectiveCurr->copy()->subYearNoOverflow()->endOfYear()->toDateString();
            $effectiveDateYoY  = $effectiveCurr->copy()->subYearNoOverflow()->endOfMonth()->toDateString();

            // 🔥 FIX: Format label curr sebagai bulan/tahun (Feb'26) sesuai tampilan target
            $labels = [
                'curr' => $effectiveCurr->translatedFormat('M\'y'),
                'mtd'  => Carbon::parse($effectiveDateMtD)->translatedFormat('M\'y'),
                'ytd'  => Carbon::parse($effectiveDateYtD)->translatedFormat('M\'y'),
                'yoy'  => Carbon::parse($effectiveDateYoY)->translatedFormat('M\'y'),
            ];

            // 🔥 FIX: Gunakan kolom `posisi` (bukan `tanggal` yang tidak ada di tabel)
            // QUERY 1: user_brimo_rpt_v2 (UREG by Rekening) - SINGLE QUERY 4 PERIODS
            $q_rek = DB::table('user_brimo_rpt_v2')
                ->select(DB::raw('UPPER(COALESCE(brdesc, branch)) as branch'))
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_rek_curr', [$effectiveDateCurr])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_rek_mtd', [$effectiveDateMtD])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_rek_ytd', [$effectiveDateYtD])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_rek_yoy', [$effectiveDateYoY])
                ->where(function($q) use ($branches) {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), array_map('strtoupper', $branches));
                })
                ->groupBy(DB::raw('UPPER(COALESCE(brdesc, branch))'));

            $rekData = $q_rek->get()->keyBy('branch');

            // 🔥 FIX: Gunakan kolom `posisi` (bukan `tanggal` yang tidak ada di tabel)
            // QUERY 2: user_brimo_fin (UREG Finansial) - SINGLE QUERY 4 PERIODS
            $q_fin = DB::table('user_brimo_fin')
                ->select(DB::raw('UPPER(COALESCE(brdesc, branch)) as branch'))
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_fin_curr', [$effectiveDateCurr])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_fin_mtd', [$effectiveDateMtD])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_fin_ytd', [$effectiveDateYtD])
                ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_fin_yoy', [$effectiveDateYoY])
                ->where(function($q) use ($branches) {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), array_map('strtoupper', $branches));
                })
                ->groupBy(DB::raw('UPPER(COALESCE(brdesc, branch))'));

            $finData = $q_fin->get()->keyBy('branch');

            $data = [];

            // 🔥 FIX: Simpan nilai RAW (bukan growth) untuk perhitungan total yang benar
            $raw_totals = [
                'ureg_rekening' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
                'ureg_finansial' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            ];

            foreach ($branches as $branchRaw) {
                $branch = strtoupper($branchRaw);

                $rek = $rekData->get($branch) ?? (object)['ureg_rek_curr' => 0, 'ureg_rek_mtd' => 0, 'ureg_rek_ytd' => 0, 'ureg_rek_yoy' => 0];
                $fin = $finData->get($branch) ?? (object)['ureg_fin_curr' => 0, 'ureg_fin_mtd' => 0, 'ureg_fin_ytd' => 0, 'ureg_fin_yoy' => 0];

                // 🔥 FIX: Hitung YoY% per baris dengan aman (hindari division by zero)
                $ureg_rek_yoy_pct = $rek->ureg_rek_yoy > 0
                    ? (($rek->ureg_rek_curr - $rek->ureg_rek_yoy) / $rek->ureg_rek_yoy) * 100
                    : 0;
                $ureg_fin_yoy_pct = $fin->ureg_fin_yoy > 0
                    ? (($fin->ureg_fin_curr - $fin->ureg_fin_yoy) / $fin->ureg_fin_yoy) * 100
                    : 0;

                $data[] = [
                    'branch' => $branchRaw,
                    'ureg_rekening' => [
                        'curr'     => $rek->ureg_rek_curr,
                        'yoy_prev' => $rek->ureg_rek_yoy,
                        'dec'      => $rek->ureg_rek_ytd,
                        'prev'     => $rek->ureg_rek_mtd,
                        'mtd'      => $rek->ureg_rek_curr - $rek->ureg_rek_mtd,
                        'ytd'      => $rek->ureg_rek_curr - $rek->ureg_rek_ytd,
                        'yoy'      => $rek->ureg_rek_curr - $rek->ureg_rek_yoy,
                        'yoy_pct'  => round($ureg_rek_yoy_pct, 1),
                        'mtd_pct'  => $rek->ureg_rek_mtd > 0 ? (($rek->ureg_rek_curr - $rek->ureg_rek_mtd) / $rek->ureg_rek_mtd) * 100 : 0,
                    ],
                    'ureg_finansial' => [
                        'curr'     => $fin->ureg_fin_curr,
                        'yoy_prev' => $fin->ureg_fin_yoy,
                        'dec'      => $fin->ureg_fin_ytd,
                        'prev'     => $fin->ureg_fin_mtd,
                        'mtd'      => $fin->ureg_fin_curr - $fin->ureg_fin_mtd,
                        'ytd'      => $fin->ureg_fin_curr - $fin->ureg_fin_ytd,
                        'yoy'      => $fin->ureg_fin_curr - $fin->ureg_fin_yoy,
                        'yoy_pct'  => round($ureg_fin_yoy_pct, 1),
                        'mtd_pct'  => $fin->ureg_fin_mtd > 0 ? (($fin->ureg_fin_curr - $fin->ureg_fin_mtd) / $fin->ureg_fin_mtd) * 100 : 0,
                    ],
                    'usak'       => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                    'volume_trx' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                ];


                // 🔥 FIX: Akumulasi nilai RAW (bukan growth) agar total YoY% bisa dihitung benar
            $raw_totals['ureg_rekening']['curr'] += $rek->ureg_rek_curr;
                $raw_totals['ureg_rekening']['yoy_prev'] = $rek->ureg_rek_yoy;
                $raw_totals['ureg_rekening']['dec']  += $rek->ureg_rek_ytd;
                $raw_totals['ureg_rekening']['prev']  += $rek->ureg_rek_mtd;
                $raw_totals['ureg_rekening']['mtd_raw']  += $rek->ureg_rek_mtd;
                $raw_totals['ureg_rekening']['ytd_raw']  += $rek->ureg_rek_ytd;
                $raw_totals['ureg_rekening']['yoy_raw']  += $rek->ureg_rek_yoy;

                $raw_totals['ureg_finansial']['curr'] += $fin->ureg_fin_curr;
                $raw_totals['ureg_finansial']['yoy_prev'] = $fin->ureg_fin_yoy;
                $raw_totals['ureg_finansial']['dec']  += $fin->ureg_fin_ytd;
                $raw_totals['ureg_finansial']['prev']  += $fin->ureg_fin_mtd;
                $raw_totals['ureg_finansial']['mtd_raw']  += $fin->ureg_fin_mtd;
                $raw_totals['ureg_finansial']['ytd_raw']  += $fin->ureg_fin_ytd;
            }

            // 🔥 FIX: Hitung total YoY% dari raw totals — aman dari division by zero
            $tot_rek_yoy_pct = $raw_totals['ureg_rekening']['yoy'] > 0
                ? (($raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['yoy']) / $raw_totals['ureg_rekening']['yoy']) * 100
                : 0;
            $tot_fin_yoy_pct = $raw_totals['ureg_finansial']['yoy'] > 0
                ? (($raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['yoy']) / $raw_totals['ureg_finansial']['yoy']) * 100
                : 0;

            return response()->json([
                'status' => 'success',
                'data'   => $data,
                'labels' => $labels,
                'total'  => [
                    'branch' => 'TOTAL AREA 6',
                    'ureg_rekening' => [
                        'curr'    => $raw_totals['ureg_rekening']['curr'],
                        'mtd'     => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['mtd'],
                        'ytd'     => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['ytd'],
                        'yoy'     => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['yoy'],
                        'yoy_pct' => round($tot_rek_yoy_pct, 1),
                    ],
                    'ureg_finansial' => [
                        'curr'    => $raw_totals['ureg_finansial']['curr'],
                        'mtd'     => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['mtd'],
                        'ytd'     => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['ytd'],
                        'yoy'     => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['yoy'],
                        'yoy_pct' => round($tot_fin_yoy_pct, 1),
                    ],
                    'usak'       => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                    'volume_trx' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                ]
            ]);
        }
    }

    private function buildBranchAliasMap(array $branches): array
    {
        $map = [];

        foreach ($branches as $branch) {
            $label = strtoupper(trim((string) $branch));
            if ($label === '') {
                continue;
            }

            $label = preg_replace('/\s+/', ' ', $label) ?? $label;
            $base = preg_replace('/^(KC|KCP)\s+/i', '', $label) ?? $label;
            $base = trim($base);

            $aliases = [$label, $base];
            if ($base !== '') {
                $aliases[] = 'KC ' . $base;
                $aliases[] = 'KCP ' . $base;
            }

            $aliases = array_values(array_unique(array_filter(array_map(function ($item) {
                $normalized = strtoupper(trim((string) $item));
                return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
            }, $aliases))));

            $canonical = in_array($label, ['KC ' . $base, 'KCP ' . $base], true) ? $label : ('KC ' . $base);
            $map[$canonical] = $aliases;
        }

        if (empty($map)) {
            $defaults = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
            foreach ($defaults as $branch) {
                $base = preg_replace('/^(KC|KCP)\s+/i', '', $branch) ?? $branch;
                $base = trim($base);
                $map[$branch] = array_values(array_unique([$branch, $base, 'KCP ' . $base]));
            }
        }

        return $map;
    }

    private function buildBrilinkFilterOptions(): array
    {
        $rows = collect([
            DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                ->selectRaw('TRIM(cabang) as branch_name')
                ->selectRaw('TRIM(uker) as uker_name')
                ->whereNotNull('cabang')
                ->whereNotNull('uker')
                ->whereRaw("TRIM(cabang) <> ''")
                ->whereRaw("TRIM(uker) <> ''")
                ->get(),
            DB::table('casa_brilink_web')
                ->selectRaw('TRIM(mbdesc) as branch_name')
                ->selectRaw('TRIM(brdesc) as uker_name')
                ->whereNotNull('mbdesc')
                ->whereNotNull('brdesc')
                ->whereRaw("TRIM(mbdesc) <> ''")
                ->whereRaw("TRIM(brdesc) <> ''")
                ->get(),
            DB::table('casa_brilink_edc')
                ->selectRaw('TRIM(mbdesc) as branch_name')
                ->selectRaw('TRIM(brdesc) as uker_name')
                ->whereNotNull('mbdesc')
                ->whereNotNull('brdesc')
                ->whereRaw("TRIM(mbdesc) <> ''")
                ->whereRaw("TRIM(brdesc) <> ''")
                ->get(),
        ])->flatten(1)
            ->map(function ($row) {
                $row->branch_name = strtoupper(trim((string) ($row->branch_name ?? '')));
                $row->uker_name = strtoupper(trim((string) ($row->uker_name ?? '')));
                return $row;
            })
            ->filter(function ($row) {
                return $row->branch_name !== '' && $row->uker_name !== '';
            })
            ->unique(fn ($row) => $row->branch_name . '|' . $row->uker_name)
            ->sortBy([
                ['branch_name', 'asc'],
                ['uker_name', 'asc'],
            ])
            ->values();

        return [
            'branchOptions' => $rows
                ->pluck('branch_name')
                ->filter()
                ->unique()
                ->values(),
            'branchUkerMap' => $rows
                ->groupBy('branch_name')
                ->map(function ($items) {
                    return $items->pluck('uker_name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
            }),
        ];
    }

    private function getBrilinkUkersForBranches(array $selectedBranches, $branchUkerMap = null): array
    {
        $selectedBranches = collect($selectedBranches)
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();

        if (empty($selectedBranches)) {
            return [];
        }

        $branchUkerMap = $branchUkerMap ?? ($this->buildBrilinkFilterOptions()['branchUkerMap'] ?? collect());

        return collect($selectedBranches)
            ->flatMap(function ($branch) use ($branchUkerMap) {
                return $branchUkerMap[$branch] ?? [];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCanonicalBranchKey(array $branchAliasMap, string $rawBranchKey): string
    {
        $candidate = strtoupper(trim($rawBranchKey));
        $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;

        foreach ($branchAliasMap as $canonical => $aliases) {
            if (in_array($candidate, $aliases, true)) {
                return $canonical;
            }
        }

        return $candidate;
    }

    private function rkaLookupService(): RkaLookupService
    {
        return app(RkaLookupService::class);
    }

    private function resolveRkaMonthColumn(Carbon $selectedDate): string
    {
        return $this->rkaLookupService()->resolveMonthColumn($selectedDate);
    }

    private function resolveRkaMonthLabel(Carbon $selectedDate): string
    {
        return $this->rkaLookupService()->resolveMonthLabel($selectedDate);
    }

    private function calculateNewPayrollMetrics($curr, $prev, $yoyPrev): array
    {
        $curr = (float) ($curr ?? 0);
        $prev = (float) ($prev ?? 0);
        $yoyPrev = (float) ($yoyPrev ?? 0);

        $yoy = $curr - $yoyPrev;
        $yoyPct = $yoyPrev != 0.0 ? ($yoy / $yoyPrev) * 100 : null;
        $pencPct = null;

        return [
            'curr' => $curr,
            'prev' => $prev,
            'yoy_prev' => $yoyPrev,
            'yoy' => $yoy,
            'yoy_pct' => $yoyPct,
            'rka' => null,
            'penc_pct' => $pencPct,
        ];
    }

    private function emptyNewPayrollMetric(): array
    {
        return [
            'curr' => null,
            'prev' => null,
            'yoy_prev' => null,
            'yoy' => null,
            'yoy_pct' => null,
            'rka' => null,
            'penc_pct' => null,
        ];
    }

    private function buildEmptyNewPayrollTotal(): array
    {
        return [
            'branch' => 'TOTAL AREA 6',
            'rekening' => $this->calculateNewPayrollMetrics(0, 0, 0),
            'saldo' => $this->calculateNewPayrollMetrics(0, 0, 0),
            'kualitas' => $this->emptyNewPayrollMetric(),
        ];
    }

    private function buildNewPayrollLabels(Carbon $selectedDate): array
    {
        $curr = $selectedDate->copy();
        $prev = $selectedDate->copy()->subMonthNoOverflow();
        $yoy = $selectedDate->copy()->subYearNoOverflow();

        return [
            'curr' => $curr->format('M-y'),
            'prev' => $prev->format('M-y'),
            'yoy_prev' => $yoy->format('M-y'),
            'rka' => 'RKA ' . $curr->format('M') . ' - ' . $curr->format('y'),
        ];
    }
}
