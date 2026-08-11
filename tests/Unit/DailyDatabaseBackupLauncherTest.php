<?php

namespace Tests\Unit;

use Tests\TestCase;

class DailyDatabaseBackupLauncherTest extends TestCase
{
    public function test_windows_launcher_calls_only_the_daily_backup_command_and_preserves_exit_code(): void
    {
        $launcher = file_get_contents(base_path('database-backup-daily.bat'));

        $this->assertStringContainsString(
            'artisan database:backup-daily %* >> "storage\logs\daily-database-backup.log" 2>&1',
            $launcher
        );
        $this->assertStringContainsString(
            'endlocal & exit /b %DAILY_BACKUP_EXIT_CODE%',
            $launcher
        );
        $this->assertStringNotContainsString('schedule:run', $launcher);
    }

    public function test_windows_task_installer_uses_midnight_start_when_available_and_system_when_elevated(): void
    {
        $installer = file_get_contents(
            base_path('scripts/install-daily-database-backup-task.ps1')
        );

        $this->assertStringContainsString(
            "\$taskName = 'ProjectABAH-DailyDatabaseBackup'",
            $installer
        );
        $this->assertStringContainsString(
            "New-ScheduledTaskTrigger -Daily -At '00:00'",
            $installer
        );
        $this->assertStringContainsString('-StartWhenAvailable', $installer);
        $this->assertStringContainsString('& schtasks.exe', $installer);
        $this->assertStringContainsString("LogonType = 'InteractiveToken'", $installer);
        $this->assertStringContainsString(
            "New-ScheduledTaskPrincipal -UserId 'SYSTEM' -RunLevel Highest",
            $installer
        );
        $this->assertStringNotContainsString('php-scheduler.bat', $installer);
    }
}
