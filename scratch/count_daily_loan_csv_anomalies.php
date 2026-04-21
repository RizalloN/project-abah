<?php
$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
if (!$handle) { fwrite(STDERR, "open failed\n"); exit(1); }
$lineNum = 0;
$header = null;
$expected = null;
$bad = [];
$periodRows = 0;
while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;
    $row = str_getcsv($line, ',', '"', '\\');
    if ($header === null) {
        $header = $row;
        $expected = count($row);
        continue;
    }
    $periodRows++;
    $count = count($row);
    if ($count !== $expected) {
        $bad[] = ['line' => $lineNum, 'count' => $count, 'sample' => substr($line, 0, 240)];
        if (count($bad) >= 20) break;
    }
}
fclose($handle);
echo json_encode(['expected'=>$expected, 'data_rows'=>$periodRows, 'bad_samples'=>$bad], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
