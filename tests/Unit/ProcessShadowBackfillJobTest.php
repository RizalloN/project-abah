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
        $job = new ProcessShadowBackfillJob(['2026-04-26'], 1234, 750, 5, 'shadow-custom');

        Artisan::shouldReceive('call')
            ->once()
            ->with('shadow:backfill', \Mockery::on(function (array $arguments): bool {
                return $arguments['--periods'] === '2026-04-26'
                    && $arguments['--chunk-size'] === 1234
                    && $arguments['--delay'] === 750
                    && $arguments['--retry-count'] === 5
                    && $arguments['--no-interaction'] === true;
            }))
            ->andReturn(0);

        $job->handle();

        $this->assertSame('shadow-custom', $job->queue);
    }
}
