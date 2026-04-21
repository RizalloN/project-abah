<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $request = Illuminate\Http\Request::create('/import/backend/daily-loan/local-file', 'POST', [
        'source_path' => 'c:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\21-04-2026\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv',
        'periode' => '19042026',
        'mode' => 'sync',
        'replace_existing_periods' => '1',
    ]);
    $request->headers->set('Accept', 'application/json');

    $response = $app->make(App\Http\Controllers\Import\ImportDailyLoanBackendController::class)
        ->importLocalCsv($request);

    echo get_class($response) . PHP_EOL;
    echo $response->getStatusCode() . PHP_EOL;
    echo $response->getContent() . PHP_EOL;
} catch (Throwable $e) {
    echo get_class($e) . PHP_EOL;
    echo $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
}
