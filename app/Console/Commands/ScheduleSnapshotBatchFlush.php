<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScheduleSnapshotBatchFlush extends Command
{
    protected $signature = 'snapshot:setup-batch-flush-schedule';

    protected $description = 'Setup scheduler entry for periodic snapshot batch flush';

    public function handle(): int
    {
        $cronEntry = '*/3 * * * * php ' . base_path('artisan') . ' snapshot:flush-due-batches >> /dev/null 2>&1';

        $this->info("Add this cron entry to flush batches every 3 minutes:");
        $this->line("");
        $this->line($cronEntry);
        $this->line("");
        $this->info("Or add to app/Console/Kernel.php:");
        $this->line("");
        $this->line("    \$schedule->command('snapshot:flush-due-batches')->everyThreeMinutes();");
        $this->line("");

        return self::SUCCESS;
    }
}
