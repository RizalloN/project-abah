<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\NamaReport;
use App\Support\ManagedReportManagementService;
use Illuminate\Support\Facades\Cache;

$service = app(ManagedReportManagementService::class);

$tables = ['daily_loan_dinamis', 'lw325_ph', 'simpanan_multipn', 'ssa_pinjaman', 'ssa_simpanan'];

echo "=== Parity check: result shape unchanged ===\n";
foreach ($tables as $table) {
    $report = NamaReport::where('active', 1)->where('table_name', $table)->first();
    if (!$report) continue;

    ManagedReportManagementService::invalidateTableCache($table);
    try { Cache::flush(); } catch (\Throwable) {}

    $r = $service->resolveReportManagementData((int) $report->id_report, [
        'max_rows' => 5000, 'page' => 1, 'per_page' => 8,
    ], false);

    $payload = $r['payload'] ?? [];
    $groups = $payload['total_groups'] ?? null;
    $rows = is_array($payload['rows'] ?? null) ? count($payload['rows']) : 0;
    $periods = is_array($payload['periods'] ?? null) ? count($payload['periods']) : 0;
    $sampleRow = is_array($payload['rows'] ?? null) && isset($payload['rows'][0]) ? $payload['rows'][0] : null;

    printf("%-25s ok=%s groups=%s rows=%d periods=%d sample_keys=[%s]\n",
        $table,
        ($r['ok'] ?? false) ? 'Y' : 'N',
        $groups ?? '-',
        $rows,
        $periods,
        $sampleRow ? implode(',', array_keys($sampleRow)) : '-'
    );
}

echo "\n=== Invalidation test (version-stamp bump) ===\n";
$table = 'daily_loan_dinamis';
$report = NamaReport::where('active', 1)->where('table_name', $table)->first();
if ($report) {
    ManagedReportManagementService::invalidateTableCache($table);
    try { Cache::flush(); } catch (\Throwable) {}

    $t1 = microtime(true);
    $r1 = $service->resolveReportManagementData((int) $report->id_report, ['max_rows' => 5000, 'page' => 1, 'per_page' => 8], false);
    $d1 = (microtime(true) - $t1) * 1000;

    $t2 = microtime(true);
    $r2 = $service->resolveReportManagementData((int) $report->id_report, ['max_rows' => 5000, 'page' => 1, 'per_page' => 8], false);
    $d2 = (microtime(true) - $t2) * 1000;

    // Bump version
    ManagedReportManagementService::invalidateTableCache($table);

    $t3 = microtime(true);
    $r3 = $service->resolveReportManagementData((int) $report->id_report, ['max_rows' => 5000, 'page' => 1, 'per_page' => 8], false);
    $d3 = (microtime(true) - $t3) * 1000;

    $t4 = microtime(true);
    $r4 = $service->resolveReportManagementData((int) $report->id_report, ['max_rows' => 5000, 'page' => 1, 'per_page' => 8], false);
    $d4 = (microtime(true) - $t4) * 1000;

    printf("  cold-1   %8.2f ms  groups=%s\n", $d1, $r1['payload']['total_groups'] ?? '-');
    printf("  warm-2   %8.2f ms  groups=%s\n", $d2, $r2['payload']['total_groups'] ?? '-');
    printf("  cold-3   %8.2f ms  groups=%s  (after invalidate; should be slow again)\n", $d3, $r3['payload']['total_groups'] ?? '-');
    printf("  warm-4   %8.2f ms  groups=%s  (after invalidate then re-cached)\n", $d4, $r4['payload']['total_groups'] ?? '-');
    if ($d3 > $d2 * 5) {
        echo "  ✓ invalidate forced full recompute (d3 >> d2)\n";
    } else {
        echo "  ⚠ invalidate may not have bumped; d3 ($d3) not >> d2 ($d2)\n";
    }
}
