<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourcePath = $argv[1] ?? 'E:\\!!PROJECT ABAH REPO\\05-05-2026\\LW321 (4) 05062026.csv';
$dateHeaders = [
    'NEXT_PMT_DATE' => 'next_pmt_date',
    'NEXT_INT_PMT_DATE' => 'next_int_pmt_date',
    'TGL_MENUNGGAK' => 'tgl_menunggak',
    'TGL_REALISASI' => 'tgl_realisasi',
    'TGL JATUH TEMPO' => 'tgl_jatuh_tempo',
];

$handle = fopen($sourcePath, 'rb');
if ($handle === false) {
    fwrite(STDERR, "Cannot open source: {$sourcePath}\n");
    exit(1);
}

$normalizeHeader = static function ($header): string {
    $header = str_replace("\xEF\xBB\xBF", '', (string) $header);
    $header = preg_replace('/^\p{C}+/u', '', $header) ?? $header;

    return trim($header);
};

$headers = array_map($normalizeHeader, fgetcsv($handle, 0, ';') ?: []);
$index = array_flip($headers);
$sourceZeroCounts = array_fill_keys(array_keys($dateHeaders), 0);
$sourceBlankCounts = array_fill_keys(array_keys($dateHeaders), 0);
$sourceOtherInvalidExamples = [];
$rows = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (!array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) {
        continue;
    }

    $rows++;
    foreach ($dateHeaders as $header => $column) {
        $value = trim((string) ($row[$index[$header] ?? -1] ?? ''));
        if ($value === '') {
            $sourceBlankCounts[$header]++;
            continue;
        }

        if ($value === '0') {
            $sourceZeroCounts[$header]++;
            continue;
        }

        $parsed = false;
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTime && $date->format($format) === $value) {
                $parsed = true;
                break;
            }
        }

        if (!$parsed && !isset($sourceOtherInvalidExamples[$header])) {
            $sourceOtherInvalidExamples[$header] = $value;
        }
    }
}
fclose($handle);

$dbSentinelCounts = [];
$dbNullCounts = [];
foreach ($dateHeaders as $header => $column) {
    $dbSentinelCounts[$column] = DB::table('lw321pn')->where($column, '1899-12-30')->count();
    $dbNullCounts[$column] = DB::table('lw321pn')->whereNull($column)->count();
}

echo json_encode([
    'rows_scanned' => $rows,
    'source_zero_counts' => $sourceZeroCounts,
    'source_blank_counts' => $sourceBlankCounts,
    'source_other_invalid_examples' => $sourceOtherInvalidExamples,
    'db_1899_12_30_counts' => $dbSentinelCounts,
    'db_null_counts' => $dbNullCounts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
