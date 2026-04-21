<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = $app->make(App\Http\Controllers\Import\ImportDailyLoanBackendController::class);
$method = new ReflectionMethod($controller, 'copySourceIntoImportStorage');
$method->setAccessible(true);
$relative = $method->invoke($controller, 'c:\\Users\\uzuma\\Downloads\\PROJECT ABAH BRISIM\\21-04-2026\\UPDATE 4_REPORT_DAILY_LOAN_DINAMIS_v7-19042026.csv');
$absolute = Illuminate\Support\Facades\Storage::path($relative);

echo $relative . PHP_EOL;
echo $absolute . PHP_EOL;
echo filesize($absolute) . PHP_EOL;

$reader = new SplFileObject($absolute, 'r');
$reader->seek(PHP_INT_MAX);
echo ($reader->key() + 1) . PHP_EOL;
