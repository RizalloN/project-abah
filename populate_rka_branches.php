<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Copying RKA Data to Other Branches ===\n\n";

// Get all Ponorogo RKA data
$ponorogo_data = DB::table('rka')
    ->where('kanca', 'KC Ponorogo')
    ->get();

echo "Found " . count($ponorogo_data) . " RKA rows for KC Ponorogo\n";

// Target branches
$target_branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi'];

$total_inserted = 0;

foreach ($target_branches as $branch) {
    echo "\nCopying data to $branch...\n";
    
    $rows_to_insert = [];
    
    foreach ($ponorogo_data as $row) {
        // Generate new unique ID
        $unique_id = $branch . '_' . $row->desc_uker . '_' . $row->mata_anggaran . '_' . uniqid();
        
        $rows_to_insert[] = [
            'uniqueid_namareport' => $unique_id,
            'tahun' => $row->tahun,
            'kanca' => $branch,
            'desc_uker' => $row->desc_uker,
            'mata_anggaran' => $row->mata_anggaran,
            'jan' => $row->jan,
            'feb' => $row->feb,
            'mar' => $row->mar,
            'apr' => $row->apr,
            'may' => $row->may,
            'jun' => $row->jun,
            'jul' => $row->jul,
            'aug' => $row->aug,
            'sep' => $row->sep,
            'oct' => $row->oct,
            'nov' => $row->nov,
            'dec' => $row->dec,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    
    // Insert in batches
    foreach (array_chunk($rows_to_insert, 1000) as $batch) {
        try {
            DB::table('rka')->insert($batch);
            $total_inserted += count($batch);
            echo "  Inserted " . count($batch) . " rows\n";
        } catch (\Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Total rows inserted: $total_inserted\n";

// Verify
$branches_after = DB::table('rka')
    ->select('kanca')
    ->distinct()
    ->orderBy('kanca')
    ->pluck('kanca');

echo "\nBranches in RKA table now:\n";
foreach($branches_after as $b) {
    $count = DB::table('rka')->where('kanca', $b)->count();
    echo "  - $b: $count rows\n";
}
