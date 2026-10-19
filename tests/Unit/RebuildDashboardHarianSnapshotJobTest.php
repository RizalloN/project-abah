<?php

namespace Tests\Unit;

use App\Jobs\RebuildDashboardHarianSnapshotJob;
use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use App\Support\DashboardHarianSnapshotService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class RebuildDashboardHarianSnapshotJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_failed_result_restores_consumed_period_and_can_be_retried(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-17']);
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->with(['2026-10-17'])->andReturn(['failed' => 1]);
        $job = new RebuildDashboardHarianSnapshotJob(null, true);
        try {
            $job->handle($service, $queue);
            $this->fail('The queue must retry a failed build.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('retained for retry', $e->getMessage());
        }
        $retry = Mockery::mock(DashboardHarianSnapshotService::class);
        $retry->shouldReceive('syncDuePeriods')->once()->with(['2026-10-17'])->andReturn(['failed' => 0, 'built' => 1]);
        $job->handle($retry, $queue);
        $this->assertSame([], $queue->consume());
    }

    public function test_next_worker_recovers_claim_after_previous_worker_dies_without_cleanup(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-17']);
        $abandoned = $queue->claim();
        $this->assertSame(['2026-10-17'], $abandoned['periods']);
        // Simulate process termination after claiming, without catch/finally.
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->with(['2026-10-17'])->andReturn(['failed' => 0, 'built' => 1]);
        (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);
        $this->assertSame([], $queue->claim()['periods']);
    }

    public function test_thrown_failure_restores_targets_and_preserves_arrivals_during_processing(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16']);
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->andReturnUsing(function () use ($queue): array {
            $queue->register(['2026-10-17']);
            throw new RuntimeException('database unavailable');
        });
        try {
            (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);
            $this->fail('The original failure must reach the queue retry mechanism.');
        } catch (RuntimeException $e) {
            $this->assertSame('database unavailable', $e->getMessage());
        }
        $this->assertSame(['2026-10-17', '2026-10-16'], $queue->consume());
    }

    public function test_success_does_not_remove_new_period_registered_during_processing(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16']);
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->andReturnUsing(function () use ($queue): array {
            $queue->register(['2026-10-17']);

            return ['failed' => 0, 'built' => 1];
        });
        (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);
        $this->assertSame(['2026-10-17'], $queue->consume());
    }

    public function test_force_rebuild_restores_only_failed_periods_and_propagates_failure(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('buildPeriodSnapshot')->once()->with('2026-10-16', true)->andReturn(115);
        $service->shouldReceive('buildPeriodSnapshot')->once()->with('2026-10-17', true)->andThrow(new RuntimeException('failed period'));
        $service->shouldReceive('buildPeriodSnapshot')->once()->with('2026-10-18', true)->andReturn(115);
        try {
            (new RebuildDashboardHarianSnapshotJob(['2026-10-16', '2026-10-17', '2026-10-18'], false, true))->handle($service, $queue);
            $this->fail('Partial failure must remain retryable.');
        } catch (RuntimeException $e) {
            $this->assertSame('failed period', $e->getPrevious()->getMessage());
        }
        $this->assertSame(['2026-10-17'], $queue->consume());
    }

    public function test_explicit_failed_period_list_restores_only_failures(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16', '2026-10-17']);
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->andReturn(['failed' => 1, 'failed_periods' => ['2026-10-17']]);
        try {
            (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);
            $this->fail('Reported failures must be retried.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('retained for retry', $e->getMessage());
        }
        $this->assertSame(['2026-10-17'], $queue->consume());
    }

    public function test_automatic_sync_failure_restores_automatic_marker(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register([null]);
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldReceive('syncDuePeriods')->once()->withNoArgs()->andThrow(new RuntimeException('automatic failure'));
        try {
            (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);
            $this->fail('Automatic failure must be retried.');
        } catch (RuntimeException $e) {
            $this->assertSame('automatic failure', $e->getMessage());
        }
        $this->assertNull($queue->consume());
    }

    public function test_empty_dirty_queue_skips_build_and_explicit_auto_job_still_runs(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $service = Mockery::mock(DashboardHarianSnapshotService::class);
        $service->shouldNotReceive('syncDuePeriods');
        (new RebuildDashboardHarianSnapshotJob(null, true))->handle($service, $queue);

        $automatic = Mockery::mock(DashboardHarianSnapshotService::class);
        $automatic->shouldReceive('syncDuePeriods')->once()->withNoArgs()->andReturn(['failed' => 0, 'built' => 0]);
        (new RebuildDashboardHarianSnapshotJob)->handle($automatic, $queue);
        $this->assertSame([], $queue->consume());
    }
}
