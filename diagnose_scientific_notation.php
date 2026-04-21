#!/usr/bin/env php
<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== DAILY LOAN TABLE DIAGNOSIS ===\n\n";

// Check column type
$columns = DB::select("SHOW COLUMNS FROM daily_loan_dinamis WHERE Field = 'nomor_rekening1'");
echo "Column definition:\n";
foreach ($columns as $col) {
    echo "  Type: {$col->Type}\n";
    echo "  Null: {$col->Null}\n";
    echo "  Default: {$col->Default}\n";
}

echo "\nScientific notation stats:\n";
$sci = DB::select("SELECT COUNT(*) as cnt FROM daily_loan_dinamis WHERE nomor_rekening1 REGEXP '[eE]'");
echo "  Records with scientific notation: " . $sci[0]->cnt . "\n";

$total = DB::table('daily_loan_dinamis')->count();
echo "  Total records: " . $total . "\n";
echo "  Percentage: " . round(($sci[0]->cnt / $total) * 100, 2) . "%\n";

echo "\nSample records:\n";

// With scientific notation
echo "\n1. With scientific notation:\n";
$samples = DB::table('daily_loan_dinamis')
    ->where(DB::raw('nomor_rekening1'), 'REGEXP', '[eE]')
    ->limit(3)
    ->get();
foreach ($samples as $i => $row) {
    echo "  [{$i}] nomor_rekening1: " . $row->nomor_rekening1 . "\n";
}

// Without scientific notation
echo "\n2. Without scientific notation:\n";
$samples = DB::table('daily_loan_dinamis')
    ->where(DB::raw('nomor_rekening1'), 'NOT REGEXP', '[eE]')
    ->limit(3)
    ->get();
foreach ($samples as $i => $row) {
    echo "  [{$i}] nomor_rekening1: " . $row->nomor_rekening1 . "\n";
}

// Check what the import controller does
echo "\n\n=== IMPORT CONTROLLER CHECK ===\n\n";

$controllerFile = __DIR__ . '/app/Http/Controllers/Import/ImportExcelController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    
    // Check for CAST AS CHAR in getCsvPreviewLimits
    if (strpos($content, "'enable_fast_path' => true") !== false) {
        echo "✓ Fast path routing found in ImportExcelController\n";
    }
    
    // Check for text-only columns handling
    if (strpos($content, "NOMOR_REKENING1") !== false) {
        echo "✓ NOMOR_REKENING1 handling present\n";
    }
}

// Check OptimizedCsvImporter
echo "\n=== OPTIMIZED CSV IMPORTER CHECK ===\n\n";

$importerFile = __DIR__ . '/app/Services/Import/OptimizedCsvImporter.php';
if (file_exists($importerFile)) {
    $content = file_get_contents($importerFile);
    
    // Look for CAST AS CHAR
    $matches = [];
    if (preg_match_all('/CAST\s*\(\s*`?nomor_rekening1`?\s*AS\s*CHAR/i', $content, $matches)) {
        echo "✓ Found " . count($matches[0]) . " CAST AS CHAR for nomor_rekening1\n";
        echo "  Locations: lines with CAST AS CHAR\n";
    } else {
        echo "❌ No CAST AS CHAR found for nomor_rekening1!\n";
    }
    
    // Look for text-only columns array
    if (preg_match('/text_only_columns|textOnlyColumns/i', $content)) {
        echo "✓ Text-only columns array defined\n";
    }
}

echo "\n";
?>
