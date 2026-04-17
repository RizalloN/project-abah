<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\NewPayrollReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller tipis untuk laporan Kinerja New Payroll.
 * Mendelegasikan seluruh logika ke NewPayrollReportService.
 */
class NewPayrollReportController extends Controller
{
    public function __construct(
        private readonly NewPayrollReportService $newPayrollService
    ) {}

    public function index(): \Illuminate\View\View
    {
        $branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
        ['branchOptions' => $branchOptions, 'branchUkerMap' => $branchUkerMap]
            = $this->newPayrollService->buildFilterOptions();

        return view('report.kinerja-new-payroll', compact('branches', 'branchOptions', 'branchUkerMap'));
    }

    public function fetchData(Request $request): JsonResponse
    {
        return $this->newPayrollService->fetchData($request);
    }
}
