<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use App\Providers\AppServiceProvider;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\SnapshotQueuePauseService;
use App\Services\Import\QueueSupervisorProcessRunner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class QueueWorkerSelfHealingTest extends TestCase
{
    public function test_monitor_does_not_duplicate_an_already_queued_or_running_snapshot_audit(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $progress = \Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('hasActiveProcessingJobs')->andReturn(false);
        $this->app->instance(ImportProgressService::class, $progress);
        $row = DB::table('jobs')->insertGetId([
            'queue' => 'snapshots-parallel', 'reserved_at' => time(),
            'available_at' => time(), 'created_at' => time(),
            'payload' => json_encode(['displayName' => \App\Jobs\AuditAndHealSnapshotsJob::class]),
        ]);
        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'dispatchSnapshotAuditIfIdle');
        $method->invoke($command);
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\AuditAndHealSnapshotsJob::class);
        DB::table('jobs')->where('id', $row)->delete();
        $method->invoke($command);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\AuditAndHealSnapshotsJob::class, 1);
    }

    public function test_monitor_attempts_safe_recovery_before_terminal_stale_sweep(): void
    {
        $execution = \Mockery::mock(ImportExecutionService::class);
        $execution->shouldReceive('recoverOrphanedZeroProgressJobs')->once()->globally()->ordered()->andReturn([]);
        $progress = \Mockery::mock(ImportProgressService::class);
        $progress->shouldReceive('purgeStaleProcessingJobs')->once()->globally()->ordered()->andReturn(0);
        $pause = \Mockery::mock(SnapshotQueuePauseService::class);
        $pause->shouldReceive('resumeWhenNoActiveImports')->once()->andReturn(false);
        $this->app->instance(ImportExecutionService::class, $execution);
        $this->app->instance(ImportProgressService::class, $progress);
        $this->app->instance(SnapshotQueuePauseService::class, $pause);
        $command = new EnsureQueueWorkerRunning();
        (new ReflectionMethod($command, 'recoverOrphanedImports'))->invoke($command);
    }

    private string $originalDefaultConnection;
    private mixed $originalSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = (string) Config::get('database.default');
        $this->originalSqliteDatabase = Config::get('database.connections.sqlite.database');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('queue')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at')->nullable();
            $table->integer('created_at')->nullable();
            $table->longText('payload')->nullable();
        });

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_report')->nullable();
            $table->string('table_name')->nullable();
            $table->string('status')->index();
            $table->integer('total_success')->default(0);
            $table->integer('total_failed')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('nama_report', function (Blueprint $table): void {
            $table->unsignedInteger('id_report')->primary();
            $table->string('table_name')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Config::set('database.default', $this->originalDefaultConnection);
        Config::set('database.connections.sqlite.database', $this->originalSqliteDatabase);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        parent::tearDown();
    }

    public function test_idle_pool_skips_process_probes_and_uses_one_queue_query(): void
    {
        $runner = $this->createMock(QueueSupervisorProcessRunner::class);
        $runner->expects($this->never())->method('run');
        $this->app->instance(QueueSupervisorProcessRunner::class, $runner);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $command = new EnsureQueueWorkerRunning();
            (new ReflectionMethod($command, 'checkAndEnsureWorker'))->invoke(
                $command, 'idle-test', 'idle-test', 4, '0', '512', 25, 3600
            );
            $this->assertCount(1, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_failed_process_probe_preserves_registry_and_defers_launch(): void
    {
        $pool = 'probe-failure-test';
        $key = 'queue:worker-pool:pids:' . sha1($pool);
        $registered = ['987654' => time()];
        Cache::put($key, $registered, 60);
        DB::table('jobs')->insert([
            'queue' => $pool, 'available_at' => time(), 'created_at' => time(),
        ]);
        $runner = $this->createMock(QueueSupervisorProcessRunner::class);
        $runner->expects($this->once())->method('run')->willThrowException(new \RuntimeException('probe timeout'));
        $this->app->instance(QueueSupervisorProcessRunner::class, $runner);
        $command = new EnsureQueueWorkerRunning();
        (new ReflectionMethod($command, 'checkAndEnsureWorker'))->invoke(
            $command, $pool, $pool, 4, '0', '512', 25, 3600
        );

        $this->assertSame($registered, Cache::get($key));
        $this->assertNull(Cache::get('queue:worker-pool:lease:' . sha1($pool)));
        $this->assertNull(DB::table('jobs')->value('reserved_at'));
    }

    public function test_multi_queue_worker_covers_all_member_pools(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'queueWorkerPoolsCoveredBy');

        $allQueues = 'imports-high,imports-daily-loan,default,reports-low,remote-sources,snapshots-priority,snapshots-parallel,shadow-backfill';
        $covered = $method->invoke($provider, $allQueues);

        $poolNames = array_column($covered, 'name');

        $this->assertContains('imports-high', $poolNames);
        $this->assertContains('imports-daily-loan', $poolNames);
        $this->assertContains('background', $poolNames);
        $this->assertContains('remote-sources', $poolNames);
        $this->assertContains('snapshots-priority', $poolNames);
        $this->assertContains('snapshots', $poolNames);
        $this->assertContains('shadow-backfill', $poolNames);
    }

    public function test_short_stale_reservation_is_not_released_without_dead_owner_proof(): void
    {
        $now = time();

        // Insert a job that was reserved 5 minutes ago by a dead worker
        DB::table('jobs')->insert([
            'id' => 101,
            'queue' => 'imports-high',
            'attempts' => 1,
            'reserved_at' => $now - 300,
            'available_at' => $now - 300,
            'created_at' => $now - 300,
            'payload' => '{}',
        ]);

        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'checkAndEnsureWorker');

        // Invoke check for imports-high pool with zero live workers and timeout 0 to avoid starting process
        $method->invoke(
            $command,
            'imports-high',
            'imports-high',
            0, // desired workers 0 so it won't try to start new workers
            '0',
            '512',
            25,
            3600
        );

        $job = DB::table('jobs')->where('id', 101)->first();
        $this->assertNotNull($job);
        $this->assertSame($now - 300, (int) $job->reserved_at);
        $this->assertSame($now - 300, (int) $job->available_at);
    }

    public function test_has_live_processing_lease_returns_false_for_stale_reservation_without_lock(): void
    {
        $now = time();
        $jobId = 77;

        // Insert a job in database reserved 200s ago
        DB::table('jobs')->insert([
            'id' => 201,
            'queue' => 'imports-high',
            'attempts' => 1,
            'reserved_at' => $now - 200,
            'available_at' => $now - 200,
            'created_at' => $now - 200,
            'payload' => json_encode(['displayName' => 'App\\Jobs\\RunImportJob', 'data' => ['command' => serialize(new \stdClass())]]) . 'jobId;i:77;',
        ]);

        $service = app(ImportProgressService::class);
        $method = new ReflectionMethod($service, 'hasLiveProcessingLease');

        // Stale reservation with no heartbeat and no runtime lock
        $this->assertFalse($method->invoke($service, $jobId));

        // When runtime lock is held, it should be recognized as alive
        $lock = Cache::store('array')->lock('import_excel_execute_job_' . $jobId, 60);
        Config::set('import.cache_store', 'array');
        $lock->get();

        try {
            $this->assertTrue($method->invoke($service, $jobId));
        } finally {
            $lock->release();
        }
    }

    public function test_has_active_processing_jobs_for_table_ignores_abandoned_stale_jobs(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 8,
            'table_name' => 'daily_loan_dinamis',
        ]);

        // Insert an abandoned job that stopped updating 30 minutes ago
        DB::table('import_jobs')->insert([
            'id' => 99,
            'id_report' => 8,
            'status' => 'processing',
            'updated_at' => now()->subMinutes(30),
        ]);

        $service = app(ImportProgressService::class);

        // Without live lease and updated > 120s ago, it should NOT block new imports for the table
        $this->assertFalse($service->hasActiveProcessingJobsForTable('daily_loan_dinamis'));

        // If updated recently (within 120s), it SHOULD be treated as active
        DB::table('import_jobs')->where('id', 99)->update(['updated_at' => now()]);
        $this->assertTrue($service->hasActiveProcessingJobsForTable('daily_loan_dinamis'));
    }

    public function test_looping_heartbeat_age_alone_never_force_kills_live_worker(): void
    {
        $source = file_get_contents(app_path('Console/Commands/EnsureQueueWorkerRunning.php'));

        $this->assertStringNotContainsString('taskkill /F', $source);
        $this->assertStringNotContainsString('kill -9', $source);
        $this->assertStringContainsString('Never force-kill an exact live PID from heartbeat age alone.', $source);
    }
}
