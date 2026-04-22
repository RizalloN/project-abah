<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$job = DB::table('import_jobs')->where('id', 5)->first();
if ($job) {
    $params = json_decode($job->job_context, true) ?: (json_decode($job->params ?? '{}', true));
    echo "Job Context: " . json_encode($params) . "\n";
}
