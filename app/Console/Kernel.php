<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Auto-flush snapshot batches every minute
        $schedule->command('snapshot:flush-due-batches')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning('Snapshot batch flush failed');
            });

        // Sync Dashboard Harian snapshots with SSA data every 5 minutes
        // This automatically rebuilds missing snapshots when new periods are added to SSA tables
        $schedule->command('snapshot:sync-harian-dashboard')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning('Dashboard Harian snapshot sync failed');
            });

        // Rebuild important Performance RM snapshots hourly (current + recent periods)
        // Ensures data stays fresh without full rebuild overhead
        $schedule->command('snapshot:rebuild-rm-scheduled')
            ->hourly()
            ->withoutOverlapping(5)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning('Performance RM snapshot scheduled rebuild failed');
            });

        // Drain persistent dirty snapshot periods every minute. The command self-loops
        // for ~55 seconds per tick so consecutive ticks effectively give continuous
        // drain coverage. Pending dirty rows from DB triggers and CRUD mutations get
        // claimed and dispatched to snapshots-parallel for incremental rebuild,
        // which is what makes "delete hourly_dpk then import ssa_simpanan" propagate
        // to dashboard_harian_snapshots automatically.
        $schedule->command('reports:snapshot:drain-dirty', ['--max-runtime=55'])
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning('Snapshot dirty drain failed');
            });

        // Monitor queue and ensure worker is running (check every 30 seconds)
        // Note: This is a fallback; queue workers should be managed by supervisor/systemd in production
        $schedule->command('queue:ensure-running', [
                '--once' => true,
                '--timeout' => 900,
                '--memory' => 512,
            ])
            ->everyMinute()
            ->withoutOverlapping(2)
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::warning('Queue worker monitor failed');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
