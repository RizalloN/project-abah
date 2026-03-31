<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceBrimoController extends Controller
{
    // 🔥 1. VIEW PERFORMANCE BRIMO
    public function index()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 4; 

        return view('report.performance-brimo', compact('branches', 'id_report'));
    }

    // 🔥 2. MESIN PENGOLAH DATA UTAMA (AJAX API)
    public function fetchData(Request $request)
    {
        $id_report = $request->input('id_report', 4);
        $branches = $request->input('branches', ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO']); 
        $posisi = $request->input('posisi'); 

        // Gunakan tanggal hari ini jika kosong
        if (!$posisi) $posisi = date('Y-m-d');

        // Baseline MTD = posisi akhir bulan sebelumnya (bukan tanggal yang sama bulan lalu)
        $baseDate = Carbon::parse($posisi);
        $dateCurr = $baseDate->copy()->format('Y-m-d');
        $dateMtD  = $baseDate->copy()->startOfMonth()->subDay()->format('Y-m-d');
        $dateYtD  = $baseDate->copy()->subYearNoOverflow()->endOfYear()->format('Y-m-d');
        $dateYoY  = $baseDate->copy()->subYearNoOverflow()->endOfMonth()->format('Y-m-d');

        $labels = [
            'curr' => Carbon::parse($dateCurr)->translatedFormat('d M Y'), 
            'mtd'  => Carbon::parse($dateMtD)->translatedFormat('M y'),
            'ytd'  => Carbon::parse($dateYtD)->translatedFormat('M y'),
            'yoy'  => Carbon::parse($dateYoY)->translatedFormat('M y'),
        ];

        $data = [];
        
        // Simpan nilai riil untuk hitungan total
        $raw_totals = [
            'ureg_rekening' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0],
            'ureg_finansial' => ['curr' => 0, 'mtd' => 0, 'ytd' => 0, 'yoy' => 0]
        ];

        foreach ($branches as $branch) {
            // 🔥 UREG BRIMO (BY REKENING)
            $ureg_rek_curr = DB::table('user_brimo_rpt_v2')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateCurr)->sum('jumlah');

            $ureg_rek_mtd = DB::table('user_brimo_rpt_v2')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateMtD)->sum('jumlah');

            $ureg_rek_ytd = DB::table('user_brimo_rpt_v2')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateYtD)->sum('jumlah');

            $ureg_rek_yoy = DB::table('user_brimo_rpt_v2')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateYoY)->sum('jumlah');

            $ureg_rek_yoy_pct = $ureg_rek_yoy > 0 ? (($ureg_rek_curr - $ureg_rek_yoy) / $ureg_rek_yoy) * 100 : 0;

            // 🔥 UREG BRIMO (BY REKENING FINANSIAL)
            $ureg_fin_curr = DB::table('user_brimo_fin')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateCurr)->sum('jumlah');

            $ureg_fin_mtd = DB::table('user_brimo_fin')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateMtD)->sum('jumlah');

            $ureg_fin_ytd = DB::table('user_brimo_fin')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateYtD)->sum('jumlah');

            $ureg_fin_yoy = DB::table('user_brimo_fin')
                ->where(function($q) use ($branch) {
                    $q->where(DB::raw('UPPER(brdesc)'), strtoupper($branch))->orWhere(DB::raw('UPPER(branch)'), strtoupper($branch));
                })->where('tanggal', '<=', $dateYoY)->sum('jumlah');

            $ureg_fin_yoy_pct = $ureg_fin_yoy > 0 ? (($ureg_fin_curr - $ureg_fin_yoy) / $ureg_fin_yoy) * 100 : 0;

            // PUSH TO DATA ARRAY (Kirim selisih/growth ke view)
            $data[] = [
                'branch' => $branch,
                'ureg_rekening' => [
                    'curr' => $ureg_rek_curr, 
                    'mtd' => $ureg_rek_curr - $ureg_rek_mtd,
                    'ytd' => $ureg_rek_curr - $ureg_rek_ytd,
                    'yoy' => $ureg_rek_curr - $ureg_rek_yoy,
                    'yoy_pct' => $ureg_rek_yoy_pct
                ],
                'ureg_finansial' => [
                    'curr' => $ureg_fin_curr, 
                    'mtd' => $ureg_fin_curr - $ureg_fin_mtd,
                    'ytd' => $ureg_fin_curr - $ureg_fin_ytd,
                    'yoy' => $ureg_fin_curr - $ureg_fin_yoy,
                    'yoy_pct' => $ureg_fin_yoy_pct
                ],
                'usak' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                'volume_trx' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-']
            ];

            // AKUMULASI NILAI RIIL UNTUK TOTAL
            $raw_totals['ureg_rekening']['curr'] += $ureg_rek_curr;
            $raw_totals['ureg_rekening']['mtd'] += $ureg_rek_mtd;
            $raw_totals['ureg_rekening']['ytd'] += $ureg_rek_ytd;
            $raw_totals['ureg_rekening']['yoy'] += $ureg_rek_yoy;

            $raw_totals['ureg_finansial']['curr'] += $ureg_fin_curr;
            $raw_totals['ureg_finansial']['mtd'] += $ureg_fin_mtd;
            $raw_totals['ureg_finansial']['ytd'] += $ureg_fin_ytd;
            $raw_totals['ureg_finansial']['yoy'] += $ureg_fin_yoy;
        }

        // 🔥 FIX: Perhitungan persentase TOTAL yang benar menggunakan raw data
        $tot_ureg_pct = $raw_totals['ureg_rekening']['yoy'] > 0 
            ? (($raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['yoy']) / $raw_totals['ureg_rekening']['yoy']) * 100 : 0;
            
        $tot_fin_pct = $raw_totals['ureg_finansial']['yoy'] > 0 
            ? (($raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['yoy']) / $raw_totals['ureg_finansial']['yoy']) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'labels' => $labels,
            'total' => [
                'branch' => 'TOTAL AREA',
                'ureg_rekening' => [
                    'curr' => $raw_totals['ureg_rekening']['curr'],
                    'mtd'  => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['mtd'],
                    'ytd'  => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['ytd'],
                    'yoy'  => $raw_totals['ureg_rekening']['curr'] - $raw_totals['ureg_rekening']['yoy'],
                    'yoy_pct' => $tot_ureg_pct
                ],
                'ureg_finansial' => [
                    'curr' => $raw_totals['ureg_finansial']['curr'],
                    'mtd'  => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['mtd'],
                    'ytd'  => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['ytd'],
                    'yoy'  => $raw_totals['ureg_finansial']['curr'] - $raw_totals['ureg_finansial']['yoy'],
                    'yoy_pct' => $tot_fin_pct
                ],
                'usak' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-'],
                'volume_trx' => ['curr' => '-', 'mtd' => '-', 'ytd' => '-', 'yoy' => '-', 'yoy_pct' => '-']
            ]
        ]);
    }
}