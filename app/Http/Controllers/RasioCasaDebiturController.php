<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RasioCasaDebiturController extends Controller
{
    public function index()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        return view('report.Rasiocasadebitur', compact('branches'));
    }

    public function fetchData(Request $request)
    {
        try {
            $tanggal = $request->input('posisi', date('Y-m-d'));
            $currDateInput = Carbon::parse($tanggal);

            // 1. SMART FALLBACK DATE
            $latestAvailableDate = DB::table('daily_loan_dinamis')
                ->where('periode', '<=', $currDateInput->toDateString())
                ->max('periode');

            $currDate = $latestAvailableDate ? Carbon::parse($latestAvailableDate) : $currDateInput->copy();

            // 2. FALLBACK POSISI PREV
            $prevMonthEnd = $currDate->copy()->subMonthNoOverflow()->endOfMonth();
            $latestPrevDate = DB::table('daily_loan_dinamis')
                ->whereBetween('periode', [
                    $prevMonthEnd->copy()->startOfMonth()->toDateString(),
                    $prevMonthEnd->toDateString()
                ])
                ->max('periode');

            $prevDate = $latestPrevDate ? Carbon::parse($latestPrevDate) : $prevMonthEnd;

            $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
            $segmenList = ['TOTAL', 'BRIGUNA', 'KPR', 'MIKRO', 'SMC'];
            $data = [];

            // Inisialisasi kerangka Grand Total Area 6
            $total = ['branch' => 'TOTAL AREA 6'];
            foreach ($segmenList as $seg) {
                $total[strtolower($seg)] = [
                    'os_prev' => 0, 'os_curr' => 0, 
                    'casa_prev' => 0, 'casa_curr' => 0
                ];
            }

            // 🔥 OPTIMASI BIG DATA: Tarik agregat melalui Application-Side Join
            $aggregatedPrevData = $this->getAggregatedDataFast($prevDate->format('Y-m-d'));
            $aggregatedCurrData = $this->getAggregatedDataFast($currDate->format('Y-m-d'));

            // Looping Pengisian Data ke Array Response
            foreach ($branches as $branch) {
                $branchNameClean = trim(str_replace(['KC.', 'KC '], '', strtoupper($branch)));
                $rowData = ['branch' => $branch];

                foreach ($segmenList as $seg) {
                    $segKey = strtolower($seg);
                    
                    // Ambil data dari hasil agregat memori PHP (Sangat Cepat)
                    $prevOS   = $aggregatedPrevData['os'][$branchNameClean][$segKey] ?? 0;
                    $prevCASA = $aggregatedPrevData['casa'][$branchNameClean][$segKey] ?? 0;
                    
                    $currOS   = $aggregatedCurrData['os'][$branchNameClean][$segKey] ?? 0;
                    $currCASA = $aggregatedCurrData['casa'][$branchNameClean][$segKey] ?? 0;

                    // Kalkulasi Metrik (Rasio, MtD)
                    $metrics = $this->calculateMetrics(
                        ['os' => $prevOS, 'casa' => $prevCASA], 
                        ['os' => $currOS, 'casa' => $currCASA]
                    );
                    
                    $rowData[$segKey] = $metrics;

                    // Akumulasi ke Grand Total Area 6
                    $total[$segKey]['os_prev'] += $prevOS;
                    $total[$segKey]['os_curr'] += $currOS;
                    $total[$segKey]['casa_prev'] += $prevCASA;
                    $total[$segKey]['casa_curr'] += $currCASA;
                }
                $data[] = $rowData;
            }

            // Kalkulasi ulang Rasio untuk Grand Total Area 6
            foreach ($segmenList as $seg) {
                $segKey = strtolower($seg);
                $total[$segKey] = $this->calculateMetrics(
                    ['os' => $total[$segKey]['os_prev'], 'casa' => $total[$segKey]['casa_prev']],
                    ['os' => $total[$segKey]['os_curr'], 'casa' => $total[$segKey]['casa_curr']]
                );
            }

            $bulanIndo = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
            ];

            return response()->json([
                'status' => 'success',
                'labels' => [
                    'prev' => $prevDate->format('d') . ' ' . $bulanIndo[$prevDate->month] . " '" . $prevDate->format('y'),
                    'curr' => $currDate->format('d') . ' ' . $bulanIndo[$currDate->month] . " '" . $currDate->format('y'),
                ],
                'data'  => $data,
                'total' => $total
            ]);

        } catch (\Exception $e) {
            Log::error("[RasioCasa] Critical Failure: " . $e->getMessage());
            
            // TAMPILKAN ERROR CERDAS KE FRONTEND (UI)
            return response()->json([
                'status' => 'error',
                'message' => 'Data terlalu besar. Sistem menghentikan kueri untuk mencegah server down. Pastikan Database telah memiliki Index. Detail Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔥 THE ENTERPRISE SOLUTION FOR BIG DATA (Application-Side Join)
     * Sama sekali menghindari MySQL JOIN & EXISTS yang membuat database Timeout.
     */
    private function getAggregatedDataFast($targetDate)
    {
        $result = ['os' => [], 'casa' => []];
        $cifList = [];
        $cifMapping = [];

        // Inisialisasi kerangka data untuk semua cabang di awal
        $branches = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
        foreach ($branches as $branchName) {
            $result['os'][$branchName] = ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
            $result['casa'][$branchName] = ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
        }

        DB::table('daily_loan_dinamis')
            ->where('periode', $targetDate)
            ->where(function($q) {
                $q->where('cabang', 'LIKE', '%MADIUN%')
                  ->orWhere('cabang', 'LIKE', '%MAGETAN%')
                  ->orWhere('cabang', 'LIKE', '%NGAWI%')
                  ->orWhere('cabang', 'LIKE', '%PONOROGO%');
            })
            ->select('id', 'cifno', 'cabang', 'segmen_dashboard', 'produk_dashboard', 'baki_debet')
            ->orderBy('id')
            ->chunkById(5000, function ($loans) use (&$result, &$cifList, &$cifMapping) {
                foreach ($loans as $loan) {
                    $branchName = str_replace(['KC.', 'KC '], '', trim(strtoupper($loan->cabang)));
                    
                    // Lewati jika nama cabang tidak terduga setelah dibersihkan
                    if (!isset($result['os'][$branchName])) continue;

                    $cif = strtoupper(trim($loan->cifno));
                    if (!empty($cif)) {
                        $cifList[$cif] = true;
                    }

                    $osVal = (float)$loan->baki_debet;
                    
                    // Gunakan engine penentu bucket yang sudah diperbaiki
                    $buckets = $this->determineBuckets($loan->segmen_dashboard, $loan->produk_dashboard);

                    foreach ($buckets as $b) {
                        $result['os'][$branchName][$b] += $osVal;
                        if (!empty($cif)) {
                            $cifMapping[$cif][$branchName][$b] = true;
                        }
                    }
                }
            });

        if (empty($cifList)) {
            return $result;
        }
        
        $uniqueCifs = array_keys($cifList);
        $latestCasaDate = DB::table('simpanan_multipn')->where('posisi', '<=', $targetDate)->max('posisi');
        $casaDate = $latestCasaDate ?: $targetDate;

        $casaBalances = [];
        $chunks = array_chunk($uniqueCifs, 5000);
        
        foreach ($chunks as $chunk) {
            $casas = DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                 // LOGIKA DIPERKETAT: Hanya GIRO dan TABUNGAN sesuai permintaan
                ->where(function ($query) {
                    $query->where('jenis_simpanan', 'like', 'GIRO%')
                          ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                })
                ->whereIn('cifno', $chunk)
                ->select('cifno', DB::raw('SUM(saldo_idr) as total_saldo'))
                ->groupBy('cifno')
                ->get();
            
            foreach ($casas as $casa) {
                $cifKey = strtoupper(trim($casa->cifno));
                $casaBalances[$cifKey] = (float)$casa->total_saldo;
            }
        }

        foreach ($casaBalances as $cif => $saldo) {
            if (isset($cifMapping[$cif])) {
                foreach ($cifMapping[$cif] as $branchName => $buckets) {
                    foreach (array_keys($buckets) as $bucketName) {
                        $result['casa'][$branchName][$bucketName] += $saldo;
                    }
                }
            }
        }

        return $result;
    }
    
    /**
     * 🔥 Engine Penentu Tabungan V2 - Sesuai Aturan Bisnis Yang Diberikan
     */
    private function determineBuckets($segmen, $produk) {
        $buckets = ['total']; // Semua pinjaman pasti masuk ke 'total'
        
        // Normalisasi input untuk perbandingan yang konsisten
        $segmen_norm = strtolower(trim($segmen));
        $produk_norm = strtolower(trim($produk));

        // ATURAN BRIGUNA: segmen 'consumer' DAN produk mengandung 'briguna'
        if (str_contains($segmen_norm, 'consumer') && str_contains($produk_norm, 'briguna')) {
            $buckets[] = 'briguna';
        }

        // ATURAN KPR: segmen 'consumer' DAN produk 'kpr'
        if (str_contains($segmen_norm, 'consumer') && $produk_norm === 'kpr') {
            $buckets[] = 'kpr';
        }
    
        // ATURAN MIKRO: segmen 'micro'
        if ($segmen_norm === 'micro') {
            $buckets[] = 'mikro';
        }
    
        // ATURAN SMC: segmen 'small'
        if ($segmen_norm === 'small') {
            $buckets[] = 'smc';
        }
        
        return array_unique($buckets); // Pastikan tidak ada duplikat bucket
    }

    /**
     * Kalkulator Rasio dan Growth
     */
    private function calculateMetrics($prev, $curr)
    {
        $os_prev = $prev['os'] ?? 0;
        $casa_prev = $prev['casa'] ?? 0;
        $os_curr = $curr['os'] ?? 0;
        $casa_curr = $curr['casa'] ?? 0;

        if ($os_curr == 0 && $casa_curr == 0) {
            return [
                'os_prev' => $os_prev > 0 ? $os_prev : null,
                'casa_prev' => $casa_prev > 0 ? $casa_prev : null,
                'rasio_prev' => $os_prev > 0 ? ($casa_prev / $os_prev) * 100 : null,
                'os_curr' => null,
                'casa_curr' => null,
                'rasio_curr' => null,
                'mtd' => null
            ];
        }

        $rasio_prev = $os_prev > 0 ? ($casa_prev / $os_prev) * 100 : 0;
        $rasio_curr = $os_curr > 0 ? ($casa_curr / $os_curr) * 100 : 0;
        $mtd = $rasio_curr - $rasio_prev;

        return [
            'os_prev'    => $os_prev,
            'casa_prev'  => $casa_prev,
            'rasio_prev' => $rasio_prev,
            'os_curr'    => $os_curr,
            'casa_curr'  => $casa_curr,
            'rasio_curr' => $rasio_curr,
            'mtd'        => $mtd
        ];
    }
}