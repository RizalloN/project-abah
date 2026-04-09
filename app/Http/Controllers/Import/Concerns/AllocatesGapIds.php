<?php

namespace App\Http\Controllers\Import\Concerns;

trait AllocatesGapIds
{
    /**
     * Gap-ID allocation is intentionally disabled.
     * Import relies on table native PK strategy (now uniqueid_* for report tables).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function allocateGapIdsForRows(string $tableName, array $rows): array
    {
        return $rows;
    }
}
