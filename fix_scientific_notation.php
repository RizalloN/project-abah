#!/usr/bin/env php
<?php
/**
 * Fix scientific notation in daily_loan_dinamis
 * Converts stored scientific notation (1.23E+10) back to proper text format
 */

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  FIX SCIENTIFIC NOTATION IN daily_loan_dinamis               ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

try {
    // Step 1: Count affected records
    echo "📊 Analyzing affected records...\n";
    
    $sciCount = DB::table('daily_loan_dinamis')
        ->where(DB::raw('nomor_rekening1'), 'REGEXP', '[eE]')
        ->count();
    
    $totalCount = DB::table('daily_loan_dinamis')->count();
    
    echo "  Total records: " . number_format($totalCount) . "\n";
    echo "  With scientific notation: " . number_format($sciCount) . "\n";
    echo "  Percentage: " . round(($sciCount / $totalCount) * 100, 2) . "%\n\n";

    if ($sciCount === 0) {
        echo "✓ No scientific notation found! Database is clean.\n\n";
        exit(0);
    }

    // Step 2: Backup before fixing
    echo "💾 Creating backup...\n";
    $backupTable = 'daily_loan_dinamis_backup_' . date('YmdHis');
    DB::statement("CREATE TABLE {$backupTable} LIKE daily_loan_dinamis");
    DB::statement("INSERT INTO {$backupTable} SELECT * FROM daily_loan_dinamis WHERE nomor_rekening1 REGEXP '[eE]'");
    $backupCount = DB::table($backupTable)->count();
    echo "   Created {$backupTable} with {$backupCount} affected records\n\n";

    // Step 3: Fix the scientific notation
    echo "🔧 Fixing scientific notation in batches...\n";
    
    $pageSize = 10000;
    $offset = 0;
    $fixed = 0;

    while (true) {
        // Get batch of affected records
        $batch = DB::table('daily_loan_dinamis')
            ->where(DB::raw('nomor_rekening1'), 'REGEXP', '[eE]')
            ->limit($pageSize)
            ->offset($offset)
            ->get(['id', 'nomor_rekening1'])
            ->toArray();

        if (empty($batch)) {
            break;
        }

        // Fix each record by reconstructing the value
        foreach ($batch as $record) {
            $value = $record->nomor_rekening1;
            
            // Convert scientific notation to regular number
            // E.g., "1.23E+10" -> "12300000000"
            if (preg_match('/^([0-9.]+)[eE]([+-]?)([0-9]+)$/', $value, $matches)) {
                $mantissa = $matches[1];
                $sign = $matches[2] === '-' ? -1 : 1;
                $exponent = (int)$matches[3] * $sign;
                
                $realValue = (string)((float)$value);
                
                // Update with properly formatted value
                DB::table('daily_loan_dinamis')
                    ->where('id', $record->id)
                    ->update(['nomor_rekening1' => $realValue]);
                
                $fixed++;
            }
        }

        echo "  Progress: {$fixed} / {$sciCount} records fixed\n";
        $offset += $pageSize;
    }

    echo "\n✅ Verification...\n";
    
    $remaining = DB::table('daily_loan_dinamis')
        ->where(DB::raw('nomor_rekening1'), 'REGEXP', '[eE]')
        ->count();
    
    echo "   Remaining with scientific notation: {$remaining}\n";

    if ($remaining === 0) {
        echo "\n✅ ALL SCIENTIFIC NOTATION FIXED!\n";
        echo "   Fixed: " . number_format($fixed) . " records\n";
        echo "   Backup: {$backupTable}\n\n";
    } else {
        echo "\n⚠️  Some records still have scientific notation\n";
        echo "   Remaining: {$remaining}\n\n";
    }

} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "   " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit(1);
}

?>
