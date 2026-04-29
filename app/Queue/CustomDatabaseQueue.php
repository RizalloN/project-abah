<?php

namespace App\Queue;

use App\Traits\IdReusable;
use Illuminate\Queue\DatabaseQueue as BaseDatabaseQueue;
use Illuminate\Support\Collection;

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

    /**
     * Push an array of jobs onto the queue.
     *
     * Laravel's database queue bulk path is used by Bus::batch() and bypasses
     * pushToDatabase(). The project intentionally reuses numeric IDs instead
     * of auto-increment, so bulk inserts must assign IDs too.
     *
     * @param  array  $jobs
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    public function bulk($jobs, $data = '', $queue = null)
    {
        $queue = $this->getQueue($queue);
        $jobs = (array) $jobs;
        $now = $this->availableAt();
        $ids = $this->findSmallestAvailableIds($this->table, count($jobs));
        $idIndex = 0;

        return $this->database->table($this->table)->insert((new Collection($jobs))->map(
            function ($job) use ($queue, $data, $now, $ids, &$idIndex) {
                $record = $this->buildDatabaseRecord(
                    $queue,
                    $this->createPayload($job, $this->getQueue($queue), $data),
                    isset($job->delay) ? $this->availableAt($job->delay) : $now,
                );

                $record['id'] = $ids[$idIndex++];

                return $record;
            }
        )->all());
    }
}
