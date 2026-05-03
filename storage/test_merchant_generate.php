<?php
/**
 * Generate sample Jumlah Merchant Detail data untuk testing
 * Output: 50.000 rows CSV file
 */

$csvFile = storage_path('test_merchant_detail_50k.csv');
$fp = fopen($csvFile, 'w');

// Header
fputcsv($fp, [
    'MID', 'TID', 'NAMA_KANCA', 'NAMA_UKER', 'SALES_VOLUME', 'TIERING_SALES_VOLUME', 'POSISI'
]);

$branches = ['KC MADIUN', 'KC MAGETAN', 'KC NGAWI', 'KC PONOROGO'];
$ukers = [
    'KC MADIUN' => ['KCP CARUBAN', 'KCP DOLOPO', 'KCP SUDIRMAN MADIUN', 'UNIT JIWAN'],
    'KC MAGETAN' => ['KCP MAGETAN', 'UNIT MAGETAN A', 'UNIT MAGETAN B'],
    'KC NGAWI' => ['KCP NGAWI', 'UNIT NGAWI A'],
    'KC PONOROGO' => ['KCP PONOROGO', 'UNIT PONOROGO A', 'UNIT PONOROGO B'],
];

$salesVolumeLevels = [0, 500000, 5000000, 20000000, 50000000];
$tieringLevels = ['0', '1 - <1jt', '1jt - <15jt', '15jt - <50jt', '>=50jt'];
$posisi = date('Y-m-d');

$totalRows = 50000;
$rowsPerBranch = $totalRows / count($branches);
$midCounter = 1000000;
$tidCounter = 2000000;

for ($i = 0; $i < $totalRows; $i++) {
    $branchIdx = (int)($i / $rowsPerBranch);
    $branch = $branches[$branchIdx] ?? $branches[0];
    $uker = $ukers[$branch][rand(0, count($ukers[$branch]) - 1)];
    
    $svLevel = $salesVolumeLevels[rand(0, count($salesVolumeLevels) - 1)];
    $tierIdx = array_search($svLevel, [0, 500000, 5000000, 20000000, 50000000]);
    $tiering = $tieringLevels[$tierIdx];
    
    fputcsv($fp, [
        'MID' . ($midCounter++),
        'TID' . ($tidCounter++),
        $branch,
        $uker,
        $svLevel,
        $tiering,
        $posisi
    ]);
    
    if ($i % 5000 == 0) {
        echo "Generated $i rows...\n";
    }
}

fclose($fp);
echo "✅ Generated 50.000 rows CSV: {$csvFile}\n";
echo "   File size: " . round(filesize($csvFile) / 1024 / 1024, 2) . " MB\n";
echo "   Posisi: {$posisi}\n";
