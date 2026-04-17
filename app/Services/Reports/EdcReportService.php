<?php

namespace App\Services\Reports;

use App\Support\RkaLookupService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk semua tab laporan EDC (edc, merchant_prod, sv_merchant_accum, mid_tid, prod_mom).
 * Diekstrak dari DataReportController::fetchData() agar dapat diuji dan dimaintain secara terpisah.
 */
class EdcReportService
{
    public function __construct(
        private readonly RkaLookupService $rkaLookup
    ) {}

    /**
     * Handle request fetch data EDC, delegasikan ke handler tab yang sesuai.
     */
    public function handle(Request $request): JsonResponse
    {
        $ctx = $this->parseContext($request);
        $tab = $request->input('tab', 'edc');

        return match ($tab) {
            'merchant_prod'    => $this->handleMerchantProd($ctx),
            'sv_merchant_accum' => $this->handleSvMerchantAccum($ctx),
            'mid_tid'          => $this->handleMidTid($ctx),
            'prod_mom'         => $this->handleProdMom($ctx),
            default            => $this->handleEdc($ctx),
        };
    }

    // -------------------------------------------------------------------------
    // Context Parser — dipanggil satu kali di awal, berisi semua parameter bersama
    // -------------------------------------------------------------------------

    private function parseContext(Request $request): array
    {
        $defaultBranches  = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($b) => trim((string) $b))->filter()->values()->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($u) => trim((string) $u))
            ->filter()
            ->reject(fn ($u) => strtoupper($u) === 'ALL UKER')
            ->values()->all();

        $branches          = !empty($selectedBranches) ? $selectedBranches : $defaultBranches;
        $isBranchFiltered  = !empty($selectedBranches);
        $groupColumn       = $isBranchFiltered ? 'NAMA_UKER' : 'NAMA_KANCA';
        $groupLabel        = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $totalBranchLabel  = !empty($selectedBranches)
            ? 'TOTAL ' . strtoupper(implode(', ', $selectedBranches))
            : 'TOTAL AREA 6';
        $upperBranches     = array_map('strtoupper', $branches);
        $upperSelectedUkers = array_map('strtoupper', $selectedUkers);

        $posisi       = $request->input('posisi', date('Y-m-d'));
        $selectedDate = Carbon::parse($posisi);

        $dateCurr   = $selectedDate->copy()->toDateString();
        $dateMtD    = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $dateYtD    = $selectedDate->copy()->subYearNoOverflow()->endOfYear()->toDateString();
        $dateYoY    = $selectedDate->copy()->subYearNoOverflow()->endOfMonth()->toDateString();
        $datePrevMoM = $selectedDate->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $rkaMonthColumn = $this->rkaLookup->resolveMonthColumn($selectedDate);
        $rkaMonthLabel  = $this->rkaLookup->resolveMonthLabel($selectedDate);

        $labels = [
            'curr'     => Carbon::parse($dateCurr)->translatedFormat('d F Y'),
            'mtd'      => Carbon::parse($dateMtD)->translatedFormat("M'y"),
            'ytd'      => Carbon::parse($dateYtD)->translatedFormat("M'y"),
            'yoy'      => Carbon::parse($dateYoY)->translatedFormat("M'y"),
            'prev_mom' => Carbon::parse($datePrevMoM)->translatedFormat('d M Y'),
            'rka'      => $rkaMonthLabel,
        ];

        return compact(
            'branches', 'isBranchFiltered', 'groupColumn', 'groupLabel',
            'totalBranchLabel', 'upperBranches', 'upperSelectedUkers',
            'selectedUkers', 'selectedDate', 'dateCurr', 'dateMtD',
            'dateYtD', 'dateYoY', 'datePrevMoM', 'rkaMonthColumn',
            'rkaMonthLabel', 'labels'
        );
    }

    // -------------------------------------------------------------------------
    // Tab Handlers
    // -------------------------------------------------------------------------

    private function applyBranchFilter($query, array $ctx): void
    {
        $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), $ctx['upperBranches']);
        if ($ctx['isBranchFiltered']) {
            $query->whereNotNull('NAMA_UKER')->whereRaw("TRIM(NAMA_UKER) <> ''");
        }
        if (!empty($ctx['selectedUkers'])) {
            $query->whereIn(DB::raw('UPPER(TRIM(NAMA_UKER))'), $ctx['upperSelectedUkers']);
        }
    }

    private function handleEdc(array $ctx): JsonResponse
    {
        $edcRkaGroups = $this->rkaLookup->aggregateByGroup(
            [
                'prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
                'sv'   => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']],
            ],
            $ctx['rkaMonthColumn'],
            $ctx['upperBranches'],
            $ctx['upperSelectedUkers'],
            $ctx['isBranchFiltered'] ? 'uker' : 'kanca'
        );

        $q = DB::table('jumlah_merchant_detail')
            ->select(DB::raw("UPPER({$ctx['groupColumn']}) as branch"))
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_mtd', [$ctx['dateMtD']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_ytd', [$ctx['dateYtD']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI)=? THEN MID END) as mid_yoy', [$ctx['dateYoY']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_mtd', [$ctx['dateMtD']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_ytd', [$ctx['dateYtD']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI)=? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_yoy', [$ctx['dateYoY']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_curr', [$ctx['dateCurr']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_mtd', [$ctx['dateMtD']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI)=? THEN SALES_VOLUME ELSE 0 END) as sv_yoy', [$ctx['dateYoY']]);

        $this->applyBranchFilter($q, $ctx);
        $rows = $q->groupBy('branch')->get();

        $data = [];
        $total = [
            'mid_curr' => 0, 'mid_mtd' => 0, 'mid_ytd' => 0, 'mid_yoy' => 0,
            'prod_curr' => 0, 'prod_mtd' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0,
            'sv_curr' => 0, 'sv_mtd' => 0, 'sv_yoy' => 0,
        ];
        $totalProdRka = 0.0;
        $totalSvRka   = 0.0;

        foreach ($rows as $r) {
            $branchKey   = strtoupper(trim((string) ($r->branch ?? '')));
            $prodRka     = round((float) ($edcRkaGroups['prod'][$branchKey] ?? 0), 2);
            $svRka       = round((float) ($edcRkaGroups['sv'][$branchKey] ?? 0), 2);
            $prodPencPct = $prodRka > 0 ? (($r->prod_curr / $prodRka) * 100) : 0;

            $data[] = [
                'branch' => $r->branch,
                'mid'    => [
                    'curr'    => $r->mid_curr, 'mtd' => $r->mid_mtd, 'ytd' => $r->mid_ytd, 'yoy' => $r->mid_yoy,
                    'mtd_val' => $r->mid_curr - $r->mid_mtd, 'mtd_pct' => $r->mid_mtd > 0 ? (($r->mid_curr - $r->mid_mtd) / $r->mid_mtd) * 100 : 0,
                    'ytd_val' => $r->mid_curr - $r->mid_ytd, 'yoy_val' => $r->mid_curr - $r->mid_yoy,
                ],
                'prod'   => [
                    'curr'     => $r->prod_curr, 'pct_tid' => $r->mid_curr > 0 ? ($r->prod_curr / $r->mid_curr) * 100 : 0,
                    'mtd_val'  => $r->prod_curr - $r->prod_mtd, 'mtd_pct' => $r->prod_mtd > 0 ? (($r->prod_curr - $r->prod_mtd) / $r->prod_mtd) * 100 : 0,
                    'ytd_val'  => $r->prod_curr - $r->prod_ytd, 'yoy_val' => $r->prod_curr - $r->prod_yoy,
                    'rka'      => $prodRka, 'penc_pct' => round($prodPencPct, 2),
                ],
                'sv'     => [
                    'curr'    => round($r->sv_curr / 1000000000, 2),
                    'mtd_val' => round(($r->sv_curr - $r->sv_mtd) / 1000000000, 2),
                    'mtd_pct' => $r->sv_mtd > 0 ? (($r->sv_curr - $r->sv_mtd) / $r->sv_mtd) * 100 : 0,
                    'yoy_val' => round(($r->sv_curr - $r->sv_yoy) / 1000000000, 2),
                    'rka'     => $svRka, 'penc_pct' => 0,
                ],
            ];

            $total['mid_curr']  += $r->mid_curr;  $total['mid_mtd'] += $r->mid_mtd; $total['mid_ytd'] += $r->mid_ytd; $total['mid_yoy'] += $r->mid_yoy;
            $total['prod_curr'] += $r->prod_curr; $total['prod_mtd'] += $r->prod_mtd; $total['prod_ytd'] += $r->prod_ytd; $total['prod_yoy'] += $r->prod_yoy;
            $total['sv_curr']   += $r->sv_curr;   $total['sv_mtd'] += $r->sv_mtd; $total['sv_yoy'] += $r->sv_yoy;
            $totalProdRka += $prodRka;
            $totalSvRka   += $svRka;
        }

        $totalProdPencPct = $totalProdRka > 0 ? (($total['prod_curr'] / $totalProdRka) * 100) : 0;

        return response()->json([
            'status' => 'success', 'labels' => $ctx['labels'], 'group_label' => $ctx['groupLabel'], 'data' => $data,
            'total' => [
                'branch' => $ctx['totalBranchLabel'],
                'mid'    => [
                    'curr' => $total['mid_curr'], 'mtd' => $total['mid_mtd'], 'ytd' => $total['mid_ytd'], 'yoy' => $total['mid_yoy'],
                    'mtd_val' => $total['mid_curr'] - $total['mid_mtd'], 'mtd_pct' => $total['mid_mtd'] > 0 ? (($total['mid_curr'] - $total['mid_mtd']) / $total['mid_mtd']) * 100 : 0,
                    'ytd_val' => $total['mid_curr'] - $total['mid_ytd'], 'yoy_val' => $total['mid_curr'] - $total['mid_yoy'],
                ],
                'prod'   => [
                    'curr' => $total['prod_curr'], 'pct_tid' => $total['mid_curr'] > 0 ? ($total['prod_curr'] / $total['mid_curr']) * 100 : 0,
                    'mtd_val' => $total['prod_curr'] - $total['prod_mtd'], 'mtd_pct' => $total['prod_mtd'] > 0 ? (($total['prod_curr'] - $total['prod_mtd']) / $total['prod_mtd']) * 100 : 0,
                    'ytd_val' => $total['prod_curr'] - $total['prod_ytd'], 'yoy_val' => $total['prod_curr'] - $total['prod_yoy'],
                    'rka' => $totalProdRka, 'penc_pct' => round($totalProdPencPct, 2),
                ],
                'sv'     => [
                    'curr' => round($total['sv_curr'] / 1000000000, 2), 'mtd_val' => round(($total['sv_curr'] - $total['sv_mtd']) / 1000000000, 2),
                    'mtd_pct' => $total['sv_mtd'] > 0 ? (($total['sv_curr'] - $total['sv_mtd']) / $total['sv_mtd']) * 100 : 0,
                    'yoy_val' => round(($total['sv_curr'] - $total['sv_yoy']) / 1000000000, 2), 'rka' => round($totalSvRka, 2), 'penc_pct' => 0,
                ],
            ],
        ]);
    }

    private function handleMerchantProd(array $ctx): JsonResponse
    {
        $merchantRkaGroups = $this->rkaLookup->aggregateByGroup(
            ['prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']]],
            $ctx['rkaMonthColumn'],
            $ctx['upperBranches'],
            $ctx['upperSelectedUkers'],
            $ctx['isBranchFiltered'] ? 'uker' : 'kanca'
        );

        $query = DB::table('jumlah_merchant_detail')
            ->select(DB::raw("UPPER({$ctx['groupColumn']}) as branch"))
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_prev_month', [$ctx['datePrevMoM']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_ytd', [$ctx['dateYtD']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? AND SALES_VOLUME >= 15000000 THEN 1 END) as prod_yoy', [$ctx['dateYoY']]);

        $this->applyBranchFilter($query, $ctx);
        $rows = $query->groupBy('branch')->get();

        $data         = [];
        $totals       = ['mid_curr' => 0, 'prod_curr' => 0, 'prod_prev_month' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0];
        $totalProdRka = 0.0;

        foreach ($rows as $row) {
            $branchKey   = strtoupper(trim((string) ($row->branch ?? '')));
            $midCurr     = (int) ($row->mid_curr ?? 0);
            $prodCurr    = (int) ($row->prod_curr ?? 0);
            $prodPrevMonth = (int) ($row->prod_prev_month ?? 0);
            $prodYtd     = (int) ($row->prod_ytd ?? 0);
            $prodYoy     = (int) ($row->prod_yoy ?? 0);
            $prodRka     = round((float) ($merchantRkaGroups['prod'][$branchKey] ?? 0), 2);
            $prodPencPct = $prodRka > 0 ? (($prodCurr / $prodRka) * 100) : 0;

            $data[] = [
                'branch' => $row->branch,
                'prod'   => [
                    'feb_prev' => $prodYoy, 'dec_prev' => $prodYtd, 'jan_prev' => $prodPrevMonth, 'curr' => $prodCurr,
                    'pct_tid'  => $midCurr > 0 ? round(($prodCurr / $midCurr) * 100, 1) : 0,
                    'mtd_val'  => $prodCurr - $prodPrevMonth,
                    'mtd_pct'  => $prodPrevMonth > 0 ? round((($prodCurr - $prodPrevMonth) / $prodPrevMonth) * 100, 1) : 0,
                    'ytd_val'  => $prodCurr - $prodYtd, 'yoy_val' => $prodCurr - $prodYoy,
                    'rka' => $prodRka, 'penc_pct' => round($prodPencPct, 1),
                ],
            ];

            $totals['mid_curr']       += $midCurr;
            $totals['prod_curr']      += $prodCurr;
            $totals['prod_prev_month'] += $prodPrevMonth;
            $totals['prod_ytd']       += $prodYtd;
            $totals['prod_yoy']       += $prodYoy;
            $totalProdRka             += $prodRka;
        }

        $totalProdPencPct = $totalProdRka > 0 ? (($totals['prod_curr'] / $totalProdRka) * 100) : 0;
        $labels = [
            'merchant_feb_prev' => Carbon::parse($ctx['dateYoY'])->translatedFormat("M'y"),
            'merchant_dec_prev' => Carbon::parse($ctx['dateYtD'])->translatedFormat("M'y"),
            'merchant_jan_prev' => Carbon::parse($ctx['datePrevMoM'])->translatedFormat("M'y"),
            'merchant_curr'     => Carbon::parse($ctx['dateCurr'])->translatedFormat('d M y'),
            'rka'               => 'RKA ' . $ctx['rkaMonthLabel'],
        ];

        return response()->json([
            'status' => 'success', 'labels' => $labels, 'group_label' => $ctx['groupLabel'], 'data' => $data,
            'total'  => [
                'branch' => $ctx['totalBranchLabel'],
                'prod'   => [
                    'feb_prev' => $totals['prod_yoy'], 'dec_prev' => $totals['prod_ytd'], 'jan_prev' => $totals['prod_prev_month'], 'curr' => $totals['prod_curr'],
                    'pct_tid'  => $totals['mid_curr'] > 0 ? round(($totals['prod_curr'] / $totals['mid_curr']) * 100, 1) : 0,
                    'mtd_val'  => $totals['prod_curr'] - $totals['prod_prev_month'],
                    'mtd_pct'  => $totals['prod_prev_month'] > 0 ? round((($totals['prod_curr'] - $totals['prod_prev_month']) / $totals['prod_prev_month']) * 100, 1) : 0,
                    'ytd_val'  => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'],
                    'rka' => round($totalProdRka, 2), 'penc_pct' => round($totalProdPencPct, 1),
                ],
            ],
        ]);
    }

    private function handleSvMerchantAccum(array $ctx): JsonResponse
    {
        $svRkaGroups = $this->rkaLookup->aggregateByGroup(
            ['sv' => ['mata_anggaran' => ['Sales Volume Merchant (EDC)']]],
            $ctx['rkaMonthColumn'],
            $ctx['upperBranches'],
            $ctx['upperSelectedUkers'],
            $ctx['isBranchFiltered'] ? 'uker' : 'kanca'
        );

        $query = DB::table('jumlah_merchant_detail')
            ->select(DB::raw("UPPER({$ctx['groupColumn']}) as branch"))
            ->selectRaw('SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_curr', [$ctx['dateCurr']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_dec_prev', [$ctx['dateYtD']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_jan_prev', [$ctx['datePrevMoM']])
            ->selectRaw('SUM(CASE WHEN DATE(POSISI) = ? THEN SALES_VOLUME ELSE 0 END) as sv_feb_prev', [$ctx['dateYoY']]);

        $this->applyBranchFilter($query, $ctx);
        $rows = $query->groupBy('branch')->get();

        $data   = [];
        $totals = ['sv_curr' => 0, 'sv_dec_prev' => 0, 'sv_jan_prev' => 0, 'sv_feb_prev' => 0];
        $totalSvRka = 0.0;

        foreach ($rows as $row) {
            $branchKey  = strtoupper(trim((string) ($row->branch ?? '')));
            $svCurr     = round(((float) ($row->sv_curr ?? 0)) / 1000000, 0);
            $svDecPrev  = round(((float) ($row->sv_dec_prev ?? 0)) / 1000000, 0);
            $svJanPrev  = round(((float) ($row->sv_jan_prev ?? 0)) / 1000000, 0);
            $svFebPrev  = round(((float) ($row->sv_feb_prev ?? 0)) / 1000000, 0);
            $svRka      = round((float) ($svRkaGroups['sv'][$branchKey] ?? 0) / 1000000, 0);
            $svPencPct  = $svRka > 0 ? (($svCurr / $svRka) * 100) : 0;

            $data[] = [
                'branch' => $row->branch,
                'sv'     => [
                    'feb_prev' => $svFebPrev, 'dec_prev' => $svDecPrev, 'jan_prev' => $svJanPrev, 'curr' => $svCurr,
                    'mtd_val'  => $svCurr - $svJanPrev, 'mtd_pct' => $svJanPrev > 0 ? round((($svCurr - $svJanPrev) / $svJanPrev) * 100, 1) : 0,
                    'yoy_val'  => $svCurr - $svFebPrev, 'rka' => $svRka, 'penc_pct' => round($svPencPct, 1),
                ],
            ];

            $totals['sv_curr']    += $svCurr;
            $totals['sv_dec_prev'] += $svDecPrev;
            $totals['sv_jan_prev'] += $svJanPrev;
            $totals['sv_feb_prev'] += $svFebPrev;
            $totalSvRka           += $svRka;
        }

        $totalSvPencPct = $totalSvRka > 0 ? (($totals['sv_curr'] / $totalSvRka) * 100) : 0;
        $labels = [
            'merchant_sv_feb_prev' => Carbon::parse($ctx['dateYoY'])->translatedFormat("M'y"),
            'merchant_sv_dec_prev' => Carbon::parse($ctx['dateYtD'])->translatedFormat("M'y"),
            'merchant_sv_jan_prev' => Carbon::parse($ctx['datePrevMoM'])->translatedFormat("M'y"),
            'merchant_sv_curr'     => Carbon::parse($ctx['dateCurr'])->translatedFormat('d M y'),
            'rka'                  => 'RKA ' . Carbon::parse($ctx['dateCurr'])->translatedFormat("M'y"),
        ];

        return response()->json([
            'status' => 'success', 'labels' => $labels, 'group_label' => $ctx['groupLabel'], 'data' => $data,
            'total'  => [
                'branch' => $ctx['totalBranchLabel'],
                'sv'     => [
                    'feb_prev' => $totals['sv_feb_prev'], 'dec_prev' => $totals['sv_dec_prev'], 'jan_prev' => $totals['sv_jan_prev'], 'curr' => $totals['sv_curr'],
                    'mtd_val'  => $totals['sv_curr'] - $totals['sv_jan_prev'],
                    'mtd_pct'  => $totals['sv_jan_prev'] > 0 ? round((($totals['sv_curr'] - $totals['sv_jan_prev']) / $totals['sv_jan_prev']) * 100, 1) : 0,
                    'yoy_val'  => $totals['sv_curr'] - $totals['sv_feb_prev'], 'rka' => round($totalSvRka, 0), 'penc_pct' => round($totalSvPencPct, 1),
                ],
            ],
        ]);
    }

    private function handleMidTid(array $ctx): JsonResponse
    {
        $query = DB::table('jumlah_merchant_detail')
            ->select(DB::raw("UPPER({$ctx['groupColumn']}) as branch"))
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_mtd', [$ctx['dateMtD']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_ytd', [$ctx['dateYtD']])
            ->selectRaw('COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_yoy', [$ctx['dateYoY']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd', [$ctx['dateMtD']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_ytd', [$ctx['dateYtD']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_yoy', [$ctx['dateYoY']]);

        $this->applyBranchFilter($query, $ctx);
        $rawData = $query->groupBy('branch')->get();

        $data   = [];
        $totals = ['mid_curr' => 0, 'mid_mtd' => 0, 'mid_ytd' => 0, 'mid_yoy' => 0, 'tid_curr' => 0, 'tid_mtd' => 0, 'tid_ytd' => 0, 'tid_yoy' => 0];

        foreach ($rawData as $row) {
            $data[] = [
                'branch' => $row->branch,
                'mid'    => [
                    'yoy' => $row->mid_yoy, 'ytd' => $row->mid_ytd, 'mtd' => $row->mid_mtd, 'curr' => $row->mid_curr,
                    'mtd_val' => $row->mid_curr - $row->mid_mtd,
                    'mtd_pct' => $row->mid_mtd > 0 ? round(($row->mid_curr - $row->mid_mtd) / $row->mid_mtd * 100, 1) : 0,
                    'ytd_val' => $row->mid_curr - $row->mid_ytd, 'yoy_val' => $row->mid_curr - $row->mid_yoy,
                ],
                'tid'    => [
                    'yoy' => $row->tid_yoy, 'ytd' => $row->tid_ytd, 'mtd' => $row->tid_mtd, 'curr' => $row->tid_curr,
                    'mtd_val' => $row->tid_curr - $row->tid_mtd,
                    'mtd_pct' => $row->tid_mtd > 0 ? round(($row->tid_curr - $row->tid_mtd) / $row->tid_mtd * 100, 1) : 0,
                    'ytd_val' => $row->tid_curr - $row->tid_ytd, 'yoy_val' => $row->tid_curr - $row->tid_yoy,
                    'rka' => 0, 'penc_pct' => 0,
                ],
            ];
            $totals['mid_curr'] += $row->mid_curr; $totals['mid_mtd'] += $row->mid_mtd; $totals['mid_ytd'] += $row->mid_ytd; $totals['mid_yoy'] += $row->mid_yoy;
            $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd; $totals['tid_ytd'] += $row->tid_ytd; $totals['tid_yoy'] += $row->tid_yoy;
        }

        $grandTotal = [
            'branch' => $ctx['totalBranchLabel'],
            'mid'    => [
                'yoy' => $totals['mid_yoy'], 'ytd' => $totals['mid_ytd'], 'mtd' => $totals['mid_mtd'], 'curr' => $totals['mid_curr'],
                'mtd_val' => $totals['mid_curr'] - $totals['mid_mtd'],
                'mtd_pct' => $totals['mid_mtd'] > 0 ? round(($totals['mid_curr'] - $totals['mid_mtd']) / $totals['mid_mtd'] * 100, 1) : 0,
                'ytd_val' => $totals['mid_curr'] - $totals['mid_ytd'], 'yoy_val' => $totals['mid_curr'] - $totals['mid_yoy'],
            ],
            'tid'    => [
                'yoy' => $totals['tid_yoy'], 'ytd' => $totals['tid_ytd'], 'mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'],
                'mtd_val' => $totals['tid_curr'] - $totals['tid_mtd'],
                'mtd_pct' => $totals['tid_mtd'] > 0 ? round(($totals['tid_curr'] - $totals['tid_mtd']) / $totals['tid_mtd'] * 100, 1) : 0,
                'ytd_val' => $totals['tid_curr'] - $totals['tid_ytd'], 'yoy_val' => $totals['tid_curr'] - $totals['tid_yoy'],
                'rka' => 0, 'penc_pct' => 0,
            ],
        ];

        return response()->json(['status' => 'success', 'labels' => $ctx['labels'], 'group_label' => $ctx['groupLabel'], 'data' => $data, 'total' => $grandTotal]);
    }

    private function handleProdMom(array $ctx): JsonResponse
    {
        $edcRkaGroups = $this->rkaLookup->aggregateByGroup(
            ['prod' => ['mata_anggaran' => ['Jumlah Merchant (EDC) yang Produktif']]],
            $ctx['rkaMonthColumn'],
            $ctx['upperBranches'],
            $ctx['upperSelectedUkers'],
            $ctx['isBranchFiltered'] ? 'uker' : 'kanca'
        );

        $q = DB::table('jumlah_merchant_detail')
            ->select(DB::raw("UPPER({$ctx['groupColumn']}) as branch"))
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME = '0' THEN MID END) as sv0_curr", [$ctx['dateCurr']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME = '0' THEN MID END) as sv0_mtd", [$ctx['datePrevMoM']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('1 - <1jt', '1jt - <15jt') THEN MID END) as sv1_15_curr", [$ctx['dateCurr']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('1 - <1jt', '1jt - <15jt') THEN MID END) as sv1_15_mtd", [$ctx['datePrevMoM']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('15jt - <50jt', '>=50jt') THEN MID END) as prod_curr", [$ctx['dateCurr']])
            ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME IN ('15jt - <50jt', '>=50jt') THEN MID END) as prod_mtd", [$ctx['datePrevMoM']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr', [$ctx['dateCurr']])
            ->selectRaw('COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd', [$ctx['datePrevMoM']])
            ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SALES_VOLUME, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as sv_vol_curr", [$ctx['dateCurr']])
            ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SALES_VOLUME, ',', '') AS DECIMAL(20,2)) ELSE 0 END) as sv_vol_mtd", [$ctx['datePrevMoM']]);

        $this->applyBranchFilter($q, $ctx);
        $rawData = $q->groupBy('branch')->get();

        $data   = [];
        $totals = ['sv0_curr' => 0, 'sv0_mtd' => 0, 'sv1_15_curr' => 0, 'sv1_15_mtd' => 0, 'prod_curr' => 0, 'prod_mtd' => 0, 'tid_curr' => 0, 'tid_mtd' => 0, 'sv_vol_curr' => 0, 'sv_vol_mtd' => 0];
        $totalProdRka = 0.0;

        foreach ($rawData as $row) {
            $branchKey  = strtoupper(trim((string) ($row->branch ?? '')));
            $prodRka    = round((float) ($edcRkaGroups['prod'][$branchKey] ?? 0), 2);
            $prodGap    = round($row->prod_curr - $prodRka, 2);
            $prodPenc   = $prodRka > 0 ? (($row->prod_curr / $prodRka) * 100) : 0;
            $svVolCurr  = $row->sv_vol_curr / 1000000000;
            $svVolMtd   = $row->sv_vol_mtd / 1000000000;

            $data[] = [
                'branch'  => $row->branch,
                'sv0'     => ['mtd' => $row->sv0_mtd, 'curr' => $row->sv0_curr, 'mom' => $row->sv0_curr - $row->sv0_mtd, 'pct' => $row->sv0_mtd > 0 ? round(($row->sv0_curr - $row->sv0_mtd) / $row->sv0_mtd * 100, 1) : 0],
                'sv1_15'  => ['mtd' => $row->sv1_15_mtd, 'curr' => $row->sv1_15_curr, 'mom' => $row->sv1_15_curr - $row->sv1_15_mtd, 'pct' => $row->sv1_15_mtd > 0 ? round(($row->sv1_15_curr - $row->sv1_15_mtd) / $row->sv1_15_mtd * 100, 1) : 0],
                'prod'    => ['mtd' => $row->prod_mtd, 'curr' => $row->prod_curr, 'mom' => $row->prod_curr - $row->prod_mtd, 'pct' => $row->prod_mtd > 0 ? round(($row->prod_curr - $row->prod_mtd) / $row->prod_mtd * 100, 1) : 0, 'rka' => $prodRka, 'gap' => $prodGap, 'penc' => round($prodPenc, 2)],
                'tid'     => ['mtd' => $row->tid_mtd, 'curr' => $row->tid_curr, 'mom' => $row->tid_curr - $row->tid_mtd, 'pct' => $row->tid_mtd > 0 ? round(($row->tid_curr - $row->tid_mtd) / $row->tid_mtd * 100, 1) : 0],
                'sv_vol'  => ['mtd' => round($svVolMtd, 2), 'curr' => round($svVolCurr, 2), 'mom' => round($svVolCurr - $svVolMtd, 2), 'pct' => $svVolMtd > 0 ? round(($svVolCurr - $svVolMtd) / $svVolMtd * 100, 1) : 0],
            ];

            $totals['sv0_curr'] += $row->sv0_curr; $totals['sv0_mtd'] += $row->sv0_mtd;
            $totals['sv1_15_curr'] += $row->sv1_15_curr; $totals['sv1_15_mtd'] += $row->sv1_15_mtd;
            $totals['prod_curr'] += $row->prod_curr; $totals['prod_mtd'] += $row->prod_mtd;
            $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd;
            $totals['sv_vol_curr'] += $svVolCurr; $totals['sv_vol_mtd'] += $svVolMtd;
            $totalProdRka += $prodRka;
        }

        $totalProdGap  = round($totals['prod_curr'] - $totalProdRka, 2);
        $totalProdPenc = $totalProdRka > 0 ? (($totals['prod_curr'] / $totalProdRka) * 100) : 0;

        $grandTotal = [
            'branch'  => $ctx['totalBranchLabel'],
            'sv0'     => ['mtd' => $totals['sv0_mtd'], 'curr' => $totals['sv0_curr'], 'mom' => $totals['sv0_curr'] - $totals['sv0_mtd'], 'pct' => $totals['sv0_mtd'] > 0 ? round(($totals['sv0_curr'] - $totals['sv0_mtd']) / $totals['sv0_mtd'] * 100, 1) : 0],
            'sv1_15'  => ['mtd' => $totals['sv1_15_mtd'], 'curr' => $totals['sv1_15_curr'], 'mom' => $totals['sv1_15_curr'] - $totals['sv1_15_mtd'], 'pct' => $totals['sv1_15_mtd'] > 0 ? round(($totals['sv1_15_curr'] - $totals['sv1_15_mtd']) / $totals['sv1_15_mtd'] * 100, 1) : 0],
            'prod'    => ['mtd' => $totals['prod_mtd'], 'curr' => $totals['prod_curr'], 'mom' => $totals['prod_curr'] - $totals['prod_mtd'], 'pct' => $totals['prod_mtd'] > 0 ? round(($totals['prod_curr'] - $totals['prod_mtd']) / $totals['prod_mtd'] * 100, 1) : 0, 'rka' => $totalProdRka, 'gap' => $totalProdGap, 'penc' => round($totalProdPenc, 2)],
            'tid'     => ['mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'], 'mom' => $totals['tid_curr'] - $totals['tid_mtd'], 'pct' => $totals['tid_mtd'] > 0 ? round(($totals['tid_curr'] - $totals['tid_mtd']) / $totals['tid_mtd'] * 100, 1) : 0],
            'sv_vol'  => ['mtd' => round($totals['sv_vol_mtd'], 2), 'curr' => round($totals['sv_vol_curr'], 2), 'mom' => round($totals['sv_vol_curr'] - $totals['sv_vol_mtd'], 2), 'pct' => $totals['sv_vol_mtd'] > 0 ? round(($totals['sv_vol_curr'] - $totals['sv_vol_mtd']) / $totals['sv_vol_mtd'] * 100, 1) : 0],
        ];

        return response()->json(['status' => 'success', 'labels' => $ctx['labels'], 'group_label' => $ctx['groupLabel'], 'data' => $data, 'total' => $grandTotal]);
    }
}
