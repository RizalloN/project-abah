<?php
$file = 'storage/app/private/excel_imports/vbonOwVvpNwIcI6wJk5Z87gMB9D6swAV0gOVSegV.txt';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 0, ';');
$branches = [];
while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
    if (isset($data[3])) {
        $branches[$data[3]] = true;
    }
}
fclose($handle);
echo "Branches in vbonOw file:\n";
print_r(array_keys($branches));
