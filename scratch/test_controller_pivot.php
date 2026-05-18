<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DashboardPinjamanReportController;
use Illuminate\Http\Request;

$curr = '2026-05-14';

$controller = app(DashboardPinjamanReportController::class);

foreach (['KC Madiun'] as $branch) {
    $request = Request::create('/report/dashboard-pinjaman/data', 'GET', [
        'periode' => $curr,
        'cabang1' => [$branch],
        'refresh' => 1,
    ]);

    $response = $controller->data($request);
    $payload = json_decode($response->getContent(), true);

    echo "=== $branch | $curr (refresh) ===" . PHP_EOL;
    echo "selected_period   = " . ($payload['selected_period'] ?? '?') . PHP_EOL;
    echo "comparison_period = " . ($payload['comparison_period'] ?? '?') . PHP_EOL;
    echo "data_source       = " . ($payload['data_source'] ?? '?') . PHP_EOL;
    echo "grand_total_value = " . number_format((float) ($payload['grand_total_value'] ?? 0), 0) . PHP_EOL . PHP_EOL;

    // Show metric columns per row
    $outputMetrics = ['principal_reduction', 'suplesi', 'ph', 'lunas'];
    $labelMap = [
        'principal_reduction' => 'Turunan Pokok',
        'suplesi' => 'Suplesi',
        'ph' => 'PH',
        'lunas' => 'Lunas',
    ];

    echo "Output metrics per before-bucket:" . PHP_EOL;
    printf("%-14s", '');
    foreach ($outputMetrics as $m) printf(" %18s", $labelMap[$m]);
    echo PHP_EOL;
    foreach (($payload['matrix_rows'] ?? []) as $r) {
        printf("%-14s", $r['label']);
        foreach ($outputMetrics as $m) {
            printf(" %18s", number_format((float) ($r['metrics'][$m] ?? 0), 0));
        }
        echo PHP_EOL;
    }

    echo PHP_EOL . "Grand totals:" . PHP_EOL;
    printf("%-14s", 'Total metric');
    foreach ($outputMetrics as $m) {
        printf(" %18s", number_format((float) ($payload['grand_totals']['metrics'][$m] ?? 0), 0));
    }
    echo PHP_EOL;
}
