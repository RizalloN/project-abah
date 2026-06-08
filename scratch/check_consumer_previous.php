<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

$builder = app()->make(\App\Support\ReportSnapshotBuilder::class);
$ref = new ReflectionMethod($builder, 'resolvePreviousMonthPerformanceRmPeriod');
$ref->setAccessible(true);

foreach (['2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'] as $period) {
    $prev = $ref->invoke($builder, $period);
    echo "Period: $period | Resolved Previous: " . ($prev ?? 'NULL') . "\n";
}
