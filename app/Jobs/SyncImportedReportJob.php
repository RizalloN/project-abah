<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Services\Import\ImportCleanupService;
use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Queue\SerializesModels;

class SyncImportedReportJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SnapshotJobRetryWindow;

    public int $timeout = 0;
    public int $tries = 40;
    public array $backoff = [60, 300];

    public ?int $jobId = null;
    public ?string $tableName = null;
    public ?string $periodHint = null;
    public ?string $source = null;
    public ?string $rebuildId = null;

    public function __construct(
        mixed $jobId = null,
        ?string $tableName = null,
        ?string $periodHint = null,
        ?string $source = null,
        ?string $rebuildId = null
    ) {
        if (is_string($jobId) && !is_numeric($jobId)) {
            $this->jobId = null;
            $this->tableName = $jobId;
            $this->periodHint = $tableName;
            $this->source = $periodHint;
            $this->rebuildId = $source;

            return;
        }

        $this->jobId = $jobId === null || $jobId === '' ? null : (int) $jobId;
        $this->tableName = $tableName;
        $this->periodHint = $periodHint;
        $this->source = $source;
        $this->rebuildId = $rebuildId;
    }

    public function uniqueId(): string
    {
        $table = strtolower(trim((string) $this->tableName));
        $period = trim((string) $this->periodHint);

        return md5($table . ':' . ($period !== '' ? $period : '__all__'));
    }

    public function middleware(): array
    {
        $middleware = [
            new DeferSnapshotJobsDuringImport(),
        ];

        if ($this->tableName === null || trim($this->tableName) === '') {
            return $middleware;
        }

        $periodScope = trim((string) $this->periodHint);
        $scope = strtolower(trim($this->tableName)) . ':' . ($periodScope !== '' ? $periodScope : '__all__');

        $middleware[] = (new WithoutOverlapping('snapshot:sync:job:' . $scope))
            ->releaseAfter(5)
            ->expireAfter(600);

        return $middleware;
    }

    public function handle(ReportDataSyncService $syncService, ImportCleanupService $cleanupService): void
    {
        try {
            if ($this->jobId !== null && $this->jobId > 0) {
                $syncService->syncImportedJob(
                    jobId: $this->jobId,
                    fallbackTableName: $this->tableName,
                    periodHint: $this->periodHint,
                    source: $this->source ?? static::class,
                    rebuildId: $this->rebuildId
                );

                return;
            }

            if ($this->tableName !== null && $this->tableName !== '') {
                $syncService->syncImportedTable(
                    tableName: $this->tableName,
                    periodHint: $this->periodHint,
                    jobId: null,
                    source: $this->source ?? static::class,
                    deleteId: null,
                    rebuildId: $this->rebuildId
                );
            }
        } finally {
            $cleanupService->finalizeImportedJobSyncDispatch(
                $this->jobId ?? 0,
                $this->tableName,
                $this->periodHint,
                $this->source ?? static::class
            );
        }
    }
}
