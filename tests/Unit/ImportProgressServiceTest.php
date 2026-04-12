<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Carbon\Carbon;
use Tests\TestCase;

class ImportProgressServiceTest extends TestCase
{
    public function test_get_status_payload_merges_job_and_cached_progress(): void
    {
        $sampleFile = storage_path('app/private/excel_imports/sample.xlsx');
        if (!is_dir(dirname($sampleFile))) {
            @mkdir(dirname($sampleFile), 0777, true);
        }
        file_put_contents($sampleFile, 'payload');

        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 77,
                'id_report' => 8,
                'file_name' => 'sample.xlsx',
                'folder_path' => dirname($sampleFile),
                'status' => 'processing',
                'total_files' => 100,
                'total_success' => 25,
                'total_failed' => 5,
                'updated_at' => '2026-04-11 10:00:00',
            ]);

        Cache::shouldReceive('get')
            ->once()
            ->andReturn([
                'message' => 'Masih jalan',
                'processed_rows' => 40,
                'total_rows' => 100,
                'total_success' => 30,
                'total_failed' => 10,
                'percent' => 40,
                'updated_at' => '2026-04-11T10:00:00+07:00',
            ]);

        try {
            $payload = app(ImportProgressService::class)->getStatusPayload(77);

            $this->assertSame('processing', $payload['status']);
            $this->assertSame(77, $payload['job_id']);
            $this->assertSame(8, $payload['report_id']);
            $this->assertSame(100, $payload['total_rows']);
            $this->assertSame(40, $payload['processed_rows']);
            $this->assertSame(30, $payload['total_success']);
            $this->assertSame(10, $payload['total_failed']);
            $this->assertSame(40, $payload['percent']);
            $this->assertSame('Masih jalan', $payload['message']);
        } finally {
            if (is_file($sampleFile)) {
                @unlink($sampleFile);
            }
        }
    }

    public function test_mark_failed_removes_matching_queue_job_row(): void
    {
        Cache::shouldReceive('put')->once()->andReturnTrue();

        $importJobsTable = Mockery::mock();
        $importJobsTable->shouldReceive('where')
            ->once()
            ->with('id', 77)
            ->andReturnSelf();
        $importJobsTable->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $attributes): bool {
                return ($attributes['status'] ?? null) === 'failed'
                    && (int) ($attributes['total_success'] ?? -1) === 0
                    && (int) ($attributes['total_failed'] ?? -1) === 0;
            }))
            ->andReturn(1);

        $jobsTable = Mockery::mock();
        $jobsTable->shouldReceive('whereRaw')
            ->once()
            ->withArgs(static function (string $sql, array $bindings): bool {
                return str_contains($sql, "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.commandName')) = ?")
                    && $bindings === [RunImportJob::class];
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('whereRaw')
            ->once()
            ->withArgs(static function (string $sql, array $bindings): bool {
                return str_contains($sql, "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.command')) LIKE ?")
                    && $bindings === ['%jobId";i:77;%'];
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('delete')->once()->andReturn(1);

        DB::shouldReceive('table')
            ->once()
            ->with('import_jobs')
            ->andReturn($importJobsTable);
        DB::shouldReceive('table')
            ->once()
            ->with('jobs')
            ->andReturn($jobsTable);

        app(ImportProgressService::class)->markFailed(77, 'gagal');
    }

    public function test_mark_completed_removes_matching_queue_job_row(): void
    {
        $importJobsTable = Mockery::mock();
        $importJobsTable->shouldReceive('where')
            ->once()
            ->with('id', 88)
            ->andReturnSelf();
        $importJobsTable->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $attributes): bool {
                return ($attributes['status'] ?? null) === 'completed'
                    && (int) ($attributes['total_success'] ?? -1) === 50
                    && (int) ($attributes['total_failed'] ?? -1) === 0
                    && (int) ($attributes['total_files'] ?? -1) === 50;
            }))
            ->andReturn(1);

        $jobsTable = Mockery::mock();
        $jobsTable->shouldReceive('whereRaw')
            ->once()
            ->withArgs(static function (string $sql, array $bindings): bool {
                return str_contains($sql, "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.commandName')) = ?")
                    && $bindings === [RunImportJob::class];
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('whereRaw')
            ->once()
            ->withArgs(static function (string $sql, array $bindings): bool {
                return str_contains($sql, "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.data.command')) LIKE ?")
                    && $bindings === ['%jobId";i:88;%'];
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('delete')->once()->andReturn(1);

        DB::shouldReceive('table')
            ->once()
            ->with('import_jobs')
            ->andReturn($importJobsTable);
        DB::shouldReceive('table')
            ->once()
            ->with('jobs')
            ->andReturn($jobsTable);

        app(ImportProgressService::class)->markCompleted(88, 50, 0, 50);
    }

    public function test_get_status_payload_reconciles_stale_queued_jobs_to_failed_state(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class)->makePartial();
        $stagedFile = storage_path('app/private/excel_imports/stale-job.csv');

        if (!is_dir(dirname($stagedFile))) {
            @mkdir(dirname($stagedFile), 0777, true);
        }
        file_put_contents($stagedFile, 'payload');

        try {
            DB::shouldReceive('table->where->first')
                ->once()
                ->andReturn((object) [
                    'id' => 55,
                    'id_report' => 8,
                    'file_name' => basename($stagedFile),
                    'folder_path' => dirname($stagedFile),
                    'status' => 'queued',
                    'total_files' => 100,
                    'total_success' => 0,
                    'total_failed' => 0,
                    'updated_at' => Carbon::now()->subMinutes(30)->toDateTimeString(),
                ]);

            Cache::shouldReceive('get')
                ->once()
                ->andReturn([]);

            $progressService->shouldReceive('markFailed')
                ->once()
                ->with(
                    55,
                    Mockery::on(static fn (string $message): bool => str_contains($message, 'terlalu lama berada di antrian')),
                    0,
                    0,
                    'failed'
                );

            $progressService->shouldReceive('findJob')
                ->once()
                ->with(55)
                ->andReturn((object) [
                    'id' => 55,
                    'id_report' => 8,
                    'file_name' => basename($stagedFile),
                    'folder_path' => dirname($stagedFile),
                    'status' => 'failed',
                    'total_files' => 100,
                    'total_success' => 0,
                    'total_failed' => 0,
                    'updated_at' => now()->toDateTimeString(),
                ]);

            $payload = $progressService->getStatusPayload(55);

            $this->assertSame('failed', $payload['status']);
            $this->assertSame(55, $payload['job_id']);
            $this->assertSame('Import sedang diproses.', $payload['message']);
        } finally {
            if (is_file($stagedFile)) {
                @unlink($stagedFile);
            }
        }
    }

}
