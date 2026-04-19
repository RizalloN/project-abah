<?php

namespace App\Queue;

use App\Traits\IdReusable;
use Illuminate\Queue\DatabaseQueue as BaseDatabaseQueue;

class CustomDatabaseQueue extends BaseDatabaseQueue
{
    use IdReusable;

    /**
     * Push a new job onto the queue.
     * Overridden to handle ID reuse.
     *
     * @param  string  $queue
     * @param  string  $payload
     * @param  int  $delay
     * @param  int  $attempts
     * @return mixed
     */
    protected function pushToDatabase($queue, $payload, $delay = 0, $attempts = 0)
    {
        $id = $this->findSmallestAvailableId($this->table);

        if ($queue === null) {
            \Illuminate\Support\Facades\Log::debug("CustomDatabaseQueue: queue is null for table {$this->table}");
        }

        $this->database->table($this->table)->insert([
            'id' => $id,
            'queue' => $queue ?? 'default',
            'payload' => $payload,
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $this->availableAt($delay),
            'created_at' => $this->currentTime(),
        ]);

        return $id;
    }
}
