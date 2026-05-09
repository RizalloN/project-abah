<?php

namespace Tests\Unit;

use App\Jobs\ExecuteBatchedSnapshotJob;
use ReflectionMethod;
use Tests\TestCase;

class ExecuteBatchedSnapshotJobTest extends TestCase
{
    public function test_compact_requests_ignores_rebuild_id_for_dedup_scope(): void
    {
        $job = new ExecuteBatchedSnapshotJob('simpanan_multipn:2026-04-30', [
            [
                'table_name' => 'simpanan_multipn',
                'period_hint' => '2026-04-30',
                'job_id' => 10,
                'source' => 'first',
                'rebuild_id' => 'rebuild-old',
            ],
            [
                'table_name' => 'simpanan_multipn',
                'period_hint' => '2026-04-30',
                'job_id' => 11,
                'source' => 'second',
                'rebuild_id' => 'rebuild-new',
            ],
        ]);

        $method = new ReflectionMethod($job, 'compactRequests');
        $method->setAccessible(true);

        $requests = $method->invoke($job, $job->requests);

        $this->assertCount(1, $requests);
        $this->assertSame(11, $requests[0]['job_id']);
        $this->assertSame('second', $requests[0]['source']);
        $this->assertSame('rebuild-new', $requests[0]['rebuild_id']);
    }
}
