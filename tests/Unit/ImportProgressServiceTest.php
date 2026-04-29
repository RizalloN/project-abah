<?php

namespace Tests\Unit;

use App\Jobs\RunImportJob;
use App\Services\Import\ImportProgressService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                'updated_at' => now()->subMinutes(5)->toDateTimeString(),
            ]);

        Cache::shouldReceive('get')
            ->twice()
            ->andReturn(
                [
                    'message' => 'Masih jalan',
                    'processed_rows' => 40,
                    'total_rows' => 100,
                    'total_success' => 30,
                    'total_failed' => 10,
                    'percent' => 40,
                    'updated_at' => now()->toIso8601String(),
                ],
                []
            );

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

    public function test_get_status_payload_prefers_job_total_rows_when_cached_total_rows_is_zero(): void
    {
        $sampleFile = storage_path('app/private/excel_imports/sample-zero.xlsx');
        if (!is_dir(dirname($sampleFile))) {
            @mkdir(dirname($sampleFile), 0777, true);
        }
        file_put_contents($sampleFile, 'payload');

        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 78,
                'id_report' => 8,
                'file_name' => 'sample-zero.xlsx',
                'folder_path' => dirname($sampleFile),
                'status' => 'processing',
                'total_files' => 326033,
                'total_success' => 0,
                'total_failed' => 0,
                'updated_at' => now()->subMinutes(2)->toDateTimeString(),
            ]);

        Cache::shouldReceive('get')
            ->twice()
            ->andReturn(
                [
                    'message' => 'Menyiapkan sanitasi CSV Daily Loan...',
                    'processed_rows' => 0,
                    'total_rows' => 0,
                    'percent' => 6,
                    'updated_at' => now()->toIso8601String(),
                ],
                []
            );

        try {
            $payload = app(ImportProgressService::class)->getStatusPayload(78);

            $this->assertSame(326033, $payload['total_rows']);
            $this->assertSame(0, $payload['processed_rows']);
            $this->assertSame(6, $payload['percent']);
        } finally {
            if (is_file($sampleFile)) {
                @unlink($sampleFile);
            }
        }
    }

    public function test_mark_failed_removes_matching_queue_job_row(): void
    {
        Cache::shouldReceive('get')->andReturn([]);
        Cache::shouldReceive('put')->andReturnTrue();
        Cache::shouldReceive('forget')->andReturnTrue();

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
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'RunImportJob');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'jobId');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'i:77;');
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

    public function test_mark_failed_sanitizes_runtime_fatal_messages_before_persisting(): void
    {
        Cache::shouldReceive('get')->andReturn([]);
        Cache::shouldReceive('put')->andReturnTrue();
        Cache::shouldReceive('forget')->andReturnTrue();

        $importJobsTable = Mockery::mock();
        $importJobsTable->shouldReceive('where')
            ->once()
            ->with('id', 91)
            ->andReturnSelf();
        $importJobsTable->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $attributes): bool {
                return ($attributes['status'] ?? null) === 'failed'
                    && (int) ($attributes['total_success'] ?? -1) === 0
                    && (int) ($attributes['total_failed'] ?? -1) === 0
                    && array_key_exists('job_fingerprint', $attributes);
            }))
            ->andReturn(1);

        $jobsTable = Mockery::mock();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'RunImportJob');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'jobId');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'i:91;');
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

        app(ImportProgressService::class)->markFailed(91, 'Fatal Error: Undefined variable $jobId (line 4076)');
    }

    public function test_mark_completed_removes_matching_queue_job_row(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('import_job_terminate:88')
            ->andReturnTrue();

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
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'RunImportJob');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'jobId');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'i:88;');
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

    public function test_has_active_processing_jobs_detects_processing_import_rows(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')
            ->once()
            ->with('status', 'processing')
            ->andReturnSelf();
        $query->shouldReceive('exists')
            ->once()
            ->andReturnTrue();

        Schema::shouldReceive('hasTable')
            ->once()
            ->with('import_jobs')
            ->andReturnTrue();
        DB::shouldReceive('table')
            ->once()
            ->with('import_jobs')
            ->andReturn($query);

        $this->assertTrue(app(ImportProgressService::class)->hasActiveProcessingJobs());
    }

    public function test_purge_queued_import_jobs_for_queues_filters_target_queue_rows(): void
    {
        $jobsTable = Mockery::mock();
        $jobsTable->shouldReceive('whereNull')
            ->once()
            ->with('reserved_at')
            ->andReturnSelf();
        $jobsTable->shouldReceive('where')
            ->once()
            ->withArgs(static function (string $column, string $operator, string $value): bool {
                return $column === 'payload'
                    && $operator === 'like'
                    && str_contains($value, 'RunImportJob');
            })
            ->andReturnSelf();
        $jobsTable->shouldReceive('whereIn')
            ->once()
            ->with('queue', ['imports-high'])
            ->andReturnSelf();
        $jobsTable->shouldReceive('delete')->once()->andReturn(2);

        DB::shouldReceive('table')
            ->once()
            ->with('jobs')
            ->andReturn($jobsTable);

        $service = app(ImportProgressService::class);
        $this->assertSame(2, $service->purgeQueuedImportJobsForQueues(['imports-high']));
    }

    public function test_create_job_reuses_active_duplicate_for_same_report_and_file(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->times(5)->andReturnSelf();
        $query->shouldReceive('whereIn')->once()->with('status', ['queued', 'processing'])->andReturnSelf();
        $query->shouldReceive('orderByDesc')->once()->with('updated_at')->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->with(['id', 'status', 'updated_at'])
            ->andReturn(collect([
                (object) [
                    'id' => 321,
                    'status' => 'queued',
                    'updated_at' => now()->toDateTimeString(),
                ],
            ]));

        DB::shouldReceive('table')
            ->once()
            ->with('import_jobs')
            ->andReturn($query);

        $createdId = app(ImportProgressService::class)->createJob([
            'id_report' => 8,
            'file_name' => 'same-file.csv',
            'folder_path' => 'C:\\imports',
            'status' => 'queued',
            'total_files' => 10,
            'total_success' => 0,
            'total_failed' => 0,
            'created_by' => 1,
        ]);

        $this->assertSame(321, $createdId);
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
                ->twice()
                ->andReturn([], []);

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

    public function test_get_status_payload_reconciles_finished_processing_jobs_to_completed_state(): void
    {
        $progressService = Mockery::mock(ImportProgressService::class)->makePartial();
        $sampleFile = storage_path('app/private/excel_imports/finished-job.csv');

        if (!is_dir(dirname($sampleFile))) {
            @mkdir(dirname($sampleFile), 0777, true);
        }
        file_put_contents($sampleFile, 'payload');

        $initialJob = (object) [
            'id' => 77,
            'id_report' => 8,
            'file_name' => basename($sampleFile),
            'folder_path' => dirname($sampleFile),
            'status' => 'processing',
            'total_files' => 323248,
            'total_success' => 323248,
            'total_failed' => 0,
            'updated_at' => now()->toDateTimeString(),
        ];

        $updatedJob = (object) [
            'id' => 77,
            'id_report' => 8,
            'file_name' => basename($sampleFile),
            'folder_path' => dirname($sampleFile),
            'status' => 'completed',
            'total_files' => 323248,
            'total_success' => 323248,
            'total_failed' => 0,
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            Cache::shouldReceive('get')->zeroOrMoreTimes()->andReturn([]);
            Cache::shouldReceive('put')->zeroOrMoreTimes()->andReturnTrue();
            Cache::shouldReceive('forget')->zeroOrMoreTimes()->andReturnTrue();

            $importJobsTable = Mockery::mock();
            $importJobsTable->shouldReceive('where')
                ->times(3)
                ->with('id', 77)
                ->andReturnSelf();
            $importJobsTable->shouldReceive('first')
                ->twice()
                ->andReturn($initialJob, $updatedJob);
            $importJobsTable->shouldReceive('update')
                ->once()
                ->with(Mockery::on(static function (array $attributes): bool {
                    return ($attributes['status'] ?? null) === 'completed'
                        && (int) ($attributes['total_success'] ?? -1) === 323248
                        && (int) ($attributes['total_failed'] ?? -1) === 0;
                }))
                ->andReturn(1);

            $jobsTable = Mockery::mock();
            $jobsTable->shouldReceive('where')
                ->withArgs(static function (string $column, string $operator, string $value): bool {
                    return $column === 'payload'
                        && $operator === 'like'
                        && str_contains($value, 'RunImportJob');
                })
                ->andReturnSelf();
            $jobsTable->shouldReceive('where')
                ->withArgs(static function (string $column, string $operator, string $value): bool {
                    return $column === 'payload'
                        && $operator === 'like'
                        && str_contains($value, 'jobId');
                })
                ->andReturnSelf();
            $jobsTable->shouldReceive('where')
                ->withArgs(static function (string $column, string $operator, string $value): bool {
                    return $column === 'payload'
                        && $operator === 'like'
                        && str_contains($value, 'i:77;');
                })
                ->andReturnSelf();
            $jobsTable->shouldReceive('delete')->once()->andReturn(1);

            DB::shouldReceive('table')
                ->with('import_jobs')
                ->andReturn($importJobsTable, $importJobsTable, $importJobsTable);
            DB::shouldReceive('table')
                ->with('jobs')
                ->andReturn($jobsTable);

            $payload = $progressService->getStatusPayload(77);

            $this->assertSame('completed', $payload['status']);
            $this->assertSame(323248, $payload['total_success']);
            $this->assertSame(0, $payload['total_failed']);
            $this->assertSame(323248, $payload['processed_rows']);
            $this->assertSame(100, $payload['percent']);
            $this->assertNotSame('Import gagal diproses.', $payload['message']);
        } finally {
            if (is_file($sampleFile)) {
                @unlink($sampleFile);
            }
        }
    }

}
