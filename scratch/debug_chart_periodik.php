<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardPinjamanChartPeriodikService;
use Illuminate\Support\Facades\DB;

$service = app(DashboardPinjamanChartPeriodikService::class);
echo "Source: " . $service->resolveSourceTable() . PHP_EOL;
echo "Periods: " . json_encode($service->fetchPeriods()->toArray()) . PHP_EOL;

$latestPeriod = $service->resolveEffectivePeriod(null);
echo "Latest Period: " . $latestPeriod . PHP_EOL;

$branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
foreach ($branches as $branch) {
    $units = DB::table($service->resolveSourceTable())
        ->where('periode', $latestPeriod)
        ->where('cabang1', $branch)
        ->count();
    echo "Branch $branch count for $latestPeriod: $units" . PHP_EOL;
}
