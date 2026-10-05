<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$root = storage_path('app/private/multipn-20260831-recovery-20261004');
$result = json_decode(file_get_contents($root . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
if ($result['different_fingerprints'] !== 0 || $result['unexpected_fingerprints'] !== 0) {
    throw new RuntimeException('Repair verification has not passed.');
}
$expected = [62 => 741515, 64 => 698911, 65 => 789491];
$jobs = DB::table('import_jobs')->whereIn('id', array_keys($expected))->get();
if ($jobs->count() !== 3 || file_exists($root . '/jobs-before.json')) {
    throw new RuntimeException('Unexpected jobs or finalization already started.');
}
file_put_contents($root . '/jobs-before.json', json_encode($jobs, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
DB::transaction(function () use ($jobs, $expected, $root): void {
    foreach ($jobs as $job) {
        if ((int) $job->id_report !== 9 || $job->status !== 'completed' || (int) $job->total_success !== $expected[$job->id]) {
            throw new RuntimeException('Job identity/count changed.');
        }
        $context = json_decode($job->job_context, true, 512, JSON_THROW_ON_ERROR);
        $context['duplicate_repair'] = ['period' => '2026-08-31', 'removed_rows' => $expected[$job->id], 'backup_directory' => $root, 'original_status' => 'completed'];
        $message = 'Batch duplikat 31-08-2026 dibatalkan setelah verifikasi seluruh baris; data asli 7 September dipertahankan. Cadangan tersedia.';
        app(App\Services\Import\ImportProgressService::class)->updateJob((int) $job->id, [
            'status' => 'terminated', 'total_success' => 0, 'total_failed' => 0,
            'message' => $message, 'job_context' => json_encode($context, JSON_THROW_ON_ERROR),
        ], ['status' => 'terminated', 'phase' => 'duplicate_removed', 'percent' => 100, 'processed_rows' => 0, 'total_success' => 0, 'total_failed' => 0, 'message' => $message]);
    }
});
echo 'DUPLICATE_JOB_HISTORY_CORRECTED' . PHP_EOL;
app(App\Support\ReportDataSyncService::class)->syncImportedJob(0, 'simpanan_multipn', '2026-08-31', 'verified_duplicate_repair_20261004');
echo 'SNAPSHOT_REFRESH_REQUESTED' . PHP_EOL;
