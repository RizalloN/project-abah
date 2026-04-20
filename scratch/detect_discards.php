<?php

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\DashboardHarianSnapshotService;

$service = app(DashboardHarianSnapshotService::class);
$targetDate = '2026-04-18';

// Function to call private method for testing
function invokeMethod(&$object, $methodName, array $parameters = array())
{
    $reflection = new \ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

// 1. Check Pinjaman Discards
echo "Checking Pinjaman Discards for $targetDate...\n";
$loanRows = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $targetDate)
    ->get();

$totalRows = count($loanRows);
$discardedRows = 0;
$discardedTotal = 0;
$discardedNames = [];

foreach ($loanRows as $row) {
    $rawKanca = $row->nama_cabang ?? $row->nama_uker ?? null;
    $label = invokeMethod($service, 'normalizeKancaLabel', [$rawKanca]);
    if ($label === '') {
        $discardedRows++;
        $discardedTotal += $row->baki_debet;
        $discardedNames[] = $rawKanca;
    }
}

echo "Total Rows: $totalRows\n";
echo "Discarded Rows: $discardedRows\n";
echo "Discarded Total: " . number_format($discardedTotal) . "\n";
if ($discardedRows > 0) {
    echo "Sample Discarded Names: " . implode(', ', array_slice(array_unique($discardedNames), 0, 5)) . "\n";
}

// 2. Check Simpanan Discards
echo "\nChecking Simpanan Discards for $targetDate...\n";
$savingsRows = DB::table('ssa_simpanan')
    ->where('Month_Day_Year_of_Posisi', $targetDate)
    ->get();

$totalRowsS = count($savingsRows);
$discardedRowsS = 0;
$discardedTotalS = 0;
$discardedNamesS = [];

foreach ($savingsRows as $row) {
    $rawKanca = $row->nama_cabang ?? $row->nama_uker ?? null;
    $label = invokeMethod($service, 'normalizeKancaLabel', [$rawKanca]);
    if ($label === '') {
        $discardedRowsS++;
        $discardedTotalS += $row->saldo;
        $discardedNamesS[] = $rawKanca;
    }
}

echo "Total Rows: $totalRowsS\n";
echo "Discarded Rows: $discardedRowsS\n";
echo "Discarded Total: " . number_format($discardedTotalS) . "\n";
if ($discardedRowsS > 0) {
    echo "Sample Discarded Names: " . implode(', ', array_slice(array_unique($discardedNamesS), 0, 5)) . "\n";
}
