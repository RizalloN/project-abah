<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sourcePath = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$dbLookup = [];
foreach (Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->pluck('nomor_rekening1')->all() as $rek) {
    $dbLookup[trim((string) $rek)] = true;
}

$handle = fopen($sourcePath, 'rb');
$lineNum = 0;
$headerSeen = false;
$missing = 0;
while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;
    $row = str_getcsv($line, ',', '"', '\\');
    if (!$headerSeen) {
        if (($row[0] ?? '') === 'PERIODE') {
            $headerSeen = true;
        }
        continue;
    }
    $rek = trim((string) ($row[10] ?? ''));
    if ($rek !== '' && !isset($dbLookup[$rek])) {
        $missing++;
    }
}
fclose($handle);

echo json_encode([
    'period_count' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->count(),
    'missing_from_db' => $missing,
    'null_period_rows_for_fix_prefix' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('uniqueid_namareport', 'like', 'imp69e748adde3fa830331821_%')->whereNull('periode')->count(),
    'total_rows' => Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
