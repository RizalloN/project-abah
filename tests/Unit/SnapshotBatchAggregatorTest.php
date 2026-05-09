<?php

namespace Tests\Unit;

use App\Jobs\ExecuteBatchedSnapshotJob;
use App\Services\Import\ImportProgressService;
use App\Support\SnapshotBatchAggregator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class SnapshotBatchAggregatorTest extends TestCase
{
    public function test_active_batches_are_read_from_registry_without_cache_enumeration(): void
    {
        Cache::flush();

        Cache::put('snapshot:batch:daily_loan_dinamis:2026-04-20', [
            'batch_key' => 'daily_loan_dinamis:2026-04-20',
            'table_name' => 'daily_loan_dinamis',
            'period_hint' => '2026-04-20',
            'first_requested_at' => now()->toIso8601String(),
            'last_updated_at' => now()->toIso8601String(),
            'request_count' => 1,
            'requests' => [],
        ], now()->addMinute());
        Cache::put('snapshot:batch:active_keys', ['daily_loan_dinamis:2026-04-20'], now()->addMinute());

        $batches = (new SnapshotBatchAggregator())->getActiveBatches();

        $this->assertArrayHasKey('daily_loan_dinamis:2026-04-20', $batches);
        $this->assertSame('daily_loan_dinamis', $batches['daily_loan_dinamis:2026-04-20']['table_name']);
    }

    public function test_flush_due_batches_prunes_stale_registry_keys(): void
    {
        Cache::flush();

        Cache::put('snapshot:batch:active_keys', ['missing:batch'], now()->addMinute());

        $flushed = (new SnapshotBatchAggregator())->flushDueBatches();

        $this->assertSame([], $flushed);
        $this->assertNull(Cache::get('snapshot:batch:active_keys'));
    }

    public function test_reset_active_batches_clears_registry_and_batch_payloads(): void
    {
        Cache::flush();

        Cache::put('snapshot:batch:daily_loan_dinamis:2026-04-20', ['requests' => [['table_name' => 'daily_loan_dinamis']]], now()->addMinute());
        Cache::put('snapshot:batch:active_keys', ['daily_loan_dinamis:2026-04-20'], now()->addMinute());

        $count = (new SnapshotBatchAggregator())->resetActiveBatches();

        $this->assertSame(1, $count);
        $this->assertNull(Cache::get('snapshot:batch:daily_loan_dinamis:2026-04-20'));
        $this->assertNull(Cache::get('snapshot:batch:active_keys'));
    }

    public function test_flush_batch_is_deferred_when_an_import_is_active(): void
    {
        Cache::flush();
        Bus::fake();

        Cache::put('snapshot:batch:daily_loan_dinamis:2026-04-20', [
            'batch_key' => 'daily_loan_dinamis:2026-04-20',
            'table_name' => 'daily_loan_dinamis',
            'period_hint' => '2026-04-20',
            'first_requested_at' => now()->toIso8601String(),
            'last_updated_at' => now()->toIso8601String(),
            'request_count' => 1,
            'requests' => [
                [
                    'table_name' => 'daily_loan_dinamis',
                    'period_hint' => '2026-04-20',
                    'job_id' => null,
                    'source' => 'unit-test',
                    'rebuild_id' => null,
                    'requested_at' => now()->toIso8601String(),
                ],
            ],
        ], now()->addMinute());
        Cache::put('snapshot:batch:active_keys', ['daily_loan_dinamis:2026-04-20'], now()->addMinute());

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnTrue();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $result = (new SnapshotBatchAggregator())->flushBatch('daily_loan_dinamis:2026-04-20');

        $this->assertSame('import_active', $result['reason']);
        $this->assertSame('daily_loan_dinamis:2026-04-20', $result['batch_key']);
        $this->assertNotNull(Cache::get('snapshot:batch:daily_loan_dinamis:2026-04-20'));
        Bus::assertNotDispatched(ExecuteBatchedSnapshotJob::class);
    }

    public function test_flush_batch_compacts_duplicate_snapshot_requests_before_dispatch(): void
    {
        Cache::flush();
        Bus::fake();

        Cache::put('snapshot:batch:simpanan_multipn:2026-04-30', [
            'batch_key' => 'simpanan_multipn:2026-04-30',
            'table_name' => 'simpanan_multipn',
            'period_hint' => '2026-04-30',
            'first_requested_at' => now()->toIso8601String(),
            'last_updated_at' => now()->toIso8601String(),
            'request_count' => 2,
            'requests' => [
                [
                    'table_name' => 'simpanan_multipn',
                    'period_hint' => '2026-04-30',
                    'job_id' => 10,
                    'source' => 'first',
                    'rebuild_id' => null,
                    'requested_at' => now()->subSecond()->toIso8601String(),
                ],
                [
                    'table_name' => 'simpanan_multipn',
                    'period_hint' => '2026-04-30',
                    'job_id' => 11,
                    'source' => 'second',
                    'rebuild_id' => null,
                    'requested_at' => now()->toIso8601String(),
                ],
            ],
        ], now()->addMinute());
        Cache::put('snapshot:batch:active_keys', ['simpanan_multipn:2026-04-30'], now()->addMinute());

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnFalse();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $result = (new SnapshotBatchAggregator())->flushBatch('simpanan_multipn:2026-04-30');

        $this->assertTrue($result['flushed']);
        $this->assertSame(1, $result['request_count']);
        Bus::assertDispatched(ExecuteBatchedSnapshotJob::class, function (ExecuteBatchedSnapshotJob $job): bool {
            return count($job->requests) === 1
                && $job->requests[0]['job_id'] === 11
                && $job->requests[0]['source'] === 'second';
        });
    }

    public function test_flush_batch_compacts_duplicate_snapshot_requests_even_when_rebuild_ids_differ(): void
    {
        Cache::flush();
        Bus::fake();

        Cache::put('snapshot:batch:simpanan_multipn:2026-04-30', [
            'batch_key' => 'simpanan_multipn:2026-04-30',
            'table_name' => 'simpanan_multipn',
            'period_hint' => '2026-04-30',
            'first_requested_at' => now()->toIso8601String(),
            'last_updated_at' => now()->toIso8601String(),
            'request_count' => 2,
            'requests' => [
                [
                    'table_name' => 'simpanan_multipn',
                    'period_hint' => '2026-04-30',
                    'job_id' => 10,
                    'source' => 'first',
                    'rebuild_id' => 'rebuild-old',
                    'requested_at' => now()->subSecond()->toIso8601String(),
                ],
                [
                    'table_name' => 'simpanan_multipn',
                    'period_hint' => '2026-04-30',
                    'job_id' => 11,
                    'source' => 'second',
                    'rebuild_id' => 'rebuild-new',
                    'requested_at' => now()->toIso8601String(),
                ],
            ],
        ], now()->addMinute());
        Cache::put('snapshot:batch:active_keys', ['simpanan_multipn:2026-04-30'], now()->addMinute());

        $importProgressService = Mockery::mock(ImportProgressService::class);
        $importProgressService->shouldReceive('hasActiveProcessingJobs')
            ->once()
            ->andReturnFalse();
        $this->app->instance(ImportProgressService::class, $importProgressService);

        $result = (new SnapshotBatchAggregator())->flushBatch('simpanan_multipn:2026-04-30');

        $this->assertTrue($result['flushed']);
        $this->assertSame(1, $result['request_count']);
        Bus::assertDispatched(ExecuteBatchedSnapshotJob::class, function (ExecuteBatchedSnapshotJob $job): bool {
            return count($job->requests) === 1
                && $job->requests[0]['job_id'] === 11
                && $job->requests[0]['source'] === 'second'
                && $job->requests[0]['rebuild_id'] === 'rebuild-new';
        });
    }
}
