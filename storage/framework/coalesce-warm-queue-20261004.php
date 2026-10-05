<?php

// One-off, scope-limited recovery. Never touches imports, reserved jobs or
// source data. Retain an oldest pending successor even if one is running.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\WarmDashboardSimpananCacheJob;
use App\Jobs\WarmLandingLoanRiskCacheJob;
use Illuminate\Support\Facades\DB;

$classes = [WarmDashboardSimpananCacheJob::class, WarmLandingLoanRiskCacheJob::class];
$apply = ($argv[1] ?? '') === '--apply';
$directory = storage_path('app/private/queue-warm-coalesce-'.date('Ymd-His'));
if ($apply && ! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create backup directory.');
}

$result = DB::transaction(function () use ($classes, $apply, $directory): array {
    $query = DB::table('jobs')->where('queue', 'reports-low')->whereNull('reserved_at')
        ->orderBy('created_at')->orderBy('id');
    if ($apply) {
        $query->lockForUpdate();
    }
    $seen = [];
    $remove = [];
    foreach ($query->get() as $row) {
        $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        if (! in_array($payload['data']['commandName'] ?? '', $classes, true)) {
            continue;
        }
        $job = unserialize($payload['data']['command'], ['allowed_classes' => $classes]);
        if ($job instanceof WarmDashboardSimpananCacheJob) {
            $identity = $job::class.'|'.$job->type.'|'.serialize($job->context);
        } elseif ($job instanceof WarmLandingLoanRiskCacheJob) {
            $identity = $job::class.'|'.$job->uniqueId();
        } else {
            throw new RuntimeException('Unexpected job payload.');
        }
        if (isset($seen[$identity])) {
            $remove[] = (array) $row;
        } else {
            $seen[$identity] = $row->id;
        }
    }
    if ($apply && $remove !== []) {
        $backup = json_encode($remove, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (file_put_contents($directory.'/jobs-before.json', $backup, LOCK_EX) !== strlen($backup)) {
            throw new RuntimeException('Backup incomplete; no jobs deleted.');
        }
        $deleted = DB::table('jobs')->where('queue', 'reports-low')->whereNull('reserved_at')
            ->whereIn('id', array_column($remove, 'id'))->delete();
        if ($deleted !== count($remove)) {
            throw new RuntimeException('Queue changed; rollback required.');
        }
    }

    return ['apply' => $apply, 'retained_pending_ids' => array_values($seen),
        'redundant_pending_count' => count($remove), 'backup' => $apply ? $directory : null];
});
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
