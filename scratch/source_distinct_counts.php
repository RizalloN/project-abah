<?php
$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
if (!$handle) { throw new RuntimeException('open failed'); }
$lineNum = 0;
$header = null;
$distinctRekening = [];
$distinctCif = [];
$total = 0;
while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $line = rtrim($line, "\r\n");
    if ($line === '') continue;
    $row = str_getcsv($line, ',', '"', '\\');
    if ($header === null) {
        if (($row[0] ?? '') === 'PERIODE') {
            $header = $row;
        }
        continue;
    }
    $total++;
    $rek = trim((string)($row[10] ?? ''));
    $cif = trim((string)($row[9] ?? ''));
    if ($rek !== '') $distinctRekening[$rek] = true;
    if ($cif !== '') $distinctCif[$cif] = true;
}
fclose($handle);
echo json_encode([
    'total' => $total,
    'distinct_rekening' => count($distinctRekening),
    'distinct_cif' => count($distinctCif),
    'duplicates_rekening' => $total - count($distinctRekening),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
