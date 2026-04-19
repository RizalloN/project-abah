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

        // Monitor queue and ensure worker is running (check every 30 seconds)
        // Note: This is a fallback; queue workers should be managed by supervisor/systemd in production
        // Uncomment to enable automatic queue worker restart:
        // $schedule->command('queue:ensure-running')
        //     ->everyThirtySeconds()
        //     ->withoutOverlapping()
        //     ->onFailure(function () {
        //         \Illuminate\Support\Facades\Log::warning('Queue worker monitor failed');
        //     });
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
