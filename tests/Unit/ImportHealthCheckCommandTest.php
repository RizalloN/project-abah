<?php

namespace Tests\Unit;

use App\Console\Commands\ImportHealthCheckCommand;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ImportHealthCheckCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_health_check_does_not_classify_import_load_data_as_killable(): void
    {
        $command = new ImportHealthCheckCommand();
        $method = new \ReflectionMethod($command, 'shouldIgnoreSlowQuery');
        $method->setAccessible(true);

        $loadData = "LOAD DATA LOCAL INFILE 'D:/tmp/ponorogo.txt' INTO TABLE `simpanan_multipn`";
        $deleteScope = "DELETE FROM `simpanan_multipn` WHERE `posisi` IN ('2026-06-05')";
        $snapshotBuild = 'INSERT INTO performance_rm_snapshots SELECT * FROM daily_loan_dinamis';

        $this->assertTrue($method->invoke($command, strtolower($loadData)));
        $this->assertTrue($method->invoke($command, strtolower($deleteScope)));
        $this->assertTrue($method->invoke($command, strtolower($snapshotBuild)));
        $this->assertFalse($method->invoke($command, 'select sleep(90)'));
    }

    public function test_health_check_reconciles_active_import_jobs_through_progress_service(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->once()->with('status', 'processing')->andReturnSelf();
        $query->shouldReceive('orderBy')->once()->with('updated_at')->andReturnSelf();
        $query->shouldReceive('limit')->once()->with(100)->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('id')->andReturn(collect([249, 250]));
        DB::shouldReceive('table')->once()->with('import_jobs')->andReturn($query);

        $progressService = Mockery::mock(ImportProgressService::class);
        $progressService->shouldReceive('getStatusPayload')->once()->with(249)->andReturn(['status' => 'failed']);
        $progressService->shouldReceive('getStatusPayload')->once()->with(250)->andReturn(['status' => 'processing']);

        $command = new ImportHealthCheckCommand();
        $method = new \ReflectionMethod($command, 'reconcileProcessingJobs');
        $method->invoke($command, $progressService);

        $this->addToAssertionCount(1);
    }

    public function test_health_check_counts_snapshot_dead_letters(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('failed_snapshot_dirty_periods')->andReturnTrue();

        $query = Mockery::mock();
        $query->shouldReceive('count')->once()->andReturn(5);
        DB::shouldReceive('table')->once()->with('failed_snapshot_dirty_periods')->andReturn($query);

        $command = new ImportHealthCheckCommand();
        $method = new \ReflectionMethod($command, 'countFailedSnapshotDirtyPeriods');

        $this->assertSame(5, $method->invoke($command));
    }
}
