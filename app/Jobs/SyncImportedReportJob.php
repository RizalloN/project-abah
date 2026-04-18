<?php

namespace App\Jobs;

use App\Services\Import\ImportCleanupService;
use App\Support\ReportDataSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SyncImportedReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    public function __construct(
        public ?int $jobId = null,
        public ?string $tableName = null,
        public ?string $periodHint = null,
        public ?string $source = null,
        public ?string $rebuildId = null
    ) {
    }

    public function middleware(): array
    {
        if ($this->tableName === null || trim($this->tableName) === '') {
            return [];
        }

        $periodScope = trim((string) $this->periodHint);
        $scope = strtolower(trim($this->tableName)) . ':' . ($periodScope !== '' ? $periodScope : '__all__');

        return [
            (new WithoutOverlapping('snapshot:sync:job:' . $scope))
                ->releaseAfter(5)
                ->expireAfter(600),
        ];
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
