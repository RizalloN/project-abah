<?php

namespace Tests\Unit;

use App\Support\DashboardHarianSnapshotDirtyPeriodQueue;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardHarianSnapshotDirtyPeriodQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_dirty_period_queue_coalesces_periods_until_consumed(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue();

        $this->assertTrue($queue->register(['2026-04-21']));
        $this->assertFalse($queue->register(['2026-04-22', '2026-04-21']));

        $this->assertSame(['2026-04-22', '2026-04-21'], $queue->consume());
        $this->assertTrue($queue->register(['2026-04-23']));
    }

    public function test_dirty_period_queue_uses_null_as_automatic_sync_marker(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue();

        $this->assertTrue($queue->register([null]));
        $this->assertNull($queue->consume());
    }

    public function test_abandoned_dispatch_lease_expires_without_losing_dirty_periods(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $this->assertTrue($queue->register(['2026-10-16']));
        $this->assertFalse($queue->register(['2026-10-17']));
        $this->travel(121)->seconds();
        try {
            $this->assertTrue($queue->register(['2026-10-18']));
            $this->assertSame(['2026-10-18', '2026-10-17', '2026-10-16'], $queue->consume());
        } finally {
            $this->travelBack();
        }
    }

    public function test_restoring_failed_work_preserves_new_arrivals(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16']);
        $consumed = $queue->consume();
        $this->assertTrue($queue->register(['2026-10-17']));
        $queue->restore($consumed);
        $this->assertSame(['2026-10-17', '2026-10-16'], $queue->consume());
    }

    public function test_previous_worker_cannot_acknowledge_a_recovered_claim(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16']);
        $first = $queue->claim();
        $queue->register(['2026-10-17']);
        $second = $queue->claim();
        $this->assertNotSame($first['token'], $second['token']);
        $queue->acknowledge($first['token']);

        $recovered = $queue->claim();
        $this->assertSame(['2026-10-17', '2026-10-16'], $recovered['periods']);
        $queue->acknowledge($recovered['token']);
        $this->assertSame([], $queue->claim()['periods']);
    }

    public function test_legacy_pending_marker_with_six_hour_ttl_can_be_replaced_after_lease(): void
    {
        $queue = new DashboardHarianSnapshotDirtyPeriodQueue;
        $queue->register(['2026-10-16']);
        Cache::put('snapshot:dashboard_harian:dirty_periods:pending', now()->subMinutes(3)->toIso8601String(), now()->addHours(6));

        $this->assertTrue($queue->register(['2026-10-17']));
        $this->assertSame(['2026-10-17', '2026-10-16'], $queue->claim()['periods']);
    }
}
