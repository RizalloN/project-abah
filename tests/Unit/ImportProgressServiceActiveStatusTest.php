<?php

namespace Tests\Unit;

use App\Services\Import\ImportProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportProgressServiceActiveStatusTest extends TestCase
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

        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('id_report')->nullable();
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

    public function test_staging_jobs_are_treated_as_active_imports(): void
    {
        DB::table('import_jobs')->insert([
            'id' => 1,
            'id_report' => 39,
            'status' => 'staging',
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(ImportProgressService::class)->hasActiveProcessingJobs());
    }

    public function test_staging_jobs_are_treated_as_active_imports_for_table_scope(): void
    {
        DB::table('nama_report')->insert([
            'id_report' => 39,
            'table_name' => 'ssa_almafacts',
        ]);

        DB::table('import_jobs')->insert([
            'id' => 1,
            'id_report' => 39,
            'status' => 'staging',
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(ImportProgressService::class)->hasActiveProcessingJobsForTable('ssa_almafacts'));
        $this->assertFalse(app(ImportProgressService::class)->hasActiveProcessingJobsForTable('daily_loan_dinamis'));
    }
}
