<?php

namespace Tests\Unit;

use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\Import\WindowsDetachedProcessLauncher;
use Tests\TestCase;

class QueueSupervisorProcessRunnerTest extends TestCase
{
    public function test_powershell_host_options_are_hidden_before_the_script(): void
    {
        $runner = new QueueSupervisorProcessRunner();
        $method = new \ReflectionMethod($runner, 'hiddenWindowsCommand');
        $command = ['C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', '-NoProfile', '-Command', 'exit 0'];
        $this->assertSame(array_merge([$command[0], '-WindowStyle', 'Hidden'], array_slice($command, 1)), $method->invoke($runner, $command));
        $this->assertSame(['schtasks.exe', '/Query'], $method->invoke($runner, ['schtasks.exe', '/Query']));
    }

    public function test_worker_detection_does_not_count_another_project(): void
    {
        $command = new \App\Console\Commands\EnsureQueueWorkerRunning();
        $method = new \ReflectionMethod($command, 'commandLineLooksLikeQueueWorker');
        $this->assertTrue($method->invoke($command, 'php "' . base_path('artisan') . '" queue:work --queue=imports-high'));
        $this->assertFalse($method->invoke($command, 'php "D:\\another-project\\artisan" queue:work --queue=imports-high'));
        $this->assertFalse($method->invoke($command, 'php "' . base_path('artisan') . '" queue:ensure-running'));
    }

    public function test_windows_process_probe_can_observe_the_current_php_process(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows process probe.');
        }
        [$code, $stdout] = (new QueueSupervisorProcessRunner())->run([
            'powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-Command',
            '(Get-Process -Id ' . getmypid() . ' -ErrorAction Stop).Id',
        ]);

        $this->assertSame(0, $code);
        $this->assertSame((string) getmypid(), $stdout);
    }

    public function test_helper_drains_both_pipes_without_deadlock_and_preserves_exit_code(): void
    {
        [$code, $stdout, $stderr] = (new QueueSupervisorProcessRunner())->run([
            PHP_BINARY, '-r', 'fwrite(STDERR, str_repeat("e", 131072)); fwrite(STDOUT, str_repeat("o", 131072)); exit(7);',
        ]);

        $this->assertSame(7, $code);
        $this->assertSame(131072, strlen($stdout));
        $this->assertSame(131072, strlen($stderr));
    }

    public function test_stuck_helper_is_interrupted_at_deadline(): void
    {
        $started = microtime(true);
        try {
            (new QueueSupervisorProcessRunner())->run([PHP_BINARY, '-r', 'sleep(20);'], 0.2);
            $this->fail('A stuck helper must time out.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('time', strtolower($e->getMessage()));
            $this->assertLessThan(5, microtime(true) - $started);
        }
    }

    public function test_launcher_reports_probe_exception_as_failure(): void
    {
        $runner = $this->createMock(QueueSupervisorProcessRunner::class);
        $runner->method('run')->willThrowException(new \RuntimeException('deadline exceeded'));
        $this->app->instance(QueueSupervisorProcessRunner::class, $runner);
        $launcher = new WindowsDetachedProcessLauncher();
        $method = new \ReflectionMethod($launcher, 'executeWindowsCommand');

        [$code, $stdout, $stderr] = $method->invoke($launcher, ['fake-helper']);

        $this->assertSame(1, $code);
        $this->assertSame('', $stdout);
        $this->assertStringContainsString('deadline exceeded', $stderr);
    }
}
