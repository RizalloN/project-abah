<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportHealthCheckCommand extends Command
{
    protected $signature = 'import:health-check {--fix : Auto-terminate stuck jobs} {--hours=2 : Hours threshold for stuck detection}';

    protected $description = 'Health check for stuck/stale import jobs and snapshot deferral queue blockage';

    public function handle(): int
    {
        try {
            $hoursThreshold = max(1, (int) $this->option('hours'));
            $shouldFix = (bool) $this->option('fix');

            $this->info('Running import health check...');

            $stuckJobs = $this->findStuckImportJobs($hoursThreshold);
            $deferredSnapshots = $this->countDeferredSnapshots();

            $this->displayStatus($stuckJobs, $deferredSnapshots, $hoursThreshold);

            if ($shouldFix && !empty($stuckJobs)) {
                $this->fixStuckJobs($stuckJobs);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            Log::error('ImportHealthCheckCommand failed', ['exception' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    private function findStuckImportJobs(int $hoursThreshold): array
    {
        $cutoff = now()->subHours($hoursThreshold);

        return DB::table('import_jobs')
            ->where('status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'id_report',
                'file_name',
                'status',
                'total_success',
                'total_failed',
                'updated_at',
                'created_at',
            ])
            ->toArray();
    }

    private function countDeferredSnapshots(): int
    {
        try {
            return DB::table('jobs')
                ->where('queue', 'snapshots-parallel')
                ->where('reserved_at', null)
                ->where('payload', 'like', '%ExecuteBatchedSnapshotJob%')
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function displayStatus(array $stuckJobs, int $deferredCount, int $threshold): void
    {
        $this->line("\n<fg=cyan>=== IMPORT HEALTH STATUS ===</>");

        if (empty($stuckJobs)) {
            $this->line('<fg=green>✓ No stuck import jobs detected.</>');
        } else {
            $this->line("<fg=red>✗ Found " . count($stuckJobs) . " stuck import job(s) (threshold: {$threshold}h)</>");
            $this->table(
                ['Job ID', 'Report ID', 'File', 'Status', 'Stuck Since', 'Success', 'Failed'],
                array_map(function ($job) {
                    $stuckSince = Carbon::parse($job->updated_at)->diffForHumans();
                    return [
                        $job->id,
                        $job->id_report,
                        substr($job->file_name, 0, 20),
                        $job->status,
                        $stuckSince,
                        $job->total_success,
                        $job->total_failed,
                    ];
                }, $stuckJobs)
            );
        }

        $this->line("\n<fg=cyan>SNAPSHOT QUEUE STATUS</>");
        if ($deferredCount === 0) {
            $this->line('<fg=green>✓ No deferred snapshot jobs pending.</>');
        } else {
            $this->line("<fg=yellow>⚠ {$deferredCount} snapshot rebuild job(s) waiting in queue (deferred)</>");
        }

        if (!empty($stuckJobs) && $deferredCount > 0) {
            $this->line("\n<fg=red>⚠ WARNING: Stuck import job is blocking snapshot rebuilds!</>");
            $this->line('  → Run with --fix flag to auto-terminate stuck jobs and resume snapshots');
        }
    }

    private function fixStuckJobs(array $stuckJobs): void
    {
        $this->line("\n<fg=yellow>Attempting to fix stuck jobs...</>");

        foreach ($stuckJobs as $job) {
            try {
                DB::table('import_jobs')
                    ->where('id', $job->id)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now(),
                    ]);

                Log::warning('Auto-terminated stuck import job', [
                    'job_id' => $job->id,
                    'report_id' => $job->id_report,
                    'file' => $job->file_name,
                    'stuck_duration_hours' => now()->diffInHours(Carbon::parse($job->updated_at)),
                ]);

                $this->line("  ✓ Marked job #{$job->id} as failed");
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed to fix job #{$job->id}: " . $e->getMessage());
            }
        }

        $this->line("\n<fg=green>Snapshot queue should resume processing within 60 seconds.</>");
    }
}
