<?php

namespace Tests\Unit;

use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportCleanupService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImportCleanupServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Bus::fake();
    }

    public function test_dispatch_imported_job_sync_coalesces_duplicate_period_requests(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(10, 'simpanan_multipn', '2026-04-04', 'unit-test');
        $service->dispatchImportedJobSync(11, 'simpanan_multipn', '2026-04-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 1);
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'simpanan_multipn'
                && $job->periodHint === '2026-04-04';
        });
    }

    public function test_finalize_imported_job_sync_dispatch_requeues_when_newer_request_arrives(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(10, 'simpanan_multipn', '2026-04-04', 'unit-test');
        $service->dispatchImportedJobSync(11, 'simpanan_multipn', '2026-04-04', 'unit-test');
        $service->finalizeImportedJobSyncDispatch(10, 'simpanan_multipn', '2026-04-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 2);
    }
}
