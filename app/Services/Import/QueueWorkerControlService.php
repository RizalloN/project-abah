<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Support\PhpCliBinary;
use Illuminate\Support\Facades\Log;

class QueueWorkerControlService
{
    public const COMMAND = 'php artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30';

    private const HEARTBEAT_TTL_SECONDS = 180;

    private const HEARTBEAT_ACTIVE_GRACE_SECONDS = 90;

    private const STARTUP_CONFIRM_TIMEOUT_MILLISECONDS = 5000;

    private const LAUNCH_REQUEST_GRACE_SECONDS = 20;

    private const AUTO_RECOVERY_MAX_BACKOFF_SECONDS = 300;

    private const RESTART_YIELD_TIMEOUT_SECONDS = 8;

    /** Longer than one scheduled watchdog cycle plus its launch backoff. */
    private const RESTART_HANDOVER_SECONDS = 150;

    public function isEnabled(): bool
    {
        if (Cache::has($this->key('enabled'))) {
            return (bool) Cache::get($this->key('enabled'));
        }

        $enabled = $this->readDurableEnabledState();
        Cache::forever($this->key('enabled'), $enabled);

        return $enabled;
    }

    public function enable(): array
    {
        $this->markEnabled();

        return $this->ensureMonitorRunning('manual', true);
    }

    public function ensureMonitorRunning(string $trigger = 'watchdog', bool $force = false): array
    {
        if (!$this->isEnabled()) {
            return array_merge($this->status(), [
                'start_requested' => false,
                'started' => false,
                'startup_confirmed' => false,
                'already_active' => false,
                'recovery_skipped' => 'disabled',
            ]);
        }

        $this->pruneStaleMonitorState();
        $alreadyActive = $this->isMonitorActive();
        if ($alreadyActive) {
            $this->resetRecoveryBackoff('healthy', $trigger);

            return array_merge($this->status(), [
                'start_requested' => true,
                'started' => true,
                'startup_confirmed' => true,
                'already_active' => true,
            ]);
        }

        $recovery = $this->recoveryState();
        $nextRetryAt = (int) ($recovery['next_retry_at'] ?? 0);
        if (!$force && $nextRetryAt > time()) {
            return array_merge($this->status(), [
                'start_requested' => false,
                'started' => false,
                'startup_confirmed' => false,
                'already_active' => false,
                'recovery_skipped' => 'backoff',
                'next_retry_at' => now()->setTimestamp($nextRetryAt)->toIso8601String(),
            ]);
        }

        $launchAccepted = $this->startDetachedMonitor();
        $startupConfirmed = $launchAccepted && $this->waitForMonitorStartup();

        if ($startupConfirmed) {
            $this->resetRecoveryBackoff('recovered', $trigger);
        } elseif (!$launchAccepted) {
            Cache::forget($this->key('launch-requested-at'));
            $this->recordRecoveryFailure($trigger, 'launcher_rejected');
        } else {
            $this->recordRecoveryPending($trigger);
        }

        return array_merge($this->status(), [
            'start_requested' => true,
            'started' => $launchAccepted,
            'startup_confirmed' => $startupConfirmed,
            'already_active' => false,
        ]);
    }

    public function markEnabled(): void
    {
        Cache::forever($this->key('enabled'), true);
        $this->writeDurableEnabledState(true);
        if (PHP_OS_FAMILY === 'Windows') {
            app(WindowsDetachedProcessLauncher::class)->setTaskEnabled(true);
        }
    }

    public function disable(): array
    {
        Cache::forever($this->key('enabled'), false);
        $this->writeDurableEnabledState(false);
        if (PHP_OS_FAMILY === 'Windows') {
            app(WindowsDetachedProcessLauncher::class)->setTaskEnabled(false);
        }

        // Laravel's restart signal is project/cache scoped. Workers finish the
        // current job and then exit; no unrelated PHP process is force-killed.
        Artisan::call('queue:restart');

        return array_merge($this->status(), [
            'stop_requested' => true,
        ]);
    }

    /**
     * Graceful restart: every worker finishes its current job, exits, and is
     * relaunched by a fresh monitor that loads the deployed code. Nothing is
     * force-killed, so an import in progress is never cut off midway.
     */
    public function restart(?string $requestedBy = null): array
    {
        if (!$this->isEnabled()) {
            return array_merge($this->status(), [
                'restart_requested' => false,
                'restart_rejected' => 'disabled',
            ]);
        }

        $lock = Cache::lock($this->key('restart-lock'), 30);
        if (!$lock->get()) {
            return array_merge($this->status(), [
                'restart_requested' => false,
                'restart_rejected' => 'in_progress',
            ]);
        }

        try {
            $previousMonitorPid = $this->monitorHeartbeatPid();
            Artisan::call('queue:restart');
            $signalAt = (int) Cache::get('illuminate:queue:restart', time());
            Cache::put($this->key('restart-request'), [
                'requested_at' => time(),
                'requested_by' => $requestedBy,
                'signal_at' => $signalAt,
                'previous_monitor_pid' => $previousMonitorPid,
            ], now()->addHours(12));

            if ($previousMonitorPid > 0 && $previousMonitorPid === getmypid()) {
                // The monitor restarting itself cannot wait for its own exit:
                // hand leadership over first, then let the caller's loop end.
                Cache::forget($this->key('monitor-heartbeat'));
                $launched = $this->startDetachedMonitor(true);
                $relaunch = ['started' => $launched, 'startup_confirmed' => $launched];
            } else {
                // The monitor checks the signal every second and releases its
                // heartbeat on exit; launch the replacement once it has yielded.
                $deadline = microtime(true) + self::RESTART_YIELD_TIMEOUT_SECONDS;
                while ($previousMonitorPid > 0
                    && $this->monitorHeartbeatPid() === $previousMonitorPid
                    && microtime(true) < $deadline) {
                    usleep(250000);
                }

                $relaunch = $this->monitorHeartbeatPid() === $previousMonitorPid && $previousMonitorPid > 0
                    ? ['started' => false, 'startup_confirmed' => false]
                    : $this->ensureMonitorRunning('manual-restart', true);
            }

            Log::warning('Queue workers restart requested from Job Management.', [
                'requested_by' => $requestedBy,
                'previous_monitor_pid' => $previousMonitorPid,
                'monitor_relaunched' => (bool) ($relaunch['startup_confirmed'] ?? false),
            ]);

            return array_merge($this->status(), [
                'restart_requested' => true,
                'monitor_relaunched' => (bool) ($relaunch['startup_confirmed'] ?? false),
            ]);
        } finally {
            $lock->release();
        }
    }

    /** @return array{requested_at:int,requested_by:?string,signal_at:int,previous_monitor_pid:int}|null */
    public function lastRestartRequest(): ?array
    {
        $request = Cache::get($this->key('restart-request'));

        return is_array($request) ? $request : null;
    }

    public function monitorHeartbeatPid(): int
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));

        return is_array($heartbeat) ? (int) ($heartbeat['pid'] ?? 0) : 0;
    }

    public function claimMonitorLeadership(): bool
    {
        $lock = Cache::lock($this->key('leader-election-lock'), 10);
        if (!$lock->get()) {
            return false;
        }

        try {
            return $this->touchMonitorHeartbeat();
        } finally {
            $lock->release();
        }
    }

    public function touchMonitorHeartbeat(): bool
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        $timestamp = is_array($heartbeat) && is_numeric($heartbeat['timestamp'] ?? null)
            ? (int) $heartbeat['timestamp']
            : 0;
        $ownerPid = is_array($heartbeat) ? (int) ($heartbeat['pid'] ?? 0) : 0;
        if ($ownerPid > 0
            && $ownerPid !== getmypid()
            && $timestamp <= (time() + 5)
            && (time() - $timestamp) <= self::HEARTBEAT_ACTIVE_GRACE_SECONDS) {
            return false;
        }

        Cache::forget($this->key('launch-requested-at'));
        Cache::put($this->key('monitor-heartbeat'), [
            'pid' => getmypid(),
            'timestamp' => time(),
        ], now()->addSeconds(self::HEARTBEAT_TTL_SECONDS));

        return true;
    }

    public function clearOwnMonitorHeartbeat(): void
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        if (is_array($heartbeat) && (int) ($heartbeat['pid'] ?? 0) === getmypid()) {
            Cache::forget($this->key('monitor-heartbeat'));
        }
    }

    public function signalDemand(string $queue): void
    {
        Cache::put($this->key('demand-signal'), [
            'queue' => trim($queue) !== '' ? trim($queue) : 'default',
            'timestamp' => time(),
        ], now()->addMinutes(2));
    }

    public function consumeDemandSignal(): bool
    {
        return Cache::pull($this->key('demand-signal')) !== null;
    }

    public function isMonitorActive(): bool
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        if (is_array($heartbeat) && is_numeric($heartbeat['timestamp'] ?? null)) {
            $age = time() - (int) $heartbeat['timestamp'];
            if ($age >= -5 && $age <= self::HEARTBEAT_ACTIVE_GRACE_SECONDS) {
                return true;
            }
        }

        return $this->monitorProcessCount() > 0;
    }

    public function status(): array
    {
        $enabled = $this->isEnabled();
        $monitorActive = $this->isMonitorActive();
        $workerCount = $this->freshWorkerCount();
        $launchRequestedAt = (int) Cache::get($this->key('launch-requested-at'), 0);
        $isStarting = $enabled && !$monitorActive
            && $launchRequestedAt > 0
            && (time() - $launchRequestedAt) <= 45;
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        $heartbeatAt = is_array($heartbeat) && is_numeric($heartbeat['timestamp'] ?? null)
            ? (int) $heartbeat['timestamp']
            : null;

        $restartRequest = $this->lastRestartRequest();
        // The old monitor exits before its replacement starts; within that
        // hand-over window an absent monitor is expected, not an outage.
        $isRestarting = $enabled && !$monitorActive && $restartRequest !== null
            && (time() - (int) ($restartRequest['requested_at'] ?? 0)) <= self::RESTART_HANDOVER_SECONDS;

        $state = !$enabled
            ? ($workerCount > 0 || $monitorActive ? 'stopping' : 'stopped')
            : ($monitorActive ? 'active' : ($isRestarting ? 'restarting' : ($isStarting ? 'starting' : 'inactive')));

        return [
            'enabled' => $enabled,
            'monitor_active' => $monitorActive,
            'worker_count' => $workerCount,
            'state' => $state,
            'state_label' => match ($state) {
                'active' => 'Worker Monitor Berjalan',
                'starting' => 'Worker Monitor Sedang Dimulai',
                'restarting' => 'Worker Sedang Direstart',
                'stopping' => 'Worker Sedang Dihentikan',
                'stopped' => 'Worker Dimatikan',
                default => 'Worker Monitor Tidak Aktif',
            },
            'tone' => match ($state) {
                'active' => 'success',
                'starting' => 'info',
                'restarting' => 'info',
                'stopping' => 'warning',
                'stopped' => 'secondary',
                default => 'danger',
            },
            'command' => self::COMMAND,
            'last_heartbeat_at' => $heartbeatAt !== null
                ? now()->setTimestamp($heartbeatAt)->toIso8601String()
                : null,
            'message' => $this->statusMessage($state, $workerCount),
            'recovery' => $this->recoveryState(),
        ];
    }

    private function statusMessage(string $state, int $workerCount): string
    {
        return match ($state) {
            'active' => $workerCount > 0
                ? "Monitor berjalan dan mendeteksi {$workerCount} worker aktif."
                : 'Monitor berjalan dan siap. Tidak ada worker anak karena antrean sedang kosong; worker akan otomatis dibuat saat ada job.',
            'starting' => 'Proses monitor sudah dijalankan dan sedang menunggu heartbeat pertama dari server.',
            'restarting' => 'Monitor lama sudah berhenti dengan aman. Monitor baru dengan kode terbaru sedang dijalankan (maksimal ±1 menit oleh watchdog).',
            'stopping' => 'Perintah berhenti sudah dikirim. Worker aktif akan selesai secara aman setelah job saat ini.',
            'stopped' => 'Worker monitor dimatikan. Job baru tetap masuk antrean tetapi tidak dijalankan otomatis.',
            default => 'Worker monitor belum aktif. Klik Aktifkan Worker untuk menjalankannya dari server.',
        };
    }

    private function freshWorkerCount(): int
    {
        $now = time();
        $pids = [];

        foreach ((array) config('queue.worker_pools', []) as $poolName => $pool) {
            $heartbeats = Cache::get('queue:worker-pool:heartbeats:' . sha1((string) $poolName), []);
            if (!is_array($heartbeats)) {
                continue;
            }

            foreach ($heartbeats as $pid => $timestamp) {
                if (is_numeric($timestamp) && ($now - (int) $timestamp) <= 15) {
                    $pids[(string) $pid] = true;
                }
            }
        }

        return max(count($pids), $this->workerProcessCount());
    }

    protected function startDetachedMonitor(bool $replacingCurrent = false): bool
    {
        $lock = Cache::lock($this->key('start-lock'), 15);
        if (!$lock->get()) {
            return false;
        }

        try {
            // A self-replacing monitor is itself the "active" process; only
            // the duplicate-launch guards apply to ordinary recovery.
            if (!$replacingCurrent && $this->isMonitorActive()) {
                return true;
            }

            $launchRequestedAt = (int) Cache::get($this->key('launch-requested-at'), 0);
            if (!$replacingCurrent && $launchRequestedAt > 0 && (time() - $launchRequestedAt) <= self::LAUNCH_REQUEST_GRACE_SECONDS) {
                return true;
            }

            $php = $this->resolvePhpCliBinary();
            if ($php === null) {
                Log::error('Queue worker monitor could not find a PHP CLI executable.');

                return false;
            }
            $artisan = base_path('artisan');
            $logPath = storage_path('logs/queue-worker-monitor.log');
            $arguments = [
                $artisan,
                'queue:ensure-running',
                '--timeout=0',
                '--memory=512',
                '--max-jobs=25',
                '--max-time=3600',
                '--check-interval=30',
                '--managed',
            ];

            if (PHP_OS_FAMILY === 'Windows') {
                $pid = app(WindowsDetachedProcessLauncher::class)->launch(
                    array_merge([$php], $arguments),
                    base_path(),
                    $logPath,
                    storage_path('logs/queue-worker-monitor-error.log'),
                    storage_path('logs/queue-worker-monitor-launcher-error.log')
                );
                if ($pid === null) {
                    return false;
                }

                if ($pid > 0) {
                    Cache::put($this->key('monitor-launch-pid'), $pid, now()->addHours(8));
                }
                Cache::put($this->key('launch-requested-at'), time(), now()->addSeconds(60));

                return true;
            } else {
                $command = sprintf(
                    'cd %s && ( %s > %s 2>&1 & echo $! )',
                    escapeshellarg(base_path()),
                    implode(' ', array_map('escapeshellarg', array_merge([$php], $arguments))),
                    escapeshellarg($logPath)
                );
            }

            if (!function_exists('popen')) {
                return false;
            }

            $process = @popen($command, 'r');
            if (!is_resource($process)) {
                return false;
            }

            $output = stream_get_contents($process);
            $exitCode = @pclose($process);
            if ($exitCode !== 0) {
                Log::warning('Queue worker monitor launcher exited with an error.', [
                    'exit_code' => $exitCode,
                ]);
                return false;
            }

            if (PHP_OS_FAMILY !== 'Windows' && preg_match('/\b(\d+)\b/', (string) $output, $matches)) {
                Cache::put($this->key('monitor-launch-pid'), (int) $matches[1], now()->addHours(8));
            }

            Cache::put($this->key('launch-requested-at'), time(), now()->addSeconds(60));

            return true;
        } finally {
            $lock->release();
        }
    }

    protected function waitForMonitorStartup(): bool
    {
        $deadline = microtime(true) + (self::STARTUP_CONFIRM_TIMEOUT_MILLISECONDS / 1000);

        do {
            if ($this->isMonitorActive()) {
                return true;
            }

            usleep(100000);
        } while (microtime(true) < $deadline);

        Log::error('Queue worker monitor launch was not confirmed by a heartbeat or live process.', [
            'timeout_ms' => self::STARTUP_CONFIRM_TIMEOUT_MILLISECONDS,
        ]);

        return false;
    }

    private function pruneStaleMonitorState(): void
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        $timestamp = is_array($heartbeat) && is_numeric($heartbeat['timestamp'] ?? null)
            ? (int) $heartbeat['timestamp']
            : 0;
        $invalidHeartbeat = !is_array($heartbeat)
            || $timestamp <= 0
            || $timestamp > (time() + 5)
            || (time() - $timestamp) > self::HEARTBEAT_ACTIVE_GRACE_SECONDS;

        if ($heartbeat !== null && $invalidHeartbeat && $this->monitorProcessCount() === 0) {
            Cache::forget($this->key('monitor-heartbeat'));
            Cache::forget($this->key('monitor-launch-pid'));
        }

        $launchRequestedAt = (int) Cache::get($this->key('launch-requested-at'), 0);
        if ($launchRequestedAt > 0 && (time() - $launchRequestedAt) > 60) {
            Cache::forget($this->key('launch-requested-at'));
        }
    }

    private function recordRecoveryFailure(string $trigger, string $reason): void
    {
        $state = $this->recoveryState();
        $failures = max(0, (int) ($state['consecutive_failures'] ?? 0)) + 1;
        $delay = min(self::AUTO_RECOVERY_MAX_BACKOFF_SECONDS, 15 * (2 ** min(4, $failures - 1)));
        Cache::put($this->key('recovery-state'), [
            'state' => 'backoff',
            'trigger' => $trigger,
            'reason' => $reason,
            'consecutive_failures' => $failures,
            'last_attempt_at' => time(),
            'next_retry_at' => time() + $delay,
        ], now()->addHours(8));
    }

    private function recordRecoveryPending(string $trigger): void
    {
        Cache::put($this->key('recovery-state'), [
            'state' => 'starting',
            'trigger' => $trigger,
            'reason' => 'awaiting_heartbeat',
            'consecutive_failures' => (int) ($this->recoveryState()['consecutive_failures'] ?? 0),
            'last_attempt_at' => time(),
            'next_retry_at' => time() + self::LAUNCH_REQUEST_GRACE_SECONDS,
        ], now()->addHours(8));
    }

    private function resetRecoveryBackoff(string $state, string $trigger): void
    {
        Cache::put($this->key('recovery-state'), [
            'state' => $state,
            'trigger' => $trigger,
            'reason' => null,
            'consecutive_failures' => 0,
            'last_attempt_at' => time(),
            'next_retry_at' => 0,
        ], now()->addHours(8));
    }

    private function recoveryState(): array
    {
        $state = Cache::get($this->key('recovery-state'), []);

        return is_array($state) ? $state : [];
    }

    private function readDurableEnabledState(): bool
    {
        $path = $this->durableControlStatePath();
        if (!is_file($path)) {
            return true;
        }

        $state = json_decode((string) @file_get_contents($path), true);

        return !is_array($state) || !array_key_exists('enabled', $state)
            ? true
            : (bool) $state['enabled'];
    }

    private function writeDurableEnabledState(bool $enabled): void
    {
        $path = $this->durableControlStatePath();
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            Log::error('Queue worker durable control directory could not be created.', ['path' => $directory]);
            return;
        }

        $temporaryPath = $path . '.' . getmypid() . '.tmp';
        $payload = json_encode([
            'enabled' => $enabled,
            'updated_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($temporaryPath, $payload, LOCK_EX) === false || !@rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            Log::error('Queue worker durable control state could not be written.', ['path' => $path]);
        }
    }

    private function durableControlStatePath(): string
    {
        if (app()->environment('testing')) {
            return storage_path('framework/testing/queue-worker-control-' . getmypid() . '.json');
        }

        return storage_path('framework/queue-worker-control.json');
    }

    private function resolvePhpCliBinary(): ?string
    {
        return $this->firstUsablePhpCliBinary([...PhpCliBinary::candidates(), 'php']);
    }

    private function firstUsablePhpCliBinary(array $candidates): ?string
    {
        return PhpCliBinary::firstUsable($candidates);
    }

    private function monitorProcessCount(): int
    {
        $output = $this->phpProcessCommandLines();
        if ($output === null || $output === '') {
            return 0;
        }

        $artisan = strtolower(str_replace('\\', '/', base_path('artisan')));
        $count = 0;
        foreach (preg_split('/\R+/', trim($output)) ?: [] as $commandLine) {
            $normalized = strtolower(str_replace('\\', '/', (string) preg_replace('/\s+/', ' ', $commandLine)));
            if (str_contains($normalized, 'queue:ensure-running')
                && str_contains($normalized, $artisan)) {
                $count++;
            }
        }

        return $count;
    }

    private function workerProcessCount(): int
    {
        $output = $this->phpProcessCommandLines();
        if ($output === null || $output === '') {
            return 0;
        }

        $artisan = strtolower(str_replace('\\', '/', base_path('artisan')));
        $count = 0;
        foreach (preg_split('/\R+/', trim($output)) ?: [] as $commandLine) {
            $normalized = strtolower(str_replace('\\', '/', (string) preg_replace('/\s+/', ' ', $commandLine)));
            if ((str_contains($normalized, 'queue:work') || str_contains($normalized, 'queue:listen'))
                && str_contains($normalized, $artisan)) {
                $count++;
            }
        }

        return $count;
    }

    private function phpProcessCommandLines(): ?string
    {
        static $cachedOutput = null;
        static $cachedAt = 0;

        if ($cachedAt > 0 && (time() - $cachedAt) <= 5) {
            return $cachedOutput;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $script = '$ErrorActionPreference = \'Stop\'; Get-CimInstance Win32_Process -Filter "Name = \'php.exe\'" | ForEach-Object { $_.CommandLine }';
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand', $encoded];
        } else {
            $command = ['ps', '-eo', 'args'];
        }
        try {
            [$exitCode, $output] = app(QueueSupervisorProcessRunner::class)->run($command);
            $cachedOutput = $exitCode === 0 ? $output : null;
        } catch (\Throwable) {
            $cachedOutput = null;
        }
        $cachedAt = time();

        return $cachedOutput;
    }

    private function key(string $suffix): string
    {
        $project = realpath(base_path()) ?: base_path();

        return 'queue:worker-control:' . sha1(strtolower(str_replace('\\', '/', $project))) . ':' . $suffix;
    }

}
