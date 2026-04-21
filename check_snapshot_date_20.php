<?php
require 'bootstrap/app.php';
require 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Get latest SSA periods
$ssaPinjaman = DB::table('ssa_pinjaman')
    ->select('month_day_year_of_periode as periode')
    ->distinct()
    ->orderBy('periode', 'desc')
    ->limit(5)
    ->pluck('periode');

$ssaSimpanan = DB::table('ssa_simpanan')
    ->select('Month_Day_Year_of_Posisi as periode')
    ->distinct()
    ->orderBy('Month_Day_Year_of_Posisi', 'desc')
    ->limit(5)
    ->pluck('periode');

$snapshots = DB::table('dashboard_harian_snapshots')
    ->select('snapshot_period')
    ->distinct()
    ->orderBy('snapshot_period', 'desc')
    ->limit(5)
    ->pluck('snapshot_period');

echo "=== SSA PINJAMAN (Latest 5) ===\n";
foreach ($ssaPinjaman as $p) {
    echo "  - $p\n";
}

echo "\n=== SSA SIMPANAN (Latest 5) ===\n";
foreach ($ssaSimpanan as $p) {
    echo "  - $p\n";
}

echo "\n=== SNAPSHOT (Latest 5) ===\n";
foreach ($snapshots as $p) {
    echo "  - $p\n";
}

// Check if 2026-04-20 exists
$pinjaman20 = DB::table('ssa_pinjaman')->where('month_day_year_of_periode', '2026-04-20')->count();
$simpanan20 = DB::table('ssa_simpanan')->where('Month_Day_Year_of_Posisi', '2026-04-20')->count();
$snap20 = DB::table('dashboard_harian_snapshots')->where('snapshot_period', '2026-04-20')->count();

echo "\n=== DATE 2026-04-20 CHECK ===\n";
echo "  SSA Pinjaman rows: $pinjaman20\n";
echo "  SSA Simpanan rows: $simpanan20\n";
echo "  Snapshot rows: $snap20\n";

// Check if both SSA tables have the date
if ($pinjaman20 > 0 && $simpanan20 > 0) {
    echo "\n✓ Both SSA tables have 2026-04-20 data\n";
    if ($snap20 === 0) {
        echo "⚠ But snapshot is MISSING for 2026-04-20 - needs rebuild!\n";
    } else {
        echo "✓ Snapshot exists for 2026-04-20\n";
    }
} else {
    echo "\n✗ One or both SSA tables missing 2026-04-20 data\n";
}
