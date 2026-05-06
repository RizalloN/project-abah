<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourcePath = $argv[1] ?? 'E:\\!!PROJECT ABAH REPO\\05-05-2026\\LW321 (4) 05062026.csv';
$table = 'lw321pn';
$sampleLimit = (int) ($argv[2] ?? 25);

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Source file not found: {$sourcePath}\n");
    exit(1);
}

$columnMap = [
    'PERIODE' => 'periode',
    'KODE_KANWIL' => 'kode_kanwil',
    'KANWIL' => 'kanwil',
    'KODE_KANCA' => 'kode_kanca',
    'KANCA' => 'kanca',
    'KODE_UKER' => 'kode_uker',
    'UKER' => 'uker',
    'CURRENCY' => 'currency',
    'LN_TYPE' => 'ln_type',
    'NOMOR_REKENING' => 'no_rekening',
    'NAMA_DEBITUR' => 'nama_debitur',
    'PLAFON' => 'plafon',
    'NEXT_PMT_DATE' => 'next_pmt_date',
    'NEXT_INT_PMT_DATE' => 'next_int_pmt_date',
    'RATE' => 'rate',
    'TGL_MENUNGGAK' => 'tgl_menunggak',
    'TGL_REALISASI' => 'tgl_realisasi',
    'TGL JATUH TEMPO' => 'tgl_jatuh_tempo',
    'JANGKA WAKTU' => 'jangka_waktu',
    'FLAG RESTRUK' => 'flag_restruk',
    'CIFNO' => 'cifno',
    'KOLEKTIBILITAS LANCAR' => 'kolektibilitas_lancar',
    'KOLEKTIBILITAS DPK' => 'kolektibilitas_dpk',
    'KOLEKTIBILITAS KURANG LANCAR' => 'kolektibilitas_kurang_lancar',
    'KOLEKTIBILITAS DIRAGUKAN' => 'kolektibilitas_diragukan',
    'KOLEKTIBILITAS MACET' => 'kolektibilitas_macet',
    'TUNGGAKAN POKOK' => 'tunggakan_pokok',
    'TUNGGAKAN BUNGA' => 'tunggakan_bunga',
    'TUNGGAKAN PINALTI' => 'tunggakan_pinalti',
    'FREQ PAYMENT' => 'freq_payment',
    'FREQ INT PAYMENT' => 'freq_int_payment',
    'CODE' => 'code',
    'DESCRIPTION' => 'description',
    'SEGMEN LV1' => 'segmen_lv1',
    'DESC SEGMEN LV1' => 'desc_segmen_lv1',
    'KOL_ADK' => 'kol_adk',
    'PN PENGELOLA SINGLEPN' => 'pn_pengelola_singlepn',
    'PN PENGELOLA 1' => 'pn_pengelola_1',
    'PN PEMRAKARSA' => 'pn_pemrakarsa',
    'PN REFERRAL' => 'pn_referral',
    'PN RESTRUK' => 'pn_restruk',
    'PN PENGELOLA 2' => 'pn_pengelola_2',
    'PN PEMUTUS' => 'pn_pemutus',
    'PN CRM' => 'pn_crm',
    'PN RM REFERRAL NAIK SEGMENTASI' => 'pn_rm_referral_naik_segmentasi',
    'PN RM CRR' => 'pn_rm_crr',
    'PLAFON DALAM IDR' => 'plafon_dalam_idr',
    'BALANCE DALAM IDR' => 'balance_dalam_idr',
];

$dateColumns = array_fill_keys(['periode', 'next_pmt_date', 'next_int_pmt_date', 'tgl_menunggak', 'tgl_realisasi', 'tgl_jatuh_tempo'], true);
$decimalColumns = array_fill_keys([
    'plafon',
    'rate',
    'kolektibilitas_lancar',
    'kolektibilitas_dpk',
    'kolektibilitas_kurang_lancar',
    'kolektibilitas_diragukan',
    'kolektibilitas_macet',
    'tunggakan_pokok',
    'tunggakan_bunga',
    'tunggakan_pinalti',
    'plafon_dalam_idr',
    'balance_dalam_idr',
], true);
$integerColumns = array_fill_keys(['freq_payment', 'freq_int_payment'], true);

$normalizeHeader = static function ($header): string {
    $header = (string) $header;
    $header = str_replace("\xEF\xBB\xBF", '', $header);
    $header = preg_replace('/^\p{C}+/u', '', $header) ?? $header;

    return trim($header);
};

$normalizeString = static function ($value): ?string {
    $value = trim((string) $value);
    return $value === '' ? null : preg_replace('/\s+/', ' ', $value);
};

$normalizeDate = static function ($value): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    return $value;
};

$normalizeDecimal = static function ($value, int $scale = 2): ?string {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        $value = $lastComma > $lastDot
            ? str_replace('.', '', str_replace(',', '.', $value))
            : str_replace(',', '', $value);
    } elseif (str_contains($value, ',')) {
        $value = str_replace(',', '.', $value);
    }

    if (!is_numeric($value)) {
        return $value;
    }

    return number_format((float) $value, $scale, '.', '');
};

$decimalToCents = static function (?string $value): int {
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return 0;
    }

    return (int) round(((float) $value) * 100);
};

$centsToDecimal = static function (int $value): string {
    return number_format($value / 100, 2, '.', '');
};

$normalizeValue = static function (string $column, $value) use ($dateColumns, $decimalColumns, $integerColumns, $normalizeString, $normalizeDate, $normalizeDecimal): ?string {
    if (isset($dateColumns[$column])) {
        return $normalizeDate($value);
    }

    if (isset($decimalColumns[$column])) {
        return $normalizeDecimal($value, $column === 'rate' ? 6 : 2);
    }

    if (isset($integerColumns[$column])) {
        $value = trim((string) $value);
        return $value === '' ? null : (string) (int) $value;
    }

    return $normalizeString($value);
};

$openCsv = static function (string $path) {
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Cannot open CSV: {$path}");
    }

    return $handle;
};

$handle = $openCsv($sourcePath);
$headers = array_map($normalizeHeader, fgetcsv($handle, 0, ';') ?: []);
$headerIndexes = array_flip($headers);
$missingHeaders = array_values(array_diff(array_keys($columnMap), $headers));

$sourceRows = 0;
$sourceAggregate = [
    'periods' => [],
    'kancas' => [],
    'sum_balance_dalam_idr_cents' => 0,
    'sum_plafon_dalam_idr_cents' => 0,
    'sum_plafon_cents' => 0,
];
$samples = [];
$firstRows = [];
$sourceKeys = [];

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (!array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) {
        continue;
    }

    $sourceRows++;
    $mapped = [];

    foreach ($columnMap as $sourceHeader => $dbColumn) {
        $mapped[$dbColumn] = $normalizeValue($dbColumn, $row[$headerIndexes[$sourceHeader] ?? -1] ?? null);
    }

    $key = ($mapped['periode'] ?? '') . '|' . ($mapped['no_rekening'] ?? '');
    if ($key !== '|') {
        $sourceKeys[$key] = ($sourceKeys[$key] ?? 0) + 1;
    }

    if (!empty($mapped['periode'])) {
        $sourceAggregate['periods'][$mapped['periode']] = ($sourceAggregate['periods'][$mapped['periode']] ?? 0) + 1;
    }
    if (!empty($mapped['kode_kanca'])) {
        $sourceAggregate['kancas'][$mapped['kode_kanca']] = ($sourceAggregate['kancas'][$mapped['kode_kanca']] ?? 0) + 1;
    }

    foreach (['balance_dalam_idr', 'plafon_dalam_idr', 'plafon'] as $sumColumn) {
        $sourceAggregate['sum_' . $sumColumn . '_cents'] += $decimalToCents($mapped[$sumColumn] ?? null);
    }

    if (count($firstRows) < 5) {
        $firstRows[] = ['line' => $sourceRows, 'key' => $key, 'data' => $mapped];
    }

    $hash = abs(crc32($key));
    if (($hash % 9973) < 15 || in_array($sourceRows, [10, 100, 1000, 10000, 50000, 100000, 200000, 300000], true)) {
        $samples[$key] = ['line' => $sourceRows, 'key' => $key, 'data' => $mapped];
        if (count($samples) >= $sampleLimit) {
            // Keep scanning aggregates, stop adding samples.
            $sampleLimit = 0;
        }
    }
}
fclose($handle);

$samples = array_values(array_merge($firstRows, $samples));
$samples = array_slice($samples, 0, max(25, (int) ($argv[2] ?? 25)));

$dbColumns = Schema::getColumnListing($table);
$dbRowCount = DB::table($table)->count();
$dbAggregates = DB::table($table)
    ->selectRaw('COUNT(*) as rows_count')
    ->selectRaw('COUNT(DISTINCT periode) as distinct_periods')
    ->selectRaw('COUNT(DISTINCT kode_kanca) as distinct_kancas')
    ->selectRaw('SUM(balance_dalam_idr) as sum_balance_dalam_idr')
    ->selectRaw('SUM(plafon_dalam_idr) as sum_plafon_dalam_idr')
    ->selectRaw('SUM(plafon) as sum_plafon')
    ->first();

$dbPeriods = DB::table($table)->select('periode', DB::raw('COUNT(*) as count'))
    ->groupBy('periode')->orderBy('periode')->get()
    ->mapWithKeys(static fn ($row) => [(string) $row->periode => (int) $row->count])
    ->all();
$dbKancas = DB::table($table)->select('kode_kanca', DB::raw('COUNT(*) as count'))
    ->groupBy('kode_kanca')->orderBy('kode_kanca')->get()
    ->mapWithKeys(static fn ($row) => [(string) $row->kode_kanca => (int) $row->count])
    ->all();

$sampleResults = [];
$columnsToCompare = array_values($columnMap);
foreach ($samples as $sample) {
    $source = $sample['data'];
    $dbRow = DB::table($table)
        ->where('periode', $source['periode'])
        ->where('no_rekening', $source['no_rekening'])
        ->first();

    $mismatches = [];
    if (!$dbRow) {
        $mismatches[] = ['column' => '__row__', 'source' => $sample['key'], 'database' => null];
    } else {
        foreach ($columnsToCompare as $column) {
            $sourceValue = $source[$column] ?? null;
            $dbValue = $normalizeValue($column, $dbRow->{$column} ?? null);
            if ($sourceValue !== $dbValue) {
                $mismatches[] = [
                    'column' => $column,
                    'source' => $sourceValue,
                    'database' => $dbValue,
                ];
            }
        }
    }

    $sampleResults[] = [
        'line' => $sample['line'],
        'key' => $sample['key'],
        'mismatch_count' => count($mismatches),
        'mismatches' => array_slice($mismatches, 0, 10),
    ];
}

$duplicateSourceKeys = array_filter($sourceKeys, static fn (int $count): bool => $count > 1);
$duplicateDbKeys = DB::table($table)
    ->select('periode', 'no_rekening', DB::raw('COUNT(*) as count'))
    ->groupBy('periode', 'no_rekening')
    ->havingRaw('COUNT(*) > 1')
    ->limit(20)
    ->get()
    ->map(static fn ($row) => [
        'key' => ((string) $row->periode) . '|' . ((string) $row->no_rekening),
        'count' => (int) $row->count,
    ])
    ->all();

$result = [
    'source_path' => $sourcePath,
    'table' => $table,
    'header_count' => count($headers),
    'mapped_column_count' => count($columnMap),
    'missing_source_headers' => $missingHeaders,
    'missing_db_columns' => array_values(array_diff(array_values($columnMap), $dbColumns)),
    'source_rows' => $sourceRows,
    'db_rows' => $dbRowCount,
    'source_periods' => $sourceAggregate['periods'],
    'db_periods' => $dbPeriods,
    'source_kancas' => $sourceAggregate['kancas'],
    'db_kancas' => $dbKancas,
    'source_sums' => [
        'balance_dalam_idr' => $centsToDecimal($sourceAggregate['sum_balance_dalam_idr_cents']),
        'plafon_dalam_idr' => $centsToDecimal($sourceAggregate['sum_plafon_dalam_idr_cents']),
        'plafon' => $centsToDecimal($sourceAggregate['sum_plafon_cents']),
    ],
    'db_sums' => [
        'balance_dalam_idr' => $normalizeDecimal($dbAggregates->sum_balance_dalam_idr ?? 0, 2),
        'plafon_dalam_idr' => $normalizeDecimal($dbAggregates->sum_plafon_dalam_idr ?? 0, 2),
        'plafon' => $normalizeDecimal($dbAggregates->sum_plafon ?? 0, 2),
    ],
    'duplicate_source_key_count' => count($duplicateSourceKeys),
    'duplicate_db_key_examples' => $duplicateDbKeys,
    'sample_count' => count($sampleResults),
    'sample_mismatch_rows' => array_values(array_filter($sampleResults, static fn ($row): bool => $row['mismatch_count'] > 0)),
    'sample_rows' => $sampleResults,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
