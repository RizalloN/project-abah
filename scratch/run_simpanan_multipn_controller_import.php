<?php

declare(strict_types=1);

use App\Http\Controllers\Import\ImportSimpananMultiPnCsvController;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function invokeMethod(object $target, string $method, array $arguments = [])
{
    $reflection = new ReflectionClass($target);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($target, $arguments);
}

$sourcePath = $argv[1] ?? '';
if ($sourcePath === '' || !is_file($sourcePath)) {
    fail('Usage: php scratch/run_simpanan_multipn_controller_import.php <absolute-csv-path>');
}

$controller = app(ImportSimpananMultiPnCsvController::class);
$previewPayload = invokeMethod($controller, 'buildPreviewPayloadFromCsvFile', [$sourcePath]);
$normalizedHeaders = array_values(array_filter(
    (array) ($previewPayload['normalized_headers'] ?? []),
    static fn ($header): bool => trim((string) $header) !== ''
));

if ($normalizedHeaders === []) {
    fail('Header import Simpanan MultiPN tidak ditemukan dari preview payload controller.');
}

$selectedColumns = array_keys($normalizedHeaders);
$loadPlan = invokeMethod($controller, 'buildDirectCsvLoadPlan', [
    $sourcePath,
    $normalizedHeaders,
    $selectedColumns,
]);

$periodHints = array_values(array_unique(array_filter(array_map(
    static fn ($value): string => trim((string) $value),
    (array) ($loadPlan['period_hints'] ?? [])
), static fn (string $value): bool => $value !== '')));

$beforeCounts = [];
if ($periodHints !== []) {
    $beforeCounts = DB::table('simpanan_multipn')
        ->selectRaw('posisi, COUNT(*) AS total_rows')
        ->whereIn('posisi', $periodHints)
        ->groupBy('posisi')
        ->orderBy('posisi')
        ->get()
        ->map(static fn ($row): array => [
            'posisi' => (string) $row->posisi,
            'total_rows' => (int) $row->total_rows,
        ])
        ->all();
}

$beforeLoad = invokeMethod($controller, 'buildSimpananMultiPnDirectLoadBeforeLoadCallback', [$loadPlan]);

$events = [];
$send = static function (string $event, array $data) use (&$events): void {
    $events[] = [
        'event' => $event,
        'data' => $data,
    ];
};

$startedAt = microtime(true);
$inserted = invokeMethod($controller, 'executeDirectCsvLoad', [
    $loadPlan,
    $beforeLoad,
    $send,
    0,
]);
$elapsed = max(microtime(true) - $startedAt, 0.001);

$uniqueIdColumn = trim((string) ($loadPlan['unique_id_column'] ?? ''));
$batchPrefix = 'SMPN_' . trim((string) ($loadPlan['import_batch_token'] ?? '')) . '_';

$verificationQuery = DB::table('simpanan_multipn');
if ($uniqueIdColumn !== '' && $batchPrefix !== 'SMPN__') {
    $verificationQuery->where($uniqueIdColumn, 'like', $batchPrefix . '%');
} else {
    $verificationQuery->where('created_at', (string) ($loadPlan['import_batch_timestamp'] ?? ''));
}

$batchSummary = $verificationQuery
    ->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(COALESCE(saldo_idr, 0)), 0) AS total_balance')
    ->first();

$afterCounts = [];
if ($periodHints !== []) {
    $afterCounts = DB::table('simpanan_multipn')
        ->selectRaw('posisi, COUNT(*) AS total_rows')
        ->whereIn('posisi', $periodHints)
        ->groupBy('posisi')
        ->orderBy('posisi')
        ->get()
        ->map(static fn ($row): array => [
            'posisi' => (string) $row->posisi,
            'total_rows' => (int) $row->total_rows,
        ])
        ->all();
}

echo json_encode([
    'source_path' => $sourcePath,
    'header_count' => count($normalizedHeaders),
    'source_rows' => (int) ($loadPlan['validation_written_rows'] ?? 0),
    'skipped_rows' => (int) ($loadPlan['validation_skipped_count'] ?? 0),
    'duplicate_rows' => (int) ($loadPlan['validation_duplicate_count'] ?? 0),
    'period_hints' => $periodHints,
    'before_period_counts' => $beforeCounts,
    'after_period_counts' => $afterCounts,
    'import_batch_timestamp' => (string) ($loadPlan['import_batch_timestamp'] ?? ''),
    'unique_id_column' => $uniqueIdColumn,
    'inserted_rows' => $inserted,
    'batch_row_count' => (int) ($batchSummary->row_count ?? 0),
    'batch_total_balance' => (string) ($batchSummary->total_balance ?? '0.00'),
    'elapsed_seconds' => round($elapsed, 3),
    'rows_per_second' => (int) round($inserted / $elapsed),
    'progress_events' => array_slice($events, -10),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
