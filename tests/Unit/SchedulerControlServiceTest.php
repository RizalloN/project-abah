<?php

namespace Tests\Unit;

use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\SchedulerControlService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\PhpExecutableFinder;
use Tests\TestCase;

class SchedulerControlServiceTest extends TestCase
{
    private string $originalStorage;

    private string $isolatedStorage;

    private QueueSupervisorProcessRunner $runner;

    private SchedulerControlService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->originalStorage = storage_path();
        $this->isolatedStorage = $this->originalStorage.'/framework/testing/runtime-'.getmypid().'/scheduler-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->isolatedStorage);
        app()->useStoragePath($this->isolatedStorage);
        $this->runner = Mockery::mock(QueueSupervisorProcessRunner::class);
        $this->scheduler = new SchedulerControlService($this->runner);
    }

    protected function tearDown(): void
    {
        app()->useStoragePath($this->originalStorage);
        $resolved = realpath($this->isolatedStorage);
        $allowed = realpath($this->originalStorage.'/framework/testing/runtime-'.getmypid());
        if ($resolved !== false && $allowed !== false && str_starts_with(strtolower($resolved), strtolower($allowed.DIRECTORY_SEPARATOR))) {
            File::deleteDirectory($resolved);
        }

        parent::tearDown();
    }

    public function test_runtime_state_paths_are_isolated_from_live_scheduler_artifacts(): void
    {
        $path = new \ReflectionMethod($this->scheduler, 'path');

        $this->assertSame($this->runtimePath('run.json'), $path->invoke($this->scheduler, 'run.json'));
        $this->assertStringStartsWith($this->originalStorage.'/framework/testing/runtime-'.getmypid().'/', storage_path());
        $this->assertNotSame($this->originalStorage.'/framework/server-runtime/run.json', $path->invoke($this->scheduler, 'run.json'));
    }

    #[DataProvider('nativeControls')]
    public function test_control_invokes_a_fixed_argument_vector_and_never_fabricates_a_heartbeat(string $action, ?string $service, string $nativeAction): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows argument vector is specific to the Windows runtime.');
        }
        $command = [];
        $this->runner->shouldReceive('run')->once()->with(Mockery::type('array'), 20.0)->andReturnUsing(function (array $arguments) use (&$command): array {
            $command = $arguments;

            return [0, json_encode(['success' => true, 'message' => 'accepted']), ''];
        });

        $result = $this->scheduler->control($action, $service);

        $php = PHP_BINDIR.DIRECTORY_SEPARATOR.'php.exe';
        if (! is_file($php)) {
            $php = (new PhpExecutableFinder)->find(false);
        }
        $expected = ['powershell.exe', '-NoProfile', '-NonInteractive', '-NoLogo', '-ExecutionPolicy', 'Bypass', '-File', base_path('scripts/manage-project-runtime.ps1'), '-Action', $nativeAction, '-ProjectDirectory', base_path(), '-PhpBinary', $php];
        if ($service !== null && $service !== 'backup') {
            array_push($expected, '-ServiceKey', $service);
        }
        $this->assertSame($expected, $command);
        $this->assertTrue($result['success']);
        $this->assertFalse(Cache::has('system:scheduler:last_heartbeat'));
        $this->assertFileDoesNotExist(storage_path('app/health/scheduler-heartbeat.json'));
        if ($service === null && $action !== 'run') {
            $this->assertSame($action !== 'stop', $this->readRuntime('control.json')['enabled']);
        } else {
            $this->assertFileDoesNotExist($this->runtimePath('control.json'));
        }
    }

    public static function nativeControls(): array
    {
        return [
            ['start', null, 'StartScheduler'], ['stop', null, 'StopScheduler'],
            ['restart', null, 'RestartScheduler'], ['run', null, 'RunScheduler'],
            ['start', 'apache', 'StartService'], ['start', 'mysql', 'StartService'], ['start', 'tunnel', 'StartService'],
            ['run', 'backup', 'RunBackup'],
        ];
    }

    #[DataProvider('unsafeControls')]
    public function test_invalid_or_injected_controls_cannot_reach_the_process_runner(string $action, ?string $service): void
    {
        $this->runner->shouldNotReceive('run');
        $this->expectException(\InvalidArgumentException::class);

        $this->scheduler->control($action, $service);
    }

    public static function unsafeControls(): array
    {
        return [
            ['start; whoami', null], ['stop', 'apache'], ['restart', 'mysql'], ['run', 'tunnel'],
            ['start', 'mysql; Stop-Process -Name php'], ['start', '../mysql'], ['start', 'unknown'],
            ['start', 'backup'], ['stop', 'backup'], ['restart', 'backup'],
        ];
    }

    public function test_a_failed_native_control_does_not_change_the_desired_scheduler_state(): void
    {
        $this->writeRuntime('control.json', ['enabled' => false]);
        $this->runner->shouldReceive('run')->once()->andReturn([5, '{"success":true,"message":"Permission denied"}', '']);

        $result = $this->scheduler->control('start');

        $this->assertFalse($result['success']);
        $this->assertFalse($this->readRuntime('control.json')['enabled']);
    }

    public function test_inspection_is_cached_and_fresh_inspection_does_not_start_any_service(): void
    {
        $native = ['scheduler' => ['available' => true, 'registered' => false], 'infrastructure' => []];
        $this->runner->shouldReceive('run')->twice()->with(Mockery::on(function (array $command): bool {
            $this->assertSame('Status', $command[array_search('-Action', $command, true) + 1]);

            return true;
        }), 8.0)->andReturn([0, json_encode($native), '']);

        $this->assertSame($native, $this->scheduler->inspect());
        $this->assertSame($native, $this->scheduler->inspect());
        $this->assertSame($native, $this->scheduler->inspect(true));
        $this->assertFileDoesNotExist($this->runtimePath('control.json'));
    }

    public function test_invalid_native_status_is_reported_as_unverified_instead_of_healthy(): void
    {
        $this->runner->shouldReceive('run')->once()->andReturn([1, '<error>', 'Task access denied']);

        $inspection = $this->scheduler->inspect();
        $status = $this->scheduler->status($inspection);

        $this->assertFalse($status['available']);
        $this->assertSame('error', $status['state']);
        $this->assertStringContainsString('Task access denied', $status['message']);
    }

    public function test_start_acknowledgement_is_starting_until_a_real_heartbeat_arrives(): void
    {
        $this->runner->shouldReceive('run')->once()->andReturn([0, '{"success":true}', '']);
        $this->scheduler->control('start');
        $inspection = ['scheduler' => ['available' => true, 'registered' => true, 'enabled' => true]];

        $pending = $this->scheduler->status($inspection);

        $this->assertSame('starting', $pending['state']);
        $this->assertNull($pending['heartbeat_at']);
        Cache::put('system:scheduler:last_heartbeat', time());
        $active = $this->scheduler->status($inspection);
        $this->assertSame('active', $active['state']);
        $this->assertNotNull($active['heartbeat_at']);
    }

    #[DataProvider('observedStates')]
    public function test_status_reports_observed_heartbeat_task_and_legacy_state(array $native, ?int $age, string $expected): void
    {
        $this->runner->shouldNotReceive('run');
        if ($age !== null) {
            Cache::put('system:scheduler:last_heartbeat', time() - $age);
        }

        $status = $this->scheduler->status(['scheduler' => $native]);

        $this->assertSame($expected, $status['state']);
    }

    public function test_a_recent_heartbeat_from_before_the_current_boot_does_not_claim_scheduler_health(): void
    {
        Cache::put('system:scheduler:last_heartbeat', time() - 5);

        $status = $this->scheduler->status([
            'boot_timestamp' => time() - 2,
            'scheduler' => ['available' => true, 'registered' => true, 'enabled' => true],
        ]);

        $this->assertSame('inactive', $status['state']);
        $this->assertNull($status['heartbeat_at']);
    }

    public function test_an_unreadable_native_task_remains_unverified_even_when_a_real_heartbeat_is_fresh(): void
    {
        Cache::put('system:scheduler:last_heartbeat', time());

        $status = $this->scheduler->status(['scheduler' => [
            'available' => false, 'registered' => false, 'enabled' => false, 'message' => 'Protected Windows task cannot be read.',
        ]]);

        $this->assertSame('error', $status['state']);
        $this->assertFalse($status['available']);
        $this->assertNotNull($status['heartbeat_at']);
        $this->assertNull($status['task_registered']);
        $this->assertNull($status['task_enabled']);
        $this->assertSame('Protected Windows task cannot be read.', $status['message']);
    }

    public static function observedStates(): array
    {
        return [
            [['available' => true, 'enabled' => true, 'registered' => true], 5, 'active'],
            [['available' => true, 'enabled' => true, 'last_result' => 1], 5, 'failed'],
            [['available' => true, 'enabled' => true, 'last_result' => 1, 'state' => 'running'], 5, 'active'],
            [['available' => true, 'enabled' => true, 'last_result' => 267009], 5, 'active'],
            [['available' => true, 'enabled' => true, 'last_result' => 267011], 5, 'active'],
            [['available' => true, 'enabled' => false, 'legacy_count' => 1], 5, 'external'],
            [['available' => true, 'enabled' => false], null, 'stopped'],
            [['available' => true, 'enabled' => false, 'state' => 'running'], 5, 'stopping'],
            [['available' => true, 'enabled' => true], 300, 'inactive'],
            [['available' => true, 'enabled' => true, 'conflict' => true], 5, 'error'],
            [['available' => false, 'enabled' => true], 5, 'error'],
            [['available' => true, 'enabled' => true], -600, 'inactive'],
        ];
    }

    public function test_run_due_executes_schedule_once_and_rejects_a_second_run_in_the_same_minute(): void
    {
        Artisan::shouldReceive('call')->once()->with('schedule:run', ['--no-interaction' => true])->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Due maintenance executed.');

        $first = $this->scheduler->runDue();
        $second = $this->scheduler->runDue();

        $this->assertSame(['outcome' => 'completed', 'exit_code' => 0], $first);
        $this->assertSame(['outcome' => 'already_ran', 'exit_code' => 0], $second);
        $state = $this->readRuntime('run.json');
        $this->assertSame(intdiv(time(), 60), $state['minute']);
        $this->assertSame(0, $state['exit_code']);
        $this->assertNotNull($state['completed_at']);
        $this->assertStringContainsString('Due maintenance executed.', file_get_contents($this->runtimePath('scheduler.log')));
    }

    public function test_concurrent_scheduler_run_returns_busy_without_executing_tasks(): void
    {
        Artisan::shouldReceive('call')->never();
        File::ensureDirectoryExists(dirname($this->runtimePath('run.lock')));
        $lock = fopen($this->runtimePath('run.lock'), 'c+');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        try {
            $this->assertSame(['outcome' => 'busy', 'exit_code' => 0], $this->scheduler->runDue());
            $this->assertFileDoesNotExist($this->runtimePath('run.json'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_disabled_scheduler_does_not_execute_due_tasks_or_write_a_heartbeat(): void
    {
        Artisan::shouldReceive('call')->never();
        $this->writeRuntime('control.json', ['enabled' => false]);

        $this->assertSame(['outcome' => 'disabled', 'exit_code' => 0], $this->scheduler->runDue());
        $this->assertFileDoesNotExist($this->runtimePath('run.json'));
        $this->assertFileDoesNotExist(storage_path('app/health/scheduler-heartbeat.json'));
    }

    public function test_failed_execution_is_recorded_without_replay_and_can_retry_in_the_next_minute(): void
    {
        Artisan::shouldReceive('call')->with('schedule:run', ['--no-interaction' => true])->once()->andThrow(new \RuntimeException('Maintenance unavailable.'));
        Artisan::shouldReceive('call')->with('schedule:run', ['--no-interaction' => true])->once()->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Maintenance recovered.');

        $this->assertSame(['outcome' => 'completed', 'exit_code' => 1], $this->scheduler->runDue());
        $failedState = $this->readRuntime('run.json');
        $this->assertSame(1, $failedState['exit_code']);
        $this->assertNotNull($failedState['completed_at']);
        $this->assertStringContainsString('Maintenance unavailable.', file_get_contents($this->runtimePath('scheduler.log')));
        $this->assertSame('already_ran', $this->scheduler->runDue()['outcome']);

        $this->writeRuntime('run.json', array_replace($failedState, ['minute' => intdiv(time(), 60) - 1]));

        $this->assertSame(['outcome' => 'completed', 'exit_code' => 0], $this->scheduler->runDue());
        $this->assertSame(0, $this->readRuntime('run.json')['exit_code']);
        $this->assertStringContainsString('Maintenance recovered.', file_get_contents($this->runtimePath('scheduler.log')));
    }

    #[DataProvider('schedulerCommandResults')]
    public function test_scheduler_command_reports_the_run_outcome_and_propagates_its_exit_code(string $outcome, int $expectedExitCode): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $scheduler->shouldReceive('runDue')->once()->andReturn(['outcome' => $outcome, 'exit_code' => $expectedExitCode]);
        app()->instance(SchedulerControlService::class, $scheduler);

        $this->assertSame($expectedExitCode, Artisan::call('system:scheduler-run'));
        $this->assertStringContainsString($outcome, Artisan::output());
    }

    public static function schedulerCommandResults(): array
    {
        return [['completed', 0], ['completed', 1], ['busy', 0], ['disabled', 0]];
    }

    private function runtimePath(string $name): string
    {
        return storage_path('framework/testing/runtime-'.getmypid().'/'.$name);
    }

    private function writeRuntime(string $name, array $data): void
    {
        File::ensureDirectoryExists(dirname($this->runtimePath($name)));
        File::put($this->runtimePath($name), json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function readRuntime(string $name): array
    {
        return json_decode(file_get_contents($this->runtimePath($name)), true, 512, JSON_THROW_ON_ERROR);
    }
}
