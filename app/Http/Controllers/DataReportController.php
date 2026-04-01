<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataReportController extends Controller
{
    // 🔥 1. VIEW PERFORMANCE EDC
    public function performanceEdc()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 1; 

        return view('report.performance-edc', compact('branches', 'id_report'));
    }

    // 🔥 2. VIEW PERFORMANCE QRIS
    public function performanceQris()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 2; 

        return view('report.performance-qris', compact('branches', 'id_report'));
    }

    // 🔥 3. VIEW PERFORMANCE BRILINK
public function performanceBrilink()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 3; 

        return view('report.performance-brilink', compact('branches', 'id_report'));
    }

    // 🔥 5. VIEW PERFORMANCE BRIMO
    public function performanceBrimo()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 4; 

        return view('report.performance-brimo', compact('branches', 'id_report'));
    }


    // 🔥 4. MESIN PENGOLAH DATA UTAMA (AJAX API)
    public function fetchData(Request $request)
    {
        $id_report = $request->input('id_report', 1);
        $branches = $request->input('branches', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']); 
        $posisi = $request->input('posisi'); 
        $tab = $request->input('tab', 'edc'); 

        if (!$posisi) $posisi = date('Y-m-d');

        $dateCurr = Carbon::parse($posisi)->toDateString(); 
        $dateMtD  = Carbon::parse($posisi)->subMonthNoOverflow()->endOfMonth()->toDateString(); 
        $dateYtD  = Carbon::parse($posisi)->subYearNoOverflow()->endOfYear()->toDateString(); 
        $dateYoY  = Carbon::parse($posisi)->subYearNoOverflow()->endOfMonth()->toDateString(); 
        
        $datePrevMoM = Carbon::parse($posisi)->subMonthNoOverflow()->endOfMonth()->toDateString();

        $labels = [
            'curr' => Carbon::parse($dateCurr)->translatedFormat('d F Y'), 
            'mtd'  => Carbon::parse($dateMtD)->translatedFormat('M\'y'),
            'ytd'  => Carbon::parse($dateYtD)->translatedFormat('M\'y'),
            'yoy'  => Carbon::parse($dateYoY)->translatedFormat('M\'y'),
            'prev_mom' => Carbon::parse($datePrevMoM)->translatedFormat('d M Y'), 
        ];

        // =================================================================================
        // LOGIKA TAB 1: PERFORMANCE EDC
        // =================================================================================
        if ($tab === 'edc') {
            
            $q = DB::table('jumlah_merchant_detail')
                ->select(DB::raw('UPPER(NAMA_KANCA) as branch'))
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

            $q->whereIn(DB::raw('UPPER(NAMA_KANCA)'), array_map('strtoupper', $branches));
            $rows = $q->groupBy('branch')->get();

            $data = [];
            $total = [
                'mid_curr'=>0,'mid_mtd'=>0,'mid_ytd'=>0,'mid_yoy'=>0,
                'prod_curr'=>0,'prod_mtd'=>0,'prod_ytd'=>0,'prod_yoy'=>0,
                'sv_curr'=>0,'sv_mtd'=>0,'sv_yoy'=>0
            ];

            foreach ($rows as $r) {
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
                        'ytd_val'=>$r->prod_curr-$r->prod_ytd, 'yoy_val'=>$r->prod_curr-$r->prod_yoy, 'rka'=>0,'penc_pct'=>0
                    ],
                    'sv'=>[
                        'curr'=>round($r->sv_curr/1000000000,2), 'mtd_val'=>round(($r->sv_curr-$r->sv_mtd)/1000000000,2),
                        'mtd_pct'=>$r->sv_mtd>0?(($r->sv_curr-$r->sv_mtd)/$r->sv_mtd)*100:0,
                        'yoy_val'=>round(($r->sv_curr-$r->sv_yoy)/1000000000,2), 'rka'=>0,'penc_pct'=>0
                    ]
                ];

                $total['mid_curr'] += $r->mid_curr; $total['mid_mtd'] += $r->mid_mtd; $total['mid_ytd'] += $r->mid_ytd; $total['mid_yoy'] += $r->mid_yoy;
                $total['prod_curr'] += $r->prod_curr; $total['prod_mtd'] += $r->prod_mtd; $total['prod_ytd'] += $r->prod_ytd; $total['prod_yoy'] += $r->prod_yoy;
                $total['sv_curr'] += $r->sv_curr; $total['sv_mtd'] += $r->sv_mtd; $total['sv_yoy'] += $r->sv_yoy;
            }

            return response()->json([
                'status'=>'success', 'labels'=>$labels, 'data'=>$data,
                'total'=>[
                    'branch'=>'TOTAL AREA 6',
                    'mid'=>[
                        'curr'=>$total['mid_curr'], 'mtd'=>$total['mid_mtd'], 'ytd'=>$total['mid_ytd'], 'yoy'=>$total['mid_yoy'],
                        'mtd_val'=>$total['mid_curr']-$total['mid_mtd'], 'mtd_pct'=>$total['mid_mtd']>0?(($total['mid_curr']-$total['mid_mtd'])/$total['mid_mtd'])*100:0,
                        'ytd_val'=>$total['mid_curr']-$total['mid_ytd'], 'yoy_val'=>$total['mid_curr']-$total['mid_yoy']
                    ],
                    'prod'=>[
                        'curr'=>$total['prod_curr'], 'pct_tid'=>$total['mid_curr']>0?($total['prod_curr']/$total['mid_curr'])*100:0,
                        'mtd_val'=>$total['prod_curr']-$total['prod_mtd'], 'mtd_pct'=>$total['prod_mtd']>0?(($total['prod_curr']-$total['prod_mtd'])/$total['prod_mtd'])*100:0,
                        'ytd_val'=>$total['prod_curr']-$total['prod_ytd'], 'yoy_val'=>$total['prod_curr']-$total['prod_yoy'], 'rka'=>0,'penc_pct'=>0
                    ],
                    'sv'=>[
                        'curr'=>round($total['sv_curr']/1000000000,2), 'mtd_val'=>round(($total['sv_curr']-$total['sv_mtd'])/1000000000,2),
                        'mtd_pct'=>$total['sv_mtd']>0?(($total['sv_curr']-$total['sv_mtd'])/$total['sv_mtd'])*100:0,
                        'yoy_val'=>round(($total['sv_curr']-$total['sv_yoy'])/1000000000,2), 'rka'=>0,'penc_pct'=>0
                    ]
                ]
            ]);
        }

        // =================================================================================
        // LOGIKA TAB 2: MID & TID (LAMA)
        // =================================================================================
        elseif ($tab === 'mid_tid') {
            $query = DB::table('jumlah_merchant_detail')
                ->select(DB::raw('UPPER(NAMA_KANCA) as branch'))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_yoy", [$dateYoY])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd", [$dateMtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_yoy", [$dateYoY]);

            $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), array_map('strtoupper', $branches));
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
                'branch' => 'TOTAL AREA 6',
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

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB 3: PRODUKTIVITAS EDC MoM
        // =================================================================================
        elseif ($tab === 'prod_mom') {
            $q = DB::table('jumlah_merchant_detail')
                ->select(DB::raw('UPPER(NAMA_KANCA) as branch'))
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

            $q->whereIn(DB::raw('UPPER(NAMA_KANCA)'), array_map('strtoupper', $branches));
            $rawData = $q->groupBy('branch')->get();

            $data = [];
            $totals = [
                'sv0_curr' => 0, 'sv0_mtd' => 0, 'sv1_15_curr' => 0, 'sv1_15_mtd' => 0,
                'prod_curr' => 0, 'prod_mtd' => 0, 'tid_curr' => 0, 'tid_mtd' => 0,
                'sv_vol_curr' => 0, 'sv_vol_mtd' => 0
            ];

            foreach ($rawData as $row) {
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
                    'prod' => ['mtd' => $row->prod_mtd, 'curr' => $row->prod_curr, 'mom' => $prod_mom, 'pct' => round($prod_pct, 1), 'rka' => 0, 'gap' => 0, 'penc' => 0],
                    'tid' => ['mtd' => $row->tid_mtd, 'curr' => $row->tid_curr, 'mom' => $tid_mom, 'pct' => round($tid_pct, 1)],
                    'sv_vol' => ['mtd' => round($sv_vol_mtd, 2), 'curr' => round($sv_vol_curr, 2), 'mom' => round($sv_vol_mom, 2), 'pct' => round($sv_vol_pct, 1)]
                ];

                $totals['sv0_curr'] += $row->sv0_curr; $totals['sv0_mtd'] += $row->sv0_mtd;
                $totals['sv1_15_curr'] += $row->sv1_15_curr; $totals['sv1_15_mtd'] += $row->sv1_15_mtd;
                $totals['prod_curr'] += $row->prod_curr; $totals['prod_mtd'] += $row->prod_mtd;
                $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd;
                $totals['sv_vol_curr'] += $sv_vol_curr; $totals['sv_vol_mtd'] += $sv_vol_mtd;
            }

            $t_sv0_mom = $totals['sv0_curr'] - $totals['sv0_mtd']; $t_sv0_pct = $totals['sv0_mtd'] > 0 ? ($t_sv0_mom / $totals['sv0_mtd']) * 100 : 0;
            $t_sv1_mom = $totals['sv1_15_curr'] - $totals['sv1_15_mtd']; $t_sv1_pct = $totals['sv1_15_mtd'] > 0 ? ($t_sv1_mom / $totals['sv1_15_mtd']) * 100 : 0;
            $t_prod_mom = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mom / $totals['prod_mtd']) * 100 : 0;
            $t_tid_mom = $totals['tid_curr'] - $totals['tid_mtd']; $t_tid_pct = $totals['tid_mtd'] > 0 ? ($t_tid_mom / $totals['tid_mtd']) * 100 : 0;
            $t_vol_mom = $totals['sv_vol_curr'] - $totals['sv_vol_mtd']; $t_vol_pct = $totals['sv_vol_mtd'] > 0 ? ($t_vol_mom / $totals['sv_vol_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => 'TOTAL AREA 6',
                'sv0' => ['mtd' => $totals['sv0_mtd'], 'curr' => $totals['sv0_curr'], 'mom' => $t_sv0_mom, 'pct' => round($t_sv0_pct, 1)],
                'sv1_15' => ['mtd' => $totals['sv1_15_mtd'], 'curr' => $totals['sv1_15_curr'], 'mom' => $t_sv1_mom, 'pct' => round($t_sv1_pct, 1)],
                'prod' => ['mtd' => $totals['prod_mtd'], 'curr' => $totals['prod_curr'], 'mom' => $t_prod_mom, 'pct' => round($t_prod_pct, 1), 'rka' => 0, 'gap' => 0, 'penc' => 0],
                'tid' => ['mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'], 'mom' => $t_tid_mom, 'pct' => round($t_tid_pct, 1)],
                'sv_vol' => ['mtd' => round($totals['sv_vol_mtd'],2), 'curr' => round($totals['sv_vol_curr'],2), 'mom' => round($t_vol_mom,2), 'pct' => round($t_vol_pct, 1)]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB QRIS: FORMAT MATRIKS
        // =================================================================================
        elseif ($tab === 'qris') {
            
            $q1 = DB::table('merchant_qris')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_ytd", [$dateYtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as jml_yoy", [$dateYoY]);
            $q1->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches));
            $dataQris = $q1->groupBy('branch')->get()->keyBy('branch');

            $q2 = DB::table('merchant_qris_volume')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_mtd", [$dateMtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_ytd", [$dateYtD])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_yoy", [$dateYoY])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_ytd", [$dateYtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_yoy", [$dateYoY]);
            
            $q2->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches));
            $dataVol = $q2->groupBy('branch')->get()->keyBy('branch');

            $data = [];
            $totals = [
                'jml_curr' => 0, 'jml_mtd' => 0, 'jml_ytd' => 0, 'jml_yoy' => 0,
                'prod_curr' => 0, 'prod_mtd' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0,
                'vol_curr' => 0, 'vol_mtd' => 0, 'vol_ytd' => 0, 'vol_yoy' => 0
            ];

            foreach ($branches as $branchRaw) {
                $b = strtoupper($branchRaw);
                $rowQ = $dataQris->get($b);
                $rowV = $dataVol->get($b);

                $jml_curr = $rowQ->jml_curr ?? 0; $jml_mtd = $rowQ->jml_mtd ?? 0; $jml_ytd = $rowQ->jml_ytd ?? 0; $jml_yoy = $rowQ->jml_yoy ?? 0;
                $prod_curr = $rowV->prod_curr ?? 0; $prod_mtd = $rowV->prod_mtd ?? 0; $prod_ytd = $rowV->prod_ytd ?? 0; $prod_yoy = $rowV->prod_yoy ?? 0;
                
                $vol_curr = ($rowV->vol_curr ?? 0) / 1000000; 
                $vol_mtd = ($rowV->vol_mtd ?? 0) / 1000000; 
                $vol_ytd = ($rowV->vol_ytd ?? 0) / 1000000; 
                $vol_yoy = ($rowV->vol_yoy ?? 0) / 1000000;

                $jml_mtd_val = $jml_curr - $jml_mtd; $jml_mtd_pct = $jml_mtd > 0 ? ($jml_mtd_val / $jml_mtd) * 100 : 0;
                $prod_mtd_val = $prod_curr - $prod_mtd; $prod_mtd_pct = $prod_mtd > 0 ? ($prod_mtd_val / $prod_mtd) * 100 : 0;
                $vol_mtd_val = $vol_curr - $vol_mtd; $vol_mtd_pct = $vol_mtd > 0 ? ($vol_mtd_val / $vol_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $b,
                    'jml' => [
                        'curr' => $jml_curr, 'mtd_val' => $jml_mtd_val, 'mtd_pct' => round($jml_mtd_pct, 1),
                        'ytd_val' => $jml_curr - $jml_ytd, 'yoy_val' => $jml_curr - $jml_yoy
                    ],
                    'prod' => [
                        'curr' => $prod_curr, 'mtd_val' => $prod_mtd_val, 'mtd_pct' => round($prod_mtd_pct, 1),
                        'ytd_val' => $prod_curr - $prod_ytd, 'yoy_val' => $prod_curr - $prod_yoy
                    ],
                    'vol' => [
                        'curr' => round($vol_curr, 2), 'mtd_val' => round($vol_mtd_val, 2), 'mtd_pct' => round($vol_mtd_pct, 1),
                        'ytd_val' => round($vol_curr - $vol_ytd, 2), 'yoy_val' => round($vol_curr - $vol_yoy, 2)
                    ]
                ];

                $totals['jml_curr'] += $jml_curr; $totals['jml_mtd'] += $jml_mtd; $totals['jml_ytd'] += $jml_ytd; $totals['jml_yoy'] += $jml_yoy;
                $totals['prod_curr'] += $prod_curr; $totals['prod_mtd'] += $prod_mtd; $totals['prod_ytd'] += $prod_ytd; $totals['prod_yoy'] += $prod_yoy;
                $totals['vol_curr'] += $vol_curr; $totals['vol_mtd'] += $vol_mtd; $totals['vol_ytd'] += $vol_ytd; $totals['vol_yoy'] += $vol_yoy;
            }

            $t_jml_mtd_val = $totals['jml_curr'] - $totals['jml_mtd']; $t_jml_mtd_pct = $totals['jml_mtd'] > 0 ? ($t_jml_mtd_val / $totals['jml_mtd']) * 100 : 0;
            $t_prod_mtd_val = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_mtd_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mtd_val / $totals['prod_mtd']) * 100 : 0;
            $t_vol_mtd_val = $totals['vol_curr'] - $totals['vol_mtd']; $t_vol_mtd_pct = $totals['vol_mtd'] > 0 ? ($t_vol_mtd_val / $totals['vol_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => 'TOTAL AREA 6',
                'jml' => [
                    'curr' => $totals['jml_curr'], 'mtd_val' => $t_jml_mtd_val, 'mtd_pct' => round($t_jml_mtd_pct, 1),
                    'ytd_val' => $totals['jml_curr'] - $totals['jml_ytd'], 'yoy_val' => $totals['jml_curr'] - $totals['jml_yoy']
                ],
                'prod' => [
                    'curr' => $totals['prod_curr'], 'mtd_val' => $t_prod_mtd_val, 'mtd_pct' => round($t_prod_mtd_pct, 1),
                    'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy']
                ],
                'vol' => [
                    'curr' => round($totals['vol_curr'], 2), 'mtd_val' => round($t_vol_mtd_val, 2), 'mtd_pct' => round($t_vol_mtd_pct, 1),
                    'ytd_val' => round($totals['vol_curr'] - $totals['vol_ytd'], 2), 'yoy_val' => round($totals['vol_curr'] - $totals['vol_yoy'], 2)
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // LOGIKA TAB QRIS MoM
        // =================================================================================
        elseif ($tab === 'qris_mom') {

            $q1 = DB::table('merchant_qris')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_prev", [$datePrevMoM])
                ->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches))
                ->groupBy('branch')
                ->get()
                ->keyBy('branch');

            $q2 = DB::table('merchant_qris_volume')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME=0 THEN 1 END) as sv0_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME=0 THEN 1 END) as sv0_prev", [$datePrevMoM])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME>=50000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' AND MERCHANT_QRIS_VOLUME>=50000 THEN 1 END) as prod_prev", [$datePrevMoM])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI)=? AND JENIS='AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_prev", [$datePrevMoM])
                ->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches))
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

            foreach ($branches as $branchRaw) {
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
                        'rka' => '-', 'gap' => '-', 'penc' => '-'
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
                'branch' => 'TOTAL AREA 6',
                'sv0' => [
                    'prev' => $totals['sv0_prev'], 'curr' => $totals['sv0_curr'], 'mom' => $t_sv0_mom, 'pct' => round($t_sv0_pct, 1)
                ],
                'prod' => [
                    'prev' => $totals['prod_prev'], 'curr' => $totals['prod_curr'], 'mom' => $t_prod_mom, 'pct' => round($t_prod_pct, 1),
                    'rka' => '-', 'gap' => '-', 'penc' => '-'
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
                'trx' => ['curr' => 0, 'mtd' => 0, 'yoy' => 0],
                'volume' => ['curr' => 0, 'mtd' => 0, 'yoy' => 0]
            ];

            foreach ($branches as $branch) {

                // STRICT MATCH (=) & UPPERCASE CABANG
                $currData = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->whereRaw('UPPER(cabang) = ?', [strtoupper($branch)])
                    ->where('periode', $periodeCurr)
                    ->get();

                $prevData = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->whereRaw('UPPER(cabang) = ?', [strtoupper($branch)])
                    ->where('periode', $periodePrev)
                    ->get();

                $yoyData = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->whereRaw('UPPER(cabang) = ?', [strtoupper($branch)])
                    ->where('periode', $periodeYoY)
                    ->get();

                $ytdData = DB::table('brilink_web_laporan_summary_transaksi_brilink_web')
                    ->whereRaw('UPPER(cabang) = ?', [strtoupper($branch)])
                    ->where('periode', $periodeYtD)
                    ->get();

                // 🔥 VALIDASI SUPER AMAN: Cek apakah data bulan ini memang ada di DB
                $hasCurrData = $currData->count() > 0;

                // LOGIKA METRIK AMAN DENGAN VARIABEL TERPISAH
                $agen_curr = $currData->count();
                $agen_prev = $prevData->count();
                $agen_yoy  = $yoyData->count();
                $agen_ytd  = $ytdData->count();

                $juragan_curr = $currData->filter(fn($x) => $x->total_fee >= 750000)->count();
                $juragan_prev = $prevData->filter(fn($x) => $x->total_fee >= 750000)->count();
                $juragan_yoy  = $yoyData->filter(fn($x) => $x->total_fee >= 750000)->count();
                $juragan_ytd  = $ytdData->filter(fn($x) => $x->total_fee >= 750000)->count();

                $bep_curr = $currData->filter(fn($x) => $x->total_fee >= 150000)->count();
                $bep_prev = $prevData->filter(fn($x) => $x->total_fee >= 150000)->count();
                $bep_yoy  = $yoyData->filter(fn($x) => $x->total_fee >= 150000)->count();
                $bep_ytd  = $ytdData->filter(fn($x) => $x->total_fee >= 150000)->count();

                $trx_curr = $currData->sum('total_transaksi');
                $trx_prev = $prevData->sum('total_transaksi');
                $trx_yoy  = $yoyData->sum('total_transaksi');

                $vol_curr = $currData->sum('total_nominal');
                $vol_prev = $prevData->sum('total_nominal');
                $vol_yoy  = $yoyData->sum('total_nominal');

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
                $trx_yoy_val = $hasCurrData ? ($trx_curr - $trx_yoy) : 0;

                $vol_mtd = $hasCurrData ? ($vol_curr - $vol_prev) : 0;
                $vol_yoy_val = $hasCurrData ? ($vol_curr - $vol_yoy) : 0;

                $data[] = [
                    'branch' => $branch,
                    'agen' => [
                        'curr' => $agen_curr, 'mtd' => $agen_mtd, 'ytd' => $agen_ytd_val, 'yoy' => $agen_yoy_val,
                    ],
                    'juragan' => [
                        'curr' => $juragan_curr, 'mtd' => $juragan_mtd, 'ytd' => $juragan_ytd_val, 'yoy' => $juragan_yoy_val,
                    ],
                    'bep' => [
                        'curr' => $bep_curr, 'mtd' => $bep_mtd, 'ytd' => $bep_ytd_val, 'yoy' => $bep_yoy_val,
                    ],
                    'trx' => [
                        'curr' => $trx_curr, 'mtd' => $trx_mtd, 'yoy' => $trx_yoy_val,
                    ],
                    'volume' => [
                        'curr' => $vol_curr, 'mtd' => $vol_mtd, 'yoy' => $vol_yoy_val,
                    ],
                ];
                
                $totals['agen']['curr'] += $agen_curr; $totals['agen']['mtd'] += $agen_mtd; $totals['agen']['ytd'] += $agen_ytd_val; $totals['agen']['yoy'] += $agen_yoy_val;
                $totals['juragan']['curr'] += $juragan_curr; $totals['juragan']['mtd'] += $juragan_mtd; $totals['juragan']['ytd'] += $juragan_ytd_val; $totals['juragan']['yoy'] += $juragan_yoy_val;
                $totals['bep']['curr'] += $bep_curr; $totals['bep']['mtd'] += $bep_mtd; $totals['bep']['ytd'] += $bep_ytd_val; $totals['bep']['yoy'] += $bep_yoy_val;
                $totals['trx']['curr'] += $trx_curr; $totals['trx']['mtd'] += $trx_mtd; $totals['trx']['yoy'] += $trx_yoy_val;
                $totals['volume']['curr'] += $vol_curr; $totals['volume']['mtd'] += $vol_mtd; $totals['volume']['yoy'] += $vol_yoy_val;
            }

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'labels' => [
                    'curr' => $periodeCurr,
                ],
                'total' => [
                    'branch' => 'TOTAL AREA 6',
                    'agen' => $totals['agen'],
                    'juragan' => $totals['juragan'],
                    'bep' => $totals['bep'],
                    'trx' => $totals['trx'],
                    'volume' => $totals['volume']
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
}
