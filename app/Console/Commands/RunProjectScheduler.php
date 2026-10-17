<?php

namespace App\Console\Commands;

use App\Services\SchedulerControlService;
use Illuminate\Console\Command;

class RunProjectScheduler extends Command
{
    protected $signature = 'system:scheduler-run';

    protected $description = 'Run due schedules once per minute with a project-scoped process lock';

    public function handle(SchedulerControlService $scheduler): int
    {
        $result = $scheduler->runDue();
        $this->line($result['outcome']);

        return $result['exit_code'];
    }
}
