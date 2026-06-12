<?php

$spreadsheetId = '1KgXJ4fi9u4-mJyaZADXF0cM9wJnVlh0f7sQBZeR8fLY';
$sheetName = 'KPI Kaunit';
$url = sprintf(
    'https://docs.google.com/spreadsheets/d/%s/gviz/tq?%s',
    $spreadsheetId,
    http_build_query(['tqx' => 'out:csv', 'sheet' => $sheetName])
);

echo "Fetching: " . $url . "\n\n";

$csv = file_get_contents($url);
$lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];

for ($i = 0; $i < min(10, count($lines)); $i++) {
    $row = str_getcsv($lines[$i]);
    echo "Row $i: " . json_encode($row) . "\n";
}
