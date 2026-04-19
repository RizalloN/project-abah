<?php

namespace App\Queue;

use App\Traits\IdReusable;
use Illuminate\Bus\DatabaseBatchRepository as BaseBatchRepository;
use Illuminate\Bus\PendingBatch;

class CustomBatchRepository extends BaseBatchRepository
{
    use IdReusable;

    /**
     * Store a new pending batch.
     *
     * @param  \Illuminate\Bus\PendingBatch  $batch
     * @return \Illuminate\Bus\Batch
     */
    public function store(PendingBatch $batch)
    {
        $id = $this->findSmallestAvailableId($this->table);

        \Illuminate\Support\Facades\Log::debug("CustomBatchRepository: storing batch with ID {$id}");

        $this->connection->table($this->table)->insert([
            'id' => $id,
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => serialize($batch->options),
            'created_at' => time(),
            'cancelled_at' => null,
            'finished_at' => null,
        ]);

        return $this->find($id);
    }
}
