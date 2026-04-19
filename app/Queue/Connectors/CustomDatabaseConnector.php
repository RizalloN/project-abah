<?php

namespace App\Queue\Connectors;

use App\Queue\CustomDatabaseQueue;
use Illuminate\Queue\Connectors\DatabaseConnector as BaseDatabaseConnector;

class CustomDatabaseConnector extends BaseDatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new CustomDatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
