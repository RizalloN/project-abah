<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Services\Reports\RunOffReportService;
use App\Support\UserBranchScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RunOffReportController extends Controller
{
    public function index(Request $request, RunOffReportService $service): View
    {
        $scope = UserBranchScope::current();
        $report = $service->build($scope, $request->boolean('refresh'));

        return view('report.dashboard-pinjaman.run-off', [
            ...$report,
            'isBranchScoped' => $scope !== null,
            'scopeLabel' => $scope['label'] ?? 'Area 6',
        ]);
    }
}
