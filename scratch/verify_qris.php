<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/report/data', 'POST', [
    'tab' => 'qris',
    'posisi' => '2025-12-31',
    'branch_office' => [],
    'nama_uker' => [],
]);
$res = app(App\Services\Reports\QrisReportService::class)->handle($req);
$data = $res->getData(true);
echo "status=" . $res->getStatusCode() . PHP_EOL;
echo "rows=" . count($data['data'] ?? []) . PHP_EOL;
print_r($data['data'][0] ?? null);
