<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

class QueueWorkerControlService
{
    public const COMMAND = 'php artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30';

    private const HEARTBEAT_TTL_SECONDS = 75;

    public function isEnabled(): bool
    {
        return (bool) Cache::get($this->key('enabled'), true);
    }

    public function enable(): array
    {
        $this->markEnabled();

        $alreadyActive = $this->isMonitorActive();
        $started = $alreadyActive || $this->startDetachedMonitor();

        return array_merge($this->status(), [
            'start_requested' => true,
            'started' => $started,
            'already_active' => $alreadyActive,
        ]);
    }

    public function markEnabled(): void
    {
        Cache::forever($this->key('enabled'), true);
    }

    public function disable(): array
    {
        Cache::forever($this->key('enabled'), false);

        // Laravel's restart signal is project/cache scoped. Workers finish the
        // current job and then exit; no unrelated PHP process is force-killed.
        Artisan::call('queue:restart');

        return array_merge($this->status(), [
            'stop_requested' => true,
        ]);
    }

    public function touchMonitorHeartbeat(): void
    {
        Cache::forget($this->key('launch-requested-at'));
        Cache::put($this->key('monitor-heartbeat'), [
            'pid' => getmypid(),
            'timestamp' => time(),
        ], now()->addSeconds(self::HEARTBEAT_TTL_SECONDS));
    }

    public function clearOwnMonitorHeartbeat(): void
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        if (is_array($heartbeat) && (int) ($heartbeat['pid'] ?? 0) === getmypid()) {
            Cache::forget($this->key('monitor-heartbeat'));
        }
    }

    public function isMonitorActive(): bool
    {
        $heartbeat = Cache::get($this->key('monitor-heartbeat'));
        if (is_array($heartbeat)
            && is_numeric($heartbeat['timestamp'] ?? null)
            && (time() - (int) $heartbeat['timestamp']) <= 45) {
            return true;
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

        $state = !$enabled
            ? ($workerCount > 0 || $monitorActive ? 'stopping' : 'stopped')
            : ($monitorActive ? 'active' : ($isStarting ? 'starting' : 'inactive'));

        return [
            'enabled' => $enabled,
            'monitor_active' => $monitorActive,
            'worker_count' => $workerCount,
            'state' => $state,
            'state_label' => match ($state) {
                'active' => 'Worker Monitor Aktif',
                'starting' => 'Worker Monitor Sedang Dimulai',
                'stopping' => 'Worker Sedang Dihentikan',
                'stopped' => 'Worker Dimatikan',
                default => 'Worker Monitor Tidak Aktif',
            },
            'tone' => match ($state) {
                'active' => 'success',
                'starting' => 'info',
                'stopping' => 'warning',
                'stopped' => 'secondary',
                default => 'danger',
            },
            'command' => self::COMMAND,
            'last_heartbeat_at' => $heartbeatAt !== null
                ? now()->setTimestamp($heartbeatAt)->toIso8601String()
                : null,
            'message' => $this->statusMessage($state, $workerCount),
        ];
    }

    private function statusMessage(string $state, int $workerCount): string
    {
        return match ($state) {
            'active' => "Monitor aktif dan mendeteksi {$workerCount} worker. Worker baru akan dijalankan saat antrean membutuhkannya.",
            'starting' => 'Proses monitor sudah dijalankan dan sedang menunggu heartbeat pertama dari server.',
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

    private function startDetachedMonitor(): bool
    {
        $lock = Cache::lock($this->key('start-lock'), 15);
        if (!$lock->get()) {
            return false;
        }

        try {
            if ($this->isMonitorActive()) {
                return true;
            }

            $php = (new PhpExecutableFinder())->find(false) ?: PHP_BINARY ?: 'php';
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
            ];

            if (PHP_OS_FAMILY === 'Windows') {
                $argumentLine = implode(' ', array_map([$this, 'windowsCommandLineQuote'], $arguments));
                $script = sprintf(
                    '$p = Start-Process -FilePath %s -ArgumentList %s -WorkingDirectory %s -WindowStyle Hidden -RedirectStandardOutput %s -RedirectStandardError %s -PassThru; $p.Id',
                    $this->powershellQuote($php),
                    $this->powershellQuote($argumentLine),
                    $this->powershellQuote(base_path()),
                    $this->powershellQuote($logPath),
                    $this->powershellQuote(storage_path('logs/queue-worker-monitor-error.log'))
                );
                $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
                $command = 'powershell.exe -NoProfile -NonInteractive -NoLogo -ExecutionPolicy Bypass -EncodedCommand '
                    . escapeshellarg($encoded) . ' 2>NUL';
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

            if (preg_match('/\b(\d+)\b/', (string) $output, $matches)) {
                Cache::put($this->key('monitor-launch-pid'), (int) $matches[1], now()->addHours(8));
            }

            Cache::put($this->key('launch-requested-at'), time(), now()->addSeconds(60));

            return true;
        } finally {
            $lock->release();
        }
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

        if ($cachedOutput !== null && (time() - $cachedAt) <= 5) {
            return $cachedOutput;
        }

        if (!function_exists('shell_exec')) {
            return null;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $script = '$ProgressPreference = \'SilentlyContinue\'; Get-CimInstance Win32_Process -Filter "Name = \'php.exe\'" -ErrorAction SilentlyContinue | ForEach-Object { $_.CommandLine }';
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $cachedOutput = shell_exec('powershell.exe -NoProfile -NonInteractive -NoLogo -ExecutionPolicy Bypass -EncodedCommand '
                . escapeshellarg($encoded) . ' 2>NUL');
            $cachedAt = time();

            return $cachedOutput;
        }

        $cachedOutput = shell_exec('ps -eo args 2>/dev/null');
        $cachedAt = time();

        return $cachedOutput;
    }

    private function key(string $suffix): string
    {
        $project = realpath(base_path()) ?: base_path();

        return 'queue:worker-control:' . sha1(strtolower(str_replace('\\', '/', $project))) . ':' . $suffix;
    }

    private function powershellQuote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function windowsCommandLineQuote(string $value): string
    {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }
}
