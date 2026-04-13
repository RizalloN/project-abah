<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Import\MySqlBulkLoadService;
use App\Support\ReportDataSyncService;

$bulkLoadService = app(MySqlBulkLoadService::class);
$syncService = app(ReportDataSyncService::class);

$before = [
    'source' => DB::table('simpanan_multipn')->count(),
    'dashboard_simpanan' => DB::table('dashboard_simpanan_snapshots')->count(),
    'dashboard_simpanan_branch' => DB::table('dashboard_simpanan_branch_snapshots')->count(),
    'dashboard_harian' => DB::table('dashboard_harian_snapshots')->count(),
    'dormant' => DB::table('rekening_dormant_snapshots')->count(),
    'rasio' => DB::table('rasio_casa_debitur_snapshots')->count(),
];

echo json_encode(['before' => $before], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

$result = $bulkLoadService->withTableWriteLock('simpanan_multipn', function () use ($syncService) {
    DB::statement('TRUNCATE TABLE `simpanan_multipn`');
    $syncService->syncAfterDelete('simpanan_multipn', null, 'manual:truncate-simpanan-multipn');

    return [
        'source' => DB::table('simpanan_multipn')->count(),
        'dashboard_simpanan' => DB::table('dashboard_simpanan_snapshots')->count(),
        'dashboard_simpanan_branch' => DB::table('dashboard_simpanan_branch_snapshots')->count(),
        'dashboard_harian' => DB::table('dashboard_harian_snapshots')->count(),
        'dormant' => DB::table('rekening_dormant_snapshots')->count(),
        'rasio' => DB::table('rasio_casa_debitur_snapshots')->count(),
        'cache_version' => cache()->get('report_cache_version:global'),
    ];
});

echo json_encode(['after' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
