<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\BusinessClusterReportService;
use App\Services\Reports\KolaborasiReportService;
use App\Services\Reports\SppgReportService;
use Illuminate\Http\Request;

/**
 * Controller tipis untuk laporan Kolaborasi Perusahaan Anak.
 * Mendelegasikan seluruh logika ke KolaborasiReportService.
 */
class KolaborasiReportController extends Controller
{
    public function __construct(
        private readonly KolaborasiReportService $kolaborasiService,
        private readonly BusinessClusterReportService $businessClusterService,
        private readonly SppgReportService $sppgReportService
    ) {}

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

    public function businessCluster(Request $request)
    {
        return view('report.business-cluster', $this->businessClusterService->buildReport(
            $request->input('branch_office')
        ));
    }

    public function sppg()
    {
        return view('report.sppg', $this->sppgReportService->buildReport());
    }
}
