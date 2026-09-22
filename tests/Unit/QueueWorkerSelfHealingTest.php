<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureQueueWorkerRunning;
use App\Providers\AppServiceProvider;
use App\Services\Import\ImportProgressService;
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

    public function test_orphaned_reserved_jobs_are_released_for_import_pools(): void
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
        $this->assertNull($job->reserved_at, 'Orphaned reserved_at must be reset to null');
        $this->assertGreaterThanOrEqual($now - 5, $job->available_at, 'Available_at must be reset to now');
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

    public function test_is_worker_stuck_identifies_deadlocked_worker(): void
    {
        $command = new EnsureQueueWorkerRunning();
        $method = new ReflectionMethod($command, 'isWorkerStuck');

        $now = time();
        $pid = 99999;

        // Fresh heartbeat (10s ago) -> NOT stuck
        $this->assertFalse($method->invoke($command, $pid, $now - 10, $now, 'background'));

        // Missing heartbeat for 300s -> IS stuck
        $this->assertTrue($method->invoke($command, $pid, $now - 300, $now, 'background'));

        // For import pool, if active import job is running -> NOT stuck
        DB::table('import_jobs')->insert([
            'id' => 150,
            'status' => 'processing',
            'updated_at' => now(),
        ]);
        $lock = Cache::store('array')->lock('import_excel_execute_job_150', 60);
        Config::set('import.cache_store', 'array');
        $lock->get();

        try {
            $this->assertFalse($method->invoke($command, $pid, $now - 300, $now, 'imports-high'));
        } finally {
            $lock->release();
        }
    }
}
