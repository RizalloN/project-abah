<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Analyzing RKA Data Structure ===\n\n";

// Check sample RKA data detail
echo "Sample RKA data for each branch:\n";
$branches = ['KC Madiun', 'KC Magetan', 'KC Ngawi', 'KC Ponorogo'];

foreach($branches as $branch) {
    echo "\n--- $branch ---\n";
    $data = DB::table('rka')
        ->where('kanca', $branch)
        ->select('uniqueid_namareport', 'desc_uker', 'mata_anggaran', 'feb')
        ->orderBy('desc_uker')
        ->limit(5)
        ->get();
    
    foreach($data as $row) {
        echo sprintf("ID: %s | Uker: %s | Mata: %s | Feb: %s\n",
            substr($row->uniqueid_namareport, 0, 50),
            $row->desc_uker,
            $row->mata_anggaran,
            $row->feb
        );
    }
}

// Check uniqueid patterns
echo "\n\n=== Checking unique ID patterns ===\n";
$sample_ids = DB::table('rka')
    ->select('uniqueid_namareport')
    ->distinct()
    ->orderBy('uniqueid_namareport')
    ->limit(20)
    ->get();

foreach($sample_ids as $row) {
    echo "  - {$row->uniqueid_namareport}\n";
}

// Check if data is really duplicated or just the same mata_anggaran values
echo "\n\n=== Check aggregated values by branch and mata_anggaran ===\n";
$agg = DB::table('rka')
    ->select('kanca', 'mata_anggaran')
    ->selectRaw('SUM(feb) as total_feb')
    ->selectRaw('COUNT(*) as row_count')
    ->groupBy('kanca', 'mata_anggaran')
    ->where('mata_anggaran', 'like', '%User QRIS%')
    ->orderBy('kanca')
    ->get();

foreach($agg as $row) {
    echo sprintf("%-20s | %-40s | Total: %s | Count: %d\n",
        $row->kanca,
        substr($row->mata_anggaran, 0, 40),
        number_format($row->total_feb, 2),
        $row->row_count
    );
}
