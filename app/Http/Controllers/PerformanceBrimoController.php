<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceBrimoController extends Controller
{
    public function index()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $filterPairs = $this->getBrimoFilterPairs();
        $branchOptions = $filterPairs
            ->pluck('branch_name')
            ->filter()
            ->unique()
            ->values();
        $branchUkerMap = $filterPairs
            ->groupBy('branch_name')
            ->map(function ($rows) {
                return $rows->pluck('uker_name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            });

        return view('report.performance-brimo', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function fetchData(Request $request)
    {
        $tanggal = $request->input('posisi', date('Y-m-d'));
        $currDate = Carbon::parse($tanggal);

        $prevDate = $currDate->copy()->subMonthNoOverflow()->endOfMonth();
        $decDate  = $currDate->copy()->subYearNoOverflow()->endOfYear();
        $yoyDate  = $currDate->copy()->subYearNoOverflow()->endOfMonth();

        $defaultBranches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $selectedBranches = collect((array) $request->input('branch_office', []))
            ->map(fn ($branch) => strtoupper(trim((string) $branch)))
            ->filter()
            ->values()
            ->all();
        $selectedUkers = collect((array) $request->input('nama_uker', []))
            ->map(fn ($uker) => strtoupper(trim((string) $uker)))
            ->filter()
            ->reject(fn ($uker) => $uker === 'ALL UKER')
            ->values()
            ->all();

        $branches = !empty($selectedBranches) ? $selectedBranches : $defaultBranches;
        $isBranchFiltered = !empty($selectedBranches);
        $displayItems = !empty($selectedUkers)
            ? $selectedUkers
            : ($isBranchFiltered ? $this->getBrimoUkersForBranches($branches) : $branches);
        $groupLabel = $isBranchFiltered ? 'UKER' : 'BRANCH OFFICE';
        $totalLabel = $isBranchFiltered
            ? 'TOTAL ' . implode(', ', $branches)
            : 'TOTAL AREA 6';

        $data = [];
        $total = [
            'branch' => $totalLabel,
            'ureg_rekening'  => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0],
            'ureg_finansial' => ['curr' => 0, 'prev' => 0, 'dec' => 0, 'yoy_prev' => 0],
        ];

        foreach ($displayItems as $displayItem) {
            $rek_curr = $this->getUregData('user_brimo_rpt_v2', $currDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $rek_prev = $this->getUregData('user_brimo_rpt_v2', $prevDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $rek_dec  = $this->getUregData('user_brimo_rpt_v2', $decDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $rek_yoy  = $this->getUregData('user_brimo_rpt_v2', $yoyDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);

            $fin_curr = $this->getUregData('user_brimo_fin', $currDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $fin_prev = $this->getUregData('user_brimo_fin', $prevDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $fin_dec  = $this->getUregData('user_brimo_fin', $decDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);
            $fin_yoy  = $this->getUregData('user_brimo_fin', $yoyDate->format('Y-m-d'), $displayItem, $isBranchFiltered, $branches);

            $total['ureg_rekening']['curr'] += $rek_curr;
            $total['ureg_rekening']['prev'] += $rek_prev;
            $total['ureg_rekening']['dec'] += $rek_dec;
            $total['ureg_rekening']['yoy_prev'] += $rek_yoy;

            $total['ureg_finansial']['curr'] += $fin_curr;
            $total['ureg_finansial']['prev'] += $fin_prev;
            $total['ureg_finansial']['dec'] += $fin_dec;
            $total['ureg_finansial']['yoy_prev'] += $fin_yoy;

            $data[] = [
                'branch' => $displayItem,
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
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        return response()->json([
            'status' => 'success',
            'group_label' => $groupLabel,
            'labels' => [
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

    private function getUregData($table, $date, $label, $isUkerMode = false, array $selectedBranches = [])
    {
        $targetDate = Carbon::parse($date)->format('Y-m-d');
        $normalizedLabel = strtoupper(trim((string) $label));

        $query = DB::table($table)
            ->whereDate('posisi', $targetDate);

        if ($isUkerMode) {
            $query->whereRaw('UPPER(COALESCE(brdesc, branch)) = ?', [$normalizedLabel]);
            if (!empty($selectedBranches)) {
                $query->whereIn(DB::raw('UPPER(COALESCE(mbdesc, branch))'), $selectedBranches);
            }
        } else {
            $query->where(function ($subQuery) use ($normalizedLabel) {
                $subQuery->whereRaw('UPPER(brdesc) = ?', [$normalizedLabel])
                    ->orWhereRaw('UPPER(branch) = ?', [$normalizedLabel])
                    ->orWhereRaw('UPPER(mbdesc) = ?', [$normalizedLabel]);
            });
        }

        $exact = $query->sum('jumlah');

        return $exact ? (float) $exact : 0;
    }

    private function calculateMetrics($curr, $prev, $dec, $yoy)
    {
        $curr = (float) ($curr ?? 0);
        $prev = (float) ($prev ?? 0);
        $dec  = (float) ($dec ?? 0);
        $yoy  = (float) ($yoy ?? 0);

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
                'yoy_pct'  => null,
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
            'yoy_pct'  => $yoy_pct,
        ];
    }

    private function getBrimoFilterPairs()
    {
        return collect([
            DB::table('user_brimo_rpt_v2')
                ->selectRaw('TRIM(COALESCE(mbdesc, branch)) as branch_name')
                ->selectRaw('TRIM(COALESCE(brdesc, branch)) as uker_name')
                ->get(),
            DB::table('user_brimo_fin')
                ->selectRaw('TRIM(COALESCE(mbdesc, branch)) as branch_name')
                ->selectRaw('TRIM(COALESCE(brdesc, branch)) as uker_name')
                ->get(),
        ])->flatten(1)
            ->filter(function ($row) {
                return !empty(trim((string) ($row->branch_name ?? '')))
                    && !empty(trim((string) ($row->uker_name ?? '')));
            })
            ->map(function ($row) {
                $row->branch_name = strtoupper(trim((string) $row->branch_name));
                $row->uker_name = strtoupper(trim((string) $row->uker_name));
                return $row;
            })
            ->unique(fn ($row) => $row->branch_name . '|' . $row->uker_name)
            ->sortBy([
                ['branch_name', 'asc'],
                ['uker_name', 'asc'],
            ])
            ->values();
    }

    private function getBrimoUkersForBranches(array $selectedBranches): array
    {
        return $this->getBrimoFilterPairs()
            ->filter(fn ($row) => in_array($row->branch_name, $selectedBranches, true))
            ->pluck('uker_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
