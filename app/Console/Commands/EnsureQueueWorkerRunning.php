<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

class EnsureQueueWorkerRunning extends Command
{
    protected $signature = 'queue:ensure-running
                          {--queues= : Queues to monitor}
                          {--timeout= : Queue worker timeout in seconds (0 = unlimited)}
                          {--memory= : Queue worker memory limit in MB}
                          {--workers= : Desired worker count for an explicit queue set}
                          {--max-jobs= : Maximum jobs before restart (0 = unlimited)}
                          {--max-time= : Maximum seconds before restart (0 = unlimited)}
                          {--check-interval=60 : How often to check if worker is running}
                          {--once : Run one check and exit}';

    protected $description = 'Ensure queue worker is running, restart if stopped';

    public function handle(): int
    {
        $checkInterval = (int) $this->option('check-interval');
        $timeout = (string) ($this->option('timeout') ?? config('queue.worker_timeout', 0));
        $memory = (string) ($this->option('memory') ?? config('queue.worker_memory', 512));
        $maxJobs = (int) ($this->option('max-jobs') ?? config('queue.worker_max_jobs', 25));
        $maxTime = (int) ($this->option('max-time') ?? config('queue.worker_max_time', 3600));
        $pools = $this->resolveWorkerPools();

        if ((bool) $this->option('once')) {
            foreach ($pools as $poolName => $pool) {
                $this->checkAndEnsureWorker(
                    $poolName,
                    $pool['queues'],
                    $pool['workers'],
                    $timeout,
                    $memory,
                    $maxJobs,
                    $maxTime
                );
            }

            return 0;
        }

        $this->info('Queue worker monitor started.');
        foreach ($pools as $poolName => $pool) {
            $this->line("Pool {$poolName}: {$pool['queues']} ({$pool['workers']} worker)");
        }
        $this->line("Check interval: {$checkInterval} seconds");
        $this->newLine();

        while (true) {
            foreach ($pools as $poolName => $pool) {
                $this->checkAndEnsureWorker(
                    $poolName,
                    $pool['queues'],
                    $pool['workers'],
                    $timeout,
                    $memory,
                    $maxJobs,
                    $maxTime
                );
            }
            sleep($checkInterval);
        }

        return 0;
    }

    private function checkAndEnsureWorker(
        string $poolName,
        string $queues,
        int $desiredWorkers,
        string $timeout,
        string $memory,
        int $maxJobs,
        int $maxTime
    ): void
    {
        $poolLock = Cache::lock('queue:worker-pool:ensure:' . sha1($poolName), 15);
        if (!$poolLock->get()) {
            return;
        }

        try {
            $queueNames = $this->queueNames($queues);
            $now = time();
            $retryAfter = $this->queueRetryAfterSeconds();
            $staleReservedCutoff = $now - $retryAfter;
            $heartbeatWorkers = $this->freshWorkerHeartbeatCount($poolName, $now);
            $registeredWorkers = $this->registeredWorkerProcessCount($poolName, $now);
            $orphanGraceCutoff = $now - 120;
            $isSnapshotOnlyPool = count(array_filter(
                $queueNames,
                static fn (string $queue): bool => str_starts_with($queue, 'snapshots-')
            )) === count($queueNames);
            $orphanCandidates = $isSnapshotOnlyPool
                ? DB::table('jobs')
                    ->whereIn('queue', $queueNames)
                    ->whereNotNull('reserved_at')
                    ->where('reserved_at', '<=', $orphanGraceCutoff)
                    ->count()
                : 0;
            $detectedWorkers = 0;
            if ($orphanCandidates > 0 && max($heartbeatWorkers, $registeredWorkers) === 0) {
                $detectedWorkers = $this->queueWorkerProcessCount($queues);
            }

            $releasedOrphanJobs = 0;
            if ($orphanCandidates > 0 && max($heartbeatWorkers, $registeredWorkers, $detectedWorkers) === 0) {
                $releasedOrphanJobs = DB::table('jobs')
                    ->whereIn('queue', $queueNames)
                    ->whereNotNull('reserved_at')
                    ->where('reserved_at', '<=', $orphanGraceCutoff)
                    ->update([
                        'reserved_at' => null,
                        'available_at' => $now,
                    ]);

                if ($releasedOrphanJobs > 0) {
                    Log::warning('Released snapshot jobs reserved by a dead worker process.', [
                        'pool' => $poolName,
                        'queues' => $queues,
                        'released_jobs' => $releasedOrphanJobs,
                    ]);
                }
            }

            $pendingJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($staleReservedCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $staleReservedCutoff);
                })
                ->count();
            $oldestReadyJobCreatedAt = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->where('available_at', '<=', $now)
                ->whereNull('reserved_at')
                ->min('created_at');
            $staleReservedJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $staleReservedCutoff)
                ->count();
            if ($pendingJobs === 0) {
                // No jobs ready to process, don't need worker.
                return;
            }

            $freshReservedWorkers = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '>', $staleReservedCutoff)
                ->count();
            $pendingWaitSeconds = $oldestReadyJobCreatedAt !== null
                ? max(0, $now - (int) $oldestReadyJobCreatedAt)
                : 0;
            $startupLeaseWorkers = $pendingWaitSeconds <= 15
                ? (int) Cache::get($this->workerLeaseKey($poolName), 0)
                : 0;
            // A reserved job is not proof that its worker process is still alive. On a
            // worker crash it remains reserved until retry_after and must not suppress recovery.
            $knownWorkers = max($heartbeatWorkers, $registeredWorkers, $startupLeaseWorkers);
            if ($knownWorkers < $desiredWorkers && $detectedWorkers === 0) {
                $detectedWorkers = $this->queueWorkerProcessCount($queues);
            }
            $activeWorkers = max($knownWorkers, $detectedWorkers);
            $workersToStart = max(0, $desiredWorkers - $activeWorkers);

            if ($workersToStart > 0) {
                $this->warn("[" . now()->toDateTimeString() . "] Pool {$poolName} needs {$workersToStart} worker(s) for {$pendingJobs} ready job(s).");

                $startedWorkers = 0;
                for ($workerNumber = 1; $workerNumber <= $workersToStart; $workerNumber++) {
                    $workerPid = $this->startQueueWorker($poolName, $queues, $timeout, $memory, $maxJobs, $maxTime, $workerNumber);
                    if ($workerPid !== null) {
                        $startedWorkers++;
                        if ($workerPid > 0) {
                            $this->rememberWorkerPid($poolName, $workerPid, $now);
                        }
                    }
                }

                if ($startedWorkers > 0) {
                    Cache::put(
                        $this->workerLeaseKey($poolName),
                        min($desiredWorkers, $activeWorkers + $startedWorkers),
                        now()->addSeconds(15)
                    );
                }

                $this->info("Pool {$poolName}: {$startedWorkers} worker(s) started.");

                Log::warning('Queue worker was not running. Automatically restarted.', [
                    'pool' => $poolName,
                    'pending_jobs' => $pendingJobs,
                    'fresh_reserved_jobs' => $freshReservedWorkers,
                    'stale_reserved_jobs' => $staleReservedJobs,
                    'released_orphan_jobs' => $releasedOrphanJobs,
                    'queues' => $queues,
                    'desired_workers' => $desiredWorkers,
                    'active_workers_before_start' => $activeWorkers,
                    'started_workers' => $startedWorkers,
                    'timestamp' => now(),
                ]);
            } else {
                $this->line("[" . now()->toDateTimeString() . "] Pool {$poolName} ready ({$activeWorkers} worker, {$pendingJobs} ready jobs)");
            }
        } catch (\Throwable $e) {
            $this->error("Error during queue check for {$poolName}: " . $e->getMessage());
            Log::error('Queue worker monitor error', [
                'pool' => $poolName,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        } finally {
            $poolLock->release();
        }
    }

    private function queueWorkerProcessCount(string $queues): int
    {
        $queueNames = $this->queueNames($queues);
        $output = $this->phpProcessCommandLines();
        if ($output === '') {
            return 0;
        }

        $workers = 0;
        foreach (preg_split('/\R+/', trim($output)) ?: [] as $commandLine) {
            $normalized = strtolower((string) preg_replace('/\s+/', ' ', $commandLine));
            if ($this->commandLineLooksLikeQueueWorker($normalized)
                && $this->commandLineCoversQueues($normalized, $queueNames)) {
                $workers++;
            }
        }

        return $workers;
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

    private function startQueueWorker(
        string $poolName,
        string $queues,
        string $timeout,
        string $memory,
        int $maxJobs,
        int $maxTime,
        int $workerNumber
    ): ?int
    {
        $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $safePoolName = preg_replace('/[^a-z0-9_-]+/i', '-', $poolName) ?: 'pool';
        $logBase = storage_path('logs/queue-worker-' . now()->format('Ymd-His') . '-' . $safePoolName . '-' . getmypid() . '-' . $workerNumber);

        $workerArgs = [
            $artisan,
            'queue:work',
            '--queue=' . $queues,
            '--timeout=' . $timeout,
            '--memory=' . $memory,
            '--sleep=' . max(0, (int) config('queue.worker_sleep', 1)),
        ];
        if ($maxJobs > 0) {
            $workerArgs[] = '--max-jobs=' . $maxJobs;
        }
        if ($maxTime > 0) {
            $workerArgs[] = '--max-time=' . $maxTime;
        }

        // Start worker in background (non-blocking).
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $launcher = base_path('scripts/start_queue_worker.ps1');
            $launcherArgs = [
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $launcher,
                '-PhpExecutable',
                $php,
                '-ArtisanPath',
                $artisan,
                '-WorkingDirectory',
                base_path(),
                '-Queues',
                $queues,
                '-Timeout',
                $timeout,
                '-Memory',
                $memory,
                '-Sleep',
                (string) max(0, (int) config('queue.worker_sleep', 1)),
                '-OutputLog',
                $logBase . '.out.log',
                '-ErrorLog',
                $logBase . '.err.log',
                '-MaxJobs',
                (string) $maxJobs,
                '-MaxTime',
                (string) $maxTime,
            ];
            $launcherArgumentLine = implode(' ', array_map([$this, 'windowsCommandLineQuote'], $launcherArgs));
            $launcherCommand = sprintf(
                'Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden',
                $this->powershellQuote('powershell.exe'),
                $this->powershellQuote($launcherArgumentLine),
                $this->powershellQuote(base_path())
            );
            $encoded = base64_encode(mb_convert_encoding($launcherCommand, 'UTF-16LE', 'UTF-8'));
            $command = 'powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded);
        } else {
            $args = array_map('escapeshellarg', array_merge([$php], $workerArgs));
            $command = sprintf(
                'cd %s && ( %s > %s 2>&1 & echo $! )',
                escapeshellarg(base_path()),
                implode(' ', $args),
                escapeshellarg($logBase . '.log')
            );
        }

        $process = @popen($command, 'r');
        if (!is_resource($process)) {
            return null;
        }

        $output = stream_get_contents($process);
        $exitCode = @pclose($process);
        if ($exitCode !== 0) {
            return null;
        }

        return preg_match('/\b(\d+)\b/', (string) $output, $matches)
            ? (int) $matches[1]
            : 0;
    }

    private function workerLeaseKey(string $poolName): string
    {
        return 'queue:worker-pool:lease:' . sha1($poolName);
    }

    private function freshWorkerHeartbeatCount(string $poolName, int $now): int
    {
        $heartbeats = Cache::get('queue:worker-pool:heartbeats:' . sha1($poolName), []);
        if (!is_array($heartbeats)) {
            return 0;
        }

        return count(array_filter(
            $heartbeats,
            static fn ($timestamp): bool => is_numeric($timestamp) && ($now - (int) $timestamp) <= 15
        ));
    }

    private function registeredWorkerProcessCount(string $poolName, int $now): int
    {
        $key = $this->workerPidKey($poolName);
        $registered = Cache::get($key, []);
        if (!is_array($registered) || $registered === []) {
            return 0;
        }

        $running = array_flip($this->runningPhpProcessIds());
        $alive = [];
        foreach ($registered as $pid => $registeredAt) {
            $pid = (int) $pid;
            if ($pid <= 0 || !isset($running[$pid])) {
                continue;
            }
            if (is_numeric($registeredAt) && ($now - (int) $registeredAt) > 28800) {
                continue;
            }
            $alive[(string) $pid] = (int) $registeredAt;
        }

        if ($alive === []) {
            Cache::forget($key);
        } else {
            Cache::put($key, $alive, now()->addHours(8));
        }

        return count($alive);
    }

    /** @return array<int, int> */
    private function runningPhpProcessIds(): array
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $script = '(Get-Process -Name php -ErrorAction SilentlyContinue).Id';
            $output = shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($script) . ' 2>NUL') ?? '';
        } else {
            $output = shell_exec('pgrep -x php 2>/dev/null') ?? '';
        }

        return array_values(array_unique(array_map(
            'intval',
            preg_split('/\s+/', trim($output), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }

    private function rememberWorkerPid(string $poolName, int $pid, int $now): void
    {
        $key = $this->workerPidKey($poolName);
        $registered = Cache::get($key, []);
        $registered = is_array($registered) ? $registered : [];
        $registered[(string) $pid] = $now;
        Cache::put($key, $registered, now()->addHours(8));
    }

    private function workerPidKey(string $poolName): string
    {
        return 'queue:worker-pool:pids:' . sha1($poolName);
    }

    private function powershellQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function windowsCommandLineQuote(string $value): string
    {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }

    private function normalizeQueues(string $queues): string
    {
        return implode(',', $this->queueNames($queues));
    }

    /** @return array<string, array{queues: string, workers: int}> */
    private function resolveWorkerPools(): array
    {
        $explicitQueues = trim((string) $this->option('queues'));
        if ($explicitQueues !== '') {
            $normalizedQueues = $this->normalizeQueues($explicitQueues);
            $poolName = 'explicit-' . substr(sha1($normalizedQueues), 0, 8);
            foreach ((array) config('queue.worker_pools', []) as $configuredName => $configuredPool) {
                if ($this->normalizeQueues((string) ($configuredPool['queues'] ?? '')) === $normalizedQueues) {
                    $poolName = (string) $configuredName;
                    break;
                }
            }

            return [
                $poolName => [
                    'queues' => $normalizedQueues,
                    'workers' => max(1, (int) ($this->option('workers') ?: 1)),
                ],
            ];
        }

        $resolved = [];
        foreach ((array) config('queue.worker_pools', []) as $poolName => $pool) {
            $queues = $this->normalizeQueues((string) ($pool['queues'] ?? ''));
            if ($queues === '') {
                continue;
            }

            $resolved[(string) $poolName] = [
                'queues' => $queues,
                'workers' => max(1, (int) ($pool['workers'] ?? 1)),
            ];
        }

        if ($resolved !== []) {
            return $resolved;
        }

        return [
            'default' => [
                'queues' => $this->normalizeQueues((string) config('queue.worker_queues', 'default')),
                'workers' => 1,
            ],
        ];
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

        sort($queueNames);
        sort($workerQueues);

        return $queueNames === $workerQueues;
    }
}
