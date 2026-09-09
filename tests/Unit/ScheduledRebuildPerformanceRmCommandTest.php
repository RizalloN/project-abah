<?php

namespace Tests\Unit;

use App\Support\ReportSnapshotBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ScheduledRebuildPerformanceRmCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduled_command_uses_public_period_rebuild_api(): void
    {
        Carbon::setTestNow('2026-09-09 08:00:00');
        Cache::flush();
        $periods = [
            '2026-09-09',
            '2026-09-08',
            '2026-09-02',
            '2026-09-30',
            '2026-08-31',
            '2025-09-09',
        ];
        $builder = Mockery::mock(ReportSnapshotBuilder::class);
        foreach ($periods as $period) {
            $builder->shouldReceive('rebuildPerformanceRm')
                ->once()
                ->with($period, false)
                ->andReturn([$period => 1]);
        }
        $this->app->instance(ReportSnapshotBuilder::class, $builder);

        $this->artisan('snapshot:rebuild-rm-scheduled')
            ->expectsOutputToContain('"rebuilt_periods": 6')
            ->assertExitCode(0);
    }
}
