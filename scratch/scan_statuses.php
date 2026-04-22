<?php
$path = 'C:\xampp\htdocs\project-ABAH\storage\app/private\excel_imports\TQL1lNHqyjfQO4lhlti8S5aqOAm6NypdZlEHOlYH.txt';
$handle = fopen($path, 'r');
$headers = fgetcsv($handle, 0, ';');
$statusIndex = array_search('Status', $headers);
if ($statusIndex === false) die("Status column not found\n");

$statuses = [];
while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (isset($row[$statusIndex])) {
        $val = trim($row[$statusIndex]);
        if (!isset($statuses[$val])) {
            $statuses[$val] = 0;
        }
        $statuses[$val]++;
    }
}
fclose($handle);
echo json_encode($statuses) . "\n";
