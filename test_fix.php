<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "=== TEST CASA FIX ===\n\n";

$targetDate = '2026-02-28';
$branchName = 'KC Madiun';

// 1. Check OS (should work)
$osResult = DB::table('daily_loan_dinamis')
    ->where('periode', $targetDate)
    ->where('cabang', 'LIKE', '%Madiun%')
    ->selectRaw('SUM(CAST(baki_debet AS DECIMAL(20,2))) as total_os')
    ->first();
$os = $osResult ? (float) $osResult->total_os : 0;
echo "1. OS Result for {$branchName} on {$targetDate}: {$os}\n";

// 2. Find latest CASA date (THE FIX)
$latestCasaDate = DB::table('simpanan_multipn')
    ->where('posisi', '<=', $targetDate)
    ->max('posisi');
echo "   Raw max result: " . var_export($latestCasaDate, true) . "\n";

// If no data before target date, get the earliest available (MATCH CONTROLLER LOGIC)
if (!$latestCasaDate) {
    $earliestDate = DB::table('simpanan_multipn')->min('posisi');
    echo "   Earliest date in simpanan: " . var_export($earliestDate, true) . "\n";
    $latestCasaDate = $earliestDate;
    echo "   ✅ Using earliest available date: {$latestCasaDate}\n";
}

$casaDate = $latestCasaDate ? Carbon::parse($latestCasaDate)->format('Y-m-d') : $targetDate;
echo "2. Final CASA date to use: {$casaDate} (requested: {$targetDate})\n";

// 3. Check CASA with the available date (THE FIX)
$casaResult = DB::table('simpanan_multipn as s')
    ->where('s.posisi', $casaDate)
    ->whereIn('s.jenis_simpanan', ['GIRO', 'TABUNGAN', 'Giro', 'Tabungan'])
    ->whereExists(function ($query) use ($targetDate, $branchName) {
        $query->select(DB::raw(1))
              ->from('daily_loan_dinamis as d')
              ->whereRaw('UPPER(d.cifno) COLLATE utf8mb4_unicode_ci = UPPER(s.CIFNO) COLLATE utf8mb4_unicode_ci')
              ->where('d.periode', $targetDate)
              ->where('d.cabang', 'LIKE', '%' . $branchName . '%');
    })
    ->selectRaw('SUM(CAST(s.saldo_idr AS DECIMAL(20,2))) as total_casa')
    ->first();
$casa = $casaResult ? (float) $casaResult->total_casa : 0;
echo "3. CASA Result for {$branchName} on {$casaDate}: {$casa}\n";

// 4. Calculate ratio
if ($os > 0) {
    $ratio = ($casa / $os) * 100;
    echo "4. Ratio CASA/OS: " . number_format($ratio, 2) . "%\n";
} else {
    echo "4. Cannot calculate ratio (OS = 0)\n";
}

echo "\n=== FIX VERIFICATION ===\n";
if ($casa > 0) {
    echo "✅ SUCCESS! CASA is now showing data: {$casa}\n";
} else {
    echo "❌ CASA is still 0. Debugging...\n";
    
    // Debug 1: Check if there are any records in simpanan for the date
    $simpCount = DB::table('simpanan_multipn')
        ->where('posisi', $casaDate)
        ->count();
    echo "   Records in simpanan_multipn on {$casaDate}: {$simpCount}\n";
    
    // Debug 2: Check CIF matches
    $loanCifs = DB::table('daily_loan_dinamis')
        ->where('periode', $targetDate)
        ->where('cabang', 'LIKE', '%Madiun%')
        ->pluck('cifno')
        ->toArray();
    
    echo "   CIFs in loan data: " . count($loanCifs) . "\n";
    
    if (count($loanCifs) > 0) {
        $matches = DB::table('simpanan_multipn')
            ->where('posisi', $casaDate)
            ->whereIn('CIFNO', $loanCifs)
            ->select('CIFNO', 'jenis_simpanan', 'saldo_idr')
            ->limit(5)
            ->get();
        
        echo "   Matching CIFs in simpanan:\n";
        foreach ($matches as $m) {
            echo "     - {$m->CIFNO} | {$m->jenis_simpanan} | {$m->saldo_idr}\n";
        }
        
        if ($matches->count() == 0) {
            echo "   No matching CIFs found. Checking sample CIFs from simpanan:\n";
            $sampleCifs = DB::table('simpanan_multipn')
                ->where('posisi', $casaDate)
                ->select('CIFNO')
                ->limit(5)
                ->pluck('CIFNO')
                ->toArray();
            echo "   Sample CIFNOs from simpanan: " . implode(', ', $sampleCifs) . "\n";
            echo "   Sample CIFs from loan: " . implode(', ', array_slice($loanCifs, 0, 5)) . "\n";
        }
    }
    
    // Debug 3: Check all available dates in simpanan
    echo "\n   All available dates in simpanan_multipn:\n";
    $dates = DB::table('simpanan_multipn')
        ->select('posisi', DB::raw('COUNT(*) as count'))
        ->groupBy('posisi')
        ->orderBy('posisi', 'desc')
        ->limit(5)
        ->get();
    foreach ($dates as $d) {
        echo "     - {$d->posisi}: {$d->count} records\n";
    }
}
