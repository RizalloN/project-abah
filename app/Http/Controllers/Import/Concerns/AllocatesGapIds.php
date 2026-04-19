<?php

namespace App\Http\Controllers\Import\Concerns;

use App\Traits\IdReusable;

trait AllocatesGapIds
{
    use IdReusable;

    protected function usesGapIdReuse(string $tableName): bool
    {
        return in_array(strtolower(trim($tableName)), ['ssa_simpanan', 'ssa_pinjaman'], true);
    }

    /**
     * Allocates gap IDs for a collection of rows if the table is opted-in for ID reuse.
     * Currently only applied to ssa_simpanan and ssa_pinjaman.
     *
     * @param string $tableName
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function allocateGapIdsForRows(string $tableName, array $rows): array
    {
        if (!$this->usesGapIdReuse($tableName)) {
            return $rows;
        }

        $rowCount = count($rows);
        if ($rowCount === 0) {
            return $rows;
        }

        $availableIds = $this->findSmallestAvailableIds($tableName, $rowCount);
        
        $assignedRows = [];
        $i = 0;
        foreach ($rows as $index => $row) {
            $row['id'] = $availableIds[$i] ?? null;
            $assignedRows[$index] = $row;
            $i++;
        }

        return $assignedRows;
    }

    /**
     * Consume pre-reserved gap IDs in order so staged CSV buffers do not reuse
     * the same ID range before rows are actually written to MySQL.
     *
     * @param string $tableName
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, int> $reservedIds
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    protected function allocateReservedGapIdsForRows(string $tableName, array $rows, array &$reservedIds, int &$offset): array
    {
        if (!$this->usesGapIdReuse($tableName) || $rows === []) {
            return $rows;
        }

        if ($reservedIds === []) {
            return $this->allocateGapIdsForRows($tableName, $rows);
        }

        $assignedRows = [];
        $rowPosition = 0;
        foreach ($rows as $index => $row) {
            if (!array_key_exists($offset, $reservedIds)) {
                $remainingRows = array_slice($rows, $rowPosition, null, true);
                $assignedRows += $this->allocateGapIdsForRows($tableName, $remainingRows);
                break;
            }

            $row['id'] = $reservedIds[$offset];
            $assignedRows[$index] = $row;
            $offset++;
            $rowPosition++;
        }

        return $assignedRows;
    }
}
