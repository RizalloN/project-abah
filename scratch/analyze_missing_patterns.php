<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbLookup = [];
foreach (Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->select('nomor_rekening1')->cursor() as $row) {
    $dbLookup[trim((string) $row->nomor_rekening1)] = true;
}

$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
$lineNum = 0;
$headerSeen = false;
$missingByCabang = [];
$missingByUnit = [];
$missingByLnType = [];
$missingByKodeKanwil = [];
$missingByLenBucket = ['<=900' => 0, '901-1024' => 0, '1025-1200' => 0, '>1200' => 0];
$totalMissing = 0;
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
    if ($rek === '' || isset($dbLookup[$rek])) {
        continue;
    }

    $totalMissing++;
    $cab = trim((string) ($row[4] ?? ''));
    $unit = trim((string) ($row[6] ?? ''));
    $lnType = trim((string) ($row[12] ?? ''));
    $kanwil = trim((string) ($row[1] ?? ''));
    $len = strlen($line);

    $missingByCabang[$cab] = ($missingByCabang[$cab] ?? 0) + 1;
    $missingByUnit[$unit] = ($missingByUnit[$unit] ?? 0) + 1;
    $missingByLnType[$lnType] = ($missingByLnType[$lnType] ?? 0) + 1;
    $missingByKodeKanwil[$kanwil] = ($missingByKodeKanwil[$kanwil] ?? 0) + 1;

    if ($len <= 900) $missingByLenBucket['<=900']++;
    elseif ($len <= 1024) $missingByLenBucket['901-1024']++;
    elseif ($len <= 1200) $missingByLenBucket['1025-1200']++;
    else $missingByLenBucket['>1200']++;
}
fclose($handle);

arsort($missingByCabang);
arsort($missingByUnit);
arsort($missingByLnType);
arsort($missingByKodeKanwil);

echo json_encode([
    'total_missing' => $totalMissing,
    'len_buckets' => $missingByLenBucket,
    'top_cabang' => array_slice($missingByCabang, 0, 10, true),
    'top_unit' => array_slice($missingByUnit, 0, 10, true),
    'top_ln_type' => array_slice($missingByLnType, 0, 10, true),
    'top_kode_kanwil' => array_slice($missingByKodeKanwil, 0, 10, true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
