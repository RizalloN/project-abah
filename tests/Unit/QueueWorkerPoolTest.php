<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use App\Providers\AppServiceProvider;
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
        $this->assertSame('snapshots-parallel', $pools['snapshots']['queues']);
        $this->assertGreaterThanOrEqual(3, $pools['snapshots']['workers']);
        $this->assertSame('default,reports-low,shadow-backfill', $pools['background']['queues']);
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
