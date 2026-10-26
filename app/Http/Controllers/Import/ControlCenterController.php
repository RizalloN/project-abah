<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use App\Services\ControlCenter\DatabaseInsightService;
use App\Services\ControlCenter\HealthCheckService;
use App\Services\ControlCenter\PhpMyAdminGateway;
use App\Services\ControlCenter\ReportDataFreshnessService;
use App\Services\ControlCenter\ShadowColumnHealthService;
use App\Services\Import\QueueWorkerMonitorService;
use App\Services\Import\WorkerCapacityPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Data endpoints of the Job Management control panel. Every endpoint is
 * read-only except the shadow repair and the phpMyAdmin gate.
 */
class ControlCenterController extends Controller
{
    public function health(Request $request, HealthCheckService $health): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return $this->guarded(fn () => $health->snapshot($request->boolean('refresh')));
    }

    public function freshness(Request $request, ReportDataFreshnessService $freshness): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return $this->guarded(fn () => $freshness->snapshot($request->boolean('refresh')));
    }

    public function workers(QueueWorkerMonitorService $monitor, WorkerCapacityPlanner $planner): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return $this->guarded(function () use ($monitor, $planner): array {
            $pools = [];
            foreach ((array) config('queue.worker_pools', []) as $name => $pool) {
                $pools[(string) $name] = ['queues' => (string) ($pool['queues'] ?? 'default'), 'workers' => max(1, (int) ($pool['workers'] ?? 1))];
            }

            // The monitor publishes its latest plan; compute one when it has not yet.
            $plan = $planner->lastPlan() ?? ($planner->enabled() ? $planner->plan($pools) : null);

            return [
                'monitor' => $monitor->snapshot(),
                'plan' => $plan,
                'planner' => [
                    'enabled' => $planner->enabled(),
                    'max_total_workers' => (int) config('queue.worker_planner.max_total_workers', 12),
                    'memory_reserve_mb' => (int) config('queue.worker_planner.memory_reserve_mb', 2048),
                ],
                'pool_policy' => collect((array) config('queue.worker_pools', []))->map(fn (array $pool, string $name): array => [
                    'name' => $name,
                    'queues' => (string) ($pool['queues'] ?? ''),
                    'capacity' => (int) ($pool['workers'] ?? 1),
                    'priority' => (int) ($pool['priority'] ?? 50),
                    'critical' => (bool) ($pool['critical'] ?? false),
                    'elastic' => (bool) ($pool['elastic'] ?? false),
                ])->sortBy('priority')->values()->all(),
            ];
        });
    }

    public function snapshots(ReportDataFreshnessService $freshness): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return $this->guarded(function () use ($freshness): array {
            $dirty = Schema::hasTable('snapshot_dirty_periods')
                ? DB::table('snapshot_dirty_periods')
                    ->orderBy('dirty_since')
                    ->limit(40)
                    ->get(['source_table', 'period_key', 'shard_type', 'shard_key', 'dirty_since', 'dirty_row_count', 'attempts', 'claimed_at', 'last_error'])
                    ->map(fn ($row): array => [
                        'source' => (string) $row->source_table,
                        'period' => (string) $row->period_key,
                        'shard' => trim((string) $row->shard_type . ' ' . (string) $row->shard_key),
                        'dirty_since' => (string) $row->dirty_since,
                        'rows' => (int) $row->dirty_row_count,
                        'attempts' => (int) $row->attempts,
                        'state' => $row->last_error !== null ? 'error' : ($row->claimed_at !== null ? 'processing' : 'waiting'),
                        'error' => $row->last_error !== null ? mb_strimwidth((string) $row->last_error, 0, 200, '…') : null,
                    ])->all()
                : [];

            $latestByTable = collect($freshness->snapshot()['reports'])->keyBy('table');
            $sources = Schema::hasTable('snapshot_source_signatures')
                ? DB::table('snapshot_source_signatures')
                    ->selectRaw('source_table, COUNT(DISTINCT snapshot_table) AS snapshot_tables, MAX(period_key) AS latest_period, MAX(built_at) AS last_built')
                    ->groupBy('source_table')
                    ->get()
                    ->map(function ($row) use ($latestByTable): array {
                        $sourceLatest = $latestByTable->get((string) $row->source_table)['latest_period'] ?? null;
                        $snapshotLatest = substr((string) $row->latest_period, 0, 10);

                        return [
                            'source' => (string) $row->source_table,
                            'snapshot_tables' => (int) $row->snapshot_tables,
                            'source_latest_period' => $sourceLatest,
                            'snapshot_latest_period' => $snapshotLatest,
                            'last_built' => (string) $row->last_built,
                            'in_sync' => $sourceLatest === null || $sourceLatest <= $snapshotLatest,
                        ];
                    })->sortBy('source')->values()->all()
                : [];

            return ['dirty_periods' => $dirty, 'sources' => $sources, 'generated_at' => now()->toIso8601String()];
        });
    }

    public function shadow(Request $request, ShadowColumnHealthService $shadow): JsonResponse
    {
        $this->releaseSessionLockIfNeeded();

        return $this->guarded(function () use ($request, $shadow): array {
            $snapshot = $shadow->snapshot($request->boolean('refresh'));
            if ($request->filled('verify')) {
                $snapshot['periods'] = $shadow->verifyRecent((int) $request->query('verify', 3));
                $snapshot['verified'] = true;
            }

            return $snapshot;
        });
    }

    public function shadowRepair(Request $request, ShadowColumnHealthService $shadow): JsonResponse
    {
        $validated = $request->validate([
            'periods' => ['required', 'array', 'min:1', 'max:' . ShadowColumnHealthService::MAX_VERIFY_PERIODS],
            'periods.*' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $result = $shadow->queueRepair($validated['periods']);

            return response()->json(['status' => 'ok', 'message' => 'Perbaikan shadow column diantrekan untuk ' . implode(', ', $result['queued_periods']) . '.'] + $result);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    public function database(Request $request, DatabaseInsightService $database, PhpMyAdminGateway $gateway): JsonResponse
    {
        return $this->guarded(fn () => $database->snapshot($request->boolean('refresh')) + ['gateway' => $gateway->state($request)]);
    }

    public function unlockDatabase(Request $request, PhpMyAdminGateway $gateway): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:255']]);
        $key = 'pma-gateway-unlock:' . $request->user()?->getAuthIdentifier() . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['status' => 'error', 'message' => 'Terlalu banyak percobaan. Coba lagi dalam ' . RateLimiter::availableIn($key) . ' detik.'], 429);
        }

        if (!$gateway->unlock($request, (string) $validated['password'])) {
            RateLimiter::hit($key, 300);

            return response()->json(['status' => 'error', 'message' => 'Password akun tidak sesuai.'], 422);
        }

        RateLimiter::clear($key);

        return response()->json([
            'status' => 'ok',
            'message' => 'Akses database dibuka selama 2 jam (terkunci otomatis setelah 20 menit tanpa aktivitas).',
            'url' => PhpMyAdminGateway::ROUTE_PREFIX,
            'gateway' => $gateway->state($request),
        ]);
    }

    public function lockDatabase(Request $request, PhpMyAdminGateway $gateway): JsonResponse
    {
        $gateway->lock($request);

        return response()->json(['status' => 'ok', 'message' => 'Akses phpMyAdmin dikunci.', 'gateway' => $gateway->state($request)]);
    }

    public function phpMyAdmin(Request $request, PhpMyAdminGateway $gateway, ?string $path = null): Response
    {
        if (!$gateway->authorizeAndTouch($request)) {
            return redirect()->route('job-management.index', ['tab' => 'database'])
                ->with('sweet_warning', ['title' => 'Akses Database Terkunci', 'text' => 'Konfirmasi password akun dari tab Database untuk membuka phpMyAdmin.']);
        }

        $this->releaseSessionLockIfNeeded();

        return $gateway->forward($request, (string) $path);
    }

    private function guarded(callable $build): JsonResponse
    {
        try {
            return response()->json(['status' => 'ok'] + $build());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => 'error', 'message' => 'Data panel gagal dimuat: ' . mb_strimwidth($e->getMessage(), 0, 200, '…')], 500);
        }
    }
}
