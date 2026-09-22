<?php

namespace Tests\Unit;

use App\Jobs\ProcessShadowBackfillJob;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ProcessShadowBackfillJobTest extends TestCase
{
    public function test_shadow_backfill_job_uses_dedicated_queue_by_default(): void
    {
        config(['queue.shadow_backfill_queue' => 'shadow-backfill']);

        $job = new ProcessShadowBackfillJob(['2026-04-26']);

        $this->assertSame('shadow-backfill', $job->queue);
    }

    public function test_shadow_backfill_job_passes_sleep_delay_to_artisan_command(): void
    {
        $job = new ProcessShadowBackfillJob(['2026-04-26'], 1234, 750, 5, 'shadow-custom', true);

        Artisan::shouldReceive('call')
            ->once()
            ->with('shadow:backfill', \Mockery::on(function (array $arguments): bool {
                return $arguments['--periods'] === '2026-04-26'
                    && $arguments['--chunk-size'] === 1234
                    && $arguments['--delay'] === 750
                    && $arguments['--retry-count'] === 5
                    && $arguments['--skip-snapshot'] === true
                    && $arguments['--no-interaction'] === true;
            }))
            ->andReturn(0);

        $job->handle();

        $this->assertSame('shadow-custom', $job->queue);
    }

    public function test_shadow_backfill_job_middleware_includes_defer_during_import_and_without_overlapping(): void
    {
        $job = new ProcessShadowBackfillJob(['2026-04-26', '2026-04-25']);
        $middleware = $job->middleware();

        $this->assertCount(3, $middleware);
        $this->assertInstanceOf(\App\Jobs\Middleware\DeferSnapshotJobsDuringImport::class, $middleware[0]);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[1]);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $middleware[2]);

        // Sorted periods
        $this->assertSame('shadow:backfill:auto:2026-04-25', $middleware[1]->key);
        $this->assertSame('shadow:backfill:auto:2026-04-26', $middleware[2]->key);
    }

    public function test_shadow_backfill_job_generates_deterministic_unique_id(): void
    {
        $job1 = new ProcessShadowBackfillJob(['2026-04-26', '2026-04-25']);
        $job2 = new ProcessShadowBackfillJob(['2026-04-25', '2026-04-26']);

        $this->assertSame($job1->uniqueId(), $job2->uniqueId());
        $this->assertStringStartsWith('shadow-backfill:', $job1->uniqueId());
    }
}
