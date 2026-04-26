<?php
$files = [
    'storage/app/temp/simpanan_multipn_direct_b872b0e8-9d93-429c-81c5-4a152f23fb91.csv',
    'storage/app/private/excel_imports/vbonOwVvpNwIcI6wJk5Z87gMB9D6swAV0gOVSegV.txt',
    'storage/app/private/excel_imports/1gzXZFLtv4VCGM3e4lMmswqqV12sK55izK87FH21.txt'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }
    $handle = fopen($file, 'r');
    $header = fgetcsv($handle, 0, ';');
    $count = 0;
    while (fgetcsv($handle, 0, ';') !== FALSE) {
        $count++;
    }
    fclose($handle);
    echo "Total rows in $file: $count\n";
}
