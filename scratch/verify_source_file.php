<?php
$file = 'storage/app/private/excel_imports/vbonOwVvpNwIcI6wJk5Z87gMB9D6swAV0gOVSegV.txt';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 0, ';');
$count = 0;
$totalSaldo = 0;

while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
    if (count($data) < 10) continue;
    if (strpos($data[3], 'Ngawi') !== false && $data[1] === '24/04/2026') {
        $count++;
        $saldo = str_replace(',', '.', $data[9]);
        $totalSaldo += (float)$saldo;
    }
}
fclose($handle);
echo "Source File Stats for KC Ngawi (24/04/2026):\n";
echo "Count: $count\n";
echo "Total Saldo: " . number_format($totalSaldo, 2, '.', '') . "\n";
