<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\NamaReport;
use Carbon\Carbon;

class DataReportController extends Controller
{
    // 🔥 METHOD 1: Menampilkan Halaman View (UI)
    public function performanceEdc()
    {
        // Pakemkan Area 6
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        
        // Asumsi ID Report untuk Jumlah Merchant / EDC di database kamu adalah 1
        $id_report = 1; 

        // Return ke view yang baru (performance-edc)
        return view('report.performance-edc', compact('branches', 'id_report'));
    }

    // 🔥 METHOD 2: Mengambil Data (API / AJAX untuk Javascript)
    public function fetchData(Request $request)
    {
        $id_report = $request->input('id_report');
        $branches = $request->input('branches', []); // Array KC Madiun, dll
        $ukers = $request->input('ukers', []); // Array Uker (All = kosong)
        $posisi = $request->input('posisi'); // Format YYYY-MM-DD

        // Jika Posisi belum dipilih, gunakan tanggal hari ini
        if (!$posisi) {
            $posisi = date('Y-m-d');
        }

        // --- MENGHITUNG 4 TITIK PERIODE WAKTU ---
        $dateCurr = Carbon::parse($posisi)->toDateString(); 
        $dateMtD  = Carbon::parse($posisi)->subMonth()->endOfMonth()->toDateString(); // Akhir bulan lalu
        $dateYtD  = Carbon::parse($posisi)->subYear()->endOfYear()->toDateString(); // Akhir tahun lalu (Desember)
        $dateYoY  = Carbon::parse($posisi)->subYear()->endOfMonth()->toDateString(); // Akhir bulan di tahun lalu (Maret '25)

        // Switch Case untuk memetakan 18 jenis report ke depannya
        switch ($id_report) {
            
            // CONTOH: REPORT 1 (JUMLAH MERCHANT DETAIL)
            case '1':
            default:
                // QUERY SUPER CEPAT: Conditional Aggregation dalam 1x Eksekusi
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

                // Filter Cabang
                if (!empty($branches)) {
                    $query->whereIn(DB::raw('UPPER(NAMA_KANCA)'), array_map('strtoupper', $branches));
                }

                // Filter Uker (Jika tidak dikosongi / bukan ALL)
                if (!empty($ukers) && !in_array('ALL', array_map('strtoupper', $ukers))) {
                    $query->whereIn(DB::raw('UPPER(NAMA_UKER)'), array_map('strtoupper', $ukers));
                }

                $rawData = $query->groupBy('branch')->get();

                // Hitung Growth dan Susun Data
                $data = [];
                $totals = [
                    'mid_curr' => 0, 'mid_mtd' => 0, 'mid_ytd' => 0, 'mid_yoy' => 0,
                    'tid_curr' => 0, 'tid_mtd' => 0, 'tid_ytd' => 0, 'tid_yoy' => 0,
                    'rka' => 0 // Sesuai request, RKA dikosongi/0 dulu
                ];

                foreach ($rawData as $row) {
                    // Kalkulasi MID
                    $mid_mtd_val = $row->mid_curr - $row->mid_mtd;
                    $mid_mtd_pct = $row->mid_mtd > 0 ? ($mid_mtd_val / $row->mid_mtd) * 100 : 0;
                    $mid_ytd_val = $row->mid_curr - $row->mid_ytd;
                    $mid_yoy_val = $row->mid_curr - $row->mid_yoy;

                    // Kalkulasi TID
                    $tid_mtd_val = $row->tid_curr - $row->tid_mtd;
                    $tid_mtd_pct = $row->tid_mtd > 0 ? ($tid_mtd_val / $row->tid_mtd) * 100 : 0;
                    $tid_ytd_val = $row->tid_curr - $row->tid_ytd;
                    $tid_yoy_val = $row->tid_curr - $row->tid_yoy;

                    $data[] = [
                        'branch' => $row->branch,
                        'mid' => [
                            'yoy' => $row->mid_yoy, 'ytd' => $row->mid_ytd, 'mtd' => $row->mid_mtd, 'curr' => $row->mid_curr,
                            'mtd_val' => $mid_mtd_val, 'mtd_pct' => round($mid_mtd_pct, 1),
                            'ytd_val' => $mid_ytd_val, 'yoy_val' => $mid_yoy_val
                        ],
                        'tid' => [
                            'yoy' => $row->tid_yoy, 'ytd' => $row->tid_ytd, 'mtd' => $row->tid_mtd, 'curr' => $row->tid_curr,
                            'mtd_val' => $tid_mtd_val, 'mtd_pct' => round($tid_mtd_pct, 1),
                            'ytd_val' => $tid_ytd_val, 'yoy_val' => $tid_yoy_val,
                            'rka' => 0, 'penc_pct' => 0
                        ]
                    ];

                    // Tambah ke Total
                    $totals['mid_curr'] += $row->mid_curr; $totals['mid_mtd'] += $row->mid_mtd;
                    $totals['mid_ytd'] += $row->mid_ytd;   $totals['mid_yoy'] += $row->mid_yoy;
                    $totals['tid_curr'] += $row->tid_curr; $totals['tid_mtd'] += $row->tid_mtd;
                    $totals['tid_ytd'] += $row->tid_ytd;   $totals['tid_yoy'] += $row->tid_yoy;
                }

                // Hitung Growth untuk baris TOTAL
                $t_mid_mtd_val = $totals['mid_curr'] - $totals['mid_mtd'];
                $t_mid_mtd_pct = $totals['mid_mtd'] > 0 ? ($t_mid_mtd_val / $totals['mid_mtd']) * 100 : 0;
                
                $t_tid_mtd_val = $totals['tid_curr'] - $totals['tid_mtd'];
                $t_tid_mtd_pct = $totals['tid_mtd'] > 0 ? ($t_tid_mtd_val / $totals['tid_mtd']) * 100 : 0;

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

                return response()->json([
                    'status' => 'success',
                    'labels' => [
                        'curr' => Carbon::parse($dateCurr)->translatedFormat('d M Y'),
                        'mtd'  => Carbon::parse($dateMtD)->translatedFormat('M\'y'),
                        'ytd'  => Carbon::parse($dateYtD)->translatedFormat('M\'y'),
                        'yoy'  => Carbon::parse($dateYoY)->translatedFormat('M\'y'),
                    ],
                    'data' => $data,
                    'total' => $grandTotal
                ]);

                break;
        }
    }
}