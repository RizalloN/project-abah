<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Import\ImportExcelController;
use Illuminate\Support\Facades\DB;

$controller = app(ImportExcelController::class);
$ref = new ReflectionClass($controller);
$invoke = function (string $method, ...$args) use ($controller, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($controller, $args);
};

$noop = static function (string $event, array $payload): void {
};

$cases = [
    [
        'key' => 'ssa_simpanan',
        'base_table' => 'ssa_simpanan',
        'test_table' => 'ssa_simpanan_import_test',
        'sample_path' => storage_path('app/ssa_simpanan_top_bottom_20.xlsx'),
        'headers' => [
            'Month, Day, Year of Posisi',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Segmentasi',
            'Segmen Kategorisasi Bisnis',
            'Saldo',
            'Tgl',
            'Bulan',
            'Tahun',
            'Bulan Tahun',
        ],
        'prepare_method' => 'prepareSsaSimpananDirectLoadSource',
        'sum_column' => 'saldo',
    ],
    [
        'key' => 'ssa_pinjaman',
        'base_table' => 'ssa_pinjaman',
        'test_table' => 'ssa_pinjaman_import_test',
        'sample_path' => storage_path('app/ssa_pinjaman_top_bottom_20.xlsx'),
        'headers' => [
            'Month, Day, Year of Periode',
            'Nama Cabang',
            'Nama Uker',
            'Produk',
            'Produk_Dashboard',
            'Segmen',
            'Segmen Lama',
            'SEGMEN_2025',
            'Segmen_Dashboard',
            'Kolektabilitas One Obligor',
            'Flag Restruk',
            'Baki Debet',
            'Jumlah Debitur Aktif',
            'Jumlah Rekening Aktif',
            'Keterangan Uker',
            'Kualitas',
            'Tgl',
            'Bulan',
            'Tahun',
            'Bulan Tahun',
        ],
        'prepare_method' => 'prepareSsaPinjamanDirectLoadSource',
        'sum_column' => 'baki_debet',
    ],
];

$results = [];

foreach ($cases as $case) {
    $samplePath = $case['sample_path'];
    if (!file_exists($samplePath)) {
        throw new RuntimeException("Sample file not found: {$samplePath}");
    }

    DB::statement('DROP TABLE IF EXISTS `' . $case['test_table'] . '`');
    DB::statement('CREATE TABLE `' . $case['test_table'] . '` LIKE `' . $case['base_table'] . '`');

    $stageResult = $invoke(
        'stageExcelToCsv',
        $noop,
        $samplePath,
        0,
        $case['headers'],
        $case['base_table']
    );

    $stagedCsvPath = (string) ($stageResult['staged_csv_path'] ?? '');
    if ($stagedCsvPath === '' || !file_exists($stagedCsvPath)) {
        throw new RuntimeException("Failed to stage sample for {$case['key']}");
    }

    $loadSource = $invoke($case['prepare_method'], $stagedCsvPath, ',', null);
    $preparedPath = (string) ($loadSource['path'] ?? $stagedCsvPath);

    $loadPlan = $invoke('buildDirectGenericCsvLoadPlan', $case['test_table'], $preparedPath, $case['headers']);
    $inserted = (int) $invoke('executeDirectGenericCsvLoad', $case['test_table'], $preparedPath, $loadPlan);

    $aggregate = DB::table($case['test_table'])
        ->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(COALESCE(`' . $case['sum_column'] . '`, 0)), 0) AS total_sum')
        ->first();

    $results[] = [
        'report' => $case['key'],
        'test_table' => $case['test_table'],
        'inserted' => $inserted,
        'row_count' => (int) ($aggregate->row_count ?? 0),
        'sum_column' => $case['sum_column'],
        'total_sum' => (string) ($aggregate->total_sum ?? '0'),
    ];

    if (!empty($loadSource['cleanup']) && $preparedPath !== '' && file_exists($preparedPath)) {
        @unlink($preparedPath);
    }
    if (file_exists($stagedCsvPath)) {
        @unlink($stagedCsvPath);
    }
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
