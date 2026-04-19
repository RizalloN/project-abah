<?php

namespace App\Queue;

use App\Traits\IdReusable;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider as BaseFailedJobProvider;
use Illuminate\Support\Str;

class CustomFailedJobProvider extends BaseFailedJobProvider
{
    use IdReusable;

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  \Throwable  $exception
     * @return string|null
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $id = $this->findSmallestAvailableId($this->table);

        $this->getTable()->insert([
            'id' => $id,
            'uuid' => $uuid = json_decode($payload, true)['uuid'] ?? (string) Str::uuid(),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) mb_convert_encoding($exception, 'UTF-8'),
            'failed_at' => \Illuminate\Support\Facades\Date::now(),
        ]);

        return $uuid;
    }
}
