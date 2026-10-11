<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\QueueWorkerMonitorService;
use App\Services\Import\WindowsDetachedProcessLauncher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class QueueWorkerMonitorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('queue.worker_pools', [
            'imports' => ['queues' => 'imports-high', 'workers' => 2],
            'snapshots' => ['queues' => 'snapshots-parallel', 'workers' => 1],
        ]);
        Cache::flush();
        $runner = Mockery::mock(QueueSupervisorProcessRunner::class);
        $runner->shouldReceive('run')->andReturn([0, '', '']);
        $this->app->instance(QueueSupervisorProcessRunner::class, $runner);
        $launcher = Mockery::mock(WindowsDetachedProcessLauncher::class);
        $launcher->shouldReceive('setTaskEnabled')->andReturn(true);
        $launcher->shouldReceive('launch')->andReturn(null);
        $this->app->instance(WindowsDetachedProcessLauncher::class, $launcher);
        $control = new QueueWorkerControlService();
        Cache::forever((new \ReflectionMethod($control, 'key'))->invoke($control, 'enabled'), true);

        foreach (['jobs', 'failed_jobs'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->text('connection')->nullable();
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    protected function tearDown(): void
    {
        $control = new QueueWorkerControlService();
        $path = (new \ReflectionMethod($control, 'durableControlStatePath'))->invoke($control);
        if (is_file($path) && str_contains($path, 'queue-worker-control-'.getmypid().'.json')) {
            unlink($path);
        }
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    /** @param array<int, array{started_at:?int,memory_mb:?float}>|null $processes */
    private function monitor(?array $processes): QueueWorkerMonitorService
    {
        return new class(app(QueueWorkerControlService::class), app(QueueSupervisorProcessRunner::class), $processes) extends QueueWorkerMonitorService
        {
            public function __construct($control, $runner, private ?array $fakeProcesses)
            {
                parent::__construct($control, $runner);
            }

            protected function processTable(): ?array
            {
                return $this->fakeProcesses;
            }
        };
    }

    public function test_pools_report_live_workers_activity_and_stalled_queues(): void
    {
        $now = time();
        Cache::put('queue:worker-pool:pids:'.sha1('snapshots'), ['501' => $now, '502' => $now, '999' => $now], 600);
        Cache::put('queue:worker-pool:heartbeats:'.sha1('snapshots'), ['501' => $now - 2], 600);
        DB::table('jobs')->insert([
            ['queue' => 'imports-high', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now - 300, 'created_at' => $now - 300],
            ['queue' => 'snapshots-parallel', 'payload' => json_encode(['displayName' => 'App\\Jobs\\RebuildX']), 'attempts' => 2, 'reserved_at' => $now - 40, 'available_at' => $now - 50, 'created_at' => $now - 50],
            ['queue' => 'snapshots-parallel', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => $now + 600, 'created_at' => $now],
        ]);

        // PID 999 is registered but no longer running, so it is not a live worker.
        $snapshot = $this->monitor([501 => ['started_at' => $now - 100, 'memory_mb' => 40.0], 502 => ['started_at' => $now - 50, 'memory_mb' => 55.5]])->snapshot();
        $pools = collect($snapshot['pools'])->keyBy('name');

        $this->assertSame('stalled', $pools['imports']['state']);
        $this->assertSame(1, $pools['imports']['ready_jobs']);
        $this->assertSame(0, $pools['imports']['live_workers']);

        $snapshots = $pools['snapshots'];
        $this->assertSame('running', $snapshots['state']);
        $this->assertSame(2, $snapshots['live_workers']);
        $this->assertSame([501, 502], array_column($snapshots['workers'], 'pid'));
        $this->assertSame(['idle', 'busy'], array_column($snapshots['workers'], 'activity'));
        $this->assertSame(1, $snapshots['reserved_jobs']);
        $this->assertSame(1, $snapshots['delayed_jobs']);
        $this->assertSame('RebuildX', $snapshots['running_jobs'][0]['job']);
        $this->assertSame(1, $snapshot['totals']['stalled_pools']);
        $this->assertSame(1, $snapshot['totals']['busy_workers']);
    }

    public function test_without_process_listing_only_fresh_heartbeats_count_as_live(): void
    {
        $now = time();
        Cache::put('queue:worker-pool:pids:'.sha1('imports'), ['11' => $now, '12' => $now], 600);
        Cache::put('queue:worker-pool:heartbeats:'.sha1('imports'), ['11' => $now - 3, '12' => $now - 120], 600);

        $snapshot = $this->monitor(null)->snapshot();

        $this->assertFalse($snapshot['process_details_available']);
        $this->assertSame([11], array_column(collect($snapshot['pools'])->firstWhere('name', 'imports')['workers'], 'pid'));
    }

    public function test_workers_started_before_restart_signal_are_outdated_until_replaced(): void
    {
        $now = time();
        Cache::forever('illuminate:queue:restart', $now - 30);
        Cache::put('queue:worker-pool:pids:'.sha1('imports'), ['21' => $now, '22' => $now], 600);
        $snapshot = $this->monitor([
            21 => ['started_at' => $now - 600, 'memory_mb' => 30.0],
            22 => ['started_at' => $now - 10, 'memory_mb' => 30.0],
        ])->snapshot();

        $workers = collect(collect($snapshot['pools'])->firstWhere('name', 'imports')['workers'])->keyBy('pid');
        $this->assertTrue($workers[21]['outdated']);
        $this->assertFalse($workers[22]['outdated']);
        $this->assertSame(1, $snapshot['totals']['outdated_workers']);
    }

    public function test_restart_sends_graceful_signal_and_tracks_hand_over(): void
    {
        $control = app(QueueWorkerControlService::class);
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturnUsing(function (): int {
            Cache::forever('illuminate:queue:restart', time());

            return 0;
        });

        $status = $control->restart('Admin Test');

        $this->assertTrue($status['restart_requested']);
        $request = $control->lastRestartRequest();
        $this->assertSame('Admin Test', $request['requested_by']);
        $this->assertEqualsWithDelta(time(), $request['signal_at'], 2);

        // An old worker still finishing its job keeps the restart in progress.
        Cache::put('queue:worker-pool:pids:'.sha1('imports'), ['31' => time()], 600);
        $restart = $this->monitor([31 => ['started_at' => time() - 900, 'memory_mb' => 30.0]])->snapshot()['restart'];
        $this->assertSame('restarting', $restart['state']);
        $this->assertSame([31], $restart['pending_workers']);
    }

    public function test_missing_monitor_during_restart_hand_over_is_not_reported_as_outage(): void
    {
        $control = app(QueueWorkerControlService::class);
        $key = (new \ReflectionMethod($control, 'key'))->invoke($control, 'restart-request');

        Cache::put($key, ['requested_at' => time() - 20, 'requested_by' => 'Admin', 'signal_at' => time() - 20, 'previous_monitor_pid' => 77], 600);
        $status = $control->status();
        $this->assertSame('restarting', $status['state']);
        $this->assertSame('info', $status['tone']);

        // A monitor still absent long after the restart is a real outage again.
        Cache::put($key, ['requested_at' => time() - 600, 'requested_by' => 'Admin', 'signal_at' => time() - 600, 'previous_monitor_pid' => 77], 600);
        $this->assertSame('inactive', $control->status()['state']);
    }

    public function test_restart_is_rejected_while_workers_are_disabled(): void
    {
        $control = app(QueueWorkerControlService::class);
        Cache::forever((new \ReflectionMethod($control, 'key'))->invoke($control, 'enabled'), false);
        Artisan::shouldReceive('call')->never();

        $this->assertSame('disabled', $control->restart('Admin')['restart_rejected']);
    }

    public function test_failed_jobs_summary_reads_latest_rows_without_file_paths(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => 'a', 'connection' => 'database', 'queue' => 'imports-high',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ImportX']),
            'exception' => "RuntimeException: boom in D:\\XAMPP\\htdocs\\app\\X.php:10\nStack trace",
            'failed_at' => now()->subMinutes(10),
        ]);

        $failed = $this->monitor([])->snapshot()['failed_jobs'];

        $this->assertSame(1, $failed['last_hour']);
        $this->assertSame(1, $failed['last_24h']);
        $this->assertSame('ImportX', $failed['latest'][0]['job']);
        $this->assertSame('RuntimeException: boom', $failed['latest'][0]['error']);
    }

    public function test_restart_endpoint_returns_monitor_telemetry_and_rejects_when_disabled(): void
    {
        $this->actingAs(User::factory()->make(['name' => 'Audit Worker', 'pn' => 'audit-worker', 'role' => 'admin']));
        Artisan::shouldReceive('call')->once()->with('queue:restart')->andReturn(0);

        $this->postJson(route('job-management.worker.restart'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['worker_status', 'worker_monitor' => ['monitor', 'restart', 'pools', 'totals', 'failed_jobs']]);

        $control = app(QueueWorkerControlService::class);
        Cache::forever((new \ReflectionMethod($control, 'key'))->invoke($control, 'enabled'), false);
        $this->postJson(route('job-management.worker.restart'))
            ->assertStatus(409)
            ->assertJsonPath('status', 'error');
    }

    public function test_controller_resolves_worker_monitor_for_data_payload(): void
    {
        $controller = app(\App\Http\Controllers\Import\ImportJobManagementController::class);
        $monitor = (new \ReflectionMethod($controller, 'resolveWorkerMonitor'))->invoke($controller);

        $this->assertIsArray($monitor, 'The data endpoint must ship worker telemetry, not null.');
        $this->assertSame(['imports', 'snapshots'], array_column($monitor['pools'], 'name'));
    }

    public function test_job_management_page_renders_worker_monitor_and_restart_control(): void
    {
        $this->actingAs(User::factory()->make(['name' => 'Audit Worker', 'pn' => 'audit-worker', 'role' => 'admin']));

        $html = $this->get(route('job-management.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="btn-worker-restart"', $html);
        $this->assertStringContainsString(route('job-management.worker.restart'), $html);
        $this->assertStringContainsString('id="worker-pool-body"', $html);
        $this->assertStringContainsString('id="worker-failed-jobs"', $html);
    }
}
