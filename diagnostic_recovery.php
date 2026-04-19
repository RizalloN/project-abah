<?php
// Diagnostic script to check recovery data for Madiun
require_once 'bootstrap/app.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== RECOVERY DATA DIAGNOSTIC FOR MADIUN ===\n\n";

// Check if table exists
if (!Schema::hasTable('lw325_ph')) {
    echo "❌ lw325_ph table does not exist\n";
    exit(1);
}

// Get current period
$today = now()->format('Y-m-d');
$period = DB::table('lw325_ph')
    ->select('periode')
    ->orderBy('periode', 'desc')
    ->limit(1)
    ->pluck('periode')
    ->first();

echo "Latest period in lw325_ph: $period\n\n";

// Check kanca values in lw325_ph
echo "1. Checking kanca values in lw325_ph:\n";
$kancaValues = DB::table('lw325_ph')
    ->select(DB::raw("DISTINCT TRIM(COALESCE(kanca, '')) as kanca"))
    ->where('periode', $period)
    ->orderBy('kanca')
    ->pluck('kanca')
    ->toArray();

echo "   Total distinct kanca values: " . count($kancaValues) . "\n";
echo "   Kanca values:\n";
foreach ($kancaValues as $kanca) {
    if (stripos($kanca, 'madiun') !== false) {
        echo "   ⭐ $kanca (MATCHES 'Madiun')\n";
    }
}

// Check if Madiun exists
$madiun = collect($kancaValues)->first(fn ($k) => stripos($k, 'madiun') !== false);

if (!$madiun) {
    echo "\n❌ No Madiun found in kanca values\n";
} else {
    echo "\n✓ Found Madiun: '$madiun'\n";
    
    // Get data for Madiun
    echo "\n2. Data for Madiun in current period:\n";
    $count = DB::table('lw325_ph')
        ->where('periode', $period)
        ->where(DB::raw("TRIM(COALESCE(kanca, ''))"), $madiun)
        ->count();
    echo "   Records for Madiun: $count\n";
    
    // Get previous period
    $prevPeriod = DB::table('lw325_ph')
        ->select('periode')
        ->where('periode', '<', $period)
        ->orderBy('periode', 'desc')
        ->limit(1)
        ->pluck('periode')
        ->first();
    
    if ($prevPeriod) {
        echo "   Previous period: $prevPeriod\n";
        
        // Check if Madiun exists in previous period
        $prevCount = DB::table('lw325_ph')
            ->where('periode', $prevPeriod)
            ->where(DB::raw("TRIM(COALESCE(kanca, ''))"), $madiun)
            ->count();
        echo "   Records for Madiun in previous period: $prevCount\n";
        
        // Check for accounts with decreased principal
        $recovery = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($period, $prevPeriod, $madiun) {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->where('n.periode', $period)
                    ->where('o.periode', $prevPeriod)
                    ->where(DB::raw("TRIM(COALESCE(n.kanca, ''))"), $madiun);
            })
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->count();
        
        echo "   Recovery accounts (decreased principal): $recovery\n";
        
        // Check LUNAS (paid off)
        $lunas = DB::table('lw325_ph as o')
            ->leftJoin('lw325_ph as n', function ($join) use ($period, $prevPeriod, $madiun) {
                $join->on('o.acctno', '=', 'n.acctno')
                    ->on('o.kanca', '=', 'n.kanca')
                    ->on('o.unit', '=', 'n.unit')
                    ->where('o.periode', $prevPeriod)
                    ->where('n.periode', $period)
                    ->where(DB::raw("TRIM(COALESCE(o.kanca, ''))"), $madiun);
            })
            ->where('o.periode', $prevPeriod)
            ->whereNull('n.acctno')
            ->where(DB::raw("TRIM(COALESCE(o.kanca, ''))"), $madiun)
            ->count();
        
        echo "   LUNAS accounts (paid off): $lunas\n";
        
        echo "\n3. Sample data - First 3 Madiun accounts with recovery:\n";
        $samples = DB::table('lw325_ph as n')
            ->join('lw325_ph as o', function ($join) use ($period, $prevPeriod, $madiun) {
                $join->on('n.acctno', '=', 'o.acctno')
                    ->on('n.kanca', '=', 'o.kanca')
                    ->on('n.unit', '=', 'o.unit')
                    ->where('n.periode', $period)
                    ->where('o.periode', $prevPeriod)
                    ->where(DB::raw("TRIM(COALESCE(n.kanca, ''))"), $madiun);
            })
            ->select(
                'n.acctno',
                DB::raw("TRIM(COALESCE(n.kanca, '')) as kanca"),
                DB::raw("TRIM(COALESCE(n.unit, '')) as unit"),
                DB::raw("COALESCE(o.pokok, 0) as prev_pokok"),
                DB::raw("COALESCE(n.pokok, 0) as curr_pokok"),
                DB::raw("COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0) as recovery")
            )
            ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
            ->limit(3)
            ->get();
        
        foreach ($samples as $sample) {
            echo "   Acctno: {$sample->acctno}, Kanca: {$sample->kanca}, Unit: {$sample->unit}\n";
            echo "      Prev: {$sample->prev_pokok}, Current: {$sample->curr_pokok}, Recovery: {$sample->recovery}\n";
        }
    }
}

echo "\n✓ Diagnostic complete\n";
