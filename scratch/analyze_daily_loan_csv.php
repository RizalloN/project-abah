<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
if (!$handle) {
    fwrite(STDERR, "open failed\n");
    exit(1);
}

$headerLine = null;
$header = null;
$dataRows = 0;
$validRows = 0;
$badRows = [];
$periods = [];
$sampleBadReasons = [];
$lineNum = 0;
$expected = 103;

while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;
    $row = str_getcsv($line, ',', '"', '\\');
    if ($header === null) {
        if (($row[0] ?? '') === 'PERIODE') {
            $header = $row;
            $headerLine = $lineNum;
        }
        continue;
    }

    $dataRows++;
    $count = count($row);
    if ($count !== $expected) {
        if (count($badRows) < 50) {
            $badRows[] = ['line' => $lineNum, 'count' => $count, 'sample' => substr($line, 0, 220)];
        }
        continue;
    }

    $validRows++;
    $period = $row[0] ?? '';
    if ($period !== '') {
        $periods[$period] = ($periods[$period] ?? 0) + 1;
    }
}

fclose($handle);

$dbRows = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->where('periode', '2026-04-19')->count();

$result = [
    'header_line' => $headerLine,
    'header_columns' => $header ? count($header) : 0,
    'data_rows_scanned_after_header' => $dataRows,
    'rows_with_expected_field_count' => $validRows,
    'rows_with_unexpected_field_count_sample' => $badRows,
    'periods_found_in_file' => $periods,
    'db_rows_for_2026_04_19' => $dbRows,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
