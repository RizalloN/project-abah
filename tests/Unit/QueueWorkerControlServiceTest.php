<?php

namespace Tests\Unit;

use App\Http\Controllers\Import\ImportIndexController;
use App\Http\Controllers\Import\ImportJobManagementController;
use App\Services\Import\QueueWorkerControlService;
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
        Cache::flush();
    }

    protected function tearDown(): void
    {
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

    public function test_controller_start_and_stop_return_worker_status_payload(): void
    {
        $controller = new ImportJobManagementController(
            Mockery::mock(ManagedReportSnapshotRebuildCoordinator::class),
            Mockery::mock(ImportIndexController::class)
        );

        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('enable')->once()->andReturn([
            'started' => true,
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

    public function test_once_command_is_a_noop_while_worker_control_is_disabled(): void
    {
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('isEnabled')->once()->andReturnFalse();
        $this->app->instance(QueueWorkerControlService::class, $service);

        $exitCode = Artisan::call('queue:ensure-running', ['--once' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('dinonaktifkan', Artisan::output());
    }

    public function test_deliberate_long_running_cli_command_enables_control_state_again(): void
    {
        $service = Mockery::mock(QueueWorkerControlService::class);
        $service->shouldReceive('markEnabled')->once();
        $service->shouldReceive('isEnabled')->twice()->andReturn(true, false);
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
        $this->assertStringContainsString('$workerControl->markEnabled();', $commandSource);
        $this->assertStringContainsString('QueueWorkerControlService::class)->isEnabled()', $providerSource);
        $this->assertStringContainsString('data-worker-start-url', $viewSource);
        $this->assertStringContainsString('data-worker-stop-url', $viewSource);
        $this->assertStringEndsWith('/job-management/worker/start', route('job-management.worker.start'));
        $this->assertStringEndsWith('/job-management/worker/stop', route('job-management.worker.stop'));
    }
}
