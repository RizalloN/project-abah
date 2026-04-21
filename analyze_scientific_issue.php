#!/usr/bin/env php
<?php
/**
 * Deep Analysis: Scientific Notation Issue in daily_loan_dinamis
 * 
 * Purpose:
 * - Determine TRUE cause of scientific notation "detection"
 * - Check if it's actual storage issue or false positive in regex
 * - Recommend proper fix
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  SCIENTIFIC NOTATION ANALYSIS - ROOT CAUSE INVESTIGATION       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Get actual samples
    echo "📋 Fetching sample records that match [eE] pattern...\n\n";

    $samples = DB::table('daily_loan_dinamis')
        ->limit(10)
        ->get(['id', 'nomor_rekening1', 'periode'])
        ->toArray();

    if (!empty($samples)) {
        foreach ($samples as $i => $row) {
            $value = $row->nomor_rekening1;
            $len = strlen($value);
            $isNumeric = is_numeric($value);
            $hasE = stripos($value, 'E') !== false;
            
            echo "Sample " . ($i+1) . ":\n";
            echo "  Value: '{$value}'\n";
            echo "  Length: {$len}\n";
            echo "  Contains E/e: " . ($hasE ? 'YES' : 'NO') . "\n";
            echo "  Is numeric: " . ($isNumeric ? 'YES' : 'NO') . "\n";
            
            // Check if it's actual scientific notation
            if (preg_match('/^[0-9]+\.?[0-9]*[eE][+-]?[0-9]+$/', $value)) {
                echo "  Scientific notation: YES (confirmed)\n";
            } else if (preg_match('/[eE]/', $value)) {
                echo "  Scientific notation: NO (false positive - contains E)\n";
            }
            echo "\n";
        }
    } else {
        echo "No records found\n";
    }

    // Test the actual patterns
    echo "\n=== REGEX PATTERN ANALYSIS ===\n\n";
    
    echo "1. Current pattern used: [eE]\n";
    echo "   - Matches ANY record with letter 'e' or 'E'\n";
    echo "   - PROBLEM: Too broad, matches valid account numbers\n\n";
    
    echo "2. Proper scientific notation pattern:\n";
    echo "   - Scientific notation: 1.23E+10 or 1.23e-5\n";
    echo "   - Regex: /^[0-9]+\\.?[0-9]*[eE][+-]?[0-9]+$/\n";
    echo "   - Examples match: 1.5E+6, 2E-3, 123E+10\n\n";
    
    // Check if any match the REAL scientific notation pattern
    $realSciCount = DB::select("
        SELECT COUNT(*) as cnt FROM daily_loan_dinamis 
        WHERE nomor_rekening1 REGEXP '^[0-9]+\\.?[0-9]*[eE][+-]?[0-9]+\$'
    ");
    
    echo "3. Records with REAL scientific notation:\n";
    echo "   Count: " . $realSciCount[0]->cnt . "\n\n";

    // Show what IS matching the [eE] pattern
    echo "4. What's actually matching [eE] pattern?\n";
    
    $patterns = DB::select("
        SELECT 
            COUNT(*) as cnt,
            CASE 
                WHEN nomor_rekening1 REGEXP '^[0-9]+\\.[0-9]+[eE][+-]?[0-9]+\$' THEN 'Real scientific'
                WHEN nomor_rekening1 LIKE '%E%' THEN 'Contains E (uppercase)'
                WHEN nomor_rekening1 LIKE '%e%' THEN 'Contains e (lowercase)'
                ELSE 'Other'
            END as category
        FROM daily_loan_dinamis
        WHERE nomor_rekening1 REGEXP '[eE]'
        GROUP BY category
    ");

    foreach ($patterns as $p) {
        echo "   {$p->category}: " . $p->cnt . "\n";
    }

    // Column type verification
    echo "\n=== COLUMN TYPE VERIFICATION ===\n\n";
    
    $cols = DB::select("SHOW COLUMNS FROM daily_loan_dinamis WHERE Field = 'nomor_rekening1'");
    foreach ($cols as $col) {
        echo "Type: {$col->Type}\n";
        echo "Null: {$col->Null}\n";
        echo "Default: {$col->Default}\n";
    }

    // CONCLUSION
    echo "\n=== CONCLUSION ===\n\n";
    
    if ($realSciCount[0]->cnt === 0) {
        echo "✅ GOOD NEWS: No true scientific notation in database!\n";
        echo "   The [eE] pattern matches legitimate account numbers containing 'E'\n";
        echo "   This is a FALSE POSITIVE in the detection regex.\n\n";
    } else {
        echo "❌ Data issue: {$realSciCount[0]->cnt} records are actual scientific notation\n\n";
    }

} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
?>
