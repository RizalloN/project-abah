<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

$sourcePath = $argv[1] ?? 'C:/Users/msi/Downloads/GI405Singlerow (7) 02 Mei 2026.xlsx';
$targetPeriod = $argv[2] ?? '2026-05-02';

$headers = [
    'periode',
    'branch',
    'currency',
    'posting_control',
    'account_number',
    'c_c',
    'p_c',
    'f_c',
    'description',
    'begining_balance',
    'equivalents_idr',
    'equivalents_usd',
    'today_debit',
    'today_credit',
    'ending_balance',
];

$numericColumns = [
    'begining_balance',
    'equivalents_idr',
    'equivalents_usd',
    'today_debit',
    'today_credit',
    'ending_balance',
];

function audit_normalize_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_numeric($value)) {
        try {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        } catch (Throwable) {
        }
    }

    $value = trim((string) $value);
    foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'n/j/Y', 'j/n/Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function audit_decimal(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        return round((float) $value, 2);
    }

    $value = trim(str_replace("\xC2\xA0", ' ', (string) ($value ?? '')));
    if ($value === '') {
        return 0.0;
    }

    $normalized = preg_replace('/[^0-9,.\-]/', '', $value) ?? '';
    if ($normalized === '') {
        return 0.0;
    }

    if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');
        $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
        $normalized = str_replace($thousandSeparator, '', $normalized);
        $normalized = str_replace($decimalSeparator, '.', $normalized);
    } elseif (str_contains($normalized, ',')) {
        $normalized = str_replace(',', '.', $normalized);
    }

    return is_numeric($normalized) ? round((float) $normalized, 2) : 0.0;
}

function audit_key(array $row): string
{
    return implode('|', [
        $row['periode'] ?? '',
        $row['branch'] ?? '',
        $row['posting_control'] ?? '',
        $row['account_number'] ?? '',
    ]);
}

function audit_fmt(float $value): string
{
    return number_format($value, 2, '.', ',');
}

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source file not found: {$sourcePath}\n");
    exit(1);
}

$reader = IOFactory::createReaderForFile($sourcePath);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($sourcePath);

try {
    $sheet = $spreadsheet->getSheetByName('GI405Singlerow') ?? $spreadsheet->getActiveSheet();
    $sourceRows = [];
    $sourceDuplicates = [];
    $sourceSums = array_fill_keys($numericColumns, 0.0);
    $sourcePhysicalRows = 0;

    foreach ($sheet->getRowIterator(2) as $row) {
        $cells = [];
        $cellIterator = $row->getCellIterator('A', 'O');
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getCalculatedValue();
        }

        if (implode('', array_map(static fn ($v) => trim((string) $v), $cells)) === '') {
            continue;
        }

        $period = audit_normalize_date($cells[0] ?? null);
        if ($period !== $targetPeriod) {
            continue;
        }

        $sourcePhysicalRows++;
        $mapped = [];
        foreach ($headers as $index => $name) {
            $mapped[$name] = in_array($name, $numericColumns, true)
                ? audit_decimal($cells[$index] ?? null)
                : trim((string) ($cells[$index] ?? ''));
        }
        $mapped['periode'] = $period;

        foreach ($numericColumns as $column) {
            $sourceSums[$column] += $mapped[$column];
        }

        $key = audit_key($mapped);
        if (isset($sourceRows[$key])) {
            $sourceDuplicates[] = $key;
        }
        $sourceRows[$key] = $mapped;
    }
} finally {
    $spreadsheet->disconnectWorksheets();
}

$dbRows = DB::table('gi405_singlerow')
    ->where('periode', $targetPeriod)
    ->get($headers)
    ->map(static function ($row) use ($numericColumns) {
        $mapped = (array) $row;
        foreach ($numericColumns as $column) {
            $mapped[$column] = round((float) ($mapped[$column] ?? 0), 2);
        }
        return $mapped;
    });

$dbPeriods = DB::table('gi405_singlerow')
    ->select('periode')
    ->distinct()
    ->orderBy('periode')
    ->pluck('periode')
    ->map(static fn ($period): string => (string) $period)
    ->values()
    ->all();

$dbByKey = [];
$dbDuplicates = [];
$dbSums = array_fill_keys($numericColumns, 0.0);
foreach ($dbRows as $row) {
    foreach ($numericColumns as $column) {
        $dbSums[$column] += $row[$column];
    }

    $key = audit_key($row);
    if (isset($dbByKey[$key])) {
        $dbDuplicates[] = $key;
    }
    $dbByKey[$key] = $row;
}

$missingInDb = array_values(array_diff(array_keys($sourceRows), array_keys($dbByKey)));
$extraInDb = array_values(array_diff(array_keys($dbByKey), array_keys($sourceRows)));
$valueMismatches = [];

foreach ($sourceRows as $key => $sourceRow) {
    if (!isset($dbByKey[$key])) {
        continue;
    }

    $dbRow = $dbByKey[$key];
    foreach ($numericColumns as $column) {
        $diff = round($dbRow[$column] - $sourceRow[$column], 2);
        if (abs($diff) > 0.01) {
            $valueMismatches[] = [
                'key' => $key,
                'column' => $column,
                'source' => audit_fmt($sourceRow[$column]),
                'database' => audit_fmt($dbRow[$column]),
                'diff' => audit_fmt($diff),
            ];
            break;
        }
    }
}

$aggregate = [];
$aggregate[] = [
    'metric' => 'rows',
    'source' => $sourcePhysicalRows,
    'database' => $dbRows->count(),
    'diff' => $dbRows->count() - $sourcePhysicalRows,
    'status' => $sourcePhysicalRows === $dbRows->count() ? 'OK' : 'MISMATCH',
];

foreach ($numericColumns as $column) {
    $source = round((float) $sourceSums[$column], 2);
    $db = round((float) $dbSums[$column], 2);
    $diff = round($db - $source, 2);
    $aggregate[] = [
        'metric' => $column,
        'source' => audit_fmt($source),
        'database' => audit_fmt($db),
        'diff' => audit_fmt($diff),
        'status' => abs($diff) <= 0.01 ? 'OK' : 'MISMATCH',
    ];
}

echo json_encode([
    'source_file' => $sourcePath,
    'period' => $targetPeriod,
    'sheet' => $sheet->getTitle(),
    'database_periods' => $dbPeriods,
    'source_unique_key_count' => count($sourceRows),
    'database_unique_key_count' => count($dbByKey),
    'aggregate' => $aggregate,
    'source_duplicate_count' => count($sourceDuplicates),
    'database_duplicate_count' => count($dbDuplicates),
    'missing_in_database_count' => count($missingInDb),
    'extra_in_database_count' => count($extraInDb),
    'numeric_value_mismatch_count' => count($valueMismatches),
    'samples' => [
        'source_duplicates' => array_slice($sourceDuplicates, 0, 5),
        'database_duplicates' => array_slice($dbDuplicates, 0, 5),
        'missing_in_database' => array_slice($missingInDb, 0, 5),
        'extra_in_database' => array_slice($extraInDb, 0, 5),
        'numeric_value_mismatches' => array_slice($valueMismatches, 0, 5),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
