<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shares one worker budget across all queue pools instead of letting every
 * pool grow to its own maximum at the same time.
 *
 * - Global cap: the configured maximum, lowered when free RAM would not
 *   survive another worker reaching its memory limit.
 * - Priority: imports before snapshot priority work, before background,
 *   bulk snapshots, shadow backfill and light refresh queues.
 * - Fairness: every pool with waiting work gets one worker before any pool
 *   gets a second; critical pools always get their first worker.
 * - Running workers are never stopped by the plan; it only limits launches.
 *
 * The planner is advisory and fail-safe: any error returns the static pool
 * capacities, which is exactly the previous behaviour.
 */
class WorkerCapacityPlanner
{
    public const PLAN_CACHE_KEY = 'queue:worker-planner:last-plan:v1';
    private const MEMORY_CACHE_KEY = 'queue:worker-planner:free-memory-mb';
    private const MEMORY_CACHE_SECONDS = 15;
    private const DEFAULT_PRIORITY = 50;

    public function __construct(private readonly QueueSupervisorProcessRunner $processRunner)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('queue.worker_planner.enabled', true);
    }

    /**
     * @param array<string, array{queues: string, workers: int}> $pools
     * @return array<string, int> pool => allowed total workers
     */
    public function targets(array $pools): array
    {
        $static = array_map(static fn (array $pool): int => max(1, (int) $pool['workers']), $pools);
        if (!$this->enabled() || $pools === []) {
            return $static;
        }

        try {
            $plan = $this->plan($pools);
            Cache::put(self::PLAN_CACHE_KEY, $plan, now()->addMinutes(5));

            return $plan['targets'] + $static;
        } catch (Throwable $e) {
            Log::warning('Worker capacity planner failed; using static pool capacity.', ['message' => $e->getMessage()]);

            return $static;
        }
    }

    /**
     * @param array<string, array{queues: string, workers: int}> $pools
     */
    public function plan(array $pools): array
    {
        $now = time();
        $queueLoad = $this->queueLoad($now);
        $definitions = (array) config('queue.worker_pools', []);
        $poolState = [];

        foreach ($pools as $name => $pool) {
            $queues = array_values(array_filter(array_map('trim', explode(',', (string) $pool['queues']))));
            $ready = 0;
            $reserved = 0;
            foreach ($queues as $queue) {
                $ready += (int) ($queueLoad[$queue]['ready'] ?? 0);
                $reserved += (int) ($queueLoad[$queue]['reserved'] ?? 0);
            }

            $definition = (array) ($definitions[$name] ?? []);
            $poolState[$name] = [
                'capacity' => max(1, (int) $pool['workers']),
                'priority' => (int) ($definition['priority'] ?? self::DEFAULT_PRIORITY),
                'critical' => (bool) ($definition['critical'] ?? false),
                'ready' => $ready,
                'reserved' => $reserved,
                'active' => max($this->freshHeartbeats($name, $now), $reserved),
            ];
        }

        $freeMemoryMb = $this->freeMemoryMb();
        $perWorkerMb = max(64, (int) config('queue.worker_planner.worker_memory_estimate_mb', (int) config('queue.worker_memory', 512)));
        $reserveMb = max(0, (int) config('queue.worker_planner.memory_reserve_mb', 2048));
        $maxTotal = max(1, (int) config('queue.worker_planner.max_total_workers', 12));
        $active = array_sum(array_column($poolState, 'active'));
        $memoryCap = $freeMemoryMb === null ? null : $active + intdiv(max(0, $freeMemoryMb - $reserveMb), $perWorkerMb);
        $globalCap = $memoryCap === null ? $maxTotal : min($maxTotal, $memoryCap);

        $allocation = $this->allocate($poolState, $globalCap);
        $limitedBy = $memoryCap !== null && $memoryCap < $maxTotal ? 'memory' : 'global_cap';

        $decisions = [];
        foreach ($poolState as $name => $state) {
            $demand = min($state['capacity'], $state['ready'] + $state['reserved']);
            $target = $allocation[$name];
            $decisions[$name] = $state + [
                'demand' => $demand,
                'target' => $target,
                'throttled' => $demand > $target,
                'limited_by' => $demand > $target ? $limitedBy : ($demand >= $state['capacity'] && $state['ready'] > 0 ? 'pool_capacity' : null),
            ];
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'global_cap' => $globalCap,
            'max_total_workers' => $maxTotal,
            'active_workers' => $active,
            'free_memory_mb' => $freeMemoryMb,
            'memory_reserve_mb' => $reserveMb,
            'worker_memory_estimate_mb' => $perWorkerMb,
            'memory_cap' => $memoryCap,
            'targets' => array_map(static fn (array $decision): int => $decision['target'], $decisions),
            'decisions' => $decisions,
        ];
    }

    /**
     * Pure allocation used by plan() and covered by unit tests.
     *
     * @param array<string, array{capacity: int, priority: int, critical: bool, ready: int, reserved: int, active: int}> $pools
     * @return array<string, int> pool => allowed total workers
     */
    public function allocate(array $pools, int $globalCap): array
    {
        uksort($pools, static fn (string $a, string $b): int => [$pools[$a]['priority'], $a] <=> [$pools[$b]['priority'], $b]);

        $targets = [];
        $demand = [];
        foreach ($pools as $name => $pool) {
            // Running workers keep their slot; the plan never asks one to stop.
            $targets[$name] = min($pool['capacity'], max(0, $pool['active']));
            $demand[$name] = min($pool['capacity'], max(0, $pool['ready']) + max(0, $pool['reserved']));
        }

        $remaining = $globalCap - array_sum($targets);

        // Pass 1 — fairness: one worker for every waiting pool, by priority.
        // Critical pools (imports) get it even when the budget is exhausted.
        foreach ($pools as $name => $pool) {
            if ($targets[$name] > 0 || $demand[$name] === 0) {
                continue;
            }
            if ($remaining > 0 || $pool['critical']) {
                $targets[$name] = 1;
                $remaining--;
            }
        }

        // Pass 2 — throughput: fill remaining demand in priority order.
        foreach (array_keys($pools) as $name) {
            if ($remaining <= 0) {
                break;
            }
            $extra = min(max(0, $demand[$name] - $targets[$name]), $remaining);
            $targets[$name] += $extra;
            $remaining -= $extra;
        }

        // Idle pools keep their configured capacity; they launch nothing.
        foreach ($pools as $name => $pool) {
            if ($demand[$name] === 0) {
                $targets[$name] = $pool['capacity'];
            }
        }

        return $targets;
    }

    public function lastPlan(): ?array
    {
        $plan = Cache::get(self::PLAN_CACHE_KEY);

        return is_array($plan) ? $plan : null;
    }

    /**
     * Free physical memory in MB, or null when the OS cannot be queried.
     */
    public function freeMemoryMb(): ?int
    {
        $cached = Cache::get(self::MEMORY_CACHE_KEY);
        if (is_int($cached)) {
            return $cached;
        }

        $free = null;
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $script = '(Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory';
                $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
                [$exitCode, $output] = $this->processRunner->run(['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-EncodedCommand', $encoded]);
                if ($exitCode === 0 && is_numeric(trim((string) $output))) {
                    $free = intdiv((int) trim((string) $output), 1024);
                }
            } elseif (is_readable('/proc/meminfo') && preg_match('/^MemAvailable:\s+(\d+)\s+kB/m', (string) file_get_contents('/proc/meminfo'), $matches) === 1) {
                $free = intdiv((int) $matches[1], 1024);
            }
        } catch (Throwable) {
            $free = null;
        }

        if ($free !== null) {
            Cache::put(self::MEMORY_CACHE_KEY, $free, self::MEMORY_CACHE_SECONDS);
        }

        return $free;
    }

    /**
     * @return array<string, array{ready: int, reserved: int}>
     */
    private function queueLoad(int $now): array
    {
        $retryAfter = max(30, (int) config('queue.connections.' . config('queue.default', 'database') . '.retry_after', 90));
        $staleCutoff = $now - $retryAfter;
        $load = [];

        foreach (DB::table('jobs')
            ->selectRaw('queue')
            ->selectRaw('SUM(CASE WHEN available_at <= ? AND (reserved_at IS NULL OR reserved_at <= ?) THEN 1 ELSE 0 END) AS ready', [$now, $staleCutoff])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NOT NULL AND reserved_at > ? THEN 1 ELSE 0 END) AS reserved', [$staleCutoff])
            ->groupBy('queue')
            ->get() as $row) {
            $load[(string) $row->queue] = ['ready' => (int) $row->ready, 'reserved' => (int) $row->reserved];
        }

        return $load;
    }

    private function freshHeartbeats(string $poolName, int $now): int
    {
        $heartbeats = Cache::get('queue:worker-pool:heartbeats:' . sha1($poolName), []);

        return is_array($heartbeats)
            ? count(array_filter($heartbeats, static fn ($timestamp): bool => is_numeric($timestamp) && ($now - (int) $timestamp) <= 15))
            : 0;
    }
}
