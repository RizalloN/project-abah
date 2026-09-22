<?php

namespace App\Jobs;

use App\Jobs\Middleware\DeferSnapshotJobsDuringImport;
use App\Services\Snapshot\SnapshotAuditAndHealService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuditAndHealSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public bool $force = false
    ) {
        $this->onQueue('snapshots-parallel');
    }

    public function middleware(): array
    {
        return [
            new DeferSnapshotJobsDuringImport(),
            (new WithoutOverlapping('snapshot:audit-and-heal-job'))
                ->releaseAfter(120)
                ->expireAfter(900),
        ];
    }

    public function handle(SnapshotAuditAndHealService $auditAndHealService): void
    {
        Log::info('AuditAndHealSnapshotsJob dimulai.');

        $result = $auditAndHealService->auditAndHealAll($this->force);

        Log::info('AuditAndHealSnapshotsJob selesai.', [
            'status' => $result['status'] ?? 'unknown',
            'tables_checked' => $result['tables_checked'] ?? 0,
            'discrepancies_found' => $result['discrepancies_found'] ?? 0,
            'rebuilds_dispatched' => $result['rebuilds_dispatched'] ?? 0,
        ]);
    }
}
