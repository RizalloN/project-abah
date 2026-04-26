<?php

namespace App\Support;

use App\Services\Import\MySqlBulkLoadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ManagedReportDeleteRecoveryService
{
    public function __construct(
        private readonly MySqlBulkLoadService $bulkLoadService
    ) {
    }

    /**
     * Delete rows where the period column is null/blank, optionally filtered by kanca.
     * Used as Plan B for blank-period scopes — avoids the 9-variant Cartesian-product path
     * and unreliable IS NULL index hints. Deletes in chunks ordered by storage engine default.
     *
     * @param callable|null $onBatch function(int $affectedRows, int $totalDeleted, int $batchNumber): void
     * @param callable|null $shouldCancel function(): bool
     * @return array{deleted_rows:int,batch_count:int}
     */
    public function deleteBlankPeriodScope(
        string $tableName,
        string $periodColumn,
        ?string $kancaColumn,
        array $scope,
        ?string $identityColumn = null,
        int $chunkSize = 10000,
        ?callable $onBatch = null,
        ?callable $shouldCancel = null
    ): array {
        $normalizedTable = trim($tableName);
        $normalizedPeriodColumn = trim($periodColumn);
        $normalizedKancaColumn = $kancaColumn !== null ? trim($kancaColumn) : null;
        $normalizedIdentityColumn = $identityColumn !== null ? trim($identityColumn) : null;
        $limit = max(1, $chunkSize);

        $kancaIsNull = (bool) ($scope['kanca_is_null'] ?? false);
        $kancaFilter = $scope['kanca_filter'] ?? null;
        if ($kancaFilter !== null) {
            $kancaFilter = trim((string) $kancaFilter);
            if ($kancaFilter === '') {
                $kancaFilter = null;
            }
        }

        if (
            $normalizedTable === ''
            || $normalizedPeriodColumn === ''
            || !Schema::hasTable($normalizedTable)
            || !Schema::hasColumn($normalizedTable, $normalizedPeriodColumn)
        ) {
            throw new \InvalidArgumentException('Scope delete blank-period tidak valid.');
        }

        if ($normalizedKancaColumn !== null && !Schema::hasColumn($normalizedTable, $normalizedKancaColumn)) {
            $normalizedKancaColumn = null;
        }

        if ($normalizedIdentityColumn !== null && !Schema::hasColumn($normalizedTable, $normalizedIdentityColumn)) {
            $normalizedIdentityColumn = null;
        }

        $this->bulkLoadService->assertTransactionalTable($normalizedTable, 'delete data report blank-period');

        return $this->bulkLoadService->withTableWriteLock($normalizedTable, function () use (
            $normalizedTable,
            $normalizedPeriodColumn,
            $normalizedKancaColumn,
            $normalizedIdentityColumn,
            $kancaIsNull,
            $kancaFilter,
            $limit,
            $onBatch,
            $shouldCancel
        ): array {
            $connection = DB::connection();
            $driverName = strtolower((string) $connection->getDriverName());
            $supportsSnapshotFlag = in_array($driverName, ['mysql', 'mariadb'], true);
            $safePeriodColumn = str_replace('`', '``', $normalizedPeriodColumn);

            if ($supportsSnapshotFlag) {
                try {
                    $connection->statement('SET @skip_snapshot_invalidation = 1');
                } catch (Throwable) {
                    $supportsSnapshotFlag = false;
                }
            }

            $deletedRows = 0;
            $batchCount = 0;

            try {
                while (true) {
                    if ($shouldCancel && $shouldCancel()) {
                        break;
                    }

                    $query = $connection->table($normalizedTable)
                        ->where(function ($q) use ($normalizedPeriodColumn) {
                            $q->whereNull($normalizedPeriodColumn)
                                ->orWhere($normalizedPeriodColumn, '');
                        });

                    if ($normalizedKancaColumn !== null) {
                        $safeKancaColumn = str_replace('`', '``', $normalizedKancaColumn);
                        if ($kancaIsNull) {
                            $query->where(function ($q) use ($normalizedKancaColumn) {
                                $q->whereNull($normalizedKancaColumn)
                                    ->orWhere($normalizedKancaColumn, '');
                            });
                        } elseif ($kancaFilter !== null) {
                            $query->where($normalizedKancaColumn, $kancaFilter);
                        }
                    }

                    $affected = $this->deleteScopedBatchByIdentity(
                        $connection,
                        $normalizedTable,
                        $query,
                        $normalizedIdentityColumn,
                        $limit
                    );

                    if ($affected <= 0) {
                        break;
                    }

                    $deletedRows += $affected;
                    $batchCount++;

                    if ($onBatch !== null) {
                        $onBatch($affected, $deletedRows, $batchCount);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Delete blank-period scope gagal.', [
                    'table_name' => $normalizedTable,
                    'period_column' => $normalizedPeriodColumn,
                    'kanca_filter' => $kancaFilter,
                    'kanca_is_null' => $kancaIsNull,
                    'deleted_rows' => $deletedRows,
                    'batch_count' => $batchCount,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            } finally {
                if ($supportsSnapshotFlag) {
                    try {
                        $connection->statement('SET @skip_snapshot_invalidation = NULL');
                    } catch (Throwable) {
                    }
                }
            }

            return [
                'deleted_rows' => $deletedRows,
                'batch_count' => $batchCount,
            ];
        });
    }

    /**
     * @param callable|null $onBatch function(int $affectedRows, int $totalDeleted, int $batchNumber): void
     * @param callable|null $shouldCancel function(): bool
     * @return array{deleted_rows:int,batch_count:int}
     */
    public function deleteBlankKancaPeriodScope(
        string $tableName,
        string $periodColumn,
        string $kancaColumn,
        string $period,
        ?string $identityColumn = null,
        int $chunkSize = 10000,
        ?callable $onBatch = null,
        ?callable $shouldCancel = null
    ): array {
        $normalizedTable = trim($tableName);
        $normalizedPeriodColumn = trim($periodColumn);
        $normalizedKancaColumn = trim($kancaColumn);
        $normalizedPeriod = trim($period);
        $normalizedIdentityColumn = $identityColumn !== null ? trim($identityColumn) : null;
        $limit = max(1, $chunkSize);

        if (
            $normalizedTable === ''
            || $normalizedPeriodColumn === ''
            || $normalizedKancaColumn === ''
            || $normalizedPeriod === ''
            || !Schema::hasTable($normalizedTable)
            || !Schema::hasColumn($normalizedTable, $normalizedPeriodColumn)
            || !Schema::hasColumn($normalizedTable, $normalizedKancaColumn)
        ) {
            throw new \InvalidArgumentException('Scope delete recovery tidak valid.');
        }

        $this->bulkLoadService->assertTransactionalTable($normalizedTable, 'delete data report recovery');

        if ($normalizedIdentityColumn !== null && !Schema::hasColumn($normalizedTable, $normalizedIdentityColumn)) {
            $normalizedIdentityColumn = null;
        }

        return $this->bulkLoadService->withTableWriteLock($normalizedTable, function () use (
            $normalizedTable,
            $normalizedPeriodColumn,
            $normalizedKancaColumn,
            $normalizedPeriod,
            $normalizedIdentityColumn,
            $limit,
            $onBatch,
            $shouldCancel
        ): array {
            $connection = DB::connection();
            $driverName = strtolower((string) $connection->getDriverName());
            $supportsSnapshotFlag = in_array($driverName, ['mysql', 'mariadb'], true);
            $safeKancaColumn = str_replace('`', '``', $normalizedKancaColumn);

            if ($supportsSnapshotFlag) {
                try {
                    $connection->statement('SET @skip_snapshot_invalidation = 1');
                } catch (Throwable) {
                    $supportsSnapshotFlag = false;
                }
            }

            $deletedRows = 0;
            $batchCount = 0;

            try {
                while (true) {
                    if ($shouldCancel && $shouldCancel()) {
                        break;
                    }

                    $query = $connection->table($normalizedTable)
                        ->where($normalizedPeriodColumn, $normalizedPeriod)
                        ->where(function ($query) use ($normalizedKancaColumn) {
                            $query
                                ->whereNull($normalizedKancaColumn)
                                ->orWhere($normalizedKancaColumn, '');
                        });

                    $affected = $this->deleteScopedBatchByIdentity(
                        $connection,
                        $normalizedTable,
                        $query,
                        $normalizedIdentityColumn,
                        $limit
                    );

                    if ($affected <= 0) {
                        break;
                    }

                    $deletedRows += $affected;
                    $batchCount++;

                    if ($onBatch !== null) {
                        $onBatch($affected, $deletedRows, $batchCount);
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Delete recovery blank-kanca scope gagal.', [
                    'table_name' => $normalizedTable,
                    'period' => $normalizedPeriod,
                    'deleted_rows' => $deletedRows,
                    'batch_count' => $batchCount,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                throw $e;
            } finally {
                if ($supportsSnapshotFlag) {
                    try {
                        $connection->statement('SET @skip_snapshot_invalidation = NULL');
                    } catch (Throwable) {
                        // ignore restore failures
                    }
                }
            }

            return [
                'deleted_rows' => $deletedRows,
                'batch_count' => $batchCount,
            ];
        });
    }

    private function deleteScopedBatchByIdentity($connection, string $tableName, $scopeQuery, ?string $identityColumn, int $limit): int
    {
        if ($identityColumn === null || $identityColumn === '') {
            return (int) (clone $scopeQuery)->limit($limit)->delete();
        }

        $deletedDangling = (int) (clone $scopeQuery)
            ->where(function ($query) use ($identityColumn) {
                $query->whereNull($identityColumn)
                    ->orWhere($identityColumn, '');
            })
            ->limit($limit)
            ->delete();

        if ($deletedDangling > 0) {
            return $deletedDangling;
        }

        $identityValues = (clone $scopeQuery)
            ->select($identityColumn)
            ->whereNotNull($identityColumn)
            ->where($identityColumn, '<>', '')
            ->orderBy($identityColumn)
            ->limit($limit)
            ->pluck($identityColumn)
            ->filter(static fn ($value) => $value !== null && $value !== '')
            ->values()
            ->all();

        if (empty($identityValues)) {
            return 0;
        }

        $deleted = 0;

        foreach (array_chunk($identityValues, 2000) as $chunk) {
            $deleted += (int) $connection->table($tableName)
                ->whereIn($identityColumn, $chunk)
                ->delete();
        }

        return $deleted;
    }
}
