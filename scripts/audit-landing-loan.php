<?php

// Read-only source profiling; direct builders intentionally bypass payload caches.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\LandingLoanAnalyticsService;
use Illuminate\Support\Facades\DB;

$service = app(LandingLoanAnalyticsService::class);
$mode = $argv[1] ?? 'profile';
$segment = $argv[2] ?? 'sme';
$queries = [];
DB::listen(function ($event) use (&$queries): void {
    $queries[] = ['sql' => $event->sql, 'bindings' => $event->bindings, 'ms' => $event->time];
});
DB::statement('SET SESSION max_statement_time=60');
if ($mode === 'schema') {
    foreach (['daily_loan_dinamis', 'ssa_pinjaman', 'dashboard_pinjaman_snapshots'] as $table) {
        try {
            $indexes = [];
            foreach (DB::select('SHOW INDEX FROM '.$table) as $index) {
                $indexes[$index->Key_name][] = $index->Column_name;
            }
            echo json_encode(['table' => $table, 'indexes' => $indexes], JSON_UNESCAPED_SLASHES).PHP_EOL;
        } catch (Throwable $e) {
            echo json_encode(['table' => $table, 'error' => $e->getMessage()]).PHP_EOL;
        }
    }
    exit;
}
if ($mode === 'explain') {
    $baseline = json_decode(file_get_contents(storage_path('app/landing-audit-'.$segment.'-baseline.json')), true);
    foreach ($baseline['queries'] as $query) {
        if (str_starts_with($query['sql'], 'select')) {
            echo json_encode(['sql' => substr($query['sql'], 0, 100), 'ms' => $query['ms'], 'plan' => DB::select('EXPLAIN '.$query['sql'], $query['bindings'])]).PHP_EOL;
        }
    }
    exit;
}
if ($mode === 'split') {
    $baseline = json_decode(file_get_contents(storage_path('app/landing-audit-micro-baseline.json')), true);
    $query = collect($baseline['queries'])->sortByDesc('ms')->first();
    $periodCount = count(array_filter($query['bindings'], fn ($value) => preg_match('/^2026-\d{2}-\d{2}$/', (string) $value)));
    $sql = preg_replace('/`periode` in \([^)]*\)/', '`periode` = ?', $query['sql'], 1);
    $start = microtime(true);
    $rows = [];
    foreach (array_slice($query['bindings'], 0, $periodCount) as $date) {
        $t = microtime(true);
        $rows = array_merge($rows, DB::select($sql, array_merge([$date], array_slice($query['bindings'], $periodCount))));
        echo json_encode(['period' => $date, 'ms' => round((microtime(true) - $t) * 1000)]).PHP_EOL;
    }
    echo json_encode(['total_ms' => round((microtime(true) - $start) * 1000), 'rows' => $rows]).PHP_EOL;
    exit;
}
$start = microtime(true);
$period = (new ReflectionMethod($service, 'resolvePeriod'))->invoke($service, $argv[4] ?? null, null, $segment);
echo json_encode(['segment' => $segment, 'period' => $period, 'resolve_ms' => round((microtime(true) - $start) * 1000)]).PHP_EOL;
$start = microtime(true);
$payload = (new ReflectionMethod($service, 'buildAnalyticsPayload'))->invoke($service, $period, (int) substr($period, 0, 4), null, $segment);
$result = ['segment' => $segment, 'period' => $period, 'build_ms' => round((microtime(true) - $start) * 1000), 'payload' => $payload, 'queries' => $queries];
$destination = storage_path('app/landing-audit-'.$segment.'-'.($argv[3] ?? 'baseline').'.json');
file_put_contents($destination, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
usort($queries, fn ($a, $b) => $b['ms'] <=> $a['ms']);
echo json_encode(['build_ms' => $result['build_ms'], 'query_count' => count($queries), 'slow_queries' => array_slice($queries, 0, 6), 'artifact' => $destination], JSON_UNESCAPED_SLASHES).PHP_EOL;
