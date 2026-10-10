<?php

// Read-only profiler for Kinerja RM Ritel / Mikro. Uses an in-memory cache so
// every run measures cold-path query cost instead of cached payloads.
// Usage: php scripts/audit-kinerja-rm.php [label] [scenarioFilter]
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Report\KinerjaRmMikroReportController;
use App\Http\Controllers\Report\KinerjaRmReportController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

config(['cache.default' => 'array']);
Bus::fake();

$label = $argv[1] ?? 'baseline';
$filter = $argv[2] ?? null;

$user = User::query()->get()->first(fn ($u) => method_exists($u, 'isAdmin') && $u->isAdmin()) ?? User::query()->first();
if ($user) {
    Auth::login($user);
}

$scenarios = [
    'ritel_consumer' => [KinerjaRmReportController::class, ['segmen' => 'CONSUMER']],
    'ritel_small' => [KinerjaRmReportController::class, ['segmen' => 'SMALL']],
    'mikro_per_uker' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'per_uker']],
    'mikro_per_rm' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'per_rm']],
    'mikro_series_bulanan' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'series_bulanan']],
    'mikro_series_harian' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'series_harian']],
    'mikro_rekap' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'rekap']],
    'mikro_tiering' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'rm_mikro_kur', 'kategori_report' => 'per_tiering']],
    'mantri_produktivitas' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'produktivitas_mantri']],
    'mantri_kuadran' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'kuadran']],
    'mantri_unit_pemutus' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'unit_pemutus']],
    'mantri_pdwk' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'pdwk_override']],
    'mantri_extreme_low' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'extreme_low_mantri']],
    'mantri_rekap' => [KinerjaRmMikroReportController::class, ['kategori_rm' => 'mantri', 'kategori_report' => 'rekap_mantri']],
];

$queries = [];
DB::listen(function ($event) use (&$queries): void {
    $queries[] = ['sql' => $event->sql, 'bindings' => $event->bindings, 'ms' => round($event->time, 1)];
});

$summary = [];
$artifact = [];
foreach ($scenarios as $name => [$class, $params]) {
    if ($filter && ! str_contains($name, $filter)) {
        continue;
    }
    app('cache')->store('array')->flush();
    $queries = [];
    $request = Request::create('/audit', 'GET', $params);
    app()->instance('request', $request);
    $start = microtime(true);
    $error = null;
    $html = '';
    try {
        $view = app($class)->index($request);
        $html = $view->render();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $ms = round((microtime(true) - $start) * 1000);
    $dbMs = round(array_sum(array_column($queries, 'ms')));
    $sorted = $queries;
    usort($sorted, fn ($a, $b) => $b['ms'] <=> $a['ms']);
    $summary[$name] = ['total_ms' => $ms, 'db_ms' => $dbMs, 'queries' => count($queries), 'html_md5' => md5(preg_replace('/\s+/', ' ', $html)), 'error' => $error];
    $artifact[$name] = $summary[$name] + ['top' => array_slice($sorted, 0, 15)];
    echo json_encode([$name => $summary[$name]], JSON_UNESCAPED_SLASHES).PHP_EOL;
}

$dest = storage_path('app/kinerja-rm-audit-'.$label.($filter ? '-'.$filter : '').'.json');
file_put_contents($dest, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo 'artifact: '.$dest.PHP_EOL;
