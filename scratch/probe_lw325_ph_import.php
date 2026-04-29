<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "DB recent lw325_ph groups:\n";
$rows = DB::table('lw325_ph')
    ->selectRaw('periode, DATE(created_at) as created_date, COUNT(*) as total, MIN(created_at) as first_created, MAX(created_at) as last_created')
    ->groupBy('periode', DB::raw('DATE(created_at)'))
    ->orderByDesc('last_created')
    ->limit(10)
    ->get();
echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$paths = [
    'storage/app/report_ph_imports/2023_LW325_PH_V7_30042025_baru.csv',
    'storage/app/report_ph_bulk_stage/lw325_ph_1776833804177_uKHgJmot.csv',
    'storage/app/temp/lw325_compare_source.csv',
];

foreach ($paths as $path) {
    echo "FILE {$path}\n";
    if (!is_file($path)) {
        echo "missing\n\n";
        continue;
    }

    $handle = fopen($path, 'r');
    $counts = [];
    $examples = [];
    for ($i = 0; ($row = fgetcsv($handle, 0, ',')) !== false && $i < 2000; $i++) {
        $count = count($row);
        $counts[$count] = ($counts[$count] ?? 0) + 1;
        if (!isset($examples[$count])) {
            $examples[$count] = [$i, array_slice($row, 0, 15)];
        }
    }
    fclose($handle);

    ksort($counts);
    echo 'counts=' . json_encode($counts) . "\n";
    foreach ($examples as $count => [$line, $sample]) {
        echo "count={$count} line={$line} sample=" . substr(json_encode($sample, JSON_UNESCAPED_UNICODE), 0, 500) . "\n";
    }
    echo "\n";
}

$sourcePath = 'storage/app/report_ph_imports/2023_LW325_PH_V7_30042025_baru.csv';
if (is_file($sourcePath)) {
    echo "Semicolon source probe:\n";
    $handle = fopen($sourcePath, 'r');
    for ($i = 0; $i < 6 && (($row = fgetcsv($handle, 0, ';')) !== false); $i++) {
        echo $i . ':' . count($row) . ':' . substr(json_encode(array_slice($row, 0, 75), JSON_UNESCAPED_UNICODE), 0, 1500) . "\n";
    }
    fclose($handle);
}

echo "\nFailed import candidate counts:\n";
$failedRows = DB::table('lw325_ph')
    ->selectRaw('created_at, COUNT(*) as total, COUNT(DISTINCT periode) as periods, MIN(periode) as min_period, MAX(periode) as max_period')
    ->whereDate('created_at', '2026-04-29')
    ->groupBy('created_at')
    ->orderByDesc('total')
    ->limit(10)
    ->get();
echo json_encode($failedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$sample = DB::table('lw325_ph')
    ->select('uniqueid_namareport', 'periode', 'acctno', 'kanca', 'unit', 'nama_debitur', 'pokok', 'bunga', 'created_at')
    ->orderByDesc('created_at')
    ->limit(5)
    ->get();
echo "DB latest samples:\n";
echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

if (($argv[1] ?? '') === '--delete-failed-20260429') {
    $query = DB::table('lw325_ph')
        ->where('created_at', '2026-04-29 16:32:49')
        ->where('uniqueid_namareport', 'like', '%\_DLD');

    $count = (clone $query)->count();
    echo "\nDeleting failed LW325_PH rows: {$count}\n";
    $deleted = $query->delete();
    echo "Deleted: {$deleted}\n";
}
