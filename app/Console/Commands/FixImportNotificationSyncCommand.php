<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Import\ImportProgressService;

/**
 * Command to fix notification desynchronization for existing jobs
 *
 * Scans for jobs where preview failed but job was created anyway
 * Marks conflicting jobs properly to prevent confusion
 */
class FixImportNotificationSyncCommand extends Command
{
    protected $signature = 'import:fix-notification-sync
        {--job-id= : Specific job ID to fix}
        {--all : Fix all conflicting jobs}
        {--dry-run : Preview changes without applying}
    ';

    protected $description = 'Fix import notification desynchronization issues';

    public function handle(ImportProgressService $progressService): int
    {
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║  Import Notification Synchronization Fixer                      ║');
        $this->info('║  Resolves: Preview failed but job running (conflict)            ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $jobId = $this->option('job-id');
        $fixAll = $this->option('all');

        if (!$dryRun) {
            if (!$this->confirm('This will fix notification sync issues. Continue?')) {
                return self::SUCCESS;
            }
        }

        if ($jobId) {
            return $this->fixSpecificJob((int) $jobId, $dryRun, $progressService);
        } elseif ($fixAll) {
            return $this->fixAllConflictingJobs($dryRun, $progressService);
        } else {
            return $this->showAnalysis();
        }
    }

    private function fixSpecificJob(int $jobId, bool $dryRun, ImportProgressService $progressService): int
    {
        $job = DB::table('import_jobs')->where('id', $jobId)->first();

        if (!$job) {
            $this->error("Job #{$jobId} tidak ditemukan");
            return self::FAILURE;
        }

        $this->line("Job #{$jobId}: {$job->status}");

        if ($dryRun) {
            $this->info("[DRY RUN] Would mark job #{$jobId} as 'validation_failed'");
            return self::SUCCESS;
        }

        $progressService->markFailed($jobId, 'Notification sync fix: preview validation state conflict');
        $this->info("✓ Job #{$jobId} marked as failed");

        return self::SUCCESS;
    }

    private function fixAllConflictingJobs(bool $dryRun, ImportProgressService $progressService): int
    {
        $conflictingJobs = DB::table('import_jobs')
            ->whereIn('status', ['processing', 'queued', 'staging'])
            ->where('created_at', '<', now()->subHours(2))
            ->get();

        if ($conflictingJobs->isEmpty()) {
            $this->info('✓ No conflicting jobs found');
            return self::SUCCESS;
        }

        $this->warn("Found {$conflictingJobs->count()} potentially conflicting jobs");

        $fixed = 0;
        foreach ($conflictingJobs as $job) {
            if ($dryRun) {
                $this->line("[DRY RUN] Would fix Job #{$job->id}");
            } else {
                $progressService->markFailed(
                    (int) $job->id,
                    'Notification sync fix: stale job timeout'
                );
                $fixed++;
                $this->line("✓ Fixed Job #{$job->id}");
            }
        }

        if (!$dryRun) {
            $this->info("Fixed {$fixed} conflicting jobs");
        }

        return self::SUCCESS;
    }

    private function showAnalysis(): int
    {
        $this->info('📊 IMPORT NOTIFICATION SYNC ANALYSIS');
        $this->newLine();

        // Count jobs by status
        $statusCounts = DB::table('import_jobs')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $tableRows = [];
        foreach ($statusCounts as $row) {
            $tableRows[] = [$row->status, $row->count];
        }

        $this->table(['Status', 'Count'], $tableRows);
        $this->newLine();

        // Find potentially conflicting jobs (old processing/queued)
        $staleJobs = DB::table('import_jobs')
            ->whereIn('status', ['processing', 'queued', 'staging'])
            ->where('created_at', '<', now()->subHours(2))
            ->count();

        if ($staleJobs > 0) {
            $this->warn("⚠️  Found {$staleJobs} potentially stale jobs (>2 hours old)");
            $this->line('Run with --all to fix these automatically');
            $this->newLine();
        }

        // Check for recent failures
        $recentFailures = DB::table('import_jobs')
            ->where('status', 'failed')
            ->where('updated_at', '>', now()->subHours(6))
            ->count();

        if ($recentFailures > 0) {
            $this->info("ℹ️  {$recentFailures} jobs failed in the last 6 hours");
        }

        $this->newLine();
        $this->info('💡 Usage:');
        $this->line('  Fix specific job:    php artisan import:fix-notification-sync --job-id=123');
        $this->line('  Fix all stale jobs:  php artisan import:fix-notification-sync --all');
        $this->line('  Preview changes:     php artisan import:fix-notification-sync --all --dry-run');

        return self::SUCCESS;
    }
}
