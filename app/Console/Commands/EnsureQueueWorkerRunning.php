<?php

namespace App\Console\Commands;

use App\Jobs\AuditAndHealSnapshotsJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\SnapshotQueuePauseService;
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
        $workerControl = app(QueueWorkerControlService::class);
        $checkInterval = (int) $this->option('check-interval');
        $timeout = (string) ($this->option('timeout') ?? config('queue.worker_timeout', 0));
        $memory = (string) ($this->option('memory') ?? config('queue.worker_memory', 512));
        $maxJobs = (int) ($this->option('max-jobs') ?? config('queue.worker_max_jobs', 25));
        $maxTime = (int) ($this->option('max-time') ?? config('queue.worker_max_time', 3600));
        $pools = $this->resolveWorkerPools();

        if ((bool) $this->option('once')) {
            if (!$workerControl->isEnabled()) {
                if ($this->output) {
                    $this->warn('Queue worker monitor dinonaktifkan melalui Job Management.');
                }

                return 0;
            }

            $this->recoverOrphanedImports();

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

            $this->dispatchSnapshotAuditIfIdle();

            return 0;
        }

        // A deliberate CLI invocation is itself an explicit request to enable
        // this project monitor again after it was stopped from the UI.
        $workerControl->markEnabled();

        if (!$workerControl->isEnabled()) {
            if ($this->output) {
                $this->warn('Queue worker monitor dinonaktifkan melalui Job Management.');
            }

            return 0;
        }

        if ($this->output) {
            $this->info('Queue worker monitor started.');
            foreach ($pools as $poolName => $pool) {
                $this->line("Pool {$poolName}: {$pool['queues']} ({$pool['workers']} worker)");
            }
            $this->line("Check interval: {$checkInterval} seconds");
            $this->newLine();
        }

        try {
            while ($workerControl->isEnabled()) {
                $workerControl->touchMonitorHeartbeat();

                try {
                    $this->maintainMonitorHealth();
                    $this->recoverOrphanedImports();

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

                    $this->dispatchSnapshotAuditIfIdle();
                } catch (\Illuminate\Database\QueryException | \PDOException $e) {
                    if ($this->output) {
                        $this->error('Database connection lost in monitor loop, reconnecting: ' . $e->getMessage());
                    }
                    Log::warning('Database connection lost in monitor loop, attempting reconnect.', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    try {
                        DB::purge();
                        DB::reconnect();
                    } catch (\Throwable $reconnectError) {
                        Log::error('Database reconnect failed: ' . $reconnectError->getMessage());
                    }
                } catch (\Throwable $e) {
                    if ($this->output) {
                        $this->error('Monitor loop unexpected error: ' . $e->getMessage());
                    }
                    Log::error('Monitor loop unexpected error', [
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }

                // Wait in short intervals so the Job Management stop switch is
                // honored promptly instead of blocking for the full check interval.
                for ($elapsed = 0; $elapsed < max(1, $checkInterval); $elapsed++) {
                    if (!$workerControl->isEnabled()) {
                        break 2;
                    }
                    sleep(1);
                    $workerControl->touchMonitorHeartbeat();
                }
            }
        } finally {
            $workerControl->clearOwnMonitorHeartbeat();
        }

        return 0;
    }

    private function recoverOrphanedImports(): void
    {
        try {
            $reconciled = app(ImportProgressService::class)->purgeStaleProcessingJobs();
            $recoveredJobIds = app(ImportExecutionService::class)->recoverOrphanedZeroProgressJobs();
            app(SnapshotQueuePauseService::class)->resumeWhenNoActiveImports();

            if ($reconciled > 0 && $this->output) {
                $this->warn("Reconciled {$reconciled} stale import job(s) without a live execution lease.");
            }
            if ($recoveredJobIds !== [] && $this->output) {
                $this->warn('Recovered orphaned import job(s): ' . implode(', ', $recoveredJobIds));
            }
        } catch (\Throwable $e) {
            if ($this->output) {
                $this->error('Error recovering orphaned imports: ' . $e->getMessage());
            }
            Log::error('Queue worker monitor could not recover orphaned imports.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
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
            $orphanCandidates = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $orphanGraceCutoff)
                ->count();
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
                    $logMessage = $isSnapshotOnlyPool
                        ? 'Released snapshot jobs reserved by a dead worker process.'
                        : 'Released queue jobs reserved by a dead worker process.';

                    Log::warning($logMessage, [
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

                if ($this->output) {
                    $this->info("Pool {$poolName}: {$startedWorkers} worker(s) started.");
                }

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
                if ($this->output) {
                    $this->line("[" . now()->toDateTimeString() . "] Pool {$poolName} ready ({$activeWorkers} worker, {$pendingJobs} ready jobs)");
                }
            }
        } catch (\Throwable $e) {
            if ($this->output) {
                $this->error("Error during queue check for {$poolName}: " . $e->getMessage());
            }
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
        static $cachedOutput = null;
        static $cachedAt = 0;

        if ($cachedOutput !== null && (time() - $cachedAt) <= 5) {
            return $cachedOutput;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return '';
        }

        $script = "\$ProgressPreference = 'SilentlyContinue'; Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" -ErrorAction SilentlyContinue | ForEach-Object { \$_.CommandLine }";
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $output = shell_exec('powershell.exe -NoProfile -NonInteractive -NoLogo -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded) . ' 2>NUL') ?? '';

        $cachedOutput = $output;
        $cachedAt = time();

        return $output;
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
                '-NonInteractive',
                '-NoLogo',
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
                "\$ProgressPreference = 'SilentlyContinue'; Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden",
                $this->powershellQuote('powershell.exe'),
                $this->powershellQuote($launcherArgumentLine),
                $this->powershellQuote(base_path())
            );
            $encoded = base64_encode(mb_convert_encoding($launcherCommand, 'UTF-16LE', 'UTF-8'));
            $powershellBinary = 'powershell.exe';
            $command = $powershellBinary . ' -NoProfile -NonInteractive -NoLogo -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded) . ' 2>NUL';
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
        $heartbeats = Cache::get('queue:worker-pool:heartbeats:' . sha1($poolName), []);
        $heartbeats = is_array($heartbeats) ? $heartbeats : [];

        foreach ($registered as $pid => $registeredAt) {
            $pid = (int) $pid;
            if ($pid <= 0 || !isset($running[$pid])) {
                continue;
            }
            if (is_numeric($registeredAt) && ($now - (int) $registeredAt) > 28800) {
                continue;
            }

            $lastHeartbeat = (int) ($heartbeats[(string) $pid] ?? 0);
            $age = $now - (int) $registeredAt;

            // Detect stuck/frozen worker: alive in OS for > 60s, but no heartbeat for > 180s
            // and not holding any active import progress.
            if ($age > 60 && $lastHeartbeat > 0 && ($now - $lastHeartbeat) > 180) {
                if ($this->isWorkerStuck($pid, $lastHeartbeat, $now, $poolName)) {
                    Log::warning("Terminating stuck worker process PID {$pid} for pool {$poolName} (no heartbeat for " . ($now - $lastHeartbeat) . "s)");
                    $this->terminateProcess($pid);
                    continue;
                }
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

    private function isWorkerStuck(int $pid, int $lastHeartbeat, int $now, string $poolName): bool
    {
        $secondsWithoutHeartbeat = $lastHeartbeat > 0 ? ($now - $lastHeartbeat) : 9999;
        if ($secondsWithoutHeartbeat < 180) {
            return false;
        }

        if (str_starts_with($poolName, 'import')) {
            try {
                if (app(ImportProgressService::class)->hasActiveProcessingJobs()) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    private function terminateProcess(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = shell_exec("taskkill /F /PID {$pid} 2>NUL");
            return $output !== null;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 9);
        }

        $output = shell_exec("kill -9 {$pid} 2>/dev/null");
        return $output !== null;
    }

    private function maintainMonitorHealth(): void
    {
        try {
            DB::flushQueryLog();
        } catch (\Throwable) {}

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    private function dispatchSnapshotAuditIfIdle(): void
    {
        static $lastAuditDispatch = 0;
        $now = time();

        if (($now - $lastAuditDispatch) < 300) {
            return;
        }

        try {
            $importProgress = app(ImportProgressService::class);
            if ($importProgress->hasActiveProcessingJobs()) {
                return;
            }

            $busySnapshotJobs = DB::table('jobs')->where('queue', 'snapshots-parallel')->count();
            if ($busySnapshotJobs > 5) {
                return;
            }

            $lastAuditDispatch = $now;
            AuditAndHealSnapshotsJob::dispatch();

            if ($this->output) {
                $this->line("[" . now()->toDateTimeString() . "] Periodic snapshot audit & auto-heal dispatched.");
            }
        } catch (\Throwable $e) {
            Log::debug('Could not dispatch periodic snapshot audit: ' . $e->getMessage());
        }
    }

    /** @return array<int, int> */
    private function runningPhpProcessIds(): array
    {
        static $cachedPids = null;
        static $cachedAt = 0;

        if ($cachedPids !== null && (time() - $cachedAt) <= 5) {
            return $cachedPids;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $script = "\$ProgressPreference = 'SilentlyContinue'; (Get-Process -Name php -ErrorAction SilentlyContinue).Id";
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $output = shell_exec('powershell.exe -NoProfile -NonInteractive -NoLogo -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded) . ' 2>NUL') ?? '';
        } else {
            $output = shell_exec('pgrep -x php 2>/dev/null') ?? '';
        }

        $pids = array_values(array_unique(array_map(
            'intval',
            preg_split('/\s+/', trim($output), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        $cachedPids = $pids;
        $cachedAt = time();

        return $pids;
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
