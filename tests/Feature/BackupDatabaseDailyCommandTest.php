<?php

namespace Tests\Feature;

use App\Services\DailyDatabaseBackupService;
use Illuminate\Console\Command;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BackupDatabaseDailyCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_command_returns_success_and_prints_the_backup_summary(): void
    {
        $service = Mockery::mock(DailyDatabaseBackupService::class);
        $service->shouldReceive('backup')
            ->once()
            ->with(null, false)
            ->andReturn([
                'status' => 'completed',
                'directory' => 'D:\\BACKUP PROJECT ABAH\\backup project-abah 31072026',
                'backup_file' => 'project_abah_31072026.sql.gz',
                'manifest_file' => 'manifest.json',
                'compressed_bytes' => 1234,
                'uncompressed_bytes' => 5678,
                'deleted_backups' => [],
                'warnings' => [],
            ]);
        $this->app->instance(DailyDatabaseBackupService::class, $service);

        $this->artisan('database:backup-daily')
            ->expectsOutputToContain('Backup database harian selesai dan lolos verifikasi.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_forwards_dry_run_without_creating_a_real_backup(): void
    {
        $service = Mockery::mock(DailyDatabaseBackupService::class);
        $service->shouldReceive('backup')
            ->once()
            ->with(null, true)
            ->andReturn([
                'status' => 'dry-run',
                'directory' => 'D:\\BACKUP PROJECT ABAH\\backup project-abah 31072026',
                'backup_file' => 'project_abah_31072026.sql.gz',
                'manifest_file' => 'manifest.json',
                'compressed_bytes' => 0,
                'uncompressed_bytes' => 0,
                'deleted_backups' => [],
                'warnings' => [],
            ]);
        $this->app->instance(DailyDatabaseBackupService::class, $service);

        $this->artisan('database:backup-daily', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run backup database harian berhasil.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_returns_failure_when_the_backup_service_throws(): void
    {
        $service = Mockery::mock(DailyDatabaseBackupService::class);
        $service->shouldReceive('backup')
            ->once()
            ->with(null, false)
            ->andThrow(new RuntimeException('Simulated daily backup failure.'));
        $this->app->instance(DailyDatabaseBackupService::class, $service);

        $this->artisan('database:backup-daily')
            ->expectsOutputToContain('Simulated daily backup failure.')
            ->assertExitCode(Command::FAILURE);
    }
}
