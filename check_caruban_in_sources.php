<?php
require_once __DIR__ . '/bootstrap/app.php';
use Illuminate\Support\Facades\DB;

$periode = '2026-04-19';

echo "=== CHECKING WHERE CARUBAN DATA COMES FROM ===\n\n";

echo "1. Searching for 'CARUBAN' in daily_loan_dinamis:\n";
echo "   Periode: $periode\n";
echo "   Search: UPPER(cabang1) LIKE '%CARUBAN%'\n\n";

$carubanDaily = DB::table('daily_loan_dinamis')
    ->whereRaw("periode = ?", [$periode])
    ->whereRaw("UPPER(cabang1) LIKE '%CARUBAN%'")
    ->selectRaw("cabang1, COUNT(*) as cnt, SUM(baki_debet1) as total")
    ->groupBy('cabang1')
    ->get();

if ($carubanDaily->count() == 0) {
    echo "❌ NO CARUBAN ENTRIES FOUND in daily_loan_dinamis\n\n";
} else {
    foreach ($carubanDaily as $row) {
        echo "  " . $row->cabang1 . ": " . number_format($row->total / 1_000_000, 1, ',', '.') . " M (" . $row->cnt . " records)\n";
    }
}

echo "\n2. Checking ssa_pinjaman for CARUBAN entries:\n";
$carubanSSA = DB::table('ssa_pinjaman')
    ->whereRaw("periode = ?", [$periode])
    ->whereRaw("UPPER(nama_cabang) LIKE '%CARUBAN%' OR UPPER(nama_uker) LIKE '%CARUBAN%'")
    ->selectRaw("nama_cabang, nama_uker, COUNT(*) as cnt, SUM(baki_debet) as total")
    ->groupBy('nama_cabang', 'nama_uker')
    ->get();

if ($carubanSSA->count() == 0) {
    echo "❌ NO CARUBAN ENTRIES FOUND in ssa_pinjaman\n\n";
} else {
    foreach ($carubanSSA as $row) {
        echo "  " . $row->nama_cabang . " - " . $row->nama_uker . ": " . number_format($row->total / 1_000_000, 1, ',', '.') . " M (" . $row->cnt . " records)\n";
    }
}

echo "\n3. Checking dashboard_harian_snapshots for Kcp Caruban:\n";
$carubanDash = DB::table('dashboard_harian_snapshots')
    ->whereRaw("periode = ?", [$periode])
    ->whereRaw("UPPER(unit_label) LIKE '%CARUBAN%'")
    ->selectRaw("kanca_label, unit_label, COUNT(*) as cnt, SUM(baki_debet_briguna) + SUM(baki_debet_kpr) as total")
    ->groupBy('kanca_label', 'unit_label')
    ->get();

if ($carubanDash->count() == 0) {
    echo "❌ NO CARUBAN ENTRIES FOUND in dashboard_harian_snapshots\n\n";
} else {
    foreach ($carubanDash as $row) {
        echo "  " . $row->kanca_label . " / " . $row->unit_label . ": " . number_format($row->total / 1_000_000, 1, ',', '.') . " M\n";
    }
}

echo "\n4. Summary:\n";
echo "   - Caruban in daily_loan_dinamis: " . ($carubanDaily->count() > 0 ? "YES" : "NO") . "\n";
echo "   - Caruban in ssa_pinjaman: " . ($carubanSSA->count() > 0 ? "YES" : "NO") . "\n";
echo "   - Caruban in dashboard: " . ($carubanDash->count() > 0 ? "YES" : "NO") . "\n";
