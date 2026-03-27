<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NamaReport;
use Carbon\Carbon;

class DataReportController extends Controller
{
    public function performanceEdc()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 1; 

        return view('report.performance-edc', compact('branches', 'id_report'));
    }

    public function fetchData(Request $request)
    {
        $id_report = $request->input('id_report', 1);
        $branches = $request->input('branches', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']); 
        $posisi = $request->input('posisi'); 
        $tab = $request->input('tab', 'edc'); // Tangkap tab yang sedang aktif

        if (!$posisi) $posisi = date('Y-m-d');

        $dateCurr = Carbon::parse($posisi)->toDateString(); 
        $dateMtD  = Carbon::parse($posisi)->subMonth()->endOfMonth()->toDateString(); 
        $dateYtD  = Carbon::parse($posisi)->subYear()->endOfYear()->toDateString(); 
        $dateYoY  = Carbon::parse($posisi)->subYear()->endOfMonth()->toDateString(); 

        $labels = [
            'curr' => Carbon::parse($dateCurr)->translatedFormat('d M Y'),
            'mtd'  => Carbon::parse($dateMtD)->translatedFormat('M\'y'),
            'ytd'  => Carbon::parse($dateYtD)->translatedFormat('M\'y'),
            'yoy'  => Carbon::parse($dateYoY)->translatedFormat('M\'y'),
        ];

        // =================================================================================
        // LOGIKA TAB 1: PERFORMANCE EDC (Menggabungkan 2 Tabel)
        // =================================================================================
        if ($tab === 'edc') {
            
            // Query 1: Ambil MID & TID Produktif dari jumlah_merchant_detail
            $q1 = DB::table('jumlah_merchant_detail')
                ->select(DB::raw('UPPER(NAMA_KANCA) as branch'))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_yoy", [$dateYoY])
                
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr_total", [$dateCurr])
                
                // Asumsi: '>= 15 Juta' di kolom TIERING_SALES_VOLUME mengandung kata "15". Sesuaikan LIKE jika teksnya beda!
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME LIKE '%15%' THEN TID END) as prod_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME LIKE '%15%' THEN TID END) as prod_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME LIKE '%15%' THEN TID END) as prod_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? AND TIERING_SALES_VOLUME LIKE '%15%' THEN TID END) as prod_yoy", [$dateYoY]);
            
            $q1->whereIn(DB::raw('UPPER(NAMA_KANCA)'), array_map('strtoupper', $branches));
            $dataMdt = $q1->groupBy('branch')->get()->keyBy('branch');

            // Query 2: Ambil SV Akumulasi dari sv_merchant (Cast Varchar ke Decimal)
            $q2 = DB::table('sv_merchant')
                ->select(DB::raw('UPPER(NAMA_KCI) as branch'))
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SV_MERCHANT, ',', '') AS DECIMAL(15,2)) ELSE 0 END) as sv_curr", [$dateCurr])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SV_MERCHANT, ',', '') AS DECIMAL(15,2)) ELSE 0 END) as sv_mtd", [$dateMtD])
                ->selectRaw("SUM(CASE WHEN DATE(POSISI) = ? THEN CAST(REPLACE(SV_MERCHANT, ',', '') AS DECIMAL(15,2)) ELSE 0 END) as sv_yoy", [$dateYoY]);

            $q2->whereIn(DB::raw('UPPER(NAMA_KCI)'), array_map('strtoupper', $branches));
            $dataSv = $q2->groupBy('branch')->get()->keyBy('branch');

            $data = [];
            $totals = [
                'mid_curr' => 0, 'mid_mtd' => 0, 'mid_ytd' => 0, 'mid_yoy' => 0,
                'tid_curr_total' => 0, 'prod_curr' => 0, 'prod_mtd' => 0, 'prod_ytd' => 0, 'prod_yoy' => 0,
                'sv_curr' => 0, 'sv_mtd' => 0, 'sv_yoy' => 0
            ];

            foreach ($branches as $branchRaw) {
                $b = strtoupper($branchRaw);
                $rowMdt = $dataMdt->get($b);
                $rowSv = $dataSv->get($b);

                // --- Pengisian Variabel Baris ---
                $mid_curr = $rowMdt->mid_curr ?? 0; $mid_mtd = $rowMdt->mid_mtd ?? 0; 
                $mid_ytd = $rowMdt->mid_ytd ?? 0;   $mid_yoy = $rowMdt->mid_yoy ?? 0;
                
                $tid_curr_tot = $rowMdt->tid_curr_total ?? 0;
                $prod_curr = $rowMdt->prod_curr ?? 0; $prod_mtd = $rowMdt->prod_mtd ?? 0; 
                $prod_ytd = $rowMdt->prod_ytd ?? 0;   $prod_yoy = $rowMdt->prod_yoy ?? 0;
                
                // Asumsi data Rp dikonversi ke Milyar (Dibagi 1.000.000.000)
                $sv_curr = ($rowSv->sv_curr ?? 0) / 1000000000;
                $sv_mtd = ($rowSv->sv_mtd ?? 0) / 1000000000;
                $sv_yoy = ($rowSv->sv_yoy ?? 0) / 1000000000;

                // --- Kalkulasi ---
                $pct_produktif = $tid_curr_tot > 0 ? ($prod_curr / $tid_curr_tot) * 100 : 0;
                
                $mid_mtd_val = $mid_curr - $mid_mtd; $mid_mtd_pct = $mid_mtd > 0 ? ($mid_mtd_val / $mid_mtd) * 100 : 0;
                $prod_mtd_val = $prod_curr - $prod_mtd; $prod_mtd_pct = $prod_mtd > 0 ? ($prod_mtd_val / $prod_mtd) * 100 : 0;
                $sv_mtd_val = $sv_curr - $sv_mtd; $sv_mtd_pct = $sv_mtd > 0 ? ($sv_mtd_val / $sv_mtd) * 100 : 0;

                $data[] = [
                    'branch' => $b,
                    'mid' => [
                        'yoy' => $mid_yoy, 'ytd' => $mid_ytd, 'mtd' => $mid_mtd, 'curr' => $mid_curr,
                        'mtd_val' => $mid_mtd_val, 'mtd_pct' => round($mid_mtd_pct, 1),
                        'ytd_val' => $mid_curr - $mid_ytd, 'yoy_val' => $mid_curr - $mid_yoy
                    ],
                    'prod' => [
                        'curr' => $prod_curr, 'pct_tid' => round($pct_produktif, 1),
                        'mtd_val' => $prod_mtd_val, 'mtd_pct' => round($prod_mtd_pct, 1),
                        'ytd_val' => $prod_curr - $prod_ytd, 'yoy_val' => $prod_curr - $prod_yoy,
                        'rka' => 0, 'penc_pct' => 0
                    ],
                    'sv' => [
                        'curr' => round($sv_curr, 2),
                        'mtd_val' => round($sv_mtd_val, 2), 'mtd_pct' => round($sv_mtd_pct, 1),
                        'yoy_val' => round($sv_curr - $sv_yoy, 2),
                        'rka' => 0, 'penc_pct' => 0
                    ]
                ];

                // Tambah Total
                $totals['mid_curr'] += $mid_curr; $totals['mid_mtd'] += $mid_mtd; $totals['mid_ytd'] += $mid_ytd; $totals['mid_yoy'] += $mid_yoy;
                $totals['tid_curr_total'] += $tid_curr_tot; $totals['prod_curr'] += $prod_curr; $totals['prod_mtd'] += $prod_mtd; $totals['prod_ytd'] += $prod_ytd; $totals['prod_yoy'] += $prod_yoy;
                $totals['sv_curr'] += $sv_curr; $totals['sv_mtd'] += $sv_mtd; $totals['sv_yoy'] += $sv_yoy;
            }

            // Kalkulasi Total
            $t_pct_prod = $totals['tid_curr_total'] > 0 ? ($totals['prod_curr'] / $totals['tid_curr_total']) * 100 : 0;
            $t_mid_mtd_val = $totals['mid_curr'] - $totals['mid_mtd']; $t_mid_mtd_pct = $totals['mid_mtd'] > 0 ? ($t_mid_mtd_val / $totals['mid_mtd']) * 100 : 0;
            $t_prod_mtd_val = $totals['prod_curr'] - $totals['prod_mtd']; $t_prod_mtd_pct = $totals['prod_mtd'] > 0 ? ($t_prod_mtd_val / $totals['prod_mtd']) * 100 : 0;
            $t_sv_mtd_val = $totals['sv_curr'] - $totals['sv_mtd']; $t_sv_mtd_pct = $totals['sv_mtd'] > 0 ? ($t_sv_mtd_val / $totals['sv_mtd']) * 100 : 0;

            $grandTotal = [
                'branch' => 'TOTAL AREA 6',
                'mid' => [
                    'yoy' => $totals['mid_yoy'], 'ytd' => $totals['mid_ytd'], 'mtd' => $totals['mid_mtd'], 'curr' => $totals['mid_curr'],
                    'mtd_val' => $t_mid_mtd_val, 'mtd_pct' => round($t_mid_mtd_pct, 1),
                    'ytd_val' => $totals['mid_curr'] - $totals['mid_ytd'], 'yoy_val' => $totals['mid_curr'] - $totals['mid_yoy']
                ],
                'prod' => [
                    'curr' => $totals['prod_curr'], 'pct_tid' => round($t_pct_prod, 1),
                    'mtd_val' => $t_prod_mtd_val, 'mtd_pct' => round($t_prod_mtd_pct, 1),
                    'ytd_val' => $totals['prod_curr'] - $totals['prod_ytd'], 'yoy_val' => $totals['prod_curr'] - $totals['prod_yoy'],
                    'rka' => 0, 'penc_pct' => 0
                ],
                'sv' => [
                    'curr' => round($totals['sv_curr'], 2),
                    'mtd_val' => round($t_sv_mtd_val, 2), 'mtd_pct' => round($t_sv_mtd_pct, 1),
                    'yoy_val' => round($totals['sv_curr'] - $totals['sv_yoy'], 2),
                    'rka' => 0, 'penc_pct' => 0
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);

        } 
        
        // =================================================================================
        // LOGIKA TAB 2: MID & TID (Lama)
        // =================================================================================
        else {
            $query = DB::table('jumlah_merchant_detail')
                ->select(DB::raw('UPPER(NAMA_KANCA) as branch'))
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN MID END) as mid_yoy", [$dateYoY])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_curr", [$dateCurr])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_mtd", [$dateMtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_ytd", [$dateYtD])
                ->selectRaw("COUNT(DISTINCT CASE WHEN DATE(POSISI) = ? THEN TID END) as tid_yoy", [$dateYoY]);

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
                    'mtd_val' => $t_mid_mtd_val, 'mtd_pct' => round($t_mid_mtd_pct, 1),
                    'ytd_val' => $totals['mid_curr'] - $totals['mid_ytd'], 'yoy_val' => $totals['mid_curr'] - $totals['mid_yoy']
                ],
                'tid' => [
                    'yoy' => $totals['tid_yoy'], 'ytd' => $totals['tid_ytd'], 'mtd' => $totals['tid_mtd'], 'curr' => $totals['tid_curr'],
                    'mtd_val' => $t_tid_mtd_val, 'mtd_pct' => round($t_tid_mtd_pct, 1),
                    'ytd_val' => $totals['tid_curr'] - $totals['tid_ytd'], 'yoy_val' => $totals['tid_curr'] - $totals['tid_yoy'],
                    'rka' => 0, 'penc_pct' => 0
                ]
            ];

            return response()->json(['status' => 'success', 'labels' => $labels, 'data' => $data, 'total' => $grandTotal]);
        }
    }
}