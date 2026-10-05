<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class QueueWorkerCrashBackoffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Tests the Windows child-process reaper.');
        }
        config()->set('cache.default', 'array');
        Cache::flush();
    }

    public function test_repeated_immediate_child_crashes_accumulate_backoff_and_remove_only_own_registration(): void
    {
        $command = new EnsureQueueWorkerRunning();
        $pool = 'crash-test';
        $pidKey = (new ReflectionMethod($command, 'workerPidKey'))->invoke($command, $pool);
        $leaseKey = (new ReflectionMethod($command, 'workerLeaseKey'))->invoke($command, $pool);
        $backoffKey = (new ReflectionMethod($command, 'poolLaunchBackoffKey'))->invoke($command, $pool);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $pid = $this->attachChild($command, $pool, 'usleep(100000); exit(7);');
            Cache::put($pidKey, [$pid => time(), 999999 => time()], 60);
            Cache::put($leaseKey, 2, 60);
            $this->reapUntilExited($command);
            $state = Cache::get($backoffKey);
            $this->assertSame($attempt, $state['failures']);
            $this->assertSame(10 * (2 ** ($attempt - 1)), $state['next_retry_at'] - $state['last_failure_at']);
            $this->assertArrayNotHasKey($pid, Cache::get($pidKey));
            $this->assertArrayHasKey(999999, Cache::get($pidKey));
            $this->assertSame(1, Cache::get($leaseKey));
        }
    }

    public function test_backoff_is_reset_only_after_child_is_observed_running_past_startup_window(): void
    {
        $command = new EnsureQueueWorkerRunning();
        $pool = 'stable-test';
        $backoffKey = (new ReflectionMethod($command, 'poolLaunchBackoffKey'))->invoke($command, $pool);
        Cache::put($backoffKey, ['failures' => 2, 'next_retry_at' => time() + 20], 60);
        $pid = $this->attachChild($command, $pool, 'usleep(500000); exit(0);');
        $reap = new ReflectionMethod($command, 'reapFinishedWindowsWorkers');
        $reap->invoke($command);
        $this->assertSame(2, Cache::get($backoffKey)['failures']);

        $metadata = new ReflectionProperty($command, 'windowsWorkerStarts');
        $starts = $metadata->getValue($command);
        $starts[$pid]['started_at'] -= 16;
        $metadata->setValue($command, $starts);
        $reap->invoke($command);
        $this->assertFalse(Cache::has($backoffKey));
        $this->assertTrue($metadata->getValue($command)[$pid]['stable']);
        $this->reapUntilExited($command);
        $this->assertFalse(Cache::has($backoffKey));
    }

    private function attachChild(EnsureQueueWorkerRunning $command, string $pool, string $code): int
    {
        $process = proc_open([PHP_BINARY, '-r', $code], [
            0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a'],
        ], $pipes, base_path(), null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        $status = proc_get_status($process);
        $pid = (int) $status['pid'];
        (new ReflectionProperty($command, 'windowsWorkerProcesses'))->setValue($command, [$pid => $process]);
        (new ReflectionProperty($command, 'windowsWorkerStarts'))->setValue($command, [
            $pid => ['pool' => $pool, 'started_at' => microtime(true), 'stable' => false],
        ]);
        return $pid;
    }

    private function reapUntilExited(EnsureQueueWorkerRunning $command): void
    {
        $processes = new ReflectionProperty($command, 'windowsWorkerProcesses');
        $reap = new ReflectionMethod($command, 'reapFinishedWindowsWorkers');
        $deadline = microtime(true) + 3;
        do {
            $reap->invoke($command);
            if ($processes->getValue($command) === []) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('The short-lived test helper did not exit.');
    }
}
