<?php

namespace Tests\Unit;

use App\Jobs\EnsureImportedSnapshotsFreshJob;
use App\Services\Import\ImportProgressService;
use App\Services\Snapshot\SnapshotAuditAndHealService;
use App\Support\SnapshotAuditCoordinator;
use App\Support\SnapshotAuditService;
use App\Support\SnapshotIntegrityGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SnapshotAuditAndHealTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('daily_loan_dinamis')) {
            Schema::create('daily_loan_dinamis', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('periode')->nullable();
                $table->decimal('baki_debet1', 20, 2)->nullable();
                $table->string('nomor_rekening1')->nullable();
            });
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('daily_loan_dinamis');
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    public function test_audit_yields_when_active_imports_exist(): void
    {
        Queue::fake();

        $importService = Mockery::mock(ImportProgressService::class);
        $importService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturn(true);

        $coordinator = Mockery::mock(SnapshotAuditCoordinator::class);
        $coordinator->shouldNotReceive('runAudit');

        $auditService = Mockery::mock(SnapshotAuditService::class);
        $guard = Mockery::mock(SnapshotIntegrityGuard::class);

        $service = new SnapshotAuditAndHealService(
            $importService,
            $coordinator,
            $auditService,
            $guard
        );

        $result = $service->auditAndHealAll();

        $this->assertSame('yielded', $result['status']);
        $this->assertSame(0, $result['tables_checked']);
        $this->assertSame(0, $result['rebuilds_dispatched']);
        Queue::assertNothingPushed();
    }

    public function test_audit_table_yields_when_table_has_active_import(): void
    {
        Queue::fake();

        $importService = Mockery::mock(ImportProgressService::class);
        $importService->shouldReceive('hasActiveProcessingJobsForTable')
            ->with('daily_loan_dinamis')
            ->andReturn(true);

        $coordinator = Mockery::mock(SnapshotAuditCoordinator::class);
        $coordinator->shouldNotReceive('runAudit');

        $auditService = Mockery::mock(SnapshotAuditService::class);
        $guard = Mockery::mock(SnapshotIntegrityGuard::class);

        $service = new SnapshotAuditAndHealService(
            $importService,
            $coordinator,
            $auditService,
            $guard
        );

        $result = $service->auditAndHealTable('daily_loan_dinamis');

        $this->assertSame('yielded', $result['status']);
        $this->assertSame(0, $result['rebuilds_dispatched']);
        Queue::assertNothingPushed();
    }

    public function test_audit_dispatches_rebuild_when_discrepancies_exist(): void
    {
        Queue::fake();

        $importService = Mockery::mock(ImportProgressService::class);
        $importService->shouldReceive('hasActiveProcessingJobsForTable')
            ->with('daily_loan_dinamis')
            ->andReturn(false);

        $coordinator = Mockery::mock(SnapshotAuditCoordinator::class);
        $coordinator->shouldReceive('runAudit')
            ->with('daily_loan_dinamis', null)
            ->andReturn([
                'status' => 'has_discrepancies',
                'table_name' => 'daily_loan_dinamis',
                'discrepancies' => [
                    ['period' => '2026-09-20', 'differences' => [['metric' => 'total_balance']]],
                ],
            ]);

        $auditService = Mockery::mock(SnapshotAuditService::class);
        $guard = Mockery::mock(SnapshotIntegrityGuard::class);
        $guard->shouldReceive('inspectTable')
            ->andReturn([]);

        $service = new SnapshotAuditAndHealService(
            $importService,
            $coordinator,
            $auditService,
            $guard
        );

        $result = $service->auditAndHealTable('daily_loan_dinamis');

        $this->assertSame('discrepancies_detected', $result['status']);
        $this->assertSame(1, $result['discrepancies_found']);
        $this->assertSame(1, $result['rebuilds_dispatched']);
        $this->assertEquals(['2026-09-20'], $result['affected_periods']);

        Queue::assertPushed(EnsureImportedSnapshotsFreshJob::class, function ($job) {
            $ref = new \ReflectionClass($job);
            $propTable = $ref->getProperty('tableName');
            $propTable->setAccessible(true);
            $propPeriod = $ref->getProperty('periodHint');
            $propPeriod->setAccessible(true);

            return $propTable->getValue($job) === 'daily_loan_dinamis'
                && $propPeriod->getValue($job) === '2026-09-20';
        });
    }

    public function test_rebuild_dispatch_is_throttled(): void
    {
        Queue::fake();

        $importService = Mockery::mock(ImportProgressService::class);
        $importService->shouldReceive('hasActiveProcessingJobsForTable')
            ->with('daily_loan_dinamis')
            ->andReturn(false);

        $coordinator = Mockery::mock(SnapshotAuditCoordinator::class);
        $coordinator->shouldReceive('runAudit')
            ->with('daily_loan_dinamis', null)
            ->andReturn([
                'status' => 'has_discrepancies',
                'table_name' => 'daily_loan_dinamis',
                'discrepancies' => [
                    ['period' => '2026-09-20', 'differences' => [['metric' => 'total_balance']]],
                ],
            ]);

        $auditService = Mockery::mock(SnapshotAuditService::class);
        $guard = Mockery::mock(SnapshotIntegrityGuard::class);
        $guard->shouldReceive('inspectTable')->andReturn([]);

        // Pre-lock in cache
        Cache::put('snapshot:auto-heal:lock:daily_loan_dinamis:2026-09-20', time(), 300);

        $service = new SnapshotAuditAndHealService(
            $importService,
            $coordinator,
            $auditService,
            $guard
        );

        $result = $service->auditAndHealTable('daily_loan_dinamis');

        $this->assertSame(1, $result['discrepancies_found']);
        $this->assertSame(0, $result['rebuilds_dispatched'], 'Throttle must suppress second rebuild dispatch');
        Queue::assertNothingPushed();
    }

    public function test_verified_clean_period_is_not_reaudited_until_data_version_changes(): void
    {
        Queue::fake();
        \Illuminate\Support\Facades\DB::table('daily_loan_dinamis')->insert([
            ['periode' => '2026-10-07', 'baki_debet1' => 10, 'nomor_rekening1' => 'A'],
            ['periode' => '2026-10-08', 'baki_debet1' => 20, 'nomor_rekening1' => 'B'],
        ]);

        $importService = Mockery::mock(ImportProgressService::class);
        $importService->shouldReceive('hasActiveProcessingJobsForTable')->andReturn(false);

        $audited = [];
        $coordinator = Mockery::mock(SnapshotAuditCoordinator::class);
        $coordinator->shouldReceive('runAudit')
            ->andReturnUsing(function (string $table, ?string $period) use (&$audited): array {
                $audited[] = $period;

                return $period === '2026-10-07'
                    ? ['status' => 'error', 'discrepancies' => []]
                    : ['status' => 'clean', 'discrepancies' => []];
            });

        $guard = Mockery::mock(SnapshotIntegrityGuard::class);
        $guard->shouldReceive('inspectTable')->andReturn([]);

        $service = new SnapshotAuditAndHealService(
            $importService,
            $coordinator,
            Mockery::mock(SnapshotAuditService::class),
            $guard
        );

        $service->auditAndHealTable('daily_loan_dinamis', null, false, 5);
        $this->assertSame(['2026-10-08', '2026-10-07'], $audited);

        // The clean period is skipped; the errored one is retried.
        $audited = [];
        $second = $service->auditAndHealTable('daily_loan_dinamis', null, false, 5);
        $this->assertSame(['2026-10-07'], $audited);
        $this->assertSame(1, $second['periods_skipped_clean']);

        // A new import bumps the version, so every period is audited again.
        \App\Support\ReportCacheVersion::bump('pinjaman');
        $audited = [];
        $service->auditAndHealTable('daily_loan_dinamis', null, false, 5);
        $this->assertSame(['2026-10-08', '2026-10-07'], $audited);

        // Forced audits never use the clean marker.
        $audited = [];
        $service->auditAndHealTable('daily_loan_dinamis', null, true, 5);
        $this->assertSame(['2026-10-08', '2026-10-07'], $audited);
    }
}
