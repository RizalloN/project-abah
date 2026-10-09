<?php

namespace App\Console\Commands;

use App\Jobs\AuditAndHealSnapshotsJob;
use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\SnapshotQueuePauseService;
use App\Support\LatestSnapshotRecoveryService;
use App\Support\StrictDateParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;

class EnsureQueueWorkerRunning extends Command
{
    /** @var array<int, resource> */
    private array $windowsWorkerProcesses = [];

    /** @var array<int, array{pool: string, started_at: float, stable: bool}> */
    private array $windowsWorkerStarts = [];

    protected $signature = 'queue:ensure-running
                          {--queues= : Queues to monitor}
                          {--timeout= : Queue worker timeout in seconds (0 = unlimited)}
                          {--memory= : Queue worker memory limit in MB}
                          {--workers= : Desired worker count for an explicit queue set}
                          {--max-jobs= : Maximum jobs before restart (0 = unlimited)}
                          {--max-time= : Maximum seconds before restart (0 = unlimited)}
                          {--check-interval=60 : How often to check if worker is running}
                          {--managed : Started by the application watchdog; never re-enable a disabled monitor}
                          {--once : Run one check and exit}';

    protected $description = 'Ensure queue worker is running, restart if stopped';

    public function handle(): int
    {
        $restartSignal = Cache::get('illuminate:queue:restart');
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

        $managedLaunch = (bool) $this->option('managed');
        // A deliberate operator CLI invocation is an explicit enable request.
        // A delayed managed launcher must never undo a Stop action from the UI.
        if (!$managedLaunch) {
            $workerControl->markEnabled();
        }

        if (!$workerControl->isEnabled()) {
            if ($this->output) {
                $this->warn('Queue worker monitor dinonaktifkan melalui Job Management.');
            }

            return 0;
        }

        if (!$workerControl->claimMonitorLeadership()) {
            if ($this->output) {
                $this->warn('Monitor lain sudah memegang leadership. Proses duplikat dihentikan.');
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
                if ($this->monitorRestartRequested($restartSignal)) {
                    break;
                }
                if (!$workerControl->touchMonitorHeartbeat()) {
                    if ($this->output) {
                        $this->warn('Leadership monitor berpindah ke proses lain. Monitor ini berhenti aman.');
                    }
                    break;
                }
                $this->reapFinishedWindowsWorkers();

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
                    if ($this->monitorRestartRequested($restartSignal)) {
                        break 2;
                    }
                    if ($workerControl->consumeDemandSignal()) {
                        break;
                    }
                    sleep(1);
                    $this->reapFinishedWindowsWorkers();
                    if (!$workerControl->touchMonitorHeartbeat()) {
                        break 2;
                    }
                }
            }
        } finally {
            $workerControl->clearOwnMonitorHeartbeat();
        }

        return 0;
    }

    private function monitorRestartRequested(mixed $initialSignal): bool
    {
        if (Cache::get('illuminate:queue:restart') === $initialSignal) {
            return false;
        }

        // Release only this monitor's heartbeat in handle()'s finally block.
        // Workers finish their current jobs and the enabled watchdog launches
        // a new monitor that reads the updated code.
        Log::info('Queue worker monitor yielding to queue restart signal.');

        return true;
    }

    private function recoverOrphanedImports(): void
    {
        try {
            $recoveredJobIds = app(ImportExecutionService::class)->recoverOrphanedZeroProgressJobs();
            // Give confirmed zero-progress orphans a recovery opportunity before
            // the stale sweep turns them terminal and excludes them from recovery.
            $reconciled = app(ImportProgressService::class)->purgeStaleProcessingJobs();
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

            // DatabaseQueue owns reservation recovery through retry_after. A short
            // heartbeat/probe outage is not proof that a long import is dead.
            $releasedOrphanJobs = 0;

            $pendingJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->where('available_at', '<=', $now)
                ->where(function ($query) use ($staleReservedCutoff): void {
                    $query->whereNull('reserved_at')
                        ->orWhere('reserved_at', '<=', $staleReservedCutoff);
                })
                ->count();
            if ($pendingJobs === 0) {
                // Idle pools need neither OS probes nor additional queue scans.
                return;
            }

            $heartbeatWorkers = $this->freshWorkerHeartbeatCount($poolName, $now);
            $registeredWorkers = $this->registeredWorkerProcessCount($poolName, $now);
            $detectedWorkers = 0;
            $staleReservedJobs = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '<=', $staleReservedCutoff)
                ->count();

            $freshReservedWorkers = DB::table('jobs')
                ->whereIn('queue', $queueNames)
                ->whereNotNull('reserved_at')
                ->where('reserved_at', '>', $staleReservedCutoff)
                ->count();
            // The lease describes workers launched moments ago, regardless of
            // how old the waiting job is. Tying it to pending age caused a fast
            // demand signal to launch the same pool twice before first heartbeat.
            $startupLeaseWorkers = (int) Cache::get($this->workerLeaseKey($poolName), 0);
            // A reserved job is not proof that its worker process is still alive. On a
            // worker crash it remains reserved until retry_after and must not suppress recovery.
            $knownWorkers = max($heartbeatWorkers, $registeredWorkers, $startupLeaseWorkers);
            if ($knownWorkers < $desiredWorkers && $detectedWorkers === 0) {
                $detectedWorkers = $this->queueWorkerProcessCount($queues);
            }
            $activeWorkers = max($knownWorkers, $detectedWorkers);
            $workersToStart = $this->workersNeededForDemand($desiredWorkers, $activeWorkers, $pendingJobs, $freshReservedWorkers);

            if ($workersToStart > 0) {
                if ($this->poolLaunchBackoffActive($poolName, $now)) {
                    if ($this->output) {
                        $this->warn("Pool {$poolName} sedang cooldown setelah kegagalan launch; retry dilakukan otomatis.");
                    }
                    return;
                }

                if ($this->output) {
                    $this->warn("[" . now()->toDateTimeString() . "] Pool {$poolName} needs {$workersToStart} worker(s) for {$pendingJobs} ready job(s).");
                }

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
                } else {
                    $this->recordPoolLaunchFailure($poolName, $now);
                }

                if ($this->output) {
                    $this->info("Pool {$poolName}: {$startedWorkers} worker(s) started.");
                }

                Log::warning($startedWorkers > 0 ? 'Queue worker capacity increased.' : 'Queue worker launch failed; retry scheduled.', [
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

    private function workersNeededForDemand(int $capacity, int $active, int $ready, int $reserved): int
    {
        // Reservations estimate occupancy, but never establish process liveness.
        $busy = min(max(0, $active), max(0, $reserved));
        $target = min(max(0, $capacity), max(0, $ready) + $busy);

        return max(0, $target - max(0, $active));
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

        if (PHP_OS_FAMILY === 'Windows') {
            $script = "\$ErrorActionPreference = 'Stop'; Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | ForEach-Object { \$_.CommandLine }";
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand', $encoded];
        } else {
            $command = ['ps', '-eo', 'args'];
        }
        [$exitCode, $output, $error] = app(QueueSupervisorProcessRunner::class)->run($command);
        if ($exitCode !== 0) {
            throw new \RuntimeException('Cannot inspect queue workers: ' . $error);
        }

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
            $process = @proc_open(
                array_merge([$php], $workerArgs),
                [
                    0 => ['file', 'NUL', 'r'],
                    1 => ['file', $logBase . '.out.log', 'a'],
                    2 => ['file', $logBase . '.err.log', 'a'],
                ],
                $pipes,
                base_path(),
                null,
                [
                    'bypass_shell' => true,
                    'create_process_group' => true,
                    'create_new_console' => false,
                ]
            );

            if (!is_resource($process)) {
                Log::error('Queue worker process could not be started.', [
                    'pool' => $poolName,
                    'queues' => $queues,
                ]);

                return null;
            }

            $status = proc_get_status($process);
            $pid = (int) ($status['pid'] ?? 0);
            if (!($status['running'] ?? false) || $pid <= 0) {
                @proc_close($process);

                return null;
            }

            // Keep the process resource alive for the lifetime of the monitor.
            // This avoids a second Session-0 launcher and lets workers inherit the
            // same service context that successfully started the monitor.
            $this->windowsWorkerProcesses[$pid] = $process;
            $this->windowsWorkerStarts[$pid] = [
                'pool' => $poolName,
                'started_at' => microtime(true),
                'stable' => false,
            ];

            return $pid;
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

    private function reapFinishedWindowsWorkers(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        foreach ($this->windowsWorkerProcesses as $pid => $process) {
            $status = proc_get_status($process);
            $startup = $this->windowsWorkerStarts[$pid] ?? null;
            $age = $startup !== null ? microtime(true) - $startup['started_at'] : null;
            if ($status['running'] ?? false) {
                if ($startup !== null) {
                    try {
                        app(\App\Support\LandingCacheWorkerGuard::class)
                            ->stopOverdueChild($process, (int) $pid, $startup['started_at']);
                    } catch (\Throwable $exception) {
                        Log::warning('Landing cache worker inspection unavailable; process retained.', ['error' => $exception->getMessage()]);
                    }
                }
                if ($startup !== null && !$startup['stable'] && $age >= 15) {
                    $this->resetPoolLaunchBackoff($startup['pool']);
                    $this->windowsWorkerStarts[$pid]['stable'] = true;
                }
                continue;
            }

            @proc_close($process);
            unset($this->windowsWorkerProcesses[$pid], $this->windowsWorkerStarts[$pid]);
            if ($startup !== null) {
                $poolName = $startup['pool'];
                // Only remove this monitor's positively observed exited child.
                // No reservations are released and no live processes are killed.
                $key = $this->workerPidKey($poolName);
                $registered = Cache::get($key, []);
                if (is_array($registered)) {
                    unset($registered[$pid]);
                    if ($registered === []) {
                        Cache::forget($key);
                    } else {
                        Cache::put($key, $registered, now()->addHours(8));
                    }
                }
                $leaseKey = $this->workerLeaseKey($poolName);
                $lease = max(0, (int) Cache::get($leaseKey, 0) - 1);
                if ($lease > 0) {
                    Cache::put($leaseKey, $lease, now()->addSeconds(15));
                } else {
                    Cache::forget($leaseKey);
                }
                if (!$startup['stable'] && $age < 15 && (int) ($status['exitcode'] ?? -1) !== 0) {
                    $this->recordPoolLaunchFailure($poolName, time());
                }
            }
        }
    }

    private function workerLeaseKey(string $poolName): string
    {
        return 'queue:worker-pool:lease:' . sha1($poolName);
    }

    private function poolLaunchBackoffActive(string $poolName, int $now): bool
    {
        $state = Cache::get($this->poolLaunchBackoffKey($poolName), []);

        return is_array($state) && (int) ($state['next_retry_at'] ?? 0) > $now;
    }

    private function recordPoolLaunchFailure(string $poolName, int $now): void
    {
        $key = $this->poolLaunchBackoffKey($poolName);
        $state = Cache::get($key, []);
        $failures = max(0, (int) (is_array($state) ? ($state['failures'] ?? 0) : 0)) + 1;
        $delay = min(300, 10 * (2 ** min(5, $failures - 1)));
        Cache::put($key, [
            'failures' => $failures,
            'last_failure_at' => $now,
            'next_retry_at' => $now + $delay,
        ], now()->addHours(2));

        Log::warning('Queue worker pool launch entered adaptive backoff.', [
            'pool' => $poolName,
            'consecutive_failures' => $failures,
            'retry_in_seconds' => $delay,
        ]);
    }

    private function resetPoolLaunchBackoff(string $poolName): void
    {
        Cache::forget($this->poolLaunchBackoffKey($poolName));
    }

    private function poolLaunchBackoffKey(string $poolName): string
    {
        return 'queue:worker-pool:launch-backoff:' . sha1($poolName);
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

            // Looping heartbeats pause while a legitimate long job is running.
            // Never force-kill an exact live PID from heartbeat age alone.
            $alive[(string) $pid] = (int) $registeredAt;
        }

        if ($alive === []) {
            Cache::forget($key);
        } else {
            Cache::put($key, $alive, now()->addHours(8));
        }

        return count($alive);
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
        $dispatchLock = Cache::lock('queue:monitor:snapshot-maintenance-dispatch', 60);
        if (!$dispatchLock->get()) {
            return;
        }

        try {
            $importProgress = app(ImportProgressService::class);
            if ($importProgress->hasActiveProcessingJobs()) {
                return;
            }

            // Aggregate audits cannot detect every absent downstream snapshot.
            // Keep the existing source-aware freshness checks alive even when
            // the scheduler is offline or the aggregate audit queue is busy.
            $this->dispatchLatestSnapshotFreshnessChecks();

            if (Cache::has('queue:monitor:snapshot-audit-cooldown')) {
                return;
            }

            // A slow audit is still one audit. Do not queue another copy on
            // each monitor restart/interval while its predecessor is pending.
            if (DB::table('jobs')->where('queue', 'snapshots-parallel')
                ->where('payload', 'like', '%' . class_basename(AuditAndHealSnapshotsJob::class) . '%')
                ->exists()) {
                return;
            }

            $busySnapshotJobs = DB::table('jobs')->where('queue', 'snapshots-parallel')->count();
            if ($busySnapshotJobs > 5) {
                return;
            }

            AuditAndHealSnapshotsJob::dispatch();
            Cache::put('queue:monitor:snapshot-audit-cooldown', true, 300);

            if ($this->output) {
                $this->line("[" . now()->toDateTimeString() . "] Periodic snapshot audit & auto-heal dispatched.");
            }
        } catch (\Throwable $e) {
            Log::debug('Could not dispatch periodic snapshot audit: ' . $e->getMessage());
        } finally {
            $dispatchLock->release();
        }
    }

    private function dispatchLatestSnapshotFreshnessChecks(): void
    {
        foreach (LatestSnapshotRecoveryService::supportedSources() as $table) {
            $cooldownKey = 'queue:monitor:snapshot-freshness:' . $table;
            if (Cache::has($cooldownKey) || !Schema::hasTable($table)) {
                continue;
            }

            $periodColumn = LatestSnapshotRecoveryService::sourcePeriodColumn($table);
            if ($periodColumn === null || !Schema::hasColumn($table, $periodColumn)) {
                continue;
            }
            $period = StrictDateParser::normalize((string) DB::table($table)->max($periodColumn));
            if ($period === null) {
                continue;
            }

            // A reserved or deferred freshness job retains ownership. Leave it
            // to the queue retry policy; never release it or create a duplicate.
            if (DB::table('jobs')->whereIn('queue', ['snapshots-priority', 'snapshots-parallel'])
                ->where('payload', 'like', '%' . class_basename(EnsureImportedSnapshotsFreshJob::class) . '%')
                ->where('payload', 'like', '%' . $table . '%')
                ->where('payload', 'like', '%' . $period . '%')
                ->exists()) {
                continue;
            }

            try {
                EnsureImportedSnapshotsFreshJob::dispatch($table, $period, static::class)
                    ->onQueue('snapshots-priority');
                Cache::put($cooldownKey, true, 900);
            } catch (\Throwable $e) {
                Log::warning('Could not dispatch source snapshot freshness check.', [
                    'table' => $table,
                    'message' => $e->getMessage(),
                ]);
            }
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
            $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand', $encoded];
        } else {
            $command = ['pgrep', '-x', 'php'];
        }
        [$exitCode, $output, $error] = app(QueueSupervisorProcessRunner::class)->run($command);
        if ($exitCode !== 0 && !(PHP_OS_FAMILY !== 'Windows' && $exitCode === 1)) {
            throw new \RuntimeException('Cannot inspect worker PIDs: ' . $error);
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
        $normalized = strtolower(str_replace('\\', '/', $commandLine));
        $artisan = strtolower(str_replace('\\', '/', base_path('artisan')));

        return str_contains($normalized, $artisan)
            && (str_contains($normalized, 'queue:work') || str_contains($normalized, 'queue:listen'));
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
