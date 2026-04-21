<?php
$path = 'C:\Users\uzuma\Downloads\PROJECT ABAH BRISIM\21-04-2026\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$reader = new SplFileObject($path, 'rb');
$reader->setFlags(SplFileObject::DROP_NEW_LINE);
$lineNum = 0;
$maxLen = 0;
$over1024 = 0;
$over4096 = 0;
$over8192 = 0;
$sample = [];
foreach ($reader as $line) {
    if ($line === false) continue;
    $lineNum++;
    $len = strlen($line);
    if ($len > $maxLen) {
        $maxLen = $len;
    }
    if ($len > 1024) $over1024++;
    if ($len > 4096) $over4096++;
    if ($len > 8192) $over8192++;
    if ($len > 8192 && count($sample) < 10) {
        $sample[] = ['line' => $lineNum, 'len' => $len, 'head' => substr($line, 0, 120)];
    }
}
echo json_encode([
    'lines' => $lineNum,
    'max_len' => $maxLen,
    'over1024' => $over1024,
    'over4096' => $over4096,
    'over8192' => $over8192,
    'samples' => $sample,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
