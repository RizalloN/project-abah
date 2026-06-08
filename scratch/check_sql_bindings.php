<?php

define('LARAVEL_START', microtime(true));
require 'd:/XAMPP/htdocs/project-ABAH/vendor/autoload.php';
$app = require_once 'd:/XAMPP/htdocs/project-ABAH/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

$builder = app()->make(\App\Support\ReportSnapshotBuilder::class);

// We want to mock DB::statement to just capture the SQL and bindings!
DB::listen(function ($query) {
    if (strpos($query->sql, 'INSERT INTO') !== false) {
        echo "SQL:\n" . $query->sql . "\n\n";
        echo "Bindings:\n" . json_encode($query->bindings, JSON_PRETTY_PRINT) . "\n\n";
    }
});

// Run for 2026-02-28
$ref = new ReflectionMethod($builder, 'buildPerformanceRmPeriodSnapshot');
$ref->setAccessible(true);
$ref->invoke($builder, '2026-02-28', true); // force = true
