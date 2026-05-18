<?php
use App\Support\DashboardHarianSnapshotService;
$service = app(DashboardHarianSnapshotService::class);
$nplData = $service->fetchTimeseriesTrend(['2026-05'], 'npl');
echo "NPL Series per Kanca:\n";
print_r($nplData['series']);
