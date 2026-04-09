<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;

$controller = app(App\Http\Controllers\DataReportController::class);
$request = Request::create('/report', 'GET', ['posisi_terakhir' => '2026-03-31']);
$response = $controller->programReferralPartnerPerusahaanAnak($request);
$data = $response->getData();
echo json_encode([
    'positions' => $data['positions'],
    'matchedCount' => $data['matchedCount'],
    'selectedDate' => $data['selectedDate'],
    'tableRows' => $data['tableRows'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
