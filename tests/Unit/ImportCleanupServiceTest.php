<?php

namespace Tests\Unit;

use App\Jobs\SyncImportedReportJob;
use App\Services\Import\ImportCleanupService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_dispatch_imported_job_sync_resolves_gi405_periods_and_dispatches_immediately(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('id_report')->nullable();
                $table->longText('job_context')->nullable();
                $table->timestamps();
            });
        }

        Schema::dropIfExists('gi405_recovery');
        Schema::create('gi405_recovery', function (Blueprint $table): void {
            $table->id();
            $table->date('periode')->nullable();
            $table->timestamps();
        });

        $createdAt = now()->subMinute();
        $updatedAt = now();

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => 19,
            'job_context' => json_encode(['table_name' => 'gi405_recovery']),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        foreach (['2026-07-02', '2026-07-03', '2026-07-04'] as $period) {
            DB::table('gi405_recovery')->insert([
                'periode' => $period,
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
        }

        $service = new ImportCleanupService();
        $service->dispatchImportedJobSync((int) $jobId, null, null, 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 3);
        foreach (['2026-07-02', '2026-07-03', '2026-07-04'] as $period) {
            Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) use ($jobId, $period): bool {
                return $job->jobId === (int) $jobId
                    && $job->tableName === 'gi405_recovery'
                    && $job->periodHint === $period;
            });

            $this->assertNull(Cache::get('snapshot:batch:gi405_recovery:' . $period));
        }
    }

    public function test_dispatch_imported_job_sync_recovers_stale_pending_marker_without_active_job(): void
    {
        $oldPending = now()->subMinutes(10)->toIso8601String();
        Cache::put('snapshot:sync:pending:ssa_simpanan:2026-07-02', $oldPending, now()->addMinutes(15));
        Cache::put('snapshot:sync:rerun:ssa_simpanan:2026-07-02', 'default', now()->addMinutes(15));

        $service = new ImportCleanupService();
        $service->dispatchImportedJobSync(10, 'ssa_simpanan', '2026-07-02', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 1);
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job): bool {
            return $job->tableName === 'ssa_simpanan'
                && $job->periodHint === '2026-07-02';
        });
        $this->assertNotSame($oldPending, Cache::get('snapshot:sync:pending:ssa_simpanan:2026-07-02'));
        $this->assertNull(Cache::get('snapshot:sync:rerun:ssa_simpanan:2026-07-02'));
    }

    public function test_finalize_imported_job_sync_dispatch_resets_pending_marker_before_rerun(): void
    {
        $oldPending = now()->subMinutes(10)->toIso8601String();
        Cache::put('snapshot:sync:pending:ssa_pinjaman:2026-07-04', $oldPending, now()->addMinutes(15));
        Cache::put('snapshot:sync:rerun:ssa_pinjaman:2026-07-04', 'default', now()->addMinutes(15));

        $service = new ImportCleanupService();
        $service->finalizeImportedJobSyncDispatch(11, 'ssa_pinjaman', '2026-07-04', 'unit-test');

        Bus::assertDispatchedTimes(SyncImportedReportJob::class, 1);
        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job): bool {
            return $job->tableName === 'ssa_pinjaman'
                && $job->periodHint === '2026-07-04';
        });
        $this->assertNotSame($oldPending, Cache::get('snapshot:sync:pending:ssa_pinjaman:2026-07-04'));
        $this->assertNull(Cache::get('snapshot:sync:rerun:ssa_pinjaman:2026-07-04'));
    }

    public function test_dispatch_imported_job_sync_resolves_nested_daily_loan_period_context(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('id_report')->nullable();
                $table->longText('job_context')->nullable();
                $table->timestamps();
            });
        }

        $jobId = DB::table('import_jobs')->insertGetId([
            'id_report' => 8,
            'job_context' => json_encode([
                'state' => [
                    'params' => [
                        'table_name' => 'daily_loan_dinamis',
                        'backend_detected_periods' => ['10052026'],
                    ],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ImportCleanupService();
        $service->dispatchImportedJobSync((int) $jobId, null, null, 'unit-test');

        Bus::assertDispatched(SyncImportedReportJob::class, function (SyncImportedReportJob $job) use ($jobId): bool {
            return $job->jobId === (int) $jobId
                && $job->tableName === 'daily_loan_dinamis'
                && $job->periodHint === '2026-05-10';
        });
    }
}
