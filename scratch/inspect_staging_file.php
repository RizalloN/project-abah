<?php
$path = 'C:\xampp\htdocs\project-ABAH\storage\app\private\imports\backend\daily-loan\20260421\direct_load_3908d777-a291-41bc-8560-c8319b60e97d.csv';
if (!file_exists($path)) {
    echo "missing\n";
    exit;
}
$reader = new SplFileObject($path, 'rb');
$reader->setFlags(SplFileObject::DROP_NEW_LINE);
$lines = 0;
$header = null;
$firstData = null;
foreach ($reader as $line) {
    if ($line === false) continue;
    $lines++;
    if ($lines === 1) $header = $line;
    if ($lines === 2) $firstData = $line;
}
echo json_encode(['lines'=>$lines,'header'=>substr((string)$header,0,120),'first_data'=>substr((string)$firstData,0,160)], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
