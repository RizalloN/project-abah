<?php

namespace App\Jobs;

use DateTimeInterface;

trait SnapshotJobRetryWindow
{
    public function retryUntil(): DateTimeInterface
    {
        $hours = max(4, (int) config('import.snapshot.retry_window_hours', 24));

        return now()->addHours($hours);
    }
}
