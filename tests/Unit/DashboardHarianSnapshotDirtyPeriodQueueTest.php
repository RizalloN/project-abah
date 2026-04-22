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
}
