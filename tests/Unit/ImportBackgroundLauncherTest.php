<?php

namespace Tests\Unit;

use App\Services\Import\ImportBackgroundLauncher;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ImportBackgroundLauncherTest extends TestCase
{
    public function test_launch_requires_a_successful_process_with_a_real_pid(): void
    {
        Process::fake(['*' => Process::result(output: "12345\n")]);

        $this->assertTrue(app(ImportBackgroundLauncher::class)->launch(15));
        Process::assertRan(function ($process): bool {
            if (DIRECTORY_SEPARATOR !== '\\') {
                return str_contains($process->command[2], 'import:run-job 15');
            }
            $script = mb_convert_encoding(base64_decode(end($process->command)), 'UTF-8', 'UTF-16LE');

            return str_contains($script, '-WindowStyle Hidden')
                && str_contains($script, 'import:run-job 15')
                && str_contains($script, '-RedirectStandardError')
                && ! str_contains(strtolower($script), 'httpd.exe');
        });
    }

    public function test_launcher_failure_does_not_report_success(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Launch denied', exitCode: 1)]);

        $this->assertFalse(app(ImportBackgroundLauncher::class)->launch(15));
    }

    public function test_exit_zero_without_pid_does_not_report_success(): void
    {
        Process::fake(['*' => Process::result(output: '')]);

        $this->assertFalse(app(ImportBackgroundLauncher::class)->launch(15));
    }

    public function test_invalid_job_does_not_spawn_a_process(): void
    {
        Process::fake();

        $this->assertFalse(app(ImportBackgroundLauncher::class)->launch(0));
        Process::assertNothingRan();
    }
}
