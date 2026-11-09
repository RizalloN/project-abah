<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Queue\CustomDatabaseQueue;
use App\Services\Import\ImportProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportQueueCleanupTest extends TestCase
{
    public function test_cleanup_keeps_reserved_wrapper_until_worker_acknowledges_it(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('jobs', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('queue');
            $table->text('payload');
            $table->integer('attempts');
            $table->integer('reserved_at')->nullable();
            $table->integer('available_at');
            $table->integer('created_at');
        });

        $queue = new CustomDatabaseQueue(DB::connection(), 'jobs', 'imports-high', 7200);
        $queue->setContainer($this->app);
        $queue->setConnectionName('database');
        $runningId = $queue->push(new RunImportJob(14), '', 'imports-high');
        $running = $queue->pop('imports-high');
        $duplicateId = $queue->push(new RunImportJob(14), '', 'imports-high');

        app(ImportProgressService::class)->cleanupQueuedImportJobRowsForJob(14);

        $this->assertTrue(DB::table('jobs')->where('id', $runningId)->exists());
        $this->assertFalse(DB::table('jobs')->where('id', $duplicateId)->exists());
        $nextId = $queue->push(new RunImportJob(15), '', 'imports-high');
        $this->assertNotSame($runningId, $nextId);

        // Laravel acknowledges by numeric ID after the import handler returns.
        $running->delete();

        $this->assertTrue(DB::table('jobs')->where('id', $nextId)->exists());
        $this->assertSame($nextId, $queue->pop('imports-high')->getJobId());
    }
}
