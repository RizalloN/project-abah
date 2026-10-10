<?php

namespace App\Jobs;

use App\Support\ConsumerRmPositionHistoryStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Restores a missing Consumer RM position capture. Without it the realization
 * calculator must scan the full Daily Loan nominative instead of the compact
 * archive, which turns a sub-second read into minutes on Kinerja RM pages.
 */
class CaptureConsumerRmPositionHistoryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(public readonly string $period)
    {
        $this->onQueue('snapshots-priority');
    }

    public function uniqueId(): string
    {
        return $this->period;
    }

    public function handle(ConsumerRmPositionHistoryStore $store): void
    {
        // A verified capture is kept as-is; imports refresh their own period.
        $store->capturePeriod($this->period);
    }
}
