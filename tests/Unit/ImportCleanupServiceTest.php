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

        $service->dispatchImportedJobSync(10, 'daily_loan_dinamis', '2026-04-04', 'unit-test');
        $service->dispatchImportedJobSync(11, 'daily_loan_dinamis', '2026-04-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 1);
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'daily_loan_dinamis'
                && $job->periodHint === '2026-04-04';
        });
    }

    public function test_dispatch_imported_job_sync_marks_rerun_when_newer_request_arrives(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(10, 'daily_loan_dinamis', '2026-04-04', 'unit-test');
        $service->dispatchImportedJobSync(11, 'daily_loan_dinamis', '2026-04-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 1);
        $this->assertSame(
            'imports-high',
            Cache::get('snapshot:sync:rerun:daily_loan_dinamis:2026-04-04')
        );
    }

    public function test_dispatch_imported_job_sync_bypasses_batching_for_lightweight_merchant_reports(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(26, 'jumlah_merchant_qris_detail', '2026-04-04', 'unit-test');
        $service->dispatchImportedJobSync(27, 'sv_merchant', '2026-04-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 2);
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'jumlah_merchant_qris_detail'
                && $job->periodHint === '2026-04-04';
        });
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'sv_merchant'
                && $job->periodHint === '2026-04-04';
        });
    }

    public function test_dispatch_imported_job_sync_normalizes_compact_date_period_hints(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(10, 'daily_loan_dinamis', '27042026', 'unit-test');

        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'daily_loan_dinamis'
                && $job->periodHint === '2026-04-27';
        });
    }

    public function test_dispatch_imported_job_sync_dispatches_lw325_ph_immediately(): void
    {
        $service = new ImportCleanupService();

        $service->dispatchImportedJobSync(10, 'lw325_ph', '27042026', 'unit-test');

        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) {
            return $job->tableName === 'lw325_ph'
                && $job->periodHint === '2026-04-27';
        });

        $this->assertNull(Cache::get('snapshot:batch:lw325_ph:2026-04-27'));
    }
}
