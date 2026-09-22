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
}
