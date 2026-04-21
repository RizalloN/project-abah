<?php
$src = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$stage = 'C:\xampp\htdocs\project-ABAH\storage\app\private\imports\backend\daily-loan\20260421\direct_load_3908d777-a291-41bc-8560-c8319b60e97d.csv';
foreach ([$src, $stage] as $path) {
    $reader = new SplFileObject($path, 'rb');
    $reader->setFlags(SplFileObject::DROP_NEW_LINE);
    $lines = 0;
    foreach ($reader as $line) {
        if ($line === false) continue;
        $lines++;
    }
    echo basename($path) . ':' . $lines . PHP_EOL;
}
