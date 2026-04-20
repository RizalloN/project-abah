<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Import\ImportExcelController;
use App\Services\Import\ExcelStagingService;
use Illuminate\Support\Facades\DB;

$file = 'C:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\SSA PINJAMAN.xlsx';

if (!file_exists($file)) {
    fwrite(STDERR, "File not found: {$file}\n");
    exit(1);
}

$controller = app(ImportExcelController::class);
$stagingService = app(ExcelStagingService::class);
$ref = new ReflectionClass($controller);

$invoke = static function (string $method, ...$args) use ($controller, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);

    return $m->invokeArgs($controller, $args);
};

$send = static function (string $event, array $payload): void {
    if ($event === 'progress' && isset($payload['message'])) {
        echo '[progress] ' . ($payload['percent'] ?? 0) . '% ' . $payload['message'] . PHP_EOL;
        return;
    }

    if ($event === 'complete') {
        echo '[complete] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }

    if ($event === 'error') {
        echo '[error] ' . ($payload['message'] ?? 'unknown error') . PHP_EOL;
    }
};

$overallStartedAt = hrtime(true);
$detectStartedAt = hrtime(true);

$preview = $stagingService->extractPreviewViaNativeXlsx($file, 20);
if ($preview === null || empty($preview['header_values'])) {
    $preview = $stagingService->detectExcelHeaderViaPython($file);
    if ($preview === null) {
        fwrite(STDERR, "Failed to detect headers from workbook.\n");
        exit(1);
    }
}

$detectMs = (int) round((hrtime(true) - $detectStartedAt) / 1_000_000);
$headerIndex = (int) ($preview['header_index'] ?? 0);
$headers = array_values((array) ($preview['header_values'] ?? []));

if ($headers === []) {
    fwrite(STDERR, "Workbook header row was empty.\n");
    exit(1);
}

$beforeCount = (int) DB::table('ssa_pinjaman')->count();

DB::table('ssa_pinjaman')->truncate();

$importStartedAt = hrtime(true);

$imported = $invoke(
    'tryPythonGPU',
    $send,
    $file,
    $headerIndex,
    'ssa_pinjaman',
    [],
    $headers,
    0,
    []
);

$backend = $imported ? 'python_gpu' : 'csv_staging_fallback';
$stagedPath = null;
$stagedRows = null;

if (!$imported) {
    $stageStartedAt = hrtime(true);
    $staged = $invoke('stageExcelToCsv', $send, $file, $headerIndex, $headers, 'ssa_pinjaman');
    if (!is_array($staged) || empty($staged['staged_csv_path'])) {
        fwrite(STDERR, "Fallback staging failed.\n");
        exit(1);
    }

    $stagedPath = (string) $staged['staged_csv_path'];
    $stagedRows = (int) ($staged['total_rows'] ?? 0);
    $stageMs = (int) round((hrtime(true) - $stageStartedAt) / 1_000_000);

    $loadStartedAt = hrtime(true);
    $loadPlan = $invoke('buildDirectGenericCsvLoadPlan', 'ssa_pinjaman', $stagedPath, $headers, []);
    $inserted = (int) $invoke('executeDirectGenericCsvLoad', 'ssa_pinjaman', $stagedPath, $loadPlan);
    $loadMs = (int) round((hrtime(true) - $loadStartedAt) / 1_000_000);
} else {
    $stageMs = null;
    $loadMs = null;
    $inserted = (int) DB::table('ssa_pinjaman')->count();
}

$afterCount = (int) DB::table('ssa_pinjaman')->count();
$aggregate = DB::table('ssa_pinjaman')
    ->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(COALESCE(baki_debet, 0)), 0) AS total_baki_debet')
    ->first();

$totalMs = (int) round((hrtime(true) - $overallStartedAt) / 1_000_000);

$result = [
    'file' => $file,
    'detect_ms' => $detectMs,
    'header_index' => $headerIndex,
    'header_count' => count($headers),
    'backend' => $backend,
    'before_count' => $beforeCount,
    'inserted_rows' => $inserted,
    'after_count' => $afterCount,
    'staged_rows' => $stagedRows,
    'stage_ms' => $stageMs,
    'load_ms' => $loadMs,
    'total_ms' => $totalMs,
    'total_baki_debet' => (string) ($aggregate->total_baki_debet ?? '0'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($stagedPath && file_exists($stagedPath)) {
    @unlink($stagedPath);
}
