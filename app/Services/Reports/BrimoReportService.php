<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk laporan BRIMO (U-Reg Rekening & Finansial).
 *
 * Menggunakan implementasi yang BENAR (2 query tunggal dengan multi-period),
 * menggantikan pola N+1 yang ada di PerformanceBrimoController lama
 * (yang memicu 8 query per baris display).
 *
 * Implementasi ini diekstrak dari tab 'brimo' di DataReportController yang sudah diperbaiki.
 */
class BrimoReportService
{
    /**
     * Handle fetch data untuk halaman BRIMO (PerformanceBrimoController).
     * Versi ini memperbaiki N+1 query dengan single-query per tabel.
     */
    public function fetchData(Request $request): JsonResponse
    {
        $tanggal  = $request->input('posisi', date('Y-m-d'));
        $currDate = Carbon::parse($tanggal);

        $prevDate = $currDate->copy()->subMonthNoOverflow()->endOfMonth();
        $decDate  = $currDate->copy()->subYearNoOverflow()->endOfYear();
        $yoyDate  = $currDate->copy()->subYearNoOverflow()->endOfMonth();

        $defaultBranches  = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($b) => strtoupper(trim((string) $b)))->filter()->values()->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($u) => strtoupper(trim((string) $u)))->filter()->reject(fn ($u) => $u === 'ALL UKER')->values()->all();

        $branches         = !empty($selectedBranches) ? $selectedBranches : $defaultBranches;
        $isBranchFiltered = !empty($selectedBranches);
        $displayItems     = !empty($selectedUkers)
            ? $selectedUkers
            : ($isBranchFiltered ? $this->getBrimoUkersForBranches($branches) : $branches);

        $groupLabel  = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $totalLabel  = $isBranchFiltered ? 'TOTAL ' . implode(', ', $branches) : 'TOTAL AREA 6';

        $dateCurr = $currDate->toDateString();
        $datePrev = $prevDate->toDateString();
        $dateDec  = $decDate->toDateString();
        $dateYoy  = $yoyDate->toDateString();

        // ✅ SINGLE QUERY untuk user_brimo_rpt_v2 (semua 4 periode sekaligus)
        $rekData = DB::table('user_brimo_rpt_v2')
            ->select(DB::raw('UPPER(COALESCE(brdesc, branch)) as branch'))
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_curr', [$dateCurr])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_prev', [$datePrev])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_dec', [$dateDec])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_yoy', [$dateYoy])
            ->where(function ($q) use ($branches, $isBranchFiltered, $displayItems) {
                if ($isBranchFiltered) {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), $displayItems)
                      ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches);
                } else {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), $displayItems);
                }
            })
            ->groupBy(DB::raw('UPPER(COALESCE(brdesc, branch))'))
            ->get()->keyBy('branch');

        // ✅ SINGLE QUERY untuk user_brimo_fin (semua 4 periode sekaligus)
        $finData = DB::table('user_brimo_fin')
            ->select(DB::raw('UPPER(COALESCE(brdesc, branch)) as branch'))
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_curr', [$dateCurr])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_prev', [$datePrev])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_dec', [$dateDec])
            ->selectRaw('SUM(CASE WHEN posisi <= ? THEN jumlah ELSE 0 END) as ureg_yoy', [$dateYoy])
            ->where(function ($q) use ($branches, $isBranchFiltered, $displayItems) {
                if ($isBranchFiltered) {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), $displayItems)
                      ->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $branches);
                } else {
                    $q->whereIn(DB::raw('UPPER(COALESCE(brdesc, branch))'), $displayItems);
                }
            })
            ->groupBy(DB::raw('UPPER(COALESCE(brdesc, branch))'))
            ->get()->keyBy('branch');

        $data  = [];
        $total = [
            'branch'         => $totalLabel,
            'ureg_rekening'  => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0],
            'ureg_finansial' => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0],
        ];

        foreach ($displayItems as $displayItem) {
            $item    = strtoupper(trim((string) $displayItem));
            $rek     = $rekData->get($item);
            $fin     = $finData->get($item);

            $rek_curr = (float) ($rek->ureg_curr ?? 0); $rek_prev = (float) ($rek->ureg_prev ?? 0);
            $rek_dec  = (float) ($rek->ureg_dec ?? 0);  $rek_yoy  = (float) ($rek->ureg_yoy ?? 0);
            $fin_curr = (float) ($fin->ureg_curr ?? 0); $fin_prev = (float) ($fin->ureg_prev ?? 0);
            $fin_dec  = (float) ($fin->ureg_dec ?? 0);  $fin_yoy  = (float) ($fin->ureg_yoy ?? 0);

            $total['ureg_rekening']['curr']    += $rek_curr;
            $total['ureg_rekening']['prev']    += $rek_prev;
            $total['ureg_rekening']['dec']     += $rek_dec;
            $total['ureg_rekening']['yoy_prev'] += $rek_yoy;
            $total['ureg_finansial']['curr']    += $fin_curr;
            $total['ureg_finansial']['prev']    += $fin_prev;
            $total['ureg_finansial']['dec']     += $fin_dec;
            $total['ureg_finansial']['yoy_prev'] += $fin_yoy;

            $data[] = [
                'branch'         => $displayItem,
                'ureg_rekening'  => $this->calculateMetrics($rek_curr, $rek_prev, $rek_dec, $rek_yoy),
                'ureg_finansial' => $this->calculateMetrics($fin_curr, $fin_prev, $fin_dec, $fin_yoy),
            ];
        }

        $total['ureg_rekening']  = $this->calculateMetrics($total['ureg_rekening']['curr'], $total['ureg_rekening']['prev'], $total['ureg_rekening']['dec'], $total['ureg_rekening']['yoy_prev']);
        $total['ureg_finansial'] = $this->calculateMetrics($total['ureg_finansial']['curr'], $total['ureg_finansial']['prev'], $total['ureg_finansial']['dec'], $total['ureg_finansial']['yoy_prev']);

        $bulanIndo = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

        return response()->json([
            'status'      => 'success',
            'group_label' => $groupLabel,
            'labels'      => [
                'curr_date'  => $currDate->format('d') . ' ' . $bulanIndo[$currDate->month] . "'" . $currDate->format('y'),
                'curr_month' => $bulanIndo[$currDate->month] . "'" . $currDate->format('y'),
                'mtd'        => $bulanIndo[$prevDate->month] . "'" . $prevDate->format('y'),
                'ytd'        => $bulanIndo[$decDate->month] . "'" . $decDate->format('y'),
                'yoy'        => $bulanIndo[$yoyDate->month] . "'" . $yoyDate->format('y'),
            ],
            'data'  => $data,
            'total' => $total,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    private function calculateMetrics(float $curr, float $prev, float $dec, float $yoy): array
    {
        if ($curr == 0) {
            return ['curr' => null, 'prev' => null, 'dec' => null, 'yoy_prev' => null, 'mtd' => null, 'mtd_pct' => null, 'ytd' => null, 'yoy' => null, 'yoy_pct' => null];
        }

        $mtd      = $curr - $prev;
        $ytd      = $curr - $dec;
        $yoy_diff = $curr - $yoy;

        return [
            'curr'     => $curr,
            'prev'     => $prev,
            'dec'      => $dec,
            'yoy_prev' => $yoy,
            'mtd'      => $mtd,
            'mtd_pct'  => $prev != 0 ? ($mtd / $prev) * 100 : 0,
            'ytd'      => $ytd,
            'yoy'      => $yoy_diff,
            'yoy_pct'  => $yoy != 0 ? ($yoy_diff / $yoy) * 100 : 0,
        ];
    }

    private function getBrimoUkersForBranches(array $selectedBranches): array
    {
        // Use UNION query to fetch branch/uker combinations from both tables in one SQL roundtrip
        $branchUkerRows = DB::select("
            SELECT DISTINCT
                UPPER(TRIM(COALESCE(mbdesc, branch))) as branch_name,
                UPPER(TRIM(COALESCE(brdesc, branch))) as uker_name
            FROM user_brimo_rpt_v2
            WHERE mbdesc IS NOT NULL AND mbdesc <> '' 
                AND brdesc IS NOT NULL AND brdesc <> ''
            UNION
            SELECT DISTINCT
                UPPER(TRIM(COALESCE(mbdesc, branch))) as branch_name,
                UPPER(TRIM(COALESCE(brdesc, branch))) as uker_name
            FROM user_brimo_fin
            WHERE mbdesc IS NOT NULL AND mbdesc <> '' 
                AND brdesc IS NOT NULL AND brdesc <> ''
        ");

        // Filter to selected branches and extract unique uker names
        $selectedBranchesUpper = array_map('strtoupper', $selectedBranches);
        $ukers = [];
        foreach ($branchUkerRows as $row) {
            if (in_array($row->branch_name, $selectedBranchesUpper, true) && !empty($row->uker_name)) {
                $ukers[$row->uker_name] = true;
            }
        }
        
        return array_keys($ukers);
    }
}
