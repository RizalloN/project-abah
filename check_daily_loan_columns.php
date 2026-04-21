<?php
include 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== STRUKTUR TABLE: daily_loan_dinamis ===\n\n";

$columns = Schema::getColumnListing('daily_loan_dinamis');
echo "Total Columns: " . count($columns) . "\n\n";

foreach ($columns as $col) {
    echo "  • $col\n";
}

// Check available columns for balance
echo "\n=== MENCARI KOLOM BALANCE ===\n";
$balanceColumns = array_filter($columns, function($col) {
    return strpos(strtolower($col), 'balance') !== false ||
           strpos(strtolower($col), 'baki') !== false ||
           strpos(strtolower($col), 'debet') !== false ||
           strpos(strtolower($col), 'os') !== false ||
           strpos(strtolower($col), 'nominal') !== false;
});

if ($balanceColumns) {
    echo "Found: " . implode(', ', $balanceColumns) . "\n";
} else {
    echo "No balance columns found. Showing first 10 sample rows:\n\n";
    
    $sample = DB::table('daily_loan_dinamis')
        ->where('periode', '2026-04-19')
        ->limit(1)
        ->first();
    
    if ($sample) {
        foreach ((array)$sample as $key => $value) {
            echo "  " . str_pad($key, 30) . " => " . substr((string)$value, 0, 50) . "\n";
        }
    }
}
?>
