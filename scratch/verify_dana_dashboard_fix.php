<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Support\DashboardDanaService;

$service = app(DashboardDanaService::class);
$data = $service->getDashboardData('2026-04-29', 'ritel');

echo "Dashboard Dana (Ritel Category) for KC MADIUN:\n";
foreach ($data['rows'] as $row) {
    if (str_contains($row['nama_cabang'], 'MADIUN') && !$row['is_total']) {
        echo "Kategori: " . $row['kategori'] . " | RKA: " . ($row['selected'] - $row['rka_rp']) . "\n";
    }
}
