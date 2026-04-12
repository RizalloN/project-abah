<?php

namespace Tests\Unit;

use App\Support\PartitionMaintenanceService;
use App\Support\ReportDataSyncService;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ReportDataSyncServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rasio_snapshot_rebuild_uses_dedicated_lock(): void
    {
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $service = new ReportDataSyncService($builder, $partitionMaintenance);

        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('lock')
            ->once()
            ->with('snapshot:rasio:rebuild:global', 120)
            ->andReturn($lock);

        $lock->shouldReceive('block')
            ->once()
            ->with(10, Mockery::type('callable'))
            ->andReturnUsing(function (int $seconds, callable $callback) {
                return $callback();
            });
        $lock->shouldReceive('release')->once();

        $reflection = new \ReflectionMethod($service, 'runWithRasioSnapshotLock');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($service, function () use ($builder) {
            $builder->shouldReceive('rebuildRasioCasa')
                ->once()
                ->with('2026-04', true)
                ->andReturn(['2026-04-30' => 12]);

            return $builder->rebuildRasioCasa('2026-04', true);
        });

        $this->assertSame(['2026-04-30' => 12], $result);
    }

    public function test_simpanan_sync_rebuilds_rasio_using_the_import_period_hint(): void
    {
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $service = new ReportDataSyncService($builder, $partitionMaintenance);

        $simpananLock = Mockery::mock(Lock::class);
        $lock = Mockery::mock(Lock::class);

        Cache::shouldReceive('lock')
            ->once()
            ->with('snapshot:simpanan:rebuild:2026-04-04', 180)
            ->andReturn($simpananLock);

        $simpananLock->shouldReceive('block')
            ->once()
            ->with(10, Mockery::type('callable'))
            ->andReturnUsing(function (int $seconds, callable $callback) {
                return $callback();
            });
        $simpananLock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->once()
            ->with('snapshot:rasio:rebuild:global', 120)
            ->andReturn($lock);

        $lock->shouldReceive('block')
            ->once()
            ->with(10, Mockery::type('callable'))
            ->andReturnUsing(function (int $seconds, callable $callback) {
                return $callback();
            });
        $lock->shouldReceive('release')->once();

        $builder->shouldReceive('rebuildDashboardSimpanan')
            ->once()
            ->with('2026-04-04', true)
            ->andReturn(['2026-04-04' => 1]);
        $builder->shouldReceive('rebuildRekeningDormant')
            ->once()
            ->with('2026-04-04', true)
            ->andReturn(['2026-04-04' => 1]);
        $builder->shouldReceive('rebuildRasioCasa')
            ->once()
            ->with('2026-04-04', true)
            ->andReturn(['2026-04-04' => 1]);

        $reflection = new \ReflectionMethod($service, 'syncSimpanan');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-04-04', 99, 'unit-test');

        $this->assertTrue(true);
    }

    public function test_performance_sync_rebuilds_new_payroll_using_the_import_period_hint(): void
    {
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $partitionMaintenance = Mockery::mock(PartitionMaintenanceService::class);
        $service = new ReportDataSyncService($builder, $partitionMaintenance);

        $builder->shouldReceive('rebuildPerformanceNewPayroll')
            ->once()
            ->with('2026-04-30', true)
            ->andReturn(['2026-04-30' => 4]);

        $reflection = new \ReflectionMethod($service, 'syncPerformanceNewPayroll');
        $reflection->setAccessible(true);

        $reflection->invoke($service, '2026-04-30', 77, 'unit-test');

        $this->assertTrue(true);
    }
}
