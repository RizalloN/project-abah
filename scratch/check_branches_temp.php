<?php
$file = 'storage/app/temp/simpanan_multipn_direct_b872b0e8-9d93-429c-81c5-4a152f23fb91.csv';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 0, ';');
$branches = [];
while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
    if (isset($data[3])) {
        $branches[$data[3]] = true;
    }
}
fclose($handle);
echo "Branches in simpanan_multipn_direct_...csv:\n";
print_r(array_keys($branches));
