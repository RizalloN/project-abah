<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\KinerjaPtpReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KinerjaPtpReportController extends Controller
{
    public function __construct(
        private readonly KinerjaPtpReportService $reportService
    ) {}

    public function index(Request $request): View
    {
        $selectedReportType = $this->reportService->normalizeReportType($request->input('jenis'));
        $selectedLevel = $this->reportService->normalizeLevel($request->input('level'));
        $availablePeriods = $this->reportService->availablePeriods($selectedReportType);
        $selectedPeriod = $this->reportService->resolveSelectedPeriod($availablePeriods, $request->input('periode'));
        $payload = $this->reportService->payload($selectedReportType, $selectedLevel, $selectedPeriod);
        $reportConfig = $this->reportService->reportConfig($selectedReportType);

        return view('report.dashboard-pinjaman.kinerja-ptp', [
            'reportTypes' => $this->reportService->reportTypes(),
            'levels' => $this->reportService->levels(),
            'selectedReportType' => $selectedReportType,
            'selectedLevel' => $selectedLevel,
            'availablePeriods' => $availablePeriods,
            'selectedPeriod' => $selectedPeriod,
            'selectedPeriodLabel' => $selectedPeriod
                ? Carbon::parse($selectedPeriod)->locale('id')->translatedFormat('d F Y')
                : '-',
            'reportConfig' => $reportConfig,
            'rows' => $payload['rows'],
            'total' => $payload['total'],
            'formatCount' => fn (mixed $value): string => $this->reportService->formatCount($value),
            'formatJuta' => fn (mixed $value): string => $this->reportService->formatJuta($value),
            'formatPercent' => fn (mixed $value): string => $this->reportService->formatPercent($value),
        ]);
    }
}
