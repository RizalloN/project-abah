<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Support\StrictDateParser;

$sourcePath = 'C:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\21-04-2026\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv';
$targetPeriod = '2026-04-19';
$artifactUniqueId = 'imp69e748adde3fa830331821_000000314195_DLD';
$batchSize = 500;
$createdAt = now()->toDateTimeString();

$columnMeta = [];
foreach (DB::select('SHOW COLUMNS FROM daily_loan_dinamis') as $column) {
    $columnMeta[strtolower((string) $column->Field)] = strtolower((string) $column->Type);
}

$dbLookup = [];
foreach (DB::table('daily_loan_dinamis')
    ->where('periode', $targetPeriod)
    ->pluck('nomor_rekening1')
    ->all() as $rekening) {
    $rekening = trim((string) $rekening);
    if ($rekening !== '') {
        $dbLookup[$rekening] = true;
    }
}

$sourceHandle = fopen($sourcePath, 'rb');
if ($sourceHandle === false) {
    fwrite(STDERR, "Gagal membuka file sumber.\n");
    exit(1);
}

$sourceHeaders = null;
$headerLineSeen = false;
$inserted = 0;
$skipped = 0;
$pendingRows = [];
$pendingRecords = [];

$sourceHeadersNormalized = [];

$normalizeHeader = static function (string $header): string {
    $header = strtolower(trim($header));
    $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
    return trim($header, '_');
};

$normalizeDecimal = static function ($value): ?string {
    if ($value === null) {
        return null;
    }

    if (is_int($value)) {
        return (string) $value . '.00';
    }

    if (is_float($value)) {
        return number_format((float) $value, 2, '.', '');
    }

    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }

    $value = preg_replace('/\s+/', '', $value) ?? $value;
    $value = preg_replace('/[^0-9,\.\-\(\)]/', '', $value) ?? $value;
    if ($value === '' || $value === '-') {
        return null;
    }

    $negative = false;
    if (preg_match('/^\((.*)\)$/', $value, $matches) === 1) {
        $negative = true;
        $value = (string) $matches[1];
    } elseif (str_starts_with($value, '-')) {
        $negative = true;
        $value = ltrim($value, '-');
    }

    $hasComma = str_contains($value, ',');
    $hasDot = str_contains($value, '.');
    $decimalSeparator = null;

    if ($hasComma && $hasDot) {
        $decimalSeparator = strrpos($value, ',') > strrpos($value, '.') ? ',' : '.';
    } elseif ($hasComma) {
        $parts = explode(',', $value);
        $lastPart = (string) end($parts);
        if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
            $decimalSeparator = ',';
        }
    } elseif ($hasDot) {
        $parts = explode('.', $value);
        $lastPart = (string) end($parts);
        if (count($parts) === 2 && strlen($lastPart) > 0 && strlen($lastPart) <= 2) {
            $decimalSeparator = '.';
        }
    }

    if ($decimalSeparator !== null) {
        [$intPart, $decimalPart] = explode($decimalSeparator, $value, 2);
        $intPart = preg_replace('/[,.]/', '', $intPart) ?? $intPart;
        $decimalPart = preg_replace('/[,.]/', '', $decimalPart) ?? $decimalPart;
    } else {
        $intPart = preg_replace('/[,.]/', '', $value) ?? $value;
        $decimalPart = '';
    }

    $intPart = preg_replace('/\D/', '', (string) $intPart) ?? '';
    $decimalPart = preg_replace('/\D/', '', (string) $decimalPart) ?? '';

    if ($intPart === '' && $decimalPart === '') {
        return null;
    }

    if ($intPart === '') {
        $intPart = '0';
    }
    if ($decimalPart === '') {
        $decimalPart = '00';
    } elseif (strlen($decimalPart) === 1) {
        $decimalPart .= '0';
    } elseif (strlen($decimalPart) > 2) {
        $decimalPart = substr($decimalPart, 0, 2);
    }

    $intPart = ltrim($intPart, '0');
    if ($intPart === '') {
        $intPart = '0';
    }

    return ($negative ? '-' : '') . $intPart . '.' . $decimalPart;
};

$normalizeInteger = static function ($value): ?int {
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = preg_replace('/[^0-9\-]/', '', $value) ?? $value;
    if ($value === '' || $value === '-') {
        return null;
    }

    return (int) $value;
};

$normalizeValue = static function (string $dbColumn, $value) use ($columnMeta, $normalizeDecimal, $normalizeInteger): mixed {
    $type = $columnMeta[$dbColumn] ?? '';
    $value = is_string($value) ? trim($value) : $value;

    if ($value === '' || $value === null) {
        return null;
    }

    if ($dbColumn === 'periode' || str_contains($type, 'date')) {
        return StrictDateParser::normalize((string) $value);
    }

    if (str_contains($type, 'decimal') || str_contains($type, 'numeric')) {
        return $normalizeDecimal($value);
    }

    if (preg_match('/\bint\b|\btinyint\b|\bsmallint\b|\bbigint\b/', $type) === 1) {
        return $normalizeInteger($value);
    }

    return (string) $value;
};

$flush = static function () use (&$pendingRows, &$pendingRecords): void {
    if ($pendingRows === []) {
        return;
    }

    DB::table('daily_loan_dinamis')->insert($pendingRows);
    $pendingRows = [];
    $pendingRecords = [];
};

DB::beginTransaction();

try {
    DB::statement('SET @skip_snapshot_invalidation = 1');

    $deletedArtifact = DB::table('daily_loan_dinamis')
        ->where('uniqueid_namareport', $artifactUniqueId)
        ->delete();

    $lineNum = 0;
    while (($line = fgets($sourceHandle)) !== false) {
        $lineNum++;
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            continue;
        }

        $parsed = str_getcsv($line, ',', '"', '\\');

        if (!$headerLineSeen) {
            if (($parsed[0] ?? '') !== 'PERIODE') {
                continue;
            }

            $headerLineSeen = true;
            $sourceHeaders = array_values($parsed);
            $sourceHeadersNormalized = array_map($normalizeHeader, $sourceHeaders);
            continue;
        }

        $rekening = trim((string) ($parsed[10] ?? ''));
        if ($rekening === '' || isset($dbLookup[$rekening])) {
            continue;
        }

        $row = [
            'uniqueid_namareport' => 'fix69e748adde3fa830331821_' . str_pad((string) $lineNum, 6, '0', STR_PAD_LEFT) . '_DLD',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        foreach ($sourceHeadersNormalized as $index => $normalizedHeader) {
            $sourceValue = $parsed[$index] ?? null;
            $dbColumn = $normalizedHeader;

            if ($dbColumn === 'textbox20') {
                $dbColumn = 'total_kewajiban';
            } elseif ($dbColumn === 'textbox21') {
                $dbColumn = 'os_idr';
            }

            if ($dbColumn === 'uniqueid_namareport' || $dbColumn === 'created_at' || $dbColumn === 'updated_at') {
                continue;
            }

            if (!array_key_exists($dbColumn, $columnMeta)) {
                continue;
            }

            $row[$dbColumn] = $normalizeValue($dbColumn, $sourceValue);
        }

        if (($row['periode'] ?? null) !== $targetPeriod) {
            continue;
        }

        $pendingRows[] = $row;
        $pendingRecords[] = $rekening;
        $dbLookup[$rekening] = true;
        $inserted++;

        if (count($pendingRows) >= $batchSize) {
            $flush();
        }
    }

    $flush();

    DB::table('dashboard_pinjaman_snapshots')->where('periode', $targetPeriod)->delete();
    DB::table('dashboard_harian_snapshots')->where('snapshot_period', $targetPeriod)->delete();
    DB::table('rasio_casa_debitur_snapshots')->where('loan_period', $targetPeriod)->delete();

    DB::statement('SET @skip_snapshot_invalidation = NULL');
    DB::commit();
} catch (\Throwable $e) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    try {
        DB::statement('SET @skip_snapshot_invalidation = NULL');
    } catch (\Throwable) {
    }
    fclose($sourceHandle);
    throw $e;
}

fclose($sourceHandle);

$finalCount = DB::table('daily_loan_dinamis')->where('periode', $targetPeriod)->count();
$nullCount = DB::table('daily_loan_dinamis')
    ->where('uniqueid_namareport', $artifactUniqueId)
    ->count();

echo json_encode([
    'deleted_artifact_rows' => $deletedArtifact ?? 0,
    'inserted_missing_rows' => $inserted,
    'final_period_count' => $finalCount,
    'artifact_remaining' => $nullCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
