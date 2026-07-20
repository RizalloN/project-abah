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

    public function test_get_status_payload_uses_database_totals_for_terminal_jobs(): void
    {
        DB::shouldReceive('table->where->first')
            ->once()
            ->andReturn((object) [
                'id' => 79,
                'id_report' => 17,
                'file_name' => 'stale-ssa.xlsx',
                'folder_path' => storage_path('app/private/excel_imports'),
                'status' => 'completed',
                'total_files' => 0,
                'total_success' => 0,
                'total_failed' => 0,
                'updated_at' => now()->subMinutes(1)->toDateTimeString(),
            ]);

        Cache::shouldReceive('get')
            ->twice()
            ->andReturn(
                [
                    'message' => 'Import selesai diproses.',
                    'processed_rows' => 1069,
                    'total_rows' => 1069,
                    'total_success' => 1069,
                    'total_failed' => 0,
                    'percent' => 100,
                    'updated_at' => now()->toIso8601String(),
                ],
                []
            );

        $payload = app(ImportProgressService::class)->getStatusPayload(79);

        $this->assertSame('completed', $payload['status']);
        $this->assertSame(0, $payload['total_rows']);
        $this->assertSame(0, $payload['processed_rows']);
        $this->assertSame(0, $payload['total_success']);
        $this->assertSame(0, $payload['total_failed']);
        $this->assertSame(0, $payload['percent']);
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
        $query->shouldReceive('whereIn')
            ->once()
            ->with('status', ['staging', 'processing'])
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
        $query->shouldReceive('whereIn')->once()->with('status', ['queued', 'staging', 'processing'])->andReturnSelf();
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

    public function test_get_status_payload_recovers_completed_direct_load_audit(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function ($table): void {
                $table->id();
                $table->unsignedInteger('id_report')->nullable();
                $table->string('file_name')->nullable();
                $table->string('folder_path')->nullable();
                $table->string('status')->nullable();
                $table->unsignedInteger('total_files')->default(0);
                $table->unsignedInteger('total_success')->default(0);
                $table->unsignedInteger('total_failed')->default(0);
                $table->string('message')->nullable();
                $table->json('job_context')->nullable();
                $table->string('job_fingerprint')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function ($table): void {
                $table->id();
                $table->string('queue')->nullable();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        DB::table('import_jobs')->insert([
            'id' => 43,
            'id_report' => 9,
            'file_name' => 'simpanan.txt',
            'folder_path' => storage_path('app/private/excel_imports'),
            'status' => 'processing',
            'total_files' => 545909,
            'total_success' => 0,
            'total_failed' => 0,
            'job_context' => json_encode([
                'table_name' => 'simpanan_multipn',
                'direct_load_audit' => [
                    'source_rows' => 545909,
                    'load_inserted_rows' => 545907,
                    'insert_shortfall' => 2,
                    'total_rows' => 545909,
                    'total_success' => 545907,
                    'total_failed' => 2,
                    'completed_at' => now()->toDateTimeString(),
                ],
            ]),
            'job_fingerprint' => 'abc',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = app(ImportProgressService::class)->getStatusPayload(43);
        $job = DB::table('import_jobs')->where('id', 43)->first();

        $this->assertSame('failed_partial', $payload['status']);
        $this->assertSame(545909, $payload['processed_rows']);
        $this->assertSame(545907, $payload['total_success']);
        $this->assertSame(2, $payload['total_failed']);
        $this->assertSame('failed_partial', $job->status);
        $this->assertSame(545907, (int) $job->total_success);
        $this->assertSame(2, (int) $job->total_failed);
        $this->assertNull($job->job_fingerprint);
    }

    public function test_detects_orphaned_simpanan_multipn_load_when_mysql_thread_is_gone(): void
    {
        config()->set('import.direct_load.simpanan_multipn.orphan_grace_seconds', 60);
        DB::shouldReceive('select')
            ->once()
            ->with('SHOW FULL PROCESSLIST')
            ->andReturn([]);

        $job = (object) [
            'id' => 249,
            'job_context' => json_encode([
                'table_name' => 'simpanan_multipn',
                'mysql_thread_id' => 958,
                'direct_load_started_at' => now()->subMinutes(5)->toIso8601String(),
            ]),
        ];

        $service = app(ImportProgressService::class);
        $method = new \ReflectionMethod($service, 'isOrphanedSimpananMultiPnDirectLoad');

        $this->assertTrue($method->invoke($service, $job));
    }

    public function test_keeps_simpanan_multipn_processing_while_mysql_thread_is_alive(): void
    {
        config()->set('import.direct_load.simpanan_multipn.orphan_grace_seconds', 60);
        DB::shouldReceive('select')
            ->once()
            ->with('SHOW FULL PROCESSLIST')
            ->andReturn([(object) ['Id' => 958]]);

        $job = (object) [
            'id' => 249,
            'job_context' => json_encode([
                'table_name' => 'simpanan_multipn',
                'mysql_thread_id' => 958,
                'direct_load_started_at' => now()->subMinutes(5)->toIso8601String(),
            ]),
        ];

        $service = app(ImportProgressService::class);
        $method = new \ReflectionMethod($service, 'isOrphanedSimpananMultiPnDirectLoad');

        $this->assertFalse($method->invoke($service, $job));
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
            $this->assertSame('Import gagal diproses.', $payload['message']);
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
