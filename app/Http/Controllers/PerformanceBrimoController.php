<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceBrimoController extends Controller
{
    // 1. VIEW PERFORMANCE BRIMO
    public function index()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $id_report = 4; 
        
        return view('report.performance-brimo', compact('branches', 'id_report'));
    }

    // 2. MESIN PENGOLAH DATA UTAMA (AJAX API)
    public function fetchData(Request $request)
    {
        $tanggal = $request->input('posisi', date('Y-m-d'));
        $currDate = Carbon::parse($tanggal);

        // 🔥 STRICT REALTIME: Menghapus fallback. Murni mengambil berdasarkan tanggal yang dipilih di datepicker.
        $prevDate = $currDate->copy()->subMonthNoOverflow()->endOfMonth();
        $decDate  = $currDate->copy()->subYearNoOverflow()->endOfYear();
        $yoyDate  = $currDate->copy()->subYearNoOverflow()->endOfMonth();

        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $data = [];

        $total = [
            'branch' => 'TOTAL AREA 6',
            'ureg_rekening'  => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0],
            'ureg_finansial' => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0]
        ];

        foreach ($branches as $branch) {
            $rek_curr = $this->getUregData('user_brimo_rpt_v2', $currDate->format('Y-m-d'), $branch);
            $rek_prev = $this->getUregData('user_brimo_rpt_v2', $prevDate->format('Y-m-d'), $branch);
            $rek_dec  = $this->getUregData('user_brimo_rpt_v2', $decDate->format('Y-m-d'), $branch);
            $rek_yoy  = $this->getUregData('user_brimo_rpt_v2', $yoyDate->format('Y-m-d'), $branch);

            $fin_curr = $this->getUregData('user_brimo_fin', $currDate->format('Y-m-d'), $branch);
            $fin_prev = $this->getUregData('user_brimo_fin', $prevDate->format('Y-m-d'), $branch);
            $fin_dec  = $this->getUregData('user_brimo_fin', $decDate->format('Y-m-d'), $branch);
            $fin_yoy  = $this->getUregData('user_brimo_fin', $yoyDate->format('Y-m-d'), $branch);

            $total['ureg_rekening']['curr'] += $rek_curr;
            $total['ureg_rekening']['prev'] += $rek_prev;
            $total['ureg_rekening']['dec']  += $rek_dec;
            $total['ureg_rekening']['yoy_prev'] += $rek_yoy;

            $total['ureg_finansial']['curr'] += $fin_curr;
            $total['ureg_finansial']['prev'] += $fin_prev;
            $total['ureg_finansial']['dec']  += $fin_dec;
            $total['ureg_finansial']['yoy_prev'] += $fin_yoy;

            $data[] = [
                'branch' => $branch,
                'ureg_rekening'  => $this->calculateMetrics($rek_curr, $rek_prev, $rek_dec, $rek_yoy),
                'ureg_finansial' => $this->calculateMetrics($fin_curr, $fin_prev, $fin_dec, $fin_yoy),
            ];
        }

        $total['ureg_rekening'] = $this->calculateMetrics(
            $total['ureg_rekening']['curr'],
            $total['ureg_rekening']['prev'],
            $total['ureg_rekening']['dec'],
            $total['ureg_rekening']['yoy_prev']
        );

        $total['ureg_finansial'] = $this->calculateMetrics(
            $total['ureg_finansial']['curr'],
            $total['ureg_finansial']['prev'],
            $total['ureg_finansial']['dec'],
            $total['ureg_finansial']['yoy_prev']
        );

        $bulanIndo = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];

        return response()->json([
            'status' => 'success',
            'labels' => [
                'curr_date'  => $currDate->format('d') . ' ' . $bulanIndo[$currDate->month] . "'" . $currDate->format('y'),
                'curr_month' => $bulanIndo[$currDate->month] . "'" . $currDate->format('y'),
                'mtd'        => $bulanIndo[$prevDate->month] . "'" . $prevDate->format('y'),
                'ytd'        => $bulanIndo[$decDate->month] . "'" . $decDate->format('y'),
                'yoy'        => $bulanIndo[$yoyDate->month] . "'" . $yoyDate->format('y'),
            ],
            'data'  => $data,
            'total' => $total
        ]);
    }

    /**
     * Query Pembacaan Data Real-time (Strict)
     */
    private function getUregData($table, $date, $cabang)
    {
        $targetDate = Carbon::parse($date)->format('Y-m-d');
        $branch = strtoupper(trim($cabang));

        // 🔥 STRICT DATE BOOSTER: Mencari tanggal posisi presisi.
        // Diperkuat dengan kombinasi whereDate dan kondisi grouping untuk brdesc/branch agar tidak mis-match.
        $exact = DB::table($table)
            ->whereDate('posisi', $targetDate)
            ->where(function($query) use ($branch) {
                $query->whereRaw('UPPER(brdesc) = ?', [$branch])
                      ->orWhereRaw('UPPER(branch) = ?', [$branch]);
            })
            ->sum('jumlah');

        return $exact ? (float)$exact : 0;
    }

    /**
     * Mesin Perhitungan Growth & Persentase
     */
    private function calculateMetrics($curr, $prev, $dec, $yoy)
    {
        $curr = (float)($curr ?? 0);
        $prev = (float)($prev ?? 0);
        $dec  = (float)($dec  ?? 0);
        $yoy  = (float)($yoy  ?? 0);

        // 🔥 LOGIC DINAMIS: 
        // JIKA data hari berjalan benar-benar 0 (kosong/belum masuk di DB pada tanggal spesifik tersebut),
        // Maka kita Wajib membalikan nilai NULL. File JavaScript UI (Blade) kita 
        // sudah diatur untuk otomatis merubah nilai NULL menjadi tanda strip ("-") yang rapi.
        if ($curr == 0) {
            return [
                'curr'     => null,
                'prev'     => null,
                'dec'      => null,
                'yoy_prev' => null,
                'mtd'      => null,
                'mtd_pct'  => null,
                'ytd'      => null,
                'yoy'      => null,
                'yoy_pct'  => null
            ];
        }

        $mtd = $curr - $prev;
        $ytd = $curr - $dec;
        $yoy_diff = $curr - $yoy;
        
        $yoy_pct = $yoy != 0 ? ($yoy_diff / $yoy) * 100 : 0;
        $mtd_pct = $prev != 0 ? ($mtd / $prev) * 100 : 0;

        return [
            'curr'     => $curr,
            'prev'     => $prev,
            'dec'      => $dec,
            'yoy_prev' => $yoy,
            'mtd'      => $mtd,
            'mtd_pct'  => $mtd_pct,
            'ytd'      => $ytd,
            'yoy'      => $yoy_diff,
            'yoy_pct'  => $yoy_pct
        ];
    }
}