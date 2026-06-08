<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

class EnsureQueueWorkerRunning extends Command
{
    protected $signature = 'queue:ensure-running
                          {--queues= : Queues to monitor}
                          {--timeout= : Queue worker timeout in seconds (0 = unlimited)}
                          {--memory= : Queue worker memory limit in MB}
                          {--max-jobs= : Maximum jobs before restart (0 = unlimited)}
                          {--max-time= : Maximum seconds before restart (0 = unlimited)}
                          {--check-interval=60 : How often to check if worker is running}
                          {--once : Run one check and exit}';

    protected $description = 'Ensure queue worker is running, restart if stopped';

    public function handle(): int
    {
        $checkInterval = (int) $this->option('check-interval');
        $queues = $this->normalizeQueues((string) ($this->option('queues') ?: config('queue.worker_queues', 'imports-high,imports-daily-loan,snapshots-parallel,default,reports-low,shadow-backfill')));
        $timeout = (string) ($this->option('timeout') ?? config('queue.worker_timeout', 0));
        $memory = (string) ($this->option('memory') ?? config('queue.worker_memory', 512));
        $maxJobs = (int) ($this->option('max-jobs') ?? config('queue.worker_max_jobs', 25));
        $maxTime = (int) ($this->option('max-time') ?? config('queue.worker_max_time', 3600));

        if ((bool) $this->option('once')) {
            $this->checkAndEnsureWorker($queues, $timeout, $memory, $maxJobs, $maxTime);

            return 0;
        }

        $this->info('Queue worker monitor started.');
        $this->line("Monitoring queues: {$queues}");
        $this->line("Check interval: {$checkInterval} seconds");
        $this->newLine();

        while (true) {
            $this->checkAndEnsureWorker($queues, $timeout, $memory, $maxJobs, $maxTime);
            sleep($checkInterval);
        }

        return 0;
    }

    private function checkAndEnsureWorker(string $queues, string $timeout, string $memory, int $maxJobs, int $maxTime): void
    {
        try {
            $queueNames = $this->queueNames($queues);
            $now = time();
            $retryAfter = $this->queueRetryAfterSeconds();
            $staleReservedCutoff = $now - $retryAfter;
            $pendingQueueNames = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($staleReservedCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $staleReservedCutoff);
                })
                ->distinct()
                ->pluck('queue')
                ->map(static fn ($queue): string => (string) $queue)
                ->all();
            $pendingJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($staleReservedCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $staleReservedCutoff);
                })
                ->count();
            $staleReservedJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $staleReservedCutoff)
                ->count();
            $isRunning = $this->isQueueWorkerRunning(implode(',', $pendingQueueNames ?: $queueNames), $retryAfter);

            if ($pendingJobs === 0) {
                // No jobs ready to process, don't need worker.
                return;
            }

            if (!$isRunning) {
                $this->warn("[" . now()->toDateTimeString() . "] Queue worker not running with {$pendingJobs} pending jobs!");
                $this->info("Attempting to start queue worker...");

                $this->startQueueWorker($queues, $timeout, $memory, $maxJobs, $maxTime);
                $this->info("Queue worker started.");

                Log::warning('Queue worker was not running. Automatically restarted.', [
                    'pending_jobs' => $pendingJobs,
                    'stale_reserved_jobs' => $staleReservedJobs,
                    'queues' => $queues,
                    'timestamp' => now(),
                ]);
            } else {
                // Worker is running, just log the status
                $this->line("[" . now()->toDateTimeString() . "] Queue worker running ({$pendingJobs} ready jobs)");
            }
        } catch (\Throwable $e) {
            $this->error("Error during queue check: " . $e->getMessage());
            Log::error('Queue worker monitor error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isQueueWorkerRunning(string $queues, ?int $retryAfter = null): bool
    {
        // Only fresh reservations prove a worker is active. Stale reserved rows
        // are retryable jobs and must not suppress auto-start.
        $queueNames = $this->queueNames($queues);
        $activeReservedCutoff = time() - ($retryAfter ?? $this->queueRetryAfterSeconds());
        $reservedCount = DB::table('jobs')
            ->whereIn('queue', $queueNames)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '>', $activeReservedCutoff)
            ->count();
        if ($reservedCount > 0) {
            return true;
        }

        // On Windows, only count php.exe processes that actually run queue workers/listeners.
        $output = $this->phpProcessCommandLines();
        if ($output !== '') {
            foreach (preg_split('/\R+/', trim($output)) ?: [] as $commandLine) {
                $normalized = strtolower((string) preg_replace('/\s+/', ' ', $commandLine));
                if (!$this->commandLineLooksLikeQueueWorker($normalized)) {
                    continue;
                }

                if ($this->commandLineCoversQueues($normalized, $queueNames)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function queueRetryAfterSeconds(): int
    {
        $connection = (string) config('queue.default', 'database');
        $retryAfter = (int) config("queue.connections.{$connection}.retry_after", 90);

        return max(30, $retryAfter);
    }

    private function phpProcessCommandLines(): string
    {
        $output = shell_exec('wmic process where "name=\'php.exe\'" get CommandLine /value 2>NUL') ?? '';
        if (trim($output) !== '') {
            return $output;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return '';
        }

        $script = "Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | ForEach-Object { \$_.CommandLine }";
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));

        return shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded) . ' 2>NUL') ?? '';
    }

    private function startQueueWorker(string $queues, string $timeout, string $memory, int $maxJobs, int $maxTime): void
    {
        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $logBase = storage_path('logs/queue-worker-' . now()->format('Ymd-His') . '-' . getmypid());

        $workerArgs = [
            $artisan,
            'queue:work',
            '--queue=' . $queues,
            '--timeout=' . $timeout,
            '--memory=' . $memory,
        ];
        if ($maxJobs > 0) {
            $workerArgs[] = '--max-jobs=' . $maxJobs;
        }
        if ($maxTime > 0) {
            $workerArgs[] = '--max-time=' . $maxTime;
        }

        // Start worker in background (non-blocking).
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $psCommand = sprintf(
                'Start-Process -FilePath %s -ArgumentList @(%s) -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s',
                $this->powershellQuote($php),
                implode(',', array_map([$this, 'powershellQuote'], $workerArgs)),
                $this->powershellQuote(base_path()),
                $this->powershellQuote($logBase . '.out.log'),
                $this->powershellQuote($logBase . '.err.log')
            );

            $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psCommand);
        } else {
            $args = array_map('escapeshellarg', array_merge([$php], $workerArgs));
            $command = sprintf(
                'cd %s && %s > %s 2>&1 &',
                escapeshellarg(base_path()),
                implode(' ', $args),
                escapeshellarg($logBase . '.log')
            );
        }

        $process = @popen($command, 'r');
        if (is_resource($process)) {
            @pclose($process);
        }
    }

    private function powershellQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function normalizeQueues(string $queues): string
    {
        return implode(',', $this->queueNames($queues));
    }

    private function queueNames(string $queues): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            static fn (string $queue): string => trim($queue),
            explode(',', $queues)
        ))));

        return $names !== [] ? $names : ['default'];
    }

    private function commandLineLooksLikeQueueWorker(string $commandLine): bool
    {
        return str_contains($commandLine, 'queue:work') || str_contains($commandLine, 'queue:listen');
    }

    private function commandLineCoversQueues(string $commandLine, array $queueNames): bool
    {
        if (!preg_match('/--queue(?:=|\s+)([^\s"]+|"[^"]+"|\'[^\']+\')/', $commandLine, $matches)) {
            return in_array('default', $queueNames, true);
        }

        $workerQueues = $this->queueNames(trim($matches[1], '"\''));

        return count(array_intersect($queueNames, $workerQueues)) > 0;
    }
}
