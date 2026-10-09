<?php

namespace Tests\Unit;

use App\Jobs\WarmLandingSmeQuadrantsJob;
use App\Support\LandingCacheWorkerGuard;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LandingCacheWorkerGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('reserved_at')->nullable();
            $table->integer('attempts');
            $table->text('payload');
        });
    }

    public function test_execution_marker_clears_on_completion_and_does_not_track_imports(): void
    {
        $guard = new LandingCacheWorkerGuard();
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getConnectionName')->andReturn('database');
        $job->shouldReceive('payload')->andReturn(['displayName' => WarmLandingSmeQuadrantsJob::class, 'timeout' => 900, 'uuid' => 'test']);
        $job->shouldReceive('getJobId')->andReturn('1');
        $job->shouldReceive('attempts')->andReturn(1);
        $guard->started($job, 123);
        $this->assertTrue(Cache::has('queue:landing-cache:execution:123'));
        $guard->finished(123);
        $this->assertFalse(Cache::has('queue:landing-cache:execution:123'));
        $import = Mockery::mock(Job::class);
        $import->shouldReceive('getConnectionName')->andReturn('database');
        $import->shouldReceive('payload')->andReturn(['displayName' => 'App\\Jobs\\RunImportJob', 'timeout' => 900]);
        $guard->started($import, 123);
        $this->assertFalse(Cache::has('queue:landing-cache:execution:123'));
    }

    public function test_only_matching_overdue_child_is_stopped_and_reservation_is_preserved(): void
    {
        $process = proc_open([PHP_BINARY, '-r', 'sleep(30);'], [], $pipes);
        $this->assertIsResource($process);
        try {
            $pid = proc_get_status($process)['pid'];
            $key = 'queue:landing-cache:execution:'.$pid;
            $guard = new LandingCacheWorkerGuard();
            $id = DB::table('jobs')->insertGetId(['reserved_at' => time(), 'attempts' => 1, 'payload' => json_encode(['uuid' => 'owned'])]);
            $before = DB::table('jobs')->where('id', $id)->first();
            $record = ['job_id' => $id, 'uuid' => 'owned', 'class' => WarmLandingSmeQuadrantsJob::class, 'attempts' => 1, 'started_at' => microtime(true), 'timeout' => 900];
            Cache::put($key, $record);
            $this->assertFalse($guard->stopOverdueChild($process, $pid, $record['started_at'] - 1));
            $record['started_at'] -= 1000;
            Cache::put($key, $record);
            // A reused PID, another reservation, or another process handle must remain untouched.
            $this->assertFalse($guard->stopOverdueChild($process, $pid, microtime(true)));
            DB::table('jobs')->where('id', $id)->update(['attempts' => 2]);
            $this->assertFalse($guard->stopOverdueChild($process, $pid, $record['started_at'] - 1));
            DB::table('jobs')->where('id', $id)->update(['attempts' => 1]);
            $this->assertTrue(proc_get_status($process)['running']);
            $this->assertTrue($guard->stopOverdueChild($process, $pid, $record['started_at'] - 1));
            $this->assertEquals($before, DB::table('jobs')->where('id', $id)->first());
            $this->assertFalse(Cache::has($key));
        } finally {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
    }
}
