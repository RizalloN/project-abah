<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class PartitionMaintenanceService
{
    public function supportsPartitionDdl(?string $driverName = null): bool
    {
        return in_array(strtolower((string) ($driverName ?: DB::connection()->getDriverName())), ['mysql', 'mariadb'], true);
    }

    public function resolveSinglePartitionForValue(string $tableName, string $columnName, string $value): ?string
    {
        if (!$this->supportsPartitionDdl()) {
            return null;
        }

        $wrappedTable = $this->wrapIdentifier($tableName);
        $wrappedColumn = $this->wrapIdentifier($columnName);

        try {
            $rows = DB::select(
                "EXPLAIN PARTITIONS SELECT 1 FROM {$wrappedTable} WHERE {$wrappedColumn} = ? LIMIT 1",
                [$value]
            );
        } catch (Throwable) {
            return null;
        }

        $partitions = [];

        foreach ($rows as $row) {
            $partitionList = $row->partitions ?? $row->Partitions ?? null;
            if (!is_string($partitionList) || trim($partitionList) === '') {
                continue;
            }

            foreach (explode(',', $partitionList) as $partitionName) {
                $partitionName = trim($partitionName);
                if ($partitionName !== '') {
                    $partitions[$partitionName] = true;
                }
            }
        }

        return count($partitions) === 1 ? array_key_first($partitions) : null;
    }

    public function truncatePartition(string $tableName, string $partitionName): void
    {
        if (!$this->supportsPartitionDdl()) {
            throw new \RuntimeException('Partition DDL hanya didukung di MySQL/MariaDB.');
        }

        $wrappedTable = $this->wrapIdentifier($tableName);
        $wrappedPartition = $this->wrapIdentifier($partitionName);

        DB::statement("ALTER TABLE {$wrappedTable} TRUNCATE PARTITION {$wrappedPartition}");
    }

    private function wrapIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', trim($value)) . '`';
    }
}
