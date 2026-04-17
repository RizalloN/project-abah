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
     * @param callable|null $onBatch function(int $affectedRows, int $totalDeleted, int $batchNumber): void
     * @param callable|null $shouldCancel function(): bool
     * @return array{deleted_rows:int,batch_count:int}
     */
    public function deleteBlankKancaPeriodScope(
        string $tableName,
        string $periodColumn,
        string $kancaColumn,
        string $period,
        int $chunkSize = 10000,
        ?callable $onBatch = null,
        ?callable $shouldCancel = null
    ): array {
        $normalizedTable = trim($tableName);
        $normalizedPeriodColumn = trim($periodColumn);
        $normalizedKancaColumn = trim($kancaColumn);
        $normalizedPeriod = trim($period);
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

        return $this->bulkLoadService->withTableWriteLock($normalizedTable, function () use (
            $normalizedTable,
            $normalizedPeriodColumn,
            $normalizedKancaColumn,
            $normalizedPeriod,
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

                    $affected = (int) $connection->table($normalizedTable)
                        ->where($normalizedPeriodColumn, $normalizedPeriod)
                        ->where(function ($query) use ($normalizedKancaColumn, $safeKancaColumn) {
                            $query
                                ->whereNull($normalizedKancaColumn)
                                ->orWhereRaw("TRIM(COALESCE(`{$safeKancaColumn}`, '')) = ''");
                        })
                        ->limit($limit)
                        ->delete();

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
}
