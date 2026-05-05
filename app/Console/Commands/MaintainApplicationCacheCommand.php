<?php

namespace App\Console\Commands;

use App\Services\CacheMaintenanceService;
use Illuminate\Console\Command;

class MaintainApplicationCacheCommand extends Command
{
    protected $signature = 'cache:maintenance {--dry-run : Report stale entries without deleting them}';

    protected $description = 'Prune expired file cache, stale file sessions, orphaned DB sessions, and old job batches.';

    public function handle(CacheMaintenanceService $cache): int
    {
        $summary = $cache->maintain((bool) $this->option('dry-run'));

        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
