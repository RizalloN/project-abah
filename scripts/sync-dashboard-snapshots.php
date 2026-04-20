#!/usr/bin/env php
<?php

/**
 * Manual Dashboard Harian Snapshot Sync Script
 * 
 * Usage:
 *   php scripts/sync-dashboard-snapshots.php              # Sync missing periods
 *   php scripts/sync-dashboard-snapshots.php --force      # Force rebuild all
 *   php scripts/sync-dashboard-snapshots.php --period 2026-04-18  # Specific period
 *   php scripts/sync-dashboard-snapshots.php --detail     # Show detailed results
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardHarianSnapshotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Parse arguments
$force = in_array('--force', $argv);
$detail = in_array('--detail', $argv);
$specificPeriod = null;

foreach ($argv as $key => $arg) {
    if ($arg === '--period' && isset($argv[$key + 1])) {
        $specificPeriod = $argv[$key + 1];
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Dashboard Harian Snapshot Manual Sync Script              ║\n";
echo "║  " . date('Y-m-d H:i:s') . "                              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $service = $app->make(DashboardHarianSnapshotService::class);
    
    // Get shared periods
    echo "[1/4] 📊 Scanning SSA periods...\n";
    $sharedPeriods = resolveSharedPeriods();
    echo "      Found " . count($sharedPeriods) . " shared periods\n";
    
    if (empty($sharedPeriods)) {
        echo "\n❌ No shared periods found. Aborting.\n\n";
        exit(1);
    }
    
    // Get existing snapshots
    echo "[2/4] 📦 Checking existing snapshots...\n";
    $existingSnapshots = DB::table('dashboard_harian_snapshots')
        ->select('snapshot_period')
        ->distinct()
        ->pluck('snapshot_period')
        ->map(fn ($val) => (string) $val)
        ->all();
    
    echo "      Found " . count($existingSnapshots) . " existing snapshots\n";
    
    // Determine periods to rebuild
    echo "[3/4] 🔍 Analyzing periods...\n";
    
    if ($specificPeriod) {
        $periodsToRebuild = [$specificPeriod];
        echo "      Target: Specific period '" . $specificPeriod . "'\n";
    } elseif ($force) {
        $periodsToRebuild = $sharedPeriods;
        echo "      Mode: Force rebuild all " . count($sharedPeriods) . " periods\n";
    } else {
        $missingPeriods = array_diff($sharedPeriods, $existingSnapshots);
        $periodsToRebuild = array_values($missingPeriods);
        
        if (empty($periodsToRebuild)) {
            echo "      ✅ All snapshots are up to date!\n\n";
            echo "Stats:\n";
            echo "  • Total shared periods: " . count($sharedPeriods) . "\n";
            echo "  • Existing snapshots: " . count($existingSnapshots) . "\n";
            echo "  • Missing periods: 0\n";
            echo "\n";
            exit(0);
        }
        
        echo "      Found " . count($periodsToRebuild) . " missing periods to rebuild\n";
    }
    
    // Rebuild snapshots
    echo "[4/4] 🔨 Rebuilding snapshots...\n\n";
    
    $results = [];
    $startTime = microtime(true);
    $successCount = 0;
    $failureCount = 0;
    $failedPeriods = [];
    
    // Create progress bar
    $total = count($periodsToRebuild);
    
    foreach ($periodsToRebuild as $index => $period) {
        $current = $index + 1;
        $percentage = (int) (($current / $total) * 100);
        $barLength = 40;
        $filledLength = (int) ($barLength * $current / $total);
        $bar = str_repeat('=', $filledLength) . str_repeat(' ', $barLength - $filledLength);
        
        try {
            $count = $service->buildPeriodSnapshot($period, $force);
            $results[$period] = $count;
            
            if ($count > 0) {
                $successCount++;
                $status = "✓";
            } else {
                $failureCount++;
                $status = "✗";
                $failedPeriods[] = $period;
            }
            
            echo "\r      [{$bar}] {$percentage}% ({$current}/{$total}) {$status} {$period}";
            
        } catch (Throwable $e) {
            $failureCount++;
            $failedPeriods[] = $period;
            echo "\r      [{$bar}] {$percentage}% ({$current}/{$total}) ✗ {$period}";
        }
    }
    
    echo "\n\n";
    
    // Calculate timing
    $duration = microtime(true) - $startTime;
    $avgTime = $total > 0 ? $duration / $total : 0;
    
    // Display results
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║  SYNC COMPLETE                                             ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "Results:\n";
    echo "  • Periods processed: " . $total . "\n";
    echo "  • Successful: " . $successCount . " ✓\n";
    echo "  • Failed: " . $failureCount . " ✗\n";
    echo "  • Duration: " . number_format($duration, 2) . "s\n";
    echo "  • Avg per period: " . number_format($avgTime, 3) . "s\n";
    
    if ($detail && !empty($results)) {
        echo "\nDetailed Results:\n";
        echo "  Period              | Rows Built\n";
        echo "  ────────────────────┼───────────\n";
        
        foreach ($results as $period => $count) {
            printf("  %-19s | %9d\n", $period, $count);
        }
    }
    
    if (!empty($failedPeriods)) {
        echo "\n⚠️  Failed Periods:\n";
        foreach ($failedPeriods as $period) {
            echo "  • " . $period . "\n";
        }
    }
    
    // Summary stats
    echo "\nSnapshot Status:\n";
    $totalSnapshots = DB::table('dashboard_harian_snapshots')
        ->select('snapshot_period')
        ->distinct()
        ->count();
    
    $latestSnapshot = DB::table('dashboard_harian_snapshots')
        ->select('snapshot_period')
        ->distinct()
        ->orderByDesc('snapshot_period')
        ->first();
    
    echo "  • Total snapshots in DB: " . $totalSnapshots . "\n";
    echo "  • Latest period: " . ($latestSnapshot ? $latestSnapshot->snapshot_period : 'N/A') . "\n";
    
    // Log to file
    $logFile = storage_path('logs/snapshot-sync-' . date('Y-m-d_H-i-s') . '.log');
    file_put_contents($logFile, json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'mode' => $force ? 'force' : ($specificPeriod ? 'specific' : 'sync-missing'),
        'total_periods' => $total,
        'successful' => $successCount,
        'failed' => $failureCount,
        'duration_seconds' => $duration,
        'results' => $results,
        'failed_periods' => $failedPeriods,
    ], JSON_PRETTY_PRINT));
    
    echo "\n✅ Log saved: " . $logFile . "\n\n";
    
    exit($failureCount > 0 ? 1 : 0);
    
} catch (Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n";
    exit(1);
}

/**
 * Get shared periods from both SSA tables.
 */
function resolveSharedPeriods(): array
{
    try {
        $loanPeriods = DB::table('ssa_pinjaman')
            ->select('month_day_year_of_periode')
            ->distinct()
            ->pluck('month_day_year_of_periode')
            ->map(fn ($val) => normalizeDate((string) $val))
            ->filter()
            ->values()
            ->all();

        $savingsPeriods = DB::table('ssa_simpanan')
            ->select('Month_Day_Year_of_Posisi')
            ->distinct()
            ->pluck('Month_Day_Year_of_Posisi')
            ->map(fn ($val) => normalizeDate((string) $val))
            ->filter()
            ->values()
            ->all();

        $shared = array_values(array_intersect($loanPeriods, $savingsPeriods));
        rsort($shared);

        return $shared;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Normalize date to YYYY-MM-DD format.
 */
function normalizeDate(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    try {
        return Carbon::parse($value)->toDateString();
    } catch (Throwable) {
        return null;
    }
}
