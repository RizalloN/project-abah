<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$jobs = DB::table('jobs')
    ->where('payload', 'like', '%Snapshot%')
    ->orWhere('payload', 'like', '%Sync%')
    ->orWhere('payload', 'like', '%Warm%')
    ->get(['id', 'queue', 'payload']);

echo "Count: " . $jobs->count() . "\n";
foreach ($jobs as $j) {
    $p = json_decode($j->payload, true);
    $displayName = $p['displayName'] ?? 'unknown';
    $command = $p['data']['command'] ?? '';
    
    $period = 'N/A';
    if (preg_match('/s:10:"(\d{4}-\d{2}-\d{2})"/', $command, $matches)) {
        $period = $matches[1];
    } elseif (preg_match('/"period":"(\d{4}-\d{2}-\d{2})"/', $command, $matches)) {
        $period = $matches[1];
    }
    
    echo $j->id . ": " . $displayName . " | Period: " . $period . " (" . $j->queue . ")\n";
}
