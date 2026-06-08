<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

$controller = app()->make(\App\Http\Controllers\Report\KinerjaRmReportController::class);

$refMethod = new ReflectionMethod($controller, 'fetchAvailablePeriods');
$refMethod->setAccessible(true);
$availablePeriods = $refMethod->invoke($controller);

$refResolvePeriod = new ReflectionMethod($controller, 'resolveSelectedPeriod');
$refResolvePeriod->setAccessible(true);
$selectedPeriod = $refResolvePeriod->invoke($controller, $availablePeriods, '2026-05-31');

$refResolveComparison = new ReflectionMethod($controller, 'resolveKinerjaComparisonPeriods');
$refResolveComparison->setAccessible(true);
$comparisonPeriods = $refResolveComparison->invoke($controller, $availablePeriods, $selectedPeriod);

$refResolveRealisasi = new ReflectionMethod($controller, 'resolveKinerjaRealisasiPeriod');
$refResolveRealisasi->setAccessible(true);
$realisasiPeriod = $refResolveRealisasi->invoke($controller, $selectedPeriod, $comparisonPeriods);

// Now let's calculate the average manually using the proposed logic!
$averagePeriods = ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'];

$snapshots = DB::table('performance_rm_snapshots')
    ->where('rm', 'like', '%RONA%')
    ->whereIn('periode', $averagePeriods)
    ->get();

$realisasi_os_sum = 0;
$realisasi_deb_sum = 0;
foreach ($snapshots as $row) {
    if ($row->produk === 'BRIGUNA-KONSUMER') {
        $realisasi_os_sum += $row->realisasi_os;
        $realisasi_deb_sum += $row->realisasi_deb;
        echo "Periode: {$row->periode} | Realisasi OS: {$row->realisasi_os} | Realisasi Deb: {$row->realisasi_deb}\n";
    }
}

$realisasiDivisor = max(1, Carbon::parse($realisasiPeriod)->month);
$achDeb = (int) round($realisasi_deb_sum / $realisasiDivisor);
$achOs = $realisasi_os_sum / $realisasiDivisor;

echo "\nSum Realisasi OS: $realisasi_os_sum\n";
echo "Sum Realisasi Deb: $realisasi_deb_sum\n";
echo "Divisor: $realisasiDivisor\n";
echo "Average OS (ach_os): $achOs (" . ($achOs / 1000000) . " Juta)\n";
echo "Average Deb (ach_deb): $achDeb\n";
