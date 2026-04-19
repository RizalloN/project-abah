<?php

/**
 * Script to rebuild recovery DH snapshots
 * This forces a fresh rebuild of the dashboard_harian_snapshots table
 * including the recovery data that was previously filtered out
 */

require_once __DIR__ . '/bootstrap/app.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\DashboardHarianSnapshotService;

echo "=== RECOVERY DH SNAPSHOT REBUILD ===\n\n";

try {
    $service = app(DashboardHarianSnapshotService::class);
    
    // Get all available periods
    echo "1. Fetching available periods...\n";
    $periods = DB::table('lw325_ph')
        ->select(DB::raw('DISTINCT periode'))
        ->orderBy('periode', 'desc')
        ->pluck('periode')
        ->toArray();
    
    echo "   Found " . count($periods) . " periods\n";
    
    if (empty($periods)) {
        echo "❌ No periods found in lw325_ph table\n";
        exit(1);
    }
    
    // Show periods
    echo "\n2. Available periods:\n";
    foreach (array_slice($periods, 0, 10) as $period) {
        echo "   - $period\n";
    }
    if (count($periods) > 10) {
        echo "   ... and " . (count($periods) - 10) . " more\n";
    }
    
    // Rebuild snapshots for recent periods
    echo "\n3. Rebuilding snapshots (force mode)...\n";
    
    $recentPeriods = array_slice($periods, 0, 5);
    $totalRows = 0;
    
    foreach ($recentPeriods as $period) {
        echo "   Building snapshot for period: $period... ";
        try {
            $rowCount = $service->buildPeriodSnapshot($period, true);
            echo "✓ ($rowCount rows)\n";
            $totalRows += $rowCount;
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n4. Verifying rebuilt snapshots...\n";
    
    // Check if recovery data is now in snapshot
    $recoveryInSnapshot = DB::table('dashboard_harian_snapshots')
        ->select('snapshot_period', 
            DB::raw('SUM(rec_dh_small) as rec_dh_small'),
            DB::raw('SUM(rec_dh_consumer) as rec_dh_consumer'),
            DB::raw('SUM(rec_dh_micro) as rec_dh_micro'),
            DB::raw('SUM(rec_dh_total) as rec_dh_total')
        )
        ->whereIn('snapshot_period', $recentPeriods)
        ->groupBy('snapshot_period')
        ->orderBy('snapshot_period', 'desc')
        ->get();
    
    echo "   Recovery data in snapshots:\n";
    foreach ($recoveryInSnapshot as $row) {
        echo "   Period: {$row->snapshot_period}\n";
        echo "      Small: {$row->rec_dh_small}, Consumer: {$row->rec_dh_consumer}, Micro: {$row->rec_dh_micro}, Total: {$row->rec_dh_total}\n";
    }
    
    echo "\n5. Testing with Madiun filter...\n";
    
    // Check recovery data from fetchPhAggregates with Madiun filter
    if (!empty($recentPeriods)) {
        $testPeriod = $recentPeriods[0];
        echo "   Testing with period: $testPeriod\n";
        
        // Directly query recovery data with Madiun filter
        $madiun = DB::table('lw325_ph')
            ->select(DB::raw("DISTINCT TRIM(COALESCE(kanca, '')) as kanca"))
            ->where('periode', $testPeriod)
            ->pluck('kanca')
            ->first(fn($k) => stripos($k, 'madiun') !== false);
        
        if ($madiun) {
            echo "   Found Madiun variant: '$madiun'\n";
            
            // Get recovery data for Madiun
            $prevPeriod = DB::table('lw325_ph')
                ->select('periode')
                ->where('periode', '<', $testPeriod)
                ->orderBy('periode', 'desc')
                ->limit(1)
                ->pluck('periode')
                ->first();
            
            if ($prevPeriod) {
                $recovery = DB::table('lw325_ph as n')
                    ->join('lw325_ph as o', function ($join) use ($testPeriod, $prevPeriod) {
                        $join->on('n.acctno', '=', 'o.acctno')
                            ->on('n.kanca', '=', 'o.kanca')
                            ->on('n.unit', '=', 'o.unit')
                            ->where('n.periode', $testPeriod)
                            ->where('o.periode', $prevPeriod);
                    })
                    ->whereRaw('(COALESCE(o.pokok, 0) - COALESCE(n.pokok, 0)) > 0')
                    ->where(DB::raw("UPPER(TRIM(COALESCE(n.kanca, '')))"), strtoupper($madiun))
                    ->selectRaw('COUNT(*) as count')
                    ->selectRaw('SUM(COALESCE(o.pokok, 0)) as total_recovery')
                    ->first();
                
                if ($recovery && $recovery->total_recovery > 0) {
                    echo "   ✓ Recovery data found for Madiun!\n";
                    echo "      Accounts with decreased principal: {$recovery->count}\n";
                    echo "      Total recovery amount: {$recovery->total_recovery}\n";
                } else {
                    echo "   ⚠ No recovery data found for Madiun in period $testPeriod\n";
                }
            }
        } else {
            echo "   ⚠ No Madiun variant found in database\n";
        }
    }
    
    echo "\n✓ Snapshot rebuild complete!\n";
    echo "   Total rows built: $totalRows\n";
    echo "\n   Dashboard should now display recovery data correctly when filtered.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
