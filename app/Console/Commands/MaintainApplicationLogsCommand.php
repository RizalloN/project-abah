<?php

namespace App\Console\Commands;

use App\Services\LogMaintenanceService;
use Illuminate\Console\Command;

class MaintainApplicationLogsCommand extends Command
{
    protected $signature = 'logs:maintenance {--dry-run : Report oversized logs without rotating them}';

    protected $description = 'Rotate oversized application logs and prune old log archives.';

    public function handle(LogMaintenanceService $logs): int
    {
        $summary = $logs->maintain((bool) $this->option('dry-run'));

        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
