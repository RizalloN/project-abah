<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$path = 'C:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\21-04-2026\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$handle = fopen($path, 'rb');
if ($handle === false) {
    fwrite(STDERR, "Gagal membuka file sumber.\n");
    exit(1);
}

$lineNum = 0;
$headerLine = null;
$header = null;
$counts = [
    'total_data_rows' => 0,
    'rows_with_expected_field_count' => 0,
    'valid_like_import' => 0,
    'invalid_periode' => 0,
    'missing_rekening' => 0,
    'missing_baki' => 0,
    'date_like_kode_kanwil' => 0,
];
$samples = [];

while (($line = fgets($handle)) !== false) {
    $lineNum++;
    $line = rtrim($line, "\r\n");
    if ($line === '') {
        continue;
    }

    $row = str_getcsv($line, ',', '"', '\\');
    if ($header === null) {
        if (($row[0] ?? '') === 'PERIODE') {
            $header = $row;
            $headerLine = $lineNum;
        }
        continue;
    }

    $counts['total_data_rows']++;
    if (count($row) === 103) {
        $counts['rows_with_expected_field_count']++;
    }

    $periode = trim((string) ($row[0] ?? ''));
    $kodeKanwil = trim((string) ($row[1] ?? ''));
    $rekening = trim((string) ($row[10] ?? ''));
    $baki = trim((string) ($row[17] ?? ''));

    $isValid = true;
    if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $periode)) {
        $counts['invalid_periode']++;
        $isValid = false;
    }
    if ($rekening === '') {
        $counts['missing_rekening']++;
        $isValid = false;
    }
    if ($baki === '') {
        $counts['missing_baki']++;
        $isValid = false;
    }
    $isDateLike = preg_match('/^\d{8}$/', $kodeKanwil) === 1
        || preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}$/', $kodeKanwil) === 1
        || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}$/', $kodeKanwil) === 1
        || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{2}$/', $kodeKanwil) === 1
        || preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $kodeKanwil) === 1
        || preg_match('/^\d{2}[-\/]\d{2}[-\/]\d{4}\s+\d{2}:\d{2}(:\d{2})?$/', $kodeKanwil) === 1;
    if ($isDateLike) {
        $counts['date_like_kode_kanwil']++;
        $isValid = false;
    }

    if ($isValid) {
        $counts['valid_like_import']++;
    } elseif (count($samples) < 20) {
        $samples[] = [
            'line' => $lineNum,
            'periode' => $periode,
            'kode_kanwil' => $kodeKanwil,
            'rekening' => $rekening,
            'baki' => $baki,
        ];
    }
}

fclose($handle);

$dbCount = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->count();

echo json_encode([
    'header_line' => $headerLine,
    'header_columns' => $header ? count($header) : 0,
    'counts' => $counts,
    'samples' => $samples,
    'db_count_for_2026_04_19' => $dbCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
