<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\EdcReportService;
use App\Services\Reports\QrisReportService;
use App\Services\Reports\BrilinkReportService;
use App\Services\Reports\ReportFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller tipis untuk laporan performa digital (EDC, QRIS, Brilink).
 * Semua business logic didelegasikan ke service class masing-masing.
 */
class DigitalPerformanceController extends Controller
{
    public function __construct(
        private readonly EdcReportService     $edcService,
        private readonly QrisReportService    $qrisService,
        private readonly BrilinkReportService $brilinkService,
        private readonly ReportFilterService  $filterService
    ) {}

    // -------------------------------------------------------------------------
    // View Methods
    // -------------------------------------------------------------------------

    public function performanceEdc(): \Illuminate\View\View
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->filterService->buildBranchUkerFilterOptions(
            'jumlah_merchant_detail',
            'NAMA_KANCA',
            'NAMA_UKER'
        );

        return view('report.performance-edc', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function performanceQris(): \Illuminate\View\View
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->filterService->buildBranchUkerFilterOptions(
            'jumlah_merchant_qris_detail',
            'MBDESC',
            'BRDESC'
        );

        return view('report.performance-qris', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function performanceBrilink(): \Illuminate\View\View
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->filterService->buildBrilinkFilterOptions();

        return view('report.performance-brilink', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    // -------------------------------------------------------------------------
    // Data Endpoint (AJAX/API)
    // -------------------------------------------------------------------------

    public function fetchData(Request $request): JsonResponse
    {
        $tab = $request->input('tab', 'edc');

        return match (true) {
            in_array($tab, ['edc', 'merchant_prod', 'sv_merchant_accum', 'mid_tid', 'prod_mom'])
                => $this->edcService->handle($request),
            in_array($tab, ['qris', 'qris_mom'])
                => $this->qrisService->handle($request),
            $tab === 'brilink'
                => $this->brilinkService->handle($request),
            default
                => response()->json(['status' => 'error', 'message' => "Unknown tab: {$tab}"], 422),
        };
    }
}
