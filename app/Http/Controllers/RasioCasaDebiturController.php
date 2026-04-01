<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            if (!Schema::hasColumn('daily_loan_dinamis', 'baki_debet')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kolom wajib `baki_debet` belum tersedia di tabel daily_loan_dinamis. Jalankan migration dan upload ulang data Daily Loan Dinamis.',
                ], 422);
            }

            $tanggal = $request->input('posisi', date('Y-m-d'));
            $currDate = Carbon::parse($tanggal);

            $prevMonthStart = $currDate->copy()->subMonthNoOverflow()->startOfMonth();
            $prevMonthEnd = $currDate->copy()->subMonthNoOverflow()->endOfMonth();

            $latestPrevDate = DB::table('daily_loan_dinamis')
                ->whereBetween('periode', [
                    $prevMonthStart->toDateString(),
                    $prevMonthEnd->toDateString()
                ])
                ->max('periode');

            // Jika tidak ada data sama sekali di bulan sebelumnya, fallback ke akhir bulan tersebut
            $prevDate = $latestPrevDate ? Carbon::parse($latestPrevDate) : $prevMonthEnd;

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
            $branches = $this->resolveDynamicBranches($aggregatedPrevData, $aggregatedCurrData);

            // Looping Pengisian Data ke Array Response
            foreach ($branches as $branch) {
                $branchNameClean = $this->normalizeBranchKey($branch);
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
                'effective_dates' => [
                    'prev' => $prevDate->toDateString(),
                    'curr' => $currDate->toDateString(),
                ],
                'meta' => [
                    'has_rows' => (($aggregatedPrevData['row_count'] ?? 0) + ($aggregatedCurrData['row_count'] ?? 0)) > 0,
                    'row_count_prev' => (int) ($aggregatedPrevData['row_count'] ?? 0),
                    'row_count_curr' => (int) ($aggregatedCurrData['row_count'] ?? 0),
                    'branch_count' => count($branches),
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
        $result = ['os' => [], 'casa' => [], 'branch_labels' => [], 'row_count' => 0];
        $cifList = [];
        $cifMapping = [];

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
                    $branchName = $this->normalizeBranchKey($loan->cabang);
                    if ($branchName === '') {
                        continue;
                    }

                    if (!isset($result['os'][$branchName])) {
                        $result['os'][$branchName] = ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
                        $result['casa'][$branchName] = ['total' => 0, 'briguna' => 0, 'kpr' => 0, 'mikro' => 0, 'smc' => 0];
                    }

                    if (!isset($result['branch_labels'][$branchName])) {
                        $result['branch_labels'][$branchName] = $this->formatBranchLabel($loan->cabang ?: $branchName);
                    }
                    $result['row_count']++;

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
        $applyJenisSimpananFilter = DB::table('simpanan_multipn')
            ->where('posisi', $casaDate)
            ->where(function ($query) {
                $query->where('jenis_simpanan', 'like', 'GIRO%')
                      ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
            })
            ->exists();
        
        foreach ($chunks as $chunk) {
            $casaQuery = DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                ->whereIn('cifno', $chunk);

            // Pertahankan logic utama GIRO/TABUNGAN.
            // Jika batch data tertentu belum mengisi jenis_simpanan dengan benar,
            // fallback ke seluruh saldo tanggal tersebut agar laporan tetap terbaca.
            if ($applyJenisSimpananFilter) {
                $casaQuery->where(function ($query) {
                    $query->where('jenis_simpanan', 'like', 'GIRO%')
                          ->orWhere('jenis_simpanan', 'like', 'TABUNGAN%');
                });
            }

            $casas = $casaQuery
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

    private function normalizeBranchKey(?string $branch): string
    {
        $value = strtoupper(trim((string) $branch));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/^KC[\.\s-]*/', '', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        if (str_contains($value, 'MADIUN')) {
            return 'MADIUN';
        }
        if (str_contains($value, 'MAGETAN')) {
            return 'MAGETAN';
        }
        if (str_contains($value, 'NGAWI')) {
            return 'NGAWI';
        }
        if (str_contains($value, 'PONOROGO')) {
            return 'PONOROGO';
        }

        return $value;
    }

    private function formatBranchLabel(string $branchKey): string
    {
        $normalized = $this->normalizeBranchKey($branchKey);
        if ($normalized === '') {
            return 'UNKNOWN BRANCH';
        }

        return 'KC ' . $normalized;
    }

    private function resolveDynamicBranches(array $prevData, array $currData): array
    {
        $priority = ['MADIUN', 'MAGETAN', 'NGAWI', 'PONOROGO'];
        $branchMap = [];

        foreach ([$prevData, $currData] as $dataset) {
            foreach (($dataset['branch_labels'] ?? []) as $branchKey => $label) {
                $normalizedKey = $this->normalizeBranchKey($branchKey);
                if ($normalizedKey === '') {
                    continue;
                }
                $branchMap[$normalizedKey] = $this->formatBranchLabel($label);
            }
        }

        if (empty($branchMap)) {
            foreach ($priority as $branchKey) {
                $branchMap[$branchKey] = $this->formatBranchLabel($branchKey);
            }
        }

        uksort($branchMap, function ($a, $b) use ($priority) {
            $indexA = array_search($a, $priority, true);
            $indexB = array_search($b, $priority, true);

            $indexA = $indexA === false ? 999 : $indexA;
            $indexB = $indexB === false ? 999 : $indexB;

            if ($indexA === $indexB) {
                return strcmp($a, $b);
            }

            return $indexA <=> $indexB;
        });

        return array_values($branchMap);
    }
    
    /**
     * 🔥 Engine Penentu Tabungan V2 - Sesuai Aturan Bisnis Yang Diberikan
     */
    private function determineBuckets($segmen, $produk) {
        $buckets = ['total']; // Semua pinjaman pasti masuk ke 'total'
        
        // Normalisasi input untuk perbandingan yang konsisten
        $segmen_norm = strtolower(trim($segmen));
        $produk_norm = strtolower(trim($produk));

        // ATURAN KONSUMER (BRIGUNA & KPR) - Sesuai permintaan user (Logika AND)
        // Hanya jika segmen adalah 'consumer', baru cek produknya.
        if (str_contains($segmen_norm, 'consumer')) {
            if (str_contains($produk_norm, 'briguna')) {
                $buckets[] = 'briguna';
            }
            if (str_contains($produk_norm, 'kpr')) {
                $buckets[] = 'kpr';
            }
        }
    
        // ATURAN MIKRO - Mengembalikan fleksibilitas dari kode asli untuk variasi data
        if (str_contains($segmen_norm, 'micro') || str_contains($segmen_norm, 'mikro') || str_contains($segmen_norm, 'umkm')) {
            $buckets[] = 'mikro';
        } 
    
        // ATURAN SMC - Mengembalikan fleksibilitas dari kode asli untuk variasi data
        if (str_contains($segmen_norm, 'small') || str_contains($segmen_norm, 'smc') || str_contains($segmen_norm, 'menengah')) {
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
