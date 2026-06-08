<?php

namespace Tests\Unit;

use Tests\TestCase;

class SchedulerCentralizationTest extends TestCase
{
    public function test_scheduler_definitions_remain_centralized_in_console_routes(): void
    {
        $kernel = file_get_contents(app_path('Console/Kernel.php'));
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertStringNotContainsString('$schedule->command(', $kernel);
        $this->assertSame(1, substr_count($consoleRoutes, 'queue:ensure-running --once'));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('network:update-duckdns'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:ensure-fresh-snapshots'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:snapshot:drain-dirty --max-runtime=55'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:dashboard-harian-sync-missing'"));
    }

    public function test_frequent_snapshot_commands_use_bounded_mutexes_and_background_execution(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertMatchesRegularExpression(
            "/Schedule::command\\('reports:snapshot:drain-dirty --max-runtime=55'\\)\\s*->everyMinute\\(\\)\\s*->withoutOverlapping\\(2\\)\\s*->runInBackground\\(\\);/s",
            $consoleRoutes
        );
        $this->assertMatchesRegularExpression(
            "/Schedule::command\\('reports:dashboard-harian-sync-missing'\\)\\s*->everyFiveMinutes\\(\\)\\s*->withoutOverlapping\\(10\\)\\s*->runInBackground\\(\\);/s",
            $consoleRoutes
        );
        $this->assertStringContainsString(
            "EnsureImportedSnapshotsFreshJob::dispatch('gi405_recovery'",
            $consoleRoutes
        );
        $this->assertStringContainsString(
            "Artisan::command('network:update-duckdns'",
            $consoleRoutes
        );
        $this->assertStringContainsString(
            "queue:ensure-running --once --timeout=900 --memory=512",
            $consoleRoutes
        );
        $this->assertStringContainsString(
            "Schedule::command('import:health-check --fix --hours=1')",
            $consoleRoutes
        );
        $this->assertStringNotContainsString("'--once' => true", $consoleRoutes);
        $this->assertStringNotContainsString("'--fix' => true", $consoleRoutes);
    }
}
