<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportJobManagementController;
use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\WindowsDetachedProcessLauncher;
use App\Support\ManagedReportSnapshotRebuildCoordinator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class QueueWorkerControlServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        $processRunner = Mockery::mock(\App\Services\Import\QueueSupervisorProcessRunner::class);
        $processRunner->shouldReceive('run')->andReturn([0, '', '']);
        $this->app->instance(\App\Services\Import\QueueSupervisorProcessRunner::class, $processRunner);
        Cache::flush();
        // Tests must not inherit the operator's durable production stop/start state.
        $service = new QueueWorkerControlService();
        $key = new \ReflectionMethod($service, 'key');
        Cache::forever($key->invoke($service, 'enabled'), true);
        $launcher = Mockery::mock(WindowsDetachedProcessLauncher::class);
        $launcher->shouldReceive('setTaskEnabled')->andReturn(true);
        $launcher->shouldReceive('launch')->never();
        $this->app->instance(WindowsDetachedProcessLauncher::class, $launcher);
    }

    protected function tearDown(): void
    {
        $service = new QueueWorkerControlService();
        $path = new \ReflectionMethod($service, 'durableControlStatePath');
        $isolatedPath = $path->invoke($service);
        if (is_file($isolatedPath) && str_contains($isolatedPath, 'queue-worker-control-' . getmypid() . '.json')) {
            unlink($isolatedPath);
        }
        Cache::flush();
        Mockery::close();

        parent::tearDown();
    }

    public function test_status_uses_monitor_heartbeat_and_exposes_exact_operator_command(): void
    {
        $service = new QueueWorkerControlService();
        $service->touchMonitorHeartbeat();

        $status = $service->status();

        $this->assertTrue($status['enabled']);
        $this->assertTrue($status['monitor_active']);
        $this->assertSame('active', $status['state']);
        $this->assertSame(
            'php artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30',
            $status['command']
        );
    }

    public function test_durable_state_is_isolated_from_live_worker_control(): void
    {
        $productionPath = storage_path('framework/queue-worker-control.json');
        $before = is_file($productionPath) ? hash_file('sha256', $productionPath) : null;
        $service = new QueueWorkerControlService();
        $path = (new \ReflectionMethod($service, 'durableControlStatePath'))->invoke($service);
        $this->assertSame(storage_path('framework/testing/queue-worker-control-' . getmypid() . '.json'), $path);
        $service->markEnabled();
        $this->assertTrue(json_decode(file_get_contents($path), true)['enabled']);
        $this->assertSame($before, is_file($productionPath) ? hash_file('sha256', $productionPath) : null);
    }

    public function test_active_monitor_is_not_reported_as_stopped_when_queue_is_idle(): void
    {
        $service = new QueueWorkerControlService();
        $service->touchMonitorHeartbeat();

        $status = $service->status();

        $this->assertSame('active', $status['state']);
        $this->assertSame('Worker Monitor Berjalan', $status['state_label']);
        $this->assertSame(0, $status['worker_count']);
        $this->assertStringContainsString('Monitor berjalan dan siap', $status['message']);
        $this->assertStringContainsString('antrean sedang kosong', $status['message']);
    }

    public function test_php_cli_resolution_rejects_apache_binary(): void
    {
        $service = new QueueWorkerControlService();
        $method = new \ReflectionMethod($service, 'firstUsablePhpCliBinary');

        $resolved = $method->invoke($service, [
            'C:\\xampp\\apache\\bin\\httpd.exe',
            PHP_BINARY,
        ]);

        $this->assertSame(PHP_BINARY, $resolved);
        $this->assertContains(strtolower(basename(str_replace('\\', '/', $resolved))), ['php.exe', 'php']);
    }

    public function test_windows_launcher_requires_scheduled_task_acknowledgement(): void
    {
        $launcher = new class extends WindowsDetachedProcessLauncher
        {
            /** @var array<int, array<int, string>> */
            public array $commands = [];
            public string $ackPath = '';

            protected function executeWindowsCommand(array $command): array
            {
                $this->commands[] = $command;

                return [0, 'SUCCESS', ''];
            }

            protected function waitForAcknowledgement(string $ackPath): bool
            {
                $this->ackPath = $ackPath;
                file_put_contents($ackPath, 'TASK_STARTED');

                return true;
            }

            protected function launcherDirectory(): string
            {
                return storage_path('framework/testing/queue-launcher-pid');
            }
        };
        $logBase = storage_path('framework/testing/queue-launcher-pid-test');

        $pid = $launcher->launch(
            [PHP_BINARY, base_path('artisan'), 'queue:ensure-running'],
            base_path(),
            $logBase . '.out.log',
            $logBase . '.err.log',
            $logBase . '.launcher.err.log'
        );

        $this->assertSame(0, $pid);
        $createCommand = collect($launcher->commands)
            ->first(fn (array $command): bool => in_array('/Create', $command, true));
        $this->assertIsArray($createCommand);
        $taskCommandIndex = array_search('/TR', $createCommand, true);
        $this->assertIsInt($taskCommandIndex);
        $this->assertStringContainsString('wscript.exe //B //NoLogo', $createCommand[$taskCommandIndex + 1]);
        $this->assertStringContainsString('queue-monitor-', $createCommand[$taskCommandIndex + 1]);
        $hiddenPath = substr($launcher->ackPath, 0, -strlen('.started')) . '.vbs';
        $hiddenScript = file_get_contents($hiddenPath);
        $this->assertStringContainsString('cmd.exe /D /C call', $hiddenScript);
        $this->assertStringContainsString('", 0, True)', $hiddenScript);
        @unlink($hiddenPath);
        @unlink(substr($launcher->ackPath, 0, -strlen('.started')));
    }

    public function test_windows_launcher_rejects_task_without_process_acknowledgement(): void
    {
        $launcher = new class extends WindowsDetachedProcessLauncher
        {
            protected function executeWindowsCommand(array $command): array
            {
                return [0, 'SUCCESS', ''];
            }

            protected function waitForAcknowledgement(string $ackPath): bool
            {
                return false;
            }

            protected function launcherDirectory(): string
            {
                return storage_path('framework/testing/queue-launcher-no-pid');
            }
        };
        $logBase = storage_path('framework/testing/queue-launcher-no-pid-test');
        @unlink($logBase . '.launcher.err.log');

        $pid = $launcher->launch(
            [PHP_BINARY, base_path('artisan'), 'queue:ensure-running'],
            base_path(),
            $logBase . '.out.log',
            $logBase . '.err.log',
            $logBase . '.launcher.err.log'
        );

        $this->assertNull($pid);
        $this->assertStringContainsString(
            'tidak memberikan acknowledgement',
            (string) file_get_contents($logBase . '.launcher.err.log')
        );
    }

    public function test_enable_only_succeeds_after_monitor_startup_is_confirmed(): void
    {
        $service = new class extends QueueWorkerControlService
        {
            private int $activeChecks = 0;

            public function isMonitorActive(): bool
            {
                $this->activeChecks++;

                return $this->activeChecks >= 2;
            }

            protected function startDetachedMonitor(): bool
            {
                return true;
            }
        };

        $status = $service->enable();

        $this->assertTrue($status['started']);
        $this->assertTrue($status['startup_confirmed']);
        $this->assertFalse($status['already_active']);
        $this->assertSame('active', $status['state']);
    }

    public function test_enable_accepts_pid_acknowledgement_while_first_heartbeat_is_still_pending(): void
    {
        $service = new class extends QueueWorkerControlService
        {
            public function isMonitorActive(): bool
            {
                return false;
            }

            public function status(): array
            {
                return ['state' => 'starting'];
            }

            protected function startDetachedMonitor(): bool
            {
                return true;
            }

            protected function waitForMonitorStartup(): bool
            {
                return false;
            }
        };

        $status = $service->enable();

        $this->assertTrue($status['started']);
        $this->assertFalse($status['startup_confirmed']);
        $this->assertSame('starting', $status['state']);
    }

    public function test_automatic_monitor_recovery_uses_backoff_after_launcher_failure(): void
    {
        $service = new class extends QueueWorkerControlService
        {
            public int $launchAttempts = 0;

            public function isMonitorActive(): bool
            {
                return false;
            }

            protected function startDetachedMonitor(): bool
            {
                $this->launchAttempts++;

                return false;
            }
        };

        $first = $service->ensureMonitorRunning('test-watchdog');
        $second = $service->ensureMonitorRunning('test-watchdog');

        $this->assertFalse($first['started']);
        $this->assertSame('backoff', $first['recovery']['state']);
        $this->assertSame('backoff', $second['recovery_skipped']);
        $this->assertSame(1, $service->launchAttempts);
    }

    public function test_controller_start_and_stop_return_worker_status_payload(): void
    {
        $controller = new ImportJobManagementController(
            Mockery::mock(ManagedReportSnapshotRebuildCoordinator::class),
            Mockery::mock(ImportIndexController::class)
        );

        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('enable')->once()->andReturn([
            'started' => true,
            'startup_confirmed' => true,
            'already_active' => false,
            'state' => 'active',
        ]);
        $service->shouldReceive('disable')->once()->andReturn([
            'stop_requested' => true,
            'state' => 'stopping',
        ]);

        $start = $controller->startWorker($service);
        $stop = $controller->stopWorker($service);

        $this->assertSame(200, $start->getStatusCode());
        $this->assertSame('active', $start->getData(true)['worker_status']['state']);
        $this->assertSame(200, $stop->getStatusCode());
        $this->assertSame('stopping', $stop->getData(true)['worker_status']['state']);
    }

    public function test_controller_returns_accepted_while_monitor_heartbeat_is_pending(): void
    {
        $controller = new ImportJobManagementController(
            Mockery::mock(ManagedReportSnapshotRebuildCoordinator::class),
            Mockery::mock(ImportIndexController::class)
        );
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('enable')->once()->andReturn([
            'started' => true,
            'startup_confirmed' => false,
            'already_active' => false,
            'state' => 'starting',
        ]);

        $response = $controller->startWorker($service);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('success', $response->getData(true)['status']);
        $this->assertStringContainsString('menunggu heartbeat', $response->getData(true)['message']);
    }

    public function test_once_command_is_a_noop_while_worker_control_is_disabled(): void
    {
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('isEnabled')->once()->andReturnFalse();
        $this->app->instance(QueueWorkerControlService::class, $service);

        $exitCode = Artisan::call('queue:ensure-running', ['--once' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('dinonaktifkan', Artisan::output());
    }

    public function test_delayed_managed_monitor_never_reenables_disabled_control(): void
    {
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('markEnabled')->never();
        $service->shouldReceive('isEnabled')->once()->andReturnFalse();
        $this->app->instance(QueueWorkerControlService::class, $service);

        $exitCode = Artisan::call('queue:ensure-running', ['--managed' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('dinonaktifkan', Artisan::output());
    }

    public function test_deliberate_long_running_cli_command_enables_control_state_again(): void
    {
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('markEnabled')->once();
        $service->shouldReceive('isEnabled')->twice()->andReturn(true, false);
        $service->shouldReceive('claimMonitorLeadership')->once()->andReturnTrue();
        $service->shouldReceive('clearOwnMonitorHeartbeat')->once();
        $this->app->instance(QueueWorkerControlService::class, $service);

        $exitCode = Artisan::call('queue:ensure-running', ['--check-interval' => 1]);

        $this->assertSame(0, $exitCode);
    }

    public function test_worker_control_is_project_scoped_and_never_force_kills_php_processes(): void
    {
        $serviceSource = file_get_contents(app_path('Services/Import/QueueWorkerControlService.php'));
        $commandSource = file_get_contents(app_path('Console/Commands/EnsureQueueWorkerRunning.php'));
        $providerSource = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $viewSource = file_get_contents(resource_path('views/import/job-management.blade.php'));

        $this->assertStringContainsString("sha1(strtolower(str_replace('\\\\', '/', \$project)))", $serviceSource);
        $this->assertStringContainsString("Artisan::call('queue:restart')", $serviceSource);
        $this->assertStringNotContainsString('taskkill /F', $serviceSource);
        $this->assertStringNotContainsString('kill -9', $serviceSource);
        $this->assertStringNotContainsString('-PassThru; $p.Id', $serviceSource);
        $this->assertStringContainsString('$workerControl->markEnabled();', $commandSource);
        $this->assertStringContainsString('$workerControl->isEnabled()', $providerSource);
        $this->assertStringContainsString("ensureMonitorRunning('job-queued')", $providerSource);
        $this->assertStringContainsString('data-worker-start-url', $viewSource);
        $this->assertStringContainsString('data-worker-stop-url', $viewSource);
        $this->assertStringEndsWith('/job-management/worker/start', route('job-management.worker.start'));
        $this->assertStringEndsWith('/job-management/worker/stop', route('job-management.worker.stop'));
    }
}
