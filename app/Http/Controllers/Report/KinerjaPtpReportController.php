<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\KinerjaPtpReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

    public function detail(Request $request): JsonResponse
    {
        $selectedReportType = $this->reportService->normalizeReportType($request->input('jenis'));
        $selectedLevel = $this->reportService->normalizeLevel($request->input('level'));
        $availablePeriods = $this->reportService->availablePeriods($selectedReportType);
        $selectedPeriod = $this->reportService->resolveSelectedPeriod($availablePeriods, $request->input('periode'));

        abort_if($selectedPeriod === null, 422, 'Periode Kinerja PTP wajib valid.');

        $dimensions = [];
        foreach ($this->reportService->groupAliases($selectedLevel) as $alias) {
            $dimensions[$alias] = trim((string) $request->input($alias, ''));
        }

        $payload = $this->reportService->detailPayload(
            $selectedReportType,
            $selectedLevel,
            $selectedPeriod,
            $dimensions,
            $request->input('metric'),
            (int) $request->input('limit', 0),
            (int) $request->input('offset', 0)
        );

        return response()->json([
            'selected_period' => $selectedPeriod,
            'report_type' => $selectedReportType,
            'level' => $selectedLevel,
            'dimensions' => $dimensions,
            'metric' => $payload['metric'],
            'metric_label' => $payload['metric_label'],
            'columns' => $payload['columns'],
            'rows' => $payload['rows'],
            'limit' => $payload['limit'],
            'offset' => $payload['offset'],
            'next_offset' => $payload['next_offset'],
            'has_more' => $payload['has_more'],
        ]);
    }
}
