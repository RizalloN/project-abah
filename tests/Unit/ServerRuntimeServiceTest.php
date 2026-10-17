<?php

namespace Tests\Unit;

use App\Services\Import\QueueWorkerControlService;
use App\Services\SchedulerControlService;
use App\Services\ServerRuntimeService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServerRuntimeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        app(Kernel::class)->bootstrap();
    }

    public function test_status_reads_observed_services_and_worker_state_without_starting_anything(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $inspection = ['scheduler' => ['registered' => true], 'infrastructure' => $this->infrastructure()];
        $scheduler->shouldReceive('inspect')->once()->with(false)->andReturn($inspection);
        $scheduler->shouldReceive('status')->once()->with($inspection)->andReturn(['state' => 'active']);
        $scheduler->shouldNotReceive('control');
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => true, 'command' => QueueWorkerControlService::COMMAND]);
        $workers->shouldNotReceive('enable');
        $workers->shouldNotReceive('disable');
        $workers->shouldNotReceive('restart');

        $snapshot = (new ServerRuntimeService($scheduler, $workers))->snapshot();

        $this->assertTrue($snapshot['healthy']);
        $this->assertSame(QueueWorkerControlService::COMMAND, $snapshot['worker']['command']);
        $this->assertSame($inspection['infrastructure'], $snapshot['infrastructure']);
        $this->assertNotEmpty($snapshot['generated_at']);
    }

    #[DataProvider('healthStates')]
    public function test_health_requires_a_verified_scheduler_worker_heartbeat_and_all_infrastructure(string $schedulerState, bool $monitorActive, array $serviceStates, bool $healthy): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $infrastructure = array_map(fn (string $state): array => ['state' => $state], $serviceStates);
        $scheduler->shouldReceive('inspect')->once()->with(false)->andReturn(['infrastructure' => $infrastructure]);
        $scheduler->shouldReceive('status')->once()->andReturn(['state' => $schedulerState]);
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => $monitorActive]);

        $this->assertSame($healthy, (new ServerRuntimeService($scheduler, $workers))->snapshot()['healthy']);
    }

    public static function healthStates(): array
    {
        return [
            ['active', true, ['running', 'running', 'running'], true],
            ['external', true, ['running', 'running', 'running'], true],
            ['starting', true, ['running', 'running', 'running'], false],
            ['active', false, ['running', 'running', 'running'], false],
            ['active', true, ['running', 'running', 'stopped'], false],
            ['active', true, ['running', 'running'], false],
            ['error', true, ['running', 'running', 'running'], false],
        ];
    }

    public function test_start_all_attempts_independent_services_and_worker_even_when_other_operations_fail(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $infrastructure = $this->infrastructure();
        $infrastructure[0]['state'] = 'stopped';
        $infrastructure[2]['state'] = 'stopped';
        $inspection = ['infrastructure' => $infrastructure];
        $scheduler->shouldReceive('inspect')->twice()->with(true)->andReturn($inspection);
        $scheduler->shouldReceive('control')->once()->with('start', 'apache')->andThrow(new \RuntimeException('Apache start denied.'));
        $scheduler->shouldReceive('control')->once()->with('start', 'tunnel')->andReturn(['success' => true, 'message' => 'Tunnel launch accepted.']);
        $scheduler->shouldReceive('control')->once()->with('start')->andThrow(new \RuntimeException('Scheduler task denied.'));
        $scheduler->shouldReceive('status')->once()->with($inspection)->andReturn(['state' => 'stopped']);
        $workers->shouldReceive('enable')->once()->andReturn(['started' => true, 'startup_confirmed' => false, 'monitor_active' => false, 'message' => 'Monitor startup accepted.']);
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => false]);
        $workers->shouldNotReceive('disable');
        $workers->shouldNotReceive('restart');
        Log::shouldReceive('notice')->once()->with('Server runtime controlled from Job Management.', Mockery::on(fn (array $context): bool => $context['actor'] === '90179583' && $context['service'] === 'all'));

        $result = (new ServerRuntimeService($scheduler, $workers))->control('all', 'start', '90179583');

        $this->assertSame('warning', $result['status']);
        $this->assertSame(['apache', 'tunnel', 'scheduler', 'worker'], array_column($result['results'], 'service'));
        $this->assertSame([false, true, false, true], array_column($result['results'], 'success'));
        $this->assertFalse($result['runtime']['healthy']);
        $this->assertStringContainsString('Apache start denied.', $result['message']);
        $this->assertStringContainsString('Scheduler task denied.', $result['message']);
    }

    public function test_accepted_start_requests_do_not_override_the_observed_unhealthy_runtime(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $scheduler->shouldReceive('control')->once()->with('start', null)->andReturn(['success' => true, 'message' => 'Task enabled.']);
        $scheduler->shouldReceive('inspect')->once()->with(true)->andReturn(['infrastructure' => $this->infrastructure()]);
        $scheduler->shouldReceive('status')->once()->andReturn(['state' => 'starting', 'heartbeat_at' => null]);
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => true]);
        $workers->shouldNotReceive('enable');
        Log::shouldReceive('notice')->once();

        $result = (new ServerRuntimeService($scheduler, $workers))->control('scheduler', 'start', '90179583');

        $this->assertSame('success', $result['status']);
        $this->assertFalse($result['runtime']['healthy']);
        $this->assertSame('starting', $result['runtime']['scheduler']['state']);
        $this->assertNull($result['runtime']['scheduler']['heartbeat_at']);
    }

    public function test_schedule_rows_derive_from_the_registered_events_including_a_new_event(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $scheduler->shouldReceive('inspect')->twice()->andReturn(['infrastructure' => []]);
        $scheduler->shouldReceive('status')->twice()->andReturn(['state' => 'stopped']);
        $workers->shouldReceive('status')->twice()->andReturn(['monitor_active' => false]);
        $service = new ServerRuntimeService($scheduler, $workers);
        $events = app(Schedule::class)->events();

        $before = $service->snapshot()['schedules'];

        $this->assertCount(count($events), $before);
        $this->assertGreaterThanOrEqual(20, count($before));
        $this->assertContains('scheduler:heartbeat', array_column($before, 'command'));
        $this->assertContains('database:backup-daily', array_column($before, 'command'));
        foreach ($events as $index => $event) {
            $this->assertSame($event->expression, $before[$index]['expression']);
            $this->assertSame($event->timezone ?? config('app.timezone'), $before[$index]['timezone']);
            $this->assertNotEmpty($before[$index]['next_run_at']);
        }

        app(Schedule::class)->command('runtime:test-schedule')->cron('13 4 * * *')->timezone('Asia/Jakarta');
        $after = $service->snapshot()['schedules'];
        $this->assertCount(count($before) + 1, $after);
        $this->assertSame('runtime:test-schedule', $after[array_key_last($after)]['command']);
        $this->assertSame('13 4 * * *', $after[array_key_last($after)]['expression']);
        $this->assertSame('Asia/Jakarta', $after[array_key_last($after)]['timezone']);
    }

    #[DataProvider('invalidServiceControls')]
    public function test_service_api_rejects_invalid_control_pairs_before_querying_or_changing_runtime(string $service, string $action): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $scheduler->shouldNotReceive('inspect');
        $scheduler->shouldNotReceive('status');
        $scheduler->shouldNotReceive('control');
        $workers->shouldNotReceive('enable');
        $this->expectException(\InvalidArgumentException::class);

        (new ServerRuntimeService($scheduler, $workers))->control($service, $action, '90179583');
    }

    public static function invalidServiceControls(): array
    {
        return [['all', 'stop'], ['apache', 'restart'], ['mysql', 'stop'], ['tunnel', 'run'], ['redis', 'start'], ['scheduler', 'start; whoami'], ['backup', 'start'], ['backup', 'stop']];
    }

    public function test_readiness_reports_every_reboot_requirement_with_a_fix_hint(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $infrastructure = [
            ['key' => 'apache', 'label' => 'Apache', 'state' => 'running', 'automatic' => true],
            ['key' => 'mysql', 'label' => 'MySQL', 'state' => 'running', 'automatic' => false],
            ['key' => 'tunnel', 'label' => 'Cloudflare Tunnel', 'state' => 'stopped', 'automatic' => true],
        ];
        $backup = ['available' => true, 'registered' => true, 'enabled' => true, 'owned' => true, 'state' => 'ready', 'last_result' => 1];
        $scheduler->shouldReceive('inspect')->once()->with(false)->andReturn(['infrastructure' => $infrastructure, 'backup' => $backup]);
        $scheduler->shouldReceive('status')->once()->andReturn([
            'state' => 'active', 'available' => true, 'task_enabled' => true, 'startup_mode' => 'login',
            'has_boot_trigger' => false, 'needs_upgrade' => true, 'legacy_count' => 0,
        ]);
        $workers->shouldReceive('status')->once()->andReturn(['enabled' => false, 'monitor_active' => false]);

        $snapshot = (new ServerRuntimeService($scheduler, $workers))->snapshot();
        $items = collect($snapshot['readiness'])->keyBy('key');

        $this->assertFalse($snapshot['restart_ready']);
        $this->assertTrue($items['service-apache']['ok']);
        $this->assertFalse($items['service-mysql']['ok']);
        $this->assertStringContainsString('otomatis', $items['service-mysql']['hint']);
        $this->assertFalse($items['service-tunnel']['ok']);
        $this->assertFalse($items['scheduler-task']['ok']);
        $this->assertTrue($items['scheduler-heartbeat']['ok']);
        $this->assertFalse($items['worker']['ok']);
        $this->assertFalse($items['backup']['ok']);
        $this->assertTrue($snapshot['backup']['failed']);
        $this->assertTrue($snapshot['backup']['runnable']);
    }

    public function test_a_fully_prepared_server_is_restart_ready(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $infrastructure = array_map(fn (array $item): array => $item + ['automatic' => true], $this->infrastructure());
        $backup = ['available' => true, 'registered' => true, 'enabled' => true, 'owned' => true, 'state' => 'running', 'last_result' => 267009];
        $scheduler->shouldReceive('inspect')->once()->andReturn(['infrastructure' => $infrastructure, 'backup' => $backup]);
        $scheduler->shouldReceive('status')->once()->andReturn([
            'state' => 'active', 'available' => true, 'task_enabled' => true, 'startup_mode' => 'system',
            'has_boot_trigger' => true, 'needs_upgrade' => false, 'legacy_count' => 0,
        ]);
        $workers->shouldReceive('status')->once()->andReturn(['enabled' => true, 'monitor_active' => true]);

        $snapshot = (new ServerRuntimeService($scheduler, $workers))->snapshot();

        $this->assertTrue($snapshot['restart_ready']);
        $this->assertSame([true], array_values(array_unique(array_column($snapshot['readiness'], 'ok'))));
        $this->assertFalse($snapshot['backup']['runnable'], 'A running backup must not be started twice.');
    }

    public function test_a_system_task_with_an_indefinite_repeating_trigger_is_restart_ready_without_a_boot_trigger(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $scheduler->shouldReceive('inspect')->once()->andReturn(['infrastructure' => [], 'backup' => ['available' => true, 'registered' => false]]);
        $scheduler->shouldReceive('status')->once()->andReturn([
            'state' => 'active', 'available' => true, 'task_enabled' => true, 'startup_mode' => 'system',
            'has_boot_trigger' => false, 'has_repeating_trigger' => true, 'needs_upgrade' => false, 'legacy_count' => 0,
        ]);
        $workers->shouldReceive('status')->once()->andReturn(['enabled' => true, 'monitor_active' => true]);

        $items = collect((new ServerRuntimeService($scheduler, $workers))->snapshot()['readiness'])->keyBy('key');

        $this->assertTrue($items['scheduler-task']['ok']);
        $this->assertSame('', $items['scheduler-task']['hint']);
    }

    public function test_start_all_also_restores_automatic_startup_of_a_running_manual_service(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $infrastructure = array_map(fn (array $item): array => $item + ['automatic' => true], $this->infrastructure());
        $infrastructure[1]['automatic'] = false;
        $scheduler->shouldReceive('inspect')->with(true)->andReturn(['infrastructure' => $infrastructure]);
        $scheduler->shouldReceive('control')->once()->with('start', 'mysql')->andReturn(['success' => true, 'message' => 'MySQL sudah berjalan. Startup diubah menjadi otomatis.']);
        $scheduler->shouldReceive('control')->once()->with('start')->andReturn(['success' => true, 'message' => 'ok']);
        $scheduler->shouldReceive('status')->once()->andReturn(['state' => 'active']);
        $workers->shouldReceive('enable')->once()->andReturn(['started' => true]);
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => true]);
        Log::shouldReceive('notice')->once();

        $result = (new ServerRuntimeService($scheduler, $workers))->control('all', 'start', '90179583');

        $this->assertSame(['mysql', 'scheduler', 'worker'], array_column($result['results'], 'service'));
        $this->assertSame('success', $result['status']);
    }

    public function test_backup_run_is_forwarded_to_the_native_backup_task_only(): void
    {
        $scheduler = Mockery::mock(SchedulerControlService::class);
        $workers = Mockery::mock(QueueWorkerControlService::class);
        $scheduler->shouldReceive('control')->once()->with('run', 'backup')->andReturn(['success' => true, 'message' => 'Backup dijalankan.']);
        $scheduler->shouldReceive('inspect')->once()->with(true)->andReturn(['infrastructure' => $this->infrastructure()]);
        $scheduler->shouldReceive('status')->once()->andReturn(['state' => 'active']);
        $workers->shouldReceive('status')->once()->andReturn(['monitor_active' => true]);
        $workers->shouldNotReceive('enable');
        Log::shouldReceive('notice')->once();

        $result = (new ServerRuntimeService($scheduler, $workers))->control('backup', 'run', '90179583');

        $this->assertSame('success', $result['status']);
        $this->assertSame(['backup'], array_column($result['results'], 'service'));
    }

    private function infrastructure(): array
    {
        return [
            ['key' => 'apache', 'state' => 'running'],
            ['key' => 'mysql', 'state' => 'running'],
            ['key' => 'tunnel', 'state' => 'running'],
        ];
    }
}
