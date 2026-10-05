<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class QueueWorkerPoolTest extends TestCase
{
    public function test_worker_capacity_adapts_to_ready_work_without_counting_orphan_reservations_as_workers(): void
    {
        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'workersNeededForDemand');

        foreach ([
            [4, 0, 1, 0, 1], // One job needs only one worker.
            [4, 0, 20, 0, 4], // Backlog is capped at configured capacity.
            [4, 1, 1, 0, 0], // Existing idle worker is sufficient.
            [4, 1, 1, 1, 1], // Busy worker leaves demand for another worker.
            [4, 0, 1, 10, 1], // Orphan reservations do not create phantom capacity.
            [4, 4, 20, 4, 0],
            [4, 0, 0, 10, 0],
        ] as [$capacity, $active, $ready, $reserved, $expected]) {
            $this->assertSame($expected, $method->invoke($command, $capacity, $active, $ready, $reserved));
        }
    }

    public function test_latency_sensitive_queues_have_isolated_worker_pools(): void
    {
        $pools = config('queue.worker_pools');

        $this->assertSame('imports-high', $pools['imports-high']['queues']);
        $this->assertGreaterThanOrEqual(2, $pools['imports-high']['workers']);
        $this->assertSame('imports-daily-loan', $pools['imports-daily-loan']['queues']);
        $this->assertSame('snapshots-priority', $pools['snapshots-priority']['queues']);
        $this->assertSame(1, $pools['snapshots-priority']['workers']);
        $this->assertSame('snapshots-parallel', $pools['snapshots']['queues']);
        $this->assertGreaterThanOrEqual(3, $pools['snapshots']['workers']);
        $this->assertSame('remote-sources', $pools['remote-sources']['queues']);
        $this->assertSame('default', $pools['background']['queues']);
        $this->assertGreaterThanOrEqual(2, $pools['background']['workers']);
        $this->assertSame('reports-low', $pools['reports-low']['queues']);
        $this->assertSame(1, $pools['reports-low']['workers']);
        $this->assertSame('shadow-backfill', $pools['shadow-backfill']['queues']);
        $this->assertSame(1, config('queue.worker_sleep'));
    }

    public function test_shared_legacy_worker_does_not_satisfy_dedicated_import_pool(): void
    {
        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'commandLineCoversQueues');

        $this->assertTrue($method->invoke(
            $command,
            'php artisan queue:work --queue=imports-high --timeout=900',
            ['imports-high']
        ));
        $this->assertFalse($method->invoke(
            $command,
            'php artisan queue:work --queue=imports-high,snapshots-parallel,default --timeout=900',
            ['imports-high']
        ));
    }

    public function test_snapshot_jobs_resolve_to_the_parallel_auto_start_pool(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'queueWorkerPoolFor');
        $pool = $method->invoke($provider, 'snapshots-parallel');

        $this->assertSame('snapshots', $pool['name']);
        $this->assertSame('snapshots-parallel', $pool['queues']);
        $this->assertGreaterThanOrEqual(3, $pool['workers']);

        $priorityPool = $method->invoke($provider, 'snapshots-priority');
        $this->assertSame('snapshots-priority', $priorityPool['name']);
        $this->assertSame('snapshots-priority', $priorityPool['queues']);
        $this->assertSame(1, $priorityPool['workers']);
    }

    public function test_worker_pool_uses_only_fresh_process_heartbeats(): void
    {
        $now = time();
        Cache::put('queue:worker-pool:heartbeats:' . sha1('snapshots'), [
            '1001' => $now,
            '1002' => $now - 10,
            '1003' => $now - 30,
        ], now()->addMinute());

        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'freshWorkerHeartbeatCount');

        $this->assertSame(2, $method->invoke($command, 'snapshots', $now));
    }

    public function test_reserved_jobs_are_not_treated_as_live_worker_processes(): void
    {
        $source = file_get_contents(app_path('Console/Commands/EnsureQueueWorkerRunning.php'));

        $this->assertStringNotContainsString('$heartbeatWorkers + $freshReservedWorkers', $source);
        $this->assertStringContainsString('registeredWorkerProcessCount($poolName, $now)', $source);
        $this->assertStringNotContainsString("'reserved_at' => null", $source);
        $this->assertStringContainsString('DatabaseQueue owns reservation recovery through retry_after', $source);
        $this->assertStringContainsString("'queue:worker-pool:pids:' . sha1(\$poolName)", $source);
        $this->assertStringContainsString("'create_process_group' => true", $source);
        $this->assertStringContainsString("'create_new_console' => false", $source);
        $this->assertStringNotContainsString('taskkill /F', $source);
    }

    public function test_staging_dispatch_reports_queued_until_worker_reserves_job(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Import/ImportFileController.php'));

        $this->assertStringContainsString("'phase' => 'staging_queued'", $source);
        $this->assertStringContainsString("'message' => 'Menunggu worker staging prioritas...'", $source);
        $this->assertStringContainsString('$progressService->markQueued($jobId, [', $source);
    }

    public function test_terminal_import_status_is_checked_before_polling_sends_progress(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Import/ImportFileController.php'));
        $terminalCheck = "if (in_array(\$status, ['completed', 'failed', 'failed_partial', 'terminated'], true))";
        $pollingStart = strpos($source, '$pollingStart = microtime(true);');
        $terminalCheckPosition = strpos($source, $terminalCheck, $pollingStart);
        $progressSendPosition = strpos($source, "\$sendWithCacheSync('progress', \$cached);", $pollingStart);

        $this->assertNotFalse($pollingStart);
        $this->assertNotFalse($terminalCheckPosition);
        $this->assertNotFalse($progressSendPosition);
        $this->assertLessThan($progressSendPosition, $terminalCheckPosition);
    }
}
