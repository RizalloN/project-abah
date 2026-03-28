<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DataReportController extends Controller
{
    public function performanceEdc()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 1; 

        return view('report.performance-edc', compact('branches', 'id_report'));
    }

    public function performanceQris()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 2; 

        return view('report.performance-qris', compact('branches', 'id_report'));
    }

    public function fetchData(Request $request)
    {
        $id_report = $request->input('id_report', 1);
        $branches = $request->input('branches', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']); 
        $posisi = $request->input('posisi'); 
        $tab = $request->input('tab', 'edc'); 

        if (!$posisi) $posisi = date('Y-m-d');

        $dateCurr = Carbon::parse($posisi)->toDateString(); 
        $dateMtD  = Carbon::parse($posisi)->subMonth()->endOfMonth()->toDateString(); 
        $dateYtD  = Carbon::parse($posisi)->subYear()->endOfYear()->toDateString(); 
        $dateYoY  = Carbon::parse($posisi)->subYear()->endOfMonth()->toDateString(); 
        
        // Khusus MoM QRIS (Tanggal yang sama di bulan sebelumnya)
        $datePrevMoM = Carbon::parse($posisi)->subMonth()->toDateString();

        $labels = [
            'curr'     => Carbon::parse($dateCurr)->translatedFormat('d M y'), // 28 Feb 26
            'mtd'      => Carbon::parse($dateMtD)->translatedFormat('M\'y'),
            'ytd'      => Carbon::parse($dateYtD)->translatedFormat('M\'y'),
            'yoy'      => Carbon::parse($dateYoY)->translatedFormat('M\'y'),
            'prev_mom' => Carbon::parse($datePrevMoM)->translatedFormat('d M y'), // 28 Jan 26
        ];

        // =================================================================================
        // LOGIKA TAB 1: PERFORMANCE EDC
        // =================================================================================
        if ($tab === 'edc') {
            // ... (Logika EDC dibiarkan utuh jika masih ada di file aslimu)
        } 
        elseif ($tab === 'mid_tid') {
            // ... (Logika MID TID dibiarkan utuh)
        }
        
        // =================================================================================
        // LOGIKA TAB QRIS: FORMAT HORIZONTAL 
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
                $vol_curr = ($rowV->vol_curr ?? 0) / 1000000000; $vol_mtd = ($rowV->vol_mtd ?? 0) / 1000000000; $vol_ytd = ($rowV->vol_ytd ?? 0) / 1000000000; $vol_yoy = ($rowV->vol_yoy ?? 0) / 1000000000;

                $pct_produktif = $jml_curr > 0 ? ($prod_curr / $jml_curr) * 100 : 0;
                $jml_mtd_val = $jml_curr - $jml_mtd; $jml_mtd_pct = $jml_mtd > 0 ? ($jml_mtd_val / $jml_mtd) * 100 : 0;
                $prod_mtd_val = $prod_curr - $prod_mtd; $prod_mtd_pct = $prod_mtd > 0 ? ($prod_mtd_val / $prod_mtd) * 100 : 0;
                $vol_mtd_val = $vol_curr - $vol_mtd; $vol_mtd_pct = $vol_mtd > 0 ? ($vol_mtd_val / $vol_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $b,
                    'jml' => [
                        'yoy' => $jml_yoy, 'ytd' => $jml_ytd, 'mtd' => $jml_mtd, 'curr' => $jml_curr,
                        'mtd_val' => $jml_mtd_val, 'mtd_pct' => round($jml_mtd_pct, 1),
                        'ytd_val' => $jml_curr - $jml_ytd, 'yoy_val' => $jml_curr - $jml_yoy,
                        'rka' => 0, 'penc_pct' => 0 
                    ],
                    'prod' => [
                        'yoy' => $prod_yoy, 'ytd' => $prod_ytd, 'mtd' => $prod_mtd, 'curr' => $prod_curr,
                        'pct_jml' => round($pct_produktif, 1),
                        'mtd_val' => $prod_mtd_val, 'mtd_pct' => round($prod_mtd_pct, 1),
                        'ytd_val' => $prod_curr - $prod_ytd, 'yoy_val' => $prod_curr - $prod_yoy,
                        'rka' => 0, 'penc_pct' => 0 
                    ],
                    'vol' => [
                        'yoy' => round($vol_yoy, 2), 'ytd' => round($vol_ytd, 2), 'mtd' => round($vol_mtd, 2), 'curr' => round($vol_curr, 2),
                        'mtd_val' => round($vol_mtd_val, 2), 'mtd_pct' => round($vol_mtd_pct, 1),
                        'ytd_val' => round($vol_curr - $vol_ytd, 2), 'yoy_val' => round($vol_curr - $vol_yoy, 2),
                        'rka' => 0, 'penc_pct' => 0 
                    ]
                ];

                $totals['jml_curr'] += $jml_curr; $totals['jml_mtd'] += $jml_mtd; $totals['jml_ytd'] += $jml_ytd; $totals['jml_yoy'] += $jml_yoy;
                $totals['prod_curr'] += $prod_curr; $totals['prod_mtd'] += $prod_mtd; $totals['prod_ytd'] += $prod_ytd; $totals['prod_yoy'] += $prod_yoy;
                $totals['vol_curr'] += $vol_curr; $totals['vol_mtd'] += $vol_mtd; $totals['vol_ytd'] += $vol_ytd; $totals['vol_yoy'] += $vol_yoy;
            }

            $t_pct_prod = $totals['jml_curr'] > 0 ? ($totals['prod_curr'] / $totals['jml_curr']) * 100 : 0;
            $t_jml_mtd_val = $totals['jml_curr'] - $totals['jml_mtd']; $t_jml_mtd_pct = $totals['jml_mtd'] > 0 ? ($t_jml_mtd_val / $totals['jml_mtd']) * 100 : 0;
            $t_prod_mtd_val = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_mtd_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mtd_val / $totals['prod_mtd']) * 100 : 0;
            $t_vol_mtd_val = $totals['vol_curr'] - $totals['vol_mtd']; $t_vol_mtd_pct = $totals['vol_mtd'] > 0 ? ($t_vol_mtd_val / $totals['vol_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => 'TOTAL AREA 6',
                'jml' => [
                    'yoy' => $totals['jml_yoy'], 'ytd' => $totals['jml_ytd'], 'mtd' => $totals['jml_mtd'], 'curr' => $totals['jml_curr'],
                    'mtd_val' => $t_jml_mtd_val, 'mtd_pct' => round($t_jml_mtd_pct, 1),
                    'ytd_val' => $totals['jml_curr'] - $totals['jml_ytd'], 'yoy_val' => $totals['jml_curr'] - $totals['jml_yoy'], 'rka' => 0, 'penc_pct' => 0
                ],
                'prod' => [
                    'yoy' => $totals['prod_yoy'], 'ytd' => $totals['prod_ytd'], 'mtd' => $totals['prod_mtd'], 'curr' => $totals['prod_curr'],
                    'pct_jml' => round($t_pct_prod, 1),
                    'mtd_val' => $t_prod_mtd_val, 'mtd_pct' => round($t_prod_mtd_pct, 1),
                    'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'], 'rka' => 0, 'penc_pct' => 0
                ],
                'vol' => [
                    'yoy' => round($totals['vol_yoy'], 2), 'ytd' => round($totals['vol_ytd'], 2), 'mtd' => round($totals['vol_mtd'], 2), 'curr' => round($totals['vol_curr'], 2),
                    'mtd_val' => round($t_vol_mtd_val, 2), 'mtd_pct' => round($t_vol_mtd_pct, 1),
                    'ytd_val' => round($totals['vol_curr'] - $totals['vol_ytd'], 2), 'yoy_val' => round($totals['vol_curr'] - $totals['vol_yoy'], 2), 'rka' => 0, 'penc_pct' => 0
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }

        // =================================================================================
        // 🔥 LOGIKA TAB QRIS MoM (TAB BARU)
        // =================================================================================
        elseif ($tab === 'qris_mom') {
            
            // 1. Total Store ID (dari merchant_qris)
            $q1 = DB::table('merchant_qris')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN NILAI ELSE 0 END) as store_prev", [$datePrevMoM]);
            $q1->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches));
            $dataStoreId = $q1->groupBy('branch')->get()->keyBy('branch');

            // 2. SV 0, Produktif, SV Bulan Berjalan (dari merchant_qris_volume)
            $q2 = DB::table('merchant_qris_volume')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                // SV 0 (QRIS Volume = 0)
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME = 0 THEN 1 END) as sv0_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME = 0 THEN 1 END) as sv0_prev", [$datePrevMoM])
                // Produktif (QRIS Volume >= 50.000)
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' AND MERCHANT_QRIS_VOLUME >= 50000 THEN 1 END) as prod_prev", [$datePrevMoM])
                // Sales Volume Akumulasi
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? AND JENIS = 'AKUMULASI' THEN MERCHANT_QRIS_VOLUME ELSE 0 END) as vol_prev", [$datePrevMoM]);
            $q2->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches));
            $dataVol = $q2->groupBy('branch')->get()->keyBy('branch');

            $data = [];
            $totals = [
                'sv0_curr' => 0, 'sv0_prev' => 0,
                'prod_curr' => 0, 'prod_prev' => 0,
                'store_curr' => 0, 'store_prev' => 0,
                'vol_curr' => 0, 'vol_prev' => 0
            ];

            foreach ($branches as $branchRaw) {
                $b = strtoupper($branchRaw);
                $rowStore = $dataStoreId->get($b);
                $rowV = $dataVol->get($b);

                $store_curr = $rowStore->store_curr ?? 0; $store_prev = $rowStore->store_prev ?? 0;
                $sv0_curr = $rowV->sv0_curr ?? 0; $sv0_prev = $rowV->sv0_prev ?? 0;
                $prod_curr = $rowV->prod_curr ?? 0; $prod_prev = $rowV->prod_prev ?? 0;
                
                // Konversi SV ke Milyar
                $vol_curr = ($rowV->vol_curr ?? 0) / 1000000000; 
                $vol_prev = ($rowV->vol_prev ?? 0) / 1000000000; 

                // Perhitungan MoM (Curr - Prev)
                $sv0_mom = $sv0_curr - $sv0_prev; $sv0_pct = $sv0_prev > 0 ? ($sv0_mom / $sv0_prev) * 100 : 0;
                $prod_mom = $prod_curr - $prod_prev; $prod_pct = $prod_prev > 0 ? ($prod_mom / $prod_prev) * 100 : 0;
                $store_mom = $store_curr - $store_prev; $store_pct = $store_prev > 0 ? ($store_mom / $store_prev) * 100 : 0;
                $vol_mom = $vol_curr - $vol_prev; $vol_pct = $vol_prev > 0 ? ($vol_mom / $vol_prev) * 100 : 0;

                $data[] = [
                    'branch' => $b,
                    'sv0' => [
                        'prev' => $sv0_prev, 'curr' => $sv0_curr, 'mom' => $sv0_mom, 'pct' => round($sv0_pct, 1)
                    ],
                    'prod' => [
                        'prev' => $prod_prev, 'curr' => $prod_curr, 'mom' => $prod_mom, 'pct' => round($prod_pct, 1),
                        'rka' => '', 'gap' => '', 'penc' => '' // Sesuai permintaan: dikosongi
                    ],
                    'store' => [
                        'prev' => $store_prev, 'curr' => $store_curr, 'mom' => $store_mom, 'pct' => round($store_pct, 1)
                    ],
                    'vol' => [
                        'prev' => round($vol_prev, 2), 'curr' => round($vol_curr, 2), 'mom' => round($vol_mom, 2), 'pct' => round($vol_pct, 1)
                    ]
                ];

                $totals['sv0_curr'] += $sv0_curr; $totals['sv0_prev'] += $sv0_prev;
                $totals['prod_curr'] += $prod_curr; $totals['prod_prev'] += $prod_prev;
                $totals['store_curr'] += $store_curr; $totals['store_prev'] += $store_prev;
                $totals['vol_curr'] += $vol_curr; $totals['vol_prev'] += $vol_prev;
            }

            // Total Selisih MoM
            $t_sv0_mom = $totals['sv0_curr'] - $totals['sv0_prev']; $t_sv0_pct = $totals['sv0_prev'] > 0 ? ($t_sv0_mom / $totals['sv0_prev']) * 100 : 0;
            $t_prod_mom = $totals['prod_curr'] - $totals['prod_prev']; $t_prod_pct = $totals['prod_prev'] > 0 ? ($t_prod_mom / $totals['prod_prev']) * 100 : 0;
            $t_store_mom = $totals['store_curr'] - $totals['store_prev']; $t_store_pct = $totals['store_prev'] > 0 ? ($t_store_mom / $totals['store_prev']) * 100 : 0;
            $t_vol_mom = $totals['vol_curr'] - $totals['vol_prev']; $t_vol_pct = $totals['vol_prev'] > 0 ? ($t_vol_mom / $totals['vol_prev']) * 100 : 0;

            $grandTotal = [
                'branch' => 'TOTAL AREA 6',
                'sv0' => [
                    'prev' => $totals['sv0_prev'], 'curr' => $totals['sv0_curr'], 'mom' => $t_sv0_mom, 'pct' => round($t_sv0_pct, 1)
                ],
                'prod' => [
                    'prev' => $totals['prod_prev'], 'curr' => $totals['prod_curr'], 'mom' => $t_prod_mom, 'pct' => round($t_prod_pct, 1),
                    'rka' => '', 'gap' => '', 'penc' => ''
                ],
                'store' => [
                    'prev' => $totals['store_prev'], 'curr' => $totals['store_curr'], 'mom' => $t_store_mom, 'pct' => round($t_store_pct, 1)
                ],
                'vol' => [
                    'prev' => round($totals['vol_prev'], 2), 'curr' => round($totals['vol_curr'], 2), 'mom' => round($t_vol_mom, 2), 'pct' => round($t_vol_pct, 1)
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }
    }
}