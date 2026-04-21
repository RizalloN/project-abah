<?php
/**
 * Find Duplicate Loans in daily_loan_dinamis
 * Helps identify where the extra records are coming from
 */

include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== FINDING DUPLICATE LOANS ===\n\n";

$periode = '2026-04-19';
$cabang = '%MADIUN%';

echo "Period: $periode\n";
echo "Cabang: $cabang\n\n";

// Find loans with multiple entries in daily_loan_dinamis
$duplicates = DB::table('daily_loan_dinamis')
    ->where('periode', $periode)
    ->where('cabang1', 'LIKE', $cabang)
    ->whereRaw("UPPER(TRIM(segmen_dashboard)) = 'CONSUMER'")
    ->selectRaw("
        nomor_rekening1,
        cifno,
        COUNT(*) as entry_count,
        SUM(baki_debet1) as total_balance,
        GROUP_CONCAT(DISTINCT nama_debitur1) as debitur_names,
        GROUP_CONCAT(DISTINCT produk_dashboard) as produk_list
    ")
    ->groupBy('nomor_rekening1', 'cifno')
    ->having('entry_count', '>', 1)
    ->orderByDesc('entry_count')
    ->orderByDesc('total_balance')
    ->limit(20)
    ->get();

echo "Found " . count($duplicates) . " loans with multiple entries (showing top 20):\n\n";

if (count($duplicates) > 0) {
    echo str_repeat("-", 120) . "\n";
    echo "NO. | REKENING      | CIF    | ENTRIES | TOTAL BALANCE | DEBITUR | PRODUCTS\n";
    echo str_repeat("-", 120) . "\n";
    
    $no = 1;
    $totalDuplicated = 0;
    $totalBalance = 0;
    
    foreach ($duplicates as $dup) {
        $extraEntries = $dup->entry_count - 1;
        $totalDuplicated += $extraEntries;
        $totalBalance += $dup->total_balance;
        
        $rekening = substr((string)$dup->nomor_rekening1, 0, 14);
        $debitur = substr((string)$dup->debitur_names, 0, 20);
        $produk = substr((string)$dup->produk_list, 0, 30);
        
        echo str_pad((string)$no++, 4) . "| " 
            . str_pad($rekening, 14) . "| "
            . str_pad((string)$dup->cifno, 7) . "| "
            . str_pad((string)$dup->entry_count, 8) . "| "
            . str_pad(number_format($dup->total_balance / 1_000_000, 2, ',', '.') . ' M', 14) . "| "
            . str_pad($debitur, 8) . "| "
            . $produk . "\n";
    }
    
    echo str_repeat("-", 120) . "\n";
    echo "SUMMARY:\n";
    echo "  Total loans with duplicates: " . count($duplicates) . "\n";
    echo "  Total duplicate entries: " . $totalDuplicated . "\n";
    echo "  Total balance in duplicated loans: " . number_format($totalBalance / 1_000_000, 1, ',', '.') . " M\n";
} else {
    echo "✓ No duplicate loans found!\n";
}

// Check if records are from same source or different historical versions
echo "\n\nDETAIL CHECK - Checking why there are multiple entries:\n";
echo str_repeat("-", 80) . "\n";

$firstDup = $duplicates->first();
if ($firstDup) {
    $detailRows = DB::table('daily_loan_dinamis')
        ->where('periode', $periode)
        ->where('nomor_rekening1', $firstDup->nomor_rekening1)
        ->select([
            'created_at',
            'updated_at',
            'baki_debet1',
            'produk_dashboard',
            'ln_type',
            'status_rekening1',
            'uniqueid_namareport'
        ])
        ->orderBy('created_at')
        ->get();
    
    echo "Example loan: " . $firstDup->nomor_rekening1 . "\n";
    echo "Debitur: " . $firstDup->debitur_names . "\n";
    echo "Total entries: " . $firstDup->entry_count . "\n\n";
    
    foreach ($detailRows as $idx => $row) {
        echo "Entry " . ($idx + 1) . ":\n";
        echo "  Created: " . $row->created_at . "\n";
        echo "  Updated: " . $row->updated_at . "\n";
        echo "  Balance: " . number_format($row->baki_debet1 / 1_000_000, 2, ',', '.') . " M\n";
        echo "  Product: " . $row->produk_dashboard . "\n";
        echo "  Ln Type: " . $row->ln_type . "\n";
        echo "  Status: " . $row->status_rekening1 . "\n";
        echo "  Report ID: " . $row->uniqueid_namareport . "\n\n";
    }
}

echo "\n=== RECOMMENDATIONS ===\n";
echo "1. If entries have SAME balance -> Possible duplicate insert\n";
echo "2. If entries have DIFFERENT balance -> Possible historical tracking\n";
echo "3. Check created_at timestamps to identify pattern\n";
echo "4. Review data load process for " . $periode . "\n";
?>
