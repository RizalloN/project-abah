<?php

namespace App\Services\Reports;

use App\Support\RkaLookupService;
use App\Support\ReportCacheVersion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk tab laporan QRIS (qris, qris_mom).
 * Dipisahkan dari controller lama agar query laporan mudah di-maintain dan diuji.
 */
class QrisReportService
{
    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    /**
     * Handle request fetch data QRIS, delegasikan ke handler tab yang sesuai.
     */
    public function handle(Request $request): JsonResponse
    {
        $ctx = $this->parseContext($request);
        $tab = $request->input('tab', 'qris');

        return match ($tab) {
            'qris_mom' => $this->handleQrisMom($ctx),
            default    => $this->handleQris($ctx),
        };
    }

    // -------------------------------------------------------------------------
    // Context Parser
    // -------------------------------------------------------------------------

    private function parseContext(Request $request): array
    {
        $defaultBranches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($b) => trim((string) $b))->filter()->values()->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($u) => trim((string) $u))
            ->filter()->reject(fn ($u) => strtoupper($u) === 'ALL UKER')
            ->values()->all();

        $branches             = !empty($selectedBranches) ? $selectedBranches : $defaultBranches;
        $isQrisBranchFiltered = !empty($selectedBranches);
        $qrisGroupColumn      = $isQrisBranchFiltered ? 'BRDESC' : 'MBDESC';
        $qrisGroupLabel       = $isQrisBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $qrisTotalLabel       = !empty($selectedBranches)
            ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
            : 'TOTAL AREA 6';
        $upperBranches        = array_map('strtoupper', $branches);
        $upperSelectedUkers   = array_map('strtoupper', $selectedUkers);

        // Split branches for RKA lookup: direct (Ponorogo) vs regional patterns (Madiun, Magetan, Ngawi)
        $rkaDirectBranches = [];
        $rkaRegionalPatterns = [];
        foreach ($branches as $branch) {
            $branchUpper = strtoupper(trim($branch));
            if ($branchUpper === 'KC PONOROGO') {
                $rkaDirectBranches[] = 'KC PONOROGO';
                continue; // Already in direct
            }
            $rkaRegionalPatterns[] = strtoupper(str_replace('KC ', '', $branchUpper)); // MADIUN, MAGETAN, NGAWI
        }

        $posisi       = $request->input('posisi', date('Y-m-d'));
        $selectedDate = Carbon::parse($posisi);

        $dateCurr    = $selectedDate->copy()->toDateString();
        $dateMtD     = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $dateYtD     = $selectedDate->copy()->subYearNoOverflow()->endOfYear()->toDateString();
        $dateYoY     = $selectedDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString();
        $datePrevMoM = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $rkaMonthColumn = $this->rkaLookup->resolveMonthColumn($selectedDate);
        $rkaMonthLabel  = $this->rkaLookup->resolveMonthLabel($selectedDate);

        $labels = [
            'curr'     => Carbon::parse($dateCurr)->translatedFormat('d M y'),
            'mtd'      => Carbon::parse($dateMtD)->translatedFormat('d M y'),
            'ytd'      => Carbon::parse($dateYtD)->translatedFormat('d M y'),
            'yoy'      => Carbon::parse($dateYoY)->translatedFormat('d M y'),
            'prev_mom' => Carbon::parse($datePrevMoM)->translatedFormat('d M y'),
            'rka'      => $rkaMonthLabel,
        ];

        return compact(
            'branches', 'isQrisBranchFiltered', 'qrisGroupColumn', 'qrisGroupLabel',
            'qrisTotalLabel', 'upperBranches', 'upperSelectedUkers', 'selectedUkers',
            'dateCurr', 'dateMtD', 'dateYtD', 'dateYoY', 'datePrevMoM',
            'rkaMonthColumn', 'rkaMonthLabel', 'labels', 'rkaDirectBranches', 'rkaRegionalPatterns'
        );
    }

    private function applyQrisFilter($query, array $ctx, string $branchColumn): void
    {
        if (!empty($ctx['upperBranches'])) {
            $query->whereIn(DB::raw('UPPER(MBDESC)'), $ctx['upperBranches']);
        }
        if ($ctx['isQrisBranchFiltered']) {
            $query->whereNotNull($branchColumn)->whereRaw("TRIM($branchColumn) <> ''");
        }
        if (!empty($ctx['selectedUkers'])) {
            $query->whereIn(DB::raw("UPPER(TRIM($branchColumn))"), $ctx['upperSelectedUkers']);
        }
    }

    private function applyQrisDateScope($query, array $dates): void
    {
        $normalizedDates = array_values(array_unique(array_filter(array_map(static function ($date): string {
            return trim((string) $date);
        }, $dates))));

        if (!empty($normalizedDates)) {
            $query->whereIn('POSISI', $normalizedDates);
        }
    }

    // -------------------------------------------------------------------------
    // Tab Handlers
    // -------------------------------------------------------------------------

    private function handleQris(array $ctx): JsonResponse
    {
        $cacheKey = 'qris_report:qris:' . sha1(json_encode([
            'cacheVersion' => ReportCacheVersion::get(),
            'dateCurr' => $ctx['dateCurr'],
            'dateMtD' => $ctx['dateMtD'],
            'dateYtD' => $ctx['dateYtD'],
            'dateYoY' => $ctx['dateYoY'],
            'branches' => $ctx['upperBranches'],
            'ukers' => $ctx['upperSelectedUkers'],
            'branchFiltered' => $ctx['isQrisBranchFiltered'],
            'groupColumn' => $ctx['qrisGroupColumn'],
            'monthColumn' => $ctx['rkaMonthColumn'],
            'rkaLookup' => 'kanca-summary-fallback-v1',
        ]));

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($ctx) {
            $qrisRkaGroups = $this->buildSplitRkaGroups(
                [
                    'jml'  => ['mata_anggaran' => ['User QRIS']],
                    'prod' => ['mata_anggaran' => ['Jumlah QRIS yang Produktif']],
                    'vol'  => ['mata_anggaran' => ['Sales Volume QRIS']],
                ],
                $ctx
            );

            $dataRows = DB::table('jumlah_merchant_qris_detail')
                ->select(DB::raw("UPPER({$ctx['qrisGroupColumn']}) as branch"))
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as jml_curr', [$ctx['dateCurr']])
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as jml_mtd', [$ctx['dateMtD']])
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as jml_ytd', [$ctx['dateYtD']])
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as jml_yoy', [$ctx['dateYoY']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI = ? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_curr", [$ctx['dateCurr']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI = ? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_mtd", [$ctx['dateMtD']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI = ? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_ytd", [$ctx['dateYtD']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI = ? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_yoy", [$ctx['dateYoY']])
                ->selectRaw("SUM(CASE WHEN POSISI = ? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_curr", [$ctx['dateCurr']])
                ->selectRaw("SUM(CASE WHEN POSISI = ? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_mtd", [$ctx['dateMtD']])
                ->selectRaw("SUM(CASE WHEN POSISI = ? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_ytd", [$ctx['dateYtD']])
                ->selectRaw("SUM(CASE WHEN POSISI = ? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_yoy", [$ctx['dateYoY']]);
            $this->applyQrisFilter($dataRows, $ctx, 'BRDESC');
            $this->applyQrisDateScope($dataRows, [$ctx['dateCurr'], $ctx['dateMtD'], $ctx['dateYtD'], $ctx['dateYoY']]);
            $dataRows = $dataRows->groupByRaw("UPPER({$ctx['qrisGroupColumn']})")->get()->keyBy('branch');

            $data   = [];
            $totals = ['jml_curr' => 0, 'jml_mtd' => 0, 'jml_ytd' => 0, 'jml_yoy' => 0, 'prod_curr' => 0, 'prod_mtd' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0, 'vol_curr' => 0, 'vol_mtd' => 0, 'vol_ytd' => 0, 'vol_yoy' => 0];
            $totalJmlRka = 0.0;
            $totalProdRka = 0.0;
            $totalVolRka  = 0.0;

            foreach ($dataRows as $branchRaw => $row) {
                $b      = strtoupper($branchRaw);
                $jml_curr  = $row->jml_curr ?? 0; $jml_mtd = $row->jml_mtd ?? 0; $jml_ytd = $row->jml_ytd ?? 0; $jml_yoy = $row->jml_yoy ?? 0;
                $prod_curr = $row->prod_curr ?? 0; $prod_mtd = $row->prod_mtd ?? 0; $prod_ytd = $row->prod_ytd ?? 0; $prod_yoy = $row->prod_yoy ?? 0;
                $vol_curr  = ($row->vol_curr ?? 0) / 1000000; $vol_mtd = ($row->vol_mtd ?? 0) / 1000000;
                $vol_ytd   = ($row->vol_ytd ?? 0) / 1000000; $vol_yoy = ($row->vol_yoy ?? 0) / 1000000;

                $jmlRka  = round((float) ($qrisRkaGroups['jml'][$b] ?? 0), 2);
                $prodRka = round((float) ($qrisRkaGroups['prod'][$b] ?? 0), 2);
                $volRka  = round(((float) ($qrisRkaGroups['vol'][$b] ?? 0)) / 1000000, 2);

                $data[] = [
                    'branch' => $b,
                    'jml'    => ['curr' => $jml_curr, 'mtd_val' => $jml_curr - $jml_mtd, 'mtd_pct' => $jml_mtd > 0 ? round(($jml_curr - $jml_mtd) / $jml_mtd * 100, 1) : 0, 'ytd_val' => $jml_curr - $jml_ytd, 'yoy_val' => $jml_curr - $jml_yoy, 'rka' => $jmlRka, 'penc_pct' => $jmlRka > 0 ? round(($jml_curr / $jmlRka) * 100, 2) : 0],
                    'prod'   => ['curr' => $prod_curr, 'pct_jml' => $jml_curr > 0 ? round(($prod_curr / $jml_curr) * 100, 1) : 0, 'mtd_val' => $prod_curr - $prod_mtd, 'mtd_pct' => $prod_mtd > 0 ? round(($prod_curr - $prod_mtd) / $prod_mtd * 100, 1) : 0, 'ytd_val' => $prod_curr - $prod_ytd, 'yoy_val' => $prod_curr - $prod_yoy, 'rka' => $prodRka, 'penc_pct' => $prodRka > 0 ? round(($prod_curr / $prodRka) * 100, 2) : 0],
                    'vol'    => ['curr' => round($vol_curr, 2), 'mtd_val' => round($vol_curr - $vol_mtd, 2), 'mtd_pct' => $vol_mtd > 0 ? round(($vol_curr - $vol_mtd) / $vol_mtd * 100, 1) : 0, 'ytd_val' => round($vol_curr - $vol_ytd, 2), 'yoy_val' => round($vol_curr - $vol_yoy, 2), 'rka' => $volRka, 'penc_pct' => $volRka > 0 ? round(($vol_curr / $volRka) * 100, 2) : 0],
                ];

                $totals['jml_curr'] += $jml_curr; $totals['jml_mtd'] += $jml_mtd; $totals['jml_ytd'] += $jml_ytd; $totals['jml_yoy'] += $jml_yoy;
                $totals['prod_curr'] += $prod_curr; $totals['prod_mtd'] += $prod_mtd; $totals['prod_ytd'] += $prod_ytd; $totals['prod_yoy'] += $prod_yoy;
                $totals['vol_curr'] += $vol_curr; $totals['vol_mtd'] += $vol_mtd; $totals['vol_ytd'] += $vol_ytd; $totals['vol_yoy'] += $vol_yoy;
                $totalJmlRka += $jmlRka; $totalProdRka += $prodRka; $totalVolRka += $volRka;
            }

            $grandTotal = [
                'branch' => $ctx['qrisTotalLabel'],
                'jml'    => ['curr' => $totals['jml_curr'], 'mtd_val' => $totals['jml_curr'] - $totals['jml_mtd'], 'mtd_pct' => $totals['jml_mtd'] > 0 ? round(($totals['jml_curr'] - $totals['jml_mtd']) / $totals['jml_mtd'] * 100, 1) : 0, 'ytd_val' => $totals['jml_curr'] - $totals['jml_ytd'], 'yoy_val' => $totals['jml_curr'] - $totals['jml_yoy'], 'rka' => round($totalJmlRka, 2), 'penc_pct' => $totalJmlRka > 0 ? round(($totals['jml_curr'] / $totalJmlRka) * 100, 2) : 0],
                'prod'   => ['curr' => $totals['prod_curr'], 'pct_jml' => $totals['jml_curr'] > 0 ? round(($totals['prod_curr'] / $totals['jml_curr']) * 100, 1) : 0, 'mtd_val' => $totals['prod_curr'] - $totals['prod_mtd'], 'mtd_pct' => $totals['prod_mtd'] > 0 ? round(($totals['prod_curr'] - $totals['prod_mtd']) / $totals['prod_mtd'] * 100, 1) : 0, 'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'], 'rka' => round($totalProdRka, 2), 'penc_pct' => $totalProdRka > 0 ? round(($totals['prod_curr'] / $totalProdRka) * 100, 2) : 0],
                'vol'    => ['curr' => round($totals['vol_curr'], 2), 'mtd_val' => round($totals['vol_curr'] - $totals['vol_mtd'], 2), 'mtd_pct' => $totals['vol_mtd'] > 0 ? round(($totals['vol_curr'] - $totals['vol_mtd']) / $totals['vol_mtd'] * 100, 1) : 0, 'ytd_val' => round($totals['vol_curr'] - $totals['vol_ytd'], 2), 'yoy_val' => round($totals['vol_curr'] - $totals['vol_yoy'], 2), 'rka' => round($totalVolRka, 2), 'penc_pct' => $totalVolRka > 0 ? round(($totals['vol_curr'] / $totalVolRka) * 100, 2) : 0],
            ];

            return [
                'labels' => $ctx['labels'],
                'group_label' => $ctx['qrisGroupLabel'],
                'data' => $data,
                'total' => $grandTotal,
            ];
        });

        return response()->json(['status' => 'success'] + $payload);
    }

    private function handleQrisMom(array $ctx): JsonResponse
    {
        $cacheKey = 'qris_report:qris_mom:' . sha1(json_encode([
            'cacheVersion' => ReportCacheVersion::get(),
            'dateCurr' => $ctx['dateCurr'],
            'datePrevMoM' => $ctx['datePrevMoM'],
            'branches' => $ctx['upperBranches'],
            'ukers' => $ctx['upperSelectedUkers'],
            'branchFiltered' => $ctx['isQrisBranchFiltered'],
            'groupColumn' => $ctx['qrisGroupColumn'],
            'monthColumn' => $ctx['rkaMonthColumn'],
            'rkaLookup' => 'kanca-summary-fallback-v1',
        ]));

        $payload = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($ctx) {
            $qrisRkaGroups = $this->buildSplitRkaGroups(
                ['prod' => ['mata_anggaran' => ['Jumlah QRIS yang Produktif']]],
                $ctx
            );

            $dataRows = DB::table('jumlah_merchant_qris_detail')
                ->select(DB::raw("UPPER({$ctx['qrisGroupColumn']}) as branch"))
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as store_curr', [$ctx['dateCurr']])
                ->selectRaw('COUNT(DISTINCT CASE WHEN POSISI = ? THEN STOREID END) as store_prev', [$ctx['datePrevMoM']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI=? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) = 0 THEN STOREID END) as sv0_curr", [$ctx['dateCurr']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI=? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) = 0 THEN STOREID END) as sv0_prev", [$ctx['datePrevMoM']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI=? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_curr", [$ctx['dateCurr']])
                ->selectRaw("COUNT(DISTINCT CASE WHEN POSISI=? AND CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) >= 50000 THEN STOREID END) as prod_prev", [$ctx['datePrevMoM']])
                ->selectRaw("SUM(CASE WHEN POSISI=? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_curr", [$ctx['dateCurr']])
                ->selectRaw("SUM(CASE WHEN POSISI=? THEN CAST(REPLACE(AKUMULASI_SV_TOTAL, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as vol_prev", [$ctx['datePrevMoM']]);
            $this->applyQrisFilter($dataRows, $ctx, 'BRDESC');
            $this->applyQrisDateScope($dataRows, [$ctx['dateCurr'], $ctx['datePrevMoM']]);
            $dataRows = $dataRows->groupByRaw("UPPER({$ctx['qrisGroupColumn']})")->get()->keyBy('branch');

            $data   = [];
            $totals = ['store_curr' => 0, 'store_prev' => 0, 'sv0_curr' => 0, 'sv0_prev' => 0, 'prod_curr' => 0, 'prod_prev' => 0, 'vol_curr' => 0, 'vol_prev' => 0];
            $totalProdRka = 0.0;

            foreach ($dataRows as $branchRaw => $row) {
                $b         = strtoupper($branchRaw);
                $store_curr = $row->store_curr ?? 0; $store_prev = $row->store_prev ?? 0;
                $sv0_curr   = $row->sv0_curr ?? 0;    $sv0_prev   = $row->sv0_prev ?? 0;
                $prod_curr  = $row->prod_curr ?? 0;    $prod_prev  = $row->prod_prev ?? 0;
                $vol_curr   = ($row->vol_curr ?? 0) / 1000000;
                $vol_prev   = ($row->vol_prev ?? 0) / 1000000;
                $prodRka    = round((float) ($qrisRkaGroups['prod'][$b] ?? 0), 2);

                $data[] = [
                    'branch' => $b,
                    'sv0'    => ['prev' => $sv0_prev, 'curr' => $sv0_curr, 'mom' => $sv0_curr - $sv0_prev, 'pct' => $sv0_prev > 0 ? round(($sv0_curr - $sv0_prev) / $sv0_prev * 100, 1) : 0],
                    'prod'   => ['prev' => $prod_prev, 'curr' => $prod_curr, 'mom' => $prod_curr - $prod_prev, 'pct' => $prod_prev > 0 ? round(($prod_curr - $prod_prev) / $prod_prev * 100, 1) : 0, 'rka' => $prodRka, 'gap' => round($prod_curr - $prodRka, 2), 'penc' => $prodRka > 0 ? round(($prod_curr / $prodRka) * 100, 2) : 0],
                    'store'  => ['prev' => $store_prev, 'curr' => $store_curr, 'mom' => $store_curr - $store_prev, 'pct' => $store_prev > 0 ? round(($store_curr - $store_prev) / $store_prev * 100, 1) : 0],
                    'vol'    => ['prev' => round($vol_prev, 2), 'curr' => round($vol_curr, 2), 'mom' => round($vol_curr - $vol_prev, 2), 'pct' => $vol_prev > 0 ? round(($vol_curr - $vol_prev) / $vol_prev * 100, 1) : 0],
                ];

                $totals['store_curr'] += $store_curr; $totals['store_prev'] += $store_prev;
                $totals['sv0_curr']   += $sv0_curr;   $totals['sv0_prev']   += $sv0_prev;
                $totals['prod_curr']  += $prod_curr;  $totals['prod_prev']  += $prod_prev;
                $totals['vol_curr']   += $vol_curr;   $totals['vol_prev']   += $vol_prev;
                $totalProdRka         += $prodRka;
            }

            $grandTotal = [
                'branch' => $ctx['qrisTotalLabel'],
                'sv0'    => ['prev' => $totals['sv0_prev'], 'curr' => $totals['sv0_curr'], 'mom' => $totals['sv0_curr'] - $totals['sv0_prev'], 'pct' => $totals['sv0_prev'] > 0 ? round(($totals['sv0_curr'] - $totals['sv0_prev']) / $totals['sv0_prev'] * 100, 1) : 0],
                'prod'   => ['prev' => $totals['prod_prev'], 'curr' => $totals['prod_curr'], 'mom' => $totals['prod_curr'] - $totals['prod_prev'], 'pct' => $totals['prod_prev'] > 0 ? round(($totals['prod_curr'] - $totals['prod_prev']) / $totals['prod_prev'] * 100, 1) : 0, 'rka' => round($totalProdRka, 2), 'gap' => round($totals['prod_curr'] - $totalProdRka, 2), 'penc' => $totalProdRka > 0 ? round(($totals['prod_curr'] / $totalProdRka) * 100, 2) : 0],
                'store'  => ['prev' => $totals['store_prev'], 'curr' => $totals['store_curr'], 'mom' => $totals['store_curr'] - $totals['store_prev'], 'pct' => $totals['store_prev'] > 0 ? round(($totals['store_curr'] - $totals['store_prev']) / $totals['store_prev'] * 100, 1) : 0],
                'vol'    => ['prev' => round($totals['vol_prev'], 2), 'curr' => round($totals['vol_curr'], 2), 'mom' => round($totals['vol_curr'] - $totals['vol_prev'], 2), 'pct' => $totals['vol_prev'] > 0 ? round(($totals['vol_curr'] - $totals['vol_prev']) / $totals['vol_prev'] * 100, 1) : 0],
            ];

            return [
                'labels' => $ctx['labels'],
                'group_label' => $ctx['qrisGroupLabel'],
                'data' => $data,
                'total' => $grandTotal,
            ];
        });

        return response()->json(['status' => 'success'] + $payload);
    }

    /**
     * Build RKA groups using split filtering: direct kanca (Ponorogo) + regional patterns (Madiun, Magetan, Ngawi)
     */
    private function buildSplitRkaGroups(array $definitions, array $ctx): array
    {
        $groups = [];

        foreach ($definitions as $defKey => $def) {
            $groups[$defKey] = [];
        }

        // Get direct RKA only when selected scope contains KC Ponorogo.
        if (!empty($ctx['rkaDirectBranches'])) {
            $directGroups = $this->rkaLookup->aggregateByGroup(
                $definitions,
                $ctx['rkaMonthColumn'],
                $ctx['rkaDirectBranches'],
                $ctx['upperSelectedUkers'],
                $ctx['isQrisBranchFiltered'] ? 'uker' : 'kanca'
            );

            foreach ($definitions as $defKey => $def) {
                $groups[$defKey] = $directGroups[$defKey] ?? [];
            }
        }

        // Get regional RKA if there are regional patterns
        if (!empty($ctx['rkaRegionalPatterns'])) {
            $regionalGroups = $this->rkaLookup->aggregateByGroupWithRegionalFilter(
                $definitions,
                $ctx['rkaMonthColumn'],
                $ctx['rkaRegionalPatterns'],
                null,
                $ctx['upperSelectedUkers'],
                $ctx['isQrisBranchFiltered'] ? 'uker' : 'region'
            );

            foreach ($definitions as $defKey => $def) {
                if (isset($regionalGroups[$defKey])) {
                    foreach ($regionalGroups[$defKey] as $groupKey => $value) {
                        $resultKey = $ctx['isQrisBranchFiltered'] ? $groupKey : ('KC ' . $groupKey);
                        $groups[$defKey][$resultKey] = $value;
                    }
                }
            }
        }

        if (!$ctx['isQrisBranchFiltered']) {
            $this->fillMissingBranchRkaFromKancaFallback($groups, $definitions, $ctx['rkaMonthColumn'], $ctx['upperBranches']);
        }

        return $groups;
    }

    private function fillMissingBranchRkaFromKancaFallback(array &$groups, array $definitions, string $monthColumn, array $branches): void
    {
        $fallbackGroups = $this->rkaLookup->aggregateByKancaWithSummaryFallback($definitions, $monthColumn, $branches);

        foreach (array_keys($definitions) as $defKey) {
            foreach ($branches as $branch) {
                $branchKey = strtoupper(trim((string) $branch));
                if ($branchKey === '') {
                    continue;
                }

                if (abs((float) ($groups[$defKey][$branchKey] ?? 0)) <= 0.0) {
                    $groups[$defKey][$branchKey] = (float) ($fallbackGroups[$defKey][$branchKey] ?? 0);
                }
            }
        }
    }
}
