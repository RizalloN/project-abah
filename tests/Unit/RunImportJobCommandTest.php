<?php

namespace Tests\Unit;

use App\Services\Import\ImmediateImportRunner;
use App\Services\Import\ImportExecutionService;
use App\Services\Import\ImportProgressService;
use Mockery;
use Tests\TestCase;

class RunImportJobCommandTest extends TestCase
{
    public function test_command_reports_success_only_for_a_completed_import_with_rows(): void
    {
        $this->runner('executed', (object) ['status' => 'completed', 'total_success' => 511868]);

        $this->artisan('import:run-job', ['jobId' => 15])
            ->expectsOutput('Job #15 completed: 511868 rows imported.')->assertExitCode(0);
    }

    public function test_nonterminal_import_is_not_reported_as_successful(): void
    {
        $this->runner('executed', (object) ['status' => 'processing', 'total_success' => 0]);

        $this->artisan('import:run-job', ['jobId' => 15])->assertExitCode(1);
    }

    public function test_completed_zero_rows_is_not_reported_as_successful(): void
    {
        $this->runner('executed', (object) ['status' => 'completed', 'total_success' => 0]);

        $this->artisan('import:run-job', ['jobId' => 15])->assertExitCode(1);
    }

    public function test_busy_runner_does_not_claim_import_completion(): void
    {
        $this->runner('already_running');

        $this->artisan('import:run-job', ['jobId' => 15])->assertExitCode(1);
    }

    private function runner(string $result, ?object $job = null): void
    {
        $execution = Mockery::mock(ImportExecutionService::class);
        $progress = Mockery::mock(ImportProgressService::class);
        if ($job !== null) {
            $progress->shouldReceive('findJob')->once()->with(15)->andReturn($job);
        }
        $runner = Mockery::mock(ImmediateImportRunner::class);
        $runner->shouldReceive('run')->once()->with(15, $progress, $execution)->andReturn($result);
        $this->app->instance(ImportExecutionService::class, $execution);
        $this->app->instance(ImportProgressService::class, $progress);
        $this->app->instance(ImmediateImportRunner::class, $runner);
    }
}
