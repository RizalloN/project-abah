<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$period = '2026-04-19';
$dbRows = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('periode', $period)
    ->select(['nomor_rekening1', 'cifno', 'kode_kanwil1', 'cabang1', 'unit1'])
    ->get();
$dbLookup = [];
foreach ($dbRows as $row) {
    $rek = trim((string) $row->nomor_rekening1);
    if ($rek !== '') {
        $dbLookup[$rek] = true;
    }
}

$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
if (!$handle) { throw new RuntimeException('open failed'); }
$lineNum = 0;
$headerSeen = false;
$missingSamples = [];
$missingTotal = 0;
$missingLenBuckets = ['<=1024' => 0, '>1024' => 0];
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
    if ($rek === '') continue;

    if (!isset($dbLookup[$rek])) {
        $missingTotal++;
        $len = strlen($line);
        if ($len > 1024) {
            $missingLenBuckets['>1024']++;
        } else {
            $missingLenBuckets['<=1024']++;
        }
        if (count($missingSamples) < 40) {
            $missingSamples[] = [
                'line' => $lineNum,
                'len' => $len,
                'rek' => $rek,
                'cif' => trim((string) ($row[9] ?? '')),
                'kode_kanwil' => trim((string) ($row[1] ?? '')),
                'cabang' => trim((string) ($row[4] ?? '')),
                'unit' => trim((string) ($row[6] ?? '')),
                'ln_type' => trim((string) ($row[12] ?? '')),
                'nama' => trim((string) ($row[13] ?? '')),
                'baki' => trim((string) ($row[17] ?? '')),
            ];
        }
    }
}
fclose($handle);

echo json_encode([
    'missing_total' => $missingTotal,
    'len_buckets' => $missingLenBuckets,
    'samples' => $missingSamples,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
