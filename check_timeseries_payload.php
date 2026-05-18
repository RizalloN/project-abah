<?php
use App\Support\DashboardHarianSnapshotService;
$service = app(DashboardHarianSnapshotService::class);
$smlData = $service->fetchTimeseriesTrend(['2026-05'], 'sml');
$nplData = $service->fetchTimeseriesTrend(['2026-05'], 'npl');
echo "SML Area Total:\n";
print_r($smlData['area_total']);
echo "\nNPL Area Total:\n";
print_r($nplData['area_total']);
