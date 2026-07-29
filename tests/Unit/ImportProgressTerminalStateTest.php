<?php

namespace Tests\Unit;

use App\Services\Import\ImportProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportProgressTerminalStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Config::set('import.cache_store', 'array');
        Config::set('import.snapshot.pause_during_import', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::dropAllTables();

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('total_files')->default(0);
            $table->unsignedBigInteger('total_success')->default(0);
            $table->unsignedBigInteger('total_failed')->default(0);
            $table->string('job_fingerprint')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->nullable();
            $table->longText('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at')->nullable();
            $table->unsignedInteger('created_at')->nullable();
        });
    }

    public function test_terminal_totals_override_stale_processing_progress(): void
    {
        DB::table('import_jobs')->insert([
            'id' => 81,
            'status' => 'processing',
            'total_files' => 320512,
            'total_success' => 0,
            'total_failed' => 0,
            'job_fingerprint' => 'daily-loan-import',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::store('array')->put('import_job_progress:81', [
            'status' => 'processing',
            'processed_rows' => 320512,
            'total_rows' => 320512,
        ], now()->addHour());

        app(ImportProgressService::class)->updateTotals(
            81,
            320512,
            0,
            320512,
            'completed',
            [
                'percent' => 100,
                'message' => 'Import selesai.',
            ]
        );

        $job = DB::table('import_jobs')->where('id', 81)->first();
        $progress = Cache::store('array')->get('import_job_progress:81');

        $this->assertSame('completed', $job->status);
        $this->assertNull($job->job_fingerprint);
        $this->assertSame('completed', $progress['status']);
        $this->assertSame(320512, $progress['processed_rows']);
        $this->assertSame(320512, $progress['total_success']);
    }
}
