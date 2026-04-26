<?php
$file = 'storage/app/temp/simpanan_multipn_direct_b872b0e8-9d93-429c-81c5-4a152f23fb91.csv';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 0, ';');

$count = 0;
$totalSaldo = 0;

while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
    // CSV columns: No;Posisi;Regional Office;Kantor Cabang;Unit Kerja;CIFNO;No Rekening;Status;Jenis Simpanan;Saldo IDR
    // 0: No, 1: Posisi, 2: Regional Office, 3: Kantor Cabang, 4: Unit Kerja, 5: CIFNO, 6: No Rekening, 7: Status, 8: Jenis Simpanan, 9: Saldo IDR
    
    if ($data[3] === '00057 -- KC Ngawi(Konsolidasi-MB)' && $data[1] === '24/04/2026') {
        $count++;
        $totalSaldo += (float)$data[9];
    }
}
fclose($handle);

echo "File Stats for KC Ngawi (24/04/2026):\n";
echo "Count: $count\n";
echo "Total Saldo: " . number_format($totalSaldo, 2, '.', '') . "\n";
