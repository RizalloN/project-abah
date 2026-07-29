<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class QueueWorkerPoolTest extends TestCase
{
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
        $this->assertSame('default,reports-low', $pools['background']['queues']);
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
        $this->assertStringContainsString("'reserved_at' => null", $source);
        $this->assertStringContainsString('Released snapshot jobs reserved by a dead worker process.', $source);
        $this->assertStringContainsString("'queue:worker-pool:pids:' . sha1(\$poolName)", $source);
        $this->assertStringContainsString("'powershell.exe'", $source);
        $this->assertFileExists(base_path('scripts/start_queue_worker.ps1'));
        $launcher = file_get_contents(base_path('scripts/start_queue_worker.ps1'));
        $this->assertStringContainsString('-RedirectStandardOutput $OutputLog', $launcher);
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
