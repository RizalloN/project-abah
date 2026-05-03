<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Import\DlyKapResegmentasiCsvImporter;
use App\Services\Import\L1133CsvImporter;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

function dec($value): float
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return 0.0;
    }

    $normalized = preg_replace('/[^0-9.\-]/', '', str_replace("\xC2\xA0", ' ', $value));

    return $normalized === '' || !is_numeric($normalized) ? 0.0 : (float) $normalized;
}

function normalize_period($value): ?string
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
    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'n/j/Y', 'j/n/Y'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

function fmt($value): string
{
    return number_format((float) $value, 2, '.', ',');
}

function cmp_line(string $name, float $source, float $db, float $tolerance = 0.05): array
{
    $diff = $db - $source;

    return [
        'metric' => $name,
        'source' => fmt($source),
        'database' => fmt($db),
        'diff' => fmt($diff),
        'ok' => abs($diff) <= $tolerance ? 'OK' : 'MISMATCH',
    ];
}

function print_section(string $title, array $payload): void
{
    echo "\n=== {$title} ===\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

$gi405Path = 'C:/Users/msi/Downloads/GI405Singlerow (7) 02 Mei 2026.xlsx';
$l1133Path = 'C:/Users/msi/Downloads/BISDWH + L1133 01052026/LAPORAN_HARIAN_PINJAMAN_KANWIL_V2 01052026.csv';
$dlyKapPaths = glob('C:/Users/msi/Downloads/BISDWH + L1133 01052026/*MDN*.csv') ?: [];

// GI405 2026-05-02
$gi405Metrics = [
    'begining_balance' => 9,
    'equivalents_idr' => 10,
    'equivalents_usd' => 11,
    'today_debit' => 12,
    'today_credit' => 13,
    'ending_balance' => 14,
];
$gi405Source = ['rows' => 0, 'sample' => null, 'sums' => array_fill_keys(array_keys($gi405Metrics), 0.0)];
$reader = IOFactory::createReaderForFile($gi405Path);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($gi405Path);
$sheet = $spreadsheet->getSheetByName('GI405Singlerow') ?? $spreadsheet->getActiveSheet();
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

    if (normalize_period($cells[0] ?? null) !== '2026-05-02') {
        continue;
    }

    $gi405Source['rows']++;
    foreach ($gi405Metrics as $metric => $index) {
        $gi405Source['sums'][$metric] += dec($cells[$index] ?? null);
    }
    $gi405Source['sample'] ??= [
        'periode' => normalize_period($cells[0] ?? null),
        'branch' => trim((string) ($cells[1] ?? '')),
        'account_number' => trim((string) ($cells[4] ?? '')),
        'begining_balance_raw' => $cells[9] ?? null,
        'begining_balance_parsed' => fmt(dec($cells[9] ?? null)),
    ];
}
$spreadsheet->disconnectWorksheets();

$gi405Db = DB::table('gi405_singlerow')
    ->where('periode', '2026-05-02')
    ->selectRaw('COUNT(*) as row_count, SUM(begining_balance) as begining_balance, SUM(equivalents_idr) as equivalents_idr, SUM(equivalents_usd) as equivalents_usd, SUM(today_debit) as today_debit, SUM(today_credit) as today_credit, SUM(ending_balance) as ending_balance')
    ->first();
$gi405Compare = [cmp_line('rows', (float) $gi405Source['rows'], (float) ($gi405Db->row_count ?? 0), 0.0)];
foreach (array_keys($gi405Metrics) as $metric) {
    $gi405Compare[] = cmp_line($metric, $gi405Source['sums'][$metric], (float) ($gi405Db->{$metric} ?? 0));
}
print_section('GI405 2026-05-02', [
    'source_file' => $gi405Path,
    'sample_decimal_parse' => $gi405Source['sample'],
    'comparison' => $gi405Compare,
]);

// L1133 2026-05-01
$l1133Importer = app(L1133CsvImporter::class);
$l1133Parsed = $l1133Importer->parse($l1133Path);
$l1133Metrics = ['jumlah_debitur', 'jumlah_rekening', 'outstanding', 'jumlah_debitur_npl', 'npl', 'jumlah_debitur_dpk', 'dpk'];
$l1133Source = ['rows' => 0, 'sums' => array_fill_keys($l1133Metrics, 0.0), 'sample' => null];
foreach ($l1133Parsed['rows'] as $row) {
    if (($row['periode'] ?? null) !== '2026-05-01') {
        continue;
    }
    $l1133Source['rows']++;
    foreach ($l1133Metrics as $metric) {
        $l1133Source['sums'][$metric] += (float) ($row[$metric] ?? 0);
    }
    $l1133Source['sample'] ??= [
        'kode_kanca' => $row['kode_kanca'] ?? null,
        'nama_kanca' => $row['nama_kanca'] ?? null,
        'jenis' => $row['jenis'] ?? null,
        'outstanding_parsed' => $row['outstanding'] ?? null,
        'npl_parsed' => $row['npl'] ?? null,
        'dpk_parsed' => $row['dpk'] ?? null,
    ];
}
$l1133Select = 'COUNT(*) as row_count';
foreach ($l1133Metrics as $metric) {
    $l1133Select .= ", SUM({$metric}) as {$metric}";
}
$l1133Db = DB::table('l1133')->where('periode', '2026-05-01')->selectRaw($l1133Select)->first();
$l1133Compare = [cmp_line('rows', (float) $l1133Source['rows'], (float) ($l1133Db->row_count ?? 0), 0.0)];
foreach ($l1133Metrics as $metric) {
    $l1133Compare[] = cmp_line($metric, $l1133Source['sums'][$metric], (float) ($l1133Db->{$metric} ?? 0));
}
print_section('L1133 2026-05-01', [
    'source_file' => $l1133Path,
    'metadata' => $l1133Parsed['metadata'],
    'sample_decimal_parse' => $l1133Source['sample'],
    'comparison' => $l1133Compare,
]);

// DLY KAP/BISDWH MDN 2026-05-01
$dlyImporter = app(DlyKapResegmentasiCsvImporter::class);
$dlyMetrics = ['l_rp', 'l_deb', 'dpk_rp', 'dpk_deb', 'kl_rp', 'kl_deb', 'd_rp', 'd_deb', 'm_rp', 'm_deb', 'npl_rp', 'npl_deb', 'tl_rp', 'tl_deb'];
$dlyReports = [];
foreach ($dlyKapPaths as $path) {
    $parsed = $dlyImporter->parse($path);
    $meta = $parsed['metadata'];
    $source = ['rows' => 0, 'sums' => array_fill_keys($dlyMetrics, 0.0), 'sample' => null];
    foreach ($parsed['rows'] as $row) {
        if (($row['periode'] ?? null) !== '2026-05-01') {
            continue;
        }
        $source['rows']++;
        foreach ($dlyMetrics as $metric) {
            $source['sums'][$metric] += (float) ($row[$metric] ?? 0);
        }
        $source['sample'] ??= [
            'kode_cabang' => $row['kode_cabang'] ?? null,
            'kode_unit' => $row['kode_unit'] ?? null,
            'source_section' => $row['source_section'] ?? null,
            'segmen' => $row['segmen'] ?? null,
            'l_rp_parsed' => $row['l_rp'] ?? null,
            'tl_rp_parsed' => $row['tl_rp'] ?? null,
        ];
    }

    $select = 'COUNT(*) as row_count';
    foreach ($dlyMetrics as $metric) {
        $select .= ", SUM({$metric}) as {$metric}";
    }
    $db = DB::table('dly_kap_resegmentasi')
        ->where('periode', '2026-05-01')
        ->where('kode_cabang', $meta['kode_cabang'])
        ->where('kode_unit', $meta['kode_unit'])
        ->selectRaw($select)
        ->first();

    $comparison = [cmp_line('rows', (float) $source['rows'], (float) ($db->row_count ?? 0), 0.0)];
    foreach ($dlyMetrics as $metric) {
        $comparison[] = cmp_line($metric, $source['sums'][$metric], (float) ($db->{$metric} ?? 0));
    }

    $dlyReports[] = [
        'source_file' => $path,
        'metadata' => $meta,
        'sample_decimal_parse' => $source['sample'],
        'comparison' => $comparison,
    ];
}
print_section('DLY KAP/BISDWH MDN 2026-05-01', $dlyReports);
