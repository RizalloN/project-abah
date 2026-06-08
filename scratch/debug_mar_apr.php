<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$builder = app()->make(\App\Support\ReportSnapshotBuilder::class);
$ref = new ReflectionMethod($builder, 'buildPerformanceRmPeriodSnapshot');
$ref->setAccessible(true);

DB::listen(function ($query) {
    if (strpos($query->sql, 'DELETE FROM') !== false || strpos($query->sql, 'INSERT INTO') !== false) {
        echo "SQL: " . $query->sql . "\n";
        echo "Bindings: " . json_encode($query->bindings) . "\n";
    }
});

echo "Deleting and rebuilding 2026-04-30...\n";
$ref->invoke($builder, '2026-04-30', true);
echo "Done.\n";
