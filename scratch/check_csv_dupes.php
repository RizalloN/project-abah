<?php
$file = 'storage/app/temp/simpanan_multipn_direct_b872b0e8-9d93-429c-81c5-4a152f23fb91.csv';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 0, ';');

$rekenings = [];
$duplicates = 0;

while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
    if (count($data) < 10) continue;
    
    if ($data[3] === '00057 -- KC Ngawi(Konsolidasi-MB)' && $data[1] === '24/04/2026') {
        $norek = $data[6];
        if (isset($rekenings[$norek])) {
            $duplicates++;
        }
        $rekenings[$norek] = true;
    }
}
fclose($handle);

echo "CSV Duplicates check for KC Ngawi (24/04/2026):\n";
echo "Duplicate Rekening Count: $duplicates\n";
