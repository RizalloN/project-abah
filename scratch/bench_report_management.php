<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\NamaReport;
use App\Support\ManagedReportManagementService;
use Illuminate\Support\Facades\Cache;

$service = app(ManagedReportManagementService::class);

$candidates = ['daily_loan_dinamis', 'lw325_ph', 'simpanan_multipn', 'ssa_pinjaman', 'ssa_simpanan'];

foreach ($candidates as $table) {
    $report = NamaReport::where('active', 1)->where('table_name', $table)->first();
    if (!$report) {
        echo "[skip] no NamaReport row for table {$table}\n";
        continue;
    }

    echo "\n=== Report: id={$report->id_report}, table={$table} ===\n";

    ManagedReportManagementService::invalidateTableCache($table);
    try { Cache::flush(); } catch (\Throwable) {}

    $options = ['max_rows' => 5000, 'page' => 1, 'per_page' => 8];

    $t1 = microtime(true);
    $r1 = $service->resolveReportManagementData((int) $report->id_report, $options, false);
    $d1 = (microtime(true) - $t1) * 1000;

    $t2 = microtime(true);
    $r2 = $service->resolveReportManagementData((int) $report->id_report, $options, false);
    $d2 = (microtime(true) - $t2) * 1000;

    $ok1 = (bool) ($r1['ok'] ?? false);
    $ok2 = (bool) ($r2['ok'] ?? false);
    $groups1 = $r1['payload']['total_groups'] ?? null;
    $groups2 = $r2['payload']['total_groups'] ?? null;

    printf("  COLD ok=%s groups=%s duration=%8.2f ms\n", $ok1 ? 'Y' : 'N', $groups1 ?? '-', $d1);
    printf("  WARM ok=%s groups=%s duration=%8.2f ms (%.1fx faster)\n", $ok2 ? 'Y' : 'N', $groups2 ?? '-', $d2, $d1 / max($d2, 0.001));
}
