<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$builder = app()->make(\App\Support\ReportSnapshotBuilder::class);
$ref = new ReflectionMethod($builder, 'buildPerformanceRmPeriodSnapshot');
$ref->setAccessible(true);

$periods = ['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31'];

foreach ($periods as $period) {
    echo "Rebuilding $period...\n";
    $rowCount = $ref->invoke($builder, $period, true);
    echo "Result rows: $rowCount\n";
}
