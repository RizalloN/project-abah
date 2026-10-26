<?php

namespace Tests\Unit;

use App\Jobs\WarmLandingMicroPerformanceJob;
use App\Services\Import\ImportProgressService;
use App\Support\LandingMicroPerformanceService;
use App\Support\UserBranchScope;
use Illuminate\Console\Scheduling\Schedule;
use Mockery;
use Tests\TestCase;

class WarmLandingMicroPerformanceJobTest extends TestCase
{
    public function test_warms_area_and_every_branch_scope(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobsForTable')->with('daily_loan_dinamis')->andReturnFalse();
        $service = Mockery::mock();
        $this->app->instance(LandingMicroPerformanceService::class, $service);
        $service->shouldReceive('payload')->once()->with(null, null)->andReturn([]);
        foreach (UserBranchScope::branchScopes() as $scope) {
            $service->shouldReceive('payload')->once()->with(null, $scope)->andReturn([]);
        }

        (new WarmLandingMicroPerformanceJob())->handle($imports);

        $this->addToAssertionCount(1);
    }

    public function test_one_failing_scope_does_not_stop_the_others(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobsForTable')->andReturnFalse();
        $service = Mockery::mock();
        $this->app->instance(LandingMicroPerformanceService::class, $service);
        $service->shouldReceive('payload')->once()->with(null, null)->andThrow(new \RuntimeException('boom'));
        $service->shouldReceive('payload')->times(count(UserBranchScope::branchScopes()))->andReturn([]);

        (new WarmLandingMicroPerformanceJob())->handle($imports);

        $this->addToAssertionCount(1);
    }

    public function test_skips_while_daily_loan_is_being_imported(): void
    {
        $imports = Mockery::mock(ImportProgressService::class);
        $imports->shouldReceive('hasActiveProcessingJobsForTable')->andReturnTrue();
        $service = Mockery::mock();
        $this->app->instance(LandingMicroPerformanceService::class, $service);
        $service->shouldReceive('payload')->never();

        (new WarmLandingMicroPerformanceJob())->handle($imports);

        $this->addToAssertionCount(1);
    }

    public function test_is_scheduled_as_a_unique_queued_job_not_inline_work(): void
    {
        $job = new WarmLandingMicroPerformanceJob();
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => ($event->description ?? '') === 'landing:warm-micro-performance');

        $this->assertCount(1, $events);
        $this->assertSame('*/10 * * * *', $events->first()->expression);
        $this->assertSame('landing-micro-warm', $job->uniqueId());
        $this->assertSame('default', $job->queue);
    }
}
