<?php

namespace Tests\Unit;

use App\Jobs\DiscoverShadowBackfillJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DiscoverShadowBackfillJobTest extends TestCase
{
    public function test_scheduler_command_queues_discovery_instead_of_scanning_inline(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobs')->andReturnFalse();
        $this->app->instance(ImportProgressService::class, $imports);
        Queue::fake();

        $this->artisan('shadow:auto-backfill-scheduler')->assertSuccessful();

        Queue::assertPushedOn((string) config('queue.shadow_backfill_queue', 'shadow-backfill'), DiscoverShadowBackfillJob::class);
    }

    public function test_scheduler_command_skips_while_an_import_is_active(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobs')->andReturnTrue();
        $this->app->instance(ImportProgressService::class, $imports);
        Queue::fake();

        $this->artisan('shadow:auto-backfill-scheduler')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_job_runs_queued_backfill_discovery_and_is_unique(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobs')->andReturnFalse();
        Artisan::shouldReceive('call')
            ->once()
            ->with('shadow:backfill', Mockery::on(fn (array $options): bool => ($options['--queue'] ?? false) === true
                && ($options['--skip-snapshot'] ?? false) === true
                && !array_key_exists('--periods', $options)))
            ->andReturn(0);

        $job = new DiscoverShadowBackfillJob();
        $job->handle($imports);

        $this->assertSame('shadow-backfill:discover', $job->uniqueId());
        $this->assertSame(1, $job->tries);
    }

    public function test_job_does_nothing_while_an_import_is_active(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobs')->andReturnTrue();
        Artisan::shouldReceive('call')->never();

        (new DiscoverShadowBackfillJob())->handle($imports);

        $this->addToAssertionCount(1);
    }
}
