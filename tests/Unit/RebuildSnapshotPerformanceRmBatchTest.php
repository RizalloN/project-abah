<?php

namespace Tests\Unit;

use App\Jobs\RebuildSnapshotPerformanceRmBatch;
use App\Support\ReportSnapshotBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class RebuildSnapshotPerformanceRmBatchTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_successful_performance_rm_batch_rebuild_bumps_report_cache_version(): void
    {
        Config::set('import.snapshot.enable_analyze_table', false);
        Cache::put('report_cache_version:global', 7, now()->addHours(24));

        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        $builder->shouldReceive('rebuildPerformanceRm')
            ->once()
            ->with('2026-05-06', true)
            ->andReturn(['2026-05-06' => 1461]);

        $job = new RebuildSnapshotPerformanceRmBatch('2026-05-06');
        $job->handle($builder);

        $this->assertSame(8, (int) Cache::get('report_cache_version:global'));
    }
}
