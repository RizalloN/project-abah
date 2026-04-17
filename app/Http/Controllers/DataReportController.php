<?php

namespace App\Http\Controllers;

use App\Services\Reports\EdcReportService;
use App\Services\Reports\QrisReportService;
use App\Services\Reports\BrilinkReportService;
use App\Services\Reports\KolaborasiReportService;
use App\Services\Reports\NewPayrollReportService;
use App\Services\Reports\ReportFilterService;
use Illuminate\Http\Request;

/**
 * DataReportController — Thin Delegator (Refactored)
 *
 * Controller ini sudah direfactor. Semua business logic, query, dan kalkulasi
 * telah dipindahkan ke service classes di app/Services/Reports/.
 *
 * Controller ini hanya dipertahankan untuk backward compatibility.
 * Routing telah diperbarui untuk menggunakan controller-controller baru:
 *   - DigitalPerformanceController (EDC, QRIS, Brilink, fetchData)
 *   - KolaborasiReportController   (Referral, BOD/BOC)
 *   - NewPayrollReportController    (New Payroll)
 *
 * Method-method di sini adalah delegator yang meneruskan ke service yang relevan.
 *
 * @see \App\Http\Controllers\Report\DigitalPerformanceController
 * @see \App\Http\Controllers\Report\KolaborasiReportController
 * @see \App\Http\Controllers\Report\NewPayrollReportController
 */
class DataReportController extends Controller
{
    public function __construct(
        private readonly EdcReportService       $edcService,
        private readonly QrisReportService      $qrisService,
        private readonly BrilinkReportService   $brilinkService,
        private readonly KolaborasiReportService $kolaborasiService,
        private readonly NewPayrollReportService $newPayrollService,
        private readonly ReportFilterService    $filterService
    ) {}

    // =========================================================================
    // View Methods — Build filter options dan render view Blade
    // =========================================================================

    public function performanceNewPayroll()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap]
            = $this->newPayrollService->buildFilterOptions();

        return view('report.kinerja-new-payroll', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function performanceEdc()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->filterService->buildBranchUkerFilterOptions(
            'jumlah_merchant_detail', 'NAMA_KANCA', 'NAMA_UKER'
        );

        return view('report.performance-edc', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function performanceQris()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap] = $this->filterService->buildBranchUkerFilterOptions(
            'merchant_qris', 'NAMA_KCI', 'NAMA_BRANCH'
        );

        return view('report.performance-qris', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function performanceBrilink()
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap]
            = $this->filterService->buildBrilinkFilterOptions();

        return view('report.performance-brilink', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    // =========================================================================
    // Data Endpoints (AJAX/API) — Delegasi ke service yang sesuai
    // =========================================================================

    public function fetchNewPayrollData(Request $request)
    {
        return $this->newPayrollService->fetchData($request);
    }

    public function fetchData(Request $request)
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

    // =========================================================================
    // Kolaborasi — Delegasi ke KolaborasiReportService
    // =========================================================================

    public function programReferralPartnerPerusahaanAnak(Request $request)
    {
        return $this->kolaborasiService->buildReport(
            $request,
            'input_rekanan',
            'report.program-referral-partner-perusahaan-anak',
            'input_rekanan + simpanan_multipn',
            'Kolaborasi Perusahaan Anak'
        );
    }

    public function nasabahPrioritasBodBoc(Request $request)
    {
        return $this->kolaborasiService->buildReport(
            $request,
            'bod_boc',
            'report.nasabah-prioritas-bod-boc',
            'bod_boc + simpanan_multipn',
            'Nasabah Prioritas BOD/BOC'
        );
    }
}
