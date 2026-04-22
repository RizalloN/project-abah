<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureQueueWorkerRunning extends Command
{
    protected $signature = 'queue:ensure-running
                          {--queues=imports-high,default,reports-low : Queues to monitor}
                          {--timeout=0 : Queue worker timeout in seconds (0 = unlimited)}
                          {--memory=256 : Queue worker memory limit in MB}
                          {--max-jobs=0 : Maximum jobs before restart (0 = unlimited)}
                          {--check-interval=60 : How often to check if worker is running}';

    protected $description = 'Ensure queue worker is running, restart if stopped';

    public function handle(): int
    {
        $checkInterval = (int) $this->option('check-interval');
        $queues = $this->option('queues');
        $timeout = $this->option('timeout');
        $memory = $this->option('memory');
        $maxJobs = (int) $this->option('max-jobs');

        $this->info('Queue worker monitor started.');
        $this->line("Monitoring queues: {$queues}");
        $this->line("Check interval: {$checkInterval} seconds");
        $this->newLine();

        while (true) {
            $this->checkAndEnsureWorker($queues, $timeout, $memory, $maxJobs);
            sleep($checkInterval);
        }

        return 0;
    }

    private function checkAndEnsureWorker(string $queues, string $timeout, string $memory, int $maxJobs): void
    {
        try {
            $isRunning = $this->isQueueWorkerRunning();
            $pendingJobs = DB::table('jobs')->count();

            if ($pendingJobs === 0) {
                // No jobs to process, don't need worker
                return;
            }

            if (!$isRunning) {
                $this->warn("[" . now()->toDateTimeString() . "] Queue worker not running with {$pendingJobs} pending jobs!");
                $this->info("Attempting to start queue worker...");

                $this->startQueueWorker($queues, $timeout, $memory, $maxJobs);
                $this->info("Queue worker started.");

                Log::warning('Queue worker was not running. Automatically restarted.', [
                    'pending_jobs' => $pendingJobs,
                    'queues' => $queues,
                    'timestamp' => now(),
                ]);
            } else {
                // Worker is running, just log the status
                $this->line("[" . now()->toDateTimeString() . "] Queue worker running ({$pendingJobs} pending jobs)");
            }
        } catch (\Throwable $e) {
            $this->error("Error during queue check: " . $e->getMessage());
            Log::error('Queue worker monitor error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isQueueWorkerRunning(): bool
    {
        // If a job is reserved, a worker is actively processing it.
        $reservedCount = DB::table('jobs')->whereNotNull('reserved_at')->count();
        if ($reservedCount > 0) {
            return true;
        }

        // On Windows, only count php.exe processes that actually run queue workers/listeners.
        $output = shell_exec('wmic process where "name=\'php.exe\'" get CommandLine /value 2>NUL') ?? '';
        if ($output !== '') {
            $normalized = strtolower($output);
            if (str_contains($normalized, 'queue:work') || str_contains($normalized, 'queue:listen')) {
                return true;
            }
        }

        return false;
    }

    private function startQueueWorker(string $queues, string $timeout, string $memory, int $maxJobs): void
    {
        $command = "php artisan queue:work --queue={$queues} --timeout={$timeout} --memory={$memory}";

        if ($maxJobs > 0) {
            $command .= " --max-jobs={$maxJobs}";
        }

        // Start worker in background (non-blocking)
        $command .= " > storage/logs/queue-worker.log 2>&1 &";

        // For Windows, use different syntax
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = "start /B php artisan queue:work --queue={$queues} --timeout={$timeout} --memory={$memory}";
            if ($maxJobs > 0) {
                $command .= " --max-jobs={$maxJobs}";
            }
        }

        shell_exec($command);
    }
}
