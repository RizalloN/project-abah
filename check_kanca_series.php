<?php
use App\Support\DashboardHarianSnapshotService;
$service = app(DashboardHarianSnapshotService::class);
$smlData = $service->fetchTimeseriesTrend(['2026-05'], 'sml');
echo "SML Series per Kanca:\n";
print_r($smlData['series']);
