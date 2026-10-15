<?php

namespace App\Services\Import;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only telemetry for the Job Management worker panel. Every value comes
 * from state the monitor and workers already publish (cache heartbeats, PID
 * registry, the jobs table) plus one OS process listing; nothing is repaired
 * or relaunched from here.
 */
class QueueWorkerMonitorService
{
    /** Workers emit a Looping heartbeat at most every 5s while between jobs. */
    private const IDLE_HEARTBEAT_SECONDS = 15;

    /** Ready jobs older than this without a live worker indicate a stuck pool. */
    private const STALLED_READY_SECONDS = 120;

    private const FAILED_JOBS_SAMPLE = 200;

    private const FAILED_JOBS_WINDOW_DAYS = 7;

    private const PROCESS_TABLE_CACHE_KEY = 'queue:worker-monitor:process-table:v1';

    private const PROCESS_TABLE_CACHE_SECONDS = 5;

    public function __construct(
        private readonly QueueWorkerControlService $workerControl,
        private readonly QueueSupervisorProcessRunner $processRunner
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $now = time();
        $processes = $this->sharedProcessTable();
        $signalAt = (int) Cache::get('illuminate:queue:restart', 0);
        $queueStats = $this->queueStats($now);

        $pools = [];
        foreach ((array) config('queue.worker_pools', []) as $poolName => $pool) {
            $pools[] = $this->poolSnapshot((string) $poolName, (array) $pool, $processes, $queueStats, $signalAt, $now);
        }

        $monitor = $this->monitorSnapshot($processes, $signalAt, $now);
        $workers = collect($pools)->flatMap(fn (array $pool): array => $pool['workers']);

        return [
            'generated_at' => now()->toIso8601String(),
            'process_details_available' => $processes !== null,
            'monitor' => $monitor,
            'restart' => $this->restartSnapshot($monitor, $workers->all(), $signalAt, $now),
            'totals' => [
                'live_workers' => collect($pools)->sum('live_workers'),
                'capacity' => collect($pools)->sum('capacity'),
                'busy_workers' => $workers->where('activity', 'busy')->count(),
                'ready_jobs' => collect($pools)->sum('ready_jobs'),
                'reserved_jobs' => collect($pools)->sum('reserved_jobs'),
                'delayed_jobs' => collect($pools)->sum('delayed_jobs'),
                'stalled_pools' => collect($pools)->where('state', 'stalled')->count(),
                'outdated_workers' => $workers->where('outdated', true)->count(),
            ],
            'pools' => $pools,
            'failed_jobs' => $this->failedJobsSummary(),
        ];
    }

    /**
     * Start time of the oldest live monitor/worker process, or null when the
     * OS listing is unavailable (code-change detection then stays inactive).
     */
    public function oldestLiveProcessStartedAt(): ?int
    {
        $processes = $this->sharedProcessTable();
        if ($processes === null) {
            return null;
        }

        $pids = [$this->workerControl->monitorHeartbeatPid()];
        foreach ((array) config('queue.worker_pools', []) as $poolName => $pool) {
            $hash = sha1((string) $poolName);
            $pids = array_merge(
                $pids,
                array_keys($this->intMap(Cache::get('queue:worker-pool:pids:'.$hash, []))),
                array_keys($this->intMap(Cache::get('queue:worker-pool:heartbeats:'.$hash, [])))
            );
        }

        $startTimes = [];
        foreach (array_unique($pids) as $pid) {
            if ($pid > 0 && isset($processes[$pid]['started_at'])) {
                $startTimes[] = (int) $processes[$pid]['started_at'];
            }
        }

        return $startTimes !== [] ? min($startTimes) : null;
    }

    /**
     * @param  array<int, array{started_at:?int,memory_mb:?float}>|null  $processes
     * @param  array<string, array<string, mixed>>  $queueStats
     * @return array<string, mixed>
     */
    private function poolSnapshot(string $poolName, array $pool, ?array $processes, array $queueStats, int $signalAt, int $now): array
    {
        $queues = $this->queueNames((string) ($pool['queues'] ?? 'default'));
        $capacity = max(0, (int) ($pool['workers'] ?? 0));
        $hash = sha1($poolName);
        $heartbeats = $this->intMap(Cache::get('queue:worker-pool:heartbeats:'.$hash, []));
        $registered = $this->intMap(Cache::get('queue:worker-pool:pids:'.$hash, []));

        $workers = [];
        foreach (array_unique(array_merge(array_keys($registered), array_keys($heartbeats))) as $pid) {
            $heartbeatAt = $heartbeats[$pid] ?? null;
            $heartbeatAge = $heartbeatAt !== null ? max(0, $now - $heartbeatAt) : null;
            if ($processes !== null) {
                if (!isset($processes[$pid])) {
                    continue;
                }
            } elseif ($heartbeatAge === null || $heartbeatAge > 30) {
                // Without an OS listing only a fresh heartbeat proves liveness.
                continue;
            }

            $process = $processes[$pid] ?? null;
            $startedAt = $process['started_at'] ?? null;
            $workers[] = [
                'pid' => $pid,
                'started_at' => $startedAt !== null ? now()->setTimestamp($startedAt)->toIso8601String() : null,
                'uptime_seconds' => $startedAt !== null ? max(0, $now - $startedAt) : null,
                'memory_mb' => $process['memory_mb'] ?? null,
                'last_heartbeat_age_seconds' => $heartbeatAge,
                // Looping only fires between jobs; a silent live worker is busy.
                'activity' => $heartbeatAge !== null && $heartbeatAge <= self::IDLE_HEARTBEAT_SECONDS ? 'idle' : 'busy',
                'outdated' => $startedAt !== null && $signalAt > 0 && $startedAt < $signalAt,
            ];
        }
        usort($workers, static fn (array $a, array $b): int => $a['pid'] <=> $b['pid']);

        $stats = ['ready' => 0, 'delayed' => 0, 'reserved' => 0, 'stale_reserved' => 0, 'oldest_ready_at' => null, 'reserved_jobs' => []];
        foreach ($queues as $queue) {
            $queueStat = $queueStats[$queue] ?? null;
            if ($queueStat === null) {
                continue;
            }
            foreach (['ready', 'delayed', 'reserved', 'stale_reserved'] as $metric) {
                $stats[$metric] += (int) $queueStat[$metric];
            }
            if ($queueStat['oldest_ready_at'] !== null) {
                $stats['oldest_ready_at'] = min($stats['oldest_ready_at'] ?? PHP_INT_MAX, (int) $queueStat['oldest_ready_at']);
            }
            $stats['reserved_jobs'] = array_merge($stats['reserved_jobs'], $queueStat['reserved_jobs']);
        }

        $oldestReadyAge = $stats['oldest_ready_at'] !== null ? max(0, $now - $stats['oldest_ready_at']) : null;
        $backoff = Cache::get('queue:worker-pool:launch-backoff:'.$hash);
        $backoffActive = is_array($backoff) && (int) ($backoff['next_retry_at'] ?? 0) > $now;
        $liveWorkers = count($workers);

        $state = match (true) {
            $backoffActive => 'backoff',
            $stats['ready'] > 0 && $liveWorkers === 0 && ($oldestReadyAge ?? 0) >= self::STALLED_READY_SECONDS => 'stalled',
            $stats['ready'] > 0 && $liveWorkers === 0 => 'waiting',
            $liveWorkers > 0 => 'running',
            default => 'idle',
        };

        return [
            'name' => $poolName,
            'queues' => $queues,
            'capacity' => $capacity,
            'live_workers' => $liveWorkers,
            'ready_jobs' => $stats['ready'],
            'delayed_jobs' => $stats['delayed'],
            'reserved_jobs' => $stats['reserved'],
            'stale_reserved_jobs' => $stats['stale_reserved'],
            'oldest_ready_age_seconds' => $oldestReadyAge,
            'running_jobs' => array_slice($stats['reserved_jobs'], 0, 5),
            'state' => $state,
            'state_label' => match ($state) {
                'backoff' => 'Cooldown Launch',
                'stalled' => 'Macet',
                'waiting' => 'Menunggu Worker',
                'running' => 'Berjalan',
                default => 'Idle',
            },
            'tone' => match ($state) {
                'backoff' => 'warning',
                'stalled' => 'danger',
                'waiting' => 'info',
                'running' => 'success',
                default => 'muted',
            },
            'backoff' => $backoffActive ? [
                'failures' => (int) ($backoff['failures'] ?? 0),
                'next_retry_at' => now()->setTimestamp((int) $backoff['next_retry_at'])->toIso8601String(),
            ] : null,
            'workers' => $workers,
        ];
    }

    /**
     * @param  array<int, array{started_at:?int,memory_mb:?float}>|null  $processes
     * @return array<string, mixed>
     */
    private function monitorSnapshot(?array $processes, int $signalAt, int $now): array
    {
        $status = $this->workerControl->status();
        $pid = $this->workerControl->monitorHeartbeatPid();
        $heartbeatAt = isset($status['last_heartbeat_at']) && $status['last_heartbeat_at'] !== null
            ? strtotime((string) $status['last_heartbeat_at'])
            : null;
        $process = $pid > 0 ? ($processes[$pid] ?? null) : null;
        $startedAt = $process['started_at'] ?? null;

        return [
            'pid' => $pid > 0 ? $pid : null,
            'active' => (bool) ($status['monitor_active'] ?? false),
            'heartbeat_age_seconds' => $heartbeatAt !== null && $heartbeatAt !== false ? max(0, $now - $heartbeatAt) : null,
            'started_at' => $startedAt !== null ? now()->setTimestamp($startedAt)->toIso8601String() : null,
            'uptime_seconds' => $startedAt !== null ? max(0, $now - $startedAt) : null,
            'memory_mb' => $process['memory_mb'] ?? null,
            'outdated' => $startedAt !== null && $signalAt > 0 && $startedAt < $signalAt,
            'recovery' => $status['recovery'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $monitor
     * @param  array<int, array<string, mixed>>  $workers
     * @return array<string, mixed>
     */
    private function restartSnapshot(array $monitor, array $workers, int $signalAt, int $now): array
    {
        $request = $this->workerControl->lastRestartRequest();
        $base = [
            'signal_at' => $signalAt > 0 ? now()->setTimestamp($signalAt)->toIso8601String() : null,
            'requested_at' => null,
            'requested_by' => null,
            'pending_workers' => [],
        ];
        if ($request === null || ($now - (int) ($request['requested_at'] ?? 0)) > 6 * 3600) {
            return $base + ['state' => 'idle', 'message' => null];
        }

        $pending = array_values(array_filter($workers, static fn (array $worker): bool => (bool) $worker['outdated']));
        $monitorReplaced = (bool) $monitor['active']
            && (int) ($monitor['pid'] ?? 0) !== (int) ($request['previous_monitor_pid'] ?? 0)
            && !$monitor['outdated'];
        $elapsed = max(0, $now - (int) $request['requested_at']);

        $state = match (true) {
            $monitorReplaced && $pending === [] => 'completed',
            $pending !== [] && $elapsed > 900 => 'waiting_long_job',
            default => 'restarting',
        };

        return array_merge($base, [
            'requested_at' => now()->setTimestamp((int) $request['requested_at'])->toIso8601String(),
            'requested_by' => $request['requested_by'] ?? null,
            'pending_workers' => array_map(static fn (array $worker): int => (int) $worker['pid'], $pending),
            'state' => $state,
            'message' => match ($state) {
                'completed' => 'Restart selesai. Monitor dan seluruh worker sudah memakai kode terbaru.',
                'waiting_long_job' => count($pending).' worker lama masih menyelesaikan job panjang. Worker akan berhenti sendiri setelah job selesai; tidak ada proses yang dimatikan paksa.',
                default => $pending !== []
                    ? 'Restart berjalan: '.count($pending).' worker lama menyelesaikan job aktif sebelum diganti.'
                    : 'Restart berjalan: menunggu monitor baru aktif.',
            },
        ]);
    }

    /** @return array<string, array<string, mixed>> */
    private function queueStats(int $now): array
    {
        if (!Schema::hasTable('jobs')) {
            return [];
        }

        $staleCutoff = $now - max(60, (int) config('queue.connections.database.retry_after', 90));
        $stats = [];
        $rows = DB::table('jobs')
            ->select('queue')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) as ready', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) as delayed_count', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) as reserved')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL AND reserved_at <= ? THEN 1 ELSE 0 END) as stale_reserved', [$staleCutoff])
            ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) as oldest_ready_at', [$now])
            ->groupBy('queue')
            ->get();
        foreach ($rows as $row) {
            $stats[(string) $row->queue] = [
                'ready' => (int) $row->ready,
                'delayed' => (int) $row->delayed_count,
                'reserved' => (int) $row->reserved,
                'stale_reserved' => (int) $row->stale_reserved,
                'oldest_ready_at' => $row->oldest_ready_at !== null ? (int) $row->oldest_ready_at : null,
                'reserved_jobs' => [],
            ];
        }

        $reserved = DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->orderBy('reserved_at')
            ->limit(50)
            ->get(['id', 'queue', 'reserved_at', 'attempts', 'payload']);
        foreach ($reserved as $job) {
            $payload = json_decode((string) $job->payload, true);
            $stats[(string) $job->queue]['reserved_jobs'][] = [
                'id' => (int) $job->id,
                'job' => class_basename((string) (is_array($payload) ? ($payload['displayName'] ?? 'Job') : 'Job')),
                'running_seconds' => max(0, $now - (int) $job->reserved_at),
                'attempts' => (int) $job->attempts,
            ];
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    private function failedJobsSummary(): array
    {
        return Cache::remember('queue:worker-monitor:failed-jobs:v2', 30, function (): array {
            if (!Schema::hasTable('failed_jobs')) {
                return ['available' => false, 'last_hour' => 0, 'last_24h' => 0, 'last_7d' => 0, 'last_24h_capped' => false, 'latest' => [], 'groups' => [], 'last_failed_at' => null];
            }

            // failed_jobs has no failed_at index and large payloads; the newest
            // rows by primary key are enough for an operational signal.
            $rows = DB::table('failed_jobs')
                ->orderByDesc('id')
                ->limit(self::FAILED_JOBS_SAMPLE)
                ->get(['id', 'queue', 'failed_at', DB::raw('SUBSTRING(exception, 1, 400) as exception_head'), DB::raw('SUBSTRING(payload, 1, 400) as payload_head')]);

            $hourAgo = now()->subHour();
            $dayAgo = now()->subDay();
            $weekAgo = now()->subDays(self::FAILED_JOBS_WINDOW_DAYS);
            $failedSince = fn (Carbon $since) => $rows->filter(fn ($row): bool => $row->failed_at !== null && Carbon::parse($row->failed_at)->greaterThanOrEqualTo($since));
            $inLastDay = $failedSince($dayAgo);
            // Old failures are history, not current worker health.
            $recent = $failedSince($weekAgo)->map(function ($row): array {
                preg_match('/"displayName"\s*:\s*"([^"]+)"/', (string) $row->payload_head, $name);
                $error = trim((string) strtok((string) $row->exception_head, "\n"));
                $error = (string) preg_replace('/ in [A-Za-z]:[\\\\\/].*$| in \/.*$/', '', $error);

                return [
                    'id' => (int) $row->id,
                    'queue' => (string) $row->queue,
                    'job' => class_basename(stripslashes((string) ($name[1] ?? 'Job'))),
                    'failed_at' => $row->failed_at !== null ? Carbon::parse($row->failed_at)->toIso8601String() : null,
                    'error' => mb_strimwidth($error, 0, 220, '…'),
                ];
            })->values();

            return [
                'available' => true,
                'last_hour' => $failedSince($hourAgo)->count(),
                'last_24h' => $inLastDay->count(),
                'last_7d' => $recent->count(),
                'last_24h_capped' => $inLastDay->count() >= self::FAILED_JOBS_SAMPLE,
                'window_days' => self::FAILED_JOBS_WINDOW_DAYS,
                'last_failed_at' => $rows->first()?->failed_at !== null ? Carbon::parse($rows->first()->failed_at)->toIso8601String() : null,
                'groups' => $recent->groupBy(fn (array $job): string => $job['job'].'|'.$job['queue'])
                    ->map(fn ($jobs): array => [
                        'job' => $jobs->first()['job'],
                        'queue' => $jobs->first()['queue'],
                        'count' => $jobs->count(),
                        'last_failed_at' => $jobs->first()['failed_at'],
                        'error' => $jobs->first()['error'],
                    ])
                    ->sortByDesc('count')->values()->all(),
                'latest' => $recent->take(5)->all(),
            ];
        });
    }

    /**
     * Process listing shared for a few seconds. On Windows each listing spawns
     * PowerShell (~0.5-1s, tens of MB), and one job-management poll needs it
     * twice (snapshot + safe-restart code-change check).
     *
     * @return array<int, array{started_at:?int,memory_mb:?float}>|null
     */
    private function sharedProcessTable(): ?array
    {
        // The console monitor loop decides restarts from this listing and
        // must always see live processes; only UI polling is shared.
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return $this->processTable();
        }

        $cached = Cache::get(self::PROCESS_TABLE_CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $processes = $this->processTable();
        if ($processes !== null) {
            Cache::put(self::PROCESS_TABLE_CACHE_KEY, $processes, self::PROCESS_TABLE_CACHE_SECONDS);
        }

        return $processes;
    }

    /**
     * PID => start time and memory for PHP processes, or null when the OS
     * listing is unavailable (callers then fall back to heartbeats only).
     *
     * @return array<int, array{started_at:?int,memory_mb:?float}>|null
     */
    protected function processTable(): ?array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $script = '$ErrorActionPreference = \'Stop\'; '
                .'Get-CimInstance Win32_Process -Filter "Name = \'php.exe\'" | ForEach-Object { '
                .'"{0}|{1}|{2}" -f $_.ProcessId, ([DateTimeOffset]$_.CreationDate).ToUnixTimeSeconds(), $_.WorkingSetSize }';
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $command = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand', $encoded];
        } else {
            $command = ['ps', '-C', 'php', '-o', 'pid=,etimes=,rss='];
        }

        try {
            [$exitCode, $output] = $this->processRunner->run($command);
        } catch (Throwable) {
            return null;
        }
        // ps exits 1 when no process matches; that is an empty list, not a failure.
        if ($exitCode !== 0 && !(PHP_OS_FAMILY !== 'Windows' && $exitCode === 1)) {
            return null;
        }

        $now = time();
        $processes = [];
        foreach (preg_split('/\R+/', trim((string) $output)) ?: [] as $line) {
            $parts = PHP_OS_FAMILY === 'Windows'
                ? explode('|', trim($line))
                : preg_split('/\s+/', trim($line));
            if (count($parts) < 3 || !ctype_digit((string) $parts[0])) {
                continue;
            }
            $processes[(int) $parts[0]] = PHP_OS_FAMILY === 'Windows'
                ? ['started_at' => (int) $parts[1], 'memory_mb' => round(((float) $parts[2]) / 1048576, 1)]
                : ['started_at' => $now - (int) $parts[1], 'memory_mb' => round(((float) $parts[2]) / 1024, 1)];
        }

        return $processes;
    }

    /** @return array<int, int> */
    private function intMap(mixed $value): array
    {
        $map = [];
        foreach (is_array($value) ? $value : [] as $pid => $timestamp) {
            if ((int) $pid > 0 && is_numeric($timestamp)) {
                $map[(int) $pid] = (int) $timestamp;
            }
        }

        return $map;
    }

    /** @return array<int, string> */
    private function queueNames(string $queues): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $queues)))));

        return $names !== [] ? $names : ['default'];
    }
}
