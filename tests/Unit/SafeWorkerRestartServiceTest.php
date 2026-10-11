<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Import\QueueSupervisorProcessRunner;
use App\Services\Import\QueueWorkerControlService;
use App\Services\Import\QueueWorkerMonitorService;
use App\Services\Import\SafeWorkerRestartService;
use App\Services\Import\WindowsDetachedProcessLauncher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SafeWorkerRestartServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
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
        // No live worker processes unless a test says otherwise.
        $monitor = Mockery::mock(QueueWorkerMonitorService::class)->makePartial();
        $monitor->shouldReceive('oldestLiveProcessStartedAt')->andReturn(null)->byDefault();
        $this->app->instance(QueueWorkerMonitorService::class, $monitor);
        // Linting is exercised in its own test; never lint the real repo here.
        $this->app->bind(SafeWorkerRestartService::class, function ($app) {
            $service = Mockery::mock(SafeWorkerRestartService::class, [$app->make(QueueWorkerControlService::class)])->makePartial();
            $service->shouldReceive('syntaxErrors')->andReturn([])->byDefault();

            return $service;
        });

        foreach (['jobs', 'import_jobs'] as $table) {
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
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->string('file_name')->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('import_jobs');
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    private function service(): SafeWorkerRestartService
    {
        return app(SafeWorkerRestartService::class);
    }

    private function queueJob(string $queue, ?int $reservedAt = null, ?int $availableAt = null): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue, 'payload' => '{}', 'attempts' => 0,
            'reserved_at' => $reservedAt, 'available_at' => $availableAt ?? time(), 'created_at' => time(),
        ]);
    }

    private function expectRestartSignal(int $times): void
    {
        Artisan::shouldReceive('call')->times($times)->with('queue:restart')->andReturn(0);
    }

    public function test_idle_system_restarts_immediately(): void
    {
        $this->expectRestartSignal(1);

        $result = $this->service()->request('Admin');

        $this->assertSame('restarted', $result['outcome']);
        $this->assertNull($this->service()->pending());
    }

    public function test_running_import_defers_restart_until_quiet_period_passes(): void
    {
        DB::table('import_jobs')->insert(['id' => 136, 'status' => 'processing', 'file_name' => 'a.xlsx', 'created_at' => now(), 'updated_at' => now()]);
        $this->expectRestartSignal(1);
        $service = $this->service();

        $result = $service->request('Admin');
        $this->assertSame('scheduled', $result['outcome']);
        $this->assertStringContainsString('Import #136', $result['busy'][0]['label']);

        $this->assertSame('waiting', $service->tick()['action']);

        // Import finished: the first quiet observation only starts the clock.
        DB::table('import_jobs')->update(['status' => 'completed']);
        $this->assertSame('settling', $service->tick()['action']);
        $this->assertSame('settling', $service->tick()['action']);

        $pending = $service->pending();
        $pending['idle_since'] = time() - 61;
        Cache::forever((new \ReflectionMethod(SafeWorkerRestartService::class, 'key'))->invoke($service, 'pending'), $pending);

        $this->assertSame('restarted', $service->tick()['action']);
        $this->assertNull($service->pending());
    }

    public function test_new_activity_during_quiet_period_resets_the_clock(): void
    {
        $this->expectRestartSignal(0);
        $service = $this->service();
        $this->queueJob('snapshots-parallel', time());
        $service->request('Admin');
        DB::table('jobs')->delete();
        $service->tick();
        $this->assertNotNull($service->pending()['idle_since']);

        $this->queueJob('snapshots-priority');
        $this->assertSame('waiting', $service->tick()['action']);
        $this->assertNull($service->pending()['idle_since']);
    }

    public function test_only_in_flight_work_blocks_restart(): void
    {
        $now = time();
        // Waiting cache warms, far-future schedules and abandoned imports do not block.
        $this->queueJob('reports-low');
        $this->queueJob('remote-sources');
        $this->queueJob('snapshots-parallel', null, $now + 3600);
        DB::table('import_jobs')->insert(['status' => 'processing', 'file_name' => 'old.xlsx', 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
        $this->assertSame([], $this->service()->busyReasons());

        // A follow-up phase due soon, waiting critical work, or any reserved job does.
        $this->queueJob('imports-high', null, $now + 60);
        $this->queueJob('reports-low', $now - 5);
        $labels = array_column($this->service()->busyReasons(), 'label');
        $this->assertContains('1 job menunggu di imports-high', $labels);
        $this->assertContains('1 job sedang diproses di reports-low', $labels);
    }

    public function test_code_change_after_workers_started_schedules_safe_restart(): void
    {
        $monitor = Mockery::mock(QueueWorkerMonitorService::class)->makePartial();
        $monitor->shouldReceive('oldestLiveProcessStartedAt')->andReturn(time() - 3600);
        $this->app->instance(QueueWorkerMonitorService::class, $monitor);
        $service = Mockery::mock(SafeWorkerRestartService::class, [app(QueueWorkerControlService::class)])->makePartial();
        $service->shouldReceive('syntaxErrors')->andReturn([]);
        $this->queueJob('imports-high', time());
        Artisan::shouldReceive('call')->never();

        // Code still being edited: wait for it to settle.
        $service->shouldReceive('codeChangedAt')->andReturn(time() - 30)->once();
        $service->tick();
        $this->assertNull($service->pending());

        $service->shouldReceive('codeChangedAt')->andReturn(time() - 600);
        $this->assertSame('waiting', $service->tick()['action']);
        $this->assertSame('code_change', $service->pending()['reason']);
    }

    public function test_monitor_restarting_itself_hands_leadership_to_a_replacement(): void
    {
        $control = new class extends QueueWorkerControlService
        {
            public ?bool $replacing = null;

            protected function startDetachedMonitor(bool $replacingCurrent = false): bool
            {
                $this->replacing = $replacingCurrent;

                return true;
            }
        };
        $control->touchMonitorHeartbeat();
        $this->assertSame(getmypid(), $control->monitorHeartbeatPid());
        $this->expectRestartSignal(1);

        $status = $control->restart('Otomatis');

        $this->assertTrue($control->replacing, 'The replacement must be launched despite this process being active.');
        $this->assertSame(0, $control->monitorHeartbeatPid(), 'Leadership must be released for the replacement.');
        $this->assertTrue($status['monitor_relaunched']);
    }

    public function test_restart_is_held_while_changed_code_does_not_parse(): void
    {
        Artisan::shouldReceive('call')->never();
        $service = Mockery::mock(SafeWorkerRestartService::class, [app(QueueWorkerControlService::class)])->makePartial();
        $service->shouldReceive('syntaxErrors')->andReturn(['app/Broken.php: PHP Parse error']);

        $this->assertSame('blocked', $service->request('Admin')['outcome']);

        Cache::forever((new \ReflectionMethod(SafeWorkerRestartService::class, 'key'))->invoke($service, 'pending'), [
            'requested_at' => time() - 300, 'requested_by' => 'Admin', 'reason' => 'manual', 'idle_since' => time() - 120,
        ]);
        $this->assertSame('blocked', $service->tick()['action']);
        $this->assertSame(['app/Broken.php: PHP Parse error'], $service->summary()['pending']['blocked_by']);
    }

    public function test_syntax_check_reports_only_unparseable_changed_files(): void
    {
        $dir = base_path('app/__lint_probe_'.getmypid());
        @mkdir($dir);
        file_put_contents($dir.'/Good.php', "<?php
return 1;
");
        file_put_contents($dir.'/Bad.php', "<?php
function (
");
        try {
            $monitor = Mockery::mock(QueueWorkerMonitorService::class)->makePartial();
            $monitor->shouldReceive('oldestLiveProcessStartedAt')->andReturn(time() - 5);
            $this->app->instance(QueueWorkerMonitorService::class, $monitor);
            touch($dir.'/Good.php', time());
            touch($dir.'/Bad.php', time());

            $errors = (new SafeWorkerRestartService(app(QueueWorkerControlService::class)))->syntaxErrors();

            $probeErrors = array_values(array_filter($errors, fn (string $error): bool => str_contains($error, '__lint_probe_')));
            $this->assertCount(1, $probeErrors);
            $this->assertStringContainsString('Bad.php', $probeErrors[0]);
        } finally {
            @unlink($dir.'/Good.php');
            @unlink($dir.'/Bad.php');
            @rmdir($dir);
        }
    }

    public function test_cancel_clears_a_scheduled_restart(): void
    {
        $this->expectRestartSignal(0);
        $this->queueJob('imports-high', time());
        $service = $this->service();
        $service->request('Admin');

        $this->assertTrue($service->cancel());
        $this->assertNull($service->pending());
        $this->assertSame('none', $service->tick()['action']);
    }

    public function test_restart_endpoint_schedules_when_busy_and_cancel_endpoint_clears_it(): void
    {
        $this->actingAs(User::factory()->make(['name' => 'Audit Worker', 'pn' => 'audit-worker', 'role' => 'admin']));
        $this->queueJob('snapshots-parallel', time());
        Artisan::shouldReceive('call')->never();

        $this->postJson(route('job-management.worker.restart'))
            ->assertStatus(202)
            ->assertJsonPath('scheduled', true)
            ->assertJsonPath('safe_restart.pending.requested_by', 'Audit Worker');

        $this->postJson(route('job-management.worker.restart.cancel'))
            ->assertOk()
            ->assertJsonPath('safe_restart.pending', null);
    }
}
