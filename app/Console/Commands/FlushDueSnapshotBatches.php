<?php

namespace App\Console\Commands;

use App\Support\SnapshotBatchAggregator;
use Illuminate\Console\Command;

class FlushDueSnapshotBatches extends Command
{
    protected $signature = 'snapshot:flush-due-batches';

    protected $description = 'Flush snapshot batches that are due for processing';

    public function handle(SnapshotBatchAggregator $aggregator): int
    {
        $flushed = $aggregator->flushDueBatches();

        $this->info("Flushed " . count($flushed) . " due snapshot batch(es).");

        return self::SUCCESS;
    }
}
