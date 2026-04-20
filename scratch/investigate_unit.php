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

// Get Snapshot totals by unit
$snapshots = DB::table('dashboard_harian_snapshots')
    ->where('snapshot_period', $targetDate)
    ->get();

foreach ($snapshots as $snap) {
    if ($snap->kanca_key !== $snap->unit_key) {
        // This is a detail row. We need to find the matching source rows.
        // The source row match is based on normalizeUnitLabel.
        
        $sourceTotal = DB::table('ssa_pinjaman')
            ->where('month_day_year_of_periode', $targetDate)
            ->get()
            ->filter(function($row) use ($service, $snap) {
                $kanca = invokeMethod($service, 'normalizeKancaLabel', [$row->nama_cabang ?? $row->nama_uker]);
                $unit = invokeMethod($service, 'normalizeUnitLabel', [$row->nama_uker, $kanca]);
                return Str::slug($unit, '-') === $snap->unit_key && Str::slug($kanca, '-') === $snap->kanca_key;
            })
            ->sum('baki_debet');
            
        if (abs($snap->total_os - $sourceTotal) > 1000) { // accounting for rounding
            echo "Mismatch found for Unit: {$snap->unit_label} ({$snap->unit_key})\n";
            echo "  Snapshot Total OS: " . number_format($snap->total_os) . "\n";
            echo "  Source Total OS:   " . number_format($sourceTotal) . "\n";
            echo "  Difference:        " . number_format($snap->total_os - $sourceTotal) . "\n";
            
            // Check Micro segment in both
            $sourceMicro = DB::table('ssa_pinjaman')
                ->where('month_day_year_of_periode', $targetDate)
                ->where('segmen_dashboard', 'Micro')
                ->get()
                ->filter(function($row) use ($service, $snap) {
                    $kanca = invokeMethod($service, 'normalizeKancaLabel', [$row->nama_cabang ?? $row->nama_uker]);
                    $unit = invokeMethod($service, 'normalizeUnitLabel', [$row->nama_uker, $kanca]);
                    return Str::slug($unit, '-') === $snap->unit_key && Str::slug($kanca, '-') === $snap->kanca_key;
                })
                ->sum('baki_debet');
                
            echo "  Snapshot Micro OS: " . number_format($snap->micro_os) . "\n";
            echo "  Source Micro OS:   " . number_format($sourceMicro) . "\n";
            echo "  Micro Difference:  " . number_format($snap->micro_os - $sourceMicro) . "\n";
            
            break; // Just one example
        }
    }
}
