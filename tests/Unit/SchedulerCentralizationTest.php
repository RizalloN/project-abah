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
        $this->assertSame(1, substr_count($consoleRoutes, "if (config('services.public_access_health.enabled', false))"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('network:update-duckdns'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('network:public-health --fix'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:ensure-fresh-snapshots'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:snapshot:drain-dirty --max-runtime=5'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('reports:dashboard-harian-sync-missing'"));
        $this->assertSame(1, substr_count($consoleRoutes, "Schedule::command('database:backup-daily'"));
    }

    public function test_frequent_snapshot_commands_use_bounded_mutexes_and_background_execution(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertMatchesRegularExpression(
            "/Schedule::command\\('reports:snapshot:drain-dirty --max-runtime=5'\\)\\s*->everyMinute\\(\\)\\s*->withoutOverlapping\\(2\\)\\s*->runInBackground\\(\\);/s",
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
            "DuckDNS update dinonaktifkan karena akses publik memakai Cloudflare Tunnel.",
            $consoleRoutes
        );
        $this->assertStringContainsString(
            "Artisan::command('network:public-health",
            $consoleRoutes
        );
        $this->assertMatchesRegularExpression(
            "/if \\(config\\('services\\.public_access_health\\.enabled', false\\)\\) \\{\\s*Schedule::command\\('network:update-duckdns'\\).*?Schedule::command\\('network:public-health --fix'\\)\\s*->everyMinute\\(\\)\\s*->withoutOverlapping\\(2\\)\\s*->runInBackground\\(\\);\\s*\\}/s",
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

    public function test_daily_database_backup_runs_at_midnight_with_a_full_day_mutex(): void
    {
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        $this->assertMatchesRegularExpression(
            <<<'REGEX'
/Schedule::command\('database:backup-daily'\)\s*->dailyAt\('00:00'\)\s*->timezone\((?:'Asia\/Jakarta'|config\('database_backup\.timezone'[^)]*\))\)\s*->withoutOverlapping\(1440\)\s*->runInBackground\(\);/s
REGEX,
            $consoleRoutes
        );
    }
}
