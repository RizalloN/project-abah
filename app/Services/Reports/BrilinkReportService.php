<?php

namespace App\Services\Reports;

use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk tab laporan Brilink.
 * Menangani query dan helper data untuk tab laporan Brilink.
 */
class BrilinkReportService
{
    public function __construct(
        private readonly RkaLookupService $rkaLookup,
        private readonly ReportFilterService $filterService
    ) {}

    /**
     * Handle request fetch data Brilink dan kembalikan JsonResponse.
     */
    public function handle(Request $request): JsonResponse
    {
        $bulanInput = $request->input('periode_bulan');

        if (!$bulanInput) {
            return response()->json(['status' => 'error', 'msg' => 'Periode kosong']);
        }

        // Parser flexible + kunci locale EN
        if (preg_match('/^\d{4}-\d{2}$/', $bulanInput)) {
            $current = Carbon::createFromFormat('Y-m', $bulanInput)->startOfMonth()->locale('en');
        } else {
            $current = Carbon::createFromFormat('F Y', $bulanInput)->startOfMonth()->locale('en');
        }

        $defaultBranches  = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($b) => trim((string) $b))->filter()->values()->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($u) => trim((string) $u))->filter()->values()->all();

        $branches         = !empty($selectedBranches) ? $selectedBranches : $defaultBranches;
        $isBranchFiltered = !empty($selectedBranches);
        $groupLabel       = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';

        // Split branches for RKA lookup: direct (Ponorogo) vs regional patterns (Madiun, Magetan, Ngawi)
        $rkaDirectBranches = [];
        $rkaRegionalPatterns = [];
        foreach ($branches as $branch) {
            $branchUpper = strtoupper(trim($branch));
            if ($branchUpper === 'KC PONOROGO') {
                $rkaDirectBranches[] = 'KC PONOROGO';
                continue;
            }
            $rkaRegionalPatterns[] = strtoupper(str_replace('KC ', '', $branchUpper));
        }

        $prevMonth         = $current->copy()->subMonth()->locale('en');
        $lastYearSameMonth = $current->copy()->subYear()->locale('en');
        $lastYearEnd       = Carbon::create($current->year - 1, 12, 1)->locale('en');

        $periodeCurr = $current->format('F Y');
        $periodePrev = $prevMonth->format('F Y');
        $periodeYoY  = $lastYearSameMonth->format('F Y');
        $periodeYtD  = $lastYearEnd->format('F Y');

        $brilinkFilterOptions = $this->filterService->buildBrilinkFilterOptions();
        $brilinkBranchUkerMap = $brilinkFilterOptions['branchUkerMap'] ?? collect();

        $displayItems = !empty($selectedUkers)
            ? $selectedUkers
            : ($isBranchFiltered ? $this->getUkersForBranches($branches, $brilinkBranchUkerMap) : $branches);

        $displayColumn     = $isBranchFiltered ? 'uker' : 'cabang';
        $casaDisplayColumn = $isBranchFiltered ? 'brdesc' : 'mbdesc';

        $brilinkRkaMonthColumn = $this->rkaLookup->resolveMonthColumn($current);
        $brilinkRkaMonthLabel  = $this->rkaLookup->resolveMonthLabel($current);
        $brilinkRkaGroups      = $this->buildSplitRkaGroups(
            [
                'agen'    => ['mata_anggaran' => ['Jumlah Agen Brilink']],
                'juragan' => ['mata_anggaran' => ['Jumlah Agen Brilink Jawara', 'Jumlah Agen Brilink Juragan']],
                'bep'     => ['mata_anggaran' => ['Jumlah Agen Brilink yang BEP']],
            ],
            $brilinkRkaMonthColumn,
            $branches,
            $isBranchFiltered,
            $selectedUkers,
            $rkaDirectBranches,
            $rkaRegionalPatterns
        );

        // CASA period resolution
        $selectedCasaDate   = $current->copy()->endOfMonth();
        $latestCasaWeb      = DB::table('casa_brilink_web')->whereDate('periode', '<=', $selectedCasaDate->toDateString())->max('periode');
        $latestCasaEdc      = DB::table('casa_brilink_edc')->whereDate('periode', '<=', $selectedCasaDate->toDateString())->max('periode');
        $latestCasaCandidates = array_filter([$latestCasaWeb, $latestCasaEdc]);
        $effectiveCasaDate  = !empty($latestCasaCandidates) ? Carbon::parse(max($latestCasaCandidates)) : $selectedCasaDate->copy();
        $casaPrevDate       = $effectiveCasaDate->copy()->subMonthNoOverflow()->endOfMonth();
        $casaYoyDate        = $effectiveCasaDate->copy()->subYearNoOverflow()->endOfMonth();
        $casaYtdDate        = Carbon::create($effectiveCasaDate->year - 1, 12, 1)->endOfMonth();

        $branchAliasMap  = $this->buildBranchAliasMap($branches);
        $branchLookupKeys = array_values(array_unique(array_merge(...array_values($branchAliasMap))));

        $fetchCasaByPeriod = function (Carbon $period) use ($branchLookupKeys, $branchAliasMap, $isBranchFiltered, $selectedUkers, $casaDisplayColumn) {
            $webRows = DB::table('casa_brilink_web')
                ->selectRaw("UPPER(TRIM($casaDisplayColumn)) as branch")
                ->selectRaw('SUM(COALESCE(jml_nominal_casa, 0)) as total_nominal')
                ->whereDate('periode', $period->toDateString())
                ->whereIn(DB::raw('UPPER(TRIM(mbdesc))'), $branchLookupKeys)
                ->when($isBranchFiltered && !empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw("UPPER(TRIM($casaDisplayColumn))"), $selectedUkers))
                ->groupBy(DB::raw("UPPER(TRIM($casaDisplayColumn))"))
                ->get();

            $edcRows = DB::table('casa_brilink_edc')
                ->selectRaw("UPPER(TRIM($casaDisplayColumn)) as branch")
                ->selectRaw('SUM(COALESCE(jml_nominal_casa, 0)) as total_nominal')
                ->whereDate('periode', $period->toDateString())
                ->whereIn(DB::raw('UPPER(TRIM(mbdesc))'), $branchLookupKeys)
                ->when($isBranchFiltered && !empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw("UPPER(TRIM($casaDisplayColumn))"), $selectedUkers))
                ->groupBy(DB::raw("UPPER(TRIM($casaDisplayColumn))"))
                ->get();

            $merged = [];
            foreach ($webRows as $row) {
                $canonical = $this->resolveCanonicalBranchKey($branchAliasMap, strtoupper(trim((string) $row->branch)));
                $merged[$canonical] = ($merged[$canonical] ?? 0) + (float) $row->total_nominal;
            }
            foreach ($edcRows as $row) {
                $canonical = $this->resolveCanonicalBranchKey($branchAliasMap, strtoupper(trim((string) $row->branch)));
                $merged[$canonical] = ($merged[$canonical] ?? 0) + (float) $row->total_nominal;
            }

            return $merged;
        };

        $casaCurrMap = $fetchCasaByPeriod($effectiveCasaDate);
        $casaPrevMap = $fetchCasaByPeriod($casaPrevDate);
        $casaYtdMap  = $fetchCasaByPeriod($casaYtdDate);
        $casaYoyMap  = $fetchCasaByPeriod($casaYoyDate);

        // Brilink main data — single query untuk semua periode (curr, prev, yoy, ytd)
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
            ->when($isBranchFiltered && !empty($selectedUkers), fn ($q) => $q->whereIn(DB::raw("UPPER(TRIM($displayColumn))"), $selectedUkers))
            ->groupBy('periode', DB::raw("UPPER(TRIM($displayColumn))"))
            ->get();

        $brilinkMap = [];
        foreach ($brilinkRows as $row) {
            $period   = (string) ($row->periode ?? '');
            $branchKey = $this->resolveCanonicalBranchKey($branchAliasMap, strtoupper(trim((string) ($row->branch ?? ''))));
            $brilinkMap[$period][$branchKey] = [
                'agen'    => (int) ($row->agen ?? 0),
                'juragan' => (int) ($row->juragan ?? 0),
                'bep'     => (int) ($row->bep ?? 0),
                'trx'     => (float) ($row->trx ?? 0),
                'volume'  => (float) ($row->volume ?? 0),
            ];
        }

        $data   = [];
        $totals = [
            'agen'    => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            'juragan' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            'bep'     => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            'trx'     => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            'volume'  => ['curr' => 0, 'mtd' => 0, 'yoy' => 0],
            'casa'    => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
        ];

        foreach ($displayItems as $branch) {
            $branchKey = $this->resolveCanonicalBranchKey($branchAliasMap, strtoupper(trim((string) $branch)));
            $currData  = $brilinkMap[$periodeCurr][$branchKey] ?? null;
            $prevData  = $brilinkMap[$periodePrev][$branchKey] ?? null;
            $yoyData   = $brilinkMap[$periodeYoY][$branchKey] ?? null;
            $ytdData   = $brilinkMap[$periodeYtD][$branchKey] ?? null;
            $hasCurrData = $currData !== null;

            $agen_curr = (int) ($currData['agen'] ?? 0); $agen_prev = (int) ($prevData['agen'] ?? 0); $agen_yoy = (int) ($yoyData['agen'] ?? 0); $agen_ytd = (int) ($ytdData['agen'] ?? 0);
            $juragan_curr = (int) ($currData['juragan'] ?? 0); $juragan_prev = (int) ($prevData['juragan'] ?? 0); $juragan_yoy = (int) ($yoyData['juragan'] ?? 0); $juragan_ytd = (int) ($ytdData['juragan'] ?? 0);
            $bep_curr = (int) ($currData['bep'] ?? 0); $bep_prev = (int) ($prevData['bep'] ?? 0); $bep_yoy = (int) ($yoyData['bep'] ?? 0); $bep_ytd = (int) ($ytdData['bep'] ?? 0);
            $trx_curr = (float) ($currData['trx'] ?? 0); $trx_prev = (float) ($prevData['trx'] ?? 0); $trx_yoy = (float) ($yoyData['trx'] ?? 0); $trx_ytd = (float) ($ytdData['trx'] ?? 0);
            $vol_curr = (float) ($currData['volume'] ?? 0); $vol_prev = (float) ($prevData['volume'] ?? 0); $vol_yoy = (float) ($yoyData['volume'] ?? 0);

            $casa_curr = (float) ($casaCurrMap[$branchKey] ?? 0);
            $casa_prev = (float) ($casaPrevMap[$branchKey] ?? 0);
            $casa_ytd  = (float) ($casaYtdMap[$branchKey] ?? 0);
            $casa_yoy  = (float) ($casaYoyMap[$branchKey] ?? 0);
            $casa_has_curr = $casa_curr > 0 || $casa_prev > 0 || $casa_ytd > 0 || $casa_yoy > 0;

            $agenRka    = round((float) ($brilinkRkaGroups['agen'][$branchKey] ?? 0), 2);
            $juraganRka = round((float) ($brilinkRkaGroups['juragan'][$branchKey] ?? 0), 2);
            $bepRka     = round((float) ($brilinkRkaGroups['bep'][$branchKey] ?? 0), 2);

            $data[] = [
                'branch'  => $branch,
                'agen'    => ['curr' => $agen_curr, 'mtd' => $hasCurrData ? ($agen_curr - $agen_prev) : 0, 'ytd' => $hasCurrData ? ($agen_curr - $agen_ytd) : 0, 'yoy' => $hasCurrData ? ($agen_curr - $agen_yoy) : 0, 'rka' => $agenRka, 'penc_pct' => $agenRka > 0 ? round(($agen_curr / $agenRka) * 100, 2) : 0],
                'juragan' => ['curr' => $juragan_curr, 'mtd' => $hasCurrData ? ($juragan_curr - $juragan_prev) : 0, 'ytd' => $hasCurrData ? ($juragan_curr - $juragan_ytd) : 0, 'yoy' => $hasCurrData ? ($juragan_curr - $juragan_yoy) : 0, 'rka' => $juraganRka, 'penc_pct' => $juraganRka > 0 ? round(($juragan_curr / $juraganRka) * 100, 2) : 0],
                'bep'     => ['curr' => $bep_curr, 'mtd' => $hasCurrData ? ($bep_curr - $bep_prev) : 0, 'ytd' => $hasCurrData ? ($bep_curr - $bep_ytd) : 0, 'yoy' => $hasCurrData ? ($bep_curr - $bep_yoy) : 0, 'rka' => $bepRka, 'penc_pct' => $bepRka > 0 ? round(($bep_curr / $bepRka) * 100, 2) : 0],
                'trx'     => ['curr' => $trx_curr, 'mtd' => $hasCurrData ? ($trx_curr - $trx_prev) : 0, 'ytd' => $hasCurrData ? ($trx_curr - $trx_ytd) : 0, 'yoy' => $hasCurrData ? ($trx_curr - $trx_yoy) : 0],
                'volume'  => ['curr' => $vol_curr, 'mtd' => $hasCurrData ? ($vol_curr - $vol_prev) : 0, 'yoy' => $hasCurrData ? ($vol_curr - $vol_yoy) : 0],
                'casa'    => ['curr' => round($casa_curr, 2), 'mtd' => $casa_has_curr ? round($casa_curr - $casa_prev, 2) : 0, 'ytd' => $casa_has_curr ? round($casa_curr - $casa_ytd, 2) : 0, 'yoy' => $casa_has_curr ? round($casa_curr - $casa_yoy, 2) : 0],
            ];

            $totals['agen']['curr']    += $agen_curr;    $totals['agen']['mtd']    += ($hasCurrData ? $agen_curr - $agen_prev : 0);    $totals['agen']['ytd']    += ($hasCurrData ? $agen_curr - $agen_ytd : 0);    $totals['agen']['yoy']    += ($hasCurrData ? $agen_curr - $agen_yoy : 0);
            $totals['juragan']['curr'] += $juragan_curr; $totals['juragan']['mtd'] += ($hasCurrData ? $juragan_curr - $juragan_prev : 0); $totals['juragan']['ytd'] += ($hasCurrData ? $juragan_curr - $juragan_ytd : 0); $totals['juragan']['yoy'] += ($hasCurrData ? $juragan_curr - $juragan_yoy : 0);
            $totals['bep']['curr']     += $bep_curr;     $totals['bep']['mtd']     += ($hasCurrData ? $bep_curr - $bep_prev : 0);       $totals['bep']['ytd']     += ($hasCurrData ? $bep_curr - $bep_ytd : 0);       $totals['bep']['yoy']     += ($hasCurrData ? $bep_curr - $bep_yoy : 0);
            $totals['trx']['curr']     += $trx_curr;     $totals['trx']['mtd']     += ($hasCurrData ? $trx_curr - $trx_prev : 0);       $totals['trx']['ytd']     += ($hasCurrData ? $trx_curr - $trx_ytd : 0);       $totals['trx']['yoy']     += ($hasCurrData ? $trx_curr - $trx_yoy : 0);
            $totals['volume']['curr']  += $vol_curr;     $totals['volume']['mtd']  += ($hasCurrData ? $vol_curr - $vol_prev : 0);        $totals['volume']['yoy']  += ($hasCurrData ? $vol_curr - $vol_yoy : 0);
            $totals['casa']['curr']    += $casa_curr;    $totals['casa']['mtd']    += ($casa_has_curr ? $casa_curr - $casa_prev : 0);    $totals['casa']['ytd']    += ($casa_has_curr ? $casa_curr - $casa_ytd : 0);    $totals['casa']['yoy']    += ($casa_has_curr ? $casa_curr - $casa_yoy : 0);
            $totals['agen']['rka']    = round((float) ($totals['agen']['rka'] ?? 0) + $agenRka, 2);
            $totals['juragan']['rka'] = round((float) ($totals['juragan']['rka'] ?? 0) + $juraganRka, 2);
            $totals['bep']['rka']     = round((float) ($totals['bep']['rka'] ?? 0) + $bepRka, 2);
        }

        $totals['agen']['penc_pct']    = ($totals['agen']['rka'] ?? 0) > 0 ? round(($totals['agen']['curr'] / $totals['agen']['rka']) * 100, 2) : 0;
        $totals['juragan']['penc_pct'] = ($totals['juragan']['rka'] ?? 0) > 0 ? round(($totals['juragan']['curr'] / $totals['juragan']['rka']) * 100, 2) : 0;
        $totals['bep']['penc_pct']     = ($totals['bep']['rka'] ?? 0) > 0 ? round(($totals['bep']['curr'] / $totals['bep']['rka']) * 100, 2) : 0;

        return response()->json([
            'status'      => 'success',
            'data'        => $data,
            'group_label' => $groupLabel,
            'labels'      => [
                'curr'     => $periodeCurr,
                'rka'      => $brilinkRkaMonthLabel,
                'casa_curr' => $effectiveCasaDate->translatedFormat("M'y"),
                'casa_dec'  => $casaYtdDate->translatedFormat("M'y"),
                'casa_prev' => $casaPrevDate->translatedFormat('d-M'),
                'casa_end'  => $effectiveCasaDate->translatedFormat('d-M'),
            ],
            'total' => [
                'branch'  => 'TOTAL AREA 6',
                'agen'    => $totals['agen'],
                'juragan' => $totals['juragan'],
                'bep'     => $totals['bep'],
                'trx'     => $totals['trx'],
                'volume'  => $totals['volume'],
                'casa'    => ['curr' => round($totals['casa']['curr'], 2), 'mtd' => round($totals['casa']['mtd'], 2), 'ytd' => round($totals['casa']['ytd'], 2), 'yoy' => round($totals['casa']['yoy'], 2)],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    public function buildBranchAliasMap(array $branches): array
    {
        $map = [];
        foreach ($branches as $branch) {
            $label = strtoupper(trim((string) $branch));
            if ($label === '') {
                continue;
            }
            $label   = preg_replace('/\s+/', ' ', $label) ?? $label;
            $base    = preg_replace('/^(KC|KCP)\s+/i', '', $label) ?? $label;
            $base    = trim($base);
            $aliases = array_values(array_unique(array_filter(array_map(function ($item) {
                $n = strtoupper(trim((string) $item));
                return preg_replace('/\s+/', ' ', $n) ?? $n;
            }, [$label, $base, 'KC ' . $base, 'KCP ' . $base]))));

            $canonical = in_array($label, ['KC ' . $base, 'KCP ' . $base], true) ? $label : ('KC ' . $base);
            $map[$canonical] = $aliases;
        }

        if (empty($map)) {
            foreach (['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'] as $branch) {
                $base = trim(preg_replace('/^(KC|KCP)\s+/i', '', $branch) ?? $branch);
                $map[$branch] = array_values(array_unique([$branch, $base, 'KCP ' . $base]));
            }
        }

        return $map;
    }

    public function resolveCanonicalBranchKey(array $branchAliasMap, string $rawBranchKey): string
    {
        $candidate = preg_replace('/\s+/', ' ', strtoupper(trim($rawBranchKey))) ?? strtoupper(trim($rawBranchKey));
        foreach ($branchAliasMap as $canonical => $aliases) {
            if (in_array($candidate, $aliases, true)) {
                return $canonical;
            }
        }
        return $candidate;
    }

    private function getUkersForBranches(array $selectedBranches, $branchUkerMap): array
    {
        return collect($selectedBranches)
            ->flatMap(fn ($branch) => $branchUkerMap[$branch] ?? [])
            ->filter()->unique()->values()->all();
    }

    /**
     * Build RKA groups using split filtering: direct kanca (Ponorogo) + regional patterns (Madiun, Magetan, Ngawi)
     */
    private function buildSplitRkaGroups(
        array $definitions,
        string $monthColumn,
        array $branches,
        bool $isBranchFiltered,
        array $selectedUkers,
        array $directBranches,
        array $regionalPatterns
    ): array {
        $groups = [];

        $upperSelectedUkers = array_map('strtoupper', $selectedUkers);
        foreach ($definitions as $defKey => $def) {
            $groups[$defKey] = [];
        }

        // Get direct RKA only when selected scope contains KC Ponorogo.
        if (!empty($directBranches)) {
            $directGroups = $this->rkaLookup->aggregateByGroup(
                $definitions,
                $monthColumn,
                $directBranches,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'kanca'
            );

            foreach ($definitions as $defKey => $def) {
                $groups[$defKey] = $directGroups[$defKey] ?? [];
            }
        }

        // Get regional RKA if there are regional patterns
        if (!empty($regionalPatterns)) {
            $regionalGroups = $this->rkaLookup->aggregateByGroupWithRegionalFilter(
                $definitions,
                $monthColumn,
                $regionalPatterns,
                null,
                $upperSelectedUkers,
                $isBranchFiltered ? 'uker' : 'region'
            );

            foreach ($definitions as $defKey => $def) {
                if (isset($regionalGroups[$defKey])) {
                    foreach ($regionalGroups[$defKey] as $groupKey => $value) {
                        $resultKey = $isBranchFiltered ? $groupKey : ('KC ' . $groupKey);
                        $groups[$defKey][$resultKey] = $value;
                    }
                }
            }
        }

        return $groups;
    }
}
