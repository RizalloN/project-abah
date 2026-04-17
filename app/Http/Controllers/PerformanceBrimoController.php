<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Reports\BrimoReportService;

/**
 * Controller untuk halaman laporan BRIMO.
 * fetchData() didelegasikan ke BrimoReportService yang memperbaiki bug N+1 query
 * (8 query per baris → 2 single-query untuk semua periode).
 */
class PerformanceBrimoController extends Controller
{
    public function __construct(
        private readonly BrimoReportService $brimoService
    ) {}

    public function index()
    {
        $branches    = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        $filterPairs = $this->getBrimoFilterPairs();

        $branchOptions = $filterPairs->pluck('branch_name')->filter()->unique()->values();
        $branchUkerMap = $filterPairs->groupBy('branch_name')
            ->map(fn ($rows) => $rows->pluck('uker_name')->filter()->unique()->values()->all());

        return view('report.performance-brimo', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    /**
     * ✅ Fix N+1 query: Sekarang didelegasikan ke BrimoReportService
     * yang menggunakan 2 single-query untuk 4 periode (curr, prev, dec, yoy),
     * menggantikan pola lama yang memicu 8 query per baris.
     */
    public function fetchData(Request $request)
    {
        return $this->brimoService->fetchData($request);
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
            ->filter(fn ($row) => !empty(trim((string) ($row->branch_name ?? ''))) && !empty(trim((string) ($row->uker_name ?? ''))))
            ->map(function ($row) {
                $row->branch_name = strtoupper(trim((string) $row->branch_name));
                $row->uker_name   = strtoupper(trim((string) $row->uker_name));
                return $row;
            })
            ->unique(fn ($row) => $row->branch_name . '|' . $row->uker_name)
            ->sortBy([['branch_name', 'asc'], ['uker_name', 'asc']])
            ->values();
    }
}
