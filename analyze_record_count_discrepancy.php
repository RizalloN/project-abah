<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== INVESTIGATING RECORD COUNT DISCREPANCY ===\n\n";

$periode = '2026-04-19';

// Check what's different between the two tables
echo "1. RECORD COUNT BY CIF (Debitur):\n";
echo str_repeat("-", 80) . "\n";

$dailyByDebitur = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        cifno,
        COUNT(*) as count_entries,
        COUNT(DISTINCT nomor_rekening1) as count_unique_loans,
        SUM(baki_debet1) as total_balance
    ")
    ->groupBy('cifno')
    ->orderByDesc('count_entries')
    ->limit(10)
    ->get();

echo "Top 10 CIFs by entry count in daily_loan_dinamis:\n";
foreach ($dailyByDebitur as $row) {
    $ratio = $row->count_unique_loans > 0 ? ($row->count_entries / $row->count_unique_loans) : 1;
    echo "CIF: " . str_pad((string)$row->cifno, 12) 
        . " | Entries: " . str_pad((string)$row->count_entries, 4)
        . " | Unique Loans: " . str_pad((string)$row->count_unique_loans, 4)
        . " | Ratio: " . str_pad(number_format($ratio, 1, ',', '.'), 5)
        . " | Balance: " . number_format($row->total_balance / 1_000_000, 1, ',', '.') . " M\n";
}

// Check status_rekening - maybe multiple statuses per loan?
echo "\n\n2. BREAKDOWN BY STATUS REKENING:\n";
echo str_repeat("-", 80) . "\n";

$byStatus = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        status_rekening1,
        COUNT(*) as count_records,
        COUNT(DISTINCT cifno) as unique_cif,
        COUNT(DISTINCT nomor_rekening1) as unique_loans,
        SUM(baki_debet1) as total_balance
    ")
    ->groupBy('status_rekening1')
    ->orderByDesc('count_records')
    ->get();

foreach ($byStatus as $row) {
    echo str_pad((string)$row->status_rekening1, 20) 
        . " | Records: " . str_pad((string)$row->count_records, 5)
        . " | CIF: " . str_pad((string)$row->unique_cif, 5)
        . " | Loans: " . str_pad((string)$row->unique_loans, 5)
        . " | Balance: " . number_format($row->total_balance / 1_000_000, 1, ',', '.') . " M\n";
}

// Check ln_type - maybe multiple loan types per account?
echo "\n\n3. BREAKDOWN BY LOAN TYPE (ln_type):\n";
echo str_repeat("-", 80) . "\n";

$byLnType = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        ln_type,
        COUNT(*) as count_records,
        COUNT(DISTINCT cifno) as unique_cif,
        COUNT(DISTINCT nomor_rekening1) as unique_loans,
        SUM(baki_debet1) as total_balance
    ")
    ->groupBy('ln_type')
    ->orderByDesc('count_records')
    ->get();

foreach ($byLnType as $row) {
    echo str_pad((string)$row->ln_type, 30) 
        . " | Records: " . str_pad((string)$row->count_records, 5)
        . " | CIF: " . str_pad((string)$row->unique_cif, 5)
        . " | Loans: " . str_pad((string)$row->unique_loans, 5)
        . " | Balance: " . number_format($row->total_balance / 1_000_000, 1, ',', '.') . " M\n";
}

// Compare with SSA Pinjaman structure
echo "\n\n4. SSA_PINJAMAN STRUCTURE:\n";
echo str_repeat("-", 80) . "\n";

$ssaByDebitur = DB::table('ssa_pinjaman')
    ->where('month_day_year_of_periode', $periode)
    ->where('nama_cabang', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        COUNT(*) as total_records,
        COUNT(DISTINCT cif) as unique_cif,
        SUM(baki_debet) as total_balance
    ")
    ->first();

echo "Total records: " . $ssaByDebitur->total_records . "\n";
echo "Unique CIFs: " . $ssaByDebitur->unique_cif . "\n";
echo "Total balance: " . number_format($ssaByDebitur->total_balance / 1_000_000, 1, ',', '.') . " M\n";

// Final comparison
echo "\n\n5. FINAL COMPARISON:\n";
echo str_repeat("-", 80) . "\n";

$dailyTotal = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', '%MADIUN%')
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        COUNT(*) as total_records,
        COUNT(DISTINCT cifno) as unique_cif,
        COUNT(DISTINCT nomor_rekening1) as unique_loans,
        SUM(baki_debet1) as total_balance
    ")
    ->first();

echo "daily_loan_dinamis:\n";
echo "  Total records: " . number_format($dailyTotal->total_records, 0, ',', '.') . "\n";
echo "  Unique CIFs: " . number_format($dailyTotal->unique_cif, 0, ',', '.') . "\n";
echo "  Unique Loans: " . number_format($dailyTotal->unique_loans, 0, ',', '.') . "\n";
echo "  Total balance: " . number_format($dailyTotal->total_balance / 1_000_000, 1, ',', '.') . " M\n\n";

echo "ssa_pinjaman:\n";
echo "  Total records: " . number_format($ssaByDebitur->total_records, 0, ',', '.') . "\n";
echo "  Unique CIFs: " . number_format($ssaByDebitur->unique_cif, 0, ',', '.') . "\n";
echo "  Total balance: " . number_format($ssaByDebitur->total_balance / 1_000_000, 1, ',', '.') . " M\n\n";

echo "ANALYSIS:\n";
echo "  Entries ratio: " . number_format($dailyTotal->total_records / $ssaByDebitur->total_records, 2, ',', '.') . "x\n";
echo "  CIF ratio: " . number_format($dailyTotal->unique_cif / $ssaByDebitur->unique_cif, 2, ',', '.') . "x\n";
echo "  Balance ratio: " . number_format($dailyTotal->total_balance / $ssaByDebitur->total_balance, 2, ',', '.') . "x\n\n";

if ($dailyTotal->total_records > $ssaByDebitur->total_records && 
    abs($dailyTotal->total_balance - $ssaByDebitur->total_balance) < 1_000_000) {
    echo "✓ Likely cause: Multiple loan facilities per CIF\n";
    echo "  Each CIF in daily_loan has multiple loan records\n";
    echo "  SSA_Pinjaman aggregates them at CIF level\n";
} else {
    echo "⚠️ Different data source or filtering applied\n";
}
?>
