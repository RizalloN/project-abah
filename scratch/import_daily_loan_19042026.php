<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(App\Http\Controllers\Import\ImportDailyLoanBackendController::class);
$request = Illuminate\Http\Request::create('/import-local-csv', 'POST', [
    'source_path' => 'C:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\21-04-2026\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv',
    'periode' => '2026-04-19',
    'mode' => 'sync',
    'replace_existing_periods' => true,
]);

$started = microtime(true);
$response = $controller->importLocalCsv($request);
$elapsed = round(microtime(true) - $started, 3);

$payload = $response->getData(true);
$dbCount = Illuminate\Support\Facades\DB::table('daily_loan_dinamis')
    ->where('periode', '2026-04-19')
    ->count();

echo json_encode([
    'elapsed_seconds' => $elapsed,
    'response_status' => $response->getStatusCode(),
    'response' => $payload,
    'db_count_for_2026_04_19' => $dbCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
